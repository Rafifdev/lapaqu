<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Motion, AnimatePresence } from 'motion-v'
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  title: string
  value: string | number
  icon?: string
  iconColor?: string
  iconBgColor?: string
  variant?: 'primary' | 'secondary' | 'warning' | 'danger'
  iconVariant?: 'primary' | 'secondary' | 'warning' | 'danger'
  trend?: string | {
    value: string
    isPositive?: boolean
    label?: string
    isNeutral?: boolean
    icon?: string
  } | null
  trendType?: 'up' | 'down'
  info?: string
  tooltip?: string
  showInfo?: boolean
  loading?: boolean
  syncing?: boolean
  animated?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  value: '',
  icon: 'trending_up',
  variant: 'secondary',
  showInfo: true,
  loading: false,
  syncing: false,
  animated: true,
})

const effectiveVariant = computed(() => props.iconVariant || props.variant || 'secondary')

// Helper untuk parsing angka dan format satuan (Rp, Porsi, Jenis, Bahan, %, dll)
function parseNumericValue(val: string | number | undefined | null) {
  if (val === undefined || val === null || val === '') {
    return { isNumeric: false, raw: val ?? '', prefix: '', num: 0, suffix: '' }
  }

  if (typeof val === 'number') {
    return { isNumeric: !isNaN(val), raw: String(val), prefix: '', num: val, suffix: '' }
  }

  const str = String(val).trim()
  const regex = /^([^0-9\-+]*)([-+]?[0-9.,\s]+)(.*)$/
  const match = str.match(regex)

  if (!match) {
    return { isNumeric: false, raw: str, prefix: '', num: 0, suffix: '' }
  }

  const prefix = match[1]
  const numStr = match[2].trim()
  const suffix = match[3]

  let cleaned = numStr
  if (cleaned.includes('.') && !cleaned.includes(',')) {
    const parts = cleaned.split('.')
    if (parts.length > 2 || (parts.length === 2 && parts[1].length === 3)) {
      cleaned = cleaned.replace(/\./g, '')
    }
  } else if (cleaned.includes('.') && cleaned.includes(',')) {
    cleaned = cleaned.replace(/\./g, '').replace(',', '.')
  } else if (cleaned.includes(',')) {
    cleaned = cleaned.replace(',', '.')
  }

  const parsedNum = parseFloat(cleaned)
  if (isNaN(parsedNum)) {
    return { isNumeric: false, raw: str, prefix: '', num: 0, suffix: '' }
  }

  return { isNumeric: true, raw: str, prefix, num: parsedNum, suffix }
}

const displayValue = ref<string>(String(props.value ?? ''))
let currentNumeric = 0
let animFrameId: number | null = null

const animateNumber = (from: number, to: number, prefix: string, suffix: string, finalRaw: string) => {
  if (animFrameId) {
    cancelAnimationFrame(animFrameId)
    animFrameId = null
  }

  if (!props.animated || from === to) {
    currentNumeric = to
    displayValue.value = finalRaw
    return
  }

  const diff = Math.abs(to - from)
  const duration = diff <= 5 ? 180 : 260
  const startTime = performance.now()

  const step = (now: number) => {
    const elapsed = now - startTime
    const progress = Math.min(elapsed / duration, 1)
    // Silk-smooth easeOutQuart curve
    const ease = 1 - Math.pow(1 - progress, 4)
    const current = Math.round(from + (to - from) * ease)
    currentNumeric = current

    if (progress < 1) {
      displayValue.value = `${prefix}${current.toLocaleString('id-ID')}${suffix}`
      animFrameId = requestAnimationFrame(step)
    } else {
      currentNumeric = to
      displayValue.value = finalRaw
      animFrameId = null
    }
  }

  animFrameId = requestAnimationFrame(step)
}

const syncValue = (newVal: string | number) => {
  const parsed = parseNumericValue(newVal)
  if (!parsed.isNumeric) {
    displayValue.value = String(newVal ?? '')
    return
  }

  const fromNum = currentNumeric
  animateNumber(fromNum, parsed.num, parsed.prefix, parsed.suffix, parsed.raw)
}

watch(() => props.value, (newVal) => {
  syncValue(newVal)
})

watch(() => props.loading, (isLoading) => {
  if (!isLoading) {
    currentNumeric = 0
    syncValue(props.value)
  }
})

onMounted(() => {
  if (!props.loading) {
    const parsed = parseNumericValue(props.value)
    if (parsed.isNumeric && parsed.num > 0) {
      animateNumber(0, parsed.num, parsed.prefix, parsed.suffix, parsed.raw)
    } else {
      displayValue.value = String(props.value ?? '')
    }
  }
})

onBeforeUnmount(() => {
  if (animFrameId) {
    cancelAnimationFrame(animFrameId)
  }
})

const trendInfo = computed(() => {
  if (!props.trend) return null

  if (typeof props.trend === 'string') {
    const isDown = props.trendType === 'down' || props.trend.toLowerCase().includes('down') || props.trend.startsWith('-')
    const isZero = props.trend === '0%' || props.trend === '0' || props.trend === '0.0%'
    return {
      value: props.trend || '0%',
      isPositive: isZero ? false : !isDown,
      isNeutral: isZero,
      label: '',
      icon: isZero ? 'remove' : (isDown ? 'arrow_downward' : 'arrow_upward'),
    }
  }

  const val = String(props.trend.value || '').trim()
  const isZero = !val || val === '0%' || val === '0.0%' || val === '0' || props.trend.isNeutral === true
  return {
    value: props.trend.value || '0%',
    isPositive: isZero ? false : (props.trend.isPositive ?? true),
    isNeutral: props.trend.isNeutral ?? isZero,
    label: props.trend.label || '',
    icon: props.trend.icon || (isZero ? 'remove' : (props.trend.isPositive ? 'arrow_upward' : 'arrow_downward')),
  }
})

const tooltipText = computed(() => {
  if (props.tooltip || props.info) return props.tooltip || props.info
  const t = (props.title || '').toLowerCase()
  if (t.includes('porsi')) {
    return 'Akumulasi kuantitas seluruh porsi menu yang terjual dan telah lunas dibayar.'
  }
  if (t.includes('omset') || t.includes('penjualan') || t.includes('revenue')) {
    return 'Total nilai transaksi kotor dari penjualan pada rentang periode yang dipilih.'
  }
  if (t.includes('pelanggan') || t.includes('customer')) {
    return 'Jumlah pelanggan yang melakukan transaksi pesanan dalam periode ini.'
  }
  if (t.includes('pesanan') || t.includes('order')) {
    return 'Jumlah seluruh pesanan yang berhasil masuk dan diproses oleh sistem.'
  }
  if (t.includes('favorit')) {
    return 'Menu makanan/minuman dengan kuantitas porsi penjualan tertinggi.'
  }
  if (t.includes('kategori')) {
    return 'Kategori menu yang menghasilkan kontribusi omset tertinggi.'
  }
  if (t.includes('antrean') || t.includes('pending')) {
    return 'Pesanan yang sedang aktif menunggu dan diproses oleh pihak dapur.'
  }
  return `Ringkasan data informasi metrik untuk ${props.title}.`
})
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div
    v-if="loading"
    class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between animate-pulse transition-all duration-300 h-full"
  >
    <!-- Top Row Skeleton -->
    <div>
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-full bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
          <div class="h-4 w-28 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
        </div>
        <div v-if="showInfo" class="w-5 h-5 rounded-full bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
      </div>

      <!-- Middle Value Skeleton -->
      <div class="mt-4 mb-2">
        <div class="h-7 w-36 bg-[#E2E8F0] dark:bg-[#334155] rounded-lg"></div>
      </div>
    </div>

    <!-- Bottom Trend Skeleton -->
    <div class="flex items-center gap-2.5 pt-2">
      <div class="h-5 w-16 rounded-full bg-[#E2E8F0] dark:bg-[#334155]"></div>
      <div class="h-3.5 w-24 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
    </div>
  </div>

  <!-- Real Stat Card: Solid & Steady without Hover Lift -->
  <div
    v-else
    class="relative bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between h-full"
  >
    <!-- Top Row: Icon Circle + Title Inline (Left) & Info Icon with Tooltip (Right) -->
    <div>
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Primary Variant: Solid Blue Background with White Icon -->
          <div
            v-if="effectiveVariant === 'primary'"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-[#4880FF] text-white shadow-xs"
          >
            <AppIcon :name="icon" :size="20" />
          </div>

          <!-- Custom Color Fallback (if explicitly passed) -->
          <div
            v-else-if="props.iconBgColor && props.iconColor && effectiveVariant !== 'secondary'"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
            :style="{ backgroundColor: props.iconBgColor, color: props.iconColor }"
          >
            <AppIcon :name="icon" :size="20" />
          </div>

          <!-- Secondary Variant: Neutral / Gray Background with Theme Text Icon -->
          <div
            v-else
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-[#F1F4F9] dark:bg-[#1B2431] text-[#202224] dark:text-white"
          >
            <AppIcon :name="icon" :size="20" />
          </div>

          <!-- Title -->
          <span class="text-sm font-semibold text-[#202224]/70 dark:text-white/70 truncate select-none">
            {{ title }}
          </span>
        </div>

        <!-- Right: Info / Exclamation Icon with Interactive Tooltip Popover -->
        <div v-if="syncing" class="w-6 h-6 flex items-center justify-center shrink-0">
          <svg
            class="animate-spin w-4 h-4 text-[#4880FF] shrink-0"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <div v-else-if="showInfo" class="relative group/tooltip shrink-0">
          <button
            type="button"
            class="w-6 h-6 rounded-full flex items-center justify-center text-[#A6A6A6] hover:text-[#4880FF] hover:bg-[#F1F4F9] dark:hover:bg-[#1B2431] transition-all duration-200 focus:outline-none cursor-pointer"
            :aria-label="`Info ${title}`"
          >
            <AppIcon name="info" :size="18" />
          </button>

          <!-- Floating Tooltip Box -->
          <div
            class="absolute right-0 top-full mt-2 hidden group-hover/tooltip:block group-focus-within/tooltip:block z-30 w-56 p-2.5 bg-[#202224] dark:bg-[#1E293B] text-white text-xs font-normal rounded-lg shadow-xl border border-white/10 pointer-events-none transition-all duration-150 leading-relaxed animate-tooltip-in"
          >
            {{ tooltipText }}
            <!-- Little Pointer Arrow -->
            <div class="absolute -top-1 right-2 w-2 h-2 bg-[#202224] dark:bg-[#1E293B] rotate-45 border-l border-t border-white/10"></div>
          </div>
        </div>
      </div>

      <!-- Middle: Big Metric Number with smooth tabular typography & In-Place Count-Up Animation -->
      <div class="mt-4 mb-2">
        <h4
          class="text-[28px] font-bold text-[#202224] dark:text-white tracking-[0.5px] leading-tight tabular-nums truncate select-none"
        >
          {{ displayValue }}
        </h4>
      </div>
    </div>

    <!-- Bottom Row: Trend Pill Badge + Subtitle with Motion V GPU Animation (Smooth Transition, No Hover Zoom) -->
    <div class="pt-2 min-h-[30px] flex items-center">
      <AnimatePresence mode="wait">
        <Motion
          v-if="trendInfo"
          :key="`${trendInfo.value}-${trendInfo.label}-${trendInfo.isPositive}-${trendInfo.isNeutral}`"
          :initial="{ opacity: 0, y: 6, scale: 0.94 }"
          :animate="{ opacity: 1, y: 0, scale: 1 }"
          :exit="{ opacity: 0, y: -5, scale: 0.96 }"
          :transition="{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }"
          class="flex items-center gap-2.5 flex-wrap text-xs sm:text-[13px] will-change-transform"
        >
          <span
            :class="[
              'inline-flex items-center gap-1 font-bold px-2.5 py-0.5 rounded-full text-xs shrink-0 transition-colors duration-300 shadow-xs select-none',
              trendInfo.isNeutral
                ? 'bg-[#F1F5F9] dark:bg-[#334155] text-[#64748B] dark:text-[#94A3B8]'
                : trendInfo.isPositive
                  ? 'bg-[#E8F8F3] dark:bg-[#00B69B]/20 text-[#00B69B]'
                  : 'bg-[#FEECEE] dark:bg-[#F93C65]/20 text-[#F93C65]',
            ]"
          >
            <AppIcon
              :name="trendInfo.icon || (trendInfo.isPositive ? 'arrow_upward' : 'arrow_downward')"
              :size="13"
              class="transition-transform duration-300"
            />
            <span>{{ trendInfo.value }}</span>
          </span>
          <span
            v-if="trendInfo.label"
            class="text-[#606060] dark:text-[#A6A6A6] font-normal text-xs sm:text-[13px] truncate transition-opacity duration-300"
          >
            {{ trendInfo.label }}
          </span>
        </Motion>
      </AnimatePresence>
    </div>
  </div>
</template>

<style scoped>
@keyframes tooltipIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-tooltip-in {
  animation: tooltipIn 160ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
