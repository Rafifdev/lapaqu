<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useFormat } from '@/composables/useFormat'
import { usePosStore } from '@/stores/pos'
import AppTable, { type TableColumn } from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { Printer } from 'lucide-vue-next'
import emptyRiwayatIllustration from '@/assets/empty_state/empty-riwayat.svg'

const { formatCurrency, formatTimeOnly, formatDate } = useFormat()
const posStore = usePosStore()

onMounted(() => {
  posStore.fetchOrders()
})

// Search with Debounce (300ms)
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
})

// Filter & Sort State (Handled directly by segmented pill dropdowns & date)
const selectedOrderType = ref('all') // all | dine_in | takeaway
const selectedPaymentMethod = ref('all') // all | cash | qris | card
const selectedSort = ref('asc') // newest | oldest | high | low

const selectedStatus = ref('all') // all | completed | expired | cancelled

const statusOptions = [
  { value: 'all', label: 'Status: Semua' },
  { value: 'completed', label: 'Status: Selesai / Lunas' },
  { value: 'expired', label: 'Status: Kadaluarsa' },
  { value: 'cancelled', label: 'Status: Dibatalkan' },
]

const orderTypeOptions = [
  { value: 'all', label: 'Tipe: Semua' },
  { value: 'dine_in', label: 'Tipe: Dine In' },
  { value: 'takeaway', label: 'Tipe: Takeaway' },
]

const paymentMethodOptions = [
  { value: 'all', label: 'Metode: Semua' },
  { value: 'cash', label: 'Metode: Cash' },
  { value: 'qris', label: 'Metode: QRIS' },
  { value: 'card', label: 'Metode: Card' },
]

const txSortOptions = [
  { value: 'asc', label: 'Sort: Ascending' },
  { value: 'desc', label: 'Sort: Descending' },
  { value: 'high', label: 'Total: Tertinggi' },
  { value: 'low', label: 'Total: Terendah' },
]
const startDate = ref('') // YYYY-MM-DD
const endDate = ref('') // YYYY-MM-DD

// Filtered and Sorted Transactions
const transactions = computed(() => {
  let list = posStore.orders.filter(
    o => o.status === 'completed' || o.paymentStatus === 'paid' || o.status === 'expired' || o.status === 'cancelled'
  )

  // Status Filter
  if (selectedStatus.value !== 'all') {
    if (selectedStatus.value === 'completed') {
      list = list.filter(o => o.status === 'completed' || o.paymentStatus === 'paid')
    } else if (selectedStatus.value === 'expired') {
      list = list.filter(o => o.status === 'expired')
    } else if (selectedStatus.value === 'cancelled') {
      list = list.filter(o => o.status === 'cancelled')
    }
  }

  // 1. Debounced Search Query (Order #, Customer, Table, Type, Method, Time, and Nominal/Amount)
  if (debouncedSearch.value) {
    const q = debouncedSearch.value.toLowerCase()
    const numericQ = q.replace(/[^0-9]/g, '')
    list = list.filter(t => {
      const matchText =
        t.orderNumber.toLowerCase().includes(q) ||
        (t.customerName && t.customerName.toLowerCase().includes(q)) ||
        (t.tableCode && t.tableCode.toLowerCase().includes(q)) ||
        (t.orderType && t.orderType.toLowerCase().includes(q)) ||
        (t.paymentMethod && t.paymentMethod.toLowerCase().includes(q)) ||
        formatTimeOnly(t.createdAt).toLowerCase().includes(q) ||
        formatDate(t.createdAt).toLowerCase().includes(q)

      if (matchText) return true

      // Nominal / Amount search (raw digits, formatted Rp, or stripped thousand separators)
      const amountStr = String(t.totalAmount || 0)
      const formattedAmount = formatCurrency(t.totalAmount || 0).toLowerCase().replace(/\s+/g, ' ')
      const qNormalized = q.replace(/\s+/g, ' ')

      return (
        amountStr.includes(q) ||
        formattedAmount.includes(qNormalized) ||
        (numericQ.length > 0 && amountStr.includes(numericQ))
      )
    })
  }

  // 2. Order Type Filter
  if (selectedOrderType.value === 'dine_in') {
    list = list.filter(t => t.orderType === 'dine_in' || !!t.tableCode)
  } else if (selectedOrderType.value === 'takeaway') {
    list = list.filter(t => t.orderType === 'takeaway' || !t.tableCode)
  }

  // 3. Payment Method Filter
  if (selectedPaymentMethod.value !== 'all') {
    list = list.filter(t => (t.paymentMethod || '').toLowerCase() === selectedPaymentMethod.value)
  }

  // 4. Date Range Filter (Filter by local calendar date range)
  if (startDate.value || endDate.value) {
    list = list.filter(t => {
      if (!t.createdAt) return false
      const d = new Date(t.createdAt)
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

  // 4. Sort (Default Ascending)
  list.sort((a, b) => {
    if (selectedSort.value === 'asc') {
      return (a.orderNumber || '').localeCompare(b.orderNumber || '') || new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()
    }
    if (selectedSort.value === 'desc') {
      return (b.orderNumber || '').localeCompare(a.orderNumber || '') || new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
    }
    if (selectedSort.value === 'newest') {
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
    }
    if (selectedSort.value === 'oldest') {
      return new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()
    }
    if (selectedSort.value === 'high') {
      return (b.totalAmount || 0) - (a.totalAmount || 0)
    }
    if (selectedSort.value === 'low') {
      return (a.totalAmount || 0) - (b.totalAmount || 0)
    }
    return 0
  })

  return list
})

// Table Columns with Evenly Distributed Widths prioritizing ID Pesanan
const columns: TableColumn[] = [
  { key: 'orderNumber', label: 'ID Pesanan', width: '20%' },
  { key: 'customerName', label: 'Pelanggan', width: '15%' },
  { key: 'orderType', label: 'Tipe Transaksi', align: 'center', width: '12%' },
  { key: 'tableCode', label: 'Meja', align: 'center', width: '9%' },
  { key: 'createdAt', label: 'Waktu', width: '11%' },
  { key: 'paymentMethod', label: 'Metode', align: 'center', width: '12%' },
  { key: 'totalAmount', label: 'Total', align: 'right', width: '11%' },
  { key: 'status', label: 'Status', align: 'center', width: '10%' },
  { key: 'actions', label: 'Aksi', align: 'center', width: '60px' },
]

// Modal detail & print receipt states
const isReceiptModalOpen = ref(false)
const selectedOrder = ref<any>(null)

const handlePrintReceipt = (order: any) => {
  selectedOrder.value = order
  isReceiptModalOpen.value = true
}

const formatPaymentMethod = (method?: string) => {
  if (!method) return 'Cash'
  const m = method.toLowerCase()
  if (m === 'cash') return 'Cash'
  if (m === 'qris') return 'QRIS'
  if (m === 'card' || m === 'debit') return 'Card'
  return method.charAt(0).toUpperCase() + method.slice(1).toLowerCase()
}

const handleConfirmPrint = () => {
  window.print()
  isReceiptModalOpen.value = false
}


</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <!-- Header: Riwayat Order (Fixed at top) -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
        Riwayat Order
      </h1>
    </div>

    <!-- Reusable Dashboard AppTable Component with Integrated Header -->
    <AppTable :columns="columns" :data="transactions" :pageSize="20" :searchable="false" :loading="posStore.isLoading" showNumbering
      numberingLabel="No" class="flex-1 min-h-0">
      <!-- Integrated Header (Clean, Perfect Fit Capsule Design with 300ms Debounce) -->
      <template #header>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl flex-wrap sm:flex-nowrap">
            <!-- 0. Status Dropdown -->
            <AppFilterDropdown
              v-model="selectedStatus"
              :options="statusOptions"
              width="w-52"
            />

            <!-- 1. Order Type Dropdown -->
            <AppFilterDropdown
              v-model="selectedOrderType"
              :options="orderTypeOptions"
              width="w-48"
            />

            <!-- 2. Payment Method Dropdown -->
            <AppFilterDropdown
              v-model="selectedPaymentMethod"
              :options="paymentMethodOptions"
              width="w-48"
            />

            <!-- 3. Sort By Dropdown -->
            <AppFilterDropdown
              v-model="selectedSort"
              :options="txSortOptions"
              width="w-52"
            />
          </div>

          <!-- Right: Search Pool Input & Native Date Filter (Pushed to right with ml-auto) -->
          <div class="flex items-center gap-2.5 ml-auto flex-wrap sm:flex-nowrap">
            <!-- Search Pool Input with Debouncing (Reusable AppInput matching filter height) -->
            <div class="w-full sm:w-[250px] shrink-0">
              <AppInput
                v-model="searchInput"
                placeholder="T-01, 16.40 WIB, Cash ..."
                suffixIcon="search"
                clearable
                inputClass="!h-[38px] !text-xs sm:!text-sm"
              />
            </div>

            <!-- Native Date Range Filter Input (Matching filter height of 38px) -->
            <div class="h-[38px] w-full sm:w-[250px] flex items-center justify-between gap-1 bg-white dark:bg-[#1B2431] border border-[#CBD5E1] dark:border-[#334155] rounded-xl px-2.5 text-sm font-semibold text-[#1E293B] dark:text-white transition-all focus-within:ring-2 focus-within:ring-[#4880FF]/25 focus-within:border-[#4880FF] shrink-0">
              <input
                type="date"
                v-model="startDate"
                class="min-w-0 w-full bg-transparent border-0 text-xs sm:text-[13px] font-semibold text-[#1E293B] dark:text-white focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
              />
              <span class="text-[#94A3B8] text-xs font-bold px-0.5 shrink-0">-</span>
              <input
                type="date"
                v-model="endDate"
                :min="startDate || undefined"
                class="min-w-0 w-full bg-transparent border-0 text-xs sm:text-[13px] font-semibold text-[#1E293B] dark:text-white focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
              />
              <button
                v-if="startDate || endDate"
                type="button"
                @click="startDate = ''; endDate = ''"
                title="Hapus filter rentang tanggal"
                class="text-[#94A3B8] hover:text-[#202224] dark:hover:text-white cursor-pointer p-0.5 rounded transition-colors flex items-center shrink-0 ml-0.5"
              >
                <AppIcon name="close" :size="15" />
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Custom Cell: ID Pesanan (Prominent) -->
      <template #cell-orderNumber="{ row, value }">
        <span class="font-bold text-sm text-[#4880FF] hover:underline cursor-pointer tracking-tight whitespace-nowrap"
          @click="handlePrintReceipt(row)">
          {{ value }}
        </span>
      </template>

      <!-- Custom Cell: Pelanggan -->
      <template #cell-customerName="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white truncate block">
          {{ value || 'Pelanggan Umum' }}
        </span>
      </template>

      <!-- Custom Cell: Tipe Transaksi (AppBadge rounded full - TitleCase/CamelCase) -->
      <template #cell-orderType="{ row, value }">
        <AppBadge :variant="(value === 'dine_in' || row.tableCode) ? 'primary' : 'warning'" size="md" rounded="full">
          {{ (value === 'takeaway' || (!row.tableCode && value !== 'dine_in')) ? 'Takeaway' : 'Dine In' }}
        </AppBadge>
      </template>

      <!-- Custom Cell: Meja -->
      <template #cell-tableCode="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">
          {{ value ? (value.startsWith('T-') ? value : `T-${String(value).padStart(2, '0')}`) : '-' }}
        </span>
      </template>

      <!-- Custom Cell: Waktu -->
      <template #cell-createdAt="{ value }">
        <span class="font-medium text-sm text-[#606060] dark:text-[#E6E6E6] tabular-nums whitespace-nowrap">
          {{ formatTimeOnly(value) }} WIB
        </span>
      </template>

      <!-- Custom Cell: Payment Method (AppBadge rounded full - TitleCase/CamelCase) -->
      <template #cell-paymentMethod="{ value }">
        <AppBadge
          :variant="value?.toLowerCase() === 'qris' ? 'primary' : (value?.toLowerCase() === 'card' || value?.toLowerCase() === 'debit' ? 'purple' : 'success')"
          size="md" rounded="full">
          {{ formatPaymentMethod(value) }}
        </AppBadge>
      </template>

      <!-- Custom Cell: Total Amount -->
      <template #cell-totalAmount="{ value }">
        <span class="font-black text-sm text-[#202224] dark:text-white tabular-nums whitespace-nowrap">
          {{ formatCurrency(value) }}
        </span>
      </template>

      <!-- Custom Cell: Status (AppBadge rounded full) -->
      <template #cell-status="{ row }">
        <AppBadge v-if="row.status === 'expired'" variant="neutral" size="md" rounded="full">
          Kadaluarsa
        </AppBadge>
        <AppBadge v-else-if="row.status === 'cancelled'" variant="danger" size="md" rounded="full">
          Dibatalkan
        </AppBadge>
        <AppBadge v-else variant="success" size="md" rounded="full">
          {{ row.paymentStatus === 'paid' || row.status === 'completed' ? 'Lunas' : 'Selesai' }}
        </AppBadge>
      </template>

      <!-- Custom Cell: Actions -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center gap-1.5">
          <button @click.stop="handlePrintReceipt(row)"
            class="p-2 rounded-lg text-[#606060] dark:text-[#94A3B8] hover:text-[#4880FF] hover:bg-[#E2EAF8] dark:hover:bg-[#4880FF]/20 transition-colors cursor-pointer"
            title="Cetak Struk">
            <Printer class="w-4 h-4" />
          </button>
        </div>
      </template>

      <!-- Custom Empty State with empty-riwayat.svg -->
      <template #empty>
        <div
          class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-8">
          <div class="relative flex items-center justify-center mb-4 sm:mb-5 pointer-events-none">
            <img :src="emptyRiwayatIllustration" alt="Riwayat Kosong"
              class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
          </div>
          <h3 class="text-xl sm:text-2xl md:text-[26px] font-black text-[#1E293B] dark:text-white tracking-tight">
            Whoops! :(
          </h3>
          <p
            class="text-xs sm:text-base font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
            Belum ada riwayat transaksi saat ini
          </p>
        </div>
      </template>
    </AppTable>



    <!-- Modal Cetak / Preview Struk -->
    <AppModal :show="isReceiptModalOpen" v-model="isReceiptModalOpen" title="Detail & Struk Transaksi" maxWidth="sm">
      <div v-if="selectedOrder" class="space-y-4 py-1 text-xs">
        <div class="text-center pb-3 border-b border-dashed border-[#E2E8F0] dark:border-[#334155]">
          <h3 class="text-base font-bold text-[#1E293B] dark:text-white">LAPAQU POS</h3>
          <p class="text-[11px] text-[#64748B] dark:text-[#94A3B8]">Struk Resmi Pembayaran Kasir</p>
          <p class="font-mono text-xs font-bold text-[#4880FF] mt-1">{{ selectedOrder.orderNumber }}</p>
        </div>

        <div class="space-y-1.5 text-[#64748B] dark:text-[#94A3B8]">
          <div class="flex justify-between">
            <span>Waktu Transaksi:</span>
            <span class="font-medium text-[#1E293B] dark:text-white">{{ formatDate(selectedOrder.createdAt) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Pelanggan:</span>
            <span class="font-bold text-[#1E293B] dark:text-white">{{ selectedOrder.customerName || 'Pelanggan Umum'
              }}</span>
          </div>
          <div class="flex justify-between">
            <span>Tipe / Meja:</span>
            <span class="font-medium text-[#1E293B] dark:text-white">{{ selectedOrder.tableCode ? (selectedOrder.tableCode.startsWith('T-') ? selectedOrder.tableCode : `T-${selectedOrder.tableCode}`) : 'Takeaway' }}</span>
          </div>
          <div class="flex justify-between">
            <span>Metode Bayar:</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase">{{ selectedOrder.paymentMethod ||
              'CASH' }}</span>
          </div>
        </div>

        <!-- Order Items -->
        <div class="border-t border-b border-dashed border-[#E2E8F0] dark:border-[#334155] py-2.5 space-y-1.5">
          <div v-for="(item, idx) in selectedOrder.items" :key="idx" class="flex justify-between text-xs font-medium">
            <span class="text-[#1E293B] dark:text-white">{{ item.quantity }}x {{ item.menuItemName }}</span>
            <span class="text-[#1E293B] dark:text-white tabular-nums font-bold">{{ formatCurrency(item.subtotal)
              }}</span>
          </div>
        </div>

        <!-- Total -->
        <div class="flex justify-between text-sm font-black pt-1">
          <span class="text-[#1E293B] dark:text-white">Total Pembayaran</span>
          <span class="text-[#4880FF] tabular-nums">{{ formatCurrency(selectedOrder.totalAmount) }}</span>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full gap-3">
          <AppButton variant="outline" size="md" @click="isReceiptModalOpen = false" class="!rounded-lg flex-1">
            Tutup
          </AppButton>
          <AppButton variant="primary" size="md" icon="print" @click="handleConfirmPrint" class="!rounded-lg flex-1">
            Cetak
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
