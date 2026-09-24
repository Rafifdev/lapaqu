<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  Store,
  Phone,
  MapPin,
  CircleCheck,
  CircleX,
  Plus,
  Pencil,
  Copy,
  Check,
  RefreshCw,
  Clock,
  Trash2,
  Unlink,
  AlertCircle
} from 'lucide-vue-next'
import QRCode from 'qrcode'
import apiClient from '@/services/api'
import { useNotyf } from '@/composables/useNotyf'
import { useDashboardI18n } from '@/i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppToggle from '@/components/ui/AppToggle.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

export interface OutletItem {
  id: string
  name: string
  address: string | null
  phone: string | null
  timezone: string
  is_active: boolean
  is_main?: boolean
  tables_count?: number
  users_count?: number
  created_at?: string
  updated_at?: string
}

const notyf = useNotyf()
const { t, translate } = useDashboardI18n()

// State
const outlets = ref<OutletItem[]>([])
const isLoading = ref(true)

// Fetch Outlets from Backend
const fetchOutlets = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/outlets')
    const list = res.data?.outlets || []
    outlets.value = list.map((item: OutletItem, idx: number) => ({
      ...item,
      is_main: typeof item.is_main === 'boolean' ? item.is_main : (idx === 0 || item.name.toLowerCase().includes('utama'))
    }))
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal memuat daftar outlet')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchOutlets()
})

onBeforeUnmount(() => {
  stopCountdown()
})

// ----------------------------------------------------
// MODAL: PAIRING DEVICE KASIR & DAPUR (HUBUNGKAN)
// ----------------------------------------------------
const isPairingModalOpen = ref(false)
const selectedOutletForPairing = ref<OutletItem | null>(null)
const pairingCode = ref<string>('')
const pairingExpiresAt = ref<string | null>(null)
const pairingQrUrl = ref<string>('')
const isPairingLoading = ref(false)
const remainingSeconds = ref<number>(0)
let countdownTimer: ReturnType<typeof setInterval> | null = null
const isCopied = ref(false)

interface ConnectedDevice {
  id: string
  user_id?: string
  user_name?: string
  staff_name?: string
  role?: string
  code: string
  device_name: string
  status: string
  connected_at: string
}

const connectedDevices = ref<ConnectedDevice[]>([])
const isLoadingDevices = ref(false)
const disconnectingDeviceId = ref<string | null>(null)

// Pagination Perangkat Terhubung (Maksimal 3 item per halaman sejajar judul)
const devicePage = ref(1)
const devicePageSize = 3
const totalDevicePages = computed(() => Math.ceil(connectedDevices.value.length / devicePageSize) || 1)
const paginatedConnectedDevices = computed(() => {
  const start = (devicePage.value - 1) * devicePageSize
  return connectedDevices.value.slice(start, start + devicePageSize)
})



const fetchConnectedDevices = async (outletId: string) => {
  isLoadingDevices.value = true
  devicePage.value = 1
  try {
    const res = await apiClient.get(`/outlets/${outletId}/devices`)
    connectedDevices.value = res.data?.devices || []
  } catch {
    connectedDevices.value = []
  } finally {
    isLoadingDevices.value = false
  }
}

const formatDate = (dateStr?: string) => {
  if (!dateStr || dateStr === '-') return '-'
  if (/^\d{2}\/\d{2}\/\d{2}$/.test(dateStr)) return dateStr
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = String(d.getFullYear()).slice(-2)
  return `${day}/${month}/${year}`
}

const handleDisconnectDevice = async (deviceId: string) => {
  if (!selectedOutletForPairing.value) return
  disconnectingDeviceId.value = deviceId
  try {
    await apiClient.delete(`/outlets/${selectedOutletForPairing.value.id}/devices/${deviceId}`)
    connectedDevices.value = connectedDevices.value.filter(d => d.id !== deviceId)
    if (devicePage.value > totalDevicePages.value) {
      devicePage.value = Math.max(1, totalDevicePages.value)
    }
    notyf.success('Perangkat berhasil diputuskan.')
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal memutuskan perangkat.')
  } finally {
    disconnectingDeviceId.value = null
  }
}

const openPairingModal = async (outlet: OutletItem) => {
  selectedOutletForPairing.value = outlet
  isPairingModalOpen.value = true
  await Promise.all([
    loadOrGeneratePairingCode(outlet.id),
    fetchConnectedDevices(outlet.id)
  ])
}

const loadOrGeneratePairingCode = async (outletId: string, forceNew = false) => {
  isPairingLoading.value = true
  stopCountdown()
  try {
    if (!forceNew) {
      const checkRes = await apiClient.get(`/outlets/${outletId}/pairing-code`)
      if (checkRes.data?.has_active_code && checkRes.data?.pairing_code) {
        pairingCode.value = checkRes.data.pairing_code
        pairingExpiresAt.value = checkRes.data.expires_at
        await generatePairingQr(checkRes.data.pairing_code)
        startCountdown(checkRes.data.expires_at)
        return
      }
    }

    const genRes = await apiClient.post(`/outlets/${outletId}/pairing-code`)
    pairingCode.value = genRes.data?.pairing_code
    pairingExpiresAt.value = genRes.data?.expires_at
    await generatePairingQr(genRes.data?.pairing_code)
    startCountdown(genRes.data?.expires_at)
    if (forceNew) {
      notyf.success('Kode pairing baru berhasil digenerate.')
    }
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal memproses kode pairing.')
  } finally {
    isPairingLoading.value = false
  }
}

const generatePairingQr = async (code: string) => {
  try {
    const origin = window.location.origin
    const pairingUrl = `${origin}/outlet/login?code=${encodeURIComponent(code)}`
    pairingQrUrl.value = await QRCode.toDataURL(pairingUrl, {
      width: 200,
      margin: 1.5,
      color: {
        dark: '#1E293B',
        light: '#FFFFFF',
      },
    })
  } catch {
    pairingQrUrl.value = ''
  }
}

const startCountdown = (expiresAtStr: string | null) => {
  stopCountdown()
  if (!expiresAtStr) return

  const target = new Date(expiresAtStr).getTime()
  const update = () => {
    const now = Date.now()
    const diff = Math.max(0, Math.floor((target - now) / 1000))
    remainingSeconds.value = diff
    if (diff <= 0) {
      stopCountdown()
    }
  }

  update()
  countdownTimer = setInterval(update, 1000)
}

const stopCountdown = () => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
}

const formattedCountdown = computed(() => {
  const m = Math.floor(remainingSeconds.value / 60)
  const s = remainingSeconds.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const copyPairingCode = async () => {
  if (!pairingCode.value) return
  try {
    await navigator.clipboard.writeText(pairingCode.value)
    isCopied.value = true
    notyf.success('Kode pairing berhasil disalin ke clipboard.')
    setTimeout(() => {
      isCopied.value = false
    }, 2500)
  } catch {
    notyf.error('Gagal menyalin kode.')
  }
}

// ----------------------------------------------------
// MODAL: FORM TAMBAH / EDIT OUTLET
// ----------------------------------------------------
const isFormModalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<string | null>(null)
const isSubmitting = ref(false)
const modalErrorMessage = ref('')

const formName = ref('')
const formAddress = ref('')
const formPhone = ref('')
const formTimezone = ref('Asia/Jakarta')
const formIsActive = ref(true)
const formIsMain = ref(false)

const timezoneOptions = [
  { value: 'Asia/Jakarta', label: 'WIB - Waktu Indonesia Barat' },
  { value: 'Asia/Makassar', label: 'WITA - Waktu Indonesia Tengah' },
  { value: 'Asia/Jayapura', label: 'WIT - Waktu Indonesia Timur' },
]

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  modalErrorMessage.value = ''
  formName.value = ''
  formAddress.value = ''
  formPhone.value = ''
  formTimezone.value = 'Asia/Jakarta'
  formIsActive.value = true
  formIsMain.value = outlets.value.length === 0
  isFormModalOpen.value = true
}

const openEditModal = (outlet: OutletItem) => {
  isEditing.value = true
  editingId.value = outlet.id
  modalErrorMessage.value = ''
  formName.value = outlet.name
  formAddress.value = outlet.address || ''
  formPhone.value = outlet.phone || ''
  formTimezone.value = outlet.timezone || 'Asia/Jakarta'
  formIsActive.value = outlet.is_active
  formIsMain.value = Boolean(outlet.is_main)
  isFormModalOpen.value = true
}

const handleSubmitForm = async () => {
  modalErrorMessage.value = ''

  if (!formName.value.trim()) {
    modalErrorMessage.value = 'Nama outlet wajib diisi.'
    return
  }

  isSubmitting.value = true
  try {
    if (isEditing.value && editingId.value) {
      await apiClient.put(`/outlets/${editingId.value}`, {
        name: formName.value.trim(),
        address: formAddress.value.trim() || null,
        phone: formPhone.value.trim() || null,
        timezone: formTimezone.value,
        is_active: formIsActive.value,
        is_main: formIsMain.value,
      })
      notyf.success('Data outlet berhasil diperbarui.')
    } else {
      await apiClient.post('/outlets', {
        name: formName.value.trim(),
        address: formAddress.value.trim() || null,
        phone: formPhone.value.trim() || null,
        timezone: formTimezone.value,
        is_main: formIsMain.value,
      })
      notyf.success('Outlet baru berhasil ditambahkan.')
    }

    isFormModalOpen.value = false
    await fetchOutlets()
  } catch (err: any) {
    modalErrorMessage.value = err.response?.data?.message || 'Gagal menyimpan data outlet.'
  } finally {
    isSubmitting.value = false
  }
}

// ----------------------------------------------------
// MODAL: HAPUS CABANG
// ----------------------------------------------------
const isDeleteModalOpen = ref(false)
const outletToDelete = ref<OutletItem | null>(null)
const isDeleting = ref(false)

const openDeleteModal = (outlet: OutletItem) => {
  outletToDelete.value = outlet
  isDeleteModalOpen.value = true
}

const handleDeleteOutlet = async () => {
  if (!outletToDelete.value) return

  isDeleting.value = true
  try {
    await apiClient.delete(`/outlets/${outletToDelete.value.id}`)
    notyf.success(`Outlet ${outletToDelete.value.name} berhasil dihapus.`)
    isDeleteModalOpen.value = false
    outletToDelete.value = null
    await fetchOutlets()
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal menghapus outlet')
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header Halaman: Judul & Tombol Tambah Cabang -->
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
          Outlet
        </h1>
      </div>

      <AppButton variant="primary" size="md" @click="openCreateModal" class="shadow-sm font-bold">
        <template #prefix>
          <Plus class="w-4 h-4" />
        </template>
        {{ t('outlets.addOutlet', 'Tambah Outlet Baru') }}
      </AppButton>
    </div>

    <!-- Skeleton Loading (4 columns on xl) -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="n in 4" :key="n"
        class="bg-white dark:bg-[#273142] rounded-xl p-4 border border-[#E8E8E8] dark:border-[#313D4F] space-y-4 animate-pulse">
        <div class="flex items-start justify-between">
          <div class="w-12 h-12 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
          <div class="flex items-center gap-1.5">
            <div class="w-20 h-8 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
            <div class="w-8 h-8 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
          </div>
        </div>
        <div class="space-y-2 pt-1">
          <div class="h-4 w-28 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
          <div class="h-3 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="outlets.length === 0"
      class="bg-white dark:bg-[#273142] rounded-xl p-12 text-center border border-[#E8E8E8] dark:border-[#313D4F] flex flex-col items-center justify-center space-y-3">
      <div
        class="w-14 h-14 rounded-lg bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] text-[#1E293B] dark:text-white flex items-center justify-center shadow-xs">
        <Store class="w-6 h-6" />
      </div>
      <h3 class="text-base font-bold text-[#1E293B] dark:text-white">{{ t('outlets.emptyTitle', 'Belum Ada Outlet') }}</h3>
      <p class="text-sm text-[#64748B] dark:text-[#94A3B8] max-w-sm">
        {{ t('outlets.emptyDesc', 'Daftarkan outlet usaha Anda untuk mulai mengelola meja, staf, dan terminal kasir.') }}
      </p>
      <AppButton variant="primary" size="md" @click="openCreateModal" class="mt-2 font-bold">
        <template #prefix>
          <Plus class="w-4 h-4" />
        </template>
        {{ t('outlets.addOutletNow', 'Tambah Outlet Sekarang') }}
      </AppButton>
    </div>

    <!-- Grid Card Outlet (Muat 4 card per row, avatar putih icon hitam rounded-lg, 1 komponen nama & alamat) -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="outlet in outlets" :key="outlet.id"
        class="bg-white dark:bg-[#273142] rounded-xl p-4 border border-[#E8E8E8] dark:border-[#313D4F] shadow-xs hover:shadow-sm transition-all duration-150 flex flex-col justify-between gap-8">
        <!-- 1. BARIS ATAS: Avatar Icon (Putih, Icon Hitam, Rounded-LG) & Outline Buttons (Rounded-LG) -->
        <div class="flex items-start justify-between gap-2">
          <!-- Avatar: Background Biru Primary, Icon Putih, Rounded-LG -->
          <div class="w-12 h-12 rounded-lg bg-[#4880FF] text-white flex items-center justify-center shrink-0 shadow-xs">
            <Store class="w-7 h-7" />
          </div>

          <!-- Buttons: Outline Rounded-LG -->
          <div class="flex items-center gap-1.5">
            <!-- Button Hubungkan: Teks Default, Tanpa Icon Kunci, Rounded-LG -->
            <button type="button" @click="openPairingModal(outlet)"
              class="h-8 px-3 rounded-lg border border-[#CBD5E1] dark:border-[#334155] hover:border-[#94A3B8] dark:hover:border-[#64748B] bg-transparent hover:bg-[#F8FAFC] dark:hover:bg-[#334155] text-xs font-semibold text-[#1E293B] dark:text-white transition-all cursor-pointer active:scale-[0.98]"
              :title="t('outlets.connectDevice', 'Hubungkan Perangkat Kasir atau Dapur')">
              {{ t('outlets.connect', 'Hubungkan') }}
            </button>

            <!-- Button Edit: Icon Pencil, Rounded-LG -->
            <button type="button" @click="openEditModal(outlet)"
              class="w-8 h-8 rounded-lg border border-[#CBD5E1] dark:border-[#334155] hover:border-[#94A3B8] dark:hover:border-[#64748B] text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white bg-transparent hover:bg-[#F8FAFC] dark:hover:bg-[#334155] flex items-center justify-center transition-all cursor-pointer active:scale-[0.98]"
              :title="t('outlets.editTooltip', 'Edit Outlet')">
              <Pencil class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- 2. BARIS BAWAH: 1 Komponen (Nama Outlet, Status Aktif/Tidak Aktif, No HP, Alamat dengan Icon) -->
        <div class="space-y-1.5">
          <!-- Baris Nama Outlet & Status dipisah tanda Ceklis -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <h3 class="text-sm font-bold text-[#1E293B] dark:text-white truncate" :title="outlet.name">
              {{ outlet.name }}
            </h3>

            <!-- Ceklis Verifikasi Hijau untuk tanda Outlet Utama -->
            <span v-if="outlet.is_main" class="inline-flex items-center text-emerald-500 shrink-0"
              title="Outlet Utama Terverifikasi">
              <svg class="w-4 h-4 drop-shadow-2xs" viewBox="0 0 24 24" fill="currentColor">
                <path fill-rule="evenodd"
                  d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                  clip-rule="evenodd" />
              </svg>
            </span>
            <span v-else class="text-[#94A3B8] text-xs shrink-0">•</span>

            <!-- Teks Status (Utama / Cabang) -->
            <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
              {{ outlet.is_main ? t('outlets.mainBadge', 'Utama') : t('outlets.branchBadge', 'Outlet') }}
            </span>
          </div>

          <!-- Status Aktif / Tidak Aktif (di atas no hp, dengan icon) -->
          <p class="text-xs font-medium flex items-center gap-1.5">
            <CircleCheck v-if="outlet.is_active" class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
            <CircleX v-else class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <span
              :class="outlet.is_active ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-[#64748B] dark:text-[#94A3B8]'">
              {{ outlet.is_active ? t('outlets.activeStatus', 'Status Aktif') : t('outlets.inactiveStatus', 'Status Tidak Aktif') }}
            </span>
          </p>

          <!-- Nomor Telepon Outlet -->
          <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium flex items-center gap-1.5">
            <Phone class="w-3.5 h-3.5 text-[#94A3B8] shrink-0" />
            <span>{{ outlet.phone || '-' }}</span>
          </p>

          <!-- Alamat Outlet (dengan icon MapPin) -->
          <p
            class="text-xs text-[#64748B] dark:text-[#94A3B8] font-normal line-clamp-2 leading-relaxed flex items-start gap-1.5">
            <MapPin class="w-3.5 h-3.5 text-[#94A3B8] shrink-0 mt-0.5" />
            <span>{{ outlet.address || t('outlets.noAddress', 'Alamat outlet belum diatur') }}</span>
          </p>
        </div>
      </div>
    </div>

    <!-- MODAL: PAIRING DEVICE OUTLET (HUBUNGKAN & INTEGRASI)    -->
    <AppModal v-model="isPairingModalOpen" :title="t('outlets.pairingModal.title', 'Integrasi Perangkat & Akun Outlet')" maxWidth="md">
      <div v-if="selectedOutletForPairing" class="flex flex-col text-center">

        <!-- Header Info Outlet -->
        <div class="flex items-start gap-4 text-left">
          <!-- Avatar Icon Outlet (48px / w-12 h-12) -->
          <div class="w-12 h-12 rounded-lg bg-[#4880FF] text-white flex items-center justify-center shrink-0 shadow-xs">
            <Store class="w-6 h-6" />
          </div>

          <!-- Detail Outlet -->
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <h4 class="text-sm font-bold text-[#1E293B] dark:text-white truncate">
                {{ selectedOutletForPairing.name }}
              </h4>
              <!-- Ceklis Verifikasi Hijau untuk tanda Outlet Utama / Titik untuk Cabang -->
              <span v-if="selectedOutletForPairing.is_main" class="inline-flex items-center text-emerald-500 shrink-0"
                title="Outlet Utama Terverifikasi">
                <svg class="w-4 h-4 drop-shadow-2xs" viewBox="0 0 24 24" fill="currentColor">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                    clip-rule="evenodd" />
                </svg>
              </span>
              <span v-else class="text-[#94A3B8] text-xs shrink-0">&bull;</span>

              <!-- Teks Status (Utama / Cabang) -->
              <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
                {{ selectedOutletForPairing.is_main ? 'Utama' : 'Cabang' }}
              </span>
            </div>

            <!-- No HP -->
            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium flex items-center gap-2">
              <Phone class="w-4 h-4 text-[#94A3B8] shrink-0" />
              <span>{{ selectedOutletForPairing.phone || '-' }}</span>
            </p>

            <!-- Alamat -->
            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-normal line-clamp-1 flex items-center gap-2">
              <MapPin class="w-4 h-4 text-[#94A3B8] shrink-0" />
              <span>{{ selectedOutletForPairing.address || 'Alamat outlet belum diatur' }}</span>
            </p>
          </div>
        </div>

        <!-- Garis Pembatas Dashed Horizontal (Sempurna Simetris 24px Atas & Bawah) -->
        <div class="py-6">
          <div class="border-t border-dashed border-[#CBD5E1] dark:border-[#334155]"></div>
        </div>

        <!-- 1. Kartu Nomor Kode Pairing -->
        <div class="space-y-4">
          <div
            @click="remainingSeconds > 0 ? copyPairingCode() : loadOrGeneratePairingCode(selectedOutletForPairing.id, true)"
            class="p-6 rounded-xl bg-gradient-to-b from-[#4880FF]/20 via-[#4880FF]/5 to-transparent border-2 border-[#4880FF]/50 dark:border-[#4880FF]/60 shadow-xs flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-150 active:scale-[0.99] hover:border-[#4880FF] group select-none relative overflow-hidden"
            :title="remainingSeconds > 0 ? 'Klik untuk salin kode pairing' : 'Klik untuk perbarui kode integrasi'">
            <div v-if="isPairingLoading" class="py-2 flex justify-center">
              <RefreshCw class="w-8 h-8 text-[#4880FF] animate-spin" />
            </div>
            <template v-else>
              <p
                class="text-2xl sm:text-3xl font-black tracking-widest text-[#1E293B] dark:text-white font-mono select-all">
                {{ pairingCode || '------' }}
              </p>
              <p
                class="text-xs sm:text-sm font-bold text-[#4880FF] dark:text-[#60A5FA] mt-2 flex items-center justify-center gap-2 transition-colors">
                <template v-if="remainingSeconds > 0">
                  <Check v-if="isCopied" class="w-4 h-4 text-[#00B69B]" />
                  <Copy v-else class="w-4 h-4 text-[#4880FF] dark:text-[#60A5FA]" />
                  <span>{{ isCopied ? 'Kode berhasil disalin!' : 'Klik untuk salin kode' }}</span>
                </template>
                <template v-else>
                  <RefreshCw
                    :class="['w-4 h-4 text-[#4880FF] dark:text-[#60A5FA]', isPairingLoading ? 'animate-spin' : '']" />
                  <span>Perbarui kode integrasi</span>
                </template>
              </p>
            </template>
          </div>

          <!-- Teks Batas Waktu Berlaku & Petunjuk -->
          <div class="space-y-2 text-center">
            <div
              class="flex items-center justify-center gap-2 text-center text-xs sm:text-sm font-medium px-2 flex-wrap">
              <span class="text-[#64748B] dark:text-[#94A3B8]">
                Berlaku selama
              </span>
              <span :class="[
                'font-bold tabular-nums',
                remainingSeconds > 0 ? 'text-[#EF4444] dark:text-red-400' : 'text-rose-500'
              ]">
                {{ formattedCountdown }}
              </span>
            </div>

            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] leading-relaxed max-w-sm mx-auto px-2">
              Masuk ke menggunakan metode login outlet, dan masukan 6 kode intergrasi perangkat & akun
            </p>
          </div>
        </div>

        <!-- Garis Pembatas Dashed Horizontal (Sempurna Simetris 24px Atas & Bawah) -->
        <div class="py-6">
          <div class="border-t border-dashed border-[#CBD5E1] dark:border-[#334155]"></div>
        </div>

        <!-- 3. Tabel Daftar Perangkat yang Terhubung (Kompak Khusus Modal) -->
        <div class="text-left space-y-2">
          <div class="flex items-center justify-between gap-3 min-h-[32px]">
            <h4 class="text-sm font-bold text-[#1E293B] dark:text-white">
              {{ t('outlets.pairingModal.connectedDevicesTitle', 'Perangkat Terhubung') }}{{ connectedDevices.length > 0 ? ` (${connectedDevices.length})` : '' }}
            </h4>

            <!-- shadcn/ui Pagination Component -->
            <AppPagination
              v-if="totalDevicePages > 1"
              v-model="devicePage"
              :totalPages="totalDevicePages"
              size="sm"
              :previousLabel="''"
              :nextLabel="''"
            />
          </div>

          <div v-if="isLoadingDevices"
            class="py-4 text-center text-xs text-[#94A3B8] flex items-center justify-center gap-2">
            <RefreshCw class="w-4 h-4 animate-spin text-[#4880FF]" />
            <span>Memuat...</span>
          </div>

          <div v-else-if="connectedDevices.length === 0"
            class="py-4 px-4 rounded-lg border border-dashed border-[#CBD5E1] dark:border-[#334155] text-center text-sm text-[#94A3B8]">
            {{ t('outlets.pairingModal.noDevices', 'Belum ada perangkat terhubung di outlet ini.') }}
          </div>

          <div v-else class="rounded-lg border border-[#E2E8F0] dark:border-[#334155] overflow-hidden">
            <table class="w-full text-xs border-collapse table-fixed">
              <thead
                class="bg-[#F8FAFC] dark:bg-[#1E293B] text-[10px] uppercase font-bold text-[#64748B] dark:text-[#94A3B8] border-b border-[#E2E8F0] dark:border-[#334155]">
                <tr>
                  <th class="py-2 px-4 text-left w-[32%]">{{ t('outlets.pairingModal.colAccount', 'Nama Akun') }}</th>
                  <th class="py-2 px-4 text-left w-[20%]">{{ t('outlets.pairingModal.colRole', 'Posisi') }}</th>
                  <th class="py-2 px-4 text-center w-[28%]">{{ t('outlets.pairingModal.colTime', 'Waktu Integrasi') }}</th>
                  <th class="py-2 px-4 text-center w-[20%]">{{ t('outlets.pairingModal.colDisconnect', 'Putus Koneksi') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155] bg-white dark:bg-[#1E293B]/40">
                <tr v-for="dev in paginatedConnectedDevices" :key="dev.id"
                  class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                  <td class="px-4 text-left font-semibold text-xs text-[#1E293B] dark:text-white truncate"
                    :title="dev.user_name || dev.device_name || 'Kasir'">
                    {{ dev.user_name || dev.device_name || 'Kasir' }}
                  </td>
                  <td class="px-4 text-left text-xs text-[#64748B] dark:text-[#94A3B8] truncate">
                    {{ dev.role || 'Kasir' }}
                  </td>
                  <td class="px-4 text-center font-mono text-xs text-[#64748B] dark:text-[#94A3B8]">
                    {{ formatDate(dev.connected_at) }}
                  </td>
                  <td class="px-4 text-center">
                    <button type="button" @click="handleDisconnectDevice(dev.id)"
                      :disabled="disconnectingDeviceId === dev.id"
                      class="inline-flex items-center justify-center p-2 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors disabled:opacity-50 cursor-pointer"
                      title="Putuskan sambungan">
                      <RefreshCw v-if="disconnectingDeviceId === dev.id" class="w-4 h-4 animate-spin" />
                      <Unlink v-else class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </AppModal>

    <!-- MODAL: FORM TAMBAH / EDIT CABANG -->
    <AppModal v-model="isFormModalOpen" :title="isEditing ? t('outlets.editOutlet', 'Edit Informasi Outlet') : t('outlets.addOutlet', 'Tambah Outlet Baru')"
      maxWidth="md">
      <div class="space-y-4 py-2">
        <div v-if="modalErrorMessage"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalErrorMessage }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">
            {{ t('outlets.form.name', 'Nama Outlet') }} <span class="text-red-500">*</span>
          </label>
          <AppInput v-model="formName" :placeholder="t('outlets.form.namePlaceholder', 'Contoh: Kopi Kenangan - Senopati')" :debounce="0" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">
            {{ t('outlets.form.address', 'Alamat Lengkap') }}
          </label>
          <AppInput v-model="formAddress" :placeholder="t('outlets.form.addressPlaceholder', 'Jl. Senopati No. 45, Jakarta Selatan')" :debounce="0" />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">
            {{ t('outlets.form.phone', 'Nomor WhatsApp / Kontak') }}
          </label>
          <AppInput v-model="formPhone" type="tel" placeholder="081234567890" :debounce="0" />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">
            {{ t('outlets.form.timezone', 'Zona Waktu Operasional') }}
          </label>
          <AppSelect v-model="formTimezone" :options="timezoneOptions" />
        </div>

        <!-- Checkbox Tetapkan sebagai Cabang Utama: 1 Line Simple (Teks di Kiri, Ceklis di Kanan) -->
        <div class="pt-2.5 border-t border-[#F1F5F9] dark:border-[#313D4F]">
          <label class="flex items-center justify-between cursor-pointer select-none py-1">
            <span class="text-xs font-semibold text-[#1E293B] dark:text-white">
              {{ t('outlets.form.setAsMain', 'Tetapkan sebagai outlet utama') }}
            </span>
            <input type="checkbox" v-model="formIsMain"
              class="w-4 h-4 rounded border-[#CBD5E1] dark:border-[#475569] text-[#4880FF] focus:ring-[#4880FF] focus:ring-offset-0 cursor-pointer transition-all" />
          </label>
        </div>

        <div v-if="isEditing" class="pt-2.5 border-t border-[#F1F5F9] dark:border-[#313D4F]">
          <AppToggle v-model="formIsActive" :label="t('outlets.form.outletStatus', 'Status Outlet')" :description="t('outlets.form.outletStatusDesc', 'Aktifkan outlet agar dapat digunakan')"
            labelClass="text-xs font-bold text-[#1E293B] dark:text-white"
            descriptionClass="text-[11px] text-[#64748B] dark:text-[#94A3B8]" />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <!-- Tombol Hapus jika sedang edit -->
          <div>
            <AppButton v-if="isEditing && outlets.length > 1" variant="danger" size="md"
              @click="openDeleteModal(outlets.find(o => o.id === editingId)!)" class="font-bold">
              <template #prefix>
                <Trash2 class="w-4 h-4" />
              </template>
              {{ t('common.delete', 'Hapus') }}
            </AppButton>
          </div>

          <div class="flex items-center gap-2.5">
            <AppButton variant="secondary" size="md" @click="isFormModalOpen = false" :disabled="isSubmitting">
              {{ t('common.cancel', 'Batal') }}
            </AppButton>
            <AppButton variant="primary" size="md" @click="handleSubmitForm" :disabled="isSubmitting" class="font-bold">
              <template #prefix>
                <RefreshCw v-if="isSubmitting" class="w-4 h-4 animate-spin" />
              </template>
              {{ isSubmitting ? t('common.saving', 'Menyimpan...') : (isEditing ? t('common.save', 'Simpan') : t('outlets.form.addBtn', 'Tambah Outlet')) }}
            </AppButton>
          </div>
        </div>
      </template>
    </AppModal>

    <!-- ======================================================= -->
    <!-- MODAL: KONFIRMASI HAPUS CABANG                          -->
    <!-- ======================================================= -->
    <AppModal v-model="isDeleteModalOpen" :title="t('outlets.deleteModal.title', 'Hapus Outlet')" maxWidth="sm">
      <div v-if="outletToDelete" class="py-2 space-y-3">
        <p class="text-sm text-[#1E293B] dark:text-white leading-relaxed">
          {{ t('outlets.deleteModal.confirmText', 'Apakah Anda yakin ingin menghapus outlet') }} <strong class="text-red-500">{{ outletToDelete.name }}</strong>?
        </p>
        <p class="text-sm text-[#64748B] dark:text-[#94A3B8] leading-relaxed">
          Tindakan ini permanen. Pastikan tidak ada transaksi aktif di outlet ini sebelum menghapusnya.
        </p>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-3 w-full">
          <AppButton variant="secondary" size="md" @click="isDeleteModalOpen = false" :disabled="isDeleting">
            Batal
          </AppButton>
          <button type="button" @click="handleDeleteOutlet" :disabled="isDeleting"
            class="h-10 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs sm:text-sm font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
            <RefreshCw v-if="isDeleting" class="w-4 h-4 animate-spin" />
            {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>
