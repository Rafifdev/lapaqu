<script setup lang="ts">
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
  type ChartData,
  type ChartOptions,
  type Plugin
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface Props {
  data: ChartData<'line'>
  options?: ChartOptions<'line'>
  plugins?: Plugin<'line'>[]
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div v-if="loading" class="w-full h-full min-h-[240px] flex flex-col justify-between p-4 animate-pulse">
    <!-- Chart Area Skeleton with simulated area curve and horizontal grid lines -->
    <div class="flex-1 relative flex flex-col justify-between border-b border-[#E2E8F0] dark:border-[#334155] pb-4">
      <div class="w-full border-t border-dashed border-[#E2E8F0] dark:border-[#334155]/60"></div>
      <div class="w-full border-t border-dashed border-[#E2E8F0] dark:border-[#334155]/60"></div>
      <div class="w-full border-t border-dashed border-[#E2E8F0] dark:border-[#334155]/60"></div>

      <!-- Pulsing Shimmer Wave Simulation -->
      <div class="absolute inset-x-0 bottom-4 top-12 bg-linear-to-t from-[#4880FF]/15 to-transparent rounded-2xl flex items-center justify-center">
        <div class="w-full h-1 bg-[#4880FF]/30 rounded-full mx-2"></div>
      </div>
    </div>
    <!-- X-axis Labels Skeleton -->
    <div class="flex items-center justify-between gap-4 pt-3">
      <div v-for="i in 7" :key="'line-lbl-' + i" class="h-3 w-12 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
    </div>
  </div>

  <Line v-else :data="data" :options="options" :plugins="plugins" />
</template>
