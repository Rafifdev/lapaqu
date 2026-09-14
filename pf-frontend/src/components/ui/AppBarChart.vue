<script setup lang="ts">
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  type ChartData,
  type ChartOptions,
  type Plugin,
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

interface Props {
  data: ChartData<'bar'>
  options?: ChartOptions<'bar'>
  plugins?: Plugin<'bar'>[]
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div v-if="loading" class="w-full h-full min-h-[220px] flex flex-col justify-between p-4 animate-pulse">
    <!-- Grid lines with skeleton bars of varying heights -->
    <div class="flex-1 flex items-end justify-between gap-3 sm:gap-6 pb-4 border-b border-[#E2E8F0] dark:border-[#334155]">
      <div
        v-for="(height, i) in ['65%', '40%', '85%', '55%', '90%', '70%', '45%', '80%']"
        :key="i"
        class="flex-1 bg-[#E2E8F0] dark:bg-[#334155] rounded-t-lg transition-all"
        :style="{ height }"
      />
    </div>
    <!-- X-axis Labels Skeleton -->
    <div class="flex items-center justify-between gap-3 sm:gap-6 pt-3">
      <div v-for="i in 8" :key="'bar-lbl-' + i" class="flex-1 h-3 bg-[#E2E8F0] dark:bg-[#334155] rounded mx-1"></div>
    </div>
  </div>

  <Bar v-else :data="data" :options="options" :plugins="plugins" />
</template>
