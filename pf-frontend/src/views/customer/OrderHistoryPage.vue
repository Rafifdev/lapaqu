<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const { formatCurrency, formatTimeOnly } = useFormat()

const isLoading = ref(true)
const orders = ref<any[]>([])

const outletId = computed(() => (route.params.outletId as string) || cartStore.outletId || '')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || '')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)

const fetchSessionOrders = async () => {
  if (!tableCode.value) {
    isLoading.value = false
    return
  }

  try {
    isLoading.value = true
    const res = await apiClient.get('/public/orders/active', {
      params: {
        table_code: tableCode.value,
        outlet_id: outletId.value || undefined,
      },
    })

    if (res.data?.orders && Array.isArray(res.data.orders)) {
      orders.value = res.data.orders
    } else if (res.data?.order) {
      orders.value = [res.data.order]
    } else {
      orders.value = []
    }
  } catch (err) {
    console.error('Failed to fetch session orders:', err)
    orders.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchSessionOrders()
})

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'completed':
      return { variant: 'success' as const, label: 'Selesai' }
    case 'ready':
      return { variant: 'info' as const, label: 'Siap Disajikan' }
    case 'preparing':
    case 'cooking':
      return { variant: 'warning' as const, label: 'Diproses' }
    case 'confirmed':
      return { variant: 'primary' as const, label: 'Diterima Dapur' }
    case 'cancelled':
      return { variant: 'danger' as const, label: 'Dibatalkan' }
    default:
      return { variant: 'neutral' as const, label: status }
  }
}

const getItemsSummary = (orderItems: any[]) => {
  if (!orderItems || orderItems.length === 0) return '-'
  return orderItems
    .map(i => `${i.quantity}x ${i.item_name_snapshot || i.name || 'Menu'}`)
    .join(', ')
}
</script>

<template>
  <div class="space-y-4 select-none">
    <!-- SKELETON LOADING STATE -->
    <div v-if="isLoading" class="space-y-4">
      <div class="space-y-2">
        <div class="h-6 w-48 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-3.5 w-64 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>

      <div class="space-y-3">
        <div v-for="n in 3" :key="n" class="p-4 rounded-3xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] space-y-3 shadow-xs">
          <div class="flex items-center justify-between">
            <div class="h-4 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="h-5 w-16 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
          <div class="h-3.5 w-4/5 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="flex justify-between items-center pt-2 border-t border-[#F1F5F9] dark:border-[#334155]">
            <div class="h-3 w-16 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="h-4 w-20 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
        </div>
      </div>

      <div class="h-11 w-full rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-6" />
    </div>

    <!-- REAL DATA STATE -->
    <template v-else>
      <div>
        <h2 class="text-lg font-black text-[#202224] dark:text-white">
          Riwayat Sesi Meja {{ tableCode || '-' }}
        </h2>
        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium mt-0.5">
          Daftar semua pesanan yang telah dikirim dalam sesi kunjungan ini.
        </p>
      </div>

      <!-- Empty State jika belum ada pesanan -->
      <div
        v-if="orders.length === 0"
        class="py-12 bg-white dark:bg-[#273142] rounded-3xl p-6 border border-[#E2E8F0] dark:border-[#334155] shadow-xs text-center space-y-3"
      >
        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-[#1E293B] text-slate-400 flex items-center justify-center mx-auto">
          <AppIcon name="receipt_long" :size="32" />
        </div>
        <h3 class="text-base font-bold text-[#1E293B] dark:text-white">Belum Ada Riwayat Pesanan</h3>
        <p class="text-sm text-[#64748B] dark:text-[#94A3B8] max-w-xs mx-auto">
          Pesanan yang Anda kirimkan ke dapur dalam sesi meja ini akan tercatat di sini.
        </p>
        <AppButton @click="router.push(menuUrl)" variant="primary" class="mt-2 !rounded-full">
          Pesan Menu Sekarang
        </AppButton>
      </div>

      <!-- Real Order List -->
      <div v-else class="space-y-3">
        <AppCard
          v-for="ord in orders"
          :key="ord.id"
          class="!p-4 border border-[#E2E8F0] dark:border-[#334155] space-y-3"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-black text-[#202224] dark:text-white font-mono">
              #{{ ord.order_number }}
            </span>
            <AppBadge :variant="getStatusBadge(ord.status).variant" size="sm" dot>
              {{ getStatusBadge(ord.status).label }}
            </AppBadge>
          </div>
          <p class="text-xs text-[#475569] dark:text-[#CBD5E1] font-medium">
            {{ getItemsSummary(ord.items) }}
          </p>
          <div class="flex justify-between items-center pt-2 border-t border-[#E2E8F0] dark:border-[#334155]">
            <span class="text-[11px] text-[#64748B] dark:text-[#94A3B8]">
              {{ formatTimeOnly(ord.created_at) }} WIB
            </span>
            <span class="text-xs font-bold text-[#1E293B] dark:text-white tabular-nums">
              {{ formatCurrency(ord.total_amount) }}
            </span>
          </div>
        </AppCard>

        <AppButton
          @click="router.push(menuUrl)"
          variant="primary"
          size="md"
          block
          icon="restaurant_menu"
          class="mt-6 !rounded-full"
        >
          Pesan Menu Lagi
        </AppButton>
      </div>
    </template>
  </div>
</template>
