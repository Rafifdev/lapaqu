<script setup lang="ts">
import { ref, computed, onMounted, onActivated, watch } from 'vue'
import { Download } from 'lucide-vue-next'
import { useFormat } from '@/composables/useFormat'
import { useTheme } from '@/composables/useTheme'
import { useDashboardI18n } from '@/i18n'
import { usePosStore } from '@/stores/pos'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppBarChart from '@/components/ui/AppBarChart.vue'
import AppDoughnutChart from '@/components/ui/AppDoughnutChart.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { ChartData, ChartOptions } from 'chart.js'

const posStore = usePosStore()
const authStore = useAuthStore()
const { formatCurrency, formatNumber } = useFormat()
const { isDark } = useTheme()
const { t, translate, locale } = useDashboardI18n()
const isLoading = ref(true)
const isYearLoading = ref(false)

interface TopReportItem {
  id: string
  name: string
  price: number
  imageUrl: string
  categoryId: string
  categoryName: string
  soldQty: number
  turnover: number
  rank: number
}

interface CategoryBreakdownItem {
  id: string
  name: string
  totalQty: number
  totalTurnover: number
  count: number
  color: string
}

// Period Filter: Hari ini, Minggu ini, Bulan ini (default), Tahun ini
const selectedPeriod = ref<'today' | 'week' | 'month' | 'year'>('month')

const periodOptions = computed(() => [
  { value: 'today', label: locale.value === 'en' ? 'Today' : 'Hari ini' },
  { value: 'week', label: locale.value === 'en' ? 'This Week' : 'Minggu ini' },
  { value: 'month', label: locale.value === 'en' ? 'This Month' : 'Bulan ini' },
  { value: 'year', label: locale.value === 'en' ? 'This Year' : 'Tahun ini' },
])

// Formatted Date (example: Monday, 24 December 2026)
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

// Dynamic stats indicators computed from real database data
const trendStats = computed(() => {
  const qty = totalSoldQty.value
  const turnover = totalTurnover.value
  const topItemName = topItemByQty.value?.name
  const topCatName = topCategory.value?.name

  return {
    qty: {
      value: `${formatNumber(qty)} Porsi`,
      isPositive: qty > 0,
      label: periodTrendLabel.value,
    },
    turnover: {
      value: formatCurrency(turnover),
      isPositive: turnover > 0,
      label: periodTrendLabel.value,
    },
    topItem: {
      value: topItemName || 'Belum ada',
      isPositive: Boolean(topItemName),
      label: 'Favorit #1',
    },
    topCategory: {
      value: topCatName || 'Belum ada',
      isPositive: Boolean(topCatName),
      label: 'Kategori utama',
    },
  }
})

// Controls for Interactive Top Items Chart
const metricType = ref<'quantity' | 'turnover'>('quantity')
const displayLimit = ref<number>(5)

// Controls for Category Chart
const categoryMetric = ref<'turnover' | 'quantity'>('turnover')

// Table Filters
const tableSearchInput = ref('')
const selectedCategoryFilter = ref('all')

const categoryFilterOptions = computed(() => [
  { value: 'all', label: 'Semua Kategori' },
  ...posStore.categories.map(c => ({ value: c.id, label: c.name })),
])

// Real Data State from Backend API
const reportItems = ref<TopReportItem[]>([])
const categoryBreakdown = ref<CategoryBreakdownItem[]>([])
const summaryData = ref<{
  total_sold_quantity: number
  total_turnover: number
  top_item: TopReportItem | null
  top_category: CategoryBreakdownItem | null
} | null>(null)

const fetchReportData = async (isInitial = false) => {
  if (isInitial || reportItems.value.length === 0) {
    isLoading.value = true
  }
  try {
    await authStore.ensureToken()
    await posStore.fetchCategories()
    const res = await apiClient.get('/reports/top-items', {
      params: { period: selectedPeriod.value }
    })
    const data = res.data
    reportItems.value = data.top_items || []
    categoryBreakdown.value = data.category_breakdown || []
    summaryData.value = data.summary || null
  } catch (err) {
    console.error('Failed to load top items report:', err)
  } finally {
    isLoading.value = false
  }
}

watch(selectedPeriod, async (newVal) => {
  if (newVal === 'year') {
    isYearLoading.value = true
  }
  try {
    await fetchReportData(false)
  } finally {
    isYearLoading.value = false
  }
})

onMounted(() => {
  if (reportItems.value.length > 0) {
    isLoading.value = false
    fetchReportData(false)
  } else {
    fetchReportData(true)
  }
})

onActivated(() => {
  fetchReportData(false)
})

// Badge Variant helper
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

// Columns
const columns = [
  { key: 'rank', label: 'Rank', align: 'center' as const, width: '8%' },
  { key: 'name', label: 'Menu', width: '30%' },
  { key: 'categoryName', label: 'Kategori', width: '16%' },
  { key: 'price', label: 'Harga Satuan', align: 'right' as const, width: '15%' },
  { key: 'soldQty', label: 'Kuantitas Terjual', align: 'center' as const, width: '15%' },
  { key: 'turnover', label: 'Kontribusi Omset', align: 'right' as const, width: '16%' },
]

// Filtered & Sorted Table Items (Preserving True Absolute Leaderboard Rank)
const filteredReportTableItems = computed(() => {
  let items = [...reportItems.value]

  // Filter by Category
  if (selectedCategoryFilter.value !== 'all') {
    items = items.filter(i => i.categoryId === selectedCategoryFilter.value)
  }

  // Filter by Search Input
  const q = tableSearchInput.value.toLowerCase().trim()
  if (q) {
    items = items.filter(i =>
      i.name.toLowerCase().includes(q) ||
      i.categoryName.toLowerCase().includes(q) ||
      i.price.toString().includes(q)
    )
  }

  return items
})

// Summary Stats
const totalSoldQty = computed(() => summaryData.value?.total_sold_quantity ?? reportItems.value.reduce((acc, it) => acc + it.soldQty, 0))
const totalTurnover = computed(() => summaryData.value?.total_turnover ?? reportItems.value.reduce((acc, it) => acc + it.turnover, 0))
const topItemByQty = computed(() => summaryData.value?.top_item ?? [...reportItems.value].sort((a, b) => b.soldQty - a.soldQty)[0])
const topCategory = computed(() => summaryData.value?.top_category ?? categoryBreakdown.value[0])

// Helper computations for Template
const barChartSubtitle = computed(() => {
  return metricType.value === 'quantity'
    ? 'Peringkat menu berdasarkan porsi terjual'
    : 'Peringkat menu berdasarkan kontribusi omset'
})

const filteredTopItems = computed(() => {
  const sorted = [...reportItems.value].sort((a, b) => {
    return metricType.value === 'quantity' ? b.soldQty - a.soldQty : b.turnover - a.turnover
  })
  if (displayLimit.value > 0) {
    return sorted.slice(0, displayLimit.value)
  }
  return sorted
})

// Top Items Bar Chart Data
const topItemsChartData = computed<ChartData<'bar'>>(() => {
  const isQty = metricType.value === 'quantity'
  const items = filteredTopItems.value

  return {
    labels: items.map(i => i.name),
    datasets: [
      {
        label: isQty ? 'Porsi Terjual' : 'Omset Penjualan (Rp)',
        data: items.map(i => (isQty ? i.soldQty : i.turnover)),
        backgroundColor: isQty ? '#00B69B' : '#4880FF',
        hoverBackgroundColor: isQty ? '#009680' : '#3568DF',
        borderRadius: 6,
        barThickness: 28,
        maxBarThickness: 36,
      },
    ],
  }
})

const topItemsChartOptions = computed<ChartOptions<'bar'>>(() => ({
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
      titleColor: '#FFFFFF',
      bodyColor: '#E2E8F0',
      padding: 12,
      cornerRadius: 8,
      callbacks: {
        label: (context) => {
          const val = context.raw as number
          return metricType.value === 'quantity'
            ? ` Terjual: ${formatNumber(val)} Porsi`
            : ` Omset: ${formatCurrency(val)}`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: {
        color: '#64748B',
        font: { size: 12, weight: 600 },
        maxRotation: 45,
        minRotation: 0,
      },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(226, 232, 240, 0.6)',
      },
      ticks: {
        color: '#64748B',
        font: { size: 11 },
        callback: (val) => {
          const num = Number(val)
          if (metricType.value === 'turnover') {
            return num >= 1_000_000 ? `${(num / 1_000_000).toFixed(1)}M` : num >= 1_000 ? `${(num / 1_000).toFixed(0)}k` : `${num}`
          }
          return `${num}`
        },
      },
    },
  },
}))

// Doughnut Chart Data & Options
const categoryChartData = computed<ChartData<'doughnut'>>(() => {
  const cats = categoryBreakdown.value
  const isTurnover = categoryMetric.value === 'turnover'

  return {
    labels: cats.map(c => c.name),
    datasets: [
      {
        data: cats.map(c => (isTurnover ? c.totalTurnover : c.totalQty)),
        backgroundColor: cats.map(c => c.color),
        borderWidth: 3,
        borderColor: isDark.value ? '#273142' : '#ffffff',
        hoverOffset: 6,
      },
    ],
  }
})

const totalCategoryLabel = computed(() => {
  if (categoryMetric.value === 'turnover') {
    return formatCurrency(totalTurnover.value)
  }
  return `${formatNumber(totalSoldQty.value)} Porsi`
})

const getCategoryValue = (cat: CategoryBreakdownItem) => {
  return categoryMetric.value === 'turnover' ? formatCurrency(cat.totalTurnover) : `${formatNumber(cat.totalQty)} Porsi`
}

const getCategoryPercent = (cat: CategoryBreakdownItem) => {
  if (categoryMetric.value === 'turnover') {
    return totalTurnover.value > 0 ? `${((cat.totalTurnover / totalTurnover.value) * 100).toFixed(1)}%` : '0%'
  }
  return totalSoldQty.value > 0 ? `${((cat.totalQty / totalSoldQty.value) * 100).toFixed(1)}%` : '0%'
}

const categoryChartOptions = computed<ChartOptions<'doughnut'>>(() => ({
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
      titleColor: '#FFFFFF',
      bodyColor: '#E2E8F0',
      padding: 12,
      cornerRadius: 8,
      callbacks: {
        label: (context) => {
          const idx = context.dataIndex
          const cat = categoryBreakdown.value[idx]
          if (!cat) return ''
          if (categoryMetric.value === 'turnover') {
            const percent = totalTurnover.value > 0 ? ((cat.totalTurnover / totalTurnover.value) * 100).toFixed(1) : 0
            return ` Omset: ${formatCurrency(cat.totalTurnover)} (${percent}%)`
          } else {
            const percent = totalSoldQty.value > 0 ? ((cat.totalQty / totalSoldQty.value) * 100).toFixed(1) : 0
            return ` Terjual: ${formatNumber(cat.totalQty)} Porsi (${percent}%)`
          }
        },
      },
    },
  },
}))

// Export CSV handler
const exportCsv = () => {
  if (reportItems.value.length === 0) return
  const headers = ['Rank', 'Menu', 'Harga Satuan', 'Kuantitas Terjual', 'Kontribusi Omset']
  const rows = filteredReportTableItems.value.map(i => [
    i.rank,
    `"${i.name.replace(/"/g, '""')}"`,
    i.price,
    i.soldQty,
    i.turnover,
  ])
  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `laporan-menu-terlaris-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header with Date Format and Period Dropdown Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div>
          <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">Laporan Menu Terlaris</h1>
        </div>
        <p class="text-xs sm:text-sm text-[#606060] dark:text-[#A6A6A6] font-medium mt-0.5">
          {{ formattedCurrentDate }}
        </p>
      </div>

      <!-- Right Controls: Period Filter Dropdown & Export CSV Button -->
      <div class="flex items-center gap-3">
        <!-- Period Dropdown (Style Riwayat Order dengan background putih & shadow-sm) -->
        <AppFilterDropdown v-model="selectedPeriod" :options="periodOptions" variant="white" width="w-44" />

        <!-- Export CSV Button -->
        <AppButton @click="exportCsv" variant="primary" size="md" class="shadow-xs">
          <template #prefix>
            <Download class="w-4 h-4" />
          </template>
          Export CSV
        </AppButton>
      </div>
    </div>

    <!-- Top Section: 2x2 Stat Cards (Left) & Category Doughnut Chart (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
      <!-- Left: 2x2 Stat Cards Grid -->
      <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <AppStatCard title="Total Porsi Terjual" :value="`${formatNumber(totalSoldQty)} Porsi`" icon="restaurant_menu"
          variant="primary" :trend="trendStats.qty" :loading="isLoading"
          :syncing="isYearLoading" />
        <AppStatCard title="Total Omset Menu" :value="formatCurrency(totalTurnover)" icon="payments" variant="secondary"
          :trend="trendStats.turnover" :loading="isLoading"
          :syncing="isYearLoading" />
        <AppStatCard title="Menu Favorit #1" :value="topItemByQty?.name || '-'" icon="emoji_events" variant="secondary"
          :trend="trendStats.topItem" :loading="isLoading"
          :syncing="isYearLoading" />
        <AppStatCard title="Kategori Terlaris" :value="topCategory?.name || '-'" icon="category" variant="secondary"
          :trend="trendStats.topCategory" :loading="isLoading"
          :syncing="isYearLoading" />
      </div>

      <!-- Right: Category Distribution Doughnut Chart -->
      <div class="lg:col-span-6">
        <AppCard title="Distribusi Kategori"
          :syncing="isYearLoading" subtitle="Proporsi berdasarkan kategori menu"
          class="h-full flex flex-col justify-between">
          <template #action>
            <!-- Toggle Omset vs Porsi with Sliding Indicator -->
            <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-2 shrink-0 min-w-[150px]">
              <div
                class="absolute top-1 bottom-1 rounded-md pointer-events-none bg-[#4880FF] shadow-xs transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
                :style="{
                  width: 'calc(50% - 4px)',
                  left: categoryMetric === 'turnover' ? '4px' : 'calc(50%)'
                }" />

              <button type="button" @click="categoryMetric = 'turnover'"
                class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
                :class="categoryMetric === 'turnover' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
                Omset
              </button>
              <button type="button" @click="categoryMetric = 'quantity'"
                class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
                :class="categoryMetric === 'quantity' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
                Porsi
              </button>
            </div>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center flex-1 pt-2">
            <!-- Doughnut Chart Canvas -->
            <div class="sm:col-span-6 h-56 flex items-center justify-center relative">
              <AppDoughnutChart :data="categoryChartData" :options="categoryChartOptions" :loading="isLoading" />
              <div class="absolute flex flex-col items-center justify-center pointer-events-none text-center">
                <span class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8]">Total</span>
                <span class="text-xs font-extrabold text-[#1E293B] dark:text-white">
                  {{ totalCategoryLabel }}
                </span>
              </div>
            </div>

            <!-- Category Legend Breakdown List -->
            <div class="sm:col-span-6 space-y-2 max-h-56 overflow-y-auto pr-1 text-xs">
              <div v-for="cat in categoryBreakdown" :key="cat.id"
                class="flex items-center justify-between p-2 rounded-xl bg-[#F8FAFC] dark:bg-[#1E293B]/60 hover:bg-[#F1F5F9] dark:hover:bg-[#1E293B] transition-colors">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                  <span class="font-bold text-[#202224] dark:text-white truncate">{{ cat.name }}</span>
                </div>
                <div class="text-right shrink-0">
                  <span class="font-extrabold text-[#202224] dark:text-white block">
                    {{ getCategoryValue(cat) }}
                  </span>
                  <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">
                    {{ getCategoryPercent(cat) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>
    </div>

    <!-- Middle Section: Top Items Bar Chart (Full Width) -->
    <AppCard title="Grafik Menu Terlaris" :subtitle="barChartSubtitle">
      <template #action>
        <div class="flex flex-wrap items-center justify-end gap-2">
          <!-- Toggle Mode (Qty vs Turnover) with Smooth Sliding Indicator -->
          <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-2 shrink-0 min-w-[210px]">
            <!-- Sliding Dynamic Indicator -->
            <div
              class="absolute top-1 bottom-1 rounded-md pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0 shadow-xs"
              :class="metricType === 'quantity' ? 'bg-[#00B69B]' : 'bg-[#4880FF]'" :style="{
                width: 'calc(50% - 4px)',
                left: metricType === 'quantity' ? '4px' : 'calc(50%)'
              }" />

            <button type="button" @click="metricType = 'quantity'"
              class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
              :class="metricType === 'quantity' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Porsi (Qty)
            </button>
            <button type="button" @click="metricType = 'turnover'"
              class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
              :class="metricType === 'turnover' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Omset (Rp)
            </button>
          </div>

          <!-- Limit Filter with Smooth Sliding Indicator -->
          <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-3 shrink-0 min-w-[180px]">
            <!-- Sliding Dynamic Indicator -->
            <div
              class="absolute top-1 bottom-1 rounded-md pointer-events-none bg-[#4880FF] shadow-xs transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
              :style="{
                width: 'calc((100% - 8px) / 3)',
                left: displayLimit === 5 ? '4px' : displayLimit === 10 ? 'calc(4px + (100% - 8px) / 3)' : 'calc(4px + (100% - 8px) * 2 / 3)'
              }" />

            <button type="button" @click="displayLimit = 5"
              class="relative z-10 px-2.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
              :class="displayLimit === 5 ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Top 5
            </button>
            <button type="button" @click="displayLimit = 10"
              class="relative z-10 px-2.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
              :class="displayLimit === 10 ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Top 10
            </button>
            <button type="button" @click="displayLimit = 0"
              class="relative z-10 px-2.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
              :class="displayLimit === 0 ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Semua
            </button>
          </div>
        </div>
      </template>

      <!-- Bar Chart Container -->
      <div class="h-80 w-full pt-2">
        <AppBarChart :data="topItemsChartData" :options="topItemsChartOptions" :loading="isLoading" />
      </div>
    </AppCard>

    <!-- Bottom Section: Table Card with Title, Subtitle & Filter Actions in Whitespace Right -->
    <AppTable title="Daftar Menu Terlaris" subtitle="Rincian lengkap performa penjualan dan kontribusi omset per menu"
      :syncing="isYearLoading"
      :columns="columns" :data="filteredReportTableItems" :loading="isLoading"
      emptyMessage="Belum ada data laporan penjualan menu.">
      <template #actions>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
          <!-- Category Filter Dropdown (Kapsul Filter) -->
          <div class="flex items-center p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedCategoryFilter" :options="categoryFilterOptions" width="w-48" />
          </div>

          <!-- 3. Search Input matching Categories Page -->
          <div class="w-full sm:w-[220px] shrink-0">
            <AppInput v-model="tableSearchInput" placeholder="Cari nama menu..." suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <template #cell-rank="{ value }">
        <span class="font-bold text-sm">#{{ value }}</span>
      </template>

      <template #cell-name="{ row }">
        <div class="flex items-center gap-3">
          <img :src="row.imageUrl" :alt="row.name" class="w-9 h-9 rounded-lg object-cover shadow-2xs" />
          <span class="font-bold text-sm text-[#202224] dark:text-white">{{ row.name }}</span>
        </div>
      </template>

      <template #cell-categoryName="{ value }">
        <span v-if="value" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1]">{{ value }}</span>
        <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">-</span>
      </template>

      <template #cell-price="{ value }">
        <span class="font-semibold text-sm text-[#64748B] dark:text-[#94A3B8]">{{ formatCurrency(value) }}</span>
      </template>

      <template #cell-soldQty="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">{{ formatNumber(value) }} Porsi</span>
      </template>

      <template #cell-turnover="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">{{ formatCurrency(value) }}</span>
      </template>
    </AppTable>
  </div>
</template>
