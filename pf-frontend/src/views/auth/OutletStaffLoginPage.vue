<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ArrowLeft,
  Delete,
  X,
  AlertCircle,
  Clock
} from 'lucide-vue-next'
import RoleIllustration from './components/RoleIllustration.vue'
import avatarManager from '@/assets/roles/avatar-manager.jpg'
import avatarCashier from '@/assets/roles/avatar-cashier.jpg'
import avatarKitchen from '@/assets/roles/avatar-kitchen.jpg'
import AppButton from '@/components/ui/AppButton.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'

interface StaffMember {
  id: string
  name: string
  email: string
  avatar: string | null
  role: string
  role_label: string
  role_description: string
  badge_color: string
  sort_order: number
  has_pin: boolean
}

interface OutletInfo {
  id: string
  name: string
  address?: string
  phone?: string
}

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// Loading & data states
const loading = ref(true)
const outlet = ref<OutletInfo | null>(null)
const staffList = ref<StaffMember[]>([])
const fetchError = ref('')

// Role selection state (null = Level 1: 3 Role Cards, string = Level 2: List of staff in selected role)
const selectedRole = ref<string | null>(null)

// Selected staff & PIN modal state
const isPinModalOpen = ref(false)
const selectedStaff = ref<StaffMember | null>(null)
const pinDigits = ref<string[]>([])
const pinLength = 6
const pinError = ref('')
const isPinSubmitting = ref(false)
const isShaking = ref(false)

// Rate limiting & Cooldown State (Max 3 wrong attempts, 30s cooldown)
const cooldownSeconds = ref(0)
const cooldownInterval = ref<any>(null)
const remainingAttempts = ref<number | null>(null)
const isKeypadDisabled = computed(() => isPinSubmitting.value || cooldownSeconds.value > 0)

// Start Cooldown Countdown Timer
const startCooldownTimer = (seconds: number) => {
  if (cooldownInterval.value) {
    clearInterval(cooldownInterval.value)
    cooldownInterval.value = null
  }
  cooldownSeconds.value = seconds

  if (selectedStaff.value) {
    localStorage.setItem(
      `lapaqu_pin_cooldown_${selectedStaff.value.id}`,
      String(Date.now() + seconds * 1000)
    )
  }

  cooldownInterval.value = setInterval(() => {
    if (cooldownSeconds.value > 1) {
      cooldownSeconds.value--
    } else {
      cooldownSeconds.value = 0
      clearInterval(cooldownInterval.value)
      cooldownInterval.value = null
      pinError.value = ''
      remainingAttempts.value = 3
      if (selectedStaff.value) {
        localStorage.removeItem(`lapaqu_pin_cooldown_${selectedStaff.value.id}`)
      }
    }
  }, 1000)
}

// Check cooldown status for a staff member (Local + Backend)
const checkStaffCooldown = async (staffId: string) => {
  // 1. Cek penyimpanan lokal terlebih dahulu
  const savedUntil = localStorage.getItem(`lapaqu_pin_cooldown_${staffId}`)
  if (savedUntil) {
    const diff = Math.ceil((parseInt(savedUntil, 10) - Date.now()) / 1000)
    if (diff > 0) {
      startCooldownTimer(diff)
      return
    } else {
      localStorage.removeItem(`lapaqu_pin_cooldown_${staffId}`)
    }
  }

  // 2. Sinkronkan dengan backend endpoint status PIN
  try {
    const res = await apiClient.get(`/auth/staff-pin-status/${staffId}`)
    if (res.data?.is_locked && res.data?.retry_after > 0) {
      startCooldownTimer(res.data.retry_after)
    } else if (typeof res.data?.remaining_attempts === 'number') {
      remainingAttempts.value = res.data.remaining_attempts
    }
  } catch { }
}

// Get staff avatar head illustration based on role
const getStaffAvatar = (staff: StaffMember) => {
  if (staff.avatar) return staff.avatar
  if (staff.role === 'store_manager') return avatarManager
  if (staff.role === 'kitchen_staff') return avatarKitchen
  return avatarCashier
}

// Staff avatar head illustration in PIN modal
const staffAvatarImg = computed(() => {
  if (!selectedStaff.value) return avatarManager
  return getStaffAvatar(selectedStaff.value)
})

// 3 Main Roles Configuration
const rolesConfig = [
  {
    key: 'store_manager',
    title: 'Store Manager',
    shortTitle: 'Store Manager',
  },
  {
    key: 'kasir',
    title: 'Kasir POS',
    shortTitle: 'Kasir POS',
  },
  {
    key: 'kitchen_staff',
    title: 'Dapur',
    shortTitle: 'Dapur',
  },
]

// Get staff list filtered by role
const getStaffByRole = (roleKey: string): StaffMember[] => {
  return staffList.value.filter((s) => s.role === roleKey)
}

// Active role meta object
const activeRoleConfig = computed(() => {
  if (!selectedRole.value) return null
  return rolesConfig.find((r) => r.key === selectedRole.value) || null
})

// Active staff list in selected role
const activeRoleStaffList = computed(() => {
  if (!selectedRole.value) return []
  return getStaffByRole(selectedRole.value)
})

// Page title and subtitle
const pageTitle = computed(() => {
  return selectedRole.value === null
    ? 'Pilih Peran Bertugas Anda'
    : `Pilih Karyawan ${activeRoleConfig.value?.shortTitle || ''}`
})

const pageSubtitle = computed(() => {
  return selectedRole.value === null
    ? 'Silakan tentukan peran operasional Anda untuk mulai bertugas di outlet'
    : 'Silakan pilih nama karyawan Anda untuk melanjutkan'
})

// Staff count and dynamic responsive layout configuration
const staffCount = computed(() => activeRoleStaffList.value.length)

// Dynamic Header Max Width to align back button with the leftmost column of the grid
const headerMaxWidthClass = computed(() => {
  if (selectedRole.value === null) {
    return 'max-w-4xl'
  }
  const count = staffCount.value
  if (count <= 3) {
    return 'max-w-4xl'
  } else if (count < 6) {
    return 'max-w-5xl'
  } else {
    return 'max-w-6xl'
  }
})

// Dynamic Grid Column & Gap Class based on staff count:
// <= 3  : 3 per row
// < 6   : 4 per row (4-5)
// < 8+  : 5 per row (6-7+)
const staffGridClass = computed(() => {
  const count = staffCount.value
  if (count <= 3) {
    return 'grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8 max-w-4xl'
  } else if (count < 6) {
    return 'grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 max-w-5xl'
  } else {
    return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 sm:gap-5 max-w-6xl'
  }
})

// Dynamic Avatar Size Class
const dynamicAvatarClass = computed(() => {
  const count = staffCount.value
  if (count <= 3) {
    return 'w-24 h-24 sm:w-28 sm:h-28'
  } else if (count < 6) {
    return 'w-18 h-18 sm:w-22 sm:h-22'
  } else {
    return 'w-14 h-14 sm:w-16 sm:h-16'
  }
})

// Dynamic Card Padding Class
const dynamicCardClass = computed(() => {
  const count = staffCount.value
  if (count <= 3) {
    return 'p-5 sm:p-6'
  } else if (count < 6) {
    return 'p-4 sm:p-5'
  } else {
    return 'p-3 sm:p-4'
  }
})

// Dynamic Name Typography & Spacing Class
const dynamicNameClass = computed(() => {
  const count = staffCount.value
  if (count <= 3) {
    return 'text-base sm:text-lg mt-4 sm:mt-5'
  } else if (count < 6) {
    return 'text-sm sm:text-base mt-3 sm:mt-4'
  } else {
    return 'text-xs sm:text-sm mt-2 sm:mt-3'
  }
})

// Fetch staff members
const fetchStaff = async () => {
  loading.value = true
  fetchError.value = ''

  // Periksa apakah perangkat sudah di-pair
  const saved = localStorage.getItem('lapaqu_paired_outlet')
  const deviceToken = localStorage.getItem('lapaqu_device_token')
  if (!saved || !deviceToken) {
    router.replace({ path: '/auth/outlet-login', query: { alert: 'unpaired' } })
    return
  }

  let outletId = ''
  try {
    const parsed = JSON.parse(saved)
    outletId = parsed.id || ''
  } catch { }

  if (!outletId) {
    router.replace({ path: '/auth/outlet-login', query: { alert: 'unpaired' } })
    return
  }

  try {
    const res = await apiClient.get('/auth/outlet-staff', {
      params: { outlet_id: outletId },
      headers: {
        Authorization: `Bearer ${deviceToken}`,
      },
    })

    outlet.value = res.data.outlet
    staffList.value = res.data.staff || []

    if (res.data.outlet) {
      localStorage.setItem('lapaqu_paired_outlet', JSON.stringify(res.data.outlet))
      if (res.data.outlet.name) {
        localStorage.setItem('lapaqu_outlet_name', res.data.outlet.name)
      }
      if (res.data.outlet.tenant_name) {
        localStorage.setItem('lapaqu_tenant_name', res.data.outlet.tenant_name)
      }
      window.dispatchEvent(new Event('outlet-info-updated'))
    }
  } catch (err: any) {
    if (err?.response?.status === 401 || err?.response?.status === 403) {
      localStorage.removeItem('lapaqu_paired_outlet')
      localStorage.removeItem('lapaqu_device_token')
      router.replace({ path: '/auth/outlet-login', query: { alert: 'unpaired' } })
      return
    }
    fetchError.value =
      err?.response?.data?.message ||
      'Gagal memuat profil staff outlet. Pastikan perangkat terhubung.'
  } finally {
    loading.value = false
  }
}

// Handler saat salah satu dari 3 Card Peran diklik
const handleRoleClick = (roleKey: string) => {
  const staffInRole = getStaffByRole(roleKey)
  const roleName = roleKey === 'store_manager' ? 'Manager' : roleKey === 'kasir' ? 'Kasir' : 'Dapur'

  if (staffInRole.length === 0) {
    fetchError.value = `Belum ada akun karyawan yang terdaftar untuk peran ${roleName}.`
    setTimeout(() => {
      fetchError.value = ''
    }, 4000)
    return
  }

  // Jika akun <= 1: Langsung masuk ke akun (buka prompt PIN)
  if (staffInRole.length === 1) {
    openPinModal(staffInRole[0])
  } else {
    // Jika akun > 1: Tampilkan card daftar karyawan untuk dipilih
    selectedRole.value = roleKey
  }
}

// Open PIN modal for a specific staff member
const openPinModal = (staff: StaffMember) => {
  selectedStaff.value = staff
  pinDigits.value = []
  pinError.value = ''
  isPinSubmitting.value = false
  isShaking.value = false
  cooldownSeconds.value = 0
  if (cooldownInterval.value) {
    clearInterval(cooldownInterval.value)
    cooldownInterval.value = null
  }
  isPinModalOpen.value = true
  checkStaffCooldown(staff.id)
}

// Close PIN modal
const closePinModal = () => {
  if (isPinSubmitting.value) return
  isPinModalOpen.value = false
  selectedStaff.value = null
  pinDigits.value = []
  pinError.value = ''
}

// Keypad input
const handleKeyPress = (digit: string) => {
  if (isKeypadDisabled.value) return
  if (pinDigits.value.length < pinLength) {
    pinDigits.value.push(digit)
    pinError.value = ''

    if (pinDigits.value.length === pinLength) {
      submitPin()
    }
  }
}

// Backspace
const handleBackspace = () => {
  if (isKeypadDisabled.value) return
  if (pinDigits.value.length > 0) {
    pinDigits.value.pop()
    pinError.value = ''
  }
}

// Clear all digits
const handleClear = () => {
  if (isKeypadDisabled.value) return
  pinDigits.value = []
  pinError.value = ''
}

// Submit PIN verification
const submitPin = async () => {
  if (!selectedStaff.value || pinDigits.value.length !== pinLength || isKeypadDisabled.value) return

  isPinSubmitting.value = true
  pinError.value = ''

  try {
    const payload = {
      user_id: selectedStaff.value.id,
      pin: pinDigits.value.join(''),
      outlet_id: outlet.value?.id,
    }

    const res = await apiClient.post('/auth/staff-pin-login', payload)

    if (res.data?.token) {
      // Clear cooldown tracking on success
      if (selectedStaff.value) {
        localStorage.removeItem(`lapaqu_pin_cooldown_${selectedStaff.value.id}`)
      }
      if (cooldownInterval.value) {
        clearInterval(cooldownInterval.value)
        cooldownInterval.value = null
      }
      cooldownSeconds.value = 0

      // Save auth session (in-memory & session storage)
      authStore.setAuthData(res.data.token, res.data.user, outlet.value ? [outlet.value] : undefined)

      isPinModalOpen.value = false
      const targetUrl = res.data.redirect_url || '/pos/orders'
      router.push(targetUrl)
    }
  } catch (err: any) {
    isShaking.value = true
    const resData = err?.response?.data
    const status = err?.response?.status

    // Jika 429 Too Many Requests atau backend kirim retry_after (Cooldown 30 detik)
    if (status === 429 || (resData?.retry_after && resData.retry_after > 0)) {
      const waitSec = resData?.retry_after || 30
      startCooldownTimer(waitSec)
      pinError.value = resData?.message || `PIN salah 3 kali. Silakan coba lagi dalam ${waitSec} detik.`
    } else {
      if (typeof resData?.remaining_attempts === 'number') {
        remainingAttempts.value = resData.remaining_attempts
      }
      pinError.value = resData?.message || 'PIN yang Anda masukkan salah. Silakan coba lagi.'
    }

    // Reset shake after animation
    setTimeout(() => {
      isShaking.value = false
      pinDigits.value = []
    }, 600)
  } finally {
    isPinSubmitting.value = false
  }
}

// Physical keyboard listener
const handleGlobalKeyDown = (e: KeyboardEvent) => {
  if (!isPinModalOpen.value || isKeypadDisabled.value) return

  if (e.key >= '0' && e.key <= '9') {
    e.preventDefault()
    handleKeyPress(e.key)
  } else if (e.key === 'Backspace') {
    e.preventDefault()
    handleBackspace()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    closePinModal()
  }
}

onMounted(() => {
  fetchStaff()
  window.addEventListener('keydown', handleGlobalKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown)
  if (cooldownInterval.value) {
    clearInterval(cooldownInterval.value)
    cooldownInterval.value = null
  }
})
</script>

<template>
  <div class="w-full max-w-6xl mx-auto flex flex-col items-center px-3 sm:px-4 py-2">
    <!-- ERROR BANNER (IF ANY) -->
    <div v-if="fetchError"
      class="w-full max-w-md mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-red-700 dark:text-red-300 text-xs sm:text-sm flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <AlertCircle class="w-4 h-4 shrink-0 text-red-600" />
        <span>{{ fetchError }}</span>
      </div>
      <AppButton size="sm" variant="outline" @click="fetchStaff">Coba Lagi</AppButton>
    </div>

    <!-- SKELETON LOADING -->
    <div v-if="loading" class="w-full space-y-8 py-2">
      <div class="text-center space-y-2">
        <AppSkeleton width="220px" height="32px" rounded="lg" class="mx-auto" />
        <AppSkeleton width="340px" height="16px" rounded="md" class="mx-auto" />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8 w-full max-w-4xl mx-auto">
        <div v-for="i in 3" :key="i"
          class="p-6 sm:p-7 rounded-2xl sm:rounded-3xl bg-white dark:bg-[#273142] border-2 border-slate-200 dark:border-slate-700 flex flex-col items-center justify-between h-[240px] sm:h-[270px]">
          <AppSkeleton width="85%" height="150px" rounded="xl" />
          <AppSkeleton width="110px" height="20px" rounded="md" class="mt-4" />
        </div>
      </div>
    </div>

    <!-- CONTENT SECTION (TRANSITION BETWEEN ROLE SELECTION & EMPLOYEE SELECTION) -->
    <div v-else class="w-full">
      <!-- Unified Heading & Subheading (Posisi Terkunci & 100% Identik di Semua Langkah) -->
      <div :class="[
        'relative w-full mx-auto text-center space-y-2 mb-8 py-2 transition-all duration-200',
        headerMaxWidthClass
      ]">
        <!-- Back Button ke Pilihan Peran (Ukuran sama seperti button logout: w-9 h-9 sm:w-10 sm:h-10 rounded-full, dinamis sejajar lurus kartu karyawan) -->
        <button v-if="selectedRole !== null" type="button" @click="selectedRole = null"
          class="absolute left-0 top-0.5 sm:top-1 w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          title="Kembali ke Pilihan Peran">
          <ArrowLeft class="w-5 h-5 sm:w-5 sm:h-5" :stroke-width="2.5" />
        </button>

        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight">
          {{ pageTitle }}
        </h1>
        <p class="text-xs sm:text-sm text-[#64748B] dark:text-[#94A3B8] max-w-md mx-auto leading-relaxed">
          {{ pageSubtitle }}
        </p>
      </div>

      <!-- Role Selection Grid -->
      <div v-if="selectedRole === null" class="w-full">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8 w-full max-w-4xl mx-auto">
          <div v-for="role in rolesConfig" :key="role.key" @click="handleRoleClick(role.key)"
            class="group p-6 sm:p-7 rounded-2xl sm:rounded-3xl bg-white dark:bg-[#273142] border-2 border-slate-200 dark:border-slate-700/80 hover:border-[#4880FF] dark:hover:border-[#4880FF] hover:shadow-md transition-all duration-200 ease-out cursor-pointer flex flex-col items-center justify-between text-center select-none hover:-translate-y-1.02 hover:scale-[1.02]">
            <!-- Illustration Area (Default abu-abu / grayscale, hover berwarna & menonjol) -->
            <div
              class="w-full h-36 sm:h-44 flex items-center justify-center p-2 rounded-xl grayscale group-hover:grayscale-0 opacity-80 group-hover:opacity-100 transition-all duration-200">
              <RoleIllustration :role="role.key" />
            </div>

            <!-- Role Title (Teks tetap default, tidak ada perubahan warna saat hover) -->
            <h3 class="text-base sm:text-lg font-bold tracking-tight mt-5 text-[#1E293B] dark:text-white select-none">
              {{ role.title }}
            </h3>
          </div>
        </div>
      </div>

      <!-- Employee Selection Grid -->
      <div v-else class="w-full flex flex-col items-center">
        <!-- Scrollable Employee Cards Container (Internal Scroll) -->
        <div class="w-full max-h-[calc(100vh-270px)] sm:max-h-[calc(100vh-290px)] overflow-y-auto py-3 pr-1 sm:pr-1.5">
          <!-- Employees Cards Grid (Dinamis: <=3: 3 per row, <6: 4 per row, <8+: 5 per row) -->
          <div :class="['grid w-full mx-auto', staffGridClass]">
            <div v-for="staff in activeRoleStaffList" :key="staff.id" @click="openPinModal(staff)" :class="[
              'group rounded-2xl sm:rounded-3xl bg-white dark:bg-[#273142] border-2 border-slate-200 dark:border-slate-700/80 hover:border-[#4880FF] dark:hover:border-[#4880FF] hover:shadow-md transition-all duration-200 ease-out cursor-pointer flex flex-col items-center justify-center text-center select-none hover:-translate-y-1.02 hover:scale-[1.02] aspect-square',
              dynamicCardClass
            ]">
              <!-- Avatar Area (Avatar Head Sesuai Role, Ukuran Dinamis & Grayscale) -->
              <div :class="[
                'rounded-full overflow-hidden border border-slate-200 dark:border-slate-700/80 shadow-xs flex items-center justify-center bg-slate-100 dark:bg-slate-800 shrink-0 grayscale group-hover:grayscale-0 opacity-80 group-hover:opacity-100 transition-all duration-200',
                dynamicAvatarClass
              ]">
                <img :src="getStaffAvatar(staff)" :alt="staff.name"
                  class="w-full h-full object-cover select-none pointer-events-none rounded-full" />
              </div>

              <!-- Staff Name (Dinamis) -->
              <div class="text-center select-none w-full px-1">
                <h3 :class="[
                  'font-bold tracking-tight text-[#1E293B] dark:text-white select-none truncate',
                  dynamicNameClass
                ]" :title="staff.name">
                  {{ staff.name }}
                </h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Link Login Email / Owner (Hanya tampil di menu pilihan peran, disembunyikan di menu karyawan) -->
    <div v-if="selectedRole === null" class="mt-8 text-center">
      <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
        Login sebagai pemilik usaha?
        <router-link to="/auth/login?mode=owner" class="font-bold text-[#4880FF] hover:underline ml-1">
          Masuk ke halaman login
        </router-link>
      </p>
    </div>

    <!-- 5. PIN KEYPAD MODAL OVERLAY -->
    <transition name="modal-fade">
      <div v-if="isPinModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
        @click.self="closePinModal">
        <div :class="[
          'modal-dialog-content w-full max-w-[360px] sm:max-w-[380px] bg-white dark:bg-[#1E293B] rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700/80 p-5 sm:p-6 flex flex-col items-center relative transition-all duration-150',
          isShaking ? 'animate-shake' : ''
        ]">
          <!-- Close Button (Diperbesar & warna default) -->
          <button type="button" @click="closePinModal"
            class="absolute top-4 right-4 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            title="Tutup">
            <X class="w-6 h-6" />
          </button>

          <div class="flex items-center justify-center gap-3.5 mx-auto my-4 w-fit max-w-full">
            <div
              class="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden shrink-0 border border-slate-200 dark:border-slate-700/80 shadow-xs">
              <img :src="staffAvatarImg" :alt="selectedStaff?.name || 'Avatar Karyawan'"
                class="w-full h-full object-cover select-none pointer-events-none rounded-full" />
            </div>

            <!-- Teks Nama & Role Karyawan (Pixel Perfect Height h-14 dengan Avatar, Role Diperbesar) -->
            <div class="flex flex-col justify-between h-14 min-w-0 text-left py-1">
              <h3
                class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white leading-none tracking-tight truncate"
                :title="selectedStaff?.name">
                {{ selectedStaff?.name }}
              </h3>
              <p v-if="selectedStaff" class="text-sm sm:text-base font-medium text-[#94A3B8] leading-none">
                {{ selectedStaff.role_label }}
              </p>
            </div>
          </div>

          <!-- Prompt -->
          <p class="text-xs text-slate-500 dark:text-slate-400 text-center mb-7">
            Masukkan 6 Digit PIN Keamanan
          </p>

          <!-- PIN Dots Indicator (Jarak simetris ke atas dan ke bawah) -->
          <div class="flex items-center justify-center gap-3.5 mb-2">
            <div v-for="idx in pinLength" :key="idx" :class="[
              'w-4 h-4 rounded-full transition-all duration-150 border-2',
              cooldownSeconds > 0
                ? 'border-amber-400/70 bg-amber-100/60 dark:bg-amber-900/40'
                : (pinDigits.length >= idx
                  ? (pinError
                    ? 'border-[#EC4453] bg-[#EC4453] scale-110 shadow-sm shadow-[#EC4453]/40'
                    : 'border-[#4880FF] bg-[#4880FF] scale-110 shadow-sm shadow-[#4880FF]/40')
                  : 'border-slate-300 dark:border-slate-600 bg-transparent')
            ]"></div>
          </div>

          <!-- Cooldown Warning / Error / Submitting Feedback Slot -->
          <div class="min-h-9 flex items-center justify-center mb-2 px-1">
            <!-- Cooldown Active Alert -->
            <div v-if="cooldownSeconds > 0"
              class="flex items-center justify-center gap-1.5 text-xs font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border border-amber-300/80 dark:border-amber-700/60 rounded-xl px-3 py-1.5 w-full max-w-[280px] shadow-xs select-none">
              <Clock class="w-3.5 h-3.5 shrink-0 text-amber-500 animate-spin" style="animation-duration: 4s;" />
              <span>Coba lagi dalam {{ cooldownSeconds }} detik</span>
            </div>

            <!-- Submitting Spinner -->
            <div v-else-if="isPinSubmitting" class="flex items-center gap-2 text-xs font-semibold text-[#4880FF]">
              <svg class="animate-spin h-3.5 w-3.5 text-[#4880FF]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
              <span>Memverifikasi PIN...</span>
            </div>

            <!-- Error Message with Remaining Attempts -->
            <p v-else-if="pinError"
              class="text-xs text-[#EC4453] font-semibold text-center leading-tight transition-opacity max-w-[280px]">
              {{ pinError }}
            </p>
          </div>

          <!-- Numeric Keypad (Touch / Clickable - Disabled saat Cooldown) -->
          <div class="grid grid-cols-3 gap-2.5 sm:gap-3 w-full max-w-[280px] mb-3 select-none">
            <!-- Digits 1-9 -->
            <button v-for="n in 9" :key="n" type="button" :disabled="isKeypadDisabled"
              @click="handleKeyPress(String(n))"
              class="h-12 sm:h-13 rounded-xl bg-slate-50 dark:bg-[#273142] border border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-[#334155] active:bg-slate-200 dark:active:bg-[#38455a] active:scale-95 text-slate-800 dark:text-white text-xl font-bold font-mono transition-all flex items-center justify-center cursor-pointer outline-none focus:outline-none focus-visible:outline-none focus:ring-0 active:outline-none disabled:opacity-35 disabled:cursor-not-allowed">
              {{ n }}
            </button>

            <!-- Clear (C) -->
            <button type="button" :disabled="isKeypadDisabled || pinDigits.length === 0" @click="handleClear"
              class="h-12 sm:h-13 rounded-xl bg-slate-50 dark:bg-[#273142] border border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-[#334155] active:bg-slate-200 dark:active:bg-[#38455a] active:scale-95 text-slate-500 dark:text-slate-400 hover:text-red-500 dark:hover:text-red-400 text-sm font-bold transition-all flex items-center justify-center cursor-pointer outline-none focus:outline-none focus-visible:outline-none focus:ring-0 active:outline-none disabled:opacity-30 disabled:cursor-not-allowed">
              C
            </button>

            <!-- Digit 0 -->
            <button type="button" :disabled="isKeypadDisabled" @click="handleKeyPress('0')"
              class="h-12 sm:h-13 rounded-xl bg-slate-50 dark:bg-[#273142] border border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-[#334155] active:bg-slate-200 dark:active:bg-[#38455a] active:scale-95 text-slate-800 dark:text-white text-xl font-bold font-mono transition-all flex items-center justify-center cursor-pointer outline-none focus:outline-none focus-visible:outline-none focus:ring-0 active:outline-none disabled:opacity-35 disabled:cursor-not-allowed">
              0
            </button>

            <!-- Backspace (⌫) -->
            <button type="button" :disabled="isKeypadDisabled || pinDigits.length === 0" @click="handleBackspace"
              class="h-12 sm:h-13 rounded-xl bg-slate-50 dark:bg-[#273142] border border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-[#334155] active:bg-slate-200 dark:active:bg-[#38455a] active:scale-95 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white text-sm font-bold transition-all flex items-center justify-center cursor-pointer outline-none focus:outline-none focus-visible:outline-none focus:ring-0 active:outline-none disabled:opacity-30 disabled:cursor-not-allowed">
              <Delete class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
/* Shake animation on wrong PIN */
@keyframes shake {

  0%,
  100% {
    transform: translateX(0);
  }

  20%,
  60% {
    transform: translateX(-8px);
  }

  40%,
  80% {
    transform: translateX(8px);
  }
}

.animate-shake {
  animation: shake 0.45s ease-in-out;
}

/* Modal overlay backdrop: murni fade in & fade out tanpa scale */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Modal dialog card pop in/out */
.modal-fade-enter-active .modal-dialog-content,
.modal-fade-leave-active .modal-dialog-content {
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.modal-fade-enter-from .modal-dialog-content,
.modal-fade-leave-to .modal-dialog-content {
  opacity: 0;
  transform: scale(0.96);
}

/* Hilangkan border hitam / outline fokus browser saat mengetik keypad */
button:focus,
button:focus-visible,
button:active {
  outline: none !important;
  -webkit-tap-highlight-color: transparent;
}
</style>
