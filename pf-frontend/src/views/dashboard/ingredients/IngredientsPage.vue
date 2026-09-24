<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, Edit2, Trash2, AlertTriangle } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import type { Ingredient } from '@/types'

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
const editingIngredient = ref<Ingredient | null>(null)
const ingredientToDelete = ref<Ingredient | null>(null)

// Form states
const formCategoryId = ref('')
const formName = ref('')
const formUnit = ref('g')
const formCurrentStock = ref<number | ''>(0)
const formLowStockThreshold = ref<number | ''>('')
const formCostPerUnit = ref<number | ''>('')
const formIsActive = ref(true)
const modalError = ref('')

const unitOptions = [
  { value: 'g', label: 'Gram (g)' },
  { value: 'kg', label: 'Kilogram (kg)' },
  { value: 'ml', label: 'Mililiter (ml)' },
  { value: 'l', label: 'Liter (l)' },
  { value: 'pcs', label: 'Pcs / Butir / Biji' },
  { value: 'lembar', label: 'Lembar' },
  { value: 'porsi', label: 'Porsi' },
]

// Popover Menu Titik Tiga (Aksi Bahan)
const activeMenuId = ref<string | null>(null)
const toggleMenu = (id: string) => {
  activeMenuId.value = activeMenuId.value === id ? null : id
}
const closeMenu = () => {
  activeMenuId.value = null
}

const loadData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchIngredients(),
      posStore.fetchIngredientCategories()
    ])
  } catch (err: any) {
    showNotification('error', 'Gagal memuat data bahan baku: ' + (err?.message || 'Error'))
  } finally {
    isLoading.value = false
  }
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

// Search & Filter state
const searchInput = ref('')
const debouncedSearch = ref('')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (newVal) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
  }, 300)
})

const selectedCategoryId = ref('all')
const selectedStockFilter = ref('all') // 'all', 'low_stock', 'in_stock'
const selectedSort = ref('name_asc')

const categoryFilterOptions = computed(() => [
  { value: 'all', label: 'Semua Kategori' },
  ...posStore.ingredientCategories.map(c => ({ value: c.id, label: c.name }))
])

const stockFilterOptions = [
  { value: 'all', label: 'Semua Stok' },
  { value: 'low_stock', label: 'Stok Menipis' },
  { value: 'in_stock', label: 'Stok Aman' },
]

const sortOptions = [
  { value: 'name_asc', label: 'Nama (A-Z)' },
  { value: 'name_desc', label: 'Nama (Z-A)' },
  { value: 'stock_asc', label: 'Stok Terendah' },
  { value: 'stock_desc', label: 'Stok Tertinggi' },
  { value: 'purchase_desc', label: 'Harga Beli Tertinggi' },
  { value: 'purchase_asc', label: 'Harga Beli Terendah' },
  { value: 'cost_desc', label: 'Harga Satuan Tertinggi' },
  { value: 'cost_asc', label: 'Harga Satuan Terendah' },
]

const formCategoryOptions = computed(() => [
  { value: '', label: '-- Tanpa Kategori --' },
  ...posStore.ingredientCategories.map(c => ({ value: c.id, label: c.name }))
])

// Table Columns Configuration (Lebar simetris & proporsional)
const columns = [
  { key: 'name', label: 'Nama Bahan', width: '21%' },
  { key: 'category', label: 'Kategori Bahan', width: '15%' },
  { key: 'displayStock', label: 'Stok Saat Ini', align: 'right' as const, width: '13%' },
  { key: 'lowStockThreshold', label: 'Batas Minimum', align: 'right' as const, width: '11%' },
  { key: 'purchasePrice', label: 'Harga Beli', align: 'right' as const, width: '14%' },
  { key: 'costPerUnit', label: 'Harga Satuan', align: 'right' as const, width: '12%' },
  { key: 'status', label: 'Status', align: 'center' as const, width: '8%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '6%' },
]

// Filtered & Sorted Ingredients
const filteredIngredients = computed(() => {
  let list = [...(posStore.ingredients || [])]

  // 1. Text Search Filter
  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter(i => i.name.toLowerCase().includes(q))
  }

  // 2. Category Filter
  if (selectedCategoryId.value && selectedCategoryId.value !== 'all') {
    list = list.filter(i => i.categoryId === selectedCategoryId.value)
  }

  // 3. Stock Status Filter
  if (selectedStockFilter.value === 'low_stock') {
    list = list.filter(i => i.isLowStock)
  } else if (selectedStockFilter.value === 'in_stock') {
    list = list.filter(i => !i.isLowStock)
  }

  // 4. Sorting
  if (selectedSort.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name))
  } else if (selectedSort.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name))
  } else if (selectedSort.value === 'stock_asc') {
    list.sort((a, b) => (a.displayStock ?? a.currentStock) - (b.displayStock ?? b.currentStock))
  } else if (selectedSort.value === 'stock_desc') {
    list.sort((a, b) => (b.displayStock ?? b.currentStock) - (a.displayStock ?? a.currentStock))
  } else if (selectedSort.value === 'purchase_desc') {
    list.sort((a, b) => getPurchasePriceInfo(b).price - getPurchasePriceInfo(a).price)
  } else if (selectedSort.value === 'purchase_asc') {
    list.sort((a, b) => getPurchasePriceInfo(a).price - getPurchasePriceInfo(b).price)
  } else if (selectedSort.value === 'cost_desc') {
    list.sort((a, b) => getBaseUnitCostInfo(b).cost - getBaseUnitCostInfo(a).cost)
  } else if (selectedSort.value === 'cost_asc') {
    list.sort((a, b) => getBaseUnitCostInfo(a).cost - getBaseUnitCostInfo(b).cost)
  }

  return list
})

const getCategoryName = (row: Ingredient) => {
  if (row.category?.name) return row.category.name
  if (row.categoryId) {
    const found = posStore.ingredientCategories.find(c => c.id === row.categoryId)
    if (found) return found.name
  }
  return null
}

const getPurchasePriceInfo = (item: Ingredient) => {
  if (item.purchasePrice !== undefined && item.purchasePrice !== null && item.purchaseUnit) {
    return { price: item.purchasePrice, unit: item.purchaseUnit }
  }
  const unit = (item.unit || '').toLowerCase()
  const cost = item.costPerUnit || 0

  if (unit === 'g' || unit === 'gram') {
    if (cost < 1000) {
      return { price: cost * 1000, unit: 'kg' }
    }
    return { price: cost, unit: 'kg' }
  }

  if (unit === 'ml' || unit === 'mililiter') {
    if (cost < 500) {
      return { price: cost * 1000, unit: 'l' }
    }
    return { price: cost, unit: 'l' }
  }

  return { price: cost, unit: item.unit }
}

const getBaseUnitCostInfo = (item: Ingredient) => {
  if (item.baseCostPerUnit !== undefined && item.baseCostPerUnit !== null && item.baseUnitDisplay) {
    return { cost: item.baseCostPerUnit, unit: item.baseUnitDisplay }
  }
  const unit = (item.unit || '').toLowerCase()
  const cost = item.costPerUnit || 0

  if (unit === 'kg' || unit === 'kilogram') {
    return { cost: Math.round(cost / 1000 * 100) / 100, unit: 'g' }
  }

  if (unit === 'l' || unit === 'liter') {
    return { cost: Math.round(cost / 1000 * 100) / 100, unit: 'ml' }
  }

  if (unit === 'g' || unit === 'gram') {
    const baseCost = cost >= 1000 ? Math.round(cost / 1000 * 100) / 100 : cost
    return { cost: baseCost, unit: 'g' }
  }

  if (unit === 'ml' || unit === 'mililiter') {
    const baseCost = cost >= 500 ? Math.round(cost / 1000 * 100) / 100 : cost
    return { cost: baseCost, unit: 'ml' }
  }

  return { cost: cost, unit: item.unit }
}

const formatNumber = (val?: number | null) => {
  if (val === null || val === undefined) return '-'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(val)
}

const formatCurrency = (val?: number | null) => {
  if (!val) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val)
}

// Modal Handlers
const openAddModal = () => {
  editingIngredient.value = null
  formCategoryId.value = ''
  formName.value = ''
  formUnit.value = 'g'
  formCurrentStock.value = 0
  formLowStockThreshold.value = ''
  formCostPerUnit.value = ''
  formIsActive.value = true
  modalError.value = ''
  isModalOpen.value = true
}

const openEditModal = (item: Ingredient) => {
  closeMenu()
  editingIngredient.value = item
  formCategoryId.value = item.categoryId || ''
  formName.value = item.name
  formUnit.value = item.unit
  formCurrentStock.value = item.displayStock ?? item.currentStock
  formLowStockThreshold.value = item.lowStockThreshold ?? ''
  formCostPerUnit.value = item.costPerUnit ?? ''
  formIsActive.value = item.isActive !== false
  modalError.value = ''
  isModalOpen.value = true
}

const confirmDelete = (item: Ingredient) => {
  closeMenu()
  ingredientToDelete.value = item
  isDeleteModalOpen.value = true
}

const handleSave = async () => {
  if (!formName.value.trim()) {
    modalError.value = 'Nama bahan baku wajib diisi.'
    return
  }
  if (!formUnit.value) {
    modalError.value = 'Satuan wajib dipilih.'
    return
  }

  modalError.value = ''
  isSubmitting.value = true

  try {
    if (editingIngredient.value) {
      await posStore.updateIngredient(editingIngredient.value.id, {
        category_id: formCategoryId.value || null,
        name: formName.value.trim(),
        unit: formUnit.value,
        low_stock_threshold: formLowStockThreshold.value !== '' ? Number(formLowStockThreshold.value) : null,
        cost_per_unit: formCostPerUnit.value !== '' ? Number(formCostPerUnit.value) : null,
        is_active: formIsActive.value,
      })
      showNotification('success', 'Bahan baku berhasil diperbarui.')
    } else {
      await posStore.createIngredient({
        category_id: formCategoryId.value || null,
        name: formName.value.trim(),
        unit: formUnit.value,
        current_stock: formCurrentStock.value !== '' ? Number(formCurrentStock.value) : 0,
        low_stock_threshold: formLowStockThreshold.value !== '' ? Number(formLowStockThreshold.value) : undefined,
        cost_per_unit: formCostPerUnit.value !== '' ? Number(formCostPerUnit.value) : undefined,
      })
      showNotification('success', 'Bahan baku baru berhasil ditambahkan.')
    }
    isModalOpen.value = false
  } catch (err: any) {
    modalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan bahan baku.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  if (!ingredientToDelete.value || isDeleting.value) return
  isDeleting.value = true
  try {
    await posStore.deleteIngredient(ingredientToDelete.value.id)
    showNotification('success', `Bahan baku '${ingredientToDelete.value.name}' berhasil dihapus.`)
    isDeleteModalOpen.value = false
    ingredientToDelete.value = null
  } catch (err: any) {
    showNotification('error', err?.response?.data?.message || err?.message || 'Gagal menghapus bahan baku.')
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
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Daftar Bahan Baku</h1>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <div v-if="isLoading" class="h-10 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl animate-pulse"></div>
        <AppButton v-else @click="openAddModal" variant="primary" size="md" icon="add" class="!rounded-lg !font-bold">
          Tambah Bahan Baku
        </AppButton>
      </div>
    </div>

    <!-- Reusable Dashboard AppTable Component with Integrated Header -->
    <AppTable :columns="columns" :data="filteredIngredients" :loading="isLoading" :pageSize="20" :searchable="false"
      showNumbering numberingLabel="No" emptyMessage="Belum ada bahan baku yang sesuai">
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <!-- Skeleton State for Filter & Search Bar -->
        <div v-if="isLoading"
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full animate-pulse">
          <div class="flex items-center gap-2">
            <div class="h-9 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-32 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
          </div>
          <div class="w-full sm:w-[250px] h-[38px] bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
        </div>

        <div v-else class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex flex-wrap items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Category Filter -->
            <AppFilterDropdown v-model="selectedCategoryId" :options="categoryFilterOptions" width="w-48" />

            <!-- 2. Stock Status Filter -->
            <AppFilterDropdown v-model="selectedStockFilter" :options="stockFilterOptions" width="w-40" />

            <!-- 3. Sort Filter -->
            <AppFilterDropdown v-model="selectedSort" :options="sortOptions" width="w-48" />
          </div>

          <!-- Right: Search Pool Input with Debouncing (Reusable AppInput matching filter height) -->
          <div class="w-full sm:w-[250px] shrink-0 ml-auto">
            <AppInput v-model="searchInput" placeholder="Cari nama bahan baku..." suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <!-- Cell Slot: Nama Bahan Baku -->
      <template #cell-name="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">
          {{ row.name }}
        </span>
      </template>

      <!-- Cell Slot: Kategori Bahan Baku (Teks Bersih Tanpa Badge) -->
      <template #cell-category="{ row }">
        <span v-if="getCategoryName(row)" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1]">
          {{ getCategoryName(row) }}
        </span>
        <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">
          -
        </span>
      </template>

      <!-- Cell Slot: Stok Saat Ini -->
      <template #cell-displayStock="{ row }">
        <div class="flex items-center justify-end gap-1.5">
          <!-- Merah jika stok habis -->
          <span v-if="(row.displayStock ?? row.currentStock) <= 0" title="Stok Habis"
            class="inline-flex shrink-0 cursor-default">
            <AlertTriangle class="w-4 h-4 text-red-500">
              <title>Stok Habis</title>
            </AlertTriangle>
          </span>

          <!-- Kuning jika stok menipis -->
          <span
            v-else-if="row.isLowStock || (row.lowStockThreshold && (row.displayStock ?? row.currentStock) <= row.lowStockThreshold)"
            title="Stok Menipis" class="inline-flex shrink-0 cursor-default">
            <AlertTriangle class="w-4 h-4 text-amber-500">
              <title>Stok Menipis</title>
            </AlertTriangle>
          </span>

          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatNumber(row.displayStock ?? row.currentStock) }}
          </span>
          <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] ml-1.5">{{ row.unit }}</span>
        </div>
      </template>

      <!-- Cell Slot: Batas Minimum -->
      <template #cell-lowStockThreshold="{ row }">
        <div class="text-right">
          <template v-if="row.lowStockThreshold">
            <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
              {{ formatNumber(row.displayLowStockThreshold ?? row.lowStockThreshold) }}
            </span>
            <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] ml-1.5">{{ row.unit }}</span>
          </template>
          <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">
            -
          </span>
        </div>
      </template>

      <!-- Cell Slot: Harga Beli (Contoh: Rp 250.000 / kg) -->
      <template #cell-purchasePrice="{ row }">
        <div class="text-right">
          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatCurrency(getPurchasePriceInfo(row).price) }}
          </span>
          <span class="text-xs font-normal text-[#64748B] dark:text-[#94A3B8] ml-1">/ {{ getPurchasePriceInfo(row).unit
          }}</span>
        </div>
      </template>

      <!-- Cell Slot: Harga Satuan Dasar (Contoh: Rp 250 / g) -->
      <template #cell-costPerUnit="{ row }">
        <div class="text-right">
          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatCurrency(getBaseUnitCostInfo(row).cost) }}
          </span>
          <span class="text-xs font-normal text-[#64748B] dark:text-[#94A3B8] ml-1">/ {{ getBaseUnitCostInfo(row).unit
          }}</span>
        </div>
      </template>

      <!-- Cell Slot: Status (AppBadge Success / Neutral) -->
      <template #cell-status="{ row }">
        <div class="flex items-center justify-center">
          <AppBadge :variant="row.isActive !== false ? 'success' : 'neutral'" size="md" rounded="full">
            {{ row.isActive !== false ? 'Aktif' : 'Non-Aktif' }}
          </AppBadge>
        </div>
      </template>

      <!-- Cell Slot: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button type="button" @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Bahan Baku">
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
            <Transition enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-1">
              <div v-if="activeMenuId === row.id"
                class="absolute right-0 top-full mt-1.5 w-44 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                <!-- 1. Edit Bahan Baku -->
                <button type="button" @click="openEditModal(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Edit2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-150" />
                  <span>Edit Bahan</span>
                </button>

                <!-- 2. Hapus Bahan Baku -->
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

    <!-- Modal Form (Tambah / Edit Bahan) -->
    <AppModal v-model="isModalOpen" :title="editingIngredient ? 'Edit Bahan Baku' : 'Tambah Bahan Baku'" maxWidth="md">
      <form @submit.prevent="handleSave" class="space-y-4 text-xs py-1">
        <div v-if="modalError"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalError }}
        </div>

        <!-- Input: Nama Bahan Baku -->
        <AppInput
          v-model="formName"
          label="Nama Bahan Baku"
          placeholder="Kopi Bubuk Robusta, Susu UHT"
          required
        />

        <!-- Input: Kategori & Satuan Dasar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <AppSelect
            v-model="formCategoryId"
            label="Kategori Bahan"
            :options="formCategoryOptions"
            placeholder="Pilih Kategori"
          />
          <AppSelect
            v-model="formUnit"
            label="Satuan Dasar"
            :options="unitOptions"
            placeholder="Pilih Satuan Dasar"
            required
          />
        </div>

        <!-- Input: Stok Awal (Hanya saat Tambah Baru) -->
        <div v-if="!editingIngredient">
          <AppInput
            v-model="formCurrentStock"
            type="number"
            :label="`Stok Awal (${formUnit})`"
            placeholder="0"
            min="0"
          />
        </div>

        <!-- Input: Batas Minimum Stok & Harga Beli -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <AppInput
            v-model="formLowStockThreshold"
            type="number"
            label="Batas Minimum Stok"
            placeholder="100"
            min="0"
          />
          <div class="space-y-1.5">
            <AppInput
              v-model="formCostPerUnit"
              type="number"
              :label="`Harga Beli (Rp) per ${formUnit}`"
              prefix="Rp"
              placeholder="250000"
              min="0"
            />
            <p
              v-if="formCostPerUnit && (formUnit === 'kg' || formUnit === 'l')"
              class="text-xs font-medium text-[#64748B] dark:text-[#94A3B8] mt-1 leading-relaxed"
            >
              Estimasi Harga Satuan: <strong class="text-[#202224] dark:text-white font-bold">{{ formatCurrency(Number(formCostPerUnit) / 1000) }} / {{ formUnit === 'kg' ? 'g' : 'ml' }}</strong>
            </p>
          </div>
        </div>

        <!-- Opsi Status Aktif ketika Edit -->
        <div v-if="editingIngredient" class="flex items-center gap-2 pt-1">
          <input
            type="checkbox"
            id="formIsActive"
            v-model="formIsActive"
            class="w-4 h-4 text-[#4880FF] rounded border-gray-300 dark:border-slate-700 focus:ring-[#4880FF] cursor-pointer"
          />
          <label
            for="formIsActive"
            class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-[#CBD5E1] cursor-pointer select-none"
          >
            Status Bahan Aktif
          </label>
        </div>
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2.5">
          <AppButton
            type="button"
            @click="isModalOpen = false"
            variant="outline"
            size="md"
            class="!rounded-lg px-5 font-bold"
            :disabled="isSubmitting"
          >
            Batal
          </AppButton>
          <AppButton
            type="button"
            @click="handleSave"
            variant="primary"
            size="md"
            class="!rounded-lg px-6 font-bold"
            :disabled="!formName.trim() || isSubmitting"
          >
            {{ isSubmitting ? 'Menyimpan...' : (editingIngredient ? 'Simpan Perubahan' : 'Tambah Bahan Baku') }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Konfirmasi Hapus -->
    <AppModal v-model="isDeleteModalOpen" title="Hapus Bahan Baku" maxWidth="sm">
      <div class="space-y-3 py-1 text-sm">
        <p class="text-[#475569] dark:text-[#CBD5E1] leading-relaxed">
          Apakah Anda yakin ingin menghapus bahan baku <strong class="text-[#1E293B] dark:text-white">{{ ingredientToDelete?.name }}</strong>? Tindakan ini tidak dapat dibatalkan.
        </p>
        <p class="text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-xl border border-amber-200 dark:border-amber-900 leading-relaxed font-medium">
          Catatan: Bahan baku tidak dapat dihapus jika sedang digunakan di resep menu aktif.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2.5">
          <AppButton
            type="button"
            @click="isDeleteModalOpen = false"
            variant="outline"
            size="md"
            class="!rounded-lg px-5 font-bold"
            :disabled="isDeleting"
          >
            Batal
          </AppButton>
          <AppButton
            type="button"
            @click="handleDelete"
            variant="danger"
            size="md"
            class="!rounded-lg px-6 font-bold"
            :disabled="isDeleting"
          >
            {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Bahan' }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
