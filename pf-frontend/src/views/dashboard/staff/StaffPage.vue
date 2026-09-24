<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, Edit2, Trash2 } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useDashboardI18n } from '@/i18n'
import apiClient from '@/services/api'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { UserRole } from '@/types'

export interface StaffItem {
  id: string
  name: string
  email?: string
  phone?: string | null
  outlet?: { id: string; name: string } | null
  outlet_id?: string
  roles?: string[]
  role: UserRole
  has_pin?: boolean
  is_active?: boolean
  created_at?: string
  avatarUrl?: string
}

const authStore = useAuthStore()
const { t, translate, locale } = useDashboardI18n()

// State
const staffList = ref<StaffItem[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)
const isDeleting = ref(false)

// Toast / Notification State
const notification = ref<{ type: 'success' | 'error'; message: string } | null>(null)
let notificationTimer: ReturnType<typeof setTimeout> | null = null

const showNotification = (type: 'success' | 'error', message: string) => {
  if (notificationTimer) clearTimeout(notificationTimer)
  notification.value = { type, message }
  notificationTimer = setTimeout(() => {
    notification.value = null
  }, 4000)
}

// Fetch Staff from Backend API
const fetchStaff = async () => {
  isLoading.value = true
  try {
    await authStore.ensureToken()
    const res = await apiClient.get('/staff')
    if (res.data && Array.isArray(res.data.staff)) {
      staffList.value = res.data.staff.map((s: any) => ({
        id: s.id,
        name: s.name,
        email: s.email,
        phone: s.phone || '',
        outlet: s.outlet,
        outlet_id: s.outlet_id,
        roles: s.roles || [],
        role: (s.role || (s.roles && s.roles[0]) || 'kasir') as UserRole,
        has_pin: s.has_pin ?? false,
        is_active: s.is_active ?? true,
        created_at: s.created_at,
      }))
    }
  } catch (err: any) {
    console.error('Failed to fetch staff:', err)
    showNotification('error', 'Gagal memuat data staf: ' + (err?.response?.data?.message || err.message))
  } finally {
    isLoading.value = false
  }
}

// Action Menu Dropdown State (Click outside to close - persis seperti TablesQrPage)
const activeMenuStaffId = ref<string | null>(null)

const toggleMenu = (staffId: string) => {
  activeMenuStaffId.value = activeMenuStaffId.value === staffId ? null : staffId
}

const closeMenu = () => {
  activeMenuStaffId.value = null
}

onMounted(() => {
  fetchStaff()
  window.addEventListener('click', closeMenu)
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (notificationTimer) clearTimeout(notificationTimer)
  window.removeEventListener('click', closeMenu)
})

// Search & Debounce
const searchInput = ref('')
const debouncedSearch = ref('')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (newVal) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
  }, 300)
})

// Filter & Sort States
const selectedRoleFilter = ref('all')
const selectedSort = ref('asc')

const filterRoleOptions = computed(() => [
  { value: 'all', label: locale.value === 'en' ? 'Posisi: Semua' : 'Posisi: Semua' },
  { value: 'store_manager', label: 'Role: Store Manager' },
  { value: 'kasir', label: 'Role: Kasir' },
  { value: 'kitchen_staff', label: 'Role: Kitchen Staff' },
  { value: 'owner', label: 'Role: Owner' },
])

const sortOptions = computed(() => [
  { value: 'asc', label: locale.value === 'en' ? 'Sort: Name (A - Z)' : 'Sort: Nama (A - Z)' },
  { value: 'desc', label: locale.value === 'en' ? 'Sort: Name (Z - A)' : 'Sort: Nama (Z - A)' },
])

// Table Columns Configuration
const columns = computed(() => [
  { key: 'name', label: 'Nama', width: '22%' },
  { key: 'outlet', label: 'Cabang', width: '18%' },
  { key: 'role', label: t('staff.colRole', 'Posisi / Role'), align: 'center' as const, width: '18%' },
  { key: 'pin', label: 'PIN Akses', align: 'center' as const, width: '14%' },
  { key: 'phone', label: 'No. WhatsApp', width: '16%' },
  { key: 'actions', label: t('staff.colActions', 'Aksi'), align: 'center' as const, width: '12%' },
])

// Filtered & Sorted Staff List
const filteredStaff = computed(() => {
  let list = [...staffList.value]

  // 1. Search Filter
  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter((user) =>
      user.name.toLowerCase().includes(q) ||
      (user.phone && user.phone.includes(q)) ||
      user.role.toLowerCase().includes(q)
    )
  }

  // 2. Role Filter
  if (selectedRoleFilter.value !== 'all') {
    list = list.filter((user) => user.role === selectedRoleFilter.value)
  }

  // 3. Sorting (Default Ascending A-Z)
  if (selectedSort.value === 'asc' || selectedSort.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name))
  } else if (selectedSort.value === 'desc' || selectedSort.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name))
  }

  return list
})

// Role Options for Select
const roleOptions = [
  { value: 'kasir', label: 'Kasir (Akses POS Kasir)' },
  { value: 'kitchen_staff', label: 'Kitchen Staff (Akses Dapur KDS)' },
  { value: 'store_manager', label: 'Store Manager (Akses Manajer Outlet)' },
]

// Dropdown Actions (Persis TablesQrPage)
const openEditStaff = (staff: StaffItem) => {
  activeMenuStaffId.value = null
  openEditModal(staff)
}

const openDeleteStaff = (staff: StaffItem) => {
  activeMenuStaffId.value = null
  confirmRemove(staff)
}

// Modal Form: Tambah / Undang Karyawan Baru
const isModalOpen = ref(false)
const newName = ref('')
const newPhone = ref('')
const newPin = ref('')
const newRole = ref<UserRole>('kasir')
const modalErrorMessage = ref('')

const openInviteModal = () => {
  newName.value = ''
  newPhone.value = ''
  newPin.value = ''
  newRole.value = 'kasir'
  modalErrorMessage.value = ''
  isModalOpen.value = true
}

const inviteStaff = async () => {
  if (!newName.value.trim()) {
    modalErrorMessage.value = 'Nama lengkap karyawan wajib diisi.'
    return
  }
  if (!/^\d{6}$/.test(newPin.value.trim())) {
    modalErrorMessage.value = 'PIN akses wajib 6 digit angka numerik.'
    return
  }

  modalErrorMessage.value = ''
  isSubmitting.value = true

  try {
    await authStore.ensureToken()
    const payload = {
      name: newName.value.trim(),
      pin: newPin.value.trim(),
      phone: newPhone.value.trim() || null,
      role: newRole.value,
    }

    const res = await apiClient.post('/staff', payload)
    showNotification('success', res.data?.message || 'Staff baru berhasil ditambahkan.')
    isModalOpen.value = false
    await fetchStaff()
  } catch (err: any) {
    console.error('Failed to create staff:', err)
    modalErrorMessage.value = err?.response?.data?.message || err?.response?.data?.error || 'Gagal menambahkan staf.'
  } finally {
    isSubmitting.value = false
  }
}

// Modal Form: Edit Staff
const isEditModalOpen = ref(false)
const staffToEdit = ref<StaffItem | null>(null)
const editName = ref('')
const editPhone = ref('')
const editPin = ref('')
const editRole = ref<UserRole>('kasir')
const editErrorMessage = ref('')
const isEditSubmitting = ref(false)

const openEditModal = (staff: StaffItem) => {
  staffToEdit.value = staff
  editName.value = staff.name
  editPhone.value = staff.phone || ''
  editPin.value = ''
  editRole.value = staff.role
  editErrorMessage.value = ''
  isEditModalOpen.value = true
}

const updateStaff = async () => {
  if (!staffToEdit.value) return
  if (!editName.value.trim()) {
    editErrorMessage.value = 'Nama lengkap karyawan tidak boleh kosong.'
    return
  }
  if (editPin.value.trim() && !/^\d{6}$/.test(editPin.value.trim())) {
    editErrorMessage.value = 'PIN akses baru harus berupa 6 digit angka numerik.'
    return
  }

  editErrorMessage.value = ''
  isEditSubmitting.value = true

  try {
    await authStore.ensureToken()
    const payload: any = {
      name: editName.value.trim(),
      phone: editPhone.value.trim() || null,
      role: editRole.value,
    }
    if (editPin.value.trim()) {
      payload.pin = editPin.value.trim()
    }

    const res = await apiClient.put(`/staff/${staffToEdit.value.id}`, payload)
    showNotification('success', res.data?.message || 'Data staf berhasil diperbarui.')
    isEditModalOpen.value = false
    await fetchStaff()
  } catch (err: any) {
    console.error('Failed to update staff:', err)
    editErrorMessage.value = err?.response?.data?.message || err?.response?.data?.error || 'Gagal memperbarui data staf.'
  } finally {
    isEditSubmitting.value = false
  }
}

// Modal Konfirmasi Hapus Staff
const isDeleteModalOpen = ref(false)
const staffToDelete = ref<StaffItem | null>(null)

const confirmRemove = (user: StaffItem) => {
  staffToDelete.value = user
  isDeleteModalOpen.value = true
}

const executeRemove = async () => {
  if (!staffToDelete.value) return
  isDeleting.value = true

  try {
    await authStore.ensureToken()
    const res = await apiClient.delete(`/staff/${staffToDelete.value.id}`)
    showNotification('success', res.data?.message || 'Akses staf berhasil dihapus.')
    staffToDelete.value = null
    isDeleteModalOpen.value = false
    await fetchStaff()
  } catch (err: any) {
    console.error('Failed to delete staff:', err)
    showNotification('error', 'Gagal menghapus staf: ' + (err?.response?.data?.message || err.message))
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Notification Banner (Toast) -->
    <transition enter-active-class="transition duration-300 ease-out transform"
      enter-from-class="-translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in transform" leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0">
      <div v-if="notification" :class="[
        'flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-semibold shadow-sm',
        notification.type === 'success'
          ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
          : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300'
      ]">
        <div class="flex items-center gap-2">
          <AppIcon :name="notification.type === 'success' ? 'check_circle' : 'error'" :size="20" />
          <span>{{ notification.message }}</span>
        </div>
        <button type="button" @click="notification = null"
          class="text-current opacity-70 hover:opacity-100 transition-opacity">
          <AppIcon name="close" :size="18" />
        </button>
      </div>
    </transition>

    <!-- Header Page -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
          {{ t('staff.pageTitle', 'Staff & Hak Akses') }}
        </h1>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <div v-if="isLoading" class="h-10 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl animate-pulse"></div>
        <AppButton v-else @click="openInviteModal" variant="primary" size="md" icon="person_add"
          class="!rounded-lg !font-bold">
          {{ t('staff.addStaff', 'Tambah Staff') }}
        </AppButton>
      </div>
    </div>

    <!-- Reusable Dashboard AppTable Component -->
    <AppTable :columns="columns" :data="filteredStaff" :pageSize="20" :searchable="false" :loading="isLoading"
      showNumbering numberingLabel="No" emptyMessage="Belum ada data staff yang sesuai">
      <!-- Integrated Header (Filters & Search) -->
      <template #header>
        <!-- Skeleton State for Filter & Search Bar -->
        <div v-if="isLoading"
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full animate-pulse">
          <div class="flex items-center gap-2">
            <div class="h-9 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-40 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
          </div>
          <div class="w-full sm:w-[260px] h-[38px] bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
        </div>

        <!-- Real Filter & Search Bar -->
        <div v-else class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Role Filter -->
            <AppFilterDropdown v-model="selectedRoleFilter" :options="filterRoleOptions" width="w-48" />

            <!-- 2. Sort Dropdown -->
            <AppFilterDropdown v-model="selectedSort" :options="sortOptions" width="w-52" />
          </div>

          <!-- Right: Search Input with Debouncing -->
          <div class="w-full sm:w-[260px] shrink-0 ml-auto">
            <AppInput v-model="searchInput" :placeholder="locale === 'en' ? 'Search name, email, phone...' : 'Cari nama, email, no hp...'" suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <!-- Table Skeleton Slots -->
      <template #skeleton-name>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
          <div class="space-y-1.5 flex-1">
            <div class="h-3.5 w-28 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
            <div class="h-2.5 w-16 bg-[#E2E8F0]/60 dark:bg-[#334155]/60 rounded-md"></div>
          </div>
        </div>
      </template>

      <template #skeleton-email>
        <div class="h-3.5 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
      </template>

      <template #skeleton-phone>
        <div class="h-3.5 w-24 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
      </template>

      <template #skeleton-role>
        <div class="flex items-center justify-center">
          <div class="h-6 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded-full"></div>
        </div>
      </template>

      <template #skeleton-actions>
        <div class="flex items-center justify-center">
          <div class="w-7 h-7 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
        </div>
      </template>

      <!-- Cell: Nama Karyawan -->
      <template #cell-name="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white block">
          {{ row.name }}
        </span>
      </template>

      <!-- Cell: Cabang (Outlet) -->
      <template #cell-outlet="{ row }">
        <span class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          {{ row.outlet?.name || '-' }}
        </span>
      </template>

      <!-- Cell: Role / Posisi -->
      <template #cell-role="{ row }">
        <div class="flex items-center justify-center">
          <AppBadge
            variant="primary" 
            size="md" rounded="full">
            {{ row.role === 'owner' ? 'Owner' : row.role === 'store_manager' ? 'Store Manager' : row.role === 'kasir' ? 'Kasir' : 'Kitchen Staff' }}
          </AppBadge>
        </div>
      </template>

      <!-- Cell: PIN Akses (Hanya teks keterangan) -->
      <template #cell-pin="{ row }">
        <span class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          {{ row.has_pin ? 'Aktif' : 'Belum Diatur' }}
        </span>
      </template>

      <!-- Cell: No. WhatsApp / HP -->
      <template #cell-phone="{ row }">
        <span class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          {{ row.phone || '-' }}
        </span>
      </template>

      <!-- Cell: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button type="button" @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Staff">
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
            <Transition enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-1">
              <div v-if="activeMenuStaffId === row.id"
                class="absolute right-0 top-full mt-1.5 w-44 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                <!-- 1. Edit Staf (Warning on hover) -->
                <button type="button" @click="openEditStaff(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Edit2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-150" />
                  <span>Edit Staf</span>
                </button>

                <!-- 2. Hapus (khusus non-owner & bukan akun sendiri, Danger on hover) -->
                <button v-if="row.role !== 'owner' && row.email !== authStore.currentUser?.email" type="button"
                  @click="openDeleteStaff(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Trash2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-150" />
                  <span>Hapus</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </template>
    </AppTable>

    <!-- Modal Form: Tambah Staf Baru -->
    <AppModal v-model="isModalOpen" title="Tambah Staf Baru" maxWidth="sm">
      <div class="space-y-4 py-2">
        <div v-if="modalErrorMessage"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalErrorMessage }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nama Lengkap</label>
          <AppInput v-model="newName" placeholder="Contoh: Rian Pratama" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Posisi / Hak Akses</label>
          <AppSelect v-model="newRole" :options="roleOptions" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">PIN Akses (6 Digit Angka)</label>
          <AppInput v-model="newPin" type="password" inputmode="numeric" maxlength="6" placeholder="Misal: 123456" required />
          <p class="text-[11px] text-[#64748B] dark:text-[#94A3B8] mt-1">
            Digunakan staf untuk login ke aplikasi Kasir (POS) dan Kitchen (KDS).
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nomor WhatsApp / HP (Opsional)</label>
          <AppInput v-model="newPhone" type="tel" placeholder="08123456789xxxxxx" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isModalOpen = false" :disabled="isSubmitting">
            Batal
          </AppButton>
          <AppButton variant="primary" size="md" @click="inviteStaff"
            :disabled="!newName.trim() || newPin.trim().length !== 6 || isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Staf' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Form: Edit Data Staff -->
    <AppModal v-model="isEditModalOpen" :title="`Edit ${staffToEdit?.name || 'Staf'}`" maxWidth="sm">
      <div class="space-y-4 py-2">
        <div v-if="editErrorMessage"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ editErrorMessage }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nama Lengkap</label>
          <AppInput v-model="editName" placeholder="Contoh: Rian Pratama" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Posisi / Hak Akses</label>
          <AppSelect v-model="editRole" :options="roleOptions" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Ganti PIN Akses (6 Digit)</label>
          <AppInput v-model="editPin" type="password" inputmode="numeric" maxlength="6" placeholder="Biarkan kosong jika tidak diubah" />
          <p class="text-[11px] text-[#64748B] dark:text-[#94A3B8] mt-1">
            Kosongkan jika tidak ingin mengganti PIN lama.
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nomor WhatsApp / HP (Opsional)</label>
          <AppInput v-model="editPhone" type="tel" placeholder="08123456789xxxxxx" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isEditModalOpen = false" :disabled="isEditSubmitting">
            Batal
          </AppButton>
          <AppButton variant="primary" size="md" @click="updateStaff"
            :disabled="!editName.trim() || isEditSubmitting">
            {{ isEditSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Konfirmasi Hapus Staff (Persis Style TablesQrPage) -->
    <AppModal v-model="isDeleteModalOpen" title="Hapus Staf" maxWidth="sm">
      <div v-if="staffToDelete" class="space-y-3 py-1 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin menghapus akses untuk <span class="font-bold text-[#1E293B] dark:text-white">{{
            staffToDelete.name }}</span> ({{ staffToDelete.email }})?
        </p>
        <p class="text-sm text-[#EF4444] bg-[#EF4444]/10 dark:bg-[#EF4444]/20 p-2.5 rounded-lg font-medium">
          Tindakan ini tidak dapat dibatalkan. Karyawan ini tidak akan dapat login lagi ke sistem POS kasir atau dapur.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isDeleteModalOpen = false" :disabled="isDeleting">
            Batal
          </AppButton>
          <AppButton variant="danger" size="md" @click="executeRemove" :disabled="isDeleting">
            {{ isDeleting ? 'Menghapus...' : 'Hapus' }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
