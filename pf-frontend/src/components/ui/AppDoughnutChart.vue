<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  type ChartData,
  type ChartOptions,
  type Plugin,
} from 'chart.js'
import { useTheme } from '@/composables/useTheme'

// Register Chart.js components
ChartJS.register(
  ArcElement,
  Title,
  Tooltip,
  Legend
)

interface Props {
  data: ChartData<'doughnut'>
  options?: ChartOptions<'doughnut'>
  plugins?: Plugin<'doughnut'>[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const { isDark } = useTheme()

// Ensure doughnut slices maintain stroke and automatically adapt stroke color to dark/light card backgrounds
const computedData = computed<ChartData<'doughnut'>>(() => {
  if (!props.data || !props.data.datasets) return props.data

  const strokeColor = isDark.value ? '#273142' : '#ffffff'

  return {
    ...props.data,
    datasets: props.data.datasets.map(dataset => {
      const isCustomColor =
        dataset.borderColor &&
        dataset.borderColor !== '#ffffff' &&
        dataset.borderColor !== '#273142' &&
        dataset.borderColor !== '#fff'

      return {
        ...dataset,
        borderWidth: dataset.borderWidth ?? 3,
        borderColor: isCustomColor ? dataset.borderColor : strokeColor,
      }
    }),
  }
})
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div v-if="loading" class="w-full h-full min-h-[180px] flex items-center justify-center p-4 animate-pulse">
    <!-- Circular Doughnut Ring Skeleton -->
    <div class="relative w-40 h-40 rounded-full border-12 border-[#E2E8F0] dark:border-[#334155] flex items-center justify-center">
      <!-- Center total skeleton -->
      <div class="flex flex-col items-center gap-1.5">
        <div class="h-2.5 w-10 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
        <div class="h-4 w-16 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
      </div>
    </div>
  </div>

  <Doughnut
    v-else
    :key="isDark ? 'dark-doughnut' : 'light-doughnut'"
    :data="computedData"
    :options="options"
    :plugins="plugins"
  />
</template>
