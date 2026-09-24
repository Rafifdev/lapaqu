<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import type { IngredientStockLog } from '@/types'

const posStore = usePosStore()
const { formatNumber } = useFormat()

const isLoading = ref(true)
const logs = ref<IngredientStockLog[]>([])
const totalLogs = ref(0)
const currentPage = ref(1)
const perPage = ref(20)

// Filters
const filterType = ref('all')
const filterIngredientId = ref('all')
const searchQuery = ref('')

const typeOptions = [
  { value: 'all', label: 'Semua Jenis Mutasi' },
  { value: 'order_deduct', label: 'Penjualan (Order)' },
  { value: 'restock', label: 'Restock Bahan' },
  { value: 'stock_opname', label: 'Stok Opname (Audit)' },
  { value: 'manual_adjustment', label: 'Penyesuaian Manual' },
  { value: 'waste', label: 'Waste / Rusak' },
  { value: 'cancellation_refund', label: 'Batal Pesanan' },
]

const ingredientOptions = computed(() => [
  { value: 'all', label: 'Semua Bahan Baku' },
  ...posStore.ingredients.map(ing => ({ value: ing.id, label: ing.name }))
])

const columns = [
  { key: 'createdAt', label: 'Waktu Transaksi', width: '18%' },
  { key: 'ingredientName', label: 'Nama Bahan Baku', width: '22%' },
  { key: 'type', label: 'Jenis Mutasi', align: 'center' as const, width: '14%' },
  { key: 'quantity', label: 'Perubahan Qty', align: 'right' as const, width: '14%' },
  { key: 'balanceBefore', label: 'Stok Awal', align: 'right' as const, width: '10%' },
  { key: 'balanceAfter', label: 'Stok Akhir', align: 'right' as const, width: '10%' },
  { key: 'notes', label: 'Catatan / Operator', width: '12%' },
]

const loadData = async () => {
  isLoading.value = true
  try {
    const params: any = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (filterType.value && filterType.value !== 'all') params.type = filterType.value
    if (filterIngredientId.value && filterIngredientId.value !== 'all') params.ingredient_id = filterIngredientId.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await posStore.fetchStockLogs(params)
    if (res && res.data) {
      logs.value = res.data
      totalLogs.value = res.total ?? res.data.length
    }
  } catch (err) {
    console.error('Error loading stock logs:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await posStore.fetchIngredients()
  await loadData()
})

watch([filterType, filterIngredientId], () => {
  currentPage.value = 1
  loadData()
})

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null
watch(searchQuery, () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    currentPage.value = 1
    loadData()
  }, 400)
})

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(dateStr))
}

const getTypeBadgeVariant = (type: string): any => {
  switch (type) {
    case 'restock':
      return 'success'
    case 'order_deduct':
    case 'sale_deduction':
      return 'danger'
    case 'stock_opname':
    case 'opname_adjustment':
      return 'indigo'
    case 'waste':
      return 'warning'
    case 'cancellation_refund':
      return 'primary'
    default:
      return 'primary'
  }
}

const getTypeLabel = (type: string): string => {
  switch (type) {
    case 'order_deduct':
    case 'sale_deduction':
      return 'Penjualan'
    case 'restock':
      return 'Restock'
    case 'stock_opname':
    case 'opname_adjustment':
      return 'Opname'
    case 'manual_adjustment':
      return 'Koreksi'
    case 'waste':
      return 'Waste'
    case 'cancellation_refund':
      return 'Batal Order'
    default:
      return type
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page (Tanpa Sub Header Kecil) -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Riwayat Mutasi Stok</h1>
    </div>

    <!-- Reusable Dashboard AppTable Component with Integrated Header -->
    <AppTable
      :columns="columns"
      :data="logs"
      :loading="isLoading"
      :pageSize="20"
      :searchable="false"
      showNumbering
      numberingLabel="No"
      emptyMessage="Belum ada riwayat mutasi stok yang tercatat"
    >
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Type Filter Dropdown -->
            <AppFilterDropdown v-model="filterType" :options="typeOptions" width="w-52" />

            <!-- 2. Ingredient Filter Dropdown -->
            <AppFilterDropdown v-model="filterIngredientId" :options="ingredientOptions" width="w-48" />
          </div>

          <!-- Right: Reusable AppInput Search -->
          <div class="w-full sm:w-[250px] shrink-0">
            <AppInput
              v-model="searchQuery"
              placeholder="Cari bahan / catatan..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Waktu Transaksi -->
      <template #cell-createdAt="{ value }">
        <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">{{ formatDate(value) }}</span>
      </template>

      <!-- Cell: Nama Bahan Baku -->
      <template #cell-ingredientName="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white leading-snug">
          {{ row.ingredient?.name || '-' }}
        </span>
      </template>

      <!-- Cell: Jenis Mutasi -->
      <template #cell-type="{ row }">
        <div class="flex justify-center">
          <AppBadge :variant="getTypeBadgeVariant(row.type)" size="md" rounded="full">
            {{ getTypeLabel(row.type) }}
          </AppBadge>
        </div>
      </template>

      <!-- Cell: Perubahan Qty -->
      <template #cell-quantity="{ row }">
        <span class="font-mono text-sm font-black" :class="row.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
          {{ row.quantity > 0 ? '+' : '' }}{{ formatNumber(row.quantity) }} <span class="text-xs font-sans font-medium text-[#64748B] dark:text-[#94A3B8]">{{ row.unit }}</span>
        </span>
      </template>

      <!-- Cell: Stok Awal -->
      <template #cell-balanceBefore="{ row }">
        <span class="font-mono text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
          {{ formatNumber(row.balanceBefore) }}
        </span>
      </template>

      <!-- Cell: Stok Akhir -->
      <template #cell-balanceAfter="{ row }">
        <span class="font-mono text-xs font-bold text-[#202224] dark:text-white">
          {{ formatNumber(row.balanceAfter) }}
        </span>
      </template>

      <!-- Cell: Catatan / Operator -->
      <template #cell-notes="{ row }">
        <div>
          <span class="text-xs text-[#202224] dark:text-white font-medium block truncate max-w-[180px]" :title="row.notes || undefined">
            {{ row.notes || '-' }}
          </span>
          <span class="text-[11px] text-[#64748B] dark:text-[#94A3B8]">
            {{ row.user?.name || 'Sistem' }}
          </span>
        </div>
      </template>
    </AppTable>
  </div>
</template>
