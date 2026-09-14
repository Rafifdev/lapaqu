<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppIcon from '@/components/ui/AppIcon.vue'
import emptyOrderIllustration from '@/assets/empty_state/empty-order.svg'

import { ref } from 'vue'
const posStore = usePosStore()
const { formatTimeOnly } = useFormat()

// Completed Orders (Strictly LIFO: Newest completed order first)
const completedOrders = computed(() => {
  return [...posStore.orders]
    .filter(o => o.status === 'completed' || o.status === 'cancelled' || o.status === 'expired')
    .sort((a, b) => new Date(b.updatedAt || b.createdAt).getTime() - new Date(a.updatedAt || a.createdAt).getTime())
})

const hasInitialData = computed(() => completedOrders.value.length > 0)
const isLoading = ref(!hasInitialData.value)
let polling: any = null

const fetchCompletedOrders = async (isBackground = false) => {
  if (!isBackground && !hasInitialData.value) {
    isLoading.value = true
  }
  try {
    await posStore.fetchOrders()
  } finally {
    isLoading.value = false
  }
}

const handleKdsRefresh = () => {
  fetchCompletedOrders(true)
}

onMounted(() => {
  fetchCompletedOrders(hasInitialData.value)
  posStore.initRealtime()
  polling = setInterval(() => fetchCompletedOrders(true), 4000)
  window.addEventListener('kds:refresh', handleKdsRefresh)
})

onUnmounted(() => {
  if (polling) clearInterval(polling)
  window.removeEventListener('kds:refresh', handleKdsRefresh)
})

const getShortOrderNumber = (num: string) => {
  if (!num) return '#000'
  return num.replace(/^ORD-/, '#').replace(/^POS-/, '#')
}

const getTableDisplayName = (code?: string) => {
  if (!code || code === '0' || code.toLowerCase() === 'takeaway') return 'Takeaway'
  const digits = code.replace(/\D/g, '')
  if (digits) {
    return `Table ${digits.padStart(2, '0')}`
  }
  return `Table ${code}`
}

const getStatusPillConfig = (status: string) => {
  switch (status) {
    case 'expired':
      return {
        label: 'Kadaluarsa',
        icon: 'history_toggle_off',
        bgClass: 'bg-[#F1F5F9] dark:bg-[#334155] text-[#64748B] dark:text-[#94A3B8]',
      }
    case 'ready':
      return {
        label: 'Ready to Serve',
        icon: 'check_circle',
        bgClass: 'bg-[#DCFCE7] dark:bg-[#16A34A]/20 text-[#16A34A] dark:text-[#4ADE80]',
      }
    case 'preparing':
      return {
        label: 'Preparing',
        icon: 'skillet',
        bgClass: 'bg-[#FEF3C7] dark:bg-[#D97706]/20 text-[#D97706] dark:text-[#FBBF24]',
      }
    case 'cancelled':
      return {
        label: 'Dibatalkan',
        icon: 'cancel',
        bgClass: 'bg-[#FEE2E2] dark:bg-[#DC2626]/20 text-[#DC2626] dark:text-[#F87171]',
      }
    case 'completed':
    default:
      return {
        label: 'Completed',
        icon: 'task_alt',
        bgClass: 'bg-[#E6F9F5] dark:bg-[#16A34A]/20 text-[#16A34A] dark:text-[#4ADE80]',
      }
  }
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <!-- Header: Completed & Cancelled Orders (Fixed at top) -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
        Riwayat Pesanan
      </h1>
    </div>

    <!-- Scrollable Content Area: Strictly BELOW Header -->
    <div class="flex-1 overflow-y-auto min-h-0 pr-1 pb-8 [scrollbar-gutter:stable]">
      <!-- Skeleton Loading State (Mirip persis struktur kartu pesanan riwayat selesai) -->
      <div v-if="isLoading && completedOrders.length === 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="n in (completedOrders.length > 0 ? Math.min(completedOrders.length, 4) : 4)"
          :key="n"
          class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between animate-pulse"
        >
          <!-- Top Section Skeleton -->
          <div class="space-y-3.5">
            <!-- Customer Name & Queue Number -->
            <div class="flex items-start justify-between gap-2">
              <div class="h-5 w-32 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
              <div class="h-4 w-12 bg-[#E2E8F0] dark:bg-[#334155] rounded font-mono"></div>
            </div>

            <!-- Time & Table Info -->
            <div class="space-y-2 mt-2">
              <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
                <div class="h-3.5 w-24 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
                <div class="h-3.5 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
              </div>
            </div>

            <!-- Dashed Divider -->
            <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#334155] my-2"></div>

            <!-- Items List -->
            <div class="space-y-2.5 pt-1">
              <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-1">
                  <div class="w-4 h-4 rounded-full bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
                  <div class="h-3.5 bg-[#E2E8F0] dark:bg-[#334155] rounded w-28"></div>
                </div>
                <div class="h-3.5 w-6 bg-[#E2E8F0] dark:bg-[#334155] rounded shrink-0"></div>
              </div>
            </div>
          </div>

          <!-- Bottom Status Pill Skeleton -->
          <div class="pt-5 mt-auto">
            <div class="h-8 bg-[#E2E8F0] dark:bg-[#334155] rounded-lg w-full"></div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="completedOrders.length === 0"
        class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-6">
        <div class="relative flex items-center justify-center mb-4 sm:mb-5 pointer-events-none">
          <img :src="emptyOrderIllustration" alt="Belum Ada Pesanan Selesai"
            class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
        </div>
        <h3 class="text-xl sm:text-2xl md:text-[26px] font-black text-[#1E293B] dark:text-white tracking-tight">
          Whoops! :(
        </h3>
        <p
          class="text-xs sm:text-base font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
          Belum ada riwayat pesanan saat ini
        </p>
      </div>

      <!-- Completed Cards Grid (Exact Order Masuk Layout & Style) -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="order in completedOrders" :key="order.id"
          class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between">
          <!-- Top Section -->
          <div>
            <!-- Header: Customer Name & Queue Number -->
            <div class="flex items-start justify-between gap-2">
              <h3 class="text-base font-bold text-[#1E293B] dark:text-white leading-tight truncate">
                {{ order.customerName || 'Pelanggan Umum' }}
              </h3>
              <span class="text-sm font-semibold text-[#94A3B8] dark:text-[#64748B] tabular-nums font-mono shrink-0">
                {{ getShortOrderNumber(order.orderNumber) }}
              </span>
            </div>

            <!-- Date/Time & Table Info -->
            <div class="space-y-1.5 mt-2.5">
              <!-- Time Row -->
              <div class="flex items-center gap-2 text-xs md:text-sm text-[#64748B] dark:text-[#94A3B8] font-medium">
                <AppIcon name="schedule" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span>{{ formatTimeOnly(order.updatedAt || order.createdAt) }} WIB</span>
              </div>

              <!-- Table Row -->
              <div class="flex items-center gap-2 text-xs md:text-sm text-[#64748B] dark:text-[#94A3B8] font-medium">
                <AppIcon name="restaurant" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span>{{ getTableDisplayName(order.tableCode) }}</span>
              </div>
            </div>

            <!-- Dashed Divider Line -->
            <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#334155] my-3.5" />

            <!-- Order Summary Section -->
            <div class="space-y-2">
              <!-- Order Header: Count -->
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-[#1E293B] dark:text-white">
                  Order <span class="text-[#94A3B8] font-normal">({{ order.items.length }})</span>
                </span>
                <span v-if="order.status === 'expired'" class="text-xs font-bold text-slate-400 dark:text-slate-500">
                  Tidak Dimasak
                </span>
                <span v-else-if="order.status === 'cancelled'" class="text-xs font-bold text-[#DC2626] dark:text-[#F87171]">
                  Tidak Dimasak
                </span>
                <span v-else class="text-xs font-bold text-[#16A34A] dark:text-[#4ADE80]">
                  Selesai Dimasak
                </span>
              </div>

              <!-- Items List (Clean Row without wrapper box) -->
              <div
                class="space-y-1.5 pt-0.5 max-h-36 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <div v-for="item in order.items" :key="item.id"
                  class="flex items-center justify-between gap-2 text-xs md:text-sm text-[#64748B] dark:text-[#CBD5E1]">
                  <span class="truncate pr-2 font-medium flex-1">
                    {{ item.quantity }}x {{ item.menuItemName || item.name || 'Menu Item' }}
                  </span>
                  <span class="text-[#16A34A] dark:text-[#4ADE80] flex items-center shrink-0">
                    <AppIcon name="check_circle" :size="16" />
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Status Pill Action -->
          <div
            class="pt-4 mt-2 flex items-center justify-between border-t border-dashed border-[#E2E8F0] dark:border-[#334155]">
            <div :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-2xs',
              getStatusPillConfig(order.status).bgClass
            ]">
              <AppIcon :name="getStatusPillConfig(order.status).icon" :size="14" />
              <span>{{ getStatusPillConfig(order.status).label }}</span>
            </div>
            <span v-if="order.status === 'ready'"
              class="text-xs text-[#D97706] dark:text-[#FBBF24] font-bold">
              Belum Diambil
            </span>
            <span v-else-if="order.status === 'cancelled'"
              class="text-xs text-[#DC2626] dark:text-[#F87171] font-bold">
              Dibatalkan Kasir
            </span>
            <span v-else-if="order.status === 'expired'"
              class="text-xs text-slate-500 dark:text-slate-400 font-bold">
              Waktu Habis
            </span>
            <span v-else class="text-xs text-[#94A3B8] dark:text-[#64748B] font-semibold">
              Sudah Diambil
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>