<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, Edit2, Trash2 } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import type { MenuCategory } from '@/types'

const posStore = usePosStore()

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

// Modal states
const isModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const editingCategory = ref<MenuCategory | null>(null)
const categoryToDelete = ref<MenuCategory | null>(null)
const formName = ref('')
const formSortOrder = ref<number>(1)
const modalError = ref('')

// Dropdown Titik Tiga (Aksi Kategori)
const activeMenuCatId = ref<string | null>(null)
const toggleMenu = (catId: string) => {
  activeMenuCatId.value = activeMenuCatId.value === catId ? null : catId
}
const closeMenu = () => {
  activeMenuCatId.value = null
}

const loadData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchCategories(),
      posStore.fetchMenuItems(),
    ])
  } catch (err: any) {
    showNotification('error', 'Gagal memuat kategori: ' + (err?.message || 'Error'))
  } finally {
    isLoading.value = false
  }
}

const getItemCount = (catId: string) => {
  if (posStore.menuItems.length > 0) {
    return posStore.menuItems.filter(m => m.categoryId === catId).length
  }
  const found = posStore.categories.find(c => c.id === catId)
  return found?.itemCount || 0
}

onMounted(() => {
  window.addEventListener('click', closeMenu)
  loadData()
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (notificationTimer) clearTimeout(notificationTimer)
  window.removeEventListener('click', closeMenu)
})

// Search & Filters matching Kasir Riwayat Order
const searchInput = ref('')
const debouncedSearch = ref('')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (newVal) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
  }, 300)
})

const selectedItemCountFilter = ref('all') // 'all', 'has_items', 'empty'
const selectedSort = ref('asc') // 'default', 'name_asc', 'name_desc', 'items_desc', 'items_asc'

const filterItemCountOptions = [
  { value: 'all', label: 'Semua Kategori' },
  { value: 'has_items', label: 'Ada Menu' },
  { value: 'empty', label: 'Menu Kosong' },
]

const filterSortOptions = [
  { value: 'asc', label: 'Ascending' },
  { value: 'desc', label: 'Descending' },
  { value: 'items_desc', label: 'Menu Terbanyak' },
  { value: 'items_asc', label: 'Menu Tersedikit' },
]

// Table Columns Configuration (Proporsional & Simetris)
const columns = [
  { key: 'name', label: 'Nama Kategori', width: '28%' },
  { key: 'itemCount', label: 'Jumlah Menu', align: 'center' as const, width: '30%' },
  { key: 'sortOrder', label: 'Urutan Tampil', align: 'center' as const, width: '30%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '12%' },
]

// Filtered & Sorted Categories from Real Store Data
const filteredCategories = computed(() => {
  let list = [...posStore.categories]

  // 1. Text Search Filter
  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter((cat) => cat.name.toLowerCase().includes(q))
  }

  // 2. Item Count Filter
  if (selectedItemCountFilter.value === 'has_items') {
    list = list.filter((cat) => getItemCount(cat.id) > 0)
  } else if (selectedItemCountFilter.value === 'empty') {
    list = list.filter((cat) => getItemCount(cat.id) === 0)
  }

  // 3. Sorting (Default Ascending A-Z)
  if (selectedSort.value === 'asc' || selectedSort.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name))
  } else if (selectedSort.value === 'desc' || selectedSort.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name))
  } else if (selectedSort.value === 'items_desc') {
    list.sort((a, b) => (b.itemCount || 0) - (a.itemCount || 0))
  } else if (selectedSort.value === 'items_asc') {
    list.sort((a, b) => (a.itemCount || 0) - (b.itemCount || 0))
  } else {
    // Default: Sort by sortOrder
    list.sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0))
  }

  return list
})

// Modal Handlers (Direct Real Backend API)
const openAdd = () => {
  editingCategory.value = null
  formName.value = ''
  formSortOrder.value = posStore.categories.length + 1
  modalError.value = ''
  isModalOpen.value = true
}

const openEdit = (cat: MenuCategory) => {
  editingCategory.value = cat
  formName.value = cat.name
  formSortOrder.value = cat.sortOrder || 1
  modalError.value = ''
  isModalOpen.value = true
}

const confirmDelete = (cat: MenuCategory) => {
  categoryToDelete.value = cat
  isDeleteModalOpen.value = true
}

const saveCategory = async () => {
  if (!formName.value.trim() || isSubmitting.value) return

  modalError.value = ''
  isSubmitting.value = true

  try {
    if (editingCategory.value) {
      await posStore.updateCategory(editingCategory.value.id, {
        name: formName.value.trim(),
        sort_order: Number(formSortOrder.value) || 1,
      })
      showNotification('success', 'Kategori menu berhasil diperbarui.')
    } else {
      await posStore.createCategory({
        name: formName.value.trim(),
        sort_order: Number(formSortOrder.value) || posStore.categories.length + 1,
      })
      showNotification('success', 'Kategori baru berhasil ditambahkan.')
    }
    isModalOpen.value = false
  } catch (err: any) {
    console.error('Error saving category:', err)
    modalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan kategori.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  if (!categoryToDelete.value || isDeleting.value) return

  isDeleting.value = true
  try {
    await posStore.deleteCategory(categoryToDelete.value.id)
    showNotification('success', 'Kategori menu berhasil dihapus.')
    isDeleteModalOpen.value = false
    categoryToDelete.value = null
  } catch (err: any) {
    console.error('Error deleting category:', err)
    showNotification('error', 'Gagal menghapus kategori: ' + (err?.response?.data?.message || err.message))
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
          class="text-current opacity-70 hover:opacity-100 transition-opacity cursor-pointer">
          <AppIcon name="close" :size="18" />
        </button>
      </div>
    </transition>

    <!-- Header Page -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Kategori Menu</h1>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <div v-if="isLoading" class="h-10 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl animate-pulse"></div>
        <AppButton v-else @click="openAdd" variant="primary" size="md" icon="add" class="!rounded-lg !font-bold">
          Tambah Kategori
        </AppButton>
      </div>
    </div>

    <!-- Reusable Dashboard AppTable Component with Integrated Header -->
    <AppTable :columns="columns" :data="filteredCategories" :loading="isLoading" :pageSize="20" :searchable="false"
      showNumbering numberingLabel="No" emptyMessage="Belum ada kategori menu yang sesuai">
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <!-- Skeleton State for Filter & Search Bar -->
        <div v-if="isLoading"
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full animate-pulse">
          <div class="flex items-center gap-2">
            <div class="h-9 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-40 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
          </div>
          <div class="w-full sm:w-[250px] h-[38px] bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
        </div>

        <div v-else class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Filter Status Menu Dropdown -->
            <AppFilterDropdown v-model="selectedItemCountFilter" :options="filterItemCountOptions" width="w-48" />

            <!-- 2. Sort Dropdown -->
            <AppFilterDropdown v-model="selectedSort" :options="filterSortOptions" width="w-52" />
          </div>
          <!-- Right: Search Pool Input with Debouncing (Reusable AppInput matching filter height) -->
          <div class="w-full sm:w-[250px] shrink-0 ml-auto">
            <AppInput v-model="searchInput" placeholder="Cari nama kategori..." suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <!-- Cell Slot: Nama Kategori -->
      <template #cell-name="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">
          {{ row.name }}
        </span>
      </template>

      <!-- Cell Slot: Jumlah Menu -->
      <template #cell-itemCount="{ row }">
        <div class="flex items-center justify-center">
          <AppBadge :variant="getItemCount(row.id) > 0 ? 'primary' : 'gray'" size="md" rounded="full">
            {{ getItemCount(row.id) }} Menu
          </AppBadge>
        </div>
      </template>

      <!-- Cell Slot: Urutan Tampil -->
      <template #cell-sortOrder="{ row }">
        <span class="font-bold text-sm text-[#64748B] dark:text-[#94A3B8]">
          #{{ row.sortOrder || 0 }}
        </span>
      </template>

      <!-- Cell Slot: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button type="button" @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Kategori">
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
            <Transition enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-1">
              <div v-if="activeMenuCatId === row.id"
                class="absolute right-0 top-full mt-1.5 w-44 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                <!-- 1. Edit Kategori (Warning on hover) -->
                <button type="button" @click="openEdit(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Edit2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-150" />
                  <span>Edit Kategori</span>
                </button>

                <!-- 2. Hapus Kategori (Danger on hover) -->
                <button type="button" @click="confirmDelete(row)"
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

    <!-- Modal Form: Tambah / Edit Kategori -->
    <AppModal v-model="isModalOpen" :title="editingCategory ? 'Edit Kategori Menu' : 'Tambah Kategori Menu'"
      maxWidth="sm">
      <form @submit.prevent="saveCategory" class="space-y-4 py-2">
        <div v-if="modalError"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalError }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nama Kategori</label>
          <AppInput v-model="formName" placeholder="Kopi Signature, Makanan Utama" required />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Urutan Tampil (Prioritas)</label>
          <AppInput v-model.number="formSortOrder" type="number" min="1" placeholder="1" required />
        </div>
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isModalOpen = false" :disabled="isSubmitting">
            Batal
          </AppButton>
          <AppButton variant="primary" size="md" @click="saveCategory" :disabled="!formName.trim() || isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Kategori' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Konfirmasi Hapus Kategori -->
    <AppModal v-model="isDeleteModalOpen" title="Hapus Kategori" maxWidth="sm">
      <div v-if="categoryToDelete" class="space-y-3 py-1 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin menghapus kategori <span class="font-bold text-[#1E293B] dark:text-white">{{
            categoryToDelete.name }}</span>?
        </p>
        <p class="text-sm text-[#EF4444] bg-[#EF4444]/10 dark:bg-[#EF4444]/20 p-2.5 rounded-lg font-medium">
          Kategori yang dihapus tidak dapat dipulihkan. Menu yang ada di dalam kategori ini tidak akan memiliki kategori
          lagi.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isDeleteModalOpen = false" :disabled="isDeleting">
            Batal
          </AppButton>
          <AppButton variant="danger" size="md" @click="handleDelete" :disabled="isDeleting">
            {{ isDeleting ? 'Menghapus...' : 'Hapus' }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
