<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import QRCode from 'qrcode'
import apiClient from '@/services/api'
import qrisSvg from '@/assets/payment_method/QRIS Black.svg'
import { getBankVAInfo } from '@/composables/useBankVA'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const { formatCurrency } = useFormat()

const outletId = computed(() => (route.params.outletId as string) || 'outlet-001')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || 'M03')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)

// Real Order State
const activeResolvedOrderId = ref('')
const orderId = computed(() => (route.query.orderId as string) || localStorage.getItem('lapaqu_active_order_id') || activeResolvedOrderId.value || '')
const orderData = ref<any>(null)
const isLoading = ref(true)
const qrisDataUrl = ref<string>('')
const isSimulating = ref(false)
let pollingInterval: any = null

const steps = [
  { label: 'Pesanan Dibuat', desc: 'Pesanan telah masuk ke antrean sistem' },
  { label: 'Menunggu Bayar', desc: 'Selesaikan transaksi pembayaran' },
  { label: 'Menyiapkan Pesanan', desc: 'Koki sedang memasak & menyiapkan hidangan' },
  { label: 'Pesanan Siap', desc: 'Hidangan siap disajikan ke meja Anda' },
]

const currentStep = computed(() => {
  if (!orderData.value) return 1
  const payStatus = orderData.value.payment_status
  const status = orderData.value.status

  if (payStatus === 'unpaid') return 1
  if (status === 'confirmed') return 2
  if (status === 'preparing') return 2
  if (status === 'ready') return 3
  if (status === 'completed') return 4
  return 2
})

const isQrisPayment = computed(() => {
  return orderData.value?.payments?.[0]?.payment_method === 'qris'
})

const isCashPayment = computed(() => {
  return orderData.value?.payments?.[0]?.payment_method === 'cash'
})

const isVaPayment = computed(() => {
  const method = orderData.value?.payments?.[0]?.payment_method || ''
  return method.startsWith('va_') || method === 'virtual_account'
})

const activeVaPayment = computed(() => {
  return orderData.value?.payments?.find((p: any) => p.payment_method?.startsWith('va_')) || orderData.value?.payments?.[0]
})

const activeVaInfo = computed(() => {
  return getBankVAInfo(activeVaPayment.value?.payment_method || activeVaPayment.value?.bank_code || '')
})

const copiedVa = ref(false)
const copiedAmount = ref(false)
const copiedOrderId = ref(false)
const isRealtimeStatusOpen = ref(true)

const vaSecondsRemaining = ref(600)
let vaTimerInterval: any = null

const getStatusVaExpirationTime = (targetExpiresAt?: string) => {
  const createdAt = orderData.value?.created_at ? new Date(orderData.value.created_at).getTime() : Date.now()
  const maxVaExpiry = createdAt + 10 * 60 * 1000
  const rawExpiry = targetExpiresAt || activeVaPayment.value?.raw_payload?.expiration_date
  if (rawExpiry) {
    const expTime = new Date(rawExpiry).getTime()
    if (expTime - createdAt > 11 * 60 * 1000) {
      return new Date(maxVaExpiry).toISOString()
    }
    return rawExpiry
  }
  return new Date(maxVaExpiry).toISOString()
}

const startVaTimer = (targetExpiresAt?: string) => {
  if (vaTimerInterval) clearInterval(vaTimerInterval)
  const calc = () => {
    const expiry = getStatusVaExpirationTime(targetExpiresAt)
    if (expiry) {
      const diffMs = new Date(expiry).getTime() - Date.now()
      const secs = Math.max(0, Math.floor(diffMs / 1000))
      vaSecondsRemaining.value = secs
      if (secs <= 0 && orderData.value && (orderData.value.status === 'pending_payment' || orderData.value.status === 'awaiting_payment')) {
        apiClient.post(`/public/orders/${orderId.value}/expire`).catch(() => {})
      }
    } else if (vaSecondsRemaining.value > 0) {
      vaSecondsRemaining.value--
      if (vaSecondsRemaining.value <= 0 && orderData.value && (orderData.value.status === 'pending_payment' || orderData.value.status === 'awaiting_payment')) {
        apiClient.post(`/public/orders/${orderId.value}/expire`).catch(() => {})
      }
    }
  }
  calc()
  vaTimerInterval = setInterval(calc, 1000)
}

const vaCountdown = computed(() => {
  const mins = Math.floor(vaSecondsRemaining.value / 60)
  const secs = vaSecondsRemaining.value % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})

const isVaExpired = computed(() => {
  if (orderData.value?.payment_status === 'paid') return false
  if (orderData.value?.payment_status === 'expired' || orderData.value?.status === 'expired' || orderData.value?.status === 'cancelled') return true
  return vaSecondsRemaining.value <= 0
})

const vaDeadlineText = computed(() => {
  const expiry = getStatusVaExpirationTime()
  const d = new Date(expiry)
  if (isNaN(d.getTime())) return ''
  const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
  const dayName = days[d.getDay()]
  const date = d.getDate()
  const monthName = months[d.getMonth()]
  const year = d.getFullYear()
  let hours = d.getHours()
  const minutes = d.getMinutes().toString().padStart(2, '0')
  const ampm = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12 || 12
  return `${dayName}, ${date} ${monthName} ${year} ${hours}:${minutes} ${ampm}`
})

const transactionTime = computed(() => {
  const d = orderData.value?.created_at ? new Date(orderData.value.created_at) : new Date()
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false })
})

const transactionDate = computed(() => {
  const d = orderData.value?.created_at ? new Date(orderData.value.created_at) : new Date()
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
})

const paymentMethodLabel = computed(() => {
  const p = orderData.value?.payments?.[0]
  if (!p) return '-'
  if (p.payment_method === 'cash') return 'Tunai di Kasir'
  if (p.payment_method === 'qris') return 'QRIS'
  if (p.payment_method?.startsWith('va_')) {
    const bank = getBankVAInfo(p.payment_method)
    return `${(bank?.shortName || bank?.code || p.bank_code || 'VA').toUpperCase()} VA`
  }
  return p.payment_method?.toUpperCase() || '-'
})

const orderSubtotal = computed(() => {
  if (orderData.value?.items && orderData.value.items.length > 0) {
    return orderData.value.items.reduce((acc: number, item: any) => acc + (Number(item.subtotal) || 0), 0)
  }
  if (orderData.value?.subtotal) return Number(orderData.value.subtotal)
  const total = Number(orderData.value?.total_amount || 0)
  if (total > 0) {
    return Math.round(total / 1.1)
  }
  return 0
})

const orderDiscount = computed(() => {
  return Number(orderData.value?.discount_amount || 0)
})

const orderTotal = computed(() => {
  return Number(orderData.value?.total_amount || orderData.value?.final_amount || 0)
})

const orderTaxFee = computed(() => {
  if (orderData.value?.tax_amount !== undefined) return Number(orderData.value.tax_amount)
  return orderSubtotal.value > 0 ? Math.round(orderSubtotal.value * 0.1) : 0
})

const orderServiceFee = computed(() => {
  if (orderData.value?.service_fee !== undefined) return Number(orderData.value.service_fee)
  const diff = orderTotal.value - (orderSubtotal.value + orderTaxFee.value - orderDiscount.value)
  return Math.max(0, diff)
})

const activeStepInfo = computed(() => {
  if (!orderData.value) return { title: 'Memuat status...', desc: '', isDone: false, isFailed: false }
  const payStatus = orderData.value.payment_status
  const status = orderData.value.status

  if (status === 'expired' || payStatus === 'expired') {
    return { title: 'Waktu Pembayaran Habis', desc: 'Pesanan dibatalkan otomatis', isDone: false, isFailed: true }
  }
  if (status === 'cancelled' || payStatus === 'cancelled') {
    return { title: 'Pesanan Dibatalkan', desc: 'Transaksi telah dibatalkan', isDone: false, isFailed: true }
  }
  if (payStatus === 'unpaid') {
    return { title: 'Menunggu Pembayaran', desc: 'Selesaikan transaksi agar pesanan diproses', isDone: false, isFailed: false }
  }
  if (status === 'confirmed') {
    return { title: 'Pesanan Dikonfirmasi', desc: 'Dapur telah menerima pesanan Anda', isDone: false, isFailed: false }
  }
  if (status === 'preparing') {
    return { title: 'Sedang Dimasak', desc: 'Koki sedang menyiapkan pesanan Anda', isDone: false, isFailed: false }
  }
  if (status === 'ready') {
    return { title: 'Pesanan Siap Disajikan', desc: 'Hidangan siap diantar ke meja Anda', isDone: false, isFailed: false }
  }
  if (status === 'completed') {
    return { title: 'Pesanan Selesai', desc: 'Terima kasih atas pesanan Anda!', isDone: true, isFailed: false }
  }
  return { title: 'Pesanan Sedang Diproses', desc: 'Memproses pesanan Anda', isDone: false, isFailed: false }
})

const copyToClipboard = (text: string, type: 'va' | 'amount' | 'orderId') => {
  if (!text) return
  navigator.clipboard?.writeText(text)
  if (type === 'va') {
    copiedVa.value = true
    setTimeout(() => {
      copiedVa.value = false
    }, 2000)
  } else if (type === 'amount') {
    copiedAmount.value = true
    setTimeout(() => {
      copiedAmount.value = false
    }, 2000)
  } else {
    copiedOrderId.value = true
    setTimeout(() => {
      copiedOrderId.value = false
    }, 2000)
  }
}

const loadOrderStatus = async () => {
  const targetId = (route.query.orderId as string) || localStorage.getItem('lapaqu_active_order_id') || activeResolvedOrderId.value

  try {
    let res: any = null
    if (targetId) {
      res = await apiClient.get(`/public/orders/${targetId}/status`)
    } else {
      // Fallback cerdas: cari order aktif meja ini langsung dari backend
      res = await apiClient.get('/public/orders/active', {
        params: {
          table_code: tableCode.value,
          outlet_id: outletId.value
        }
      })
    }

    if (res?.data?.order) {
      orderData.value = res.data.order
      activeResolvedOrderId.value = orderData.value.id
      localStorage.setItem('lapaqu_active_order_id', orderData.value.id)

      if (orderData.value.payment_status === 'unpaid' && isVaPayment.value) {
        startVaTimer(activeVaPayment.value?.raw_payload?.expiration_date)
      }

      // Jika QRIS dan belum lunas, generate visual QR
      if (orderData.value.payment_status === 'unpaid' && isQrisPayment.value) {
        const qrString = orderData.value.payments?.[0]?.raw_payload?.qr_string
        if (qrString && !qrisDataUrl.value) {
          qrisDataUrl.value = await QRCode.toDataURL(qrString, {
            width: 240,
            margin: 1,
            color: { dark: '#000000', light: '#FFFFFF' },
          })
        }
      }
    } else {
      orderData.value = null
    }
  } catch (err) {
    console.error('Failed to load order status:', err)
  } finally {
    isLoading.value = false
  }
}

const isCancelling = ref(false)

const handleCancelTransfer = async () => {
  if (!orderId.value || isCancelling.value) return
  isCancelling.value = true
  try {
    await apiClient.post(`/public/orders/${orderId.value}/cancel`)
    localStorage.removeItem('lapaqu_active_order_id')
    cartStore.clearPendingOrder()
    await loadOrderStatus()
  } catch (err) {
    console.error('Failed to cancel transfer:', err)
  } finally {
    isCancelling.value = false
  }
}

const handleSimulatePayment = async () => {
  if (!orderId.value || isSimulating.value) return
  isSimulating.value = true
  try {
    await apiClient.post(`/public/orders/${orderId.value}/simulate-pay`)
    await loadOrderStatus()
  } catch (err) {
    console.error('Failed to simulate pay:', err)
  } finally {
    isSimulating.value = false
  }
}

onMounted(() => {
  loadOrderStatus()
  pollingInterval = setInterval(loadOrderStatus, 3000)
})

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval)
  if (vaTimerInterval) clearInterval(vaTimerInterval)
})
</script>

<template>
  <div class="space-y-4 py-1 select-none max-w-xl mx-auto">
    <!-- 0. SKELETON LOADING STATE (Saat Memuat Status Pesanan) -->
    <div v-if="isLoading" class="space-y-5">
      <!-- Status Banner Skeleton -->
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

      <!-- Stepper Skeleton -->
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

      <!-- Items Summary Skeleton -->
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
    <div v-else-if="!orderData" class="py-12 bg-white dark:bg-[#273142] rounded-3xl p-6 border border-[#E2E8F0] dark:border-[#334155] shadow-xs text-center space-y-3">
      <div
        class="w-16 h-16 rounded-full bg-slate-100 dark:bg-[#1E293B] text-slate-400 flex items-center justify-center mx-auto">
        <AppIcon name="receipt_long" :size="32" />
      </div>
      <h3 class="text-base font-bold text-[#1E293B] dark:text-white">Belum Ada Pesanan Aktif</h3>
      <p class="text-xs text-[#64748B] dark:text-[#94A3B8] max-w-xs mx-auto">
        Silakan pilih hidangan di menu dan lakukan pemesanan untuk melihat status di sini.
      </p>
      <AppButton @click="router.push(menuUrl)" variant="primary" class="mt-2 !rounded-full">
        Lihat Menu Sekarang
      </AppButton>
    </div>

    <!-- JIKA ADA PESANAN AKTIF: TANPA DIV PEMBUNGKUS KARTU, DIPISAHKAN DASHED LINE -->
    <div v-else class="space-y-5">
      <!-- 0. HORIZONTAL PROGRESSIVE STEPPER (Di atas div pesanan diproses) -->
      <div class="px-1 pt-1 pb-1 select-none">
        <div class="flex items-start justify-between relative">
          <template v-for="(step, idx) in steps" :key="idx">
            <!-- Step Item (Bulatan + Label) -->
            <div class="flex flex-col items-center text-center z-10 w-16 sm:w-20 shrink-0">
              <!-- Bulatan Step -->
              <div :class="[
                'w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 shadow-xs',
                idx < currentStep
                  ? 'bg-[#4880FF] text-white'
                  : (idx === currentStep
                    ? (activeStepInfo.isFailed
                        ? 'bg-rose-500 text-white ring-4 ring-rose-500/20 shadow-md scale-105'
                        : 'bg-[#4880FF] text-white ring-4 ring-[#4880FF]/20 shadow-md scale-105')
                    : 'bg-white dark:bg-[#1E293B] border-2 border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500')
              ]">
                <AppIcon v-if="idx < currentStep" name="check" :size="15" />
                <template v-else-if="idx === currentStep">
                  <AppIcon v-if="activeStepInfo.isFailed" name="close" :size="15" />
                  <AppIcon v-else-if="activeStepInfo.isDone" name="check" :size="15" />
                  <!-- Efek loading animasi status pembayaran -->
                  <svg v-else class="animate-spin w-3.5 h-3.5 sm:w-4 sm:h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </template>
                <span v-else class="text-[10px] sm:text-xs">{{ idx + 1 }}</span>
              </div>

              <!-- Label Step -->
              <p :class="[
                'text-[10px] sm:text-xs mt-1.5 leading-tight transition-colors',
                idx <= currentStep
                  ? 'font-bold text-[#1E293B] dark:text-white'
                  : 'font-medium text-[#64748B] dark:text-[#94A3B8]'
              ]">
                {{ step.label }}
              </p>
            </div>

            <!-- Garis Penghubung antar Step -->
            <div v-if="idx < steps.length - 1" class="flex-1 mt-3.5 sm:mt-4 -mx-2 sm:-mx-3">
              <div class="h-0.5 w-full transition-colors duration-500"
                :class="idx < currentStep ? 'bg-[#4880FF]' : 'bg-slate-200 dark:bg-slate-700'">
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- 1. Header Status Banner (Hanya jika status expired, cancelled, atau unpaid - div hijau pesanan sedang diproses dihilangkan) -->
      <div v-if="orderData.status === 'expired' || orderData.payment_status === 'expired' || orderData.status === 'cancelled' || orderData.payment_status === 'cancelled' || orderData.payment_status === 'unpaid'" :class="[
        orderData.status === 'expired' || orderData.payment_status === 'expired'
          ? 'bg-slate-100 dark:bg-slate-800/40 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300'
          : orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'
          ? 'bg-rose-50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300'
          : orderData.payment_status === 'unpaid'
          ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300'
          : 'bg-[#E6F8F5] dark:bg-[#00B69B]/10 border-[#00B69B]/30 text-[#00B69B]',
        'p-5 sm:p-6 rounded-2xl border text-center space-y-2.5'
      ]">
        <div :class="[
          orderData.status === 'expired' || orderData.payment_status === 'expired'
            ? 'bg-slate-500'
            : orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'
            ? 'bg-rose-500'
            : orderData.payment_status === 'unpaid' ? 'bg-amber-500' : 'bg-[#00B69B]',
          'w-12 h-12 rounded-full text-white flex items-center justify-center mx-auto shadow-md'
        ]">
          <AppIcon :name="orderData.status === 'expired' || orderData.payment_status === 'expired' ? 'history_toggle_off' : (orderData.status === 'cancelled' || orderData.payment_status === 'cancelled' ? 'close' : (orderData.payment_status === 'unpaid' ? 'schedule' : 'restaurant'))" :size="24"
            :class="orderData.payment_status === 'paid' ? 'animate-pulse' : ''" />
        </div>

        <h2 class="text-base sm:text-lg font-black text-[#1E293B] dark:text-white">
          {{ orderData.status === 'expired' || orderData.payment_status === 'expired'
            ? 'Waktu Pembayaran Habis (Kadaluarsa)'
            : (orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'
              ? 'Pesanan Dibatalkan'
              : (orderData.payment_status === 'unpaid' ? 'Menunggu Pembayaran' : 'Pesanan Sedang Diproses!')) }}
        </h2>

        <div class="flex items-center justify-center gap-2 flex-wrap">
          <AppBadge :variant="orderData.status === 'expired' || orderData.payment_status === 'expired' ? 'neutral' : (orderData.status === 'cancelled' || orderData.payment_status === 'cancelled' ? 'danger' : (orderData.payment_status === 'unpaid' ? 'warning' : 'success'))" size="sm">
            #{{ orderData.order_number }}
          </AppBadge>
          <AppBadge variant="primary" size="sm">
            Meja {{ orderData.table?.table_number || tableCode }}
          </AppBadge>
        </div>

        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium">
          {{ orderData.status === 'expired' || orderData.payment_status === 'expired'
            ? 'Batas waktu pembayaran pesanan telah habis sehingga pesanan dibatalkan otomatis. Silakan pesan kembali melalui menu.'
            : (orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'
              ? 'Pesanan telah dibatalkan. Silakan pesan kembali melalui menu.'
              : (orderData.payment_status === 'unpaid'
                ? 'Selesaikan pembayaran untuk mulai diproses oleh dapur'
                : 'Estimasi penyajian: ± 10–15 menit')) }}
        </p>

        <div v-if="orderData.status === 'expired' || orderData.payment_status === 'expired' || orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'" class="pt-2">
          <AppButton @click="router.push(menuUrl)" variant="primary" size="sm" class="!rounded-full font-bold">
            Pesan Lagi di Menu
          </AppButton>
        </div>
      </div>

      <!-- DASHED HORIZONTAL LINE SEBELUM PEMBAYARAN JIKA UNPAID -->
      <div v-if="orderData.payment_status === 'unpaid'" class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <!-- 2. PEMBAYARAN JIKA UNPAID (QRIS / VA / TUNAI) - Tanpa AppCard kotak pembungkus -->
      <!-- 2.1 QRIS -->
      <div v-if="orderData.payment_status === 'unpaid' && isQrisPayment" class="space-y-4 text-center">
        <div class="flex items-center justify-center gap-2 mb-1">
          <img :src="qrisSvg" alt="QRIS" class="h-5 object-contain dark:invert" />
          <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400">
            Sandbox
          </span>
        </div>
        <div class="bg-white p-3 rounded-2xl shadow-xs inline-block mx-auto border border-slate-200">
          <img v-if="qrisDataUrl" :src="qrisDataUrl" alt="QR Code" class="w-52 h-52 object-contain mx-auto rounded-lg" />
          <div v-else class="w-52 h-52 flex items-center justify-center text-xs text-slate-400">
            Memuat QR Code...
          </div>
        </div>
        <div class="space-y-0.5">
          <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">Total yang Harus Dibayar</p>
          <p class="text-xl font-black text-[#1E293B] dark:text-white tabular-nums">
            {{ formatCurrency(orderData.total_amount) }}
          </p>
        </div>
        <button type="button" :disabled="isSimulating" @click="handleSimulatePayment"
          class="w-full h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] disabled:opacity-50 text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
          <AppIcon name="bolt" :size="18" />
          <span v-if="!isSimulating"> Simulasikan Bayar (Sandbox)</span>
          <span v-else>Memproses Pembayaran...</span>
        </button>
      </div>

      <!-- 2.2 VIRTUAL ACCOUNT -->
      <div v-else-if="orderData.payment_status === 'unpaid' && isVaPayment" class="space-y-4">
        <!-- Header Bank Logo & Nama -->
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
            <img
              v-if="activeVaInfo?.logo"
              :src="activeVaInfo.logo"
              :alt="activeVaInfo.name"
              class="h-5 sm:h-5.5 w-auto object-contain shrink-0"
            />
            <span
              v-else
              class="px-2 py-0.5 rounded text-xs font-black text-white shrink-0"
              :style="{ backgroundColor: activeVaInfo?.bgColor || '#003D79' }"
            >
              {{ activeVaInfo?.code || 'VA' }}
            </span>
            <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">
              {{ activeVaInfo?.name || 'Virtual Account' }}
            </span>
          </div>

          <!-- Button Back di sebelah kanan -->
          <button
            type="button"
            @click="router.push(menuUrl)"
            class="w-7 h-7 -mr-1 flex items-center justify-center rounded-full text-[#64748B] hover:text-[#1E293B] dark:text-[#94A3B8] dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-90 transition-all cursor-pointer shrink-0"
            title="Kembali ke Menu"
          >
            <AppIcon name="arrow_back" :size="20" />
          </button>
        </div>

        <!-- Kartu Nomor Virtual Account -->
        <div
          @click="copyToClipboard(activeVaPayment?.raw_payload?.account_number || '', 'va')"
          class="p-5 sm:p-6 rounded-2xl sm:rounded-3xl bg-gradient-to-b from-[#4880FF]/20 via-[#4880FF]/5 to-transparent border-2 border-[#4880FF]/50 dark:border-[#4880FF]/60 shadow-xs flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-150 active:scale-[0.99] hover:border-[#4880FF] group select-none relative overflow-hidden"
          title="Klik untuk salin nomor Virtual Account"
        >
          <p class="text-xl sm:text-2xl font-black tracking-wider text-[#1E293B] dark:text-white font-mono select-all">
            {{ activeVaPayment?.raw_payload?.account_number || '88908XXXXXXXX' }}
          </p>
          <p class="text-xs sm:text-sm font-bold text-[#4880FF] dark:text-[#60A5FA] mt-2 flex items-center justify-center gap-1.5 transition-colors">
            <AppIcon v-if="copiedVa" name="check" :size="16" />
            <span>{{ copiedVa ? 'Nomor berhasil disalin!' : 'Klik untuk salin nomor' }}</span>
          </p>
        </div>

        <!-- Teks Batas Waktu Bayar -->
        <div class="flex items-center justify-center gap-2 text-center text-xs sm:text-sm font-medium px-1 flex-wrap">
          <span class="text-[#64748B] dark:text-[#94A3B8]">
            Bayar sebelum {{ vaDeadlineText }}
          </span>
          <span :class="[
            'font-bold tabular-nums',
            isVaExpired ? 'text-rose-500' : 'text-[#EF4444] dark:text-red-400'
          ]">
            {{ isVaExpired ? 'Hangus' : vaCountdown }}
          </span>
        </div>

        <!-- Tombol Aksi Simulasi & Batalkan -->
        <div class="space-y-2 pt-1">
          <button type="button" :disabled="isCancelling" @click="handleCancelTransfer"
            class="w-full h-11 rounded-full bg-[#EF3826] hover:bg-red-600 active:scale-[0.98] disabled:opacity-60 text-white font-bold text-xs sm:text-sm shadow-md shadow-red-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
            <div v-if="isCancelling" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <AppIcon v-else name="close" :size="18" />
            <span>{{ isCancelling ? 'Membatalkan Transfer...' : 'Batalin Transfer' }}</span>
          </button>

          <button type="button" :disabled="isSimulating || isVaExpired" @click="handleSimulatePayment"
            class="w-full h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] disabled:opacity-50 text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
            <AppIcon :name="isVaExpired ? 'error_outline' : 'bolt'" :size="18" />
            <span v-if="isVaExpired">Virtual Account Hangus</span>
            <span v-else-if="!isSimulating"> Simulasikan Bayar (Sandbox)</span>
            <span v-else>Memproses Pembayaran...</span>
          </button>
        </div>
      </div>

      <!-- 2.3 TUNAI DI KASIR -->
      <div v-else-if="orderData.payment_status === 'unpaid' && isCashPayment" class="space-y-3">
        <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-xs">
          <AppIcon name="payments" :size="22" class="shrink-0 text-amber-500 mt-0.5" />
          <div>
            <p class="font-bold">Silakan Lakukan Pembayaran ke Kasir</p>
            <p class="opacity-90 mt-0.5">
              Sebutkan nomor meja <strong>{{ orderData.table?.table_number || tableCode }}</strong> atau kode pesanan
              <strong>#{{ orderData.order_number }}</strong> kepada kasir sebesar <strong>{{ formatCurrency(orderData.total_amount) }}</strong>.
            </p>
          </div>
        </div>

        <button type="button" :disabled="isSimulating" @click="handleSimulatePayment"
          class="w-full h-10 rounded-full bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer">
          <AppIcon name="bolt" :size="16" />
          <span v-if="!isSimulating"> Simulasikan Kasir Terima Uang (Sandbox)</span>
          <span v-else>Memproses...</span>
        </button>
      </div>



      <!-- DASHED HORIZONTAL LINE SEBELUM DETAIL MENU / RINCIAN -->
      <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <!-- 4. DETAIL MENU DIPESAN (Ganti Menjadi Detail seperti di Payment) -->
      <div>
        <h4 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white mb-3.5">
          Rincian pesanan
        </h4>

        <div class="space-y-3.5 text-sm sm:text-base">
          <!-- Status -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Status</span>
            <span
              v-if="orderData.payment_status === 'paid'"
              class="font-bold text-emerald-500 inline-flex items-center gap-1.5"
            >
              <span class="leading-none">Berhasil</span>
              <AppIcon name="check_circle" :size="18" class="text-emerald-500 shrink-0" />
            </span>
            <span
              v-else-if="orderData.status === 'expired' || orderData.payment_status === 'expired'"
              class="font-bold text-rose-500 dark:text-rose-400 inline-flex items-center gap-1.5"
            >
              <span class="leading-none">Hangus</span>
              <AppIcon name="cancel" :size="18" class="text-rose-500 dark:text-rose-400 shrink-0" />
            </span>
            <span
              v-else-if="orderData.status === 'cancelled' || orderData.payment_status === 'cancelled'"
              class="font-bold text-rose-500 dark:text-rose-400 inline-flex items-center gap-1.5"
            >
              <span class="leading-none">Batal</span>
              <AppIcon name="cancel" :size="18" class="text-rose-500 dark:text-rose-400 shrink-0" />
            </span>
            <span
              v-else
              class="font-bold text-amber-500 dark:text-amber-400 inline-flex items-center gap-1.5"
            >
              <span class="leading-none">Menunggu</span>
              <svg class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="12" cy="13" r="8" /><path d="M12 2v3" /><path d="M10 2h4" /><path d="M12 9v4l2.5 2.5" />
              </svg>
            </span>
          </div>

          <!-- Metode pembayaran -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Metode pembayaran</span>
            <span class="font-bold text-[#1E293B] dark:text-white">
              {{ paymentMethodLabel }}
            </span>
          </div>

          <!-- Waktu Transaksi -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Waktu</span>
            <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
              {{ transactionTime }}
            </span>
          </div>

          <!-- Tanggal Transaksi -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Tanggal</span>
            <span class="font-medium text-[#1E293B] dark:text-white">
              {{ transactionDate }}
            </span>
          </div>

          <!-- ID transaksi (Nomor Pesanan) -->
          <div class="flex items-center justify-between gap-3">
            <span class="text-[#64748B] dark:text-[#94A3B8] shrink-0">ID transaksi</span>
            <div class="flex items-center gap-1.5 font-mono text-[#1E293B] dark:text-white min-w-0">
              <span class="truncate max-w-[170px] sm:max-w-[210px] text-sm font-semibold">#{{ orderData.order_number }}</span>
              <button type="button" @click="copyToClipboard(orderData.order_number, 'orderId')"
                class="text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] active:scale-90 transition-all p-0.5 cursor-pointer shrink-0"
                title="Salin ID Transaksi">
                <AppIcon :name="copiedOrderId ? 'check' : 'content_copy'" :size="16" :class="copiedOrderId ? 'text-emerald-500' : ''" />
              </button>
            </div>
          </div>

          <!-- Meja -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Meja</span>
            <span class="font-medium text-[#1E293B] dark:text-white">
              {{ orderData.table?.table_number || tableCode }}
            </span>
          </div>

          <!-- Dash Horizontal Line -->
          <div class="pt-1 border-t border-dashed border-slate-200 dark:border-[#334155]/80"></div>

          <!-- Daftar Item Menu yang Dipesan -->
          <div class="space-y-3">
            <div v-for="item in orderData.items" :key="item.id" class="flex items-start justify-between gap-3">
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
              {{ formatCurrency(orderSubtotal) }}
            </span>
          </div>

          <!-- Biaya layanan -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Biaya layanan</span>
            <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
              {{ formatCurrency(orderServiceFee) }}
            </span>
          </div>

          <!-- Pajak (PB1 10%) -->
          <div class="flex items-center justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Pajak (PB1 10%)</span>
            <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
              {{ formatCurrency(orderTaxFee) }}
            </span>
          </div>

          <!-- Potongan Promo jika ada -->
          <div v-if="orderDiscount > 0" class="flex items-center justify-between text-emerald-500">
            <span>Potongan Promo</span>
            <span class="font-medium tabular-nums">-{{ formatCurrency(orderDiscount) }}</span>
          </div>

          <!-- Garis Total -->
          <div class="pt-2 border-t border-[#F1F5F9] dark:border-[#334155]/60"></div>

          <!-- Total Asli Pesanan -->
          <div class="flex items-center justify-between pt-0.5">
            <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white">Total</span>
            <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white tabular-nums">
              {{ formatCurrency(orderData.total_amount) }}
            </span>
          </div>
        </div>
      </div>

      <!-- DASHED HORIZONTAL LINE SEBELUM TOMBOL -->
      <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

      <!-- 5. Tombol Buat Pesanan Baru -->
      <div class="pt-1">
        <AppButton @click="router.push(menuUrl)" variant="outline" size="lg" block icon="add"
          class="!rounded-full border-[#4880FF] text-[#4880FF] hover:bg-[#4880FF]/10 font-bold">
          Buat Pesanan Baru
        </AppButton>
      </div>
    </div>
  </div>
</template>
