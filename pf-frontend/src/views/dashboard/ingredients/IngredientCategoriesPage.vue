<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, Edit2, Trash2 } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import type { IngredientCategory } from '@/types'

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
const editingCategory = ref<IngredientCategory | null>(null)
const categoryToDelete = ref<IngredientCategory | null>(null)
const formName = ref('')
const formDescription = ref('')
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
      posStore.fetchIngredientCategories(),
      posStore.fetchIngredients(),
    ])
  } catch (err: any) {
    showNotification('error', 'Gagal memuat kategori bahan: ' + (err?.message || 'Error'))
  } finally {
    isLoading.value = false
  }
}

const getIngredientCount = (catId: string) => {
  if (posStore.ingredients.length > 0) {
    return posStore.ingredients.filter(i => i.categoryId === catId).length
  }
  const found = posStore.ingredientCategories.find(c => c.id === catId)
  return found?.ingredientsCount || 0
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

// Search & Filters
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
const selectedSort = ref('asc') // 'asc', 'desc', 'items_desc', 'items_asc'

const filterItemCountOptions = [
  { value: 'all', label: 'Semua Kategori' },
  { value: 'has_items', label: 'Ada Bahan Baku' },
  { value: 'empty', label: 'Bahan Kosong' },
]

const filterSortOptions = [
  { value: 'asc', label: 'Ascending (A-Z)' },
  { value: 'desc', label: 'Descending (Z-A)' },
  { value: 'items_desc', label: 'Bahan Terbanyak' },
  { value: 'items_asc', label: 'Bahan Tersedikit' },
]

// Table Columns Configuration (Lebar simetris & proporsional)
const columns = [
  { key: 'name', label: 'Nama Kategori', width: '24%' },
  { key: 'description', label: 'Deskripsi / Keterangan', width: '51%' },
  { key: 'itemCount', label: 'Jumlah Bahan', align: 'center' as const, width: '15%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '10%' },
]

// Filtered & Sorted Categories
const filteredCategories = computed(() => {
  let list = [...posStore.ingredientCategories]

  // 1. Text Search Filter
  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter((cat) => cat.name.toLowerCase().includes(q) || (cat.description && cat.description.toLowerCase().includes(q)))
  }

  // 2. Item Count Filter
  if (selectedItemCountFilter.value === 'has_items') {
    list = list.filter((cat) => getIngredientCount(cat.id) > 0)
  } else if (selectedItemCountFilter.value === 'empty') {
    list = list.filter((cat) => getIngredientCount(cat.id) === 0)
  }

  // 3. Sorting
  if (selectedSort.value === 'asc' || selectedSort.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name))
  } else if (selectedSort.value === 'desc' || selectedSort.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name))
  } else if (selectedSort.value === 'items_desc') {
    list.sort((a, b) => getIngredientCount(b.id) - getIngredientCount(a.id))
  } else if (selectedSort.value === 'items_asc') {
    list.sort((a, b) => getIngredientCount(a.id) - getIngredientCount(b.id))
  }

  return list
})

// Modal Handlers
const openAdd = () => {
  editingCategory.value = null
  formName.value = ''
  formDescription.value = ''
  modalError.value = ''
  isModalOpen.value = true
}

const openEdit = (cat: IngredientCategory) => {
  editingCategory.value = cat
  formName.value = cat.name
  formDescription.value = cat.description || ''
  modalError.value = ''
  isModalOpen.value = true
}

const confirmDelete = (cat: IngredientCategory) => {
  categoryToDelete.value = cat
  isDeleteModalOpen.value = true
}

const saveCategory = async () => {
  if (!formName.value.trim() || isSubmitting.value) return

  modalError.value = ''
  isSubmitting.value = true

  try {
    if (editingCategory.value) {
      await posStore.updateIngredientCategory(editingCategory.value.id, {
        name: formName.value.trim(),
        description: formDescription.value.trim() || undefined,
      })
      showNotification('success', 'Kategori bahan baku berhasil diperbarui.')
    } else {
      await posStore.createIngredientCategory({
        name: formName.value.trim(),
        description: formDescription.value.trim() || undefined,
      })
      showNotification('success', 'Kategori bahan baru berhasil ditambahkan.')
    }
    isModalOpen.value = false
  } catch (err: any) {
    console.error('Error saving ingredient category:', err)
    modalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan kategori bahan.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  if (!categoryToDelete.value || isDeleting.value) return

  isDeleting.value = true
  try {
    await posStore.deleteIngredientCategory(categoryToDelete.value.id)
    showNotification('success', 'Kategori bahan baku berhasil dihapus.')
    isDeleteModalOpen.value = false
    categoryToDelete.value = null
  } catch (err: any) {
    console.error('Error deleting ingredient category:', err)
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
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Kategori Bahan Baku</h1>
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
      showNumbering numberingLabel="No" emptyMessage="Belum ada kategori bahan baku yang sesuai">
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
        <span class="font-bold text-sm text-[#202224] dark:text-white whitespace-nowrap">
          {{ row.name }}
        </span>
      </template>

      <!-- Cell Slot: Deskripsi / Keterangan -->
      <template #cell-description="{ row }">
        <p v-if="row.description" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1] leading-relaxed max-w-xl">
          {{ row.description }}
        </p>
        <span v-else class="text-sm text-slate-300 dark:text-slate-600 italic">
          -
        </span>
      </template>

      <!-- Cell Slot: Jumlah Bahan Baku -->
      <template #cell-itemCount="{ row }">
        <div class="flex items-center justify-center">
          <AppBadge :variant="getIngredientCount(row.id) > 0 ? 'primary' : 'gray'" size="md" rounded="full">
            {{ getIngredientCount(row.id) }} Bahan
          </AppBadge>
        </div>
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
    <AppModal v-model="isModalOpen" :title="editingCategory ? 'Edit Kategori Bahan Baku' : 'Tambah Kategori Bahan Baku'"
      maxWidth="sm">
      <form @submit.prevent="saveCategory" class="space-y-4 py-2">
        <div v-if="modalError"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalError }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">Nama Kategori</label>
          <AppInput v-model="formName" placeholder="Biji Kopi, Susu & Dairy, Sirup, Packaging" required />
        </div>

        <AppTextarea
          v-model="formDescription"
          label="Deskripsi / Keterangan (Opsional)"
          placeholder="Biji kopi espresso blend, single origin arabica/robusta, dan bubuk kopi dasar..."
          :rows="3"
        />
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
    <AppModal v-model="isDeleteModalOpen" title="Hapus Kategori Bahan Baku" maxWidth="sm">
      <div v-if="categoryToDelete" class="space-y-3 py-1 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin menghapus kategori <span class="font-bold text-[#202224] dark:text-white">{{
            categoryToDelete.name }}</span>?
        </p>
        <p class="text-xs text-[#EF4444] bg-[#EF4444]/10 dark:bg-[#EF4444]/20 p-2.5 rounded-lg font-medium">
          Bahan baku yang berada dalam kategori ini tidak akan terhapus, melainkan status kategorinya menjadi tanpa kategori.
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
