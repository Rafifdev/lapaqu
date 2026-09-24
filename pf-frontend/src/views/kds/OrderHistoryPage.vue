<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useFormat } from '@/composables/useFormat'
import { usePosStore } from '@/stores/pos'
import { usePosKdsI18n } from '@/i18n'
import AppTable, { type TableColumn } from '@/components/ui/AppTable.vue'
import AppTableFilterBar, { type FilterBarItem } from '@/components/ui/AppTableFilterBar.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import emptyRiwayatIllustration from '@/assets/empty_state/empty-riwayat.svg'

const { formatTimeOnly, formatDate, formatCustomerName } = useFormat()
const posStore = usePosStore()
const { t } = usePosKdsI18n()

onMounted(() => {
  posStore.fetchOrders()
})

// Search & Date filter states
const searchInput = ref('')
const startDate = ref('') // YYYY-MM-DD
const endDate = ref('')   // YYYY-MM-DD

const statusOptions = [
  { value: 'all', label: 'Status: Semua' },
  { value: 'completed', label: 'Status: Selesai' },
  { value: 'ready', label: 'Status: Siap Saji' },
  { value: 'preparing', label: 'Status: Dimasak' },
  { value: 'confirmed', label: 'Status: Menunggu' },
  { value: 'cancelled', label: 'Status: Dibatalkan' },
  { value: 'expired', label: 'Status: Kadaluarsa' },
]

const orderTypeOptions = [
  { value: 'all', label: 'Tipe: Semua' },
  { value: 'dine_in', label: 'Tipe: Dine In' },
  { value: 'takeaway', label: 'Tipe: Takeaway' },
]

const filtersConfig = computed<FilterBarItem[]>(() => [
  {
    key: 'status',
    options: statusOptions,
    width: 'w-48',
  },
  {
    key: 'orderType',
    options: orderTypeOptions,
    width: 'w-44',
  },
])

const filterValues = ref<Record<string, string | number>>({
  status: 'all',
  orderType: 'all',
})

const isFiltered = computed(() => {
  return (
    searchInput.value.trim() !== '' ||
    filterValues.value.status !== 'all' ||
    filterValues.value.orderType !== 'all' ||
    startDate.value !== '' ||
    endDate.value !== ''
  )
})

const clearFilters = () => {
  searchInput.value = ''
  filterValues.value = {
    status: 'all',
    orderType: 'all',
  }
  startDate.value = ''
  endDate.value = ''
}

const clearDates = () => {
  startDate.value = ''
  endDate.value = ''
}

// Modal detail order
const isDetailModalOpen = ref(false)
const selectedOrder = ref<any>(null)

const openOrderDetail = (order: any) => {
  selectedOrder.value = order
  isDetailModalOpen.value = true
}

// Standard table columns (1 data per column, exact matching other dashboard/POS tables)
const columns: TableColumn[] = [
  { key: 'orderNumber', label: 'ID Pesanan', width: '16%' },
  { key: 'customerName', label: 'Pelanggan', width: '16%' },
  { key: 'tableCode', label: 'Meja', align: 'center', width: '11%' },
  { key: 'items', label: 'Daftar Pesanan', width: '25%' },
  { key: 'createdAt', label: 'Waktu', align: 'center', width: '13%' },
  { key: 'status', label: 'Status', align: 'center', width: '13%' },
  { key: 'actions', label: 'Aksi', align: 'center', width: '6%' },
]

// All historical orders filtered & sorted
const historicalOrders = computed(() => {
  let list = [...posStore.orders]

  // Status Filter
  const status = filterValues.value.status
  if (status !== 'all') {
    if (status === 'completed') {
      list = list.filter(o => o.status === 'completed' || o.paymentStatus === 'paid')
    } else if (status === 'preparing') {
      list = list.filter(o => o.status === 'preparing' || (o.status as any) === 'cooking')
    } else {
      list = list.filter(o => o.status === status)
    }
  }

  // Order Type Filter
  const orderType = filterValues.value.orderType
  if (orderType === 'dine_in') {
    list = list.filter(o => o.orderType === 'dine_in' || !!o.tableCode)
  } else if (orderType === 'takeaway') {
    list = list.filter(o => o.orderType === 'takeaway' || !o.tableCode)
  }

  // Date Range Filter
  if (startDate.value || endDate.value) {
    list = list.filter(o => {
      if (!o.createdAt) return false
      const d = new Date(o.createdAt)
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      const orderDate = `${year}-${month}-${day}`

      if (startDate.value && endDate.value) {
        return orderDate >= startDate.value && orderDate <= endDate.value
      } else if (startDate.value) {
        return orderDate >= startDate.value
      } else if (endDate.value) {
        return orderDate <= endDate.value
      }
      return true
    })
  }

  // Search Filter
  const q = searchInput.value.trim().toLowerCase()
  if (q) {
    list = list.filter(o => {
      const matchHeader =
        o.orderNumber.toLowerCase().includes(q) ||
        (o.customerName && o.customerName.toLowerCase().includes(q)) ||
        (o.tableCode && o.tableCode.toLowerCase().includes(q))

      if (matchHeader) return true

      // Match item names
      if (o.items && Array.isArray(o.items)) {
        return o.items.some((it: any) =>
          (it.menuItemName || (it as any).name || '').toLowerCase().includes(q)
        )
      }
      return false
    })
  }

  // Strictly newest first
  return list.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
})

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'completed':
      return { variant: 'success' as const, label: 'Selesai' }
    case 'ready':
      return { variant: 'primary' as const, label: 'Siap Saji' }
    case 'preparing':
    case 'cooking':
      return { variant: 'warning' as const, label: 'Dimasak' }
    case 'confirmed':
      return { variant: 'info' as const, label: 'Menunggu' }
    case 'cancelled':
      return { variant: 'danger' as const, label: 'Dibatalkan' }
    case 'expired':
    default:
      return { variant: 'neutral' as const, label: 'Kadaluarsa' }
  }
}

const getTableDisplayName = (code?: string) => {
  if (!code || code === '0' || code.toLowerCase() === 'takeaway') return 'Takeaway'
  const digits = code.replace(/\D/g, '')
  if (digits) {
    return `Meja ${digits.padStart(2, '0')}`
  }
  return `Meja ${code}`
}

const formatItemsText = (items?: any[]) => {
  if (!items || items.length === 0) return '-'
  return items.map((it: any) => `${it.quantity}x ${it.menuItemName || it.name || 'Menu'}`).join(', ')
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0 space-y-4">
    <!-- Header: Riwayat Pesanan -->
    <div class="flex items-center justify-between gap-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
        {{ t('kds.historyTitle', 'Riwayat Pesanan') }}
      </h1>

      <span class="text-xs font-semibold px-3 py-1 rounded-full bg-[#E2E8F0] dark:bg-[#334155] text-[#475569] dark:text-[#CBD5E1]">
        Total: {{ historicalOrders.length }} Pesanan
      </span>
    </div>

    <!-- Reusable AppTable Component with Integrated Header -->
    <AppTable
      :columns="columns"
      :data="historicalOrders"
      :pageSize="15"
      :searchable="false"
      :loading="posStore.isLoading"
      :emptyIllustration="emptyRiwayatIllustration"
      emptyMessage="Belum ada riwayat pesanan yang sesuai"
      showNumbering
      numberingLabel="No"
      class="flex-1 min-h-0"
    >
      <!-- Integrated Header (Reusable AppTableFilterBar) -->
      <template #header>
        <AppTableFilterBar
          v-model:search="searchInput"
          searchPlaceholder="Cari ID, pelanggan, meja, menu..."
          searchWidth="w-full sm:w-[250px]"
          showDateRange
          v-model:startDate="startDate"
          v-model:endDate="endDate"
          :filters="filtersConfig"
          v-model:filterValues="filterValues"
          @reset-dates="clearDates"
        >
          <template #actions>
            <button
              v-if="isFiltered"
              type="button"
              @click="clearFilters"
              class="h-[38px] px-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-[#4880FF] hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold transition-colors cursor-pointer shrink-0"
              title="Reset Filter"
            >
              Reset
            </button>
          </template>
        </AppTableFilterBar>
      </template>

      <!-- Cell 1: ID Pesanan (1 Data) -->
      <template #cell-orderNumber="{ row, value }">
        <span
          class="font-bold text-sm text-[#4880FF] hover:underline cursor-pointer tracking-tight whitespace-nowrap"
          @click="openOrderDetail(row)"
        >
          {{ value }}
        </span>
      </template>

      <!-- Cell 2: Pelanggan (1 Data) -->
      <template #cell-customerName="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white truncate block">
          {{ formatCustomerName(value) }}
        </span>
      </template>

      <!-- Cell 3: Meja (1 Data) -->
      <template #cell-tableCode="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">
          {{ getTableDisplayName(value) }}
        </span>
      </template>

      <!-- Cell 4: Daftar Pesanan (1 Data) -->
      <template #cell-items="{ value }">
        <span
          class="text-sm font-medium text-[#202224] dark:text-[#CBD5E1] truncate block"
          :title="formatItemsText(value)"
        >
          {{ formatItemsText(value) }}
        </span>
      </template>

      <!-- Cell 5: Waktu (1 Data) -->
      <template #cell-createdAt="{ value }">
        <span class="font-medium text-sm text-[#606060] dark:text-[#E6E6E6] tabular-nums whitespace-nowrap">
          {{ formatTimeOnly(value) }} WIB
        </span>
      </template>

      <!-- Cell 6: Status (1 Data) -->
      <template #cell-status="{ value }">
        <AppBadge :variant="getStatusBadge(value).variant" size="md">
          {{ getStatusBadge(value).label }}
        </AppBadge>
      </template>

      <!-- Cell 7: Aksi -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <button
            type="button"
            @click="openOrderDetail(row)"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] hover:bg-[#4880FF]/10 transition-colors cursor-pointer"
            title="Lihat Detail Pesanan"
          >
            <AppIcon name="receipt_long" :size="18" />
          </button>
        </div>
      </template>
    </AppTable>

    <!-- Order Detail Modal -->
    <AppModal v-model="isDetailModalOpen" title="Rincian Pesanan Dapur" maxWidth="md">
      <div v-if="selectedOrder" class="space-y-4 py-2">
        <!-- Order Header Overview -->
        <div class="p-3.5 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <h4 class="font-bold text-sm text-[#1E293B] dark:text-white">
              {{ selectedOrder.orderNumber }}
            </h4>
            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5">
              {{ formatCustomerName(selectedOrder.customerName) }} • {{ getTableDisplayName(selectedOrder.tableCode) }}
            </p>
          </div>
          <div class="text-right">
            <AppBadge :variant="getStatusBadge(selectedOrder.status).variant" size="md">
              {{ getStatusBadge(selectedOrder.status).label }}
            </AppBadge>
            <p class="text-[11px] text-[#94A3B8] mt-1 font-mono">
              {{ formatTimeOnly(selectedOrder.createdAt) }} WIB • {{ formatDate(selectedOrder.createdAt) }}
            </p>
          </div>
        </div>

        <!-- Items Table -->
        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
          <table class="w-full text-xs">
            <thead class="bg-slate-100 dark:bg-slate-800/80 font-bold text-[#64748B] dark:text-[#94A3B8] border-b border-slate-200 dark:border-slate-700">
              <tr>
                <th class="py-2.5 px-3 text-left">Item Menu</th>
                <th class="py-2.5 px-3 text-center w-16">Qty</th>
                <th class="py-2.5 px-3 text-left">Catatan Khusus</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="it in (selectedOrder.items || [])" :key="it.id" class="text-[#1E293B] dark:text-white">
                <td class="py-2.5 px-3 font-semibold">
                  {{ it.menuItemName || (it as any).name || 'Menu Item' }}
                </td>
                <td class="py-2.5 px-3 text-center font-bold text-[#4880FF]">
                  {{ it.quantity }}x
                </td>
                <td class="py-2.5 px-3 text-[#64748B] dark:text-[#94A3B8] italic">
                  {{ it.notes || (it as any).specialNotes || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Notes if any -->
        <div v-if="selectedOrder.notes" class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-xl">
          <span class="block text-xs font-bold text-amber-800 dark:text-amber-200 mb-0.5">Catatan Pesanan:</span>
          <p class="text-xs text-amber-900 dark:text-amber-300">{{ selectedOrder.notes }}</p>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end w-full">
          <AppButton variant="outline" size="md" @click="isDetailModalOpen = false">
            Tutup
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
