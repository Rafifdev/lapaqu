<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useCartStore } from '@/stores/cart'
import { useFormat } from '@/composables/useFormat'

interface Props {
  tableCode?: string
  tenantName?: string
  outletName?: string
  cartUrl?: string
  myOrderUrl?: string
  totalPrice?: number
  subtitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  tableCode: '',
  tenantName: '',
  outletName: '',
  cartUrl: '',
  myOrderUrl: '',
  totalPrice: undefined,
  subtitle: '',
})

const emit = defineEmits<{
  (e: 'click'): void
  (e: 'cart-click'): void
}>()

const router = useRouter()
const cartStore = useCartStore()
const { formatNumber } = useFormat()

const totalItems = computed(() => {
  if (cartStore.hasPendingOrder) {
    return cartStore.pendingOrder?.total_items || 1
  }
  return cartStore.totalItemsCount
})

const displayPrice = computed(() => {
  if (cartStore.hasPendingOrder) {
    return cartStore.pendingOrder?.total_amount || 0
  }
  if (props.totalPrice !== undefined) {
    return props.totalPrice
  }
  return cartStore.totalPrice
})

const displaySubtitle = computed(() => {
  if (cartStore.hasPendingOrder) {
    return `Pesanan #${cartStore.pendingOrder?.order_number || ''} (Belum Bayar)`
  }
  if (props.subtitle) {
    return props.subtitle
  }
  if (props.tableCode) {
    return `Pesanan Meja ${props.tableCode}`
  }
  return 'Pesanan'
})

// Button Utama: Masuk ke halaman Pesanan Saya (Checkout / Lanjutkan Pembayaran)
const handleMainClick = () => {
  emit('click')
  if (cartStore.hasPendingOrder) {
    if (props.myOrderUrl) {
      router.push(`${props.myOrderUrl}?openQris=1`)
    }
    return
  }
  if (props.myOrderUrl) {
    router.push(props.myOrderUrl)
  }
}

// Button Icon Cart: Hanya muncul jika TIDAK ADA pending order
const handleCartClick = () => {
  emit('cart-click')
  if (props.myOrderUrl) {
    router.push(props.myOrderUrl)
  } else if (props.cartUrl) {
    router.push(props.cartUrl)
  }
}
</script>

<template>
  <!-- Bottom Docked Cart Bar -->
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="translate-y-full"
    enter-to-class="translate-y-0"
    leave-active-class="transition-all duration-200 ease-in"
    leave-from-class="translate-y-0"
    leave-to-class="translate-y-full"
  >
    <div
      v-if="totalItems > 0 || cartStore.hasPendingOrder"
      class="fixed bottom-0 inset-x-0 w-full md:max-w-md mx-auto bg-white dark:bg-[#273142] rounded-t-3xl px-6 pt-6 pb-8 z-40 border-t border-[#E2E8F0]/70 dark:border-[#334155]/70 shadow-[0_-4px_22px_rgba(0,0,0,0.08)]"
    >
      <div class="flex items-center gap-3 select-none">
        <!-- Main Button: Amber (Warning) saat Pending Order, Biru saat Keranjang Biasa -->
        <button
          type="button"
          @click="handleMainClick"
          :class="[
            'flex-1 h-[56px] rounded-full active:scale-[0.98] transition-all flex items-center justify-between px-6 text-white shadow-md cursor-pointer min-w-0',
            cartStore.hasPendingOrder
              ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/25'
              : 'bg-[#4880FF] hover:bg-[#3971F0] shadow-[#4880FF]/25'
          ]"
        >
          <!-- Left Info: Item Count & Subtitle -->
          <div class="flex flex-col text-left min-w-0 pr-2">
            <template v-if="cartStore.hasPendingOrder">
              <!-- Line 1: Lanjutkan Pembayaran (Dominan) -->
              <span class="font-bold text-sm sm:text-base leading-tight tracking-tight text-white truncate">
                Lanjutkan Pembayaran
              </span>
              <!-- Line 2: 1 item #ORD-2026 -->
              <span class="text-xs text-white/85 font-medium leading-tight truncate mt-0.5">
                {{ totalItems }} {{ totalItems > 1 ? 'items' : 'item' }} #{{ cartStore.pendingOrder?.order_number || '' }}
              </span>
            </template>
            <template v-else>
              <span class="font-bold text-sm sm:text-[15px] leading-tight tracking-tight text-white truncate">
                {{ totalItems }} {{ totalItems > 1 ? 'items' : 'item' }}
              </span>
              <span class="text-xs text-white/90 font-medium leading-tight truncate mt-0.5">
                {{ displaySubtitle }}
              </span>
            </template>
          </div>

          <!-- Right: Formatted Price & Arrow Icon -->
          <div class="flex items-center gap-1.5 pl-2 shrink-0">
            <span class="font-bold text-base tabular-nums tracking-tight text-white whitespace-nowrap">
              {{ formatNumber(displayPrice) }}
            </span>
            <AppIcon v-if="cartStore.hasPendingOrder" name="arrow_forward" :size="18" class="text-white" />
          </div>
        </button>

        <!-- Right Shopping Bag Squircle Button (DIHILANGKAN jika status pesanan sedang menunggu pembayaran) -->
        <button
          v-if="!cartStore.hasPendingOrder"
          type="button"
          @click="handleCartClick"
          class="h-[56px] w-[56px] rounded-2xl bg-white dark:bg-[#273142] border-2 border-[#4880FF] flex items-center justify-center shadow-xs hover:bg-blue-50/50 dark:hover:bg-[#323D4E] active:scale-95 transition-all cursor-pointer shrink-0"
          title="Lihat Pesanan Saya"
        >
          <AppIcon name="shopping_bag" :size="24" :filled="true" class="text-[#4880FF]" />
        </button>
      </div>
    </div>
  </Transition>
</template>
