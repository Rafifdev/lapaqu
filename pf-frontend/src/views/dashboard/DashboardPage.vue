<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import apiClient from '@/services/api'
import type { ChartData, ChartOptions } from 'chart.js'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { Download } from 'lucide-vue-next'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppLineChart from '@/components/ui/AppLineChart.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppTable, { type TableColumn } from '@/components/ui/AppTable.vue'
import AppTableFilterBar, { type FilterBarItem } from '@/components/ui/AppTableFilterBar.vue'
import AppSearchInput from '@/components/ui/AppSearchInput.vue'
const DEFAULT_MENU_IMAGE = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=100&auto=format&fit=crop&q=80'
import { useFormat } from '@/composables/useFormat'
import { usePosStore } from '@/stores/pos'
import { useDashboardI18n } from '@/i18n'
import { onBeforeUnmount } from 'vue'

const { formatCurrency } = useFormat()
const { t, translate, locale } = useDashboardI18n()

// Real server date directly from backend system / database
const serverDate = ref('')
const formattedCurrentDate = computed(() => {
  if (serverDate.value) return serverDate.value
  const now = new Date()
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(now)
})
const posStore = usePosStore()

const isLoading = ref(true)
const isYearLoading = ref(false)
const selectedPeriod = ref<'today' | 'week' | 'month' | 'year'>('today')

const periodOptions = computed(() => [
  { value: 'today', label: t('overview.today') },
  { value: 'week', label: t('overview.thisWeek') },
  { value: 'month', label: t('overview.thisMonth') },
  { value: 'year', label: t('overview.thisYear') },
])

const outletInfo = ref<{ name: string; tenant: string } | null>(null)

// Stats reactive state (Bersih 0 tanpa dummy mockup)
const statsData = ref({
  total_customers: 0,
  total_orders: 0,
  total_sales: 0,
  total_pending: 0,
})

// Trend Dinamis (% Naik/Turun vs Periode Sebelumnya)
const trendsData = ref({
  customers: { value: '0%', isPositive: false, isNeutral: true, label: 'vs kemarin', icon: 'remove' },
  orders: { value: '0%', isPositive: false, isNeutral: true, label: 'vs kemarin', icon: 'remove' },
  sales: { value: '0%', isPositive: false, isNeutral: true, label: 'vs kemarin', icon: 'remove' },
  pending: { value: '0 pesanan', isPositive: true, isNeutral: false, label: 'Dapur lancar', icon: 'check_circle' },
})

// Deals list from backend
interface DealItem {
  id: string | number
  order_number: string
  product_name: string
  location: string
  date_time: string
  raw_date?: string
  piece: number
  amount: number
  status: 'Delivered' | 'Pending' | 'Rejected'
  avatar: string
}

const dealsList = ref<DealItem[]>([])

// Kolom untuk AppTable Reusable Component
const dealsColumns = computed<TableColumn[]>(() => [
  { key: 'product_name', label: t('overview.table.customer'), width: '32%' },
  { key: 'location', label: t('overview.table.tableNo'), width: '16%' },
  { key: 'date_time', label: t('overview.table.time'), width: '18%' },
  { key: 'piece', label: 'Qty', align: 'center', width: '10%' },
  { key: 'amount', label: t('overview.table.total'), align: 'right', width: '12%' },
  { key: 'status', label: t('overview.table.status'), align: 'center', width: '12%' },
])

// Filter & Search State untuk Deals Details
const dealsStatusFilter = ref('all')
const dealsStatusOptions = computed(() => [
  { value: 'all', label: t('overview.table.allStatus') },
  { value: 'Delivered', label: t('overview.table.paid') },
  { value: 'Pending', label: t('overview.table.pending') },
  { value: 'Rejected', label: t('overview.table.cancelled') },
])

const dealsSortFilter = ref('asc')
const dealsSortOptions = [
  { value: 'asc', label: 'Sort: Ascending' },
  { value: 'desc', label: 'Sort: Descending' },
]

// Reusable Filter Bar Configuration (Bisa dioper langsung ke AppTableFilterBar)
const dealsFilters = computed<FilterBarItem[]>(() => [
  {
    key: 'status',
    options: dealsStatusOptions.value,
    width: 'w-44',
  },
  {
    key: 'sort',
    options: dealsSortOptions,
    width: 'w-52',
  },
])
const dealsFilterValues = ref<Record<string, string | number>>({
  status: 'all',
  sort: 'asc',
})

const dealsStartDate = ref('')
const dealsEndDate = ref('')
const dealsSearchQuery = ref('')

const filteredDeals = computed(() => {
  // Filter Deals berdasarkan dropdown selectedPeriod utama (Hari ini / 7 Hari / Bulan ini)
  let list = dealsList.value.filter(d => {
    const raw = d.raw_date || d.date_time
    if (!raw) return true
    const dt = new Date(raw)
    if (isNaN(dt.getTime())) return true

    const now = new Date()
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    const orderDateStr = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`

    if (selectedPeriod.value === 'today') {
      return orderDateStr === todayStr
    } else if (selectedPeriod.value === 'week') {
      const diffTime = now.getTime() - dt.getTime()
      return diffTime >= 0 && diffTime <= 7 * 24 * 60 * 60 * 1000
    } else if (selectedPeriod.value === 'month') {
      return dt.getFullYear() === now.getFullYear() && dt.getMonth() === now.getMonth()
    } else if (selectedPeriod.value === 'year') {
      return dt.getFullYear() === now.getFullYear()
    }
    return true
  })

  // 1. Status Filter (Sinkron baik dari dealsFilterValues.status maupun dealsStatusFilter)
  const currentStatus = (dealsFilterValues.value.status || dealsStatusFilter.value || 'all') as string
  if (currentStatus !== 'all') {
    list = list.filter(d => (d.status || '').toLowerCase() === currentStatus.toLowerCase())
  }

  // 2. Date Range Filter (Identik dengan TransactionHistoryPage)
  if (dealsStartDate.value || dealsEndDate.value) {
    list = list.filter(d => {
      const raw = d.raw_date || d.date_time
      if (!raw) return false
      const dt = new Date(raw)
      if (isNaN(dt.getTime())) return true
      const year = dt.getFullYear()
      const month = String(dt.getMonth() + 1).padStart(2, '0')
      const day = String(dt.getDate()).padStart(2, '0')
      const orderDate = `${year}-${month}-${day}`

      if (dealsStartDate.value && dealsEndDate.value) {
        return orderDate >= dealsStartDate.value && orderDate <= dealsEndDate.value
      } else if (dealsStartDate.value) {
        return orderDate >= dealsStartDate.value
      } else if (dealsEndDate.value) {
        return orderDate <= dealsEndDate.value
      }
      return true
    })
  }

  // 3. Search Query (Product Name, Order Number, Location, Amount)
  if (dealsSearchQuery.value.trim()) {
    const q = dealsSearchQuery.value.trim().toLowerCase()
    list = list.filter(d =>
      (d.product_name || '').toLowerCase().includes(q) ||
      (d.order_number || '').toLowerCase().includes(q) ||
      (d.location || '').toLowerCase().includes(q) ||
      String(d.amount || '').includes(q)
    )
  }

  // 4. Sort Filter (Identik dengan TransactionHistoryPage)
  const currentSort = (dealsFilterValues.value.sort || dealsSortFilter.value || 'asc') as string
  const sorted = [...list]
  sorted.sort((a, b) => {
    const timeA = a.raw_date ? new Date(a.raw_date).getTime() : 0
    const timeB = b.raw_date ? new Date(b.raw_date).getTime() : 0

    if (currentSort === 'asc') {
      return (a.order_number || '').localeCompare(b.order_number || '') || timeA - timeB
    }
    if (currentSort === 'desc') {
      return (b.order_number || '').localeCompare(a.order_number || '') || timeB - timeA
    }
    return 0
  })

  return sorted
})

// Chart raw series
const rawLabels = ref<string[]>(['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'])
const rawValues = ref<number[]>([0, 0, 0, 0, 0, 0, 0, 0])

// 4 Stat Cards List (Nilai angka & tren otomatis dianimasikan secara in-place oleh AppStatCard)
const statsCards = computed(() => [
  {
    title: 'Total Penjualan',
    value: formatCurrency(statsData.value.total_sales),
    icon: 'payments',
    variant: 'primary' as const,
    trend: trendsData.value.sales,
  },
  {
    title: 'Total Pesanan',
    value: statsData.value.total_orders.toLocaleString('id-ID'),
    icon: 'inventory_2',
    variant: 'secondary' as const,
    trend: trendsData.value.orders,
  },
  {
    title: 'Total Pelanggan',
    value: statsData.value.total_customers.toLocaleString('id-ID'),
    icon: 'group',
    variant: 'secondary' as const,
    trend: trendsData.value.customers,
  },
  {
    title: 'Antrean Pending',
    value: statsData.value.total_pending.toLocaleString('id-ID'),
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
  animation: {
    duration: 260,
    easing: 'easeOutQuart',
  },
  transitions: {
    active: {
      animation: {
        duration: 300,
        easing: 'easeOutQuart',
      },
    },
  },
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
    } else if (selectedPeriod.value === 'year') {
      return d.getFullYear() === now.getFullYear()
    }
    return true
  })
}

// Hitung Tren & Statistik Realtime dari posStore (Reaktif Instan)
const calculateLocalStats = () => {
  const orders = posStore.orders || []
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
  } else if (selectedPeriod.value === 'year') {
    labelPeriod = 'tahun lalu'
    const prevYear = now.getFullYear() - 1
    prevOrders = posStore.orders.filter(o => {
      if (!o.createdAt) return false
      const d = new Date(o.createdAt)
      return d.getFullYear() === prevYear
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
    // Jika data saat ini masih 0, tampilkan 0% warna abu-abu (jangan -100%)
    if (curr <= 0) {
      return {
        value: '0%',
        isPositive: false,
        isNeutral: true,
        label: `vs ${label}`,
        icon: 'remove',
      }
    }

    // Jika periode sebelumnya 0 dan saat ini ada data transaksi
    if (prev <= 0) {
      return {
        value: '+100%',
        isPositive: true,
        isNeutral: false,
        label: `Naik dari ${label}`,
        icon: 'arrow_upward',
      }
    }

    const diff = curr - prev
    const pct = Math.round((diff / prev) * 1000) / 10
    const isZero = pct === 0
    const isPos = pct > 0
    const formattedVal = Number.isInteger(pct) ? `${pct}%` : `${pct.toFixed(1)}%`

    if (isZero) {
      return {
        value: '0%',
        isPositive: false,
        isNeutral: true,
        label: `Stabil vs ${label}`,
        icon: 'remove',
      }
    }

    return {
      value: `${isPos ? '+' : ''}${formattedVal}`,
      isPositive: isPos,
      isNeutral: false,
      label: `${isPos ? 'Naik dari' : 'Turun dari'} ${label}`,
      icon: isPos ? 'arrow_upward' : 'arrow_downward',
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
      isNeutral: false,
      label: pendingOrders.length === 0 ? 'Dapur lancar' : 'Menunggu dapur',
      icon: pendingOrders.length === 0 ? 'check_circle' : 'schedule',
    },
  }

  updateChartFromOrders()
}

// Hitung Grafik Live Realtime dari Data Pesanan Kasir & Online Sesuai Periode
const updateChartFromOrders = () => {
  const now = new Date()
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
  } else if (selectedPeriod.value === 'year') {
    // Tahun Ini (12 Bulan)
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    const values = new Array(12).fill(0)
    paid.forEach(o => {
      const d = o.createdAt ? new Date(o.createdAt) : new Date()
      if (d.getFullYear() === now.getFullYear()) {
        values[d.getMonth()] += (o.totalAmount || 0)
      }
    })
    rawLabels.value = months
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

  try {
    const res = await apiClient.get(`/dashboard/overview?period=${selectedPeriod.value}`, {
      timeout: 4000
    })

    if (res.data) {
      if (res.data.date && res.data.date.formatted) {
        serverDate.value = res.data.date.formatted
      }
      if (res.data.outlet) {
        outletInfo.value = res.data.outlet
        if (res.data.outlet.id) {
          posStore.initRealtime(res.data.outlet.id)
        }
      }
      if (res.data.stats) {
        const s = res.data.stats
        if (
          statsData.value.total_customers !== s.total_customers ||
          statsData.value.total_orders !== s.total_orders ||
          statsData.value.total_sales !== s.total_sales ||
          statsData.value.total_pending !== s.total_pending
        ) {
          statsData.value = {
            total_customers: s.total_customers,
            total_orders: s.total_orders,
            total_sales: s.total_sales,
            total_pending: s.total_pending,
          }
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
        dealsList.value = res.data.deals.map((item: any) => ({
          ...item,
          raw_date: item.raw_date || item.created_at || '',
          avatar: item.avatar || DEFAULT_MENU_IMAGE,
        }))
      }
    }
  } catch (err) {
    console.warn('Backend overview endpoint fallback to posStore:', err)
    calculateLocalStats()
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
        const matchedMenuItem = posStore.menuItems.find(
          m => m.id === (firstItem as any)?.menuItemId || m.name === (firstItem as any)?.menuItemName || m.name === (firstItem as any)?.name
        )
        const itemAvatar = (firstItem as any)?.imageUrl || matchedMenuItem?.imageUrl || DEFAULT_MENU_IMAGE

        return {
          id: o.id || idx,
          order_number: o.orderNumber || `ORD-${idx}`,
          product_name: title,
          location: o.tableCode ? `Meja ${o.tableCode} (Dine In)` : 'Takeaway',
          date_time: dateFormatted,
          piece: pieceCount,
          amount: o.totalAmount || 0,
          status: dealStatus,
          avatar: itemAvatar
        }
      })
    }
    isLoading.value = false
  }
}

const handleRealtimeSync = async () => {
  await posStore.fetchOrders(true)
  await fetchDashboardData()
}

// Reaktivitas Instan saat pesanan berubah di posStore
watch(() => posStore.orders, () => {
  if (statsData.value.total_orders === 0 && posStore.orders.length > 0) {
    calculateLocalStats()
  }
}, { deep: true })

let pollInterval: any = null

onMounted(async () => {
  posStore.initRealtime()
  await Promise.allSettled([
    posStore.fetchOrders(false),
    fetchDashboardData(),
  ])
  window.addEventListener('kds:refresh', handleRealtimeSync)

  // Interval polling backup setiap 10 detik di background secara mulus
  pollInterval = setInterval(async () => {
    await posStore.fetchOrders(true)
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

watch(selectedPeriod, async (newVal) => {
  if (newVal === 'year') {
    isYearLoading.value = true
  }
  // 1. Eksekusi kalkulasi lokal langsung seketika (Instant 0ms Feedback)
  calculateLocalStats()
  updateChartFromOrders()

  // 2. Sinkronkan ke server secara background
  try {
    await fetchDashboardData()
  } finally {
    isYearLoading.value = false
  }
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


// {{ t('overview.exportCsv') }} Handler (Samakan dengan Laporan Penjualan & Omset)
const exportCsv = () => {
  const list = filteredDeals.value.length > 0 ? filteredDeals.value : dealsList.value
  if (list.length === 0) {
    alert('Belum ada data transaksi untuk diexport pada periode ini.')
    return
  }
  const headers = ['No', 'Order Number', 'Product / Item', 'Location', 'Date Time', 'Piece', 'Amount (IDR)', 'Status']
  const rows = list.map((d, idx) => [
    idx + 1,
    `"${d.order_number || '-'}"`,
    `"${(d.product_name || 'Pesanan Resto').replace(/"/g, '""')}"`,
    `"${d.location || '-'}"`,
    `"${d.date_time || '-'}"`,
    d.piece || 1,
    d.amount || 0,
    `"${d.status || 'Delivered'}"`
  ])
  const csvRows = [headers.join(','), ...rows.map(e => e.join(','))]
  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + csvRows.join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `dashboard-deals-${selectedPeriod.value}-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

</script>

<template>
  <div class="space-y-7 pb-10">
    <!-- Page Title, Formatted Date & Action Filters (Samakan dengan Laporan Penjualan & Omset) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div>
          <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">Dashboard</h1>
        </div>
        <p class="text-xs sm:text-sm text-[#64748B] dark:text-[#94A3B8] font-semibold mt-0.5">
          {{ formattedCurrentDate }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Period Filter Dropdown (White Card Style matching TopItems & Laporan Penjualan) -->
        <AppFilterDropdown
          v-model="selectedPeriod"
          :options="periodOptions"
          variant="white"
          width="w-44"
          align="right"
        />

        <!-- {{ t('overview.exportCsv') }} Button -->
        <AppButton @click="exportCsv" variant="primary" size="md" class="!rounded-lg !font-bold shadow-sm">
          <template #prefix>
            <Download class="w-4 h-4" />
          </template>
          {{ t('overview.exportCsv') }}
        </AppButton>
      </div>
    </div>

    <!-- 4 Stat Cards Row (Komponen card statis tidak re-render, animasi halus pada angka & stats bawah saja) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="stat in statsCards"
        :key="stat.title"
        class="h-full"
      >
        <AppStatCard
          :title="stat.title"
          :value="stat.value"
          :icon="stat.icon"
          :variant="stat.variant"
          :trend="stat.trend"
          :loading="isLoading"
          :syncing="isYearLoading"
        />
      </div>
    </div>

    <!-- Sales Details Chart Card (Clean & Modern vue-chartjs) -->
    <div
      class="bg-white dark:bg-[#273142] rounded-[14px] p-6 md:p-8 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] space-y-6">
      <!-- Card Header -->
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-[#202224] dark:text-white leading-tight">
          Sales Details
        </h2>
        <svg
          v-if="isYearLoading"
          class="animate-spin w-5 h-5 text-[#4880FF] shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <!-- vue-chartjs Canvas Container -->
      <div class="relative w-full h-[320px]">
        <AppLineChart :data="chartData" :options="chartOptions" :loading="isLoading" />
      </div>
    </div>

    <!-- Deals Details Table Card (Menggunakan Reusable Component AppTable) -->
    <AppTable
      :title="t('overview.charts.dealsDetails')"
      :columns="dealsColumns"
      :data="filteredDeals"
      :loading="isLoading"
      :pageSize="20"
      showNumbering
      numberingLabel="No"
      :scrollable="true"
      maxHeight="max-h-[440px]"
      minHeight="min-h-[360px]"
      :emptyTitle="t('overview.table.emptyTitle')"
      :emptyMessage="t('overview.table.emptyDesc')"
    >
      <!-- Sub-header: Filters (Status) di kiri, Search dan Range Date di kanan sejajar -->
      <template #header>
        <div class="space-y-4 w-full">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-[#202224] dark:text-white leading-tight">
              Deals Details
            </h2>
            <svg
              v-if="isYearLoading"
              class="animate-spin w-5 h-5 text-[#4880FF] shrink-0"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>

          <!-- Reusable Component Filter Bar (Varian identik dengan Riwayat Order) -->
          <AppTableFilterBar
            v-model:search="dealsSearchQuery"
            searchPlaceholder="Cari produk, order, meja..."
            showDateRange
            v-model:startDate="dealsStartDate"
            v-model:endDate="dealsEndDate"
            :filters="dealsFilters"
            v-model:filterValues="dealsFilterValues"
          />
        </div>
      </template>

      <!-- Cell: Product Name with Real Database Avatar & Order Number -->
      <template #cell-product_name="{ row }">
        <div class="flex items-center gap-3.5">
          <img
            :src="row.avatar || DEFAULT_MENU_IMAGE"
            :alt="row.product_name"
            @error="handleImageError"
            class="w-11 h-11 rounded-xl object-cover bg-[#D8D8D8] shrink-0"
          />
          <div class="flex flex-col">
            <span class="font-bold text-sm text-[#202224] dark:text-white leading-snug">{{ row.product_name }}</span>
            <span class="text-sm font-mono text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ row.order_number }}</span>
          </div>
        </div>
      </template>

      <!-- Cell: Location -->
      <template #cell-location="{ value }">
        <span class="text-sm font-medium text-[#475569] dark:text-[#E2E8F0]">{{ value }}</span>
      </template>

      <!-- Cell: Date - Time -->
      <template #cell-date_time="{ value }">
        <span class="text-sm font-medium text-[#475569] dark:text-[#E2E8F0] tabular-nums">{{ value }}</span>
      </template>

      <!-- Cell: Piece -->
      <template #cell-piece="{ value }">
        <span class="text-sm font-semibold text-[#202224] dark:text-white tabular-nums">{{ value }}</span>
      </template>

      <!-- Cell: Amount -->
      <template #cell-amount="{ value }">
        <span class="text-sm font-bold text-[#202224] dark:text-white tabular-nums">{{ formatCurrency(value) }}</span>
      </template>

      <!-- Cell: Status Pill -->
      <template #cell-status="{ value }">
        <AppBadge :variant="getBadgeVariant(value)" rounded="full" size="md">
          {{ value }}
        </AppBadge>
      </template>
    </AppTable>
  </div>
</template>


