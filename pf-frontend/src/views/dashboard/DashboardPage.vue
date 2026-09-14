<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import apiClient from '@/services/api'
import type { ChartData, ChartOptions } from 'chart.js'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppLineChart from '@/components/ui/AppLineChart.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
const DEFAULT_MENU_IMAGE = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=100&auto=format&fit=crop&q=80'
import { useFormat } from '@/composables/useFormat'
import { useAnimatedNumber } from '@/composables/useAnimatedNumber'
import { usePosStore } from '@/stores/pos'
import { onBeforeUnmount } from 'vue'

const { formatCurrency } = useFormat()

// Formatted Date (example: Monday, 24 December 2026)
const formattedCurrentDate = computed(() => {
  const now = new Date()
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(now)
})
const posStore = usePosStore()

const isLoading = ref(true)
const selectedPeriod = ref<'today' | 'week' | 'month'>('today')

const periodOptions = [
  { value: 'today', label: 'Hari Ini' },
  { value: 'week', label: '7 Hari Terakhir' },
  { value: 'month', label: 'Bulan Ini' },
]

const outletInfo = ref({
  name: 'Cabang Senopati Utama',
  tenant: 'Kopi Kenangan Senopati',
})

// Stats reactive state (Bersih 0 tanpa dummy mockup)
const statsData = ref({
  total_customers: 0,
  total_orders: 0,
  total_sales: 0,
  total_pending: 0,
})

// Trend Dinamis (% Naik/Turun vs Periode Sebelumnya)
const trendsData = ref({
  customers: { value: '0.0%', isPositive: true, label: 'vs kemarin' },
  orders: { value: '0.0%', isPositive: true, label: 'vs kemarin' },
  sales: { value: '0.0%', isPositive: true, label: 'vs kemarin' },
  pending: { value: '0 pesanan', isPositive: true, label: 'Menunggu dapur' },
})

// Deals list from backend
interface DealItem {
  id: string | number
  order_number: string
  product_name: string
  location: string
  date_time: string
  piece: number
  amount: number
  status: 'Delivered' | 'Pending' | 'Rejected'
  avatar: string
}

const dealsList = ref<DealItem[]>([])

// 5 Items Per Page Pagination State
const currentPage = ref(1)
const pageSize = 5

const totalPages = computed(() => Math.ceil(dealsList.value.length / pageSize) || 1)

const paginatedDeals = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return dealsList.value.slice(start, start + pageSize)
})

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const total = totalPages.value
  const cur = currentPage.value

  if (total <= 5) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (cur > 3) pages.push('...')
    const start = Math.max(2, cur - 1)
    const end = Math.min(total - 1, cur + 1)
    for (let i = start; i <= end; i++) pages.push(i)
    if (cur < total - 2) pages.push('...')
    pages.push(total)
  }
  return pages
})

const goToPage = (page: number | string) => {
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Chart raw series
const rawLabels = ref<string[]>(['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'])
const rawValues = ref<number[]>([0, 0, 0, 0, 0, 0, 0, 0])

// Animasi Count-Up Halus Native untuk Nilai Angka Stat Cards
const animatedCustomers = useAnimatedNumber(() => statsData.value.total_customers, 800)
const animatedOrders = useAnimatedNumber(() => statsData.value.total_orders, 800)
const animatedSales = useAnimatedNumber(() => statsData.value.total_sales, 950)
const animatedPending = useAnimatedNumber(() => statsData.value.total_pending, 600)

// 4 Stat Cards List dengan Nilai Animasi Counter dan Persentase Tren Realtime
const statsCards = computed(() => [
  {
    title: 'Total Pelanggan',
    value: animatedCustomers.value.toLocaleString('id-ID'),
    icon: 'group',
    variant: 'primary' as const,
    trend: trendsData.value.customers,
  },
  {
    title: 'Total Pesanan',
    value: animatedOrders.value.toLocaleString('id-ID'),
    icon: 'inventory_2',
    variant: 'secondary' as const,
    trend: trendsData.value.orders,
  },
  {
    title: 'Total Penjualan',
    value: formatCurrency(animatedSales.value),
    icon: 'payments',
    variant: 'secondary' as const,
    trend: trendsData.value.sales,
  },
  {
    title: 'Antrean Pending',
    value: animatedPending.value.toLocaleString('id-ID'),
    icon: 'history',
    variant: 'secondary' as const,
    trend: trendsData.value.pending,
  },
])

// vue-chartjs ChartData Definition (Clean DashStack Palette)
const chartData = computed<ChartData<'line'>>(() => ({
  labels: rawLabels.value,
  datasets: [
    {
      label: 'Penjualan',
      data: rawValues.value,
      borderColor: '#4880FF',
      borderWidth: 2.5,
      backgroundColor: (context: any) => {
        const chart = context.chart
        const { ctx, chartArea } = chart
        if (!chartArea) return 'rgba(72, 128, 255, 0.15)'
        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
        gradient.addColorStop(0, 'rgba(72, 128, 255, 0.28)')
        gradient.addColorStop(1, 'rgba(72, 128, 255, 0.0)')
        return gradient
      },
      fill: true,
      tension: 0.35,
      pointRadius: 4,
      pointHoverRadius: 7,
      pointBackgroundColor: '#4880FF',
      pointBorderColor: '#FFFFFF',
      pointBorderWidth: 2,
    },
  ],
}))

// vue-chartjs Options Definition (Clean & Interactive Tooltips)
const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
    mode: 'index',
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      backgroundColor: '#1E293B',
      titleColor: '#FFFFFF',
      bodyColor: '#FFFFFF',
      titleFont: { family: 'Roboto Flex', size: 12, weight: 'bold' },
      bodyFont: { family: 'Roboto Flex', size: 13, weight: 'bold' },
      padding: 10,
      cornerRadius: 8,
      displayColors: false,
      callbacks: {
        title: (items) => `Waktu: ${items[0]?.label || ''}`,
        label: (context) => `Omset: ${formatCurrency(Number(context.raw) || 0)}`,
      },
    },
  },
  scales: {
    x: {
      grid: {
        display: false,
      },
      ticks: {
        font: { family: 'Roboto Flex', size: 11, weight: 600 },
        color: '#94A3B8',
      },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(207, 207, 207, 0.25)',
      },
      ticks: {
        font: { family: 'Roboto Flex', size: 11, weight: 600 },
        color: '#94A3B8',
        callback: (val) => {
          const num = Number(val)
          if (num >= 1000000) return `Rp ${(num / 1000000).toFixed(1)}jt`
          if (num >= 1000) return `Rp ${Math.round(num / 1000)}rb`
          return `Rp ${num}`
        },
      },
    },
  },
}))

// Thumbnail fallback helper
const handleImageError = (e: Event) => {
  const target = e.target as HTMLImageElement
  if (target) {
    target.src = DEFAULT_MENU_IMAGE
  }
}

// Filter Pesanan Berdasarkan Periode Waktu Terpilih (Hari Ini / 7 Hari / Bulan Ini)
const getPeriodFilteredOrders = (orders: any[]) => {
  const now = new Date()
  const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

  return orders.filter(o => {
    if (!o.createdAt) return false
    const d = new Date(o.createdAt)
    const orderDateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

    if (selectedPeriod.value === 'today') {
      return orderDateStr === todayStr
    } else if (selectedPeriod.value === 'week') {
      const diffTime = now.getTime() - d.getTime()
      return diffTime >= 0 && diffTime <= 7 * 24 * 60 * 60 * 1000
    } else if (selectedPeriod.value === 'month') {
      return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()
    }
    return true
  })
}

// Hitung Tren & Statistik Realtime dari posStore (Reaktif Instan)
const calculateLocalStats = () => {
  if (!posStore.orders || posStore.orders.length === 0) return

  const now = new Date()
  let labelPeriod = 'kemarin'

  const currentOrders = getPeriodFilteredOrders(posStore.orders)
  const currentPaid = currentOrders.filter(o =>
    (o.paymentStatus === 'paid' || o.status === 'completed') &&
    !['cancelled', 'voided', 'refunded'].includes(o.status)
  )

  // Orders periode sebelumnya untuk komparasi tren %
  let prevOrders: typeof posStore.orders = []
  if (selectedPeriod.value === 'today') {
    labelPeriod = 'kemarin'
    const yesterday = new Date(now)
    yesterday.setDate(now.getDate() - 1)
    const yStr = `${yesterday.getFullYear()}-${String(yesterday.getMonth() + 1).padStart(2, '0')}-${String(yesterday.getDate()).padStart(2, '0')}`
    prevOrders = posStore.orders.filter(o => {
      if (!o.createdAt) return false
      const d = new Date(o.createdAt)
      const dStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
      return dStr === yStr
    })
  } else if (selectedPeriod.value === 'week') {
    labelPeriod = 'minggu lalu'
    const endPrev = now.getTime() - 7 * 24 * 60 * 60 * 1000
    const startPrev = now.getTime() - 14 * 24 * 60 * 60 * 1000
    prevOrders = posStore.orders.filter(o => {
      if (!o.createdAt) return false
      const t = new Date(o.createdAt).getTime()
      return t >= startPrev && t < endPrev
    })
  } else {
    labelPeriod = 'bulan lalu'
    const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    prevOrders = posStore.orders.filter(o => {
      if (!o.createdAt) return false
      const d = new Date(o.createdAt)
      return d.getFullYear() === prevMonthDate.getFullYear() && d.getMonth() === prevMonthDate.getMonth()
    })
  }

  const prevPaid = prevOrders.filter(o =>
    (o.paymentStatus === 'paid' || o.status === 'completed') &&
    !['cancelled', 'voided', 'refunded'].includes(o.status)
  )

  // Antrean aktif (pesanan menunggu bayar atau sedang diproses dapur)
  const pendingOrders = posStore.orders.filter(o =>
    ['pending_payment', 'awaiting_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'].includes(o.status)
  )

  const curRev = currentPaid.reduce((s, o) => s + (o.totalAmount || 0), 0)
  const prevRev = prevPaid.reduce((s, o) => s + (o.totalAmount || 0), 0)

  const curOrd = currentPaid.length
  const prevOrd = prevPaid.length

  const curCust = new Set(currentPaid.map(o => o.customerName || 'Order Manual')).size
  const prevCust = new Set(prevPaid.map(o => o.customerName || 'Order Manual')).size

  const formatTrend = (curr: number, prev: number, label: string) => {
    if (prev === 0) {
      if (curr === 0) return { value: '0.0%', isPositive: true, label: `Stabil vs ${label}` }
      return { value: '+100%', isPositive: true, label: `Naik dari ${label}` }
    }
    const pct = Math.round(((curr - prev) / prev) * 1000) / 10
    const isPos = pct >= 0
    return {
      value: `${isPos ? '+' : ''}${pct.toFixed(1)}%`,
      isPositive: isPos,
      label: `${isPos ? 'Naik dari' : 'Turun dari'} ${label}`,
    }
  }

  statsData.value = {
    total_customers: curCust,
    total_orders: curOrd,
    total_sales: curRev,
    total_pending: pendingOrders.length,
  }

  trendsData.value = {
    customers: formatTrend(curCust, prevCust, labelPeriod),
    orders: formatTrend(curOrd, prevOrd, labelPeriod),
    sales: formatTrend(curRev, prevRev, labelPeriod),
    pending: {
      value: `${pendingOrders.length} pesanan`,
      isPositive: pendingOrders.length <= 5,
      label: 'Menunggu dapur',
    },
  }

  updateChartFromOrders()
}

// Hitung Grafik Live Realtime dari Data Pesanan Kasir & Online Sesuai Periode
const updateChartFromOrders = () => {
  const periodOrders = getPeriodFilteredOrders(posStore.orders)
  const paid = periodOrders.filter(o =>
    (o.paymentStatus === 'paid' || o.status === 'completed') &&
    !['cancelled', 'voided', 'refunded'].includes(o.status)
  )

  if (selectedPeriod.value === 'today') {
    const hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00']
    const map: Record<string, number> = {}
    hours.forEach(h => { map[h] = 0 })

    paid.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const h = d.getHours()
      const bucketHour = Math.min(22, Math.max(8, Math.floor(h / 2) * 2))
      const key = `${String(bucketHour).padStart(2, '0')}:00`
      if (map[key] !== undefined) {
        map[key] += (o.totalAmount || 0)
      }
    })
    rawLabels.value = hours
    rawValues.value = hours.map(h => map[h])
  } else if (selectedPeriod.value === 'week') {
    const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']
    const labels: string[] = []
    const values: number[] = []
    const now = new Date()
    for (let i = 6; i >= 0; i--) {
      const targetDate = new Date()
      targetDate.setDate(now.getDate() - i)
      labels.push(dayNames[targetDate.getDay()])
      const dayStart = new Date(targetDate.getFullYear(), targetDate.getMonth(), targetDate.getDate(), 0, 0, 0).getTime()
      const dayEnd = new Date(targetDate.getFullYear(), targetDate.getMonth(), targetDate.getDate(), 23, 59, 59).getTime()
      const daySum = paid
        .filter(o => {
          const t = o.createdAt ? new Date(o.createdAt).getTime() : 0
          return t >= dayStart && t <= dayEnd
        })
        .reduce((s, o) => s + (o.totalAmount || 0), 0)
      values.push(daySum)
    }
    rawLabels.value = labels
    rawValues.value = values
  } else {
    // Bulan Ini (Interval 5 harian)
    const labels: string[] = ['1-5', '6-10', '11-15', '16-20', '21-25', '26-31']
    const values = [0, 0, 0, 0, 0, 0]
    paid.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      const day = d.getDate()
      const idx = Math.min(5, Math.floor((day - 1) / 5))
      values[idx] += (o.totalAmount || 0)
    })
    rawLabels.value = labels
    rawValues.value = values
  }
}

// Fetch Dynamic Realtime Data from pf-backend API
const fetchDashboardData = async () => {
  // Hitung instan secara lokal terlebih dahulu
  calculateLocalStats()

  try {
    const res = await apiClient.get(`/dashboard/overview?period=${selectedPeriod.value}`, {
      timeout: 4000
    })

    if (res.data) {
      if (res.data.outlet) {
        outletInfo.value = res.data.outlet
        if (res.data.outlet.id) {
          posStore.initRealtime(res.data.outlet.id)
        }
      }
      if (res.data.stats) {
        statsData.value = {
          total_customers: res.data.stats.total_customers,
          total_orders: res.data.stats.total_orders,
          total_sales: res.data.stats.total_sales,
          total_pending: res.data.stats.total_pending,
        }
        if (res.data.stats.trends) {
          trendsData.value = res.data.stats.trends
        }
      }
      if (res.data.chart && res.data.chart.values && res.data.chart.values.some((v: number) => v > 0)) {
        rawLabels.value = res.data.chart.labels || []
        rawValues.value = res.data.chart.values || []
      } else {
        updateChartFromOrders()
      }
      if (res.data.deals && Array.isArray(res.data.deals) && res.data.deals.length > 0) {
        dealsList.value = res.data.deals.map((item: any, i: number) => ({
          ...item,
          avatar: DEFAULT_MENU_IMAGE,
        }))
      }
    }
  } catch (err) {
    console.warn('Backend overview endpoint fallback to posStore:', err)
  } finally {
    // If dealsList is still empty, populate from posStore.orders
    if (dealsList.value.length === 0 && posStore.orders.length > 0) {
      dealsList.value = posStore.orders.map((o, idx) => {
        const firstItem = o.items?.[0]
        const itemCount = o.items?.length || 1
        let title = (firstItem as any)?.menuItemName || (firstItem as any)?.name || 'Pesanan Resto'
        if (itemCount > 1) {
          title += ` (+${itemCount - 1} item)`
        }
        let dealStatus: 'Delivered' | 'Pending' | 'Rejected' = 'Delivered'
        if (['pending_payment', 'awaiting_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'].includes(o.status)) {
          dealStatus = 'Pending'
        } else if (['cancelled', 'voided', 'refunded'].includes(o.status)) {
          dealStatus = 'Rejected'
        }
        const pieceCount = o.items ? o.items.reduce((sum: number, it: any) => sum + (it.quantity || 1), 0) : 1
        const d = o.createdAt ? new Date(o.createdAt) : new Date()
        const dateFormatted = `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()} - ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`
        return {
          id: o.id || idx,
          order_number: o.orderNumber || `ORD-${idx}`,
          product_name: title,
          location: o.tableCode ? `Meja ${o.tableCode} (Dine In)` : 'Takeaway',
          date_time: dateFormatted,
          piece: pieceCount,
          amount: o.totalAmount || 0,
          status: dealStatus,
          avatar: DEFAULT_MENU_IMAGE
        }
      })
    }
    isLoading.value = false
  }
}

const handleRealtimeSync = async () => {
  await posStore.fetchOrders()
  calculateLocalStats()
  await fetchDashboardData()
}

// Reaktivitas Instan saat pesanan berubah di posStore
watch(() => posStore.orders, () => {
  calculateLocalStats()
}, { deep: true })

let pollInterval: any = null

onMounted(async () => {
  posStore.initRealtime()
  await posStore.fetchOrders()
  calculateLocalStats()
  await fetchDashboardData()
  window.addEventListener('kds:refresh', handleRealtimeSync)

  // Interval polling backup setiap 10 detik agar tetap terupdate jika koneksi WS idle
  pollInterval = setInterval(async () => {
    await posStore.fetchOrders()
    await fetchDashboardData()
  }, 10000)
})

onBeforeUnmount(() => {
  window.removeEventListener('kds:refresh', handleRealtimeSync)
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
})

watch(selectedPeriod, () => {
  calculateLocalStats()
  fetchDashboardData()
  currentPage.value = 1
})


const getBadgeVariant = (status: string) => {
  switch (status.toLowerCase()) {
    case 'delivered':
    case 'completed':
    case 'selesai':
    case 'lunas':
      return 'completed'
    case 'processing':
    case 'diproses':
    case 'dimasak':
      return 'processing'
    case 'in transit':
    case 'transit':
      return 'in_transit'
    case 'pending':
    case 'on hold':
    case 'menunggu':
      return 'on_hold'
    case 'rejected':
    case 'cancelled':
    case 'batal':
      return 'rejected'
    default:
      return 'primary'
  }
}

const getStatusBadgeClass = (status: string) => {
  switch (status) {
    case 'Delivered':
    case 'Completed':
      return 'bg-[#00B69B] text-white'
    case 'Pending':
      return 'bg-[#FCBE2D] text-white'
    case 'Rejected':
    case 'Cancelled':
      return 'bg-[#FD5454] text-white'
    default:
      return 'bg-[#64748B] text-white'
  }
}

</script>

<template>
  <div class="space-y-7 pb-10">
    <!-- Page Title & Formatted Date -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">Dashboard</h1>
        <p class="text-xs sm:text-sm text-[#606060] dark:text-[#A6A6A6] font-medium mt-0.5">
          {{ formattedCurrentDate }}
        </p>
      </div>
    </div>

    <!-- 4 Stat Cards Row (Figma DashStack 100% Dynamic) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <AppStatCard v-for="stat in statsCards" :key="stat.title" :title="stat.title" :value="stat.value"
        :icon="stat.icon" :variant="stat.variant" :trend="stat.trend" :loading="isLoading" />
    </div>

    <!-- Sales Details Chart Card (Clean & Modern vue-chartjs) -->
    <div
      class="bg-white dark:bg-[#273142] rounded-[14px] p-6 md:p-8 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] space-y-6">
      <!-- Card Header: Title on Left, Synchronized Dropdown Filter on Right -->
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-[#202224] dark:text-white leading-tight">
          Sales Details
        </h2>

        <!-- Dropdown Filter (Style Riwayat Order dengan background putih seperti awal) -->
        <AppFilterDropdown
          v-model="selectedPeriod"
          :options="periodOptions"
          variant="white"
          width="w-44"
          align="right"
        />
      </div>

      <!-- vue-chartjs Canvas Container -->
      <div class="relative w-full h-[320px]">
        <AppLineChart :data="chartData" :options="chartOptions" :loading="isLoading" />
      </div>
    </div>

    <!-- Deals Details Table Card (5 Items Per Page with Pagination) -->
    <div
      class="bg-white dark:bg-[#273142] rounded-[14px] p-6 md:p-8 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] space-y-6">
      <!-- Card Header -->
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-[#202224] dark:text-white leading-tight">
          Deals Details
        </h2>
      </div>

      <!-- Table (Exactly 5 Items Per Page) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <!-- Table Header with DashStack #F1F4F9 Background (Text Normal Base) -->
          <thead>
            <tr class="bg-[#F1F4F9] dark:bg-[#323D4E] rounded-xl text-sm font-bold text-[#202224] dark:text-white">
              <th class="py-3.5 px-4 rounded-l-xl text-center w-14">No</th>
              <th class="py-3.5 px-4">Product Name</th>
              <th class="py-3.5 px-4">Location</th>
              <th class="py-3.5 px-4">Date - Time</th>
              <th class="py-3.5 px-4 text-center">Piece</th>
              <th class="py-3.5 px-4 text-right">Amount</th>
              <th class="py-3.5 px-5 text-center rounded-r-xl">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E8E8E8] dark:divide-[#313D4F] text-sm">
            <tr v-for="(item, idx) in paginatedDeals" :key="item.id"
              class="hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/30 transition-colors">
              <!-- Nomor Urut (Sub text sm) -->
              <td class="py-4 px-4 text-center text-sm font-bold text-[#64748B] dark:text-[#94A3B8] tabular-nums">
                {{ (currentPage - 1) * pageSize + idx + 1 }}
              </td>

              <!-- Product Name (Text Normal Base) with Order Number (Sub text sm) -->
              <td class="py-4 px-4 font-semibold text-[#202224] dark:text-white">
                <div class="flex items-center gap-3.5">
                  <img :src="item.avatar || DEFAULT_MENU_IMAGE" :alt="item.product_name"
                    @error="handleImageError" class="w-11 h-11 rounded-xl object-cover bg-[#D8D8D8] shrink-0" />
                  <div class="flex flex-col">
                    <span class="font-bold text-sm text-[#202224] dark:text-white leading-snug">{{ item.product_name }}</span>
                    <span class="text-sm font-mono text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ item.order_number }}</span>
                  </div>
                </div>
              </td>

              <!-- Location (Text Normal Base) -->
              <td class="py-4 px-4 font-medium text-sm text-[#475569] dark:text-[#E2E8F0]">
                {{ item.location }}
              </td>

              <!-- Date - Time (Text Normal Base) -->
              <td class="py-4 px-4 font-medium text-sm text-[#475569] dark:text-[#E2E8F0] tabular-nums">
                {{ item.date_time }}
              </td>

              <!-- Piece (Text Normal Base) -->
              <td class="py-4 px-4 font-semibold text-sm text-[#202224] dark:text-white text-center tabular-nums">
                {{ item.piece }}
              </td>

              <!-- Amount (Text Normal Base) -->
              <td class="py-4 px-4 font-bold text-sm text-[#202224] dark:text-white tabular-nums text-right">
                {{ formatCurrency(item.amount) }}
              </td>

              <!-- Status Pill (Vibrant solid green/yellow/red) -->
              <td class="py-4 px-5 text-center">
                <AppBadge :variant="getBadgeVariant(item.status)" rounded="full" size="md">
                  {{ item.status }}
                </AppBadge>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-if="paginatedDeals.length === 0">
              <td colspan="7" class="py-8 text-center text-sm font-semibold text-[#64748B] dark:text-[#94A3B8]">
                Belum ada transaksi pada periode ini
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer matching DashStack -->
      <div v-if="dealsList.length > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2 border-t border-[#F1F4F9] dark:border-[#313D4F]/50">
        <!-- Result count (Sub text sm) -->
        <p class="text-sm font-medium text-[#64748B] dark:text-[#94A3B8]">
          Menampilkan <span class="font-bold text-[#202224] dark:text-white">{{ (currentPage - 1) * pageSize + 1 }}-{{
            Math.min(currentPage * pageSize, dealsList.length) }}</span> dari <span
            class="font-bold text-[#202224] dark:text-white">{{ dealsList.length }}</span> data
        </p>

        <!-- Pagination Page Buttons (Sub text sm) -->
        <div class="flex items-center gap-2">
          <!-- Previous Button -->
          <button type="button" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" :class="[
            'h-9 w-9 rounded-lg flex items-center justify-center border text-sm font-bold transition-colors cursor-pointer',
            currentPage === 1
              ? 'border-[#E2E8F0] dark:border-[#313D4F] text-[#CBD5E1] dark:text-[#475569] cursor-not-allowed opacity-50'
              : 'border-[#E2E8F0] dark:border-[#313D4F] text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] hover:text-[#4880FF]'
          ]" title="Halaman Sebelumnya">
            <AppIcon name="chevron_left" :size="18" />
          </button>

          <!-- Numbered Page Buttons -->
          <template v-for="(p, idx) in visiblePages" :key="idx">
            <span v-if="p === '...'" class="h-9 px-2 flex items-center justify-center text-sm font-bold text-[#94A3B8]">
              ...
            </span>
            <button v-else type="button" @click="goToPage(p)" :class="[
              'h-9 min-w-[36px] px-3 rounded-lg text-sm font-bold transition-all cursor-pointer flex items-center justify-center',
              currentPage === p
                ? 'bg-[#4880FF] text-white shadow-sm font-black'
                : 'border border-[#E2E8F0] dark:border-[#313D4F] text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] hover:text-[#4880FF]'
            ]">
              {{ p }}
            </button>
          </template>

          <!-- Next Button -->
          <button type="button" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" :class="[
            'h-9 w-9 rounded-lg flex items-center justify-center border text-sm font-bold transition-colors cursor-pointer',
            currentPage === totalPages
              ? 'border-[#E2E8F0] dark:border-[#313D4F] text-[#CBD5E1] dark:text-[#475569] cursor-not-allowed opacity-50'
              : 'border-[#E2E8F0] dark:border-[#313D4F] text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] hover:text-[#4880FF]'
          ]" title="Halaman Berikutnya">
            <AppIcon name="chevron_right" :size="18" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
