<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, Edit2, Trash2 } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import type { MenuItem } from '@/types'

const posStore = usePosStore()

const isLoading = ref(true)
const isSubmitting = ref(false)

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

// Search & Filter state
const searchInput = ref('')
const debouncedSearch = ref('')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (newVal) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedSearch.value = (newVal || '').trim()
  }, 300)
})

const selectedCategoryId = ref('all')
const selectedRecipeFilter = ref('all')
const selectedSort = ref('name_asc')

const categoryFilterOptions = computed(() => [
  { value: 'all', label: 'Semua Kategori' },
  ...posStore.categories.map((c) => ({ value: c.id, label: c.name })),
])

const recipeFilterOptions = [
  { value: 'all', label: 'Semua Status Resep' },
  { value: 'has_recipe', label: 'Sudah Ada Resep' },
  { value: 'no_recipe', label: 'Belum Ada Resep' },
]

const sortOptions = [
  { value: 'name_asc', label: 'Nama (A-Z)' },
  { value: 'name_desc', label: 'Nama (Z-A)' },
  { value: 'price_desc', label: 'Harga Tertinggi' },
  { value: 'price_asc', label: 'Harga Terendah' },
  { value: 'servings_desc', label: 'Porsi Terbanyak' },
  { value: 'servings_asc', label: 'Porsi Tersedikit' },
]

const handleImageError = (e: Event) => {
  const target = e.target as HTMLImageElement
  if (target) {
    target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500&auto=format&fit=crop&q=80'
  }
}

const getCategoryName = (row: MenuItem): string => {
  if (row.category?.name) return row.category.name
  if (row.categoryId) {
    const found = posStore.categories.find(c => c.id === row.categoryId)
    if (found) return found.name
  }
  return ''
}

// Category Badge Variant helper matching TopItemsPage (Daftar Menu Terlaris)
const getCategoryBadgeVariant = (name: string): any => {
  const n = (name || '').toLowerCase()
  if (n.includes('promo')) return 'danger'
  if (n.includes('main') || n.includes('makanan')) return 'primary'
  if (n.includes('bev') || n.includes('minuman') || n.includes('kopi')) return 'indigo'
  if (n.includes('cake') || n.includes('snack') || n.includes('roti')) return 'purple'
  if (n.includes('app') || n.includes('appetizer')) return 'warning'
  if (n.includes('dessert')) return 'success'
  return 'primary'
}

// Table Columns Configuration (Proporsional & Simetris 100%)
const columns = [
  { key: 'name', label: 'Menu', width: '28%' },
  { key: 'category', label: 'Kategori', width: '18%' },
  { key: 'price', label: 'Harga Jual', align: 'right' as const, width: '16%' },
  { key: 'recipeStatus', label: 'Komposisi Resep', align: 'center' as const, width: '16%' },
  { key: 'maxServings', label: 'Estimasi Porsi', align: 'center' as const, width: '14%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '8%' },
]

// Filtered & Sorted Menu Items
const filteredMenuItems = computed(() => {
  let list = [...(posStore.menuItems || [])]

  if (selectedCategoryId.value !== 'all') {
    list = list.filter((m) => m.categoryId === selectedCategoryId.value)
  }

  if (selectedRecipeFilter.value === 'has_recipe') {
    list = list.filter((m) => m.recipes && m.recipes.length > 0)
  } else if (selectedRecipeFilter.value === 'no_recipe') {
    list = list.filter((m) => !m.recipes || m.recipes.length === 0)
  }

  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    list = list.filter((m) => m.name.toLowerCase().includes(q))
  }

  if (selectedSort.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name))
  } else if (selectedSort.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name))
  } else if (selectedSort.value === 'price_desc') {
    list.sort((a, b) => (b.price || 0) - (a.price || 0))
  } else if (selectedSort.value === 'price_asc') {
    list.sort((a, b) => (a.price || 0) - (b.price || 0))
  } else if (selectedSort.value === 'servings_desc') {
    list.sort((a, b) => (b.maxServings ?? -1) - (a.maxServings ?? -1))
  } else if (selectedSort.value === 'servings_asc') {
    list.sort((a, b) => (a.maxServings ?? 999999) - (b.maxServings ?? 999999))
  }

  return list
})

// Popover Titik Tiga State
const activeMenuId = ref<string | null>(null)
const toggleMenu = (id: string) => {
  activeMenuId.value = activeMenuId.value === id ? null : id
}
const closeMenu = () => {
  activeMenuId.value = null
}

// Recipe Editor Modal State
const isModalOpen = ref(false)
const selectedMenuItem = ref<MenuItem | null>(null)
const recipeRows = ref<Array<{ ingredient_id: string; quantity_needed: number | '' }>>([])
const modalError = ref('')

// Modal Delete State
const isDeleteModalOpen = ref(false)
const itemToDelete = ref<MenuItem | null>(null)
const isDeleting = ref(false)

const ingredientSelectOptions = computed(() => [
  { value: '', label: '-- Pilih Bahan Baku --' },
  ...posStore.ingredients.map((ing) => ({
    value: ing.id,
    label: `${ing.name} (${formatNumber(ing.displayStock ?? ing.currentStock)} ${ing.unit})`,
  })),
])

const openRecipeEditor = (item: MenuItem) => {
  selectedMenuItem.value = item
  modalError.value = ''

  if (item.recipes && item.recipes.length > 0) {
    recipeRows.value = item.recipes.map((r) => ({
      ingredient_id: r.ingredientId,
      quantity_needed: r.quantityNeeded,
    }))
  } else {
    recipeRows.value = [{ ingredient_id: '', quantity_needed: '' }]
  }

  isModalOpen.value = true
}

const confirmDelete = (item: MenuItem) => {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

const handleDelete = async () => {
  if (!itemToDelete.value || isDeleting.value) return
  isDeleting.value = true
  try {
    await posStore.updateMenuItemRecipe(itemToDelete.value.id, [])
    showNotification('success', `Resep untuk '${itemToDelete.value.name}' berhasil dihapus.`)
    isDeleteModalOpen.value = false
    itemToDelete.value = null
  } catch (err: any) {
    showNotification('error', 'Gagal menghapus resep: ' + (err?.message || 'Error'))
  } finally {
    isDeleting.value = false
  }
}

const addRecipeRow = () => {
  recipeRows.value.push({ ingredient_id: '', quantity_needed: '' })
}

const removeRecipeRow = (index: number) => {
  recipeRows.value.splice(index, 1)
}

const getIngredient = (ingredientId: string) => {
  return posStore.ingredients.find((i) => i.id === ingredientId)
}

const getIngredientUnitCost = (ing: any) => {
  if (ing.baseCostPerUnit !== undefined && ing.baseCostPerUnit !== null) {
    return ing.baseCostPerUnit
  }
  const unit = (ing.unit || '').toLowerCase()
  const cost = ing.costPerUnit || 0
  if (['kg', 'kilogram', 'l', 'liter'].includes(unit)) {
    return Math.round((cost / 1000) * 100) / 100
  }
  return cost
}

const estimatedHpp = computed(() => {
  let total = 0
  for (const row of recipeRows.value) {
    if (!row.ingredient_id || !row.quantity_needed) continue
    const ing = getIngredient(row.ingredient_id)
    if (ing) {
      total += getIngredientUnitCost(ing) * Number(row.quantity_needed)
    }
  }
  return Math.round(total)
})

const marginPercent = computed(() => {
  if (!selectedMenuItem.value || selectedMenuItem.value.price <= 0) return 0
  const margin = selectedMenuItem.value.price - estimatedHpp.value
  return Math.round((margin / selectedMenuItem.value.price) * 100)
})

const formatNumber = (val?: number | null) => {
  if (val === null || val === undefined) return '-'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(val)
}

const formatCurrency = (val?: number | null) => {
  if (!val && val !== 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val)
}

const handleSaveRecipe = async () => {
  if (!selectedMenuItem.value) return

  const validRows = recipeRows.value.filter((r) => r.ingredient_id && Number(r.quantity_needed) > 0)

  const seenIds = new Set<string>()
  for (const r of validRows) {
    if (seenIds.has(r.ingredient_id)) {
      modalError.value = 'Terdapat bahan baku yang sama berulang dalam resep. Harap gabungkan baris tersebut.'
      return
    }
    seenIds.add(r.ingredient_id)
  }

  modalError.value = ''
  isSubmitting.value = true

  try {
    await posStore.updateMenuItemRecipe(
      selectedMenuItem.value.id,
      validRows.map((r) => ({
        ingredient_id: r.ingredient_id,
        quantity_needed: Number(r.quantity_needed),
      }))
    )
    showNotification('success', `Resep untuk '${selectedMenuItem.value.name}' berhasil disimpan.`)
    isModalOpen.value = false
  } catch (err: any) {
    modalError.value = err?.response?.data?.message || 'Gagal menyimpan resep menu.'
  } finally {
    isSubmitting.value = false
  }
}

const loadData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchMenuItems(),
      posStore.fetchIngredients(),
      posStore.fetchCategories(),
    ])
  } catch (err: any) {
    showNotification('error', 'Gagal memuat data resep: ' + (err?.message || 'Error'))
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
</script>

<template>
  <div class="space-y-6">
    <!-- Notification Banner (Toast) using AppIcon -->
    <transition enter-active-class="transition duration-300 ease-out transform"
      enter-from-class="-translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in transform" leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0">
      <div v-if="notification" :class="[
        'flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-semibold shadow-sm',
        notification.type === 'success'
          ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
          : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300',
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

    <!-- Header Page (Tanpa Tanggal/Waktu) -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Resep Menu</h1>
    </div>

    <!-- Reusable Dashboard AppTable Component with Integrated Header -->
    <AppTable :columns="columns" :data="filteredMenuItems" :loading="isLoading" :pageSize="15" :searchable="false"
      showNumbering numberingLabel="No" emptyMessage="Belum ada resep menu yang sesuai">
      <!-- Integrated Header (Segmented Pill Container & Reusable AppInput Search) -->
      <template #header>
        <!-- Skeleton State -->
        <div v-if="isLoading"
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full animate-pulse">
          <div class="flex items-center gap-2">
            <div class="h-9 w-44 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-44 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-40 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
          </div>
          <div class="w-full sm:w-[250px] h-[38px] bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
        </div>

        <!-- Loaded Controls -->
        <div v-else class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container (Persis seperti Kategori & Bahan Baku) -->
          <div class="flex flex-wrap items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedCategoryId" :options="categoryFilterOptions" width="w-48" />
            <AppFilterDropdown v-model="selectedRecipeFilter" :options="recipeFilterOptions" width="w-48" />
            <AppFilterDropdown v-model="selectedSort" :options="sortOptions" width="w-44" />
          </div>

          <!-- Right: Search Pool Input (Persis seperti Kategori & Bahan Baku) -->
          <div class="w-full sm:w-[250px] shrink-0 ml-auto">
            <AppInput v-model="searchInput" placeholder="Cari nama menu..." suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <!-- Cell Slot: Menu (Image & Name persis seperti tabel Deals Details) -->
      <template #cell-name="{ row }">
        <div class="flex items-center gap-3.5">
          <img
            :src="row.imageUrl || 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500&auto=format&fit=crop&q=80'"
            :alt="row.name"
            @error="handleImageError"
            class="w-11 h-11 rounded-xl object-cover bg-[#D8D8D8] dark:bg-[#334155] shrink-0"
          />
          <span class="font-bold text-sm text-[#202224] dark:text-white leading-snug">{{ row.name }}</span>
        </div>
      </template>

      <!-- Cell Slot: Kategori Menu (Teks Bersih Tanpa Badge) -->
      <template #cell-category="{ row }">
        <span v-if="getCategoryName(row)" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1]">
          {{ getCategoryName(row) }}
        </span>
        <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">
          -
        </span>
      </template>

      <!-- Cell Slot: Harga Jual -->
      <template #cell-price="{ row }">
        <div class="text-right">
          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatCurrency(row.price) }}
          </span>
        </div>
      </template>

      <!-- Cell Slot: Komposisi Resep (AppBadge Reusable Simetris Tengah) -->
      <template #cell-recipeStatus="{ row }">
        <div class="flex items-center justify-center">
          <AppBadge v-if="row.recipes && row.recipes.length > 0" variant="primary" size="md" rounded="full">
            {{ row.recipes.length }} Bahan Baku
          </AppBadge>
          <AppBadge v-else variant="neutral" size="md" rounded="full">
            Belum Ada Resep
          </AppBadge>
        </div>
      </template>

      <!-- Cell Slot: Estimasi Porsi Tersedia (Porsi persis seperti Daftar Menu Terlaris) -->
      <template #cell-maxServings="{ row }">
        <div class="flex items-center justify-center">
          <template v-if="row.recipes && row.recipes.length > 0">
            <span v-if="row.maxServings !== undefined && row.maxServings !== null" class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
              {{ formatNumber(row.maxServings) }} Porsi
            </span>
            <span v-else class="text-xs font-medium text-slate-400 dark:text-slate-500">
              Tidak terbatas
            </span>
          </template>
          <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">
            -
          </span>
        </div>
      </template>

      <!-- Cell Slot: Aksi (Menu Titik Tiga Persis seperti Tabel Bahan Baku) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button type="button" @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Menu">
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Popover Dropdown Menu dengan Smooth Vue Transition -->
            <Transition enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-1">
              <div v-if="activeMenuId === row.id"
                class="absolute right-0 top-full mt-1.5 w-44 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                <!-- 1. Edit Resep (Amber hover matching IngredientsPage & IngredientCategoriesPage) -->
                <button type="button" @click="openRecipeEditor(row); closeMenu()"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Edit2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-150" />
                  <span>Edit Resep</span>
                </button>

                <!-- 2. Hapus Resep -->
                <button type="button" @click="confirmDelete(row); closeMenu()"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                  <Trash2
                    class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-150" />
                  <span>Hapus Resep</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </template>
    </AppTable>

    <!-- Modal Recipe Editor with Reusable AppModal -->
    <AppModal v-model="isModalOpen" :title="`Edit Resep Menu: ${selectedMenuItem?.name || ''}`" maxWidth="lg">
      <div class="space-y-4 py-2">
        <!-- Error Banner -->
        <div v-if="modalError"
          class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalError }}
        </div>

        <!-- Recipe Rows Editor using Reusable AppSelect & AppInput -->
        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
          <div v-for="(row, idx) in recipeRows" :key="idx" class="flex items-start gap-2.5">
            <!-- Reusable AppSelect for Ingredient -->
            <div class="flex-1">
              <AppSelect
                v-model="row.ingredient_id"
                :options="ingredientSelectOptions"
                placeholder="Pilih Bahan Baku"
              />
            </div>

            <!-- Reusable AppInput for Quantity -->
            <div class="w-36">
              <AppInput
                v-model="row.quantity_needed"
                type="number"
                min="0"
                step="any"
                placeholder="Takaran"
                :suffix="getIngredient(row.ingredient_id)?.baseUnitDisplay || getIngredient(row.ingredient_id)?.unit || ''"
                :debounce="0"
              />
            </div>

            <!-- Reusable AppButton for Delete Row -->
            <div class="pt-0.5">
              <AppButton
                variant="ghost"
                size="sm"
                icon="delete"
                @click="removeRecipeRow(idx)"
                title="Hapus baris"
                class="!text-red-500 hover:!bg-red-50 dark:hover:!bg-red-950/30"
              />
            </div>
          </div>
        </div>

        <!-- Reusable AppButton for Add Row -->
        <AppButton variant="outline" size="sm" block icon="add" @click="addRecipeRow">
          Tambah Bahan Baku ke Resep
        </AppButton>

        <!-- Financial Summary Card using Reusable AppCard -->
        <AppCard>
          <div class="text-xs space-y-2">
            <div class="flex justify-between items-center text-[#64748B] dark:text-[#94A3B8]">
              <span>Harga Jual Menu:</span>
              <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
                {{ formatCurrency(selectedMenuItem?.price) }}
              </span>
            </div>
            <div class="flex justify-between items-center text-[#64748B] dark:text-[#94A3B8]">
              <span>Estimasi HPP Bahan Baku / Porsi:</span>
              <span class="font-bold text-sm text-[#4880FF] tabular-nums">
                {{ formatCurrency(estimatedHpp) }}
              </span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-[#E2E8F0] dark:border-[#334155] font-semibold">
              <span class="text-[#202224] dark:text-white">Estimasi Margin Keuntungan:</span>
              <span class="font-bold text-sm tabular-nums" :class="marginPercent >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                {{ marginPercent }}% ({{ formatCurrency((selectedMenuItem?.price || 0) - estimatedHpp) }})
              </span>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Modal Footer using Reusable AppButton -->
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isModalOpen = false" :disabled="isSubmitting">
            Batal
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleSaveRecipe" :loading="isSubmitting" :disabled="isSubmitting">
            Simpan Resep
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Konfirmasi Hapus Resep Menu -->
    <AppModal v-model="isDeleteModalOpen" title="Hapus Resep Menu" maxWidth="sm">
      <div v-if="itemToDelete" class="space-y-3 py-1 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin menghapus resep untuk <span class="font-bold text-[#202224] dark:text-white">{{
            itemToDelete.name }}</span>?
        </p>
        <p class="text-xs text-[#EF4444] bg-[#EF4444]/10 dark:bg-[#EF4444]/20 p-2.5 rounded-lg font-medium">
          Tindakan ini akan mengosongkan komposisi bahan baku pada menu ini. Stok bahan baku tidak akan lagi terpotong otomatis saat menu ini dipesan di kasir.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isDeleteModalOpen = false" :disabled="isDeleting">
            Batal
          </AppButton>
          <AppButton variant="danger" size="md" @click="handleDelete" :disabled="isDeleting" :loading="isDeleting">
            {{ isDeleting ? 'Menghapus...' : 'Hapus Resep' }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
