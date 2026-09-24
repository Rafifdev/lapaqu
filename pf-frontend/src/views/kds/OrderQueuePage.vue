<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePosStore } from '@/stores/pos'
import { usePosKdsI18n } from '@/i18n'
import { useFormat } from '@/composables/useFormat'
import { useNotyf } from '@/composables/useNotyf'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import emptyOrderIllustration from '@/assets/empty_state/empty-order.svg'
import apiClient from '@/services/api'

const route = useRoute()
const posStore = usePosStore()
const notyf = useNotyf()
const { t, translate } = usePosKdsI18n()

const handleOrderToast = (e: any) => {
  const detail = e.detail
  const num = detail?.orderNumber ? `#${detail.orderNumber.replace(/^ORD-/, '')}` : 'Baru'
  const loc = detail?.tableNumber ? `Meja ${detail.tableNumber}` : 'Takeaway'
  notyf.open({
    type: 'info',
    message: `🍳 Pesanan Dapur Masuk: ${num} • ${loc}`,
    duration: 6000,
  })
}

const pageTitle = computed(() => {
  if (route.query.status === 'ready') {
    return t('kds.readyTitle', 'Antrean Siap Saji')
  }
  return t('kds.queueTitle')
})
const { formatTimeOnly, formatCustomerName } = useFormat()

const isLoading = ref(false)
let pollingInterval: any = null
const pendingItemUpdates = new Map<string, 'ready' | 'preparing' | 'served'>()
const pendingOrderStatusUpdates = new Map<string, string>()

let nowInterval: any = null

const handleKdsRefresh = (event?: any) => {
  // Jika item dapur atau order status sudah diupdate in-place, lewati fetch kds menyeluruh
  if (event?.detail?.itemId || (event?.detail?.orderId && pendingOrderStatusUpdates.has(event.detail.orderId))) {
    return
  }
  fetchKdsOrders(true)
}

onMounted(() => {
  const activeOutletId = localStorage.getItem('lapaqu_outlet_id') || undefined
  fetchKdsOrders(posStore.orders.length > 0)
  posStore.initRealtime(activeOutletId)
  pollingInterval = setInterval(() => fetchKdsOrders(true), 4000)
  window.addEventListener('kds:refresh', handleKdsRefresh)
  window.addEventListener('app:order-notification', handleOrderToast)
  nowInterval = setInterval(() => {
    now.value = Date.now()
  }, 30000) // Update timer setiap 30 detik tanpa re-render berlebihan
})

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval)
  if (nowInterval) clearInterval(nowInterval)
  window.removeEventListener('kds:refresh', handleKdsRefresh)
  window.removeEventListener('app:order-notification', handleOrderToast)
})

const fetchKdsOrders = async (isBackground = false) => {
  try {
    if (!isBackground && posStore.orders.length === 0) {
      isLoading.value = true
    }
    const activeOutletId = localStorage.getItem('lapaqu_outlet_id') || undefined
    const params: any = {}
    if (activeOutletId) params.outlet_id = activeOutletId
    const res = await apiClient.get('/kds/orders', { params })
    const data = res.data
    if (Array.isArray(data?.orders)) {
        const activeKdsOrders = data.orders.map((o: any) => {
          const pendingStatus = pendingOrderStatusUpdates.get(o.id)
          const resolvedStatus = pendingStatus !== undefined ? pendingStatus : (o.status === 'processing' ? 'preparing' : o.status)
          const rawT = o.table?.table_number || o.tableCode || ''
          const num = rawT.replace(/[^0-9]/g, '')
          const resolvedTableCode = num ? `T-${num.padStart(2, '0')}` : rawT

          return {
            id: o.id,
            orderNumber: o.order_number || o.orderNumber,
            outletId: o.outlet_id,
            tableId: o.table_id,
            tableCode: resolvedTableCode,
            source: o.source || 'manual_kasir',
            customerName: o.customer_name || 'Pelanggan Manual',
            status: resolvedStatus,
            paymentStatus: o.payment_status || 'unpaid',
            paymentMethod: o.payments?.[0]?.payment_method || 'cash',
            items: (o.items || []).map((it: any) => {
              const pendingItemStatus = pendingItemUpdates.get(it.id)
              const itemStatus = pendingItemStatus !== undefined ? pendingItemStatus : (it.status === 'ready' || it.status === 'served' ? it.status : 'preparing')
              return {
                id: it.id,
                menuItemId: it.menu_item_id,
                menuItemName: it.item_name_snapshot || 'Menu Item',
                quantity: it.quantity,
                unitPrice: it.base_price_snapshot || 0,
                subtotal: it.subtotal || 0,
                notes: it.notes,
                selectedOptions: (it.options || []).map((opt: any) => ({
                  optionName: opt.option_name_snapshot || opt.name,
                  priceModifier: opt.price_modifier_snapshot || 0,
                })),
                status: itemStatus,
              }
            }),
            subtotal: o.total_amount || 0,
            taxAmount: 0,
            discountAmount: o.discount_amount || 0,
            totalAmount: o.final_amount || o.total_amount || 0,
            createdAt: o.created_at || new Date().toISOString(),
            updatedAt: o.updated_at || new Date().toISOString(),
          }
        })

        // In-place reconciliation agar kartu tidak flicker
        const currentOrders = posStore.orders
        const activeMap = new Map<string, any>()
        activeKdsOrders.forEach((ao: any) => activeMap.set(ao.id, ao))

        const updatedOrders: any[] = []
        const handledIds = new Set<string>()

        for (const ord of currentOrders) {
          if (activeMap.has(ord.id)) {
            handledIds.add(ord.id)
            const fresh = activeMap.get(ord.id)!
            if (ord.status !== fresh.status) ord.status = fresh.status
            if (ord.paymentStatus !== fresh.paymentStatus) ord.paymentStatus = fresh.paymentStatus
            if (ord.tableCode !== fresh.tableCode) ord.tableCode = fresh.tableCode

            if (fresh.items && fresh.items.length) {
              if (ord.items.length !== fresh.items.length) {
                ord.items = fresh.items
              } else {
                for (let idx = 0; idx < ord.items.length; idx++) {
                  const ci = ord.items[idx]
                  const fi = fresh.items[idx]
                  if (ci && fi && ci.id === fi.id) {
                    if (ci.status !== fi.status) ci.status = fi.status
                  } else {
                    ord.items = fresh.items
                    break
                  }
                }
              }
            }
            updatedOrders.push(ord)
          } else if (ord.status === 'completed' || ord.status === 'cancelled' || ord.status === 'expired') {
            updatedOrders.push(ord)
          }
        }

        for (const fresh of activeKdsOrders) {
          if (!handledIds.has(fresh.id)) {
            updatedOrders.push(fresh)
          }
        }

        const isListChanged =
          updatedOrders.length !== currentOrders.length ||
          updatedOrders.some((o, idx) => o.id !== currentOrders[idx]?.id)

        if (isListChanged) {
          posStore.orders = updatedOrders
        }
      }
  } catch (err: any) {
    console.warn('Fetch KDS API failed, using store data:', err?.message)
  } finally {
    if (!isBackground) {
      isLoading.value = false
    }
  }
}

const parseDateSafe = (d: any): number => {
  if (!d) return 0
  if (typeof d === 'string') {
    const safeStr = d.includes(' ') && !d.includes('T') ? d.replace(' ', 'T') : d
    const t = new Date(safeStr).getTime()
    return isNaN(t) ? 0 : t
  }
  return new Date(d).getTime() || 0
}

// Active Kitchen Queue Orders (Filtered by Top Bar status if present, strictly FIFO)
const kdsOrders = computed(() => {
  const statusFilter = route.query.status as string | undefined

  let list = [...posStore.orders]
    .filter(o => ['confirmed', 'preparing', 'cooking', 'ready', 'awaiting_payment'].includes(o.status))

  if (statusFilter === 'confirmed') {
    list = list.filter(o => o.status === 'confirmed' || o.status === 'awaiting_payment')
  } else if (statusFilter === 'preparing') {
    list = list.filter(o => o.status === 'preparing' || (o.status as any) === 'cooking')
  } else if (statusFilter === 'ready') {
    list = list.filter(o => o.status === 'ready')
  }

  return list.sort((a, b) => parseDateSafe(a.createdAt) - parseDateSafe(b.createdAt))
})

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

const now = ref(Date.now())

const getElapsedMinutes = (isoDate: string) => {
  if (!isoDate) return 0
  const t = parseDateSafe(isoDate)
  if (t === 0) return 0
  return Math.max(1, Math.floor((now.value - t) / 60000))
}

const getElapsedTime = (isoDate: string) => {
  return `${getElapsedMinutes(isoDate)} mnt`
}

// AC-KDS-03: SLA Warning Badge Variant using existing AppBadge design system
const getSlaVariant = (isoDate: string): 'primary' | 'warning' | 'danger' => {
  const m = getElapsedMinutes(isoDate)
  if (m >= 15) return 'danger'   // Merah (DashStack danger)
  if (m >= 10) return 'warning'  // Oranye/Kuning (DashStack warning)
  return 'primary'               // Biru (DashStack primary)
}

const partialOrders = ref<Set<string>>(new Set())

const isPartialServed = (orderId: string) => partialOrders.value.has(orderId)

const getOrderReadyCounts = (order: any) => {
  const readyCount = (order.items || []).filter((i: any) => i.status === 'ready' || i.status === 'served').length
  const totalCount = (order.items || []).length
  return {
    readyCount,
    totalCount,
    isAllReady: readyCount === totalCount && totalCount > 0,
    isPartialReady: readyCount > 0 && readyCount < totalCount
  }
}

const markAsPartialReady = async (order: any) => {
  partialOrders.value.add(order.id)
  const readyItems = (order.items || []).filter((i: any) => i.status === 'ready')

  for (const it of readyItems) {
    it.status = 'served'
    pendingItemUpdates.set(it.id, 'served')
  }

  try {
    await Promise.all(
      readyItems.map((it: any) =>
        apiClient.patch(`/kds/items/${it.id}/status`, { status: 'served' }).catch(() => {})
      )
    )
  } catch (err: any) {
    console.warn('markAsPartialReady API error:', err?.message)
  } finally {
    setTimeout(() => {
      readyItems.forEach((it: any) => pendingItemUpdates.delete(it.id))
    }, 3500)
  }
}

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

const getStatusPillConfig = (order: any) => {
  if (order.status === 'ready') {
    return {
      label: 'Ready to Serve',
      icon: 'check_circle',
      bgClass: 'bg-[#DCFCE7] dark:bg-[#16A34A]/20 text-[#16A34A] dark:text-[#4ADE80]',
    }
  }
  if (order.status === 'preparing' && isPartialServed(order.id)) {
    return {
      label: 'Partial Saji',
      icon: 'restaurant',
      bgClass: 'bg-[#FFA756]/15 dark:bg-[#FFA756]/20 text-[#FFA756]',
    }
  }
  switch (order.status) {
    case 'preparing':
    case 'cooking':
      return {
        label: 'Preparing',
        icon: 'skillet',
        bgClass: 'bg-[#FEF3C7] dark:bg-[#D97706]/20 text-[#D97706] dark:text-[#FBBF24]',
      }
    case 'completed':
      return {
        label: 'Completed',
        icon: 'task_alt',
        bgClass: 'bg-[#4880FF]/15 dark:bg-[#4880FF]/20 text-[#4880FF] dark:text-[#93C5FD]',
      }
    default:
      return {
        label: 'New Order',
        icon: 'notifications_active',
        bgClass: 'bg-[#EBF1FF] dark:bg-[#4880FF]/20 text-[#4880FF] dark:text-[#93C5FD]',
      }
  }
}

const toggleItemReady = async (order: any, item: any) => {
  if (order.status !== 'preparing') return
  if (item.status === 'served') return // Item sudah disajikan parsial, terkunci & tidak dapat di-unchecklist
  const nextStatus = item.status === 'ready' ? 'cooking' : 'ready'
  const newUiStatus = nextStatus === 'ready' ? 'ready' : 'preparing'

  // Instant optimistic update in local state & posStore lock (prevent background poll overwrite)
  item.status = newUiStatus
  pendingItemUpdates.set(item.id, newUiStatus)
  posStore.setPendingKdsItem?.(item.id, newUiStatus)

  try {
    const res = await apiClient.patch(`/kds/items/${item.id}/status`, { status: nextStatus })
    if (res.data?.item?.status) {
      const confirmed = res.data.item.status === 'ready' || res.data.item.status === 'served' ? res.data.item.status : 'preparing'
      item.status = confirmed
      posStore.setPendingKdsItem?.(item.id, confirmed)
    }
  } catch (err: any) {
    console.warn('Update item status API error:', err?.message)
  } finally {
    // Retain protection for 4.5 seconds to avoid any concurrent background GET race condition
    setTimeout(() => {
      pendingItemUpdates.delete(item.id)
    }, 4500)
  }
}

const markAsPreparing = async (order: any) => {
  pendingOrderStatusUpdates.set(order.id, 'preparing')
  order.status = 'preparing'
  order.items.forEach((i: any) => {
    if (i.status === 'pending') {
      i.status = 'preparing'
      pendingItemUpdates.set(i.id, 'preparing')
    }
  })
  try {
    await posStore.updateOrderStatus(order.id, 'preparing')
  } catch (err: any) {
    console.warn('markAsPreparing error:', err?.message)
  } finally {
    setTimeout(() => {
      pendingOrderStatusUpdates.delete(order.id)
      order.items.forEach((i: any) => pendingItemUpdates.delete(i.id))
    }, 3500)
  }
}

const markAsReady = async (order: any) => {
  partialOrders.value.delete(order.id)
  pendingOrderStatusUpdates.set(order.id, 'ready')
  order.status = 'ready'
  order.items.forEach((i: any) => {
    if (i.status !== 'served') {
      i.status = 'ready'
      pendingItemUpdates.set(i.id, 'ready')
    }
  })
  try {
    await posStore.updateOrderStatus(order.id, 'ready')
  } catch (err: any) {
    console.warn('markAsReady error:', err?.message)
  } finally {
    setTimeout(() => {
      pendingOrderStatusUpdates.delete(order.id)
      order.items.forEach((i: any) => pendingItemUpdates.delete(i.id))
    }, 3500)
  }
}

const markAsCompleted = async (order: any) => {
  pendingOrderStatusUpdates.set(order.id, 'completed')
  order.status = 'completed'
  try {
    await posStore.updateOrderStatus(order.id, 'completed')
  } catch (err: any) {
    console.warn('markAsCompleted error:', err?.message)
  } finally {
    setTimeout(() => {
      pendingOrderStatusUpdates.delete(order.id)
    }, 3500)
  }
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <!-- Header: KDS Order Queue (Fixed at top) -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">{{ pageTitle }} <span v-if="kdsOrders.length > 0" class="text-lg font-normal text-[#64748B] dark:text-[#94A3B8]">({{ kdsOrders.length }})</span></h1>
    </div>

    <!-- Scrollable Content Area: Strictly BELOW Header -->
    <div class="flex-1 overflow-y-auto min-h-0 pr-1 pb-8 [scrollbar-gutter:stable]">
      <!-- Skeleton Loading State (Mirip persis struktur kartu pesanan dapur) -->
      <div v-if="isLoading && posStore.orders.length === 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="n in 4"
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

            <!-- Order Summary Header -->
            <div class="flex items-center justify-between gap-2">
              <div class="h-4 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
              <div class="h-5 w-14 bg-[#E2E8F0] dark:bg-[#334155] rounded-full"></div>
            </div>

            <!-- Items List -->
            <div class="space-y-2.5 pt-1">
              <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-1">
                  <div class="w-4 h-4 rounded bg-[#E2E8F0] dark:bg-[#334155] shrink-0"></div>
                  <div class="h-3.5 bg-[#E2E8F0] dark:bg-[#334155] rounded w-28"></div>
                </div>
                <div class="h-3.5 w-6 bg-[#E2E8F0] dark:bg-[#334155] rounded shrink-0"></div>
              </div>
            </div>
          </div>

          <!-- Bottom Button Action Skeleton -->
          <div class="pt-5 mt-auto">
            <div class="h-10 bg-[#E2E8F0] dark:bg-[#334155] rounded-lg w-full"></div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="kdsOrders.length === 0"
        class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-6">
        <div class="relative flex items-center justify-center mb-4 sm:mb-5 pointer-events-none">
          <img :src="emptyOrderIllustration" alt="Tidak Ada Antrean"
            class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
        </div>
        <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-[#1E293B] dark:text-white tracking-tight">
          Whoops! :(
        </h3>
        <p
          class="text-sm font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
          {{ route.query.status === 'confirmed' ? 'Belum ada pesanan yang menunggu dimasak' : route.query.status === 'preparing' ? 'Belum ada pesanan yang sedang dimasak' : route.query.status === 'ready' ? 'Belum ada pesanan yang siap disajikan' : 'Belum ada antrean pesanan dapur saat ini' }}
        </p>
      </div>

      <!-- Live KDS Cards Grid (Exact Order Masuk Layout & Style) -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="order in kdsOrders" :key="order.id"
          class="bg-white dark:bg-[#273142] rounded-[14px] p-6 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col justify-between">
          <!-- Top Section -->
          <div>
            <!-- Header: Customer Name & Queue Number -->
            <div class="flex items-start justify-between gap-2">
              <h3 class="text-base font-bold text-[#1E293B] dark:text-white leading-tight truncate">
                {{ formatCustomerName(order.customerName) }}
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
              <!-- Order Header: Count & Elapsed Time -->
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-[#1E293B] dark:text-white">
                  Order <span class="text-[#94A3B8] font-normal">({{ order.items.length }})</span>
                </span>
                <AppBadge :variant="getSlaVariant(order.createdAt)" size="sm" rounded="full">
                  {{ getElapsedTime(order.createdAt) }}
                </AppBadge>
              </div>

              <!-- Items List (Clean Row without wrapper box) -->
              <div
                class="space-y-1.5 pt-0.5 max-h-36 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <div v-for="item in order.items" :key="item.id"
                  @click="order.status === 'preparing' && item.status !== 'served' ? toggleItemReady(order, item) : null"
                  :title="item.status === 'served' ? 'Sudah disajikan ke meja (tidak dapat diubah)' : order.status !== 'preparing' ? 'Klik Masak terlebih dahulu' : ''"
                  :class="[
                    'flex items-center justify-between gap-2 text-xs md:text-sm py-1 transition-colors',
                    item.status === 'served'
                      ? 'cursor-not-allowed text-[#16A34A] dark:text-[#4ADE80]'
                      : order.status !== 'preparing'
                        ? 'cursor-not-allowed opacity-60 text-[#64748B] dark:text-[#CBD5E1]'
                        : 'cursor-pointer',
                    item.status === 'ready'
                      ? 'text-[#00B69B] dark:text-[#4ADE80]'
                      : order.status === 'preparing' && item.status !== 'served'
                        ? 'text-[#64748B] dark:text-[#CBD5E1] hover:text-[#1E293B] dark:hover:text-white'
                        : ''
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

                  <span v-if="item.notes" class="text-xs text-[#FCBE2D] italic shrink-0 max-w-[90px] truncate">
                    {{ item.notes }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Status Pill Action -->
          <div
            class="pt-4 mt-2 flex items-center justify-between border-t border-dashed border-[#E2E8F0] dark:border-[#334155]">
            <div :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-2xs',
              getStatusPillConfig(order).bgClass
            ]">
              <AppIcon :name="getStatusPillConfig(order).icon" :size="14" />
              <span>{{ getStatusPillConfig(order).label }}</span>
            </div>

            <!-- Action Button / Status Pickup di Kanan -->
            <div class="flex items-center gap-1.5">
              <AppButton v-if="order.status === 'confirmed' || order.status === 'awaiting_payment'" variant="primary" size="md" @click="markAsPreparing(order)"
                :title="t('kds.startCooking')">
                {{ t('kds.startCooking') }}
              </AppButton>
              <template v-else-if="order.status === 'preparing'">
                <!-- Jika SEMUA item tercentang: Siap Saji -->
                <AppButton v-if="getOrderReadyCounts(order).isAllReady" variant="success" size="md" @click="markAsReady(order)"
                  :title="t('kds.markReady')">
                  {{ t('kds.markReady') }}
                </AppButton>
                <!-- Jika SEBAGIAN item tercentang: Partial Saji -->
                <AppButton v-else-if="getOrderReadyCounts(order).isPartialReady" variant="primary" class="!bg-[#FFA756] hover:!bg-[#F59338] text-white" size="md" @click="markAsPartialReady(order)"
                  title="Sajikan menu yang sudah selesai dimasak">
                  {{ isPartialServed(order.id) ? 'Disajikan' : 'Partial Saji' }} ({{ getOrderReadyCounts(order).readyCount }}/{{ getOrderReadyCounts(order).totalCount }})
                </AppButton>
                <!-- Jika BELUM ada item tercentang: Disabled Siap Saji -->
                <AppButton v-else variant="secondary" size="md" disabled title="Centang menu yang telah selesai dimasak">
                  Siap Saji
                </AppButton>
              </template>
              <span v-else-if="order.status === 'ready'"
                class="px-3 py-1.5 rounded-lg text-xs font-bold shadow-2xs bg-[#FEF3C7] dark:bg-[#D97706]/20 text-[#D97706] dark:text-[#FBBF24]">
                Belum Diambil
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
