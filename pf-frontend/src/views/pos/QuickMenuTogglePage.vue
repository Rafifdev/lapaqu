<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable, { type TableColumn } from '@/components/ui/AppTable.vue'
import AppToggle from '@/components/ui/AppToggle.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import emptyMenuIllustration from '@/assets/empty_state/empty-menu.svg'

const defaultFoodImage = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'

const { formatCurrency } = useFormat()
const posStore = usePosStore()

const isLoading = ref(true)
const searchQuery = ref('')
const selectedCategory = ref('all')
const selectedStatus = ref<'all' | 'available' | 'unavailable'>('all')

onMounted(async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchMenuItems(),
      posStore.fetchCategories()
    ])
  } catch (err) {
    console.error('Failed to load menu items:', err)
  } finally {
    isLoading.value = false
  }
})

// Column widths matching exact proportions
const columns: TableColumn[] = [
  { key: 'menu', label: 'Menu', align: 'left', width: '34%' },
  { key: 'categoryName', label: 'Kategori', align: 'left', width: '18%' },
  { key: 'servings', label: 'Sisa Porsi', align: 'center', width: '12%' },
  { key: 'price', label: 'Harga', align: 'right', width: '12%' },
  { key: 'status', label: 'Status', align: 'center', width: '12%' },
  { key: 'action', label: 'Ketersediaan', align: 'center', width: '12%' },
]

const categoryOptions = computed(() => {
  const opts = [{ value: 'all', label: 'Semua Kategori' }]
  if (posStore.categories && posStore.categories.length) {
    posStore.categories.forEach((c: any) => {
      opts.push({ value: c.id, label: c.name })
    })
  }
  return opts
})

const statusOptions = [
  { value: 'all', label: 'Semua Status' },
  { value: 'available', label: 'Tersedia' },
  { value: 'unavailable', label: 'Habis' },
]

const filteredTableData = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()

  return posStore.menuItems
    .filter((item: any) => {
      // 1. Search Query Filter
      const matchSearch = !q ||
        item.name.toLowerCase().includes(q) ||
        (item.category?.name && item.category.name.toLowerCase().includes(q))

      // 2. Category Filter
      const matchCat = selectedCategory.value === 'all' ||
        item.categoryId === selectedCategory.value ||
        item.category?.id === selectedCategory.value

      // 3. Status Filter
      const matchStatus = selectedStatus.value === 'all' ||
        (selectedStatus.value === 'available' ? item.isAvailable : !item.isAvailable)

      return matchSearch && matchCat && matchStatus
    })
    .map((item: any) => ({
      id: item.id,
      name: item.name,
      description: item.description,
      categoryName: item.category?.name || 'Umum',
      price: item.price,
      isAvailable: item.isAvailable,
      imageUrl: item.imageUrl || item.image_url || item.image || defaultFoodImage,
      maxServings: item.maxServings
    }))
})

const handleImageError = (e: Event) => {
  const target = e.target as HTMLImageElement
  if (target) {
    target.src = defaultFoodImage
  }
}

const handleToggle = async (id: string) => {
  try {
    await posStore.toggleMenuAvailability(id)
  } catch (err) {
    console.error('Failed to toggle menu availability:', err)
  }
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0 overflow-hidden space-y-3">
    <!-- Header: Quick Stok -->
    <div class="flex items-center justify-between shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Quick Stok</h1>
    </div>

    <!-- Reusable Dashboard AppTable Component - Zero Scrollbar Fit -->
    <AppTable
      :columns="columns"
      :data="filteredTableData"
      :loading="isLoading"
      :pageSize="6"
      :searchable="false"
      :scrollable="false"
      minHeight="min-h-0"
      showNumbering
      numberingLabel="No"
      :emptyIllustration="emptyMenuIllustration"
      emptyTitle="Menu Tidak Ditemukan"
      emptyMessage="Belum ada menu yang sesuai dengan filter atau pencarian Anda"
      class="!p-4 sm:!p-5 shadow-none border border-slate-200/80 dark:border-[#313D4F] flex-1 flex flex-col min-h-0 justify-between overflow-hidden"
    >
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <!-- Skeleton State for Filter & Search Bar -->
        <div
          v-if="isLoading"
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 w-full animate-pulse"
        >
          <div class="flex items-center gap-2">
            <div class="h-9 w-44 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
            <div class="h-9 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
          </div>
          <div class="w-full sm:w-[250px] h-[38px] bg-[#E2E8F0] dark:bg-[#334155] rounded-xl"></div>
        </div>

        <div
          v-else
          class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 w-full"
        >
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex flex-wrap items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Category Filter Dropdown -->
            <AppFilterDropdown
              v-model="selectedCategory"
              :options="categoryOptions"
              width="w-48"
            />

            <!-- 2. Status Filter Dropdown -->
            <AppFilterDropdown
              v-model="selectedStatus"
              :options="statusOptions"
              width="w-40"
            />
          </div>

          <!-- Right: Reusable AppInput Search Matching DashStack Standard -->
          <div class="w-full sm:w-[250px] shrink-0 ml-auto">
            <AppInput
              v-model="searchQuery"
              placeholder="Cari nama menu..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Menu (Image + Name) -->
      <template #cell-menu="{ row }">
        <div class="flex items-center gap-3 py-0.5">
          <div class="w-10 h-10 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 shrink-0 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs">
            <img
              :src="row.imageUrl"
              :alt="row.name"
              class="w-full h-full object-cover select-none pointer-events-none"
              loading="lazy"
              @error="handleImageError"
            />
          </div>
          <div class="min-w-0">
            <div class="font-bold text-sm text-[#202224] dark:text-white truncate">
              {{ row.name }}
            </div>
            <div v-if="row.description" class="text-xs text-[#64748B] dark:text-[#94A3B8] truncate max-w-xs">
              {{ row.description }}
            </div>
          </div>
        </div>
      </template>

      <!-- Cell: Kategori (Teks Bersih Sesuai Desain DashStack) -->
      <template #cell-categoryName="{ row }">
        <span v-if="row.categoryName" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1]">
          {{ row.categoryName }}
        </span>
        <span v-else class="text-xs text-slate-400 italic">-</span>
      </template>

      <!-- Cell: Sisa Porsi (Kolom Terpisah) -->
      <template #cell-servings="{ row }">
        <div class="flex items-center justify-center gap-1.5">
          <template v-if="row.maxServings !== undefined && row.maxServings !== null">
            <span
              class="font-bold text-sm tabular-nums"
              :class="row.maxServings > 0 ? 'text-[#202224] dark:text-white' : 'text-rose-500'"
            >
              {{ row.maxServings }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-[#94A3B8]">porsi</span>
          </template>
          <span v-else class="text-xs text-slate-400 italic">-</span>
        </div>
      </template>

      <!-- Cell: Harga (Warna Default Text Netral) -->
      <template #cell-price="{ row }">
        <span class="font-semibold text-sm text-[#202224] dark:text-white tabular-nums">
          {{ formatCurrency(row.price) }}
        </span>
      </template>

      <!-- Cell: Status (AppBadge Standar DashStack) -->
      <template #cell-status="{ row }">
        <div class="flex justify-center">
          <AppBadge
            :variant="row.isAvailable ? 'success' : 'danger'"
            size="md"
            rounded="full"
          >
            {{ row.isAvailable ? 'Tersedia' : 'Habis' }}
          </AppBadge>
        </div>
      </template>

      <!-- Cell: Ketersediaan Toggle (AppToggle Standar DashStack) -->
      <template #cell-action="{ row }">
        <div class="flex items-center justify-center" @click.stop>
          <AppToggle
            :modelValue="row.isAvailable"
            @update:modelValue="handleToggle(row.id)"
          />
        </div>
      </template>
    </AppTable>
  </div>
</template>
