<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppSearchInput from '@/components/ui/AppSearchInput.vue'
import AppBottomActionBar from '@/components/ui/AppBottomActionBar.vue'
import AppBottomSheetModal from '@/components/ui/AppBottomSheetModal.vue'
import QRCode from 'qrcode'
import apiClient from '@/services/api'
import { onUnmounted } from 'vue'
import qrisSvg from '@/assets/payment_method/QRIS Black.svg'
import xenditSvg from '@/assets/xendit.svg'
import AppButton from '@/components/ui/AppButton.vue'
import { MAIN_VA_BANKS, OTHER_VA_BANKS, getBankVAInfo } from '@/composables/useBankVA'
import { useCartStore } from '@/stores/cart'
import { useCustomerI18n } from '@/i18n'
import type { CartItem } from '@/types'
import { useFormat } from '@/composables/useFormat'

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()
const { t, translate } = useCustomerI18n()
const { formatCurrency, formatNumber } = useFormat()

const formatTitleCase = (str: string) => {
  if (!str) return ''
  return str
    .toLowerCase()
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}



const outletId = computed(() => (route.params.outletId as string) || cartStore.outletId || '')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || '')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)

const isLoading = ref(true)

onMounted(async () => {
  try {
    await new Promise((resolve) => setTimeout(resolve, 400))

    // 1. Cek apakah ada pesanan pending di cartStore yang sedang menunggu pembayaran
    if (cartStore.hasPendingOrder && cartStore.pendingOrder) {
      activeOrder.value = cartStore.pendingOrder.order || {
        id: cartStore.pendingOrder.id,
        order_number: cartStore.pendingOrder.order_number,
        total_amount: cartStore.pendingOrder.total_amount,
      }
      activePayment.value = cartStore.pendingOrder.payment || {
        payment_method: cartStore.pendingOrder.payment_method,
      }
      if (cartStore.pendingOrder.qrisDataUrl) {
        qrisDataUrl.value = cartStore.pendingOrder.qrisDataUrl
      } else if (cartStore.pendingOrder.payment?.qr_string) {
        qrisDataUrl.value = await QRCode.toDataURL(cartStore.pendingOrder.payment.qr_string, {
          width: 280,
          margin: 1,
          color: { dark: '#000000', light: '#FFFFFF' },
        })
      }
      selectedPaymentMethod.value = cartStore.pendingOrder.payment_method || 'qris'
      if (selectedPaymentMethod.value !== 'cash') {
        const savedExpiry = cartStore.pendingOrder.expires_at || cartStore.pendingOrder.payment?.expiration_date
        if (savedExpiry) {
          startQrisTimer(savedExpiry)
        } else {
          const isVA = cartStore.pendingOrder.payment_method?.startsWith('va_')
          const duration = isVA ? 10 * 60 * 1000 : 5 * 60 * 1000
          const createdAt = cartStore.pendingOrder.created_at ? new Date(cartStore.pendingOrder.created_at).getTime() : Date.now()
          const fallbackExpiry = new Date(createdAt + duration).toISOString()
          startQrisTimer(fallbackExpiry)
        }
      }
      startPollingPaymentStatus(cartStore.pendingOrder.id)

      // Jika user klik "Lanjutkan Pembayaran" dari floating cart di halaman menu atau sedang menunggu kasir
      if (route.query.openQris === '1' || route.query.openQris === 'true' || selectedPaymentMethod.value === 'cash') {
        isQrisModalOpen.value = true
      }
    } else {
      const activeOrderId = localStorage.getItem('lapaqu_active_order_id')
      if (cartStore.items.length === 0 && activeOrderId) {
        router.replace(`${statusUrl.value}?orderId=${activeOrderId}`)
        return
      } else if (cartStore.items.length === 0) {
        router.replace(menuUrl.value)
        return
      }
    }
  } finally {
    isLoading.value = false
  }
})

// Redirect aman jika keranjang kosong saat user tidak sedang membuka modal pembayaran
watch(
  () => cartStore.items.length,
  (len) => {
    if (!isLoading.value && len === 0 && !isQrisModalOpen.value && !activeOrder.value && !cartStore.hasPendingOrder && !isPaymentModalOpen.value) {
      const activeOrderId = localStorage.getItem('lapaqu_active_order_id')
      if (activeOrderId) {
        router.replace(`${statusUrl.value}?orderId=${activeOrderId}`)
      } else {
        router.replace(menuUrl.value)
      }
    }
  }
)

const cartUrl = computed(() => `/order/${outletId.value}/${tableCode.value}/my-order`)
const statusUrl = computed(() => `/order/${outletId.value}/${tableCode.value}/status`)

// Promo Code State
const promoCodeInput = ref('')
const appliedPromo = ref<{ code: string; discount: number; title: string } | null>(null)
const promoError = ref('')

const handleApplyPromo = () => {
  promoError.value = ''
  const code = promoCodeInput.value.trim().toUpperCase()
  if (!code) {
    promoError.value = 'Masukkan kode promo terlebih dahulu'
    return
  }

  // Demo valid promo codes
  if (code === 'DISKON10' || code === 'HEMAT') {
    appliedPromo.value = {
      code,
      discount: 10000,
      title: 'Diskon Spesial Rp 10.000'
    }
  } else if (code === 'LAPAQU20') {
    appliedPromo.value = {
      code,
      discount: 15000,
      title: 'Diskon Lapaqu Rp 15.000'
    }
  } else {
    promoError.value = 'Kode promo tidak valid atau telah kedaluwarsa'
  }
}

const handleRemovePromo = () => {
  appliedPromo.value = null
  promoCodeInput.value = ''
  promoError.value = ''
}

// Perhitungan Biaya (Calculation)
const subtotal = computed(() => cartStore.totalPrice)
const totalItemsCount = computed(() => cartStore.totalItemsCount)
const serviceFee = computed(() => (subtotal.value > 0 ? 2000 : 0))
const taxFee = computed(() => (subtotal.value > 0 ? Math.round(subtotal.value * 0.1) : 0))
const discountAmount = computed(() => (appliedPromo.value ? appliedPromo.value.discount : 0))

const grandTotal = computed(() => {
  return Math.max(0, subtotal.value + serviceFee.value + taxFee.value - discountAmount.value)
})

interface DisplayOption {
  label: string
  value: string
}

// Varian yang persis digunakan di Keranjang Pesanan (CartPage.vue)
const getItemOptionList = (item: CartItem): DisplayOption[] => {
  if (item.selectedOptions && item.selectedOptions.length > 0) {
    return item.selectedOptions.map((opt: any) => ({
      label: opt.groupName || 'Pilihan',
      value: opt.optionName || opt.name || ''
    }))
  }
  const name = (item.menuItem?.name || '').toLowerCase()
  if (name.includes('rice') || name.includes('beef') || name.includes('nasi') || name.includes('ayam') || name.includes('mie') || name.includes('paket') || name.includes('hemat')) {
    return [
      { label: 'Level Pedas', value: 'Pedas Sedang' },
      { label: 'Porsi Nasi', value: 'Reguler' },
      { label: 'Sambal', value: 'Dipisah' }
    ]
  }
  return [
    { label: 'Ice Level', value: 'No Ice' },
    { label: 'Caffeine Dose', value: 'Light Dose' },
    { label: 'Sugar Level', value: 'No Sugar' },
    { label: 'Size', value: 'Reguler' }
  ]
}

// Navigasi edit item
const handleEditItem = (item: CartItem) => {
  if (hasPendingPayment.value) return
  router.push(menuUrl.value)
}

// Payment Methods Modal Sheet
const isPaymentModalOpen = ref(false)
const initialModalHeight = ref<number | null>(null)
const isContentOverflowing = ref(false)
const isScrollingBack = ref(false)
let scrollBackTimer: any = null

watch(isPaymentModalOpen, async (isOpen) => {
  if (isOpen) {
    isOtherBankOpen.value = false
    initialModalHeight.value = null
    isContentOverflowing.value = false
    isScrollingBack.value = false
    await nextTick()
    // Ukuran dinamis awal di-lock agar tinggi modal stabil dan tidak loncat saat dropdown dibuka atau ditutup
    const el = paymentBottomSheetRef.value?.modalRef
    if (el) {
      initialModalHeight.value = el.offsetHeight
    }
    const scrollBody = paymentBottomSheetRef.value?.scrollBodyRef
    if (scrollBody && scrollBody.scrollHeight > scrollBody.clientHeight + 4) {
      isContentOverflowing.value = true
    }
  } else {
    isOtherBankOpen.value = false
    initialModalHeight.value = null
    isContentOverflowing.value = false
    isScrollingBack.value = false
    if (scrollBackTimer) clearTimeout(scrollBackTimer)
  }
})

// Tinggi modal dynamic di awal, dan ukuran tetap terkunci stabil baik saat dropdown aktif maupun saat di-collapse
const paymentModalStyle = computed(() => {
  if (initialModalHeight.value) {
    return {
      height: `${initialModalHeight.value}px`,
      maxHeight: `${initialModalHeight.value}px`,
    }
  }
  return undefined
})

const isSubmitting = ref(false)
const selectedPaymentMethod = ref<string>('qris')
const isOtherBankOpen = ref(false)
const otherBankRef = ref<HTMLElement | null>(null)
const copiedVa = ref(false)
const copiedAmount = ref(false)
const copiedTrxId = ref(false)
const isVaStatusOpen = ref(true)
const activeGuideTab = ref<'mbanking' | 'atm'>('mbanking')

const isOtherBankSelected = computed(() => {
  return OTHER_VA_BANKS.some((b) => b.id === selectedPaymentMethod.value)
})

const selectedOtherBank = computed(() => {
  return OTHER_VA_BANKS.find((b) => b.id === selectedPaymentMethod.value)
})

const activeVaInfo = computed(() => {
  const methodOrCode =
    activePayment.value?.payment_method ||
    activePayment.value?.bank_code ||
    cartStore.pendingOrder?.payment_method ||
    cartStore.pendingOrder?.payment?.bank_code ||
    selectedPaymentMethod.value ||
    ''
  return getBankVAInfo(methodOrCode)
})

const transactionTime = computed(() => {
  const d = activeOrder.value?.created_at ? new Date(activeOrder.value.created_at) : new Date()
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false })
})

const transactionDate = computed(() => {
  const d = activeOrder.value?.created_at ? new Date(activeOrder.value.created_at) : new Date()
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
})

const transactionOrderId = computed(() => {
  return activeOrder.value?.order_number || activeOrder.value?.id || ''
})

const vaOrderSubtotal = computed(() => {
  if (activeOrder.value?.items && activeOrder.value.items.length > 0) {
    return activeOrder.value.items.reduce((acc: number, item: any) => acc + (Number(item.subtotal) || 0), 0)
  }
  if (cartStore.totalPrice > 0) return cartStore.totalPrice
  if (activeOrder.value?.subtotal) return Number(activeOrder.value.subtotal)
  const total = Number(activeOrder.value?.total_amount || 0)
  if (total > 0) {
    return Math.round(total / 1.1)
  }
  return 0
})

const vaOrderDiscount = computed(() => {
  return Number(activeOrder.value?.discount_amount || 0)
})

const vaOrderTotal = computed(() => {
  return Number(activeOrder.value?.total_amount || activeOrder.value?.final_amount || 0)
})

const vaOrderTaxFee = computed(() => {
  if (activeOrder.value?.tax_amount !== undefined) return Number(activeOrder.value.tax_amount)
  return vaOrderSubtotal.value > 0 ? Math.round(vaOrderSubtotal.value * 0.1) : 0
})

const vaOrderServiceFee = computed(() => {
  if (activeOrder.value?.service_fee !== undefined) return Number(activeOrder.value.service_fee)
  const diff = vaOrderTotal.value - (vaOrderSubtotal.value + vaOrderTaxFee.value - vaOrderDiscount.value)
  return Math.max(0, diff)
})



const toggleOtherBank = () => {
  // Tangkap tinggi awal sebelum dropdown terbuka jika belum tercatat
  if (!initialModalHeight.value && paymentBottomSheetRef.value?.modalRef) {
    initialModalHeight.value = paymentBottomSheetRef.value.modalRef.offsetHeight
  }
  isOtherBankOpen.value = !isOtherBankOpen.value
  const scrollBody = paymentBottomSheetRef.value?.scrollBodyRef
  if (isOtherBankOpen.value) {
    if (scrollBackTimer) clearTimeout(scrollBackTimer)
    isScrollingBack.value = false
    // Beri jeda singkat agar CSS grid mulai membuka, lalu smooth scroll secara alami tanpa loop rAF paksa
    setTimeout(() => {
      if (isOtherBankOpen.value && scrollBody) {
        scrollBody.scrollTo({
          top: scrollBody.scrollHeight,
          behavior: 'smooth',
        })
      }
    }, 80)
  } else {
    // Ketika dropdown di-collapse, kembalikan posisi scroll internal ke paling atas secara smooth
    isScrollingBack.value = true
    if (scrollBody) {
      scrollBody.scrollTo({
        top: 0,
        behavior: 'smooth',
      })
    }
    scrollBackTimer = setTimeout(() => {
      isScrollingBack.value = false
      if (scrollBody) {
        scrollBody.scrollTop = 0
      }
    }, 300)
  }
}



const copyToClipboard = (text: string, type: 'va' | 'amount' | 'trxId') => {
  if (!text) return
  navigator.clipboard?.writeText(text)
  if (type === 'va') {
    copiedVa.value = true
    setTimeout(() => { copiedVa.value = false }, 2000)
  } else if (type === 'amount') {
    copiedAmount.value = true
    setTimeout(() => { copiedAmount.value = false }, 2000)
  } else if (type === 'trxId') {
    copiedTrxId.value = true
    setTimeout(() => { copiedTrxId.value = false }, 2000)
  }
}

// Bottom Sheet Payment Modal Ref
const paymentBottomSheetRef = ref<InstanceType<typeof AppBottomSheetModal> | null>(null)

const handleOpenPaymentModal = () => {
  if (cartStore.items.length === 0) return
  isPaymentModalOpen.value = true
}

// State QRIS Modal & Polling Status
const isQrisModalOpen = ref(false)
const activeOrder = ref<any>(null)
const activePayment = ref<any>(null)
const qrisDataUrl = ref<string>('')
const qrisSecondsRemaining = ref(300)
let qrisTimerInterval: any = null
let paymentPollInterval: any = null
const isSimulating = ref(false)
const paymentSuccess = ref(false)

const isQrisPayment = computed(() => {
  const pm = activePayment.value?.payment_method || cartStore.pendingOrder?.payment_method
  if (pm) return pm === 'qris'
  return selectedPaymentMethod.value === 'qris'
})

const isVaPayment = computed(() => {
  const pm = activePayment.value?.payment_method || cartStore.pendingOrder?.payment_method
  if (pm) return pm.startsWith('va_')
  return selectedPaymentMethod.value.startsWith('va_')
})

const isCashPayment = computed(() => {
  const pm = activePayment.value?.payment_method || cartStore.pendingOrder?.payment_method
  if (pm) return pm === 'cash'
  return selectedPaymentMethod.value === 'cash'
})

const orderExpiredNotice = ref<string>('')
let expiredNoticeTimeout: any = null

const handleOrderExpired = async () => {
  if (paymentSuccess.value) return
  const orderId = activeOrder.value?.id || cartStore.pendingOrder?.id
  const orderNumber = activeOrder.value?.order_number || cartStore.pendingOrder?.order_number || ''

  if (paymentPollInterval) clearInterval(paymentPollInterval)
  if (qrisTimerInterval) clearInterval(qrisTimerInterval)
  isQrisModalOpen.value = false

  if (orderId) {
    try {
      await apiClient.post(`/public/orders/${orderId}/expire`)
    } catch (e) {
      console.warn('Auto cancel expired order:', e)
    }
  }

  cartStore.clearPendingOrder()
  localStorage.removeItem('lapaqu_active_order_id')
  checkoutIdempotencyKey.value = ''
  activeOrder.value = null
  activePayment.value = null
  qrisDataUrl.value = ''

  orderExpiredNotice.value = orderNumber
    ? `Batas waktu pembayaran pesanan #${orderNumber} telah habis (Kadaluarsa). Silakan buat pesanan baru.`
    : 'Batas waktu pembayaran telah habis (Hangus). Silakan pilih metode pembayaran baru untuk memesan ulang.'

  if (expiredNoticeTimeout) clearTimeout(expiredNoticeTimeout)
  expiredNoticeTimeout = setTimeout(() => {
    orderExpiredNotice.value = ''
  }, 12000)
}

const startQrisTimer = (targetExpiresAt?: string) => {
  if (qrisTimerInterval) clearInterval(qrisTimerInterval)

  const calculateSeconds = () => {
    const isVA = selectedPaymentMethod.value?.startsWith('va_') || activePayment.value?.payment_method?.startsWith('va_') || cartStore.pendingOrder?.payment_method?.startsWith('va_')
    const expiry = targetExpiresAt || (isVA ? getVaExpirationTime() : (activePayment.value?.expiration_date || cartStore.pendingOrder?.expires_at))
    if (expiry) {
      const diffMs = new Date(expiry).getTime() - Date.now()
      const secs = Math.max(0, Math.floor(diffMs / 1000))
      qrisSecondsRemaining.value = secs
      if (secs <= 0) {
        if (qrisTimerInterval) clearInterval(qrisTimerInterval)
        handleOrderExpired()
      }
    } else {
      if (qrisSecondsRemaining.value > 0) {
        qrisSecondsRemaining.value--
        if (qrisSecondsRemaining.value <= 0) {
          if (qrisTimerInterval) clearInterval(qrisTimerInterval)
          handleOrderExpired()
        }
      } else if (qrisTimerInterval) {
        clearInterval(qrisTimerInterval)
        handleOrderExpired()
      }
    }
  }

  calculateSeconds()
  qrisTimerInterval = setInterval(calculateSeconds, 1000)
}

const qrisCountdown = computed(() => {
  const mins = Math.floor(qrisSecondsRemaining.value / 60)
  const secs = qrisSecondsRemaining.value % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})

const qrisTimerDigits = computed(() => {
  const mins = Math.floor(qrisSecondsRemaining.value / 60)
  const secs = qrisSecondsRemaining.value % 60
  const mStr = String(mins).padStart(2, '0')
  const sStr = String(secs).padStart(2, '0')
  return {
    m1: mStr[0] || '0',
    m2: mStr[1] || '0',
    s1: sStr[0] || '0',
    s2: sStr[1] || '0',
  }
})

const getVaExpirationTime = () => {
  const createdAt = activeOrder.value?.created_at ? new Date(activeOrder.value.created_at).getTime() : Date.now()
  const maxVaExpiry = createdAt + 10 * 60 * 1000

  const rawExpiry = activePayment.value?.expiration_date 
    || activePayment.value?.raw_payload?.expiration_date
    || cartStore.pendingOrder?.payment?.expiration_date 
    || cartStore.pendingOrder?.expires_at

  if (rawExpiry) {
    const expTime = new Date(rawExpiry).getTime()
    // Jika data lama dari database berdurasi 24 jam (> 11 menit), batasi ke 10 menit batas waktu VA
    if (expTime - createdAt > 11 * 60 * 1000) {
      return new Date(maxVaExpiry).toISOString()
    }
    return rawExpiry
  }
  return new Date(maxVaExpiry).toISOString()
}

const vaCountdown = computed(() => {
  const secs = qrisSecondsRemaining.value
  if (secs <= 0) return '00:00'
  const hours = Math.floor(secs / 3600)
  const mins = Math.floor((secs % 3600) / 60)
  const remainingSecs = secs % 60

  if (hours > 0) {
    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(remainingSecs).padStart(2, '0')}`
  }
  return `${String(mins).padStart(2, '0')}:${String(remainingSecs).padStart(2, '0')}`
})

const vaDeadlineText = computed(() => {
  const expiry = getVaExpirationTime()
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

const isVaExpired = computed(() => {
  if (paymentSuccess.value || activeOrder.value?.payment_status === 'paid') return false
  if (activeOrder.value?.payment_status === 'expired' || activeOrder.value?.status === 'cancelled') return true
  return qrisSecondsRemaining.value <= 0
})

const startPollingPaymentStatus = (orderId: string) => {
  if (paymentPollInterval) clearInterval(paymentPollInterval)
  paymentPollInterval = setInterval(async () => {
    try {
      const res = await apiClient.get(`/public/orders/${orderId}/status`)
      if (res.data?.order) {
        const p = res.data.order.payments?.find((pay: any) => pay.payment_method?.startsWith('va_')) || res.data.order.payments?.[0]
        if (p) {
          const exp = p.raw_payload?.expiration_date || p.expiration_date
          if (exp && exp !== activePayment.value?.expiration_date) {
            activePayment.value = {
              ...activePayment.value,
              ...p,
              expiration_date: exp,
              account_number: p.raw_payload?.account_number || p.account_number || activePayment.value?.account_number,
            }
          }
        }
      }
      const ord = res.data?.order
      const isPaidOrAccepted = ord?.payment_status === 'paid' || ['confirmed', 'processing', 'preparing', 'cooking', 'ready', 'completed'].includes(ord?.status)
      if (isPaidOrAccepted) {
        clearInterval(paymentPollInterval)
        if (qrisTimerInterval) clearInterval(qrisTimerInterval)
        paymentSuccess.value = true
        localStorage.setItem('lapaqu_active_order_id', orderId)
        setTimeout(() => {
          cartStore.clearPendingOrder()
          cartStore.clearCart()
          isQrisModalOpen.value = false
          router.push(`${statusUrl.value}?orderId=${orderId}`)
        }, 1200)
      } else if (res.data?.order?.payment_status === 'expired' || res.data?.order?.status === 'expired' || res.data?.order?.status === 'cancelled') {
        handleOrderExpired()
      }
    } catch (err) {
      console.error('Polling error:', err)
    }
  }, 2500)
}

const handleDownloadQris = () => {
  if (!qrisDataUrl.value) return
  const a = document.createElement('a')
  a.href = qrisDataUrl.value
  a.download = `QRIS-${activeOrder.value?.order_number || 'lapaqu'}.png`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

const handleShareQris = async () => {
  if (navigator.share) {
    try {
      const shareData: ShareData = {
        title: `QRIS Pembayaran ${activeOrder.value?.order_number || ''}`,
        text: `Silakan scan QRIS untuk pembayaran pesanan #${activeOrder.value?.order_number || ''} (${activeOrder.value?.outlet?.name || activeOrder.value?.outletName || 'Lapaqu Cafe'}) sebesar ${formatCurrency(activeOrder.value?.total_amount || 0)}`,
        url: window.location.href,
      }

      // Coba sertakan file gambar QRIS jika didukung oleh browser mobile
      if (qrisDataUrl.value && navigator.canShare) {
        try {
          const res = await fetch(qrisDataUrl.value)
          const blob = await res.blob()
          const file = new File([blob], `QRIS-${activeOrder.value?.order_number || 'order'}.png`, { type: 'image/png' })
          if (navigator.canShare({ files: [file] })) {
            shareData.files = [file]
          }
        } catch {
          // Fallback share URL & text saja
        }
      }

      await navigator.share(shareData)
    } catch {
      // User membatalkan dialog share (AbortError / dismiss), biarkan saja dan jangan salin link/alert
    }
    return
  }

  // Fallback perangkat desktop tanpa navigator.share: salin senyap tanpa alert popup
  navigator.clipboard?.writeText(window.location.href)
}

// Drag gesture state & background scroll lock for QRIS Payment Modal
const qrisModalRef = ref<HTMLElement | null>(null)
const qrisScrollRef = ref<HTMLElement | null>(null)
const isDraggingQris = ref(false)
const isClosingQrisByDrag = ref(false)
const qrisDragTranslateY = ref(0)
let qrisDragStartY = 0
let qrisDragStartX = 0
let qrisDragStartTime = 0
let isTrackingQrisTouch = false

const resetQrisDragState = () => {
  isDraggingQris.value = false
  isClosingQrisByDrag.value = false
  qrisDragTranslateY.value = 0
  isTrackingQrisTouch = false
}

const preventBackgroundWheel = (e: WheelEvent) => {
  if (!qrisModalRef.value || !qrisModalRef.value.contains(e.target as Node)) {
    if (e.cancelable) e.preventDefault()
  }
}

const preventBackgroundTouch = (e: TouchEvent) => {
  if (!qrisModalRef.value || !qrisModalRef.value.contains(e.target as Node)) {
    if (e.cancelable) e.preventDefault()
  }
}

watch(isQrisModalOpen, (isOpen) => {
  if (typeof window !== 'undefined') {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
      window.addEventListener('wheel', preventBackgroundWheel, { passive: false })
      window.addEventListener('touchmove', preventBackgroundTouch, { passive: false })
      resetQrisDragState()
    } else {
      document.body.style.overflow = ''
      window.removeEventListener('wheel', preventBackgroundWheel)
      window.removeEventListener('touchmove', preventBackgroundTouch)
      if (!isClosingQrisByDrag.value) {
        resetQrisDragState()
      }
    }
  }
})

const finishQrisDrag = () => {
  const timeElapsed = Date.now() - qrisDragStartTime
  const velocity = qrisDragTranslateY.value / Math.max(1, timeElapsed)

  // Dragged down past 70px or fast downward flick
  if (qrisDragTranslateY.value > 70 || (velocity > 0.3 && qrisDragTranslateY.value > 25)) {
    isDraggingQris.value = false
    isClosingQrisByDrag.value = true

    // Animasikan terus meluncur ke bawah (translateY 100%) sampai tuntas tertutup
    setTimeout(() => {
      isQrisModalOpen.value = false
      setTimeout(() => {
        resetQrisDragState()
      }, 100)
    }, 240)
  } else {
    // Snap back ke posisi semula jika drag dilepas sebelum batas tutup
    isDraggingQris.value = false
    qrisDragTranslateY.value = 0
  }
}

const onQrisTouchStart = (e: TouchEvent) => {
  if (e.touches.length !== 1) return
  const touch = e.touches[0]
  qrisDragStartY = touch.clientY
  qrisDragStartX = touch.clientX
  qrisDragStartTime = Date.now()
  isTrackingQrisTouch = true
}

const onQrisTouchMove = (e: TouchEvent) => {
  if (!isTrackingQrisTouch || e.touches.length !== 1) return
  const touch = e.touches[0]
  const deltaY = touch.clientY - qrisDragStartY
  const deltaX = touch.clientX - qrisDragStartX

  if (isDraggingQris.value) {
    qrisDragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
    return
  }

  // Cancel tracking if mostly horizontal movement
  if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
    isTrackingQrisTouch = false
    return
  }

  const scrollable = qrisScrollRef.value
  const currentScrollTop = scrollable ? scrollable.scrollTop : 0

  // Start dragging sheet down if content is at top
  if (deltaY > 6 && currentScrollTop <= 0) {
    isDraggingQris.value = true
    qrisDragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
  }
}

const onQrisTouchEnd = (e: TouchEvent) => {
  if (!isTrackingQrisTouch) return
  isTrackingQrisTouch = false

  if (isDraggingQris.value) {
    finishQrisDrag()
  }
}

const onQrisPointerDown = (e: PointerEvent) => {
  if (e.button !== 0) return
  const target = e.target as HTMLElement | null
  if (target && target.closest('button, input, textarea, a, label')) return

  const scrollable = qrisScrollRef.value
  if (scrollable && scrollable.contains(target) && scrollable.scrollTop > 0) return

  qrisDragStartY = e.clientY
  qrisDragStartX = e.clientX
  qrisDragStartTime = Date.now()

  const onPointerMove = (pe: PointerEvent) => {
    const deltaY = pe.clientY - qrisDragStartY
    const deltaX = pe.clientX - qrisDragStartX

    if (isDraggingQris.value) {
      qrisDragTranslateY.value = Math.max(0, deltaY)
      return
    }

    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
      cleanupPointer()
      return
    }

    const currentScrollTop = scrollable ? scrollable.scrollTop : 0
    if (deltaY > 6 && currentScrollTop <= 0) {
      isDraggingQris.value = true
      qrisDragTranslateY.value = Math.max(0, deltaY)
    }
  }

  const onPointerUp = () => {
    cleanupPointer()
    if (isDraggingQris.value) {
      finishQrisDrag()
    }
  }

  const cleanupPointer = () => {
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerUp)
  }

  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', onPointerUp)
}

const hasPendingPayment = computed(() => {
  return !!(
    (cartStore.hasPendingOrder || activeOrder.value?.id) &&
    !paymentSuccess.value
  )
})

const handleBottomDockAction = () => {
  if (hasPendingPayment.value) {
    // Munculkan kembali modal pembayaran QRIS
    isQrisModalOpen.value = true
  } else {
    handleOpenPaymentModal()
  }
}

const handleClosePaymentModal = () => {
  if (!isClosingQrisByDrag.value) {
    resetQrisDragState()
  }
  isQrisModalOpen.value = false
  // Jangan redirect ke halaman status pembayaran, tetap di halaman Pesanan Saya
}

const navigateToVaInstructions = () => {
  const bankCode = activePayment.value?.bank_code || activeVaInfo.value?.code || 'bri'
  router.push({
    name: 'customer-va-instructions',
    params: {
      outletId: route.params.outletId || cartStore.outletId || '',
      tableCode: route.params.tableCode || cartStore.tableCode || '',
    },
    query: {
      bank: bankCode.toLowerCase(),
    },
  })
}

const isCancelling = ref(false)

const handleCancelTransfer = async () => {
  if (isCancelling.value) return

  const orderId = activeOrder.value?.id || cartStore.pendingOrder?.id
  isCancelling.value = true

  try {
    if (paymentPollInterval) clearInterval(paymentPollInterval)
    if (qrisTimerInterval) clearInterval(qrisTimerInterval)

    if (orderId) {
      await apiClient.post(`/public/orders/${orderId}/expire`)
    }
  } catch (err: any) {
    console.error('Gagal membatalkan transfer di backend:', err)
  } finally {
    cartStore.clearPendingOrder()
    localStorage.removeItem('lapaqu_active_order_id')
    checkoutIdempotencyKey.value = ''
    activeOrder.value = null
    activePayment.value = null
    qrisDataUrl.value = ''
    isQrisModalOpen.value = false
    isCancelling.value = false
  }
}

const handleSimulatePayment = async () => {
  if (!activeOrder.value?.id || isSimulating.value) return
  isSimulating.value = true
  try {
    await apiClient.post(`/public/orders/${activeOrder.value.id}/simulate-pay`)
    paymentSuccess.value = true
    localStorage.setItem('lapaqu_active_order_id', activeOrder.value.id)
    clearInterval(paymentPollInterval)
    clearInterval(qrisTimerInterval)
    setTimeout(() => {
      cartStore.clearPendingOrder()
      cartStore.clearCart()
      isQrisModalOpen.value = false
      router.push(`${statusUrl.value}?orderId=${activeOrder.value.id}`)
    }, 1200)
  } catch (err) {
    console.error('Simulate payment error:', err)
  } finally {
    isSimulating.value = false
  }
}

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = ''
    window.removeEventListener('wheel', preventBackgroundWheel)
    window.removeEventListener('touchmove', preventBackgroundTouch)
  }
  if (paymentPollInterval) clearInterval(paymentPollInterval)
  if (qrisTimerInterval) clearInterval(qrisTimerInterval)
})

// Client Idempotency Key per checkout attempt
const checkoutIdempotencyKey = ref<string>('')

const handleConfirmPayment = async () => {
  if (cartStore.items.length === 0 || isSubmitting.value) return
  isSubmitting.value = true

  if (!checkoutIdempotencyKey.value) {
    checkoutIdempotencyKey.value = `IDEMP-${Date.now()}-${Math.random().toString(36).substring(2, 11).toUpperCase()}`
  }

  try {
    const itemsPayload = cartStore.items.map((item) => ({
      menu_item_id: item.menuItem.id,
      quantity: item.quantity,
      notes: item.notes || '',
      selected_option_ids: (item.selectedOptions || [])
        .map((o) => o.optionId)
        .filter(Boolean),
    }))

    const resolvedTableToken =
      (route.query.token as string) ||
      localStorage.getItem('lapaqu_table_token') ||
      tableCode.value ||
      ''

    const res = await apiClient.post(
      '/public/orders',
      {
        table_token: resolvedTableToken,
        customer_name: cartStore.customerName.trim() || 'Pelanggan Umum',
        customer_phone: cartStore.customerPhone || null,
        payment_method: selectedPaymentMethod.value,
        notes: null,
        items: itemsPayload,
      },
      {
        headers: {
          'Idempotency-Key': checkoutIdempotencyKey.value,
        },
      }
    )

    const { order, payment } = res.data

    isPaymentModalOpen.value = false

    if (selectedPaymentMethod.value === 'qris' && payment?.qr_string) {
      activeOrder.value = order
      activePayment.value = payment
      qrisDataUrl.value = await QRCode.toDataURL(payment.qr_string, {
        width: 280,
        margin: 1,
        color: { dark: '#000000', light: '#FFFFFF' },
      })
      // Batas waktu bayar 5 menit sesuai Xendit QRIS expiration
      const expiryDate = payment?.expiration_date || new Date(Date.now() + 5 * 60 * 1000).toISOString()
      paymentSuccess.value = false
      isQrisModalOpen.value = true

      // Simpan pending order ke cartStore agar tetap tersinkronisasi di seluruh halaman
      cartStore.setPendingOrder({
        id: order.id,
        order_number: order.order_number,
        total_amount: order.total_amount,
        total_items: cartStore.totalItemsCount,
        payment_method: selectedPaymentMethod.value,
        payment,
        order,
        qrisDataUrl: qrisDataUrl.value,
        expires_at: expiryDate,
        created_at: new Date().toISOString(),
      })

      startQrisTimer(expiryDate)
      startPollingPaymentStatus(order.id)
    } else if (selectedPaymentMethod.value.startsWith('va_')) {
      activeOrder.value = order
      activePayment.value = payment
      const vaExpiryDate = payment?.expiration_date || new Date(Date.now() + 10 * 60 * 1000).toISOString()
      paymentSuccess.value = false
      isQrisModalOpen.value = true

      cartStore.setPendingOrder({
        id: order.id,
        order_number: order.order_number,
        total_amount: order.total_amount,
        total_items: cartStore.totalItemsCount,
        payment_method: selectedPaymentMethod.value,
        payment,
        order,
        expires_at: vaExpiryDate,
        created_at: new Date().toISOString(),
      })

      startQrisTimer(vaExpiryDate)
      startPollingPaymentStatus(order.id)
    } else {
      // Tunai (Cash) / Bayar di Kasir
      activeOrder.value = order
      activePayment.value = payment
      paymentSuccess.value = false
      isQrisModalOpen.value = true

      cartStore.setPendingOrder({
        id: order.id,
        order_number: order.order_number,
        total_amount: order.total_amount,
        total_items: cartStore.totalItemsCount,
        payment_method: 'cash',
        payment,
        order,
        created_at: new Date().toISOString(),
      })

      // Kosongkan keranjang agar item tidak ganda
      cartStore.items = []
      try {
        localStorage.removeItem('lapaqu_pos_cart_state')
      } catch (e) {}

      startPollingPaymentStatus(order.id)
    }
  } catch (err: any) {
    console.error('Checkout error:', err)
    alert(err.response?.data?.message || 'Gagal memproses pesanan. Silakan coba lagi.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <!-- 0. SKELETON LOADING STATE (Saat Memuat Pesanan Saya) -->
  <div v-if="isLoading" class="select-none space-y-4 pb-36">
    <!-- Grouping Nama & Items dengan gap space-y-3 -->
    <div class="space-y-3">
      <div class="h-5 w-36 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse ml-1" />
      <!-- Skeleton Input Nama (Style Kartu Kode Promo) -->
      <div class="bg-white dark:bg-[#273142] rounded-3xl p-4 shadow-xs flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 flex-1">
          <div class="w-8 h-8 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
          <div class="h-4 w-36 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
      </div>

      <!-- 1. KARTU TUNGGAL SKELETON (ITEMS LIST) -->
      <div
        class="bg-white dark:bg-[#273142] rounded-3xl p-4 sm:p-5 shadow-xs divide-y divide-[#F1F5F9] dark:divide-[#334155]">
      <!-- Item Row Skeletons -->
      <div v-for="n in 2" :key="n" class="py-4 first:pt-0 last:pb-4">
        <div class="flex items-start justify-between gap-4">
          <!-- Left Info Skeleton -->
          <div class="flex-1 min-w-0 space-y-2">
            <div class="h-5 w-3/4 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="space-y-1 pt-1">
              <div class="h-3 w-1/2 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
              <div class="h-3 w-2/5 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            </div>
            <div class="h-5 w-24 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-3" />
          </div>
          <!-- Right: Food Image Skeleton w-24 h-24 rounded-2xl -->
          <div
            class="w-24 h-24 rounded-2xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0 border border-[#E2E8F0]/70 dark:border-[#334155]/60" />
        </div>
        <!-- Bottom Row: Edit Pill & Stepper Skeletons -->
        <div class="flex items-center justify-between mt-3">
          <div class="h-8 w-20 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="w-24 flex items-center justify-between shrink-0">
            <div class="w-7.5 h-7.5 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="h-4 w-6 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="w-7.5 h-7.5 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
        </div>
      </div>

      <!-- Bottom: Tambah Menu Lain Skeleton -->
      <div class="pt-4 flex items-center justify-between gap-3">
        <div class="space-y-2">
          <div class="h-4 w-32 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="h-3 w-48 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
        <div class="h-9.5 w-32 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
      </div>
    </div>
    </div>

    <!-- 2. RINGKASAN PEMBAYARAN SKELETON -->
    <div class="space-y-3">
      <div class="h-5 w-40 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse ml-1" />

      <!-- Promo Card Skeleton -->
      <div class="bg-white dark:bg-[#273142] rounded-3xl p-4 shadow-xs flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 flex-1">
          <div class="w-8 h-8 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
          <div class="h-4 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
        <div class="h-9 w-20 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
      </div>

      <!-- Rincian Biaya Skeleton -->
      <div class="bg-white dark:bg-[#273142] rounded-3xl p-4 sm:p-5 shadow-xs space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between">
          <div class="h-4 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="h-4 w-20 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
        <div class="pt-2 border-t border-[#F1F5F9] dark:border-[#334155]" />
        <div class="flex items-center justify-between">
          <div class="h-5 w-36 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="h-6 w-24 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
      </div>
    </div>

    <!-- Sticky Bottom Dock Skeleton -->
    <div
      class="fixed bottom-0 inset-x-0 w-full md:max-w-md mx-auto bg-white dark:bg-[#273142] rounded-t-3xl px-6 pt-6 pb-8 z-40 border-t border-[#E2E8F0]/70 dark:border-[#334155]/70 shadow-[0_-4px_22px_rgba(0,0,0,0.08)] flex items-center justify-between gap-4">
      <div class="space-y-2">
        <div class="h-3 w-12 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-6 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>
      <div
        class="h-[52px] sm:h-[54px] flex-1 max-w-[260px] sm:max-w-[285px] rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
    </div>
  </div>

  <!-- REAL DATA STATE -->
  <div v-else-if="cartStore.items.length > 0 || isQrisModalOpen || activeOrder" class="select-none">
    <!-- Content List & Ringkasan -->
    <div class="space-y-4 pb-36">
      <!-- Banner Notifikasi Waktu Pembayaran Habis (Hangus) & Order Ulang -->
      <div v-if="orderExpiredNotice"
        class="p-4 rounded-3xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 shadow-xs flex items-center justify-between gap-3 animate-fade-in">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-full bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-xs">
            <AppIcon name="error_outline" :size="18" />
          </div>
          <p class="text-xs font-semibold leading-relaxed">{{ orderExpiredNotice }}</p>
        </div>
        <button type="button" @click="orderExpiredNotice = ''" class="p-1 text-rose-500 hover:text-rose-700 shrink-0 cursor-pointer">
          <AppIcon name="close" :size="16" />
        </button>
      </div>

      <!-- Banner Pending Payment jika modal ditutup sebelum bayar -->
      <div v-if="hasPendingPayment"
        @click="isQrisModalOpen = true"
        class="p-4 rounded-3xl bg-amber-50 dark:bg-amber-950/40 flex items-center justify-between gap-3 text-amber-800 dark:text-amber-200 shadow-xs cursor-pointer active:scale-[0.99] transition-all">
        <!-- Kiri: Icon Jam + Teks Menunggu Pembayaran & ID -->
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
            <AppIcon name="schedule" :size="18" />
          </div>
          <div class="min-w-0">
            <p class="font-bold text-sm leading-tight text-amber-800 dark:text-amber-200 truncate">Menunggu Pembayaran</p>
            <p class="text-[11px] font-medium opacity-80 mt-0.5 tabular-nums text-amber-700/90 dark:text-amber-300/80">
              #{{ activeOrder?.order_number || cartStore.pendingOrder?.order_number || activeOrder?.id }}
            </p>
          </div>
        </div>

        <!-- Kanan: Timer Countdown Model Placeholder Abu-Abu (Tanpa Border) -->
        <div class="flex items-center gap-1 select-none shrink-0">
          <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-200/80 dark:bg-slate-700/70 text-slate-700 dark:text-slate-200 font-bold text-sm sm:text-base flex items-center justify-center shadow-2xs">
            {{ qrisTimerDigits.m1 }}
          </div>
          <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-200/80 dark:bg-slate-700/70 text-slate-700 dark:text-slate-200 font-bold text-sm sm:text-base flex items-center justify-center shadow-2xs">
            {{ qrisTimerDigits.m2 }}
          </div>
          <span class="text-sm sm:text-base font-bold text-slate-500 dark:text-slate-400 leading-none select-none text-center px-0.5">:</span>
          <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-200/80 dark:bg-slate-700/70 text-slate-700 dark:text-slate-200 font-bold text-sm sm:text-base flex items-center justify-center shadow-2xs">
            {{ qrisTimerDigits.s1 }}
          </div>
          <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-200/80 dark:bg-slate-700/70 text-slate-700 dark:text-slate-200 font-bold text-sm sm:text-base flex items-center justify-center shadow-2xs">
            {{ qrisTimerDigits.s2 }}
          </div>
        </div>
      </div>

      <!-- 1. SECTION DAFTAR PESANAN & NAMA (Gap space-y-3 persis seperti promo & rincian harga) -->
      <div class="space-y-3">
        <!-- Teks Penjelas Informasi Pesanan -->
        <h3 v-if="!hasPendingPayment" class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white px-1">
          {{ t('orderSummary.title') }}
        </h3>

        <!-- KARTU NAMA PEMESAN (Posisi di Paling Atas, Style Persis Seperti Kartu Kode Promo) -->
        <div v-if="!hasPendingPayment" class="bg-white dark:bg-[#273142] rounded-3xl p-4 shadow-xs">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div
                class="w-8 h-8 rounded-full bg-[#4880FF]/10 dark:bg-[#4880FF]/20 text-[#4880FF] flex items-center justify-center shrink-0">
                <AppIcon name="person" :size="18" />
              </div>
              <input
                v-model="cartStore.customerName"
                type="text"
                placeholder="Nama Pemesan (opsional)"
                class="w-full bg-transparent text-sm font-semibold text-[#1E293B] dark:text-white placeholder:text-[#94A3B8] outline-none"
              />
            </div>
            <button
              v-if="cartStore.customerName"
              type="button"
              @click="cartStore.customerName = ''"
              class="p-1 text-[#64748B] hover:text-[#1E293B] dark:text-[#94A3B8] dark:hover:text-white cursor-pointer shrink-0"
              title="Hapus Nama"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </div>

        <!-- DAFTAR ITEM PESANAN (KARTU TUNGGAL TERGABUNG SESUAI REFERENSI) -->
        <div
          class="bg-white dark:bg-[#273142] rounded-3xl p-4 sm:p-5 shadow-xs transition-colors divide-y divide-[#F1F5F9] dark:divide-[#334155]">
        <!-- List Item Pesanan -->
        <div v-for="item in cartStore.items" :key="item.id" class="py-4 first:pt-0 last:pb-4 transition-colors">
          <!-- Baris Atas: Info Item di Kiri & Foto di Kanan -->
          <div class="flex items-start justify-between gap-4">
            <!-- Left Info -->
            <div class="flex-1 min-w-0">
              <!-- Judul Menu -->
              <h3 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white leading-snug line-clamp-2">
                {{ formatTitleCase(item.menuItem.name) }}
              </h3>

              <!-- Daftar Opsi Varian (Ice Level, Sweetness, dll) -->
              <div class="mt-1 space-y-0.5 text-xs text-[#64748B] dark:text-[#94A3B8]">
                <div v-for="(opt, idx) in getItemOptionList(item)" :key="idx"
                  class="flex items-center gap-2 leading-snug">
                  <span class="font-semibold text-[#1E293B] dark:text-white">{{ opt.label }} :</span>
                  <span>{{ opt.value }}</span>
                </div>
              </div>

              <!-- Catatan Khusus Item -->
              <p v-if="item.notes" class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-1 line-clamp-2 font-medium">
                <span class="italic">catatan : {{ item.notes }}</span>
              </p>
            </div>

            <!-- Right: Foto Makanan (Rounded Squircle) -->
            <div
              class="w-24 h-24 rounded-2xl overflow-hidden shrink-0 bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0]/70 dark:border-[#334155]/60 shadow-2xs">
              <img
                :src="item.menuItem.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&auto=format&fit=crop&q=80'"
                :alt="item.menuItem.name" class="w-full h-full object-cover" />
            </div>
          </div>

          <!-- Harga Item (Di Bawah Varian) & Total Item Sejajar Saat Menunggu Pembayaran -->
          <div class="mt-2 flex items-center justify-between">
            <span class="text-base font-bold text-[#1E293B] dark:text-white tabular-nums tracking-tight">
              {{ formatCurrency(item.subtotal) }}
            </span>
            <!-- Total Item di sebelah kanan sejajar harga saat Lanjutkan Pembayaran -->
            <span v-if="hasPendingPayment" class="text-sm font-bold text-[#1E293B] dark:text-white tabular-nums">
              {{ item.quantity }} item
            </span>
          </div>

          <!-- Baris Bawah: Tombol Edit Pill di Kiri, Stepper Qty di Kanan (Hanya tampil jika belum pending payment) -->
          <div v-if="!hasPendingPayment" class="flex items-center justify-between mt-3">
            <!-- Tombol Edit Pill -->
            <button type="button" @click="handleEditItem(item)"
              class="h-8 px-4 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] hover:bg-slate-50 dark:hover:bg-slate-800 text-[#1E293B] dark:text-white text-xs font-bold flex items-center gap-2 transition-all active:scale-95 shadow-2xs cursor-pointer">
              <AppIcon name="edit" :size="14" />
              <span>Edit</span>
            </button>

            <!-- Counter Qty Stepper (- Qty +) dengan fixed width 96px (w-24) agar tidak bergeser/berubah ukuran saat puluhan -->
            <div class="w-24 flex items-center justify-between shrink-0">
              <button type="button" @click="cartStore.updateQuantity(item.id, -1)"
                class="w-7.5 h-7.5 shrink-0 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs flex items-center justify-center text-[#202224] dark:text-white hover:bg-[#E2E8F0] dark:hover:bg-[#3B4758] transition-all cursor-pointer active:scale-90"
                title="Kurangi">
                <AppIcon name="remove" :size="15" />
              </button>
              <span
                class="flex-1 text-center text-sm font-bold text-[#202224] dark:text-white tabular-nums select-none">
                {{ item.quantity }}
              </span>
              <button type="button" @click="cartStore.updateQuantity(item.id, 1)"
                class="w-7.5 h-7.5 shrink-0 rounded-full bg-[#4880FF] hover:bg-[#3971F0] text-white shadow-xs flex items-center justify-center transition-all cursor-pointer active:scale-90"
                title="Tambah">
                <AppIcon name="add" :size="15" />
              </button>
            </div>
          </div>
        </div>

        <!-- Bagian Bawah: Need anything else? / Tambah Menu Lain -->
        <div v-if="!hasPendingPayment" class="pt-4 flex items-center justify-between gap-3">
          <div class="flex flex-col min-w-0 pr-2">
            <span class="text-sm font-bold text-[#1E293B] dark:text-white">Ada pesanan lain?</span>
            <span class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5">Tambah menu lezat lainnya jika ingin.</span>
          </div>
          <button type="button" @click="router.push(menuUrl)"
            class="h-10 px-4 sm:px-5 rounded-full border border-[#4880FF] text-[#4880FF] hover:bg-blue-50 dark:hover:bg-blue-950/40 active:scale-95 text-xs sm:text-sm flex items-center justify-center transition-all shrink-0 cursor-pointer">
            <span class="font-bold tracking-tight">{{ t('orderSummary.addMoreItems') }}</span>
          </button>
        </div>
      </div>
      </div>

      <!-- 2. SECTION RINGKASAN PEMBAYARAN -->
      <div class="space-y-3">
        <!-- Teks Penjelas Ringkasan Pembayaran -->
        <h3 class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white px-1">
          {{ t('orderSummary.summaryTitle') }}
        </h3>

        <!-- KARTU KODE PROMO (Di Bawah Teks Ringkasan Pembayaran) -->
        <div class="bg-white dark:bg-[#273142] rounded-3xl p-4 shadow-xs">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div
                class="w-8 h-8 rounded-full bg-[#4880FF]/10 dark:bg-[#4880FF]/20 text-[#4880FF] flex items-center justify-center shrink-0">
                <AppIcon name="sell" :size="18" />
              </div>
              <input v-model="promoCodeInput" type="text" placeholder="Kode Promo" :disabled="hasPendingPayment || !!appliedPromo"
                @keyup.enter="handleApplyPromo"
                class="w-full bg-transparent text-sm font-semibold text-[#1E293B] dark:text-white placeholder:text-[#94A3B8] outline-none disabled:opacity-60" />
            </div>

            <!-- Tombol Pakai / Hapus -->
            <button v-if="!appliedPromo" type="button" @click="handleApplyPromo" :disabled="hasPendingPayment"
              class="h-9 px-5 rounded-full bg-[#4880FF] hover:bg-[#3971F0] disabled:opacity-50 disabled:pointer-events-none text-white font-bold text-xs sm:text-sm shadow-sm active:scale-95 transition-all cursor-pointer shrink-0">
              Pakai
            </button>
            <button v-else type="button" @click="handleRemovePromo" :disabled="hasPendingPayment"
              class="h-9 px-4 rounded-full bg-red-50 dark:bg-red-950/40 disabled:opacity-50 disabled:pointer-events-none text-red-500 font-bold text-xs sm:text-sm active:scale-95 transition-all cursor-pointer shrink-0">
              Hapus
            </button>
          </div>

          <!-- Feedback Kode Promo -->
          <div v-if="appliedPromo" class="mt-2 text-xs font-semibold text-emerald-500 flex items-center gap-1">
            <AppIcon name="check_circle" :size="14" />
            <span>{{ appliedPromo.title }} terpasang (-{{ formatCurrency(appliedPromo.discount) }})</span>
          </div>
          <div v-if="promoError" class="mt-2 text-xs font-semibold text-red-500 flex items-center gap-1">
            <AppIcon name="error" :size="14" />
            <span>{{ promoError }}</span>
          </div>
        </div>

        <!-- KARTU RINCIAN PEMBAYARAN (RINGKASAN BIAYA) -->
        <div class="bg-white dark:bg-[#273142] rounded-3xl p-4 sm:p-6 shadow-xs space-y-3 text-sm">
          <div class="flex items-center justify-between text-[#64748B] dark:text-[#94A3B8]">
            <span>{{ t('orderSummary.subtotal') }}</span>
            <span class="font-bold text-[#1E293B] dark:text-white tabular-nums">{{ formatCurrency(subtotal) }}</span>
          </div>

          <div class="flex items-center justify-between text-[#64748B] dark:text-[#94A3B8]">
            <span>{{ t('orderSummary.service') }}</span>
            <span class="font-bold text-[#1E293B] dark:text-white tabular-nums">{{ formatCurrency(serviceFee) }}</span>
          </div>

          <div class="flex items-center justify-between text-[#64748B] dark:text-[#94A3B8]">
            <span>{{ t('orderSummary.tax') }}</span>
            <span class="font-bold text-[#1E293B] dark:text-white tabular-nums">{{ formatCurrency(taxFee) }}</span>
          </div>

          <div v-if="appliedPromo" class="flex items-center justify-between text-emerald-500">
            <span>Potongan Promo</span>
            <span class="font-bold tabular-nums">-{{ formatCurrency(appliedPromo.discount) }}</span>
          </div>

          <!-- Separator Divider -->
          <div class="pt-2 border-t border-[#F1F5F9] dark:border-[#334155]" />

          <!-- Total Keseluruhan -->
          <div class="flex items-center justify-between">
            <span class="text-base font-bold text-[#1E293B] dark:text-white">{{ t('orderSummary.grandTotal') }}</span>
            <span class="text-lg font-bold text-[#1E293B] dark:text-white tabular-nums tracking-tight">
              {{ formatCurrency(grandTotal) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 4. STICKY BOTTOM DOCK (TOTAL + BUTTON PILIH / LANJUTKAN PEMBAYARAN) -->
    <AppBottomActionBar
      v-if="cartStore.items.length > 0 || hasPendingPayment"
      :total-price="hasPendingPayment && activeOrder ? activeOrder.total_amount : grandTotal"
      :button-text="hasPendingPayment ? t('cart.checkout') : t('orderSummary.selectPayment')"
      :button-variant="hasPendingPayment ? 'warning' : 'primary'"
      :button-icon="hasPendingPayment ? 'arrow_forward' : ''"
      @click="handleBottomDockAction"
    />

    <!-- 5. REUSABLE BOTTOM SHEET MODAL METODE PEMBAYARAN -->
    <AppBottomSheetModal ref="paymentBottomSheetRef" v-model="isPaymentModalOpen" :title="t('orderSummary.selectPayment')"
      :show-close-button="false" height="h-auto max-h-[90dvh]" :modal-style="paymentModalStyle"
      :scrollable="isOtherBankOpen || isScrollingBack || isContentOverflowing">
      <div class="px-6 py-4 space-y-4">
        <!-- KELOMPOK 1: Default (QRIS) -->
        <div class="space-y-2">
          <h4 class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white px-0.5">
            Default
          </h4>
          <div @click="selectedPaymentMethod = 'qris'"
            class="flex items-center justify-between px-4 py-3 min-h-[64px] rounded-2xl border transition-all cursor-pointer bg-white dark:bg-[#1E293B]"
            :class="selectedPaymentMethod === 'qris'
              ? 'border-[#4880FF] ring-1 ring-[#4880FF]/20 shadow-xs'
              : 'border-[#E2E8F0] dark:border-[#334155] hover:border-[#4880FF]/50'">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-16 h-8 flex items-center shrink-0">
                <img :src="qrisSvg" alt="QRIS" class="max-h-7 max-w-full object-contain object-left dark:invert" />
              </div>
              <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">QRIS</span>
            </div>
            <!-- Radio Button Indicator Bulat -->
            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0 ml-2"
              :class="selectedPaymentMethod === 'qris' ? 'border-[#4880FF]' : 'border-[#CBD5E1] dark:border-[#475569]'">
              <div v-if="selectedPaymentMethod === 'qris'" class="w-2.5 h-2.5 rounded-full bg-[#4880FF]" />
            </div>
          </div>
        </div>

        <!-- KELOMPOK 2: Virtual Account -->
        <div class="space-y-3">
          <h4 class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white px-0.5 mb-0.5">
            Virtual Account
          </h4>

          <!-- Main VA: Mandiri, BRI, BNI -->
          <div v-for="bank in MAIN_VA_BANKS" :key="bank.id" @click="selectedPaymentMethod = bank.id"
            class="flex items-center justify-between px-4 py-3 min-h-[64px] rounded-2xl border transition-all cursor-pointer bg-white dark:bg-[#1E293B]"
            :class="selectedPaymentMethod === bank.id
              ? 'border-[#4880FF] ring-1 ring-[#4880FF]/20 shadow-xs'
              : 'border-[#E2E8F0] dark:border-[#334155] hover:border-[#4880FF]/50'">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-16 h-8 flex items-center shrink-0">
                <img v-if="bank.logo" :src="bank.logo" :alt="bank.shortName"
                  class="max-h-7 max-w-full object-contain object-left" />
              </div>
              <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">{{ bank.name
                }}</span>
            </div>

            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0 ml-2"
              :class="selectedPaymentMethod === bank.id ? 'border-[#4880FF]' : 'border-[#CBD5E1] dark:border-[#475569]'">
              <div v-if="selectedPaymentMethod === bank.id" class="w-2.5 h-2.5 rounded-full bg-[#4880FF]" />
            </div>
          </div>

          <!-- Bank Lainnya (Dropdown) -->
          <div ref="otherBankRef" class="rounded-2xl border overflow-hidden bg-white dark:bg-[#1E293B] transition-all"
            :class="(isOtherBankSelected && !isOtherBankOpen)
              ? 'border-[#4880FF] ring-1 ring-[#4880FF]/20 shadow-xs'
              : 'border-[#E2E8F0] dark:border-[#334155]'">
            <!-- Trigger Dropdown (Container Logo w-16 h-8 Pixel Perfect dengan BNI/Mandiri/BRI) -->
            <div @click="toggleOtherBank"
              class="flex items-center justify-between px-4 py-3 min-h-[64px] cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40 select-none">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-16 h-8 flex items-center shrink-0">
                  <div
                    class="w-10 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[#4880FF] flex items-center justify-center shrink-0">
                    <AppIcon name="account_balance" :size="20" />
                  </div>
                </div>
                <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">
                  Bank Lainnya
                </span>
              </div>

              <div class="flex items-center gap-2 shrink-0 ml-2">
                <AppIcon name="expand_more" :size="20"
                  class="text-[#64748B] dark:text-[#94A3B8] transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                  :class="isOtherBankOpen ? 'rotate-180 text-[#4880FF]' : ''" />
              </div>
            </div>

            <!-- List Sub-Bank: Animasi Smooth Expand & Collapse Menggunakan CSS Grid -->
            <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
              :class="isOtherBankOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'">
              <div class="overflow-hidden bg-white dark:bg-[#1E293B]">
                <div class="px-2 pb-2 pt-1 space-y-2">
                  <div v-for="bank in OTHER_VA_BANKS" :key="bank.id" @click.stop="selectedPaymentMethod = bank.id"
                    class="flex items-center justify-between px-3 py-3 transition-all cursor-pointer select-none rounded-xl border"
                    :class="selectedPaymentMethod === bank.id
                      ? 'border-[#4880FF] ring-1 ring-[#4880FF]/20 bg-blue-50/30 dark:bg-[#4880FF]/10'
                      : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/40'">
                    <div class="flex items-center gap-3 min-w-0">
                      <!-- Container Logo persis sama dengan BNI, Mandiri, BRI (w-16 h-8 object-left) -->
                      <div class="w-16 h-8 flex items-center shrink-0">
                        <img v-if="bank.logo" :src="bank.logo" :alt="bank.shortName"
                          class="max-h-7 max-w-full object-contain object-left" />
                        <span v-else class="px-2 py-0.5 rounded text-[10px] font-black tracking-wider text-white"
                          :style="{ backgroundColor: bank.bgColor }">
                          {{ bank.code }}
                        </span>
                      </div>
                      <!-- Text Label persis sama dengan BNI, Mandiri, BRI (posisi tepat di x = 94px) -->
                      <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">
                        {{ bank.name }}
                      </span>
                    </div>

                    <div
                      class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0 ml-2"
                      :class="selectedPaymentMethod === bank.id ? 'border-[#4880FF]' : 'border-[#CBD5E1] dark:border-[#475569]'">
                      <div v-if="selectedPaymentMethod === bank.id" class="w-2.5 h-2.5 rounded-full bg-[#4880FF]" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- KELOMPOK 3: Bayar di Kasir (Tunai) -->
        <div class="space-y-2">
          <h4 class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white px-0.5">
            Bayar di Kasir
          </h4>
          <div @click="selectedPaymentMethod = 'cash'"
            class="flex items-center justify-between px-4 py-3 min-h-[64px] rounded-2xl border transition-all cursor-pointer bg-white dark:bg-[#1E293B]"
            :class="selectedPaymentMethod === 'cash'
              ? 'border-[#4880FF] ring-1 ring-[#4880FF]/20 shadow-xs'
              : 'border-[#E2E8F0] dark:border-[#334155] hover:border-[#4880FF]/50'">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-16 h-8 flex items-center shrink-0">
                <div
                  class="w-9 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-500 flex items-center justify-center shrink-0">
                  <AppIcon name="payments" :size="20" />
                </div>
              </div>
              <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">Tunai</span>
            </div>
            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0 ml-2"
              :class="selectedPaymentMethod === 'cash' ? 'border-[#4880FF]' : 'border-[#CBD5E1] dark:border-[#475569]'">
              <div v-if="selectedPaymentMethod === 'cash'" class="w-2.5 h-2.5 rounded-full bg-[#4880FF]" />
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="px-6 pt-3 pb-4 sm:pb-6 bg-white dark:bg-[#273142]">
          <AppBottomActionBar :is-fixed="false" :total-price="grandTotal" button-text="Bayar Sekarang"
            :loading="isSubmitting" @click="handleConfirmPayment" />
        </div>
      </template>
    </AppBottomSheetModal>

    <!-- 6. MODAL TAMPILAN QRIS DINAMIS (XENDIT SANDBOX) -->
    <Teleport to="body">
      <!-- Backdrop Overlay -->
      <Transition enter-active-class="transition-opacity duration-200 ease-out" enter-from-class="opacity-0"
        enter-to-class="opacity-100" leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="isQrisModalOpen" @click="handleClosePaymentModal" @touchmove.prevent
          :class="[
            'fixed inset-0 bg-black/60 z-50 cursor-pointer touch-none transition-opacity duration-240 ease-in-out',
            isClosingQrisByDrag ? 'opacity-0 pointer-events-none' : 'opacity-100'
          ]" />
      </Transition>

      <!-- Bottom Sheet Modal Container (Draggable & Drag-to-Close) -->
      <Transition :css="!isClosingQrisByDrag" name="sheet-modal">
        <div ref="qrisModalRef" v-if="isQrisModalOpen && activeOrder"
          @touchstart="onQrisTouchStart"
          @touchmove="onQrisTouchMove"
          @touchend="onQrisTouchEnd"
          @touchcancel="onQrisTouchEnd"
          @pointerdown="onQrisPointerDown"
          :style="[
            isQrisPayment ? {
              background: 'linear-gradient(180deg, #5B8EFF 0%, #4880FF 40%, #225FDE 100%)',
              backgroundColor: '#4880FF'
            } : undefined,
            isClosingQrisByDrag ? {
              transform: 'translateY(100%)',
              transition: 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
            } : (isDraggingQris || qrisDragTranslateY > 0) ? {
              transform: `translateY(${qrisDragTranslateY}px)`,
              transition: isDraggingQris ? 'none' : 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
            } : undefined
          ]"
          :class="[
            'fixed inset-x-0 bottom-0 w-full md:max-w-md mx-auto z-50 rounded-t-3xl shadow-2xl select-none max-h-[90dvh] flex flex-col overflow-hidden overscroll-contain transform-gpu',
            isQrisPayment
              ? 'text-white h-auto'
              : (isCashPayment
                ? 'bg-white dark:bg-[#273142] h-auto max-h-[88dvh]'
                : 'bg-white dark:bg-[#273142] h-[90dvh]')
          ]">
          <!-- Top Drag Handle bar -->
          <div class="pt-3 pb-2 shrink-0 select-none cursor-grab active:cursor-grabbing w-full flex items-center justify-center"
            title="Tarik ke bawah untuk menutup modal">
            <div :class="[
              'w-12 h-1 rounded-full pointer-events-none',
              isQrisPayment
                ? 'bg-white/40'
                : 'bg-[#CBD5E1] dark:bg-[#475569]'
            ]" />
          </div>

          <!-- Fixed Header Bayar di Kasir (Tunai) -->
          <div v-if="isCashPayment" class="shrink-0 px-5 pt-1.5 bg-white dark:bg-[#273142]">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-full bg-[#4880FF]/10 dark:bg-[#4880FF]/20 text-[#4880FF] flex items-center justify-center shrink-0">
                <AppIcon name="payments" :size="20" />
              </div>
              <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white truncate">
                Bayar di Kasir (Tunai)
              </span>
            </div>
            <div class="border-t border-dashed border-slate-200 dark:border-[#334155] mt-4"></div>
          </div>

          <!-- Fixed Header Virtual Account (Logo & Nama Bank + Dashed Line, Perfect Pixel, Jarak 2x Lipat ke Nomor VA) -->
          <div v-if="isVaPayment" class="shrink-0 px-5 pt-1.5 bg-white dark:bg-[#273142]">
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
            <!-- Dashed horizontal line berjarak 16px (mt-4), lalu jarak ke div no VA 16px (pt-4) = total 32px (2x lipat) -->
            <div class="border-t border-dashed border-slate-200 dark:border-[#334155] mt-4"></div>
          </div>

          <!-- Internal Scroll Body -->
          <div ref="qrisScrollRef" :class="[
            'overflow-y-auto px-5 no-scrollbar overscroll-contain',
            isQrisPayment ? 'flex-initial pb-0' : 'flex-1 pb-8'
          ]">
            <!-- Banner Berhasil jika sudah bayar -->
            <div v-if="paymentSuccess"
              class="mb-3 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 flex items-center gap-3 text-emerald-700 dark:text-emerald-300">
              <div
                class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                <AppIcon name="check" :size="20" />
              </div>
              <div class="text-xs">
                <p class="font-bold">Pembayaran Berhasil Diterima!</p>
                <p class="opacity-90">Mengalihkan ke status pesanan...</p>
              </div>
            </div>

            <!-- TAMPILAN JIKA QRIS: KARTU GRADIENT MENTOK DENGAN HEADER, RESTO & QR CODE -->
            <template v-if="isQrisPayment">
              <div class="pt-4 pb-10 sm:pb-12">
                <!-- 1. Header Bar: Logo QRIS (kiri), Timer Digit (Tepat di Tengah Tegak Lurus), & Logo Xendit (kanan) -->
                <div class="relative flex items-center justify-between px-2 h-9">
                  <!-- Sisi Kiri: Logo QRIS -->
                  <div class="flex items-center justify-start shrink-0">
                    <img :src="qrisSvg" alt="QRIS" class="h-6.5 sm:h-7.5 object-contain"
                      style="filter: brightness(0) invert(1);" />
                  </div>

                  <!-- Tengah: Timer Countdown Model Placeholder Abu-abu (Tegak Lurus Center Pixel-Perfect dengan Nama Kafe) -->
                  <div class="absolute left-1/2 -translate-x-1/2 flex items-center gap-1 select-none">
                    <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/60 dark:border-slate-700 font-bold text-sm sm:text-base flex items-center justify-center shadow-xs">
                      {{ qrisTimerDigits.m1 }}
                    </div>
                    <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/60 dark:border-slate-700 font-bold text-sm sm:text-base flex items-center justify-center shadow-xs">
                      {{ qrisTimerDigits.m2 }}
                    </div>
                    <span class="text-sm sm:text-base font-bold text-white leading-none select-none text-center px-0">:</span>
                    <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/60 dark:border-slate-700 font-bold text-sm sm:text-base flex items-center justify-center shadow-xs">
                      {{ qrisTimerDigits.s1 }}
                    </div>
                    <div class="w-6.5 h-7.5 sm:w-7 sm:h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/60 dark:border-slate-700 font-bold text-sm sm:text-base flex items-center justify-center shadow-xs">
                      {{ qrisTimerDigits.s2 }}
                    </div>
                  </div>

                  <!-- Sisi Kanan: Logo Xendit Pengganti GPN -->
                  <div class="flex flex-col items-center opacity-95 shrink-0">
                    <img :src="xenditSvg" alt="Xendit" class="h-5 w-5 sm:h-5.5 sm:w-5.5 object-contain" />
                    <span class="text-[9px] font-black text-white tracking-wider mt-0.5 uppercase">XENDIT</span>
                  </div>
                </div>

                <!-- 2. Nama Resto & NMID (Posisi diangkat lebih ke atas) -->
                <div class="text-center space-y-0.5 mt-4 sm:mt-5">
                  <h3 class="text-base sm:text-lg font-black text-white tracking-tight drop-shadow-xs">
                    {{ activeOrder.outlet?.name || activeOrder.outletName || '' }}
                  </h3>
                  <p class="text-[11px] font-mono text-white/85 tracking-wider">
                    NMID : {{ activePayment?.nmid || '' }}
                  </p>
                </div>

                <!-- 3. Kotak Putih QR Code (Posisi diangkat lebih ke atas) -->
                <div
                  class="bg-white p-3.5 sm:p-4 rounded-2xl shadow-sm mx-auto w-full aspect-square max-w-[250px] sm:max-w-[270px] flex items-center justify-center mt-3.5 sm:mt-4">
                  <img v-if="qrisDataUrl" :src="qrisDataUrl" alt="QRIS Code" class="w-full h-full object-contain" />
                  <div v-else
                    class="w-full h-full flex flex-col items-center justify-center text-xs text-slate-400 gap-2">
                    <AppSpinner :size="24" color="text-[#4880FF]" />
                    <span>Memuat QRIS...</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- TAMPILAN JIKA TUNAI (CASH): INSTRUKSI BAYAR DI KASIR -->
            <template v-else-if="isCashPayment">
              <div class="pt-3 pb-6 space-y-4">
                <!-- Kartu Informasi Pesanan & Total -->
                <div class="p-4 rounded-2xl bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] space-y-3 shadow-xs">
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium">Nomor Pesanan</span>
                    <span class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white">#{{ activeOrder?.order_number }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium">Nomor Meja</span>
                    <span class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white">Meja {{ tableCode }}</span>
                  </div>
                  <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-semibold text-[#64748B] dark:text-[#94A3B8]">Total Pembayaran</span>
                    <span class="text-base sm:text-lg font-black text-[#4880FF]">Rp {{ formatNumber(activeOrder?.total_amount || 0) }}</span>
                  </div>
                </div>

                <!-- Box Petunjuk Kasir -->
                <div class="p-3.5 rounded-2xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 flex items-start gap-3 shadow-xs">
                  <div class="w-8 h-8 rounded-full bg-[#4880FF] text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                    <AppIcon name="storefront" :size="18" />
                  </div>
                  <div class="text-xs text-[#1E293B] dark:text-slate-200 space-y-1">
                    <p class="font-bold text-[#4880FF]">Silakan Menuju ke Kasir</p>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                      Sebutkan nomor pesanan <strong>#{{ activeOrder?.order_number }}</strong> atau <strong>Meja {{ tableCode }}</strong> kepada kasir untuk membayar tunai.
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                      *Pesanan akan otomatis diproses dapur setelah kasir mengonfirmasi pembayaran Anda.
                    </p>
                  </div>
                </div>

                <!-- Status Live Polling Kasir -->
                <div class="p-3.5 rounded-2xl bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between shadow-xs">
                  <div class="flex items-center gap-3">
                    <div class="w-7 h-7 flex items-center justify-center shrink-0">
                      <AppSpinner :size="20" color="text-[#4880FF]" />
                    </div>
                    <div>
                      <span class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium block leading-none">Status</span>
                      <span class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white mt-1 block leading-tight">Menunggu Konfirmasi Kasir</span>
                    </div>
                  </div>
                </div>

                <!-- Tombol Aksi: Simulasi Bayar (Sandbox) & Batalin Pesanan -->
                <div class="space-y-2 pt-1">
                  <button
                    type="button"
                    :disabled="isSimulating || paymentSuccess"
                    @click="handleSimulatePayment"
                    class="w-full h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] disabled:opacity-50 text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer"
                  >
                    <AppIcon name="bolt" :size="18" />
                    <span v-if="!isSimulating">Simulasikan Konfirmasi Kasir (Sandbox)</span>
                    <span v-else>Memproses Konfirmasi Kasir...</span>
                  </button>

                  <button
                    type="button"
                    :disabled="isCancelling || paymentSuccess"
                    @click="handleCancelTransfer"
                    class="w-full h-11 rounded-full bg-[#EF3826] hover:bg-red-600 active:scale-[0.98] disabled:opacity-60 text-white font-bold text-xs sm:text-sm shadow-md shadow-red-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer"
                  >
                    <AppSpinner v-if="isCancelling" :size="16" color="text-white" />
                    <AppIcon v-else name="close" :size="18" />
                    <span>{{ isCancelling ? 'Membatalkan...' : 'Batalin Pesanan' }}</span>
                  </button>
                </div>
              </div>
            </template>

            <!-- TAMPILAN JIKA VIRTUAL ACCOUNT -->
            <template v-else-if="isVaPayment">
              <div class="space-y-4 pt-4 pb-4">
                <!-- 1. Kartu Nomor Virtual Account (Aksen Gradasi Biru ke Transparan) -->
                <div
                  @click="copyToClipboard(activePayment?.account_number || '', 'va')"
                  class="p-5 sm:p-6 rounded-2xl sm:rounded-3xl bg-gradient-to-b from-[#4880FF]/20 via-[#4880FF]/5 to-transparent border-2 border-[#4880FF]/50 dark:border-[#4880FF]/60 shadow-xs flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-150 active:scale-[0.99] hover:border-[#4880FF] group select-none relative overflow-hidden"
                  title="Klik untuk salin nomor Virtual Account"
                >
                  <p
                    class="text-xl sm:text-2xl font-black tracking-wider text-[#1E293B] dark:text-white font-mono select-all">
                    {{ activePayment?.account_number || '88908XXXXXXXX' }}
                  </p>
                  <p class="text-xs sm:text-sm font-bold text-[#4880FF] dark:text-[#60A5FA] mt-2 flex items-center justify-center gap-1.5 transition-colors">
                    <AppIcon v-if="copiedVa" name="check" :size="16" />
                    <span>{{ copiedVa ? 'Nomor berhasil disalin!' : 'Klik untuk salin nomor' }}</span>
                  </p>
                </div>

                <!-- Teks Batas Waktu Bayar di Bawah Div Kartu VA (Rata Tengah) -->
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

                <!-- 2. Tombol Tatacara Bayar VA (Default Variant Outlined) -->
                <div>
                  <button
                    type="button"
                    @click="navigateToVaInstructions"
                    class="w-full py-3.5 px-4.5 sm:px-5 rounded-full bg-transparent border border-[#CBD5E1] dark:border-[#475569] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] text-[#334155] dark:text-[#E2E8F0] active:scale-[0.98] flex items-center justify-between transition-all duration-150 cursor-pointer shadow-2xs group"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <!-- Icon List Biru (Aksen Ijo jadi Biru) -->
                      <svg class="w-5.5 h-5.5 text-[#4880FF] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="9" y1="6" x2="20" y2="6" />
                        <line x1="9" y1="12" x2="20" y2="12" />
                        <line x1="9" y1="18" x2="20" y2="18" />
                        <circle cx="4" cy="6" r="1.2" fill="currentColor" />
                        <circle cx="4" cy="12" r="1.2" fill="currentColor" />
                        <circle cx="4" cy="18" r="1.2" fill="currentColor" />
                      </svg>
                      <span class="text-sm sm:text-base font-medium text-[#334155] dark:text-[#E2E8F0] group-hover:text-[#4880FF] transition-colors truncate">
                        Cara bayar pakai virtual account
                      </span>
                    </div>

                    <!-- Arrow Right Biru -->
                    <svg class="w-5.5 h-5.5 text-[#4880FF] shrink-0 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="5" y1="12" x2="19" y2="12" />
                      <polyline points="13 6 19 12 13 18" />
                    </svg>
                  </button>
                </div>

                <!-- Dash Horizontal Line -->
                <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

                <!-- 3. Bagian Status & Stepper Progres -->
                <div>
                  <!-- Header Accordion Status -->
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <!-- Spinner Loading -->
                      <div class="w-7 h-7 flex items-center justify-center shrink-0">
<AppSpinner :size="20" color="text-[#4880FF]" />
                      </div>
                      <div>
                        <span class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium block leading-none">Status</span>
                        <span class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white leading-tight mt-1 block">Nomor VA dibuat</span>
                      </div>
                    </div>
                    <button type="button" @click="isVaStatusOpen = !isVaStatusOpen"
                      class="w-8 h-8 rounded-full flex items-center justify-center text-[#64748B] dark:text-[#94A3B8] hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                      title="Toggle Progres Status">
                      <AppIcon name="expand_more" :size="22" class="transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]" :class="{ 'rotate-180': isVaStatusOpen }" />
                    </button>
                  </div>

                  <!-- Card Timeline Stepper (Collapsible with Smooth Height Animation via CSS Grid) -->
                  <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                    :class="isVaStatusOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'">
                    <div class="overflow-hidden">
                      <div class="pt-3">
                        <div class="p-4 rounded-2xl bg-[#F8FAFC] dark:bg-[#1A2232] border border-[#E2E8F0] dark:border-[#334155]/80 space-y-0 shadow-2xs">
                          <!-- Step 1: Nomor VA dibuat -->
                          <div class="flex items-start gap-3.5">
                            <div class="flex flex-col items-center">
                              <div class="w-6 h-6 rounded-full bg-[#4880FF] text-white flex items-center justify-center shrink-0 shadow-xs">
                                <AppIcon name="check" :size="15" />
                              </div>
                              <div class="w-0.5 h-7 bg-[#4880FF] my-0.5"></div>
                            </div>
                            <div class="pt-0.5">
                              <p class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white">Nomor VA dibuat</p>
                            </div>
                          </div>

                          <!-- Step 2: Pembayaran diterima -->
                          <div class="flex items-start gap-3.5">
                            <div class="flex flex-col items-center">
                              <div :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center shrink-0 shadow-xs transition-colors',
                                paymentSuccess ? 'bg-[#4880FF] text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400'
                              ]">
                                <AppIcon v-if="paymentSuccess" name="check" :size="15" />
                                <span v-else class="text-xs tracking-tighter leading-none font-bold">···</span>
                              </div>
                              <div :class="['w-0.5 h-7 my-0.5 transition-colors', paymentSuccess ? 'bg-[#4880FF]' : 'bg-slate-200 dark:bg-slate-700']"></div>
                            </div>
                            <div class="pt-0.5">
                              <p :class="['text-xs sm:text-sm font-semibold transition-colors', paymentSuccess ? 'text-[#1E293B] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]']">
                                Pembayaran diterima
                              </p>
                            </div>
                          </div>

                          <!-- Step 3: Transaksi diproses layanan -->
                          <div class="flex items-start gap-3.5">
                            <div class="flex flex-col items-center">
                              <div :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center shrink-0 shadow-xs transition-colors',
                                paymentSuccess ? 'bg-[#4880FF] text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400'
                              ]">
                                <AppIcon v-if="paymentSuccess" name="check" :size="15" />
                                <span v-else class="text-xs tracking-tighter leading-none font-bold">···</span>
                              </div>
                            </div>
                            <div class="pt-0.5">
                              <p :class="['text-xs sm:text-sm font-semibold transition-colors', paymentSuccess ? 'text-[#1E293B] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]']">
                                Transaksi diproses layanan
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Dash Horizontal Line -->
                <div class="border-t border-dashed border-slate-200 dark:border-[#334155]"></div>

                <!-- 4. Section Rincian Transaksi (Hanya Data Asli Database) -->
                <div>
                  <h4 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white mb-3.5">
                    Rincian transaksi
                  </h4>

                  <div class="space-y-3.5 text-sm sm:text-base">
                    <!-- Status dengan Warna Warning dan Icon Timer -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Status</span>
                      <span
                        v-if="paymentSuccess || activeOrder?.payment_status === 'paid'"
                        class="font-bold text-emerald-500 inline-flex items-center gap-1.5"
                      >
                        <span class="leading-none">Berhasil</span>
                        <AppIcon name="check_circle" :size="18" class="text-emerald-500 shrink-0" />
                      </span>
                      <span
                        v-else-if="isVaExpired"
                        class="font-bold text-rose-500 dark:text-rose-400 inline-flex items-center gap-1.5"
                      >
                        <span class="leading-none">Hangus</span>
                        <AppIcon name="cancel" :size="18" class="text-rose-500 dark:text-rose-400 shrink-0" />
                      </span>
                      <span
                        v-else
                        class="font-bold text-amber-500 dark:text-amber-400 inline-flex items-center gap-1.5"
                      >
                        <span class="leading-none">Menunggu</span>
                        <svg
                          class="w-4 h-4 sm:w-4.5 sm:h-4.5 text-amber-500 dark:text-amber-400 shrink-0"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2.2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        >
                          <circle cx="12" cy="13" r="8" />
                          <path d="M12 2v3" />
                          <path d="M10 2h4" />
                          <path d="M12 9v4l2.5 2.5" />
                        </svg>
                      </span>
                    </div>

                    <!-- Metode pembayaran (Tanpa Icon Bank) -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Metode pembayaran</span>
                      <span class="font-bold text-[#1E293B] dark:text-white">
                        {{ (activeVaInfo?.shortName || activeVaInfo?.code || activePayment?.bank_code || 'VA').toUpperCase() }} VA
                      </span>
                    </div>

                    <!-- Waktu Transaksi Asli -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Waktu</span>
                      <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                        {{ transactionTime }}
                      </span>
                    </div>

                    <!-- Tanggal Transaksi Asli -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Tanggal</span>
                      <span class="font-medium text-[#1E293B] dark:text-white">
                        {{ transactionDate }}
                      </span>
                    </div>

                    <!-- ID transaksi Asli (Nomor Pesanan) -->
                    <div class="flex items-center justify-between gap-3">
                      <span class="text-[#64748B] dark:text-[#94A3B8] shrink-0">ID transaksi</span>
                      <div class="flex items-center gap-1.5 font-mono text-[#1E293B] dark:text-white min-w-0">
                        <span class="truncate max-w-[170px] sm:max-w-[210px] text-sm font-semibold">{{ transactionOrderId }}</span>
                        <button type="button" @click="copyToClipboard(transactionOrderId, 'trxId')"
                          class="text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] active:scale-90 transition-all p-0.5 cursor-pointer shrink-0"
                          title="Salin ID Transaksi">
                          <AppIcon :name="copiedTrxId ? 'check' : 'content_copy'" :size="16" :class="copiedTrxId ? 'text-emerald-500' : ''" />
                        </button>
                      </div>
                    </div>

                    <!-- Meja -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Meja</span>
                      <span class="font-medium text-[#1E293B] dark:text-white">
                        {{ activeOrder?.table?.table_number || activeOrder?.table_token || cartStore.tableCode || tableCode }}
                      </span>
                    </div>

                    <!-- Dash Horizontal Line -->
                    <div class="pt-1 border-t border-dashed border-slate-200 dark:border-[#334155]/80"></div>

                    <!-- Jumlah (Subtotal Makanan/Minuman) -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Jumlah</span>
                      <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                        {{ formatCurrency(vaOrderSubtotal) }}
                      </span>
                    </div>

                    <!-- Biaya layanan -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Biaya layanan</span>
                      <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                        {{ formatCurrency(vaOrderServiceFee) }}
                      </span>
                    </div>

                    <!-- Pajak (PB1 10%) -->
                    <div class="flex items-center justify-between">
                      <span class="text-[#64748B] dark:text-[#94A3B8]">Pajak (PB1 10%)</span>
                      <span class="font-medium text-[#1E293B] dark:text-white tabular-nums">
                        {{ formatCurrency(vaOrderTaxFee) }}
                      </span>
                    </div>

                    <!-- Potongan Promo (jika ada) -->
                    <div v-if="vaOrderDiscount > 0" class="flex items-center justify-between text-emerald-500">
                      <span>Potongan Promo</span>
                      <span class="font-medium tabular-nums">-{{ formatCurrency(vaOrderDiscount) }}</span>
                    </div>

                    <!-- Garis Total -->
                    <div class="pt-2 border-t border-[#F1F5F9] dark:border-[#334155]/60"></div>

                    <!-- Total Asli Pesanan (Bold Saja) -->
                    <div class="flex items-center justify-between pt-0.5">
                      <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white">Total</span>
                      <span class="font-bold text-base sm:text-lg text-[#1E293B] dark:text-white tabular-nums">
                        {{ formatCurrency(activeOrder.total_amount) }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- 5. Tombol Aksi Testing Sandbox & Navigasi -->
                <div class="space-y-2 pt-1">
                  <!-- Tombol Batalin Transfer (Warna Danger) -->
                  <button type="button" :disabled="isCancelling || paymentSuccess" @click="handleCancelTransfer"
                    class="w-full h-11 rounded-full bg-[#EF3826] hover:bg-red-600 active:scale-[0.98] disabled:opacity-60 text-white font-bold text-xs sm:text-sm shadow-md shadow-red-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
                    <AppSpinner v-if="isCancelling" :size="16" color="text-white" />
                    <AppIcon v-else name="close" :size="18" />
                    <span>{{ isCancelling ? 'Membatalkan Transfer...' : 'Batalin Transfer' }}</span>
                  </button>

                  <button type="button" :disabled="isSimulating || paymentSuccess || isVaExpired" @click="handleSimulatePayment"
                    class="w-full h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] disabled:opacity-50 text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
                    <AppIcon :name="isVaExpired ? 'error_outline' : 'bolt'" :size="18" />
                    <span v-if="isVaExpired">Virtual Account Hangus</span>
                    <span v-else-if="!isSimulating"> Simulasikan Bayar (Sandbox)</span>
                    <span v-else>Memproses Pembayaran...</span>
                  </button>

                  <button type="button" @click="router.push(`${statusUrl}?orderId=${activeOrder.id}`)"
                    class="w-full h-11 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] hover:bg-slate-50 dark:hover:bg-slate-800 text-[#1E293B] dark:text-white font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 transition-all cursor-pointer">
                    <span>Lihat Status Pesanan</span>
                    <AppIcon name="arrow_forward" :size="16" />
                  </button>
                </div>
              </div>
            </template>
          </div>

          <!-- DOCK BAWAH PERSIS SEPERTI FLOATING CART DENGAN 2 BUTTON KIRI KANAN (DI ATAS DIV BIRU UTAMA) -->
          <div v-if="isQrisPayment"
            class="shrink-0 bg-white dark:bg-[#273142] rounded-t-3xl px-6 pt-4 pb-6 shadow-[0_-8px_25px_rgba(0,0,0,0.18)] select-none border-t border-white/20 dark:border-white/10">
            <!-- Baris Total & Harga -->
            <div class="flex items-center justify-between px-1 pb-2 mb-3">
              <span class="text-base sm:text-lg font-normal text-[#64748B] dark:text-[#94A3B8] leading-none">Total</span>
              <span
                class="text-lg sm:text-xl font-extrabold text-[#1E293B] dark:text-white tabular-nums tracking-tight leading-none">
                {{ formatCurrency(activeOrder.total_amount) }}
              </span>
            </div>

            <!-- Baris 2 Button Kiri Kanan: Share (Outline) & Download (Primary) -->
            <div class="grid grid-cols-2 gap-3">
              <AppButton @click="handleShareQris" variant="outline" size="lg"
                class="w-full !rounded-full font-bold shadow-xs !py-3">
                <template #prefix>
                  <AppIcon name="send" :size="18" />
                </template>
                Share
              </AppButton>
              <AppButton @click="handleDownloadQris" variant="primary" size="lg"
                class="w-full !rounded-full font-bold shadow-xs !py-3">
                <template #prefix>
                  <AppIcon name="download" :size="18" />
                </template>
                Download
              </AppButton>
            </div>

            <!-- Tombol Batalkan Pesanan QRIS -->
            <div class="pt-3">
              <button type="button" :disabled="isCancelling || paymentSuccess" @click="handleCancelTransfer"
                class="w-full h-11 rounded-full bg-[#EF3826] hover:bg-red-600 active:scale-[0.98] disabled:opacity-60 text-white font-bold text-xs sm:text-sm shadow-md shadow-red-500/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
                <AppSpinner v-if="isCancelling" :size="16" color="text-white" />
                <AppIcon v-else name="close" :size="18" />
                <span>{{ isCancelling ? 'Membatalkan...' : 'Batalin Pesanan' }}</span>
              </button>
            </div>

            <!-- Tombol Aksi Testing Sandbox & Status Pesanan -->
            <div class="flex items-center gap-2 pt-2.5">
              <button type="button" :disabled="isSimulating || paymentSuccess" @click="handleSimulatePayment"
                class="flex-1 h-9 rounded-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] disabled:opacity-50 text-white font-bold text-[11px] shadow-xs flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                <AppIcon name="bolt" :size="14" />
                <span v-if="!isSimulating"> Simulasikan Bayar</span>
                <span v-else>Memproses...</span>
              </button>

              <button type="button" @click="router.push(`${statusUrl}?orderId=${activeOrder.id}`)"
                class="flex-1 h-9 rounded-full border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] hover:bg-slate-50 dark:hover:bg-slate-800 text-[#1E293B] dark:text-white font-semibold text-[11px] flex items-center justify-center gap-1 transition-all cursor-pointer">
                <span>Status Pesanan</span>
                <AppIcon name="arrow_forward" :size="13" />
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
<style scoped>
/* Transisi Smooth Bottom Sheet Modal */
.sheet-modal-enter-active {
  transition: transform 380ms cubic-bezier(0.16, 1.18, 0.3, 1);
}

.sheet-modal-leave-active {
  transition: transform 240ms cubic-bezier(0.25, 1, 0.5, 1);
}

.sheet-modal-enter-from,
.sheet-modal-leave-to {
  transform: translateY(100%);
}

/* Transisi Backdrop */
.fade-enter-active {
  transition: opacity 300ms ease-out;
}

.fade-leave-active {
  transition: opacity 240ms ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
