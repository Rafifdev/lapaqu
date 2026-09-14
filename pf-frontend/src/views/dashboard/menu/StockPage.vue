<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable from '@/components/ui/AppTable.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppToggle from '@/components/ui/AppToggle.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import type { MenuItem, Ingredient } from '@/types'

const posStore = usePosStore()
const { formatCurrency } = useFormat()

const activeTab = ref<'ingredients' | 'recipes'>('ingredients')
const isLoading = ref(true)

// Notifications / Toast
const toastMessage = ref('')
const toastType = ref<'success' | 'danger'>('success')
const showToast = ref(false)
let toastTimer: any = null

const notify = (msg: string, type: 'success' | 'danger' = 'success') => {
  toastMessage.value = msg
  toastType.value = type
  showToast.value = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    showToast.value = false
  }, 2500)
}

// Load data
const loadData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchIngredients(),
      posStore.fetchMenuItems(),
      posStore.fetchCategories(),
    ])
  } catch (err: any) {
    console.error('Failed to load data:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadData()
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

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (toastTimer) clearTimeout(toastTimer)
})

// ==========================================
// TAB 1: BAHAN BAKU (INGREDIENTS)
// ==========================================
const selectedIngredientFilter = ref('all') // 'all', 'low_stock', 'out_of_stock', 'safe'
const ingredientFilterOptions = [
  { value: 'all', label: 'Status: Semua Bahan' },
  { value: 'low_stock', label: 'Status: Stok Menipis' },
  { value: 'out_of_stock', label: 'Status: Stok Habis' },
  { value: 'safe', label: 'Status: Stok Aman' },
]

const ingredientColumns = [
  { key: 'name', label: 'Nama Bahan Baku', width: '28%' },
  { key: 'unit', label: 'Satuan', align: 'center' as const, width: '15%' },
  { key: 'currentStock', label: 'Stok Saat Ini', align: 'center' as const, width: '18%' },
  { key: 'lowStockThreshold', label: 'Batas Minimum', align: 'center' as const, width: '15%' },
  { key: 'costPerUnit', label: 'Biaya / Satuan', align: 'right' as const, width: '14%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '10%' },
]

const filteredIngredients = computed(() => {
  let list = [...posStore.ingredients]

  if (debouncedSearch.value && activeTab.value === 'ingredients') {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter((item: Ingredient) => item.name.toLowerCase().includes(q))
  }

  if (selectedIngredientFilter.value === 'low_stock') {
    list = list.filter((i: Ingredient) => i.isLowStock && i.currentStock > 0)
  } else if (selectedIngredientFilter.value === 'out_of_stock') {
    list = list.filter((i: Ingredient) => i.currentStock <= 0)
  } else if (selectedIngredientFilter.value === 'safe') {
    list = list.filter((i: Ingredient) => !i.isLowStock && i.currentStock > 0)
  }

  return list
})

// Ingredient Stats
const totalIngredientsCount = computed(() => posStore.ingredients.length)
const lowStockCount = computed(() => posStore.ingredients.filter((i: Ingredient) => i.isLowStock && i.currentStock > 0).length)
const outOfStockCount = computed(() => posStore.ingredients.filter((i: Ingredient) => i.currentStock <= 0).length)
const safeStockCount = computed(() => posStore.ingredients.filter((i: Ingredient) => !i.isLowStock && i.currentStock > 0).length)

// Unit Presets for Dropdown
const unitPresets = [
  { value: 'gram', label: 'Gram (g) - Satuan Berat' },
  { value: 'kg', label: 'Kilogram (kg) - Satuan Berat' },
  { value: 'ml', label: 'Mililiter (ml) - Satuan Volume' },
  { value: 'liter', label: 'Liter (l) - Satuan Volume' },
  { value: 'pcs', label: 'Pcs / Buah - Satuan Unit' },
  { value: 'butir', label: 'Butir - Telur / Buah' },
  { value: 'lembar', label: 'Lembar - Keju / Roti' },
  { value: 'porsi', label: 'Porsi - Siap Saji' },
]

// Ingredient Modal State (Add / Edit)
const isIngredientModalOpen = ref(false)
const isEditingIngredient = ref(false)
const editingIngredientId = ref<string | null>(null)
const formIngName = ref('')
const formIngUnit = ref('gram')
const formIngStock = ref<number | ''>('')
const formIngThreshold = ref<number | ''>('')
const formIngCost = ref<number | ''>('')
const ingModalError = ref('')
const isSavingIngredient = ref(false)

const openAddIngredient = () => {
  isEditingIngredient.value = false
  editingIngredientId.value = null
  formIngName.value = ''
  formIngUnit.value = 'gram'
  formIngStock.value = ''
  formIngThreshold.value = ''
  formIngCost.value = ''
  ingModalError.value = ''
  isIngredientModalOpen.value = true
}

const openEditIngredient = (item: Ingredient) => {
  isEditingIngredient.value = true
  editingIngredientId.value = item.id
  formIngName.value = item.name
  formIngUnit.value = item.unit
  formIngStock.value = item.displayStock
  formIngThreshold.value = item.lowStockThreshold !== null && item.lowStockThreshold !== undefined ? item.lowStockThreshold : ''
  formIngCost.value = item.costPerUnit || ''
  ingModalError.value = ''
  isIngredientModalOpen.value = true
}

const saveIngredient = async () => {
  if (!formIngName.value.trim() || !formIngUnit.value.trim()) {
    ingModalError.value = 'Nama dan satuan wajib diisi.'
    return
  }

  isSavingIngredient.value = true
  ingModalError.value = ''

  try {
    if (isEditingIngredient.value && editingIngredientId.value) {
      await posStore.updateIngredient(editingIngredientId.value, {
        name: formIngName.value.trim(),
        unit: formIngUnit.value.trim(),
        low_stock_threshold: formIngThreshold.value === '' ? null : Number(formIngThreshold.value),
        cost_per_unit: formIngCost.value === '' ? null : Number(formIngCost.value),
      })
      notify('Bahan baku berhasil diperbarui!')
    } else {
      await posStore.createIngredient({
        name: formIngName.value.trim(),
        unit: formIngUnit.value.trim(),
        current_stock: formIngStock.value === '' ? 0 : Number(formIngStock.value),
        low_stock_threshold: formIngThreshold.value === '' ? null : Number(formIngThreshold.value),
        cost_per_unit: formIngCost.value === '' ? null : Number(formIngCost.value),
      })
      notify('Bahan baku berhasil ditambahkan!')
    }
    isIngredientModalOpen.value = false
  } catch (err: any) {
    ingModalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan bahan baku.'
  } finally {
    isSavingIngredient.value = false
  }
}

// Adjust Stock Modal State
const isAdjustModalOpen = ref(false)
const selectedIngredientForAdjust = ref<Ingredient | null>(null)
const adjustType = ref<'restock' | 'set'>('restock')
const adjustQuantity = ref<number | ''>('')
const adjustError = ref('')
const isAdjusting = ref(false)

const openAdjustStock = (item: Ingredient) => {
  selectedIngredientForAdjust.value = item
  adjustType.value = 'restock'
  adjustQuantity.value = ''
  adjustError.value = ''
  isAdjustModalOpen.value = true
}

const previewNewStock = computed(() => {
  if (!selectedIngredientForAdjust.value) return 0
  const current = selectedIngredientForAdjust.value.displayStock
  const qty = Number(adjustQuantity.value) || 0
  if (adjustType.value === 'restock') {
    return Math.max(0, current + qty)
  } else {
    return Math.max(0, qty)
  }
})

const saveAdjustStock = async () => {
  if (!selectedIngredientForAdjust.value || adjustQuantity.value === '') {
    adjustError.value = 'Jumlah penyesuaian wajib diisi.'
    return
  }

  isAdjusting.value = true
  adjustError.value = ''

  try {
    await posStore.adjustIngredientStock(selectedIngredientForAdjust.value.id, {
      quantity: Number(adjustQuantity.value),
      type: adjustType.value,
    })
    notify(
      adjustType.value === 'restock'
        ? `Stok ${selectedIngredientForAdjust.value.name} berhasil ditambah.`
        : `Stok ${selectedIngredientForAdjust.value.name} berhasil diatur ulang.`
    )
    isAdjustModalOpen.value = false
  } catch (err: any) {
    adjustError.value = err?.response?.data?.message || err?.message || 'Gagal menyesuaikan stok.'
  } finally {
    isAdjusting.value = false
  }
}

// Delete Ingredient Modal State
const isDeleteModalOpen = ref(false)
const ingredientToDelete = ref<Ingredient | null>(null)
const isDeleting = ref(false)
const deleteError = ref('')

const openDeleteIngredient = (item: Ingredient) => {
  ingredientToDelete.value = item
  deleteError.value = ''
  isDeleteModalOpen.value = true
}

const confirmDeleteIngredient = async () => {
  if (!ingredientToDelete.value) return
  isDeleting.value = true
  deleteError.value = ''

  try {
    await posStore.deleteIngredient(ingredientToDelete.value.id)
    notify(`Bahan ${ingredientToDelete.value.name} berhasil dihapus.`)
    isDeleteModalOpen.value = false
  } catch (err: any) {
    deleteError.value = err?.response?.data?.message || err?.message || 'Gagal menghapus bahan baku.'
  } finally {
    isDeleting.value = false
  }
}

// ==========================================
// TAB 2: RESEP & PORSI MENU (MENU RECIPES)
// ==========================================
const selectedCategoryFilter = ref('all')
const selectedAvailabilityFilter = ref('all')

const availabilityOptions = [
  { value: 'all', label: 'Status: Semua Menu' },
  { value: 'available', label: 'Status: Tersedia' },
  { value: 'unavailable', label: 'Status: Habis' },
]

const categoryFilterOptions = computed(() => {
  return [
    { value: 'all', label: 'Kategori: Semua' },
    ...posStore.categories.map((c: any) => ({ value: c.id, label: `Kategori: ${c.name}` })),
  ]
})

const menuColumns = [
  { key: 'name', label: 'Menu Hidangan', width: '30%' },
  { key: 'price', label: 'Harga Jual', align: 'right' as const, width: '14%' },
  { key: 'recipes', label: 'Komposisi Resep', width: '26%' },
  { key: 'maxServings', label: 'Estimasi Porsi', align: 'center' as const, width: '16%' },
  { key: 'isAvailable', label: 'Status & Aksi', align: 'center' as const, width: '14%' },
]

const filteredMenuItems = computed(() => {
  let list = [...posStore.menuItems]

  if (debouncedSearch.value && activeTab.value === 'recipes') {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter(
      (item: MenuItem) =>
        item.name.toLowerCase().includes(q) ||
        Boolean(item.description && item.description.toLowerCase().includes(q))
    )
  }

  if (selectedCategoryFilter.value !== 'all') {
    list = list.filter((item: MenuItem) => item.categoryId === selectedCategoryFilter.value)
  }

  if (selectedAvailabilityFilter.value === 'available') {
    list = list.filter((item: MenuItem) => item.isAvailable !== false)
  } else if (selectedAvailabilityFilter.value === 'unavailable') {
    list = list.filter((item: MenuItem) => item.isAvailable === false)
  }

  return list
})

const handleToggleAvailability = async (item: MenuItem) => {
  try {
    await posStore.toggleMenuItemAvailability(item.id)
    notify(`Status ketersediaan ${item.name} berhasil diperbarui.`)
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Gagal mengubah status ketersediaan.', 'danger')
  }
}

// Recipe Management Modal State
const isRecipeModalOpen = ref(false)
const selectedMenuItemForRecipe = ref<MenuItem | null>(null)
const recipeRows = ref<Array<{ ingredient_id: string; quantity_needed: number | '' }>>([])
const recipeModalError = ref('')
const isSavingRecipe = ref(false)

const openRecipeModal = (item: MenuItem) => {
  selectedMenuItemForRecipe.value = item
  recipeModalError.value = ''

  if (item.recipes && item.recipes.length > 0) {
    recipeRows.value = item.recipes.map((r: any) => ({
      ingredient_id: r.ingredientId,
      quantity_needed: r.quantityNeeded,
    }))
  } else {
    recipeRows.value = [{ ingredient_id: posStore.ingredients[0]?.id || '', quantity_needed: '' }]
  }

  isRecipeModalOpen.value = true
}

const addRecipeRow = () => {
  const unusedIngredient = posStore.ingredients.find(
    (ing: Ingredient) => !recipeRows.value.some((row) => row.ingredient_id === ing.id)
  )
  recipeRows.value.push({
    ingredient_id: unusedIngredient?.id || posStore.ingredients[0]?.id || '',
    quantity_needed: '',
  })
}

const removeRecipeRow = (index: number) => {
  recipeRows.value.splice(index, 1)
}

const getIngredientById = (id: string) => {
  return posStore.ingredients.find((i: Ingredient) => i.id === id)
}

// Preview max servings in Recipe Modal
const modalPreviewServings = computed(() => {
  if (recipeRows.value.length === 0) return null
  let minServings: number | null = null

  for (const row of recipeRows.value) {
    if (!row.ingredient_id || row.quantity_needed === '' || Number(row.quantity_needed) <= 0) {
      continue
    }
    const ing = getIngredientById(row.ingredient_id)
    if (!ing) continue

    let neededInBase = Number(row.quantity_needed)
    if (ing.unit === 'kg') neededInBase = Number(row.quantity_needed) * 1000
    if (ing.unit === 'liter') neededInBase = Number(row.quantity_needed) * 1000

    const possible = Math.floor(ing.currentStock / neededInBase)
    if (minServings === null || possible < minServings) {
      minServings = possible
    }
  }

  return minServings
})

const saveRecipe = async () => {
  if (!selectedMenuItemForRecipe.value) return

  // Validate
  const validRecipes: Array<{ ingredient_id: string; quantity_needed: number }> = []
  for (const row of recipeRows.value) {
    if (!row.ingredient_id) continue
    const qty = Number(row.quantity_needed)
    if (isNaN(qty) || qty <= 0) {
      recipeModalError.value = 'Semua takaran bahan yang dimasukkan harus lebih besar dari 0.'
      return
    }
    validRecipes.push({
      ingredient_id: row.ingredient_id,
      quantity_needed: qty,
    })
  }

  isSavingRecipe.value = true
  recipeModalError.value = ''

  try {
    await posStore.updateMenuItemRecipe(selectedMenuItemForRecipe.value.id, validRecipes)
    notify(`Resep untuk ${selectedMenuItemForRecipe.value.name} berhasil disimpan!`)
    isRecipeModalOpen.value = false
  } catch (err: any) {
    recipeModalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan resep.'
  } finally {
    isSavingRecipe.value = false
  }
}

const handleImageError = (e: Event) => {
  const target = e.target as HTMLImageElement
  if (target) {
    target.src = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Toast Notification -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showToast"
        :class="[
          'fixed bottom-5 right-5 z-[999999] px-4 py-3 rounded-xl shadow-xl flex items-center gap-2.5 text-sm font-semibold text-white',
          toastType === 'success' ? 'bg-[#00B69B]' : 'bg-rose-500'
        ]"
      >
        <AppIcon :name="toastType === 'success' ? 'check_circle' : 'error'" :size="20" class="text-white shrink-0" />
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- Header Page & Tab Switcher -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-[#202224] dark:text-white tracking-tight">
          Manajemen Stok & Resep Menu
        </h1>
        <p class="text-xs sm:text-sm text-[#64748B] dark:text-[#94A3B8] mt-1">
          Kelola stok bahan baku, rumus takaran porsi, dan ketersediaan menu secara otomatis.
        </p>
      </div>

      <!-- Segmented Tab Navigation -->
      <div class="flex items-center gap-1.5 p-1.5 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-2xl shrink-0 self-start sm:self-auto border border-[#E2E8F0]/80 dark:border-[#334155]/60">
        <button
          type="button"
          @click="activeTab = 'ingredients'"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer',
            activeTab === 'ingredients'
              ? 'bg-white dark:bg-[#273142] text-[#4880FF] shadow-sm'
              : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'
          ]"
        >
          <AppIcon name="inventory_2" :size="18" />
          <span>Bahan Baku ({{ posStore.ingredients.length }})</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'recipes'"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer',
            activeTab === 'recipes'
              ? 'bg-white dark:bg-[#273142] text-[#4880FF] shadow-sm'
              : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'
          ]"
        >
          <AppIcon name="restaurant_menu" :size="18" />
          <span>Resep & Porsi Menu ({{ posStore.menuItems.length }})</span>
        </button>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 1: BAHAN BAKU CONTENT -->
    <!-- ======================================================== -->
    <div v-if="activeTab === 'ingredients'" class="space-y-6">
      <!-- Summary Stat Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Bahan -->
        <div class="p-4 rounded-2xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wider">Total Bahan</p>
            <h3 class="text-2xl font-black text-[#202224] dark:text-white mt-1 tabular-nums">{{ totalIngredientsCount }}</h3>
          </div>
          <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-[#4880FF] flex items-center justify-center">
            <AppIcon name="kitchen" :size="24" />
          </div>
        </div>

        <!-- Card 2: Stok Aman -->
        <div class="p-4 rounded-2xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wider">Stok Aman</p>
            <h3 class="text-2xl font-black text-[#00B69B] mt-1 tabular-nums">{{ safeStockCount }}</h3>
          </div>
          <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-[#00B69B] flex items-center justify-center">
            <AppIcon name="check_circle" :size="24" />
          </div>
        </div>

        <!-- Card 3: Stok Menipis -->
        <div class="p-4 rounded-2xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wider">Stok Menipis</p>
            <h3 class="text-2xl font-black text-amber-500 mt-1 tabular-nums">{{ lowStockCount }}</h3>
          </div>
          <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-500 flex items-center justify-center">
            <AppIcon name="warning" :size="24" />
          </div>
        </div>

        <!-- Card 4: Stok Habis -->
        <div class="p-4 rounded-2xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wider">Stok Habis</p>
            <h3 class="text-2xl font-black text-rose-500 mt-1 tabular-nums">{{ outOfStockCount }}</h3>
          </div>
          <div class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center">
            <AppIcon name="error_outline" :size="24" />
          </div>
        </div>
      </div>

      <!-- Ingredients Table -->
      <AppTable
        :columns="ingredientColumns"
        :data="filteredIngredients"
        :loading="isLoading"
        :pageSize="20"
        :searchable="false"
        showNumbering
        numberingLabel="No"
        emptyMessage="Belum ada data bahan baku. Klik '+ Tambah Bahan' untuk mulai mencatat stok bahan."
      >
        <!-- Table Header Toolbar -->
        <template #header>
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
            <!-- Filter Dropdown -->
            <div class="flex items-center gap-2">
              <AppFilterDropdown
                v-model="selectedIngredientFilter"
                :options="ingredientFilterOptions"
                width="w-56"
              />
            </div>

            <!-- Right Controls: Search + Add Button -->
            <div class="flex items-center gap-3 w-full sm:w-auto ml-auto">
              <div class="w-full sm:w-[240px]">
                <AppInput
                  v-model="searchInput"
                  placeholder="Cari bahan baku..."
                  suffixIcon="search"
                  clearable
                  inputClass="!h-[38px] !text-xs sm:!text-sm"
                />
              </div>

              <AppButton
                variant="primary"
                class="!h-[38px] !px-4 !text-xs sm:!text-sm !font-bold shrink-0 flex items-center gap-1.5"
                @click="openAddIngredient"
              >
                <AppIcon name="add" :size="18" />
                <span>Tambah Bahan</span>
              </AppButton>
            </div>
          </div>
        </template>

        <!-- Cell: Nama Bahan -->
        <template #cell-name="{ row }">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F1F4F9] dark:bg-[#1E293B] flex items-center justify-center text-[#4880FF] shrink-0">
              <AppIcon name="eco" :size="20" />
            </div>
            <div class="flex flex-col min-w-0">
              <span class="font-bold text-sm text-[#202224] dark:text-white truncate">
                {{ row.name }}
              </span>
              <span class="text-xs text-[#94A3B8]">
                ID: {{ String(row.id).substring(0, 8) }}...
              </span>
            </div>
          </div>
        </template>

        <!-- Cell: Satuan -->
        <template #cell-unit="{ row }">
          <div class="flex flex-col items-center">
            <span class="font-bold text-sm text-[#202224] dark:text-white uppercase tracking-wide">
              {{ row.unit }}
            </span>
            <span v-if="row.unit !== row.baseUnit" class="text-[11px] text-[#94A3B8]">
              (Basis: {{ row.baseUnit }})
            </span>
          </div>
        </template>

        <!-- Cell: Stok Saat Ini -->
        <template #cell-currentStock="{ row }">
          <div class="flex flex-col items-center gap-1">
            <span class="font-black text-sm tabular-nums" :class="[
              row.currentStock <= 0 ? 'text-rose-500' : row.isLowStock ? 'text-amber-500' : 'text-[#202224] dark:text-white'
            ]">
              {{ row.displayStock }} {{ row.unit }}
            </span>
            <AppBadge
              v-if="row.currentStock <= 0"
              variant="danger"
              class="!text-[10px] !py-0.5 !px-2"
            >
              Habis
            </AppBadge>
            <AppBadge
              v-else-if="row.isLowStock"
              variant="warning"
              class="!text-[10px] !py-0.5 !px-2"
            >
              Menipis
            </AppBadge>
            <AppBadge
              v-else
              variant="success"
              class="!text-[10px] !py-0.5 !px-2"
            >
              Aman
            </AppBadge>
          </div>
        </template>

        <!-- Cell: Batas Minimum -->
        <template #cell-lowStockThreshold="{ row }">
          <div class="flex items-center justify-center">
            <span v-if="row.lowStockThreshold !== null && row.lowStockThreshold !== undefined" class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] tabular-nums">
              {{ row.lowStockThreshold }} {{ row.unit }}
            </span>
            <span v-else class="text-xs text-[#CBD5E1] dark:text-[#64748B]">
              -
            </span>
          </div>
        </template>

        <!-- Cell: Biaya / Satuan -->
        <template #cell-costPerUnit="{ row }">
          <span v-if="row.costPerUnit" class="font-bold text-xs sm:text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatCurrency(row.costPerUnit) }}
          </span>
          <span v-else class="text-xs text-[#CBD5E1] dark:text-[#64748B]">
            -
          </span>
        </template>

        <!-- Cell: Aksi -->
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-center gap-1.5">
            <!-- Restock / Sesuaikan -->
            <button
              type="button"
              @click="openAdjustStock(row as any)"
              class="p-1.5 rounded-lg text-[#4880FF] hover:bg-[#4880FF]/10 transition-colors cursor-pointer"
              title="Restock / Sesuaikan Stok"
            >
              <AppIcon name="add_circle" :size="18" />
            </button>

            <!-- Edit -->
            <button
              type="button"
              @click="openEditIngredient(row as any)"
              class="p-1.5 rounded-lg text-[#64748B] hover:text-[#202224] dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
              title="Edit Bahan"
            >
              <AppIcon name="edit" :size="18" />
            </button>

            <!-- Delete -->
            <button
              type="button"
              @click="openDeleteIngredient(row as any)"
              class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors cursor-pointer"
              title="Hapus Bahan"
            >
              <AppIcon name="delete" :size="18" />
            </button>
          </div>
        </template>
      </AppTable>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 2: RESEP & PORSI MENU CONTENT -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'recipes'" class="space-y-6">
      <AppTable
        :columns="menuColumns"
        :data="filteredMenuItems"
        :loading="isLoading"
        :pageSize="20"
        :searchable="false"
        showNumbering
        numberingLabel="No"
        emptyMessage="Belum ada menu yang sesuai kriteria filter."
      >
        <!-- Table Header Toolbar -->
        <template #header>
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
            <!-- Category & Availability Filters -->
            <div class="flex items-center gap-2">
              <AppFilterDropdown
                v-model="selectedCategoryFilter"
                :options="categoryFilterOptions"
                width="w-52"
              />

              <AppFilterDropdown
                v-model="selectedAvailabilityFilter"
                :options="availabilityOptions"
                width="w-48"
              />
            </div>

            <!-- Search Bar -->
            <div class="w-full sm:w-[250px] shrink-0 ml-auto">
              <AppInput
                v-model="searchInput"
                placeholder="Cari menu hidangan..."
                suffixIcon="search"
                clearable
                inputClass="!h-[38px] !text-xs sm:!text-sm"
              />
            </div>
          </div>
        </template>

        <!-- Cell: Menu Hidangan with Image -->
        <template #cell-name="{ row }">
          <div class="flex items-center gap-3">
            <img
              :src="row.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'"
              :alt="row.name"
              @error="handleImageError"
              class="w-11 h-11 rounded-xl object-cover bg-[#E2E8F0] dark:bg-[#1E293B] shrink-0"
            />
            <div class="flex flex-col min-w-0">
              <span class="font-bold text-sm text-[#202224] dark:text-white truncate">
                {{ row.name }}
              </span>
              <span class="text-xs text-[#94A3B8] truncate max-w-[220px]">
                {{ row.description || 'Menu hidangan resto' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Cell: Harga Jual -->
        <template #cell-price="{ row }">
          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatCurrency(row.price) }}
          </span>
        </template>

        <!-- Cell: Komposisi Resep -->
        <template #cell-recipes="{ row }">
          <div class="flex flex-wrap items-center gap-1.5">
            <template v-if="row.recipes && row.recipes.length > 0">
              <span
                v-for="rec in row.recipes"
                :key="rec.id"
                class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-[#F1F4F9] dark:bg-[#1E293B] text-[#475569] dark:text-[#94A3B8] border border-[#E2E8F0] dark:border-[#334155]"
              >
                {{ rec.ingredient?.name || 'Bahan' }}: {{ rec.quantityNeeded }} {{ rec.ingredient?.unit || '' }}
              </span>
            </template>
            <span v-else class="text-xs italic text-[#94A3B8]">
              Belum ada resep bahan
            </span>
          </div>
        </template>

        <!-- Cell: Estimasi Porsi Tersedia -->
        <template #cell-maxServings="{ row }">
          <div class="flex flex-col items-center gap-1">
            <template v-if="!row.recipes || row.recipes.length === 0">
              <AppBadge variant="neutral" class="!text-[11px] !py-0.5 !px-2.5">
                Tak Terbatas (Bebas)
              </AppBadge>
            </template>
            <template v-else-if="row.maxServings === 0">
              <AppBadge variant="danger" class="!text-[11px] !py-0.5 !px-2.5">
                0 Porsi (Bahan Habis)
              </AppBadge>
            </template>
            <template v-else-if="row.maxServings !== null && row.maxServings !== undefined && row.maxServings <= 5">
              <AppBadge variant="warning" class="!text-[11px] !py-0.5 !px-2.5 font-bold tabular-nums">
                Sisa {{ row.maxServings }} Porsi
              </AppBadge>
            </template>
            <template v-else>
              <AppBadge variant="success" class="!text-[11px] !py-0.5 !px-2.5 font-bold tabular-nums">
                {{ row.maxServings }} Porsi Tersedia
              </AppBadge>
            </template>
          </div>
        </template>

        <!-- Cell: Status & Aksi -->
        <template #cell-isAvailable="{ row }">
          <div class="flex items-center justify-center gap-3">
            <!-- Availability Toggle -->
            <div title="Toggle Ketersediaan Menu">
              <AppToggle :model-value="row.isAvailable" @change="handleToggleAvailability(row as any)" />
            </div>

            <!-- Button Edit Resep -->
            <button
              type="button"
              @click="openRecipeModal(row as any)"
              class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-[#4880FF]/10 text-[#4880FF] hover:bg-[#4880FF]/20 transition-all cursor-pointer"
              title="Atur Resep Takaran Bahan"
            >
              <AppIcon name="menu_book" :size="15" />
              <span>Resep</span>
            </button>
          </div>
        </template>
      </AppTable>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: TAMBAH / EDIT BAHAN BAKU -->
    <!-- ======================================================== -->
    <AppModal
      v-model="isIngredientModalOpen"
      :title="isEditingIngredient ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru'"
      maxWidth="md"
    >
      <div class="space-y-4">
        <!-- Alert Error -->
        <div v-if="ingModalError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
          <AppIcon name="error_outline" :size="18" class="shrink-0" />
          <span>{{ ingModalError }}</span>
        </div>

        <!-- Nama Bahan -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            Nama Bahan Baku <span class="text-rose-500">*</span>
          </label>
          <AppInput
            v-model="formIngName"
            placeholder="Biji Kopi Arabika, Susu Segar, Roti Burger"
            required
          />
        </div>

        <!-- Satuan -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            Satuan Ukuran <span class="text-rose-500">*</span>
          </label>
          <div class="grid grid-cols-2 gap-2 mb-2">
            <button
              v-for="preset in unitPresets"
              :key="preset.value"
              type="button"
              @click="formIngUnit = preset.value"
              :class="[
                'px-2.5 py-1.5 rounded-xl text-xs font-semibold border text-left transition-all cursor-pointer truncate',
                formIngUnit === preset.value
                  ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] font-bold'
                  : 'border-[#E2E8F0] dark:border-[#334155] text-[#64748B] dark:text-[#94A3B8] hover:border-[#CBD5E1]'
              ]"
            >
              {{ preset.label }}
            </button>
          </div>
          <AppInput
            v-model="formIngUnit"
            placeholder="Ketik satuan kustom (bungkus, kotak)"
          />
        </div>

        <!-- Stok Awal (hanya saat tambah baru) -->
        <div v-if="!isEditingIngredient">
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            Stok Awal (dalam {{ formIngUnit || 'satuan' }})
          </label>
          <AppInput
            v-model="formIngStock"
            type="number"
            placeholder="1000"
          />
        </div>

        <!-- Batas Minimum Alert -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            Batas Minimum Peringatan (dalam {{ formIngUnit || 'satuan' }})
          </label>
          <AppInput
            v-model="formIngThreshold"
            type="number"
            placeholder="100 (opsional)"
          />
          <p class="text-[11px] text-[#94A3B8] mt-1">
            Bahan akan ditandai berstatus "Menipis" jika stok berada di bawah angka ini.
          </p>
        </div>

        <!-- Estimasi Biaya Satuan -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            Estimasi Harga Beli / Satuan (Rp)
          </label>
          <AppInput
            v-model="formIngCost"
            type="number"
            placeholder="15000 (opsional)"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="isIngredientModalOpen = false" :disabled="isSavingIngredient">
          Batal
        </AppButton>
        <AppButton variant="primary" @click="saveIngredient" :loading="isSavingIngredient">
          {{ isEditingIngredient ? 'Simpan Perubahan' : 'Tambah Bahan' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ======================================================== -->
    <!-- MODAL: RESTOCK / SESUAIKAN STOK -->
    <!-- ======================================================== -->
    <AppModal
      v-model="isAdjustModalOpen"
      title="Restock / Sesuaikan Stok Bahan"
      maxWidth="md"
    >
      <div v-if="selectedIngredientForAdjust" class="space-y-4">
        <!-- Detail Info Bahan -->
        <div class="p-3.5 rounded-xl bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
          <div>
            <p class="text-xs text-[#94A3B8]">Bahan Baku</p>
            <h4 class="text-base font-bold text-[#202224] dark:text-white">{{ selectedIngredientForAdjust.name }}</h4>
          </div>
          <div class="text-right">
            <p class="text-xs text-[#94A3B8]">Stok Saat Ini</p>
            <p class="text-sm font-black text-[#4880FF] tabular-nums">
              {{ selectedIngredientForAdjust.displayStock }} {{ selectedIngredientForAdjust.unit }}
            </p>
          </div>
        </div>

        <!-- Alert Error -->
        <div v-if="adjustError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
          <AppIcon name="error_outline" :size="18" class="shrink-0" />
          <span>{{ adjustError }}</span>
        </div>

        <!-- Type Selector -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1.5">
            Metode Penyesuaian
          </label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="adjustType = 'restock'"
              :class="[
                'p-3 rounded-xl border text-center transition-all cursor-pointer',
                adjustType === 'restock'
                  ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] font-bold'
                  : 'border-[#E2E8F0] dark:border-[#334155] text-[#64748B] dark:text-[#94A3B8]'
              ]"
            >
              <AppIcon name="add_circle" :size="20" class="mx-auto mb-1 text-[#4880FF]" />
              <div class="text-xs">Tambah Stok (+ Restock)</div>
            </button>

            <button
              type="button"
              @click="adjustType = 'set'"
              :class="[
                'p-3 rounded-xl border text-center transition-all cursor-pointer',
                adjustType === 'set'
                  ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] font-bold'
                  : 'border-[#E2E8F0] dark:border-[#334155] text-[#64748B] dark:text-[#94A3B8]'
              ]"
            >
              <AppIcon name="tune" :size="20" class="mx-auto mb-1 text-slate-500" />
              <div class="text-xs">Atur Ulang (= Set Stok)</div>
            </button>
          </div>
        </div>

        <!-- Input Jumlah -->
        <div>
          <label class="block text-xs font-bold text-[#64748B] dark:text-[#94A3B8] mb-1">
            {{ adjustType === 'restock' ? 'Jumlah Tambahan Stok' : 'Jumlah Stok Baru' }} ({{ selectedIngredientForAdjust.unit }})
          </label>
          <AppInput
            v-model="adjustQuantity"
            type="number"
            :placeholder="adjustType === 'restock' ? '500' : '1200'"
            required
          />
        </div>

        <!-- Preview Result -->
        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between text-xs">
          <span class="font-medium text-emerald-800 dark:text-emerald-300">Estimasi Stok Akhir:</span>
          <span class="font-black text-emerald-700 dark:text-emerald-200 text-sm tabular-nums">
            {{ previewNewStock }} {{ selectedIngredientForAdjust.unit }}
          </span>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="isAdjustModalOpen = false" :disabled="isAdjusting">
          Batal
        </AppButton>
        <AppButton variant="primary" @click="saveAdjustStock" :loading="isAdjusting">
          Simpan Stok
        </AppButton>
      </template>
    </AppModal>

    <!-- ======================================================== -->
    <!-- MODAL: HAPUS BAHAN BAKU -->
    <!-- ======================================================== -->
    <AppModal
      v-model="isDeleteModalOpen"
      title="Hapus Bahan Baku"
      maxWidth="sm"
    >
      <div v-if="ingredientToDelete" class="space-y-3">
        <div v-if="deleteError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
          <AppIcon name="error_outline" :size="18" class="shrink-0" />
          <span>{{ deleteError }}</span>
        </div>

        <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          Apakah kamu yakin ingin menghapus bahan baku <strong class="text-[#202224] dark:text-white">{{ ingredientToDelete.name }}</strong>?
        </p>
        <p class="text-xs text-rose-500">
          Catatan: Bahan tidak dapat dihapus jika masih digunakan dalam resep menu aktif.
        </p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="isDeleteModalOpen = false" :disabled="isDeleting">
          Batal
        </AppButton>
        <AppButton variant="danger" @click="confirmDeleteIngredient" :loading="isDeleting">
          Hapus Bahan
        </AppButton>
      </template>
    </AppModal>

    <!-- ======================================================== -->
    <!-- MODAL: ATUR RESEP MENU -->
    <!-- ======================================================== -->
    <AppModal
      v-model="isRecipeModalOpen"
      :title="`Atur Resep Menu: ${selectedMenuItemForRecipe?.name || ''}`"
      maxWidth="xl"
    >
      <div v-if="selectedMenuItemForRecipe" class="space-y-5">
        <!-- Error Alert -->
        <div v-if="recipeModalError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
          <AppIcon name="error_outline" :size="18" class="shrink-0" />
          <span>{{ recipeModalError }}</span>
        </div>

        <!-- Recipe Instruction Info -->
        <div class="p-3.5 rounded-xl bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 flex items-start gap-2.5">
          <AppIcon name="info" :size="20" class="text-[#4880FF] shrink-0 mt-0.5" />
          <p class="text-xs text-blue-900 dark:text-blue-200 leading-relaxed">
            Tentukan takaran bahan yang dibutuhkan untuk menyajikan <strong>1 porsi</strong> menu ini. Sistem otomatis menghitung porsi yang tersedia dan mengurangi stok bahan setiap pesanan masuk.
          </p>
        </div>

        <!-- Ingredients Selection Repeater -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-[#64748B] dark:text-[#94A3B8] uppercase tracking-wider">
              Daftar Takaran Bahan Per Porsi
            </h4>
            <button
              type="button"
              @click="addRecipeRow"
              class="flex items-center gap-1 text-xs font-bold text-[#4880FF] hover:underline cursor-pointer"
            >
              <AppIcon name="add" :size="16" />
              <span>Tambah Bahan Resep</span>
            </button>
          </div>

          <div v-if="recipeRows.length === 0" class="p-6 rounded-xl border border-dashed border-[#CBD5E1] dark:border-[#334155] text-center">
            <p class="text-xs text-[#94A3B8]">Belum ada bahan dalam resep ini. Klik "Tambah Bahan Resep" di atas.</p>
          </div>

          <div
            v-for="(row, idx) in recipeRows"
            :key="idx"
            class="flex items-center gap-3 p-3 rounded-xl bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155]"
          >
            <!-- Dropdown Bahan -->
            <div class="flex-1 min-w-0">
              <label class="block text-[11px] font-semibold text-[#94A3B8] mb-1">Pilih Bahan Baku</label>
              <select
                v-model="row.ingredient_id"
                class="w-full h-10 px-3 rounded-xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] text-xs font-medium text-[#202224] dark:text-white focus:border-[#4880FF] focus:outline-none"
              >
                <option v-for="ing in posStore.ingredients" :key="ing.id" :value="ing.id">
                  {{ ing.name }} (Stok: {{ ing.displayStock }} {{ ing.unit }})
                </option>
              </select>
            </div>

            <!-- Takaran Input -->
            <div class="w-36 shrink-0">
              <label class="block text-[11px] font-semibold text-[#94A3B8] mb-1">
                Kebutuhan ({{ getIngredientById(row.ingredient_id)?.unit || 'unit' }})
              </label>
              <AppInput
                v-model="row.quantity_needed"
                type="number"
                step="any"
                placeholder="0.0"
                inputClass="!h-10 !text-xs"
              />
            </div>

            <!-- Remove Row Button -->
            <div class="pt-5 shrink-0">
              <button
                type="button"
                @click="removeRecipeRow(idx)"
                class="p-2 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors cursor-pointer"
                title="Hapus baris"
              >
                <AppIcon name="delete" :size="18" />
              </button>
            </div>
          </div>
        </div>

        <!-- Real-time Servings Preview -->
        <div
          v-if="modalPreviewServings !== null"
          :class="[
            'p-4 rounded-xl border flex items-center justify-between',
            modalPreviewServings > 0
              ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800'
              : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800'
          ]"
        >
          <div>
            <p class="text-xs font-bold" :class="modalPreviewServings > 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300'">
              Kalkulasi Ketersediaan Porsi:
            </p>
            <p class="text-[11px] text-[#64748B] dark:text-[#94A3B8] mt-0.5">
              Berdasarkan stok bahan terendah saat ini
            </p>
          </div>
          <div class="text-right">
            <span class="text-xl font-black tabular-nums" :class="modalPreviewServings > 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-600 dark:text-rose-300'">
              {{ modalPreviewServings }} Porsi
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="isRecipeModalOpen = false" :disabled="isSavingRecipe">
          Batal
        </AppButton>
        <AppButton variant="primary" @click="saveRecipe" :loading="isSavingRecipe">
          Simpan Resep
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>
