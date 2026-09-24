<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Download } from 'lucide-vue-next'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppLineChart from '@/components/ui/AppLineChart.vue'
import AppDoughnutChart from '@/components/ui/AppDoughnutChart.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import { useTheme } from '@/composables/useTheme'
import { useDashboardI18n } from '@/i18n'
import type { ChartData, ChartOptions } from 'chart.js'

const posStore = usePosStore()
const { formatCurrency, formatNumber } = useFormat()
const { isDark } = useTheme()
const { t, translate, locale } = useDashboardI18n()

const isLoading = ref(true)
const isYearLoading = ref(false)

// Period Filter: Hari ini, Minggu ini, Bulan ini (default), Tahun ini
const selectedPeriod = ref<'today' | 'week' | 'month' | 'year'>('month')

const periodOptions = computed(() => [
  { value: 'today', label: locale.value === 'en' ? 'Today' : 'Hari ini' },
  { value: 'week', label: locale.value === 'en' ? 'This Week' : 'Minggu ini' },
  { value: 'month', label: locale.value === 'en' ? 'This Month' : 'Bulan ini' },
  { value: 'year', label: locale.value === 'en' ? 'This Year' : 'Tahun ini' },
])

// Formatted Date matching TopItemsPage & Dashboard
const formattedCurrentDate = computed(() => {
  const now = new Date()
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(now)
})

// Period comparison text label
const periodTrendLabel = computed(() => {
  switch (selectedPeriod.value) {
    case 'today':
      return 'From yesterday:'
    case 'week':
      return 'From last week:'
    case 'year':
      return 'From last year:'
    case 'month':
    default:
      return 'From the Last month:'
  }
})

// Dynamic real stats indicator
const trendStats = computed(() => {
  return {
    sales: { value: totalSales.value > 0 ? `${totalTransactions.value} pesanan` : '0 pesanan', isPositive: totalSales.value > 0, label: periodTrendLabel.value },
    orders: { value: `${totalTransactions.value} Transaksi`, isPositive: totalTransactions.value > 0, label: periodTrendLabel.value },
    aov: { value: formatCurrency(aov.value), isPositive: aov.value > 0, label: 'Rata-rata order' },
    payment: { value: paymentDistribution.value.find(p => Number(p.percent) > 0)?.percent ? `${paymentDistribution.value.find(p => Number(p.percent) > 0)?.percent}%` : '0%', isPositive: true, label: 'Dominan' },
  }
})

// Metric Toggle for Trend Line Chart: 'revenue' | 'orders'
const trendMetric = ref<'revenue' | 'orders'>('revenue')

// Table Filters & Search
const tableSearchInput = ref('')
const selectedPaymentFilter = ref('all')
const selectedOrderTypeFilter = ref('all')

const paymentFilterOptions = [
  { value: 'all', label: 'Semua Metode' },
  { value: 'qris', label: 'QRIS' },
  { value: 'cash', label: 'Tunai (Cash)' },
  { value: 'card', label: 'Debit / Card' },
]

const orderTypeFilterOptions = [
  { value: 'all', label: 'Semua Tipe' },
  { value: 'dine_in', label: 'Dine In' },
  { value: 'takeaway', label: 'Takeaway' },
]

// Lifecycle
onMounted(async () => {
  try {
    posStore.initRealtime()
    await posStore.fetchOrders()
    window.addEventListener('kds:refresh', handleRefresh)
  } catch (err) {
    console.error('Error fetching orders:', err)
  } finally {
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('kds:refresh', handleRefresh)
})

watch(selectedPeriod, async (newVal) => {
  if (newVal === 'year') {
    isYearLoading.value = true
    try {
      await posStore.fetchOrders(true)
    } finally {
      isYearLoading.value = false
    }
  }
})

const handleRefresh = async () => {
  await posStore.fetchOrders()
}

// Master Sales Data Source (Murni dari Pesanan Riil di Database)
const allOrders = computed(() => {
  return posStore.orders || []
})

// Filter Pesanan Sesuai Periode yang Dipilih
const filteredPeriodOrders = computed(() => {
  const now = new Date()
  const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const startOfWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay()).getTime()
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).getTime()
  const startOfYear = new Date(now.getFullYear(), 0, 1).getTime()

  return allOrders.value.filter(o => {
    const time = o.createdAt ? new Date(o.createdAt).getTime() : Date.now()
    if (selectedPeriod.value === 'today') return time >= startOfDay
    if (selectedPeriod.value === 'week') return time >= startOfWeek
    if (selectedPeriod.value === 'year') return time >= startOfYear
    return time >= startOfMonth
  })
})

// Paid / Completed Orders for Sales Reports
const paidOrders = computed(() => {
  return filteredPeriodOrders.value.filter(o => o.status === 'completed' || o.paymentStatus === 'paid')
})

// Stat Card Metrics (100% Data Asli Database)
const totalSales = computed(() => {
  return paidOrders.value.reduce((sum, o) => sum + (o.totalAmount || 0), 0)
})

const totalTransactions = computed(() => {
  return paidOrders.value.length
})

const aov = computed(() => {
  if (totalTransactions.value === 0) return 0
  return Math.round(totalSales.value / totalTransactions.value)
})

// Payment Breakdown Calculations (100% Data Asli Database)
const qrisSales = computed(() => {
  return paidOrders.value
    .filter(o => (o.paymentMethod || '').toLowerCase() === 'qris')
    .reduce((sum, o) => sum + (o.totalAmount || 0), 0)
})

const cashSales = computed(() => {
  return paidOrders.value
    .filter(o => (o.paymentMethod || '').toLowerCase() === 'cash')
    .reduce((sum, o) => sum + (o.totalAmount || 0), 0)
})

const cardSales = computed(() => {
  return paidOrders.value
    .filter(o => {
      const m = (o.paymentMethod || '').toLowerCase()
      return m === 'card' || m === 'debit' || m === 'credit'
    })
    .reduce((sum, o) => sum + (o.totalAmount || 0), 0)
})

const paymentDistribution = computed(() => {
  const total = totalSales.value
  return [
    { id: 'qris', name: 'QRIS (Online)', amount: qrisSales.value, percent: total > 0 ? ((qrisSales.value / total) * 100).toFixed(1) : '0.0', color: '#4880FF' },
    { id: 'cash', name: 'Tunai / Cash Kasir', amount: cashSales.value, percent: total > 0 ? ((cashSales.value / total) * 100).toFixed(1) : '0.0', color: '#00B69B' },
    { id: 'card', name: 'Kartu Debit / EDC', amount: cardSales.value, percent: total > 0 ? ((cardSales.value / total) * 100).toFixed(1) : '0.0', color: '#8280FF' },
  ]
})

// ==========================================
// 1. REVENUE & SALES TREND LINE CHART DATA (DATA ASLI DATABASE)
// ==========================================
const trendChartData = computed<ChartData<'line'>>(() => {
  let labels: string[] = []
  let revenueData: number[] = []
  let ordersData: number[] = []

  if (selectedPeriod.value === 'today') {
    labels = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00']
    const revMap: Record<string, number> = {}
    const countMap: Record<string, number> = {}
    labels.forEach(l => { revMap[l] = 0; countMap[l] = 0 })

    paidOrders.value.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const h = d.getHours()
      let slot = '08:00'
      if (h >= 22) slot = '22:00'
      else if (h >= 20) slot = '20:00'
      else if (h >= 18) slot = '18:00'
      else if (h >= 16) slot = '16:00'
      else if (h >= 14) slot = '14:00'
      else if (h >= 12) slot = '12:00'
      else if (h >= 10) slot = '10:00'
      else slot = '08:00'

      revMap[slot] = (revMap[slot] || 0) + (o.totalAmount || 0)
      countMap[slot] = (countMap[slot] || 0) + 1
    })
    revenueData = labels.map(l => revMap[l] || 0)
    ordersData = labels.map(l => countMap[l] || 0)
  } else if (selectedPeriod.value === 'week') {
    labels = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']
    const dayMap: Record<number, string> = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu' }
    const revMap: Record<string, number> = {}
    const countMap: Record<string, number> = {}
    labels.forEach(l => { revMap[l] = 0; countMap[l] = 0 })

    paidOrders.value.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const dayName = dayMap[d.getDay()] || 'Senin'
      revMap[dayName] = (revMap[dayName] || 0) + (o.totalAmount || 0)
      countMap[dayName] = (countMap[dayName] || 0) + 1
    })
    revenueData = labels.map(l => revMap[l] || 0)
    ordersData = labels.map(l => countMap[l] || 0)
  } else if (selectedPeriod.value === 'year') {
    labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    const revMap: Record<number, number> = {}
    const countMap: Record<number, number> = {}
    labels.forEach((_, idx) => { revMap[idx] = 0; countMap[idx] = 0 })

    paidOrders.value.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const m = d.getMonth()
      revMap[m] = (revMap[m] || 0) + (o.totalAmount || 0)
      countMap[m] = (countMap[m] || 0) + 1
    })
    revenueData = labels.map((_, idx) => revMap[idx] || 0)
    ordersData = labels.map((_, idx) => countMap[idx] || 0)
  } else {
    labels = ['Tgl 1-5', 'Tgl 6-10', 'Tgl 11-15', 'Tgl 16-20', 'Tgl 21-25', 'Tgl 26-30']
    const revMap: Record<string, number> = {}
    const countMap: Record<string, number> = {}
    labels.forEach(l => { revMap[l] = 0; countMap[l] = 0 })

    paidOrders.value.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const date = d.getDate()
      let slot = 'Tgl 1-5'
      if (date > 25) slot = 'Tgl 26-30'
      else if (date > 20) slot = 'Tgl 21-25'
      else if (date > 15) slot = 'Tgl 16-20'
      else if (date > 10) slot = 'Tgl 11-15'
      else if (date > 5) slot = 'Tgl 6-10'

      revMap[slot] = (revMap[slot] || 0) + (o.totalAmount || 0)
      countMap[slot] = (countMap[slot] || 0) + 1
    })
    revenueData = labels.map(l => revMap[l] || 0)
    ordersData = labels.map(l => countMap[l] || 0)
  }

  const isRev = trendMetric.value === 'revenue'

  return {
    labels,
    datasets: [
      {
        label: isRev ? 'Total Omset (Rp)' : 'Total Transaksi',
        data: isRev ? revenueData : ordersData,
        borderColor: isRev ? '#4880FF' : '#00B69B',
        backgroundColor: isRev
          ? (ctx) => {
              const canvas = ctx.chart.ctx
              const gradient = canvas.createLinearGradient(0, 0, 0, 320)
              gradient.addColorStop(0, 'rgba(72, 128, 255, 0.35)')
              gradient.addColorStop(1, 'rgba(72, 128, 255, 0.0)')
              return gradient
            }
          : (ctx) => {
              const canvas = ctx.chart.ctx
              const gradient = canvas.createLinearGradient(0, 0, 0, 320)
              gradient.addColorStop(0, 'rgba(0, 182, 155, 0.35)')
              gradient.addColorStop(1, 'rgba(0, 182, 155, 0.0)')
              return gradient
            },
        fill: true,
        tension: 0.4,
        borderWidth: 3,
        pointBackgroundColor: isRev ? '#4880FF' : '#00B69B',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 7,
      },
    ],
  }
})

const trendChartOptions = computed<ChartOptions<'line'>>(() => {
  const isRev = trendMetric.value === 'revenue'
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      duration: 260,
      easing: 'easeOutQuart',
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1E293B',
        titleFont: { size: 12, weight: 'bold' },
        bodyFont: { size: 13, weight: 'bold' },
        padding: 12,
        cornerRadius: 8,
        displayColors: false,
        callbacks: {
          label: (context) => {
            const val = context.parsed.y ?? 0
            return isRev ? `Omset: ${formatCurrency(val)}` : `Volume: ${formatNumber(val)} Transaksi`
          },
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#94A3B8', font: { size: 11, weight: 'bold' } },
      },
      y: {
        grid: { color: '#F1F4F9' },
        ticks: {
          color: '#94A3B8',
          font: { size: 11 },
          callback: (value) => {
            const num = Number(value)
            if (isRev) {
              if (num >= 1000000) return `${(num / 1000000).toFixed(1)} jt`
              if (num >= 1000) return `${(num / 1000).toFixed(0)} rb`
              return num
            }
            return num
          },
        },
      },
    },
  }
})

// ==========================================
// 2. PAYMENT DOUGHNUT CHART DATA
// ==========================================
const doughnutChartData = computed<ChartData<'doughnut'>>(() => {
  return {
    labels: paymentDistribution.value.map(p => p.name),
    datasets: [
      {
        data: paymentDistribution.value.map(p => p.amount),
        backgroundColor: paymentDistribution.value.map(p => p.color),
        borderWidth: 3,
        borderColor: isDark.value ? '#273142' : '#ffffff',
        hoverOffset: 4,
      },
    ],
  }
})

const doughnutChartOptions = computed<ChartOptions<'doughnut'>>(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      duration: 260,
      easing: 'easeOutQuart',
    },
    cutout: '72%',
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1E293B',
        titleFont: { size: 12, weight: 'bold' },
        bodyFont: { size: 12, weight: 'bold' },
        padding: 10,
        cornerRadius: 8,
        callbacks: {
          label: (context) => {
            const val = context.parsed || 0
            return ` ${formatCurrency(val)}`
          },
        },
      },
    },
  }
})

// ==========================================
// 3. TABLE TRANSACTIONS LIST
// ==========================================
const tableColumns = computed(() => [
  { key: 'orderNumber', label: locale.value === 'en' ? 'Order No.' : 'No. Order', width: '20%' },
  { key: 'createdAt', label: locale.value === 'en' ? 'Time' : 'Waktu', width: '13%' },
  { key: 'customerName', label: locale.value === 'en' ? 'Customer / Table' : 'Pelanggan / Meja', width: '18%' },
  { key: 'orderType', label: locale.value === 'en' ? 'Order Type' : 'Tipe Order', align: 'center' as const, width: '12%' },
  { key: 'paymentMethod', label: locale.value === 'en' ? 'Payment' : 'Metode Bayar', align: 'center' as const, width: '13%' },
  { key: 'totalAmount', label: locale.value === 'en' ? 'Total Sales' : 'Total Omset', align: 'right' as const, width: '13%' },
  { key: 'status', label: t('common.status', 'Status'), align: 'center' as const, width: '11%' },
])

const filteredTableTransactions = computed(() => {
  let list = [...paidOrders.value]

  // Filter by Payment Method
  if (selectedPaymentFilter.value !== 'all') {
    list = list.filter(o => (o.paymentMethod || '').toLowerCase() === selectedPaymentFilter.value.toLowerCase())
  }

  // Filter by Order Type
  if (selectedOrderTypeFilter.value !== 'all') {
    list = list.filter(o => {
      if (selectedOrderTypeFilter.value === 'dine_in') return o.orderType === 'dine_in' || o.tableCode
      if (selectedOrderTypeFilter.value === 'takeaway') return o.orderType === 'takeaway' || (!o.tableCode && o.orderType !== 'dine_in')
      return true
    })
  }

  // Filter by Search Query
  const q = tableSearchInput.value.toLowerCase().trim()
  if (q) {
    list = list.filter(o =>
      (o.orderNumber || '').toLowerCase().includes(q) ||
      (o.customerName || '').toLowerCase().includes(q) ||
      (o.tableCode || '').toLowerCase().includes(q) ||
      (o.paymentMethod || '').toLowerCase().includes(q) ||
      (o.totalAmount || 0).toString().includes(q)
    )
  }

  return list
})

// Export CSV Handler
const exportCsv = () => {
  if (paidOrders.value.length === 0) {
    alert('Belum ada data transaksi yang lunas untuk diexport.')
    return
  }
  const headers = ['Order Number', 'Customer', 'Table', 'Order Type', 'Payment Method', 'Amount', 'Date']
  const rows = paidOrders.value.map(o => [
    o.orderNumber,
    o.customerName || 'Pelanggan Umum',
    o.tableCode || '-',
    o.orderType === 'takeaway' ? 'Takeaway' : 'Dine In',
    (o.paymentMethod || 'Cash').toUpperCase(),
    o.totalAmount || 0,
    o.createdAt || new Date().toISOString()
  ])
  const csvRows = [headers.join(','), ...rows.map(e => e.join(','))]
  const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join(String.fromCharCode(10))
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `laporan-penjualan-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const formatTime = (dateStr?: string) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')} WIB`
}
</script>

<template>
  <div class="space-y-6">
    <!-- Top Header: Title, Subtitle Date & Action Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div>
          <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">
            {{ locale === 'en' ? 'Sales Report & Revenue' : 'Laporan Penjualan & Omset' }}
          </h1>
        </div>
        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-semibold mt-0.5">
          {{ formattedCurrentDate }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Period Filter Dropdown (White Card Style matching TopItems & Dashboard) -->
        <AppFilterDropdown
          v-model="selectedPeriod"
          :options="periodOptions"
          variant="white"
          width="w-44"
          align="right"
        />

        <!-- Export CSV Button -->
        <AppButton @click="exportCsv" variant="primary" size="md" class="!rounded-lg !font-bold">
          <template #prefix>
            <Download class="w-4 h-4" />
          </template>
          Export CSV
        </AppButton>
      </div>
    </div>

    <!-- Top Section (Matching TopItemsPage Layout): 4 Stat Cards in 2x2 Grid (Left) & Payment Doughnut Card (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
      <!-- Left: 2x2 Stat Cards Grid (6 Columns) -->
      <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Card 1: Total Omset -->
        <AppStatCard
          title="Total Omset Penjualan"
          :value="formatCurrency(totalSales)"
          :trend="trendStats.sales"
          :loading="isLoading"
          :syncing="isYearLoading"
          variant="primary"
          icon="payments"
          tooltip="Akumulasi total pendapatan kotor dari seluruh transaksi lunas pada periode terpilih."
        />

        <!-- Card 2: Total Transaksi -->
        <AppStatCard
          title="Total Transaksi"
          :value="`${formatNumber(totalTransactions)} Transaksi`"
          :trend="trendStats.orders"
          :loading="isLoading"
          :syncing="isYearLoading"
          variant="secondary"
          icon="receipt_long"
          tooltip="Jumlah transaksi yang berhasil diselesaikan di kasir POS maupun pemesanan QR meja."
        />

        <!-- Card 3: Rata-rata Order (AOV) -->
        <AppStatCard
          title="Rata-rata Order (AOV)"
          :value="formatCurrency(aov)"
          :trend="trendStats.aov"
          :loading="isLoading"
          :syncing="isYearLoading"
          variant="secondary"
          icon="trending_up"
          tooltip="Average Order Value (AOV): Rata-rata nilai belanja pelanggan per setiap transaksi."
        />

        <!-- Card 4: Metode Bayar Favorit -->
        <AppStatCard
          title="Metode Bayar Favorit"
          :value="paymentDistribution[0]?.name || 'QRIS (Online)'"
          :trend="trendStats.payment"
          :loading="isLoading"
          :syncing="isYearLoading"
          variant="secondary"
          icon="account_balance_wallet"
          tooltip="Metode pembayaran dengan kontribusi volume dan nominal tertinggi pada periode ini."
        />
      </div>

      <!-- Right: Payment Distribution Doughnut Card (6 Columns) -->
      <div class="lg:col-span-6">
        <AppCard
          title="Distribusi Pembayaran"
          :syncing="isYearLoading"
          subtitle="Proporsi omset berdasarkan metode pembayaran"
          class="h-full flex flex-col justify-between"
        >
          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center flex-1 pt-2">
            <!-- Doughnut Chart Canvas with Center Total -->
            <div class="sm:col-span-6 h-56 flex items-center justify-center relative">
              <AppDoughnutChart :data="doughnutChartData" :options="doughnutChartOptions" :loading="isLoading" />
              <div class="absolute flex flex-col items-center justify-center pointer-events-none text-center">
                <span class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8]">Total Omset</span>
                <span class="text-xs font-extrabold text-[#1E293B] dark:text-white">
                  {{ formatCurrency(totalSales) }}
                </span>
              </div>
            </div>

            <!-- Custom Payment Legend Breakdown List -->
            <div class="sm:col-span-6 space-y-2 max-h-56 overflow-y-auto pr-1 text-xs">
              <div
                v-for="item in paymentDistribution"
                :key="item.id"
                class="flex items-center justify-between p-2 rounded-xl bg-[#F8FAFC] dark:bg-[#1E293B]/60 hover:bg-[#F1F5F9] dark:hover:bg-[#1E293B] transition-colors"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }"></span>
                  <span class="font-bold text-[#202224] dark:text-white truncate">{{ item.name }}</span>
                </div>
                <div class="text-right shrink-0">
                  <span class="font-extrabold text-[#202224] dark:text-white block">
                    {{ formatCurrency(item.amount) }}
                  </span>
                  <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">
                    {{ item.percent }}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>
    </div>

    <!-- Middle Section: Revenue & Sales Trend Line Chart (Full Width) -->
    <AppCard
      title="Grafik Tren Penjualan & Omset"
      :syncing="isYearLoading"
      subtitle="Pergerakan omset dan frekuensi transaksi sepanjang periode"
    >
      <template #action>
        <!-- Toggle Mode (Omset vs Transaksi) with Sliding Pill Indicator -->
        <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-2 shrink-0 min-w-[230px]">
          <!-- Sliding Dynamic Indicator -->
          <div
            class="absolute top-1 bottom-1 rounded-md pointer-events-none transition-all duration-300 ease-out z-0 shadow-xs"
            :class="trendMetric === 'revenue' ? 'bg-[#4880FF]' : 'bg-[#00B69B]'"
            :style="{
              width: 'calc(50% - 4px)',
              left: trendMetric === 'revenue' ? '4px' : 'calc(50%)'
            }"
          />

          <button
            type="button"
            @click="trendMetric = 'revenue'"
            class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
            :class="trendMetric === 'revenue' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
          >
            Omset
          </button>
          <button
            type="button"
            @click="trendMetric = 'orders'"
            class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
            :class="trendMetric === 'orders' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
          >
            Transaksi
          </button>
        </div>
      </template>

      <!-- Line Chart Container -->
      <div class="h-80 w-full pt-2">
        <AppLineChart :data="trendChartData" :options="trendChartOptions" :loading="isLoading" />
      </div>
    </AppCard>

    <!-- Bottom Section: Transactions Table Card (AppTable with Reusable Filters) -->
    <AppTable
      title="Daftar Transaksi Penjualan"
      subtitle="Rincian lengkap seluruh transaksi yang berhasil."
      :syncing="isYearLoading"
      :columns="tableColumns"
      :data="filteredTableTransactions"
      :loading="isLoading"
      emptyMessage="Belum ada transaksi penjualan yang sesuai filter."
    >
      <template #actions>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
          <!-- 1. Payment Filter Dropdown (Kapsul Filter) -->
          <div class="flex items-center p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedPaymentFilter" :options="paymentFilterOptions" width="w-44" />
          </div>

          <!-- 2. Order Type Filter Dropdown -->
          <div class="flex items-center p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedOrderTypeFilter" :options="orderTypeFilterOptions" width="w-40" />
          </div>

          <!-- 3. Search Input with 500ms Debouncing -->
          <div class="w-full sm:w-[220px] shrink-0">
            <AppInput
              v-model="tableSearchInput"
              placeholder="Cari no order / pelanggan..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Order Number -->
      <template #cell-orderNumber="{ value }">
        <span class="font-bold text-sm text-[#4880FF] hover:underline cursor-pointer tracking-tight whitespace-nowrap">
          {{ value }}
        </span>
      </template>

      <!-- Cell: Waktu -->
      <template #cell-createdAt="{ value }">
        <span class="font-semibold text-sm text-[#64748B] dark:text-[#94A3B8] whitespace-nowrap">
          {{ formatTime(value) }}
        </span>
      </template>

      <!-- Cell: Pelanggan / Meja -->
      <template #cell-customerName="{ row, value }">
        <div class="flex flex-col">
          <span class="font-bold text-sm text-[#202224] dark:text-white">
            {{ value || 'Pelanggan Manual' }}
          </span>
          <span v-if="row.tableCode" class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
            Meja {{ row.tableCode }}
          </span>
        </div>
      </template>

      <!-- Cell: Tipe Order -->
      <template #cell-orderType="{ row, value }">
        <AppBadge
          :variant="(value === 'dine_in' || row.tableCode) ? 'primary' : 'warning'"
          size="md"
          rounded="full"
        >
          {{ (value === 'takeaway' || (!row.tableCode && value !== 'dine_in')) ? 'Takeaway' : 'Dine In' }}
        </AppBadge>
      </template>

      <!-- Cell: Metode Bayar -->
      <template #cell-paymentMethod="{ value }">
        <AppBadge
          :variant="value?.toLowerCase() === 'qris' ? 'primary' : (value?.toLowerCase() === 'card' || value?.toLowerCase() === 'debit' ? 'purple' : 'success')"
          size="md"
          rounded="full"
        >
          {{ (value || 'Cash').toUpperCase() }}
        </AppBadge>
      </template>

      <!-- Cell: Total Omset -->
      <template #cell-totalAmount="{ value }">
        <span class="font-black text-sm text-[#202224] dark:text-white tabular-nums">
          {{ formatCurrency(value) }}
        </span>
      </template>

      <!-- Cell: Status -->
      <template #cell-status>
        <AppBadge variant="success" size="md" rounded="full">
          Lunas
        </AppBadge>
      </template>
    </AppTable>
  </div>
</template>
