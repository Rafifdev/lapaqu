<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useFormat } from '@/composables/useFormat'
import { usePosStore } from '@/stores/pos'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { Motion, AnimatePresence } from 'motion-v'
import type { Order } from '@/types'
import emptyOrderIllustration from '@/assets/empty_state/empty-order.svg'

const router = useRouter()
const { formatCurrency } = useFormat()
const posStore = usePosStore()

let pollInterval: any = null

const handleRefresh = (event?: any) => {
  // Jika refresh berasal dari update status spesifik yang sudah di-reconcile in-place, lewati refetch menyeluruh
  if (event?.detail?.orderId && !event?.detail?.status?.includes('completed') && !event?.detail?.status?.includes('cancelled') && !event?.detail?.status?.includes('expired')) {
    return
  }
  posStore.fetchOrders(true)
}

const now = ref(Date.now())
let nowInterval: any = null

const parseDateSafe = (d: any): number => {
  if (!d) return 0
  if (typeof d === 'string') {
    const safeStr = d.includes(' ') && !d.includes('T') ? d.replace(' ', 'T') : d
    const t = new Date(safeStr).getTime()
    return isNaN(t) ? 0 : t
  }
  return new Date(d).getTime() || 0
}

const getElapsedMinutes = (isoDate: string) => {
  if (!isoDate) return 0
  const t = parseDateSafe(isoDate)
  if (t === 0) return 0
  return Math.max(1, Math.floor((now.value - t) / 60000))
}

const getElapsedTime = (isoDate: string) => {
  return `${getElapsedMinutes(isoDate)} mnt`
}

const getSlaVariant = (isoDate: string): 'primary' | 'warning' | 'danger' => {
  const m = getElapsedMinutes(isoDate)
  if (m >= 15) return 'danger'
  if (m >= 10) return 'warning'
  return 'primary'
}

onMounted(() => {
  posStore.fetchOrders(orders.value.length > 0)
  posStore.initRealtime()
  pollInterval = setInterval(handleRefresh, 4000)
  window.addEventListener('kds:refresh', handleRefresh)
  nowInterval = setInterval(() => {
    now.value = Date.now()
  }, 30000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (nowInterval) clearInterval(nowInterval)
  window.removeEventListener('kds:refresh', handleRefresh)
})

const orders = computed(() => posStore.incomingOrders)
const selectedOrder = ref<Order | null>(null)
const isDetailOpen = ref(false)

const formatOrderDate = (isoStr: string) => {
  if (!isoStr) return '-'
  const safeStr = isoStr.includes(' ') && !isoStr.includes('T') ? isoStr.replace(' ', 'T') : isoStr
  const date = new Date(safeStr)
  if (isNaN(date.getTime())) return '-'
  const day = date.getDate()
  const month = date.toLocaleDateString('id-ID', { month: 'short' })
  const time = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'
  return `${day} ${month}, ${time}`
}

const getShortOrderNumber = (num: string) => {
  if (!num) return '#000'
  return num.replace(/^ORD-/, '#').replace(/^POS-/, '#')
}

const getTableDisplayName = (tableCode?: string) => {
  if (!tableCode) return 'Takeaway'
  const num = tableCode.replace(/[^0-9]/g, '')
  return num ? `Table ${num.padStart(2, '0')}` : tableCode
}

const getStatusPillConfig = (order: Order) => {
  const readyCount = (order.items || []).filter(i => i.status === 'ready' || i.status === 'served').length
  const totalCount = (order.items || []).length
  if (order.status === 'preparing' && readyCount > 0 && readyCount < totalCount) {
    return {
      label: `Partial Saji (${readyCount}/${totalCount})`,
      icon: 'restaurant',
      bgClass: 'bg-[#FFA756]/15 dark:bg-[#FFA756]/20 text-[#FFA756]',
    }
  }
  switch (order.status) {
    case 'pending_payment':
    case 'awaiting_payment':
      return {
        label: 'Menunggu Bayar',
        icon: 'schedule',
        bgClass: 'bg-[#FEF3C7] dark:bg-[#D97706]/20 text-[#D97706] dark:text-[#FBBF24]',
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
    case 'completed':
      return {
        label: 'Completed',
        icon: 'task_alt',
        bgClass: 'bg-[#E2EAF8] dark:bg-[#334155] text-[#4880FF] dark:text-[#93C5FD]',
      }
    case 'expired':
      return {
        label: 'Kadaluarsa',
        icon: 'history_toggle_off',
        bgClass: 'bg-[#F1F5F9] dark:bg-[#334155] text-[#64748B] dark:text-[#94A3B8]',
      }
    case 'cancelled':
      return {
        label: 'Cancelled',
        icon: 'cancel',
        bgClass: 'bg-[#FEE2E2] dark:bg-[#DC2626]/20 text-[#DC2626] dark:text-[#F87171]',
      }
    default:
      return {
        label: 'New Order',
        icon: 'receipt_long',
        bgClass: 'bg-[#EBF1FF] dark:bg-[#4880FF]/20 text-[#4880FF] dark:text-[#93C5FD]',
      }
  }
}

const markAsPreparing = (order: Order) => {
  posStore.updateOrderStatus(order.id, 'preparing')
}

const markAsReady = (order: Order) => {
  posStore.updateOrderStatus(order.id, 'ready')
}

const isCompleteSuccess = ref(false)
let successTimer: any = null

const markAsCompleted = async (order: Order) => {
  await posStore.updateOrderStatus(order.id, 'completed')
  isCompleteSuccess.value = true
  if (successTimer) clearTimeout(successTimer)
  successTimer = setTimeout(() => {
    isCompleteSuccess.value = false
  }, 1200)
}

const isCancelModalOpen = ref(false)
const orderToCancel = ref<Order | null>(null)
const isCancelling = ref(false)

const openCancelModal = (order: Order) => {
  orderToCancel.value = order
  isCancelModalOpen.value = true
}

const handleCancelOrder = async () => {
  if (!orderToCancel.value) return
  isCancelling.value = true
  try {
    await posStore.updateOrderStatus(orderToCancel.value.id, 'cancelled')
    isCancelModalOpen.value = false
    orderToCancel.value = null
  } catch (err: any) {
    console.error('Failed to cancel order:', err)
  } finally {
    isCancelling.value = false
  }
}

const openDetail = (order: Order) => {
  selectedOrder.value = order
  isDetailOpen.value = true
}

const voidOrderItem = async (order: Order, itemId: string) => {
  try {
    await posStore.voidOrderItem(order.id, itemId, 'Dibatalkan oleh kasir')
    const it = order.items.find(i => i.id === itemId)
    if (it) {
      it.status = 'voided'
    }
  } catch (err: any) {
    alert(err?.message || 'Gagal membatalkan item')
  }
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <!-- Header Section (Fixed at top) -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
          Order Masuk <span v-if="orders.length > 0" class="text-lg font-normal text-[#64748B] dark:text-[#94A3B8]">({{ orders.length }})</span>
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <AppButton @click="router.push('/pos/manual')" variant="primary" size="md" icon="add"
          class="!rounded-lg shadow-sm">
          Order Manual
        </AppButton>
      </div>
    </div>

    <!-- Scrollable Content Area: Strictly BELOW Header -->
    <div class="flex-1 overflow-y-auto min-h-0 pr-1 pb-8 [scrollbar-gutter:stable]">

      <!-- Empty State if no incoming orders -->
      <div v-if="orders.length === 0"
        class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-6">
        <div class="relative flex items-center justify-center mb-4 sm:mb-5 pointer-events-none">
          <img :src="emptyOrderIllustration" alt="Tidak Ada Order Masuk"
            class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
        </div>
        <h3 class="text-xl sm:text-2xl md:text-[26px] font-black text-[#1E293B] dark:text-white tracking-tight">
          Whoops! :(
        </h3>
        <p
          class="text-xs sm:text-base font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
          Belum ada order masuk saat ini
        </p>
      </div>

      <!-- Live Order Cards Grid (Exact Reference Card Design) -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="order in orders" :key="order.id"
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
                <span>{{ formatOrderDate(order.createdAt) }}</span>
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
              <!-- Order Header: Count & Elapsed Time (Identik dengan Dapur) -->
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-[#1E293B] dark:text-white">
                  Order <span class="text-[#94A3B8] font-normal">({{ order.items.length }})</span>
                </span>
                <AppBadge :variant="getSlaVariant(order.createdAt)" size="sm" rounded="full">
                  {{ getElapsedTime(order.createdAt) }}
                </AppBadge>
              </div>

              <!-- Items List (Exact Match with Dapur) -->
              <div
                class="space-y-1.5 pt-0.5 max-h-36 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <div v-for="item in order.items" :key="item.id"
                  :class="[
                    'flex items-center justify-between gap-2 text-xs md:text-sm py-1 transition-colors',
                    item.status === 'voided' ? 'line-through opacity-40' : 'text-[#64748B] dark:text-[#CBD5E1]'
                  ]">
                  <div class="flex items-center gap-2 truncate flex-1 min-w-0">
                    <div :class="[
                      'w-4 h-4 rounded flex items-center justify-center shrink-0 transition-colors',
                      item.status === 'ready' || item.status === 'served'
                        ? 'bg-[#16A34A] dark:bg-[#22C55E] text-white'
                        : 'border border-[#CBD5E1] dark:border-[#475569] bg-white dark:bg-[#273142]'
                    ]">
                      <AppIcon v-if="item.status === 'ready' || item.status === 'served'" name="check" :size="12" />
                    </div>
                    <span :class="['truncate font-medium', (item.status === 'ready' || item.status === 'served') ? 'line-through opacity-75' : '']">
                      {{ item.quantity }}x {{ item.menuItemName }}
                    </span>
                  </div>

                  <span v-if="item.notes" class="text-[10px] text-[#FCBE2D] italic shrink-0 max-w-[90px] truncate">
                    {{ item.notes }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Status Pill & Action (Cashier only serves when ready) -->
          <div class="pt-4 mt-2 flex items-center justify-between gap-2 border-t border-dashed border-[#E2E8F0] dark:border-[#334155]">
            <div :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-2xs shrink-0',
              getStatusPillConfig(order).bgClass
            ]">
              <AppIcon :name="getStatusPillConfig(order).icon" :size="14" />
              <span>{{ getStatusPillConfig(order).label }}</span>
            </div>

            <!-- Batalkan Pesanan (Hanya di kasir sebelum masuk tahap masak) -->
            <div v-if="order.status === 'confirmed'" class="flex items-center gap-1.5" @click.stop>
              <AppButton variant="danger" size="sm" class="!py-1.5 !px-3.5 !rounded-lg !text-xs font-bold" @click="openCancelModal(order)" title="Batalkan Pesanan">
                Batalkan
              </AppButton>
            </div>

            <!-- Cashier finishes order after serving -->
            <div v-else-if="order.status === 'ready'" class="flex items-center gap-1.5" @click.stop>
              <AppButton variant="primary" size="sm" class="!py-1.5 !px-3.5 !rounded-lg !text-xs font-bold" @click="markAsCompleted(order)" title="Selesaikan Order">
                Selesai
              </AppButton>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Modal Konfirmasi Batalkan Pesanan -->
    <AppModal v-model="isCancelModalOpen" title="Batalkan Pesanan" maxWidth="sm">
      <div v-if="orderToCancel" class="space-y-3 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin membatalkan pesanan <span class="font-bold text-[#1E293B] dark:text-white">{{
            getShortOrderNumber(orderToCancel.orderNumber) }}</span>?
        </p>
        <p class="text-xs text-[#EF3826] font-medium bg-[#EF3826]/10 dark:bg-[#EF3826]/20 p-2.5 rounded-lg">
          Pesanan yang dibatalkan akan otomatis dihapus dari antrean kasir dan dapur.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="secondary" size="md" @click="isCancelModalOpen = false">
            Kembali
          </AppButton>
          <AppButton variant="danger" size="md" :loading="isCancelling" @click="handleCancelOrder">
            Ya, Batalkan
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Order Detail & Void Modal -->
    <AppModal v-model="isDetailOpen" :title="`Detail ${selectedOrder?.orderNumber || 'Order'}`" maxWidth="lg">
      <div v-if="selectedOrder" class="space-y-4 text-xs md:text-sm">
        <!-- Order Header Summary Info -->
        <div class="grid grid-cols-2 gap-3 bg-[#F1F4F9] dark:bg-[#323D4E] p-3.5 rounded-xl">
          <div>
            <p class="text-[#64748B] dark:text-[#94A3B8] font-semibold">Meja / Lokasi:</p>
            <p class="font-bold text-sm text-[#202224] dark:text-white mt-0.5">
              {{ getTableDisplayName(selectedOrder.tableCode) }}
            </p>
          </div>
          <div>
            <p class="text-[#64748B] dark:text-[#94A3B8] font-semibold">Status / Metode Bayar:</p>
            <p v-if="selectedOrder.status === 'expired' || selectedOrder.paymentStatus === 'expired'" class="font-bold text-sm text-slate-500 mt-0.5">
              Kadaluarsa (Tidak Dibayar)
            </p>
            <p v-else-if="selectedOrder.status === 'pending_payment' || selectedOrder.status === 'awaiting_payment'" class="font-bold text-sm uppercase text-amber-500 mt-0.5">
              {{ selectedOrder.paymentMethod || 'QRIS' }} (Menunggu Bayar)
            </p>
            <p v-else class="font-bold text-sm uppercase text-[#00B69B] mt-0.5">
              {{ selectedOrder.paymentMethod || 'QRIS' }} (Lunas)
            </p>
          </div>
        </div>

        <!-- Items Table / List -->
        <div class="space-y-2.5">
          <h4 class="font-bold text-[#202224] dark:text-white text-sm">Daftar Item Menu:</h4>
          <div v-for="item in selectedOrder.items" :key="item.id"
            class="flex items-center justify-between p-3 rounded-xl border border-[#E8E8E8] dark:border-[#313D4F] bg-[#F8FAFC] dark:bg-[#1E293B]">
            <div>
              <p
                :class="['font-bold text-sm text-[#202224] dark:text-white', item.status === 'voided' ? 'line-through text-[#FD5454]' : '']">
                {{ item.quantity }}x {{ item.menuItemName }}
              </p>
              <p v-if="item.selectedOptions && item.selectedOptions.length > 0"
                class="text-xs text-[#4880FF] font-semibold mt-0.5">
                {{item.selectedOptions.map(o => o.optionName).join(', ')}}
              </p>
            </div>

            <div class="flex items-center gap-3">
              <span class="font-bold text-[#202224] dark:text-white tabular-nums">{{ formatCurrency(item.subtotal)
              }}</span>
              <button v-if="item.status !== 'voided' && selectedOrder.status === 'confirmed'"
                @click="voidOrderItem(selectedOrder, item.id)"
                class="px-2.5 py-1 rounded-md bg-[#FFEBEB] text-[#FD5454] font-bold text-xs hover:bg-[#FD5454] hover:text-white transition-colors cursor-pointer">
                Void
              </button>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end w-full">
          <AppButton @click="isDetailOpen = false" variant="primary" size="md" class="!rounded-lg">
            Tutup
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Success Checkmark Overlay (GPU-Accelerated 60fps Smooth - Motion V) -->
    <AnimatePresence>
      <Motion v-if="isCompleteSuccess" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }"
        :transition="{ duration: 0.18, ease: 'easeOut' }"
        class="fixed inset-0 z-[99999] flex items-center justify-center pointer-events-none bg-black/45 [transform:translateZ(0)]">
        <Motion :initial="{ scale: 0.4, rotate: -20, opacity: 0 }" :animate="{ scale: 1, rotate: 0, opacity: 1 }"
          :exit="{ scale: 0.8, opacity: 0 }" :transition="{ type: 'spring', damping: 14, stiffness: 260 }"
          class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white dark:bg-[#1E293B] shadow-2xl border border-white/20 dark:border-[#334155] flex items-center justify-center text-[#00B69B] [transform:translateZ(0)]">
          <AppIcon name="check_circle" :size="64" />
        </Motion>
      </Motion>
    </AnimatePresence>
  </div>
</template>
