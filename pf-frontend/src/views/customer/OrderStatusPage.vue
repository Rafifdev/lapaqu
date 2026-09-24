<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import { useCustomerI18n } from '@/i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppDropdownMotion from '@/components/ui/AppDropdownMotion.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import QRCode from 'qrcode'
import apiClient from '@/services/api'
import qrisSvg from '@/assets/payment_method/QRIS Black.svg'
import { getBankVAInfo } from '@/composables/useBankVA'
import { getEcho } from '@/services/echo'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const { formatCurrency } = useFormat()
const { t, translate, locale } = useCustomerI18n()

const outletId = computed(() => (route.params.outletId as string) || cartStore.outletId || '')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || '')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)

// Real Multi-Order State
const activeResolvedOrderId = ref('')
const orderData = ref<any>(null)
const ordersList = ref<any[]>([])
const isLoading = ref(true)
const expandedOrders = ref<Record<string, boolean>>({})

const qrisDataUrls = ref<Record<string, string>>({})
const isSimulatingId = ref<string | null>(null)
const isCancellingId = ref<string | null>(null)
const copiedOrderIdId = ref<string | null>(null)
const copiedVaId = ref<string | null>(null)

// Realtime WebSocket Channels via Reverb
const activeTableId = ref<string>('')
const subscribedChannels = ref<string[]>([])

const setupRealtimeEcho = () => {
  const echo = getEcho()
  if (!echo) return

  // Subscribe ke channel Meja (table.{tableId})
  if (activeTableId.value && !subscribedChannels.value.includes(`table.${activeTableId.value}`)) {
    const tableChan = `table.${activeTableId.value}`
    echo.channel(tableChan)
      .listen('.order.status.updated', (e: any) => {
        console.log('[Echo Realtime] Order status updated for table:', e)
        loadOrderStatus()
      })
      .listen('.order.paid', (e: any) => {
        console.log('[Echo Realtime] Order paid for table:', e)
        loadOrderStatus()
      })
      .listen('.kitchen.item.status.updated', (e: any) => {
        console.log('[Echo Realtime] Kitchen item status updated for table:', e)
        loadOrderStatus()
      })
    subscribedChannels.value.push(tableChan)
  }

  // Subscribe ke channel setiap Order aktif (order.{orderId})
  ordersList.value.forEach((ord) => {
    if (ord?.id && !subscribedChannels.value.includes(`order.${ord.id}`)) {
      const orderChan = `order.${ord.id}`
      echo.channel(orderChan)
        .listen('.order.status.updated', (e: any) => {
          console.log('[Echo Realtime] Order status updated for order:', e)
          loadOrderStatus()
        })
        .listen('.order.paid', (e: any) => {
          console.log('[Echo Realtime] Order paid for order:', e)
          loadOrderStatus()
        })
        .listen('.kitchen.item.status.updated', (e: any) => {
          console.log('[Echo Realtime] Kitchen item status updated for order:', e)
          loadOrderStatus()
        })
      subscribedChannels.value.push(orderChan)
    }
  })
}

const teardownRealtimeEcho = () => {
  const echo = getEcho()
  if (!echo) return
  subscribedChannels.value.forEach((chan) => {
    try {
      echo.leave(chan)
    } catch (e) {}
  })
  subscribedChannels.value = []
}

let pollingInterval: any = null
let vaTimerInterval: any = null

const steps = computed(() => [
  { 
    label: locale.value === 'en' ? 'Order Received' : 'Pesanan Diterima', 
    desc: locale.value === 'en' ? 'Kitchen received your order' : 'Dapur telah menerima pesanan Anda' 
  },
  { 
    label: locale.value === 'en' ? 'Cooking' : 'Menyiapkan Pesanan', 
    desc: locale.value === 'en' ? 'Chef is cooking your dishes' : 'Koki sedang memasak & menyiapkan hidangan' 
  },
  { 
    label: locale.value === 'en' ? 'Ready to Serve' : 'Pesanan Siap', 
    desc: locale.value === 'en' ? 'Dishes are ready for your table' : 'Hidangan siap disajikan ke meja Anda' 
  },
  { 
    label: locale.value === 'en' ? 'Completed' : 'Pesanan Selesai', 
    desc: locale.value === 'en' ? 'Order completed and enjoyed' : 'Pesanan telah selesai dinikmati' 
  },
])

// Expand / Collapse Helpers
// Default expand: jika cuma ada 1 pesanan langsung expand, jika lebih dari 1 maka hanya pesanan terbaru yang default expand
const isExpanded = (id: string, index?: number) => {
  if (expandedOrders.value[id] !== undefined) {
    return expandedOrders.value[id]
  }
  const total = ordersList.value.length > 0 ? ordersList.value.length : (orderData.value ? 1 : 0)
  if (total <= 1) {
    return true
  }
  return index !== undefined ? index === total - 1 : false
}

const toggleOrder = (id: string, index?: number) => {
  const current = isExpanded(id, index)
  expandedOrders.value[id] = !current
}

// Order Status Helpers
const getOrderStageLabel = (ord: any) => {
  if (!ord) return 'Pesanan Diterima'
  if (ord.status === 'expired' || ord.payment_status === 'expired') return 'Waktu Habis'
  if (ord.status === 'cancelled' || ord.payment_status === 'cancelled') return 'Pesanan Dibatalkan'
  if (ord.status === 'confirmed') return 'Pesanan Diterima'
  if (ord.status === 'preparing') return 'Menyiapkan Pesanan'
  if (ord.status === 'ready') return 'Pesanan Siap'
  if (ord.status === 'completed') return 'Pesanan Selesai'
  const stepIdx = getOrderCurrentStep(ord)
  if (steps.value[stepIdx]) return steps.value[stepIdx].label
  return 'Pesanan Diterima'
}

const getOrderCurrentStep = (ord: any) => {
  if (!ord) return 0
  const status = ord.status

  if (status === 'confirmed') return 0
  if (status === 'preparing') return 1
  if (status === 'ready') return 2
  if (status === 'completed') return 3
  return 0
}

const getOrderActiveStepInfo = (ord: any) => {
  if (!ord) return { title: 'Memuat status...', desc: '', isDone: false, isFailed: false }
  const payStatus = ord.payment_status
  const status = ord.status

  if (status === 'expired' || payStatus === 'expired') {
    return { title: 'Waktu Pembayaran Habis', desc: 'Pesanan dibatalkan otomatis', isDone: false, isFailed: true }
  }
  if (status === 'cancelled' || payStatus === 'cancelled') {
    return { title: 'Pesanan Dibatalkan', desc: 'Transaksi telah dibatalkan', isDone: false, isFailed: true }
  }
  if (status === 'confirmed') {
    return { title: 'Pesanan Diterima', desc: 'Dapur telah menerima pesanan Anda', isDone: false, isFailed: false }
  }
  if (status === 'preparing') {
    return { title: 'Menyiapkan Pesanan', desc: 'Koki sedang memasak & menyiapkan hidangan', isDone: false, isFailed: false }
  }
  if (status === 'ready') {
    return { title: 'Pesanan Siap Disajikan', desc: 'Hidangan siap diantar ke meja Anda', isDone: false, isFailed: false }
  }
  if (status === 'completed') {
    return { title: 'Pesanan Selesai', desc: 'Terima kasih atas pesanan Anda!', isDone: true, isFailed: false }
  }
  return { title: 'Pesanan Sedang Diproses', desc: 'Memproses pesanan Anda', isDone: false, isFailed: false }
}

const isOrderQris = (ord: any) => {
  return ord?.payments?.[0]?.payment_method === 'qris'
}

const isOrderCash = (ord: any) => {
  return ord?.payments?.[0]?.payment_method === 'cash'
}

const isOrderVa = (ord: any) => {
  const method = ord?.payments?.[0]?.payment_method || ''
  return method.startsWith('va_') || method === 'virtual_account'
}

const getOrderVaPayment = (ord: any) => {
  return ord?.payments?.find((p: any) => p.payment_method?.startsWith('va_')) || ord?.payments?.[0]
}

const getOrderVaInfo = (ord: any) => {
  const vaPay = getOrderVaPayment(ord)
  return getBankVAInfo(vaPay?.payment_method || vaPay?.bank_code || '')
}

const getOrderPaymentMethodLabel = (ord: any) => {
  const p = ord?.payments?.[0]
  if (!p) return '-'
  if (p.payment_method === 'cash') return 'Tunai di Kasir'
  if (p.payment_method === 'qris') return 'QRIS'
  if (p.payment_method?.startsWith('va_')) {
    const bank = getBankVAInfo(p.payment_method)
    return `${(bank?.shortName || bank?.code || p.bank_code || 'VA').toUpperCase()} VA`
  }
  return p.payment_method?.toUpperCase() || '-'
}

const getOrderSubtotal = (ord: any) => {
  if (ord?.items && ord.items.length > 0) {
    return ord.items.reduce((acc: number, item: any) => acc + (Number(item.subtotal) || 0), 0)
  }
  if (ord?.subtotal) return Number(ord.subtotal)
  const total = Number(ord?.total_amount || 0)
  if (total > 0) {
    return Math.round(total / 1.1)
  }
  return 0
}

const getOrderTaxFee = (ord: any) => {
  if (ord?.tax_amount !== undefined) return Number(ord.tax_amount)
  const sub = getOrderSubtotal(ord)
  return sub > 0 ? Math.round(sub * 0.1) : 0
}

const getOrderServiceFee = (ord: any) => {
  if (ord?.service_fee !== undefined) return Number(ord.service_fee)
  const total = Number(ord?.total_amount || ord?.final_amount || 0)
  const sub = getOrderSubtotal(ord)
  const tax = getOrderTaxFee(ord)
  const disc = Number(ord?.discount_amount || 0)
  const diff = total - (sub + tax - disc)
  return Math.max(0, diff)
}

const formatOrderTime = (createdAt: string) => {
  const d = createdAt ? new Date(createdAt) : new Date()
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace(':', '.')
}

const formatOrderDate = (createdAt: string) => {
  const d = createdAt ? new Date(createdAt) : new Date()
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const copyToClipboard = (text: string, type: 'va' | 'orderId', orderId: string) => {
  if (!text) return
  navigator.clipboard?.writeText(text)
  if (type === 'va') {
    copiedVaId.value = orderId
    setTimeout(() => { copiedVaId.value = null }, 2000)
  } else {
    copiedOrderIdId.value = orderId
    setTimeout(() => { copiedOrderIdId.value = null }, 2000)
  }
}

const handleCancelTransfer = async (ord: any) => {
  if (!ord?.id || isCancellingId.value) return
  isCancellingId.value = ord.id
  try {
    await apiClient.post(`/public/orders/${ord.id}/cancel`)
    if (localStorage.getItem('lapaqu_active_order_id') === ord.id) {
      localStorage.removeItem('lapaqu_active_order_id')
    }
    ordersList.value = ordersList.value.filter((o: any) => o.id !== ord.id)
    if (ordersList.value.length > 0) {
      orderData.value = ordersList.value[ordersList.value.length - 1]
      localStorage.setItem('lapaqu_active_order_id', orderData.value.id)
    } else {
      orderData.value = null
      activeResolvedOrderId.value = ''
      localStorage.removeItem('lapaqu_active_order_id')
      localStorage.removeItem('lapaqu_viewed_status_fingerprint')
    }
    await loadOrderStatus()
  } catch (err) {
    console.error('Failed to cancel order:', err)
  } finally {
    isCancellingId.value = null
  }
}

const handleSimulatePayment = async (ord: any) => {
  if (!ord?.id || isSimulatingId.value) return
  isSimulatingId.value = ord.id
  try {
    await apiClient.post(`/public/orders/${ord.id}/simulate-pay`)
    await loadOrderStatus()
  } catch (err) {
    console.error('Failed to simulate pay:', err)
  } finally {
    isSimulatingId.value = null
  }
}

const loadOrderStatus = async () => {
  const targetId = (route.query.orderId as string) || localStorage.getItem('lapaqu_active_order_id') || activeResolvedOrderId.value

  try {
    let activeRes: any = null
    try {
      activeRes = await apiClient.get('/public/orders/active', {
        params: {
          table_code: tableCode.value,
          outlet_id: outletId.value,
        },
      })
    } catch (e) {
      console.error('Error fetching active orders:', e)
    }

    let list: any[] = []
    if (activeRes?.data?.orders && Array.isArray(activeRes.data.orders) && activeRes.data.orders.length > 0) {
      list = activeRes.data.orders
    } else if (activeRes?.data?.order) {
      list = [activeRes.data.order]
    }

    // Filter status yang valid: HANYA yang SUDAH DIBAYAR & SUDAH DITERIMA (TIDAK ADA MENUNGGU BAYAR/PENDING/BATAL)
    const validStatuses = ['confirmed', 'processing', 'preparing', 'cooking', 'ready', 'completed']
    list = list.filter((o: any) =>
      validStatuses.includes(o.status) &&
      o.payment_status === 'paid' &&
      o.status !== 'cancelled' &&
      o.status !== 'expired' &&
      o.status !== 'pending_payment'
    )

    // Jika targetId spesifik ada tapi belum masuk ke list meja, ambil order tersebut HANYA jika sudah dibayar & diterima
    if (targetId && !list.some((o: any) => o.id === targetId)) {
      try {
        const singleRes = await apiClient.get(`/public/orders/${targetId}/status`)
        const singleOrder = singleRes?.data?.order
        if (singleOrder) {
          if (
            validStatuses.includes(singleOrder.status) &&
            singleOrder.payment_status === 'paid' &&
            singleOrder.status !== 'cancelled' &&
            singleOrder.status !== 'expired' &&
            singleOrder.status !== 'pending_payment'
          ) {
            list.push(singleOrder)
          } else {
            // Jika pesanan masih belum bayar atau dibatalkan, bersihkan dari active order
            if (localStorage.getItem('lapaqu_active_order_id') === targetId) {
              localStorage.removeItem('lapaqu_active_order_id')
            }
          }
        }
      } catch (e) {}
    }

    if (list.length > 0) {
      // Urutkan ascending: Pesanan #1 adalah pesanan pertama, Pesanan #2 adalah pesanan kedua, dst.
      list.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
      ordersList.value = list
      orderData.value = list[list.length - 1]
      activeResolvedOrderId.value = orderData.value.id
      activeTableId.value = list[0]?.table_id || list[0]?.table?.id || ''
      localStorage.setItem('lapaqu_active_order_id', orderData.value.id)
      const viewedFp = list.map((o: any) => `${o.id}:${o.status}:${o.payment_status}`).join('|')
      localStorage.setItem('lapaqu_viewed_status_fingerprint', viewedFp)
      setupRealtimeEcho()
    } else {
      ordersList.value = []
      orderData.value = null
      activeResolvedOrderId.value = ''
      localStorage.removeItem('lapaqu_active_order_id')
      localStorage.removeItem('lapaqu_viewed_status_fingerprint')
    }
  } catch (err) {
    console.error('Failed to load order status:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadOrderStatus()
  pollingInterval = setInterval(loadOrderStatus, 3000)
})

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval)
  if (vaTimerInterval) clearInterval(vaTimerInterval)
  teardownRealtimeEcho()
})
</script>

<template>
  <div class="py-1 select-none max-w-xl mx-auto">
    <!-- 0. SKELETON LOADING STATE -->
    <div v-if="isLoading" class="space-y-5">
      <div class="text-center space-y-3">
        <div class="w-12 h-12 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mx-auto" />
        <div class="h-5 w-48 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mx-auto" />
        <div class="flex items-center justify-center gap-2">
          <div class="h-6 w-24 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="h-6 w-20 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
        <div class="h-3 w-56 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mx-auto mt-2" />
      </div>

      <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <div class="space-y-3">
        <div class="h-4 w-32 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="space-y-3 pt-1">
          <div v-for="n in 3" :key="n" class="flex items-center gap-3">
            <div class="w-6 h-6 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
            <div class="h-3.5 w-44 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
        </div>
      </div>

      <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <div class="space-y-3">
        <div class="h-4 w-36 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="space-y-3 pt-1">
          <div v-for="n in 2" :key="n" class="flex items-center justify-between">
            <div class="space-y-1">
              <div class="h-3.5 w-36 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
              <div class="h-2.5 w-24 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            </div>
            <div class="h-4 w-16 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
        </div>
      </div>
    </div>

    <!-- JIKA TIDAK ADA PESANAN AKTIF -->
    <div v-else-if="ordersList.length === 0 && !orderData" class="py-12 bg-white dark:bg-[#273142] rounded-3xl p-6 border border-[#E2E8F0] dark:border-[#334155] shadow-xs text-center space-y-3">
      <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-[#1E293B] text-slate-400 flex items-center justify-center mx-auto">
        <AppIcon name="receipt_long" :size="32" />
      </div>
      <h3 class="text-base font-bold text-[#1E293B] dark:text-white">{{ locale === 'en' ? 'No Active Orders' : 'Belum Ada Pesanan Aktif' }}</h3>
      <p class="text-xs text-[#64748B] dark:text-[#94A3B8] max-w-xs mx-auto">
        {{ locale === 'en' ? 'Please select items from menu and place your order to track here.' : 'Silakan pilih hidangan di menu dan lakukan pemesanan untuk melihat status di sini.' }}
      </p>
      <AppButton @click="router.push(menuUrl)" variant="primary" class="mt-2 !rounded-full">
        {{ locale === 'en' ? 'Browse Menu Now' : 'Lihat Menu Sekarang' }}
      </AppButton>
    </div>

    <!-- JIKA ADA PESANAN AKTIF: LOOP SELURUH PROSES PESANAN DENGAN PEMBATAS HORIZONTAL DASHED -->
    <div v-else class="flex flex-col">
      <div
        v-for="(ord, index) in (ordersList.length > 0 ? ordersList : (orderData ? [orderData] : []))"
        :key="ord.id || index"
        class="flex flex-col"
      >
        <!-- PEMBATAS HORIZONTAL DASHED DI SETIAP ANTAR PROSES PESANAN -->
        <div v-if="index > 0" class="my-6 border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

        <!-- Header Nomor Pesanan di Bagian Atas Progression Bar (Expand / Collapse) -->
        <div
          @click="toggleOrder(ord.id || String(index), index)"
          class="flex flex-col py-1 cursor-pointer select-none"
        >
          <!-- Baris Utama: Judul Pesanan & Icon Expand (Posisi Selalu Terkunci Sempurna) -->
          <div class="flex items-center justify-between h-8">
            <span class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white leading-none">
              {{ locale === 'en' ? `Order #${index + 1}` : `Pesanan #${index + 1}` }}
              <span
                v-if="ord.customer_name && !ord.customer_name.startsWith('Pelanggan Meja')"
                class="text-xs sm:text-sm font-semibold text-[#64748B] dark:text-[#94A3B8] ml-1"
              >
                ({{ ord.customer_name }})
              </span>
            </span>
            <button
              type="button"
              class="w-8 h-8 rounded-full flex items-center justify-center text-[#64748B] dark:text-[#94A3B8] hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            >
              <AppIcon
                name="expand_more"
                :size="22"
                class="transition-transform duration-200"
                :class="{ 'rotate-180': isExpanded(ord.id || String(index), index) }"
              />
            </button>
          </div>

          <!-- Subteks proses pesanan (hanya muncul ketika di-collapse saja, hilang ketika di-expand) -->
          <div
            v-if="!isExpanded(ord.id || String(index), index)"
            class="flex items-center gap-1.5 mt-1 text-xs font-medium text-[#64748B] dark:text-[#94A3B8]"
          >
            <AppSpinner
              v-if="ord.status !== 'completed' && ord.status !== 'cancelled' && ord.status !== 'expired'"
              :size="13"
              color="text-[#64748B] dark:text-[#94A3B8]"
            />
            <AppIcon
              v-else-if="ord.status === 'completed'"
              name="check_circle"
              :size="14"
              class="text-[#00B69B]"
            />
            <AppIcon
              v-else
              name="cancel"
              :size="14"
              class="text-rose-500"
            />
            <span>{{ getOrderStageLabel(ord) }}</span>
          </div>
        </div>

        <!-- Konten Proses Pesanan (Expand / Collapse dari Header Nomor dengan Motion V) -->
        <AppDropdownMotion :show="isExpanded(ord.id || String(index), index)" type="collapse">
          <div class="pt-5 space-y-5">
            <!-- 0. HORIZONTAL PROGRESSIVE STEPPER (Progression Bar) -->
            <div class="px-1 pt-1 pb-1 select-none">
              <div class="flex items-start justify-between relative">
                <template v-for="(step, idx) in steps" :key="idx">
                  <div class="flex flex-col items-center text-center z-10 w-16 sm:w-20 shrink-0">
                    <div :class="[
                      'w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 shadow-xs',
                      idx < getOrderCurrentStep(ord)
                        ? 'bg-[#4880FF] text-white'
                        : (idx === getOrderCurrentStep(ord)
                          ? (getOrderActiveStepInfo(ord).isFailed
                              ? 'bg-rose-500 text-white ring-4 ring-rose-500/20 shadow-md scale-105'
                              : 'bg-[#4880FF] text-white ring-4 ring-[#4880FF]/20 shadow-md scale-105')
                          : 'bg-white dark:bg-[#1E293B] border-2 border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500')
                    ]">
                      <AppIcon v-if="idx < getOrderCurrentStep(ord)" name="check" :size="15" />
                      <template v-else-if="idx === getOrderCurrentStep(ord)">
                        <AppIcon v-if="getOrderActiveStepInfo(ord).isFailed" name="close" :size="15" />
                        <AppIcon v-else-if="getOrderActiveStepInfo(ord).isDone" name="check" :size="15" />
                        <AppSpinner v-else :size="14" color="text-white" />
                      </template>
                      <span v-else class="text-[10px] sm:text-xs">{{ idx + 1 }}</span>
                    </div>

                    <p :class="[
                      'text-[10px] sm:text-xs mt-1.5 leading-tight transition-colors',
                      idx <= getOrderCurrentStep(ord)
                        ? 'font-bold text-[#1E293B] dark:text-white'
                        : 'font-medium text-[#64748B] dark:text-[#94A3B8]'
                    ]">
                      {{ step.label }}
                    </p>
                  </div>

                  <div v-if="idx < steps.length - 1" class="flex-1 mt-3.5 sm:mt-4 -mx-2 sm:-mx-3">
                    <div class="h-0.5 w-full transition-colors duration-500"
                      :class="idx < getOrderCurrentStep(ord) ? 'bg-[#4880FF]' : 'bg-slate-200 dark:bg-slate-700'">
                    </div>
                  </div>
                </template>
              </div>
            </div>



            <!-- DASHED HORIZONTAL LINE SEBELUM DETAIL MENU / RINCIAN -->
            <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

            <!-- 4. DETAIL MENU DIPESAN (Rincian Pesanan) -->
            <div>
              <h4 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white mb-3.5">
                {{ locale === 'en' ? 'Order details' : 'Rincian pesanan' }}
              </h4>

              <div class="space-y-3.5 text-sm sm:text-base">
                <!-- Status -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Status</span>
                  <span
                    v-if="ord.payment_status === 'paid'"
                    class="font-bold text-emerald-500 inline-flex items-center gap-1.5"
                  >
                    <span class="leading-none">Berhasil</span>
                    <AppIcon name="check_circle" :size="18" class="text-emerald-500 shrink-0" />
                  </span>
                  <span
                    v-else-if="ord.status === 'expired' || ord.payment_status === 'expired'"
                    class="font-bold text-rose-500 dark:text-rose-400 inline-flex items-center gap-1.5"
                  >
                    <span class="leading-none">Hangus</span>
                    <AppIcon name="cancel" :size="18" class="text-rose-500 dark:text-rose-400 shrink-0" />
                  </span>
                  <span
                    v-else-if="ord.status === 'cancelled' || ord.payment_status === 'cancelled'"
                    class="font-bold text-rose-500 dark:text-rose-400 inline-flex items-center gap-1.5"
                  >
                    <span class="leading-none">Batal</span>
                    <AppIcon name="cancel" :size="18" class="text-rose-500 dark:text-rose-400 shrink-0" />
                  </span>
                  <span
                    v-else
                    class="font-bold text-emerald-500 inline-flex items-center gap-1.5"
                  >
                    <span class="leading-none">Berhasil</span>
                    <AppIcon name="check_circle" :size="18" class="text-emerald-500 shrink-0" />
                  </span>
                </div>

                <!-- Metode pembayaran -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Metode pembayaran</span>
                  <span class="font-bold text-[#1E293B] dark:text-white">
                    {{ getOrderPaymentMethodLabel(ord) }}
                  </span>
                </div>

                <!-- Waktu Transaksi -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Waktu</span>
                  <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                    {{ formatOrderTime(ord.created_at) }}
                  </span>
                </div>

                <!-- Tanggal Transaksi -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Tanggal</span>
                  <span class="font-medium text-[#1E293B] dark:text-white">
                    {{ formatOrderDate(ord.created_at) }}
                  </span>
                </div>

                <!-- ID transaksi (Nomor Pesanan) -->
                <div class="flex items-center justify-between gap-3">
                  <span class="text-[#64748B] dark:text-[#94A3B8] shrink-0">ID transaksi</span>
                  <div class="flex items-center gap-1.5 font-mono text-[#1E293B] dark:text-white min-w-0">
                    <span class="truncate max-w-[170px] sm:max-w-[210px] text-sm font-semibold">#{{ ord.order_number }}</span>
                    <button type="button" @click="copyToClipboard(ord.order_number, 'orderId', ord.id)"
                      class="text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] active:scale-90 transition-all p-0.5 cursor-pointer shrink-0"
                      title="Salin ID Transaksi">
                      <AppIcon :name="copiedOrderIdId === ord.id ? 'check' : 'content_copy'" :size="16" :class="copiedOrderIdId === ord.id ? 'text-emerald-500' : ''" />
                    </button>
                  </div>
                </div>

                <!-- Nama Pemesan jika ada -->
                <div v-if="ord.customer_name && !ord.customer_name.startsWith('Pelanggan Meja')" class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Nama pemesan</span>
                  <span class="font-bold text-[#1E293B] dark:text-white">
                    {{ ord.customer_name }}
                  </span>
                </div>

                <!-- Meja -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Meja</span>
                  <span class="font-medium text-[#1E293B] dark:text-white">
                    {{ ord.table?.table_number || tableCode }}
                  </span>
                </div>

                <!-- Dash Horizontal Line -->
                <div class="pt-1 border-t border-dashed border-slate-200 dark:border-[#334155]/80"></div>

                <!-- Daftar Item Menu yang Dipesan -->
                <div class="space-y-3">
                  <div v-for="item in ord.items" :key="item.id" class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="font-bold text-[#1E293B] dark:text-white text-sm sm:text-base leading-snug">
                        {{ item.quantity }}x {{ item.item_name_snapshot }}
                      </p>
                      <p v-if="item.options && item.options.length" class="text-xs text-[#4880FF] font-semibold mt-0.5">
                        {{ item.options.map((o: any) => o.option_name_snapshot).join(', ') }}
                      </p>
                      <p v-if="item.notes" class="text-xs text-[#64748B] dark:text-[#94A3B8] italic mt-0.5">
                        catatan: {{ item.notes }}
                      </p>
                    </div>
                    <span class="font-bold text-[#1E293B] dark:text-white tabular-nums text-sm sm:text-base shrink-0">
                      {{ formatCurrency(item.subtotal) }}
                    </span>
                  </div>
                </div>

                <!-- Dash Horizontal Line -->
                <div class="pt-1 border-t border-dashed border-slate-200 dark:border-[#334155]/80"></div>

                <!-- Jumlah (Subtotal Makanan/Minuman) -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Jumlah</span>
                  <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                    {{ formatCurrency(getOrderSubtotal(ord)) }}
                  </span>
                </div>

                <!-- Biaya layanan -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Biaya layanan</span>
                  <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                    {{ formatCurrency(getOrderServiceFee(ord)) }}
                  </span>
                </div>

                <!-- Pajak (PB1 10%) -->
                <div class="flex items-center justify-between">
                  <span class="text-[#64748B] dark:text-[#94A3B8]">Pajak (PB1 10%)</span>
                  <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                    {{ formatCurrency(getOrderTaxFee(ord)) }}
                  </span>
                </div>

                <!-- Potongan Promo jika ada -->
                <div v-if="Number(ord.discount_amount) > 0" class="flex items-center justify-between text-emerald-500">
                  <span>Potongan Promo</span>
                  <span class="font-medium tabular-nums">-{{ formatCurrency(ord.discount_amount) }}</span>
                </div>

                <!-- Garis Total (Dashed Line) -->
                <div class="pt-2 border-t border-dashed border-slate-200 dark:border-[#334155]/80"></div>

                <!-- Total Asli Pesanan -->
                <div class="flex items-center justify-between pt-0.5">
                  <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white">Total</span>
                  <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white tabular-nums">
                    {{ formatCurrency(ord.total_amount) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </AppDropdownMotion>
      </div>

      <!-- DASHED HORIZONTAL LINE SEBELUM TOMBOL -->
      <div class="mt-5 border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <!-- 5. Tombol Buat Pesanan Baru -->
      <div class="pt-5">
        <AppButton @click="router.push(menuUrl)" variant="outline" size="lg" block icon="add"
          class="!rounded-full border-[#4880FF] text-[#4880FF] hover:bg-[#4880FF]/10 font-bold">
          Buat Pesanan Baru
        </AppButton>
      </div>
    </div>
  </div>
</template>
