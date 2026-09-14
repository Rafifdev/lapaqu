<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppSearchInput from '@/components/ui/AppSearchInput.vue'
import AppFloatingCartBar from '@/components/customer/AppFloatingCartBar.vue'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/api'

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || 'Kopi Kenangan Senopati')
const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || 'Cabang Senopati Utama')

const isSearchOpen = computed(() => cartStore.isSearchOpen)
const searchInputRef = ref<any>(null)

const openSearch = () => {
  cartStore.isSearchOpen = true
  nextTick(() => {
    searchInputRef.value?.focus?.() || document.querySelector('header input')?.focus()
  })
}

const closeSearch = () => {
  cartStore.searchQuery = ''
  cartStore.isSearchOpen = false
}

onMounted(async () => {
  try {
    const res = await apiClient.get('/dashboard/overview', { timeout: 3000 })
    if (res.data?.outlet?.tenant) {
      tenantName.value = res.data.outlet.tenant
      localStorage.setItem('lapaqu_tenant_name', res.data.outlet.tenant)
    }
    if (res.data?.outlet?.name) {
      outletName.value = res.data.outlet.name
      localStorage.setItem('lapaqu_outlet_name', res.data.outlet.name)
    }
  } catch {
    // fallback
  }
})

const outletId = computed(() => (route.params.outletId as string) || 'outlet-001')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || 'M03')

const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)
const cartUrl = computed(() => `/order/${outletId.value}/${tableCode.value}/my-order`)
const myOrderUrl = computed(() => `/order/${outletId.value}/${tableCode.value}/my-order`)
const statusUrl = computed(() => `/order/${outletId.value}/${tableCode.value}/status`)

const isActive = (path: string) => route.path === path
const goToOrderStatus = () => {
  const activeId = localStorage.getItem('lapaqu_active_order_id')
  if (activeId) {
    router.push(`${statusUrl.value}?orderId=${activeId}`)
  } else {
    router.push(statusUrl.value)
  }
}

const isMenuPage = computed(() => route.name === 'customer-menu' || route.path === menuUrl.value)
const hasActiveOrder = computed(() => !!localStorage.getItem('lapaqu_active_order_id') || cartStore.hasPendingOrder)
</script>

<template>
  <div :class="[
    'min-h-screen flex flex-col items-center font-sans selection:bg-[#4880FF]/20 transition-colors',
    (route.name === 'customer-my-order')
      ? 'bg-[#F5F6FA] dark:bg-[#1B2431]'
      : 'bg-white dark:bg-[#273142]'
  ]">
    <div :class="[
      'w-full md:max-w-md min-h-screen flex flex-col relative transition-colors', (route.name === 'customer-my-order') ? 'pb-0' : 'pb-4',
      (route.name === 'customer-my-order')
        ? 'bg-[#F5F6FA] dark:bg-[#1B2431]'
        : 'bg-white dark:bg-[#273142]'
    ]">
      
      <!-- Outlet Header: Normal vs Active Search Mode -->
      <header v-if="route.name !== 'customer-va-instructions'" :class="[
        'px-4 flex items-center sticky top-0 z-40 transition-colors',
        (route.name === 'customer-my-order')
          ? 'h-14 bg-[#F5F6FA] dark:bg-[#1B2431]'
          : 'h-16 bg-white dark:bg-[#273142]'
      ]">
        
        <!-- MODE 1: Search Active (Seperti di Gambar: Tombol Back + Input Search Full) -->
        <div v-if="isMenuPage && isSearchOpen" class="w-full flex items-center gap-2">
          <!-- Button Back -->
          <button
            type="button"
            @click="closeSearch"
            class="w-9 h-9 -ml-1 flex items-center justify-center rounded-full text-[#1E293B] hover:text-[#4880FF] dark:text-white dark:hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:scale-90 transition-all cursor-pointer shrink-0"
            title="Kembali"
          >
            <AppIcon name="arrow_back" :size="24" />
          </button>

          <!-- Search Input Box -->
          <div class="flex-1 min-w-0">
            <AppSearchInput
              ref="searchInputRef"
              v-model="cartStore.searchQuery"
              placeholder="Cari makanan"
              borderless
              rounded="lg"
                            hideSearchIcon
              class="w-full !max-w-none"
            />
          </div>
        </div>

        <!-- MODE 3: My Order Page Header (Sesuai Referensi: Tombol Back + Judul Pesanan Saya) -->
        <div v-else-if="route.name === 'customer-my-order'" class="w-full flex items-center gap-3">
          <button
            type="button"
            @click="router.push(menuUrl)"
            class="w-9 h-9 -ml-1 flex items-center justify-center rounded-full text-[#1E293B] hover:text-[#4880FF] dark:text-white dark:hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:scale-90 transition-all cursor-pointer shrink-0"
            title="Kembali ke Menu"
          >
            <AppIcon name="arrow_back" :size="24" />
          </button>
          <h1 class="text-lg sm:text-xl font-bold text-[#1E293B] dark:text-white tracking-tight">
            Pesanan Saya
          </h1>
        </div>

        <!-- MODE 3: Normal Header (Logo + Nama Tenant & Search Icon di Kanan) -->
        <div v-else class="w-full flex items-center justify-between gap-2">
          <!-- Logo Lapaqu & Nama Tenant -->
          <div class="flex items-center gap-2 min-w-0 select-none">
            <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-10 h-10 object-contain shrink-0" />
            <div class="min-w-0">
              <h1 class="text-sm sm:text-base font-extrabold text-[#1E293B] dark:text-white leading-tight truncate">
                {{ tenantName }}
              </h1>
              <p v-if="outletName && outletName !== tenantName" class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8] mt-0.5 truncate">
                {{ outletName }}
              </p>
            </div>
          </div>

          <!-- Search & Proses Pesanan Icon di Pojok Kanan -->
          <div v-if="isMenuPage" class="flex items-center gap-1 shrink-0">
            <!-- Icon Search -->
            <button
              type="button"
              @click="openSearch"
              class="w-10 h-10 flex items-center justify-center rounded-xl text-[#64748B] hover:text-[#1E293B] dark:text-[#94A3B8] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:scale-95 transition-all cursor-pointer relative"
              title="Cari Menu"
            >
              <AppIcon name="search" :size="22" />
              <span
                v-if="cartStore.searchQuery"
                class="absolute top-2 right-2 w-2 h-2 rounded-full bg-[#4880FF]"
              />
            </button>

            <!-- Icon Proses Pesanan (Status Order) di Sebelah Kanan Search -->
            <button
              type="button"
              @click="goToOrderStatus"
              class="w-10 h-10 flex items-center justify-center rounded-xl text-[#64748B] hover:text-[#1E293B] dark:text-[#94A3B8] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:scale-95 transition-all cursor-pointer relative"
              title="Proses Pesanan"
            >
              <AppIcon name="receipt_long" :size="22" />
              <span
                v-if="hasActiveOrder"
                class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-[#00B69B] ring-2 ring-white dark:ring-[#273142] animate-pulse"
              />
            </button>
          </div>
          <!-- Button Back di Kanan jika di luar menu page -->
          <div v-else class="flex items-center shrink-0">
            <button
              type="button"
              @click="router.push(menuUrl)"
              class="w-9 h-9 flex items-center justify-center rounded-full text-[#1E293B] hover:text-[#4880FF] dark:text-white dark:hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:scale-90 transition-all cursor-pointer"
              title="Kembali ke Menu"
            >
              <AppIcon name="arrow_back" :size="24" />
            </button>
          </div>
        </div>
      </header>

      <!-- Dark Overlay saat Search Aktif (ShopeeFood style: Full Width Edge-to-Edge, Hitam Tanpa Blur) -->
      <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isMenuPage && cartStore.isSearchOpen && !cartStore.searchQuery"
          @click="closeSearch"
          class="fixed inset-0 top-16 bg-black/60 z-35 cursor-pointer"
        />
      </Transition>

      <!-- Main Content Page -->
      <main :class="route.name === 'customer-va-instructions' ? 'p-0 flex-1' : 'flex-1 p-4 pb-6'">
        <router-view />
      </main>

      <!-- Floating Cart Bar (ShopeeFood / GrabFood style) -->
      <AppFloatingCartBar
        v-if="isMenuPage"
        :table-code="tableCode"
        :tenant-name="tenantName"
        :outlet-name="outletName"
        :cart-url="cartUrl"
        :my-order-url="myOrderUrl"
      />
    </div>
  </div>
</template>
