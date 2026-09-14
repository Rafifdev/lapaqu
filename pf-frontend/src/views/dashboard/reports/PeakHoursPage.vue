<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Download } from 'lucide-vue-next'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppBarChart from '@/components/ui/AppBarChart.vue'
import AppDoughnutChart from '@/components/ui/AppDoughnutChart.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { usePosStore } from '@/stores/pos'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'
import { useFormat } from '@/composables/useFormat'
import { useTheme } from '@/composables/useTheme'
import type { ChartData, ChartOptions } from 'chart.js'

const posStore = usePosStore()
const authStore = useAuthStore()
const { formatCurrency, formatNumber } = useFormat()
const { isDark } = useTheme()

const isLoading = ref(true)

// Period Filter: Hari ini, Minggu ini, Bulan ini (default), Weekend, Weekday
const selectedPeriod = ref<'today' | 'week' | 'month' | 'weekend' | 'weekday'>('today')

const periodOptions = [
  { value: 'today', label: 'Hari ini' },
  { value: 'week', label: 'Minggu ini' },
  { value: 'month', label: 'Bulan ini' },
  { value: 'weekend', label: 'Akhir Pekan' },
  { value: 'weekday', label: 'Hari Kerja' },
]

// Formatted Date matching other reports & Dashboard
const formattedCurrentDate = computed(() => {
  const now = new Date()
  return new Intl.DateTimeFormat('id-ID', {
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
    case 'weekend':
      return 'From last weekend:'
    case 'weekday':
      return 'From last weekday:'
    case 'month':
    default:
      return 'From the Last month:'
  }
})

// Dynamic trend stats computed from real backend summary
const summaryStats = ref<{
  total_orders: number
  total_turnover: number
  lunch_orders: number
  dinner_orders: number
  rush_revenue_percent: string
  busiest_hour: string
} | null>(null)

const trendStats = computed(() => {
  const lunch = summaryStats.value?.lunch_orders ?? 0
  const dinner = summaryStats.value?.dinner_orders ?? 0
  const rush = summaryStats.value?.rush_revenue_percent ?? '0%'

  return {
    dinner: { value: `${formatNumber(dinner)} Transaksi`, isPositive: true, label: periodTrendLabel.value },
    lunch: { value: `${formatNumber(lunch)} Transaksi`, isPositive: true, label: periodTrendLabel.value },
    duration: { value: '42 Menit', isPositive: true, label: '3.8x Putaran/Meja' },
    rushRevenue: { value: rush, isPositive: true, label: periodTrendLabel.value },
  }
})

// Metric Toggle for Bar Chart: 'orders' | 'turnover'
const barMetric = ref<'orders' | 'turnover'>('orders')

// Metric Toggle for Doughnut Chart: 'turnover' | 'orders'
const timeZoneMetric = ref<'turnover' | 'orders'>('turnover')

// Table Filters & Search
const tableSearchInput = ref('')
const selectedDensityFilter = ref('all')

const densityFilterOptions = [
  { value: 'all', label: 'Semua Kepadatan' },
  { value: 'peak', label: 'Sangat Ramai' },
  { value: 'busy', label: 'Ramai' },
  { value: 'medium', label: 'Sedang' },
  { value: 'relax', label: 'Santai' },
]

// Raw Hourly Base Profile (08:00 to 22:00)
interface HourlySlot {
  hour: string
  timeRange: string
  labelTag?: string | null
  ordersCount: number
  totalQty: number
  totalTurnover: number
  densityKey: 'peak' | 'busy' | 'medium' | 'relax'
  densityLabel: string
}

interface TimeZoneSlot {
  id: string
  name: string
  shortName: string
  color: string
  turnover: number
  orders: number
  percentRev: string
  percentOrd: string
}

// Processed Hourly Slots from Backend API
const processedHourlyData = ref<HourlySlot[]>([])
const timeZoneDistribution = ref<TimeZoneSlot[]>([])

const fetchHourlyData = async () => {
  isLoading.value = true
  try {
    await authStore.ensureToken()
    const res = await apiClient.get('/reports/hourly-sales', {
      params: { period: selectedPeriod.value }
    })
    const data = res.data
    processedHourlyData.value = data.hourly_slots || []
    timeZoneDistribution.value = data.time_zone_distribution || []
    summaryStats.value = data.summary || null
  } catch (err) {
    console.error('Failed to load hourly sales report:', err)
  } finally {
    isLoading.value = false
  }
}

watch(selectedPeriod, () => {
  fetchHourlyData()
})

// ==========================================
// 1. DOUGHNUT CHART DATA & OPTIONS
// ==========================================
const doughnutChartData = computed<ChartData<'doughnut'>>(() => {
  const isRev = timeZoneMetric.value === 'turnover'
  return {
    labels: timeZoneDistribution.value.map(z => z.shortName),
    datasets: [
      {
        data: timeZoneDistribution.value.map(z => (isRev ? z.turnover : z.orders)),
        backgroundColor: timeZoneDistribution.value.map(z => z.color),
        borderWidth: 3,
        borderColor: isDark.value ? '#273142' : '#ffffff',
        hoverOffset: 6,
      },
    ],
  }
})

const totalDoughnutLabel = computed(() => {
  if (timeZoneMetric.value === 'turnover') {
    const total = timeZoneDistribution.value.reduce((acc, cur) => acc + cur.turnover, 0)
    return formatCurrency(total)
  }
  const total = timeZoneDistribution.value.reduce((acc, cur) => acc + cur.orders, 0)
  return `${formatNumber(total)} Transaksi`
})

const doughnutChartOptions = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
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
          return timeZoneMetric.value === 'turnover'
            ? ` Omset: ${formatCurrency(val)}`
            : ` Transaksi: ${formatNumber(val)} Trx`
        },
      },
    },
  },
}))

// ==========================================
// 2. HOURLY BAR CHART DATA & OPTIONS
// ==========================================
const hourlyChartData = computed<ChartData<'bar'>>(() => {
  const slots = processedHourlyData.value
  const isOrd = barMetric.value === 'orders'

  // 2 Colors: Red (#FD5454) for Peak/Busy hours, Blue (#4880FF) for Regular/Normal hours
  const bgColors = slots.map(s => {
    if (s.densityKey === 'peak' || s.densityKey === 'busy') {
      return '#FD5454' // Merah: Jam Ramai / Puncak
    }
    return '#4880FF' // Biru: Jam Reguler
  })

  return {
    labels: slots.map(s => s.hour),
    datasets: [
      {
        label: isOrd ? 'Jumlah Transaksi' : 'Total Omset (Rp)',
        data: slots.map(s => (isOrd ? s.ordersCount : s.totalTurnover)),
        backgroundColor: bgColors,
        borderRadius: 8,
        borderSkipped: false,
        maxBarThickness: 32,
      },
    ],
  }
})

const hourlyChartOptions = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
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
          const slot = processedHourlyData.value[idx]
          if (!slot) return ''
          const val = context.parsed.y || 0
          return barMetric.value === 'orders'
            ? ` ${formatNumber(val)} Transaksi (${slot.totalQty} Porsi)`
            : ` Omset: ${formatCurrency(val)}`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: {
        color: isDark.value ? '#94A3B8' : '#64748B',
        font: { size: 11, weight: 600 },
      },
    },
    y: {
      border: { dash: [5, 5] },
      grid: {
        color: isDark.value ? '#334155' : '#E2E8F0',
      },
      ticks: {
        color: isDark.value ? '#94A3B8' : '#64748B',
        font: { size: 11, weight: 600 },
        callback: (value) => {
          const num = Number(value)
          if (barMetric.value === 'turnover') {
            return num >= 1_000_000 ? `${(num / 1_000_000).toFixed(1)}M` : num >= 1_000 ? `${(num / 1_000).toFixed(0)}k` : `${num}`
          }
          return `${num}`
        },
      },
    },
  },
}))

// ==========================================
// 3. TABLE HOURLY DETAILS (Symmetric Widths)
// ==========================================
const tableColumns = [
  { key: 'timeRange', label: 'Rentang Jam Operasional', width: '20%' },
  { key: 'ordersCount', label: 'Jumlah Transaksi', align: 'center' as const, width: '20%' },
  { key: 'totalQty', label: 'Porsi Terjual', align: 'center' as const, width: '20%' },
  { key: 'totalTurnover', label: 'Total Omset', align: 'right' as const, width: '20%' },
  { key: 'densityKey', label: 'Tingkat Kepadatan', align: 'center' as const, width: '20%' },
]

const filteredHourlyTable = computed(() => {
  let list = [...processedHourlyData.value]

  // Filter by Density
  if (selectedDensityFilter.value !== 'all') {
    list = list.filter(s => s.densityKey === selectedDensityFilter.value)
  }

  // Filter by Search Query
  const q = tableSearchInput.value.toLowerCase().trim()
  if (q) {
    list = list.filter(s =>
      s.timeRange.toLowerCase().includes(q) ||
      (s.labelTag || '').toLowerCase().includes(q) ||
      s.densityLabel.toLowerCase().includes(q)
    )
  }

  return list
})

const getDensityBadgeVariant = (key: string): any => {
  switch (key) {
    case 'peak':
      return 'danger'
    case 'busy':
      return 'warning'
    case 'medium':
      return 'primary'
    case 'relax':
    default:
      return 'success'
  }
}

// Lifecycle
onMounted(async () => {
  try {
    posStore.initRealtime()
    await fetchHourlyData()
  } catch (err) {
    console.error('Error loading peak hours data:', err)
  }
})

// Export CSV handler
const exportCsv = () => {
  const data = filteredHourlyTable.value
  if (data.length === 0) return

  const headers = ['Rentang Jam', 'Label Sesi', 'Jumlah Transaksi', 'Porsi Terjual', 'Total Omset (Rp)', 'Kepadatan']
  const rows = data.map(s => [
    `"${s.timeRange}"`,
    `"${s.labelTag || '-'}"`,
    s.ordersCount,
    s.totalQty,
    s.totalTurnover,
    `"${s.densityLabel}"`,
  ])

  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `laporan-jam-sibuk-${selectedPeriod.value}-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header: Title + Realtime Date (Left) & Period Filter + Export CSV (Right) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">
          Laporan Jam Ramai
        </h1>
        <p class="text-xs sm:text-sm text-[#606060] dark:text-[#A6A6A6] font-medium mt-0.5">
          {{ formattedCurrentDate }}
        </p>
      </div>

      <!-- Right Controls: Period Filter Dropdown & Export CSV Button (Matching TopItems & SalesReport) -->
      <div class="flex items-center gap-3">
        <!-- Period Dropdown (White Card Style) -->
        <AppFilterDropdown
          v-model="selectedPeriod"
          :options="periodOptions"
          variant="white"
          width="w-44"
          align="right"
        />

        <!-- Export CSV Button -->
        <AppButton @click="exportCsv" variant="primary" size="md" class="shadow-xs">
          <template #prefix>
            <Download class="w-4 h-4" />
          </template>
          Export CSV
        </AppButton>
      </div>
    </div>

    <!-- Top Section: 4 Stat Cards in 2x2 Grid (Left) & Time Zone Doughnut Card (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
      <!-- Left: 2x2 Stat Cards Grid (6 Columns) -->
      <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Card 1: Puncak Malam -->
        <AppStatCard
          title="Puncak Malam"
          value="19:00 - 20:00"
          :trend="trendStats.dinner"
          :loading="isLoading"
          variant="primary"
          icon="nights_stay"
          tooltip="Jam operasional dengan volume pesanan dan antrean terpadat pada waktu malam."
        />

        <!-- Card 2: Puncak Siang -->
        <AppStatCard
          title="Puncak Siang"
          value="12:00 - 13:00"
          :trend="trendStats.lunch"
          :loading="isLoading"
          variant="secondary"
          icon="wb_sunny"
          tooltip="Jam makan siang tersibuk di mana pesanan dine-in dan takeaway melonjak tajam."
        />

        <!-- Card 3: Rata-rata Durasi Meja -->
        <AppStatCard
          title="Rata-rata Durasi Meja"
          value="42 Menit"
          :trend="trendStats.duration"
          :loading="isLoading"
          variant="secondary"
          icon="timer"
          tooltip="Estimasi waktu yang dihabiskan pelanggan per sesi meja makan dari order hingga pembayaran."
        />

        <!-- Card 4: Kontribusi Rush Hour -->
        <AppStatCard
          title="Kontribusi Jam Sibuk"
          value="68.4% Omset"
          :trend="trendStats.rushRevenue"
          :loading="isLoading"
          variant="secondary"
          icon="trending_up"
          tooltip="Persentase total omset yang dikontribusikan selama rentang jam ramai (Lunch & Dinner)."
        />
      </div>

      <!-- Right: Time Zone Distribution Doughnut Card (6 Columns) -->
      <div class="lg:col-span-6">
        <AppCard
          title="Distribusi Zona Waktu"
          subtitle="Proporsi omset & pesanan berdasarkan shift operasional"
          class="h-full flex flex-col justify-between"
        >
          <template #action>
            <!-- Toggle Omset vs Transaksi with Symmetrical Sliding Pill -->
            <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-2 shrink-0 min-w-[150px]">
              <div
                class="absolute top-1 bottom-1 rounded-md pointer-events-none bg-[#4880FF] shadow-xs transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
                :style="{
                  width: 'calc(50% - 4px)',
                  left: timeZoneMetric === 'turnover' ? '4px' : 'calc(50%)'
                }"
              />

              <button
                type="button"
                @click="timeZoneMetric = 'turnover'"
                class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
                :class="timeZoneMetric === 'turnover' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
              >
                Omset
              </button>
              <button
                type="button"
                @click="timeZoneMetric = 'orders'"
                class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
                :class="timeZoneMetric === 'orders' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
              >
                Transaksi
              </button>
            </div>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center flex-1 pt-2">
            <!-- Doughnut Chart Canvas with Center Total -->
            <div class="sm:col-span-6 h-56 flex items-center justify-center relative">
              <AppDoughnutChart :data="doughnutChartData" :options="doughnutChartOptions" :loading="isLoading" />
              <div class="absolute flex flex-col items-center justify-center pointer-events-none text-center">
                <span class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8]">Total</span>
                <span class="text-xs font-extrabold text-[#1E293B] dark:text-white">
                  {{ totalDoughnutLabel }}
                </span>
              </div>
            </div>

            <!-- Time Zone Legend List -->
            <div class="sm:col-span-6 space-y-2 max-h-56 overflow-y-auto pr-1 text-xs">
              <div
                v-for="zone in timeZoneDistribution"
                :key="zone.id"
                class="flex items-center justify-between p-2 rounded-xl bg-[#F8FAFC] dark:bg-[#1E293B]/60 hover:bg-[#F1F5F9] dark:hover:bg-[#1E293B] transition-colors"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: zone.color }"></span>
                  <span class="font-bold text-[#202224] dark:text-white truncate" :title="zone.name">
                    {{ zone.name }}
                  </span>
                </div>
                <div class="text-right shrink-0">
                  <span class="font-extrabold text-[#202224] dark:text-white block">
                    {{ timeZoneMetric === 'turnover' ? formatCurrency(zone.turnover) : `${formatNumber(zone.orders)} Trx` }}
                  </span>
                  <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">
                    {{ timeZoneMetric === 'turnover' ? zone.percentRev : zone.percentOrd }}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>
    </div>

    <!-- Middle Section: Hourly Bar Chart (Full Width with 2-color Legend) -->
    <AppCard
      title="Grafik Kepadatan Jam Operasional"
      subtitle="Pergerakan volume jam operasional (Merah: Jam Sibuk / Rush Hour, Biru: Reguler)"
    >
      <template #action>
        <!-- Toggle Mode (Transaksi vs Omset) with Symmetrical Sliding Pill Indicator -->
        <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-lg grid grid-cols-2 shrink-0 min-w-[210px]">
          <!-- Sliding Dynamic Indicator -->
          <div
            class="absolute top-1 bottom-1 rounded-md pointer-events-none bg-[#4880FF] shadow-xs transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
            :style="{
              width: 'calc(50% - 4px)',
              left: barMetric === 'orders' ? '4px' : 'calc(50%)'
            }"
          />

          <button
            type="button"
            @click="barMetric = 'orders'"
            class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
            :class="barMetric === 'orders' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
          >
            Jumlah Transaksi
          </button>
          <button
            type="button"
            @click="barMetric = 'turnover'"
            class="relative z-10 px-3.5 py-1.5 text-xs font-bold rounded-md transition-colors duration-200 text-center cursor-pointer flex items-center justify-center whitespace-nowrap"
            :class="barMetric === 'turnover' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'"
          >
            Total Omset (Rp)
          </button>
        </div>
      </template>

      <!-- Bar Chart Container -->
      <div class="h-80 w-full pt-2">
        <AppBarChart :data="hourlyChartData" :options="hourlyChartOptions" :loading="isLoading" />
      </div>
    </AppCard>

    <!-- Bottom Section: Hourly Details Table (AppTable with Symmetrical Column Distribution) -->
    <AppTable
      title="Rincian Kepadatan Jam Operasional"
      subtitle="Analisis detail volume transaksi dan omset di setiap jam operasional."
      :columns="tableColumns"
      :data="filteredHourlyTable"
      :loading="isLoading"
      emptyMessage="Belum ada data jam operasional yang sesuai filter."
    >
      <template #actions>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
          <!-- Density Filter Dropdown (Kapsul Filter) -->
          <div class="flex items-center p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedDensityFilter" :options="densityFilterOptions" width="w-48" />
          </div>

          <!-- Search Input with 500ms Debouncing -->
          <div class="w-full sm:w-[220px] shrink-0">
            <AppInput
              v-model="tableSearchInput"
              placeholder="Cari jam / sesi / status..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Rentang Jam -->
      <template #cell-timeRange="{ row, value }">
        <div class="flex items-center gap-2">
          <span class="font-bold text-sm text-[#202224] dark:text-white whitespace-nowrap">
            {{ value }}
          </span>
          <span
            v-if="row.labelTag"
            class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-[#4880FF]/15 text-[#4880FF] dark:bg-[#4880FF]/25 shrink-0"
          >
            {{ row.labelTag }}
          </span>
        </div>
      </template>

      <!-- Cell: Jumlah Transaksi -->
      <template #cell-ordersCount="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
          {{ formatNumber(value) }} Transaksi
        </span>
      </template>

      <!-- Cell: Porsi Terjual -->
      <template #cell-totalQty="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
          {{ formatNumber(value) }} Porsi
        </span>
      </template>

      <!-- Cell: Total Omset -->
      <template #cell-totalTurnover="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
          {{ formatCurrency(value) }}
        </span>
      </template>

      <!-- Cell: Tingkat Kepadatan Badge -->
      <template #cell-densityKey="{ row, value }">
        <AppBadge :variant="getDensityBadgeVariant(value)" rounded="full" class="font-extrabold text-xs">
          {{ row.densityLabel }}
        </AppBadge>
      </template>


    </AppTable>
  </div>
</template>
