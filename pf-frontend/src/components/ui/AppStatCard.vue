<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  title?: string
  value?: string | number
  icon?: string
  iconColor?: string
  iconBgColor?: string
  variant?: 'primary' | 'secondary'
  iconVariant?: 'primary' | 'secondary'
  trend?: string | {
    value: string
    isPositive?: boolean
    label?: string
  }
  trendType?: 'up' | 'down'
  info?: string
  tooltip?: string
  showInfo?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  value: '',
  icon: 'trending_up',
  variant: 'secondary',
  showInfo: true,
  loading: false,
})

const effectiveVariant = computed(() => props.iconVariant || props.variant || 'secondary')

const trendInfo = computed(() => {
  if (!props.trend) return null

  if (typeof props.trend === 'string') {
    const isDown = props.trendType === 'down' || props.trend.toLowerCase().includes('down') || props.trend.startsWith('-')
    return {
      value: props.trend,
      isPositive: !isDown,
      label: '',
    }
  }

  return {
    value: props.trend.value,
    isPositive: props.trend.isPositive ?? true,
    label: props.trend.label || '',
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
    class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between animate-pulse transition-all duration-200"
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

  <!-- Real Stat Card -->
  <div
    v-else
    class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between transition-all duration-200"
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
          <span class="text-base font-semibold text-[#202224]/70 dark:text-white/70 truncate">
            {{ title }}
          </span>
        </div>

        <!-- Right: Info / Exclamation Icon with Interactive Tooltip Popover -->
        <div v-if="showInfo" class="relative group shrink-0">
          <button
            type="button"
            class="w-6 h-6 rounded-full flex items-center justify-center text-[#A6A6A6] hover:text-[#4880FF] hover:bg-[#F1F4F9] dark:hover:bg-[#1B2431] transition-all focus:outline-none cursor-pointer"
            :aria-label="`Info ${title}`"
          >
            <AppIcon name="info" :size="18" />
          </button>

          <!-- Floating Tooltip Box -->
          <div
            class="absolute right-0 top-full mt-2 hidden group-hover:block group-focus-within:block z-30 w-56 p-2.5 bg-[#202224] dark:bg-[#1E293B] text-white text-xs font-normal rounded-lg shadow-xl border border-white/10 pointer-events-none transition-all duration-150 leading-relaxed"
          >
            {{ tooltipText }}
            <!-- Little Pointer Arrow -->
            <div class="absolute -top-1 right-2 w-2 h-2 bg-[#202224] dark:bg-[#1E293B] rotate-45 border-l border-t border-white/10"></div>
          </div>
        </div>
      </div>

      <!-- Middle: Big Metric Number -->
      <div class="mt-4 mb-2">
        <h4 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight leading-tight truncate" :title="String(value)">
          {{ value }}
        </h4>
      </div>
    </div>

    <!-- Bottom Row: Trend Pill Badge + Subtitle (Matching reference [ ↑ 2.7% ] From the Last month:) -->
    <div v-if="trendInfo" class="flex items-center gap-2.5 flex-wrap pt-2 text-xs sm:text-[13px]">
      <span
        :class="[
          'inline-flex items-center gap-1 font-bold px-2.5 py-0.5 rounded-full text-xs shrink-0',
          trendInfo.isPositive
            ? 'bg-[#E8F8F3] dark:bg-[#00B69B]/20 text-[#00B69B]'
            : 'bg-[#FEECEE] dark:bg-[#F93C65]/20 text-[#F93C65]',
        ]"
      >
        <AppIcon :name="trendInfo.isPositive ? 'arrow_upward' : 'arrow_downward'" :size="13" />
        {{ trendInfo.value }}
      </span>
      <span v-if="trendInfo.label" class="text-[#606060] dark:text-[#A6A6A6] font-normal text-xs sm:text-[13px] truncate">
        {{ trendInfo.label }}
      </span>
    </div>
  </div>
</template>
