<script setup lang="ts">
import AppPageTransition from '@/components/ui/AppPageTransition.vue'
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePosStore } from '@/stores/pos'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { usePosKdsI18n } from '@/i18n'
import lapaquLogo from '@/assets/brand_logo/lapaqu-logo.png'
import apiClient from '@/services/api'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const posStore = usePosStore()
const { t, translate } = usePosKdsI18n()
const incomingCount = computed(() => posStore.incomingOrders.length)

const isDark = ref(document.documentElement.classList.contains('dark'))
const isLogoutModalOpen = ref(false)
const isRefreshing = ref(false)
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || (authStore.currentUser as any)?.tenant?.name || 'Lapaqu')
const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || (authStore.currentUser as any)?.outlet?.name || '')
const restaurantLogo = ref(localStorage.getItem('lapaqu_restaurant_logo') || '')

const handleBrandingUpdated = (e: any) => {
  if (e.detail?.tenantName) tenantName.value = e.detail.tenantName
  if (e.detail?.outletName) outletName.value = e.detail.outletName
  if (e.detail?.logo !== undefined) restaurantLogo.value = e.detail.logo
}


const handleRefresh = () => {
  isRefreshing.value = true
  window.dispatchEvent(new CustomEvent('kds:refresh'))
  posStore.fetchOrders(true)
  setTimeout(() => {
    isRefreshing.value = false
  }, 600)
}

const toggleTheme = () => {
  isDark.value = !isDark.value
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

// Sliding iOS pill indicator logic
const navContainerRef = ref<HTMLElement | null>(null)
const indicatorLeft = ref(0)
const indicatorTop = ref(0)
const indicatorWidth = ref(0)
const indicatorHeight = ref(0)
const isIndicatorVisible = ref(false)
let resizeObserver: ResizeObserver | null = null

const updateNavIndicator = () => {
  if (!navContainerRef.value) return
  const activeEl = navContainerRef.value.querySelector<HTMLElement>('[data-active="true"]')
  if (activeEl) {
    indicatorLeft.value = activeEl.offsetLeft
    indicatorTop.value = activeEl.offsetTop
    indicatorWidth.value = activeEl.offsetWidth
    indicatorHeight.value = activeEl.offsetHeight
    isIndicatorVisible.value = true
  } else {
    isIndicatorVisible.value = false
  }
}

// Recalculate indicator on route navigation or when incoming orders count changes
watch([() => route.path, incomingCount], () => {
  nextTick(() => {
    updateNavIndicator()
  })
})

onMounted(() => {
  nextTick(() => {
    updateNavIndicator()
    window.addEventListener('resize', updateNavIndicator)
    window.addEventListener('lapaqu:branding-updated', handleBrandingUpdated)
    try {
      apiClient.get('/dashboard/overview', { timeout: 3000 }).then((res) => {
        if (res.data?.outlet?.name) {
          outletName.value = res.data.outlet.name
          localStorage.setItem('lapaqu_outlet_name', res.data.outlet.name)
        }
        if (res.data?.outlet?.tenant) {
          tenantName.value = res.data.outlet.tenant
          localStorage.setItem('lapaqu_tenant_name', res.data.outlet.tenant)
        }
        if (res.data?.outlet?.logo_url) {
          restaurantLogo.value = res.data.outlet.logo_url
          localStorage.setItem('lapaqu_restaurant_logo', res.data.outlet.logo_url)
        }
      }).catch(() => {})
    } catch {}

    if (navContainerRef.value && typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(() => {
        updateNavIndicator()
      })
      resizeObserver.observe(navContainerRef.value)
      Array.from(navContainerRef.value.children).forEach((el) => {
        if (el.tagName === 'A') {
          resizeObserver?.observe(el)
        }
      })
    }
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', updateNavIndicator)
  window.removeEventListener('lapaqu:branding-updated', handleBrandingUpdated)
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})

const handleLogout = () => {
  isLogoutModalOpen.value = false
  authStore.logout()
  router.push('/auth/login')
}
</script>

<template>
  <div
    class="h-screen bg-[#F5F6FA] dark:bg-[#1B2431] flex flex-col font-sans transition-colors duration-200 overflow-hidden">
    <!-- Top POS Navigation Bar (76px height) -->
    <header
      class="h-[76px] bg-white dark:bg-[#273142] border-b border-[#E2E8F0] dark:border-[#334155] px-2 sm:px-4 md:px-5 lg:px-8 flex items-center justify-between gap-2 md:gap-3 sticky top-0 z-20 shrink-0 transition-colors duration-200">
      <!-- Left: Back Button (Owner only) + Brand & Outlet Info (Enlarged 1 Level) -->
      <div class="flex items-center gap-2.5 sm:gap-3.5 shrink-0">
        <!-- Back to Dashboard (Owner only) - Simple Circle Button matching Theme Switch -->
        <router-link
          v-if="authStore.isOwner"
          to="/dashboard"
          class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] hover:text-[#4880FF] transition-colors cursor-pointer shrink-0"
          :title="t('pos.backToDashboard')"
        >
          <AppIcon name="arrow_back" :size="22" />
        </router-link>

        <div class="flex items-center gap-3 min-w-0 select-none py-1">
          <img
            :src="restaurantLogo || lapaquLogo"
            :alt="tenantName"
            class="w-10 h-10 sm:w-11 sm:h-11 object-contain rounded-xl shrink-0"
          />
          <div class="flex flex-col min-w-0 justify-center">
            <span
              class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white leading-tight truncate max-w-[130px] sm:max-w-[180px] md:max-w-[220px]"
              :title="tenantName"
            >
              {{ tenantName }}
            </span>
            <span
              v-if="outletName"
              class="text-xs sm:text-sm font-medium text-[#64748B] dark:text-[#94A3B8] leading-tight mt-0.5 truncate max-w-[130px] sm:max-w-[180px] md:max-w-[220px]"
              :title="outletName"
            >
              {{ outletName }}
            </span>
          </div>
        </div>
      </div>

      <!-- Center: POS Navigation Tabs (Fits completely on iPad Mini & iPad Air, scrollable on narrow phones) -->
      <div class="flex-1 flex items-center justify-start lg:justify-center min-w-0 overflow-x-auto no-scrollbar py-1 px-1">
        <nav ref="navContainerRef" class="flex items-center gap-1 md:gap-1.5 lg:gap-2 relative select-none shrink-0">
          <!-- Sliding Blue Pill Indicator (iOS Spring Physics) -->
          <div v-show="isIndicatorVisible"
            class="absolute rounded-xl bg-[#4880FF] shadow-sm pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
            :style="{
              left: `${indicatorLeft}px`,
              top: `${indicatorTop}px`,
              width: `${indicatorWidth}px`,
              height: `${indicatorHeight}px`,
            }" />

          <router-link to="/pos/orders" :data-active="route.path === '/pos/orders'" :class="[
            'relative z-10 px-3 sm:px-3.5 md:px-4 lg:px-4.5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm md:text-sm lg:text-sm font-bold flex items-center gap-2 whitespace-nowrap transition-colors duration-200',
            route.path === '/pos/orders'
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="receipt_long" :size="18" class="shrink-0" />
            <span>{{ t('pos.incomingOrders') }} <span v-if="incomingCount > 0">({{ incomingCount }})</span></span>
          </router-link>

          <router-link to="/pos/manual" :data-active="route.path === '/pos/manual'" :class="[
            'relative z-10 px-3 sm:px-3.5 md:px-4 lg:px-4.5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm md:text-sm lg:text-sm font-bold flex items-center gap-2 whitespace-nowrap transition-colors duration-200',
            route.path === '/pos/manual'
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="add_circle" :size="18" class="shrink-0" />
            <span>{{ t('pos.manualOrder') }}</span>
          </router-link>

          <router-link to="/pos/tables" :data-active="route.path === '/pos/tables'" :class="[
            'relative z-10 px-3 sm:px-3.5 md:px-4 lg:px-4.5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm md:text-sm lg:text-sm font-bold flex items-center gap-2 whitespace-nowrap transition-colors duration-200',
            route.path === '/pos/tables'
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="table_restaurant" :size="18" class="shrink-0" />
            <span>{{ t('pos.manageTable') }}</span>
          </router-link>

          <router-link to="/pos/history" :data-active="route.path === '/pos/history'" :class="[
            'relative z-10 px-3 sm:px-3.5 md:px-4 lg:px-4.5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm md:text-sm lg:text-sm font-bold flex items-center gap-2 whitespace-nowrap transition-colors duration-200',
            route.path === '/pos/history'
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="history" :size="18" class="shrink-0" />
            <span>{{ t('pos.orderHistory') }}</span>
          </router-link>

          <router-link to="/pos/quick-toggle" :data-active="route.path === '/pos/quick-toggle'" :class="[
            'relative z-10 px-3 sm:px-3.5 md:px-4 lg:px-4.5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm md:text-sm lg:text-sm font-bold flex items-center gap-2 whitespace-nowrap transition-colors duration-200',
            route.path === '/pos/quick-toggle'
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="toggle_on" :size="18" class="shrink-0" />
            <span>{{ t('pos.quickStock') }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Right Actions: Refresh, Theme Toggle, Logout Button (Enlarged 1 Level) -->
      <div class="flex items-center gap-1.5 sm:gap-2 md:gap-2.5 shrink-0">
        <!-- Refresh Button (Circle Button on Left of Theme Switch) -->
        <button @click="handleRefresh"
          class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] hover:text-[#4880FF] transition-colors cursor-pointer"
          :title="t('pos.refreshTitle')">
          <AppIcon name="refresh" :size="20" :class="{ 'animate-spin': isRefreshing }" />
        </button>

        <!-- Dark/Light Mode Switch -->
        <button @click="toggleTheme"
          class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          :title="t('pos.themeTitle')">
          <AppIcon v-if="isDark" name="light_mode" :size="20" class="text-[#FBBF24]" />
          <AppIcon v-else name="dark_mode" :size="20" />
        </button>

        <!-- Logout Button (Icon Only) -->
        <button @click="isLogoutModalOpen = true"
          class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center text-[#FD5454] hover:bg-[#FFEBEB] dark:hover:bg-[#FD5454]/15 transition-colors cursor-pointer"
          :title="t('pos.logoutTitle')">
          <AppIcon name="logout" :size="20" />
        </button>
      </div>
    </header>

    <!-- Main Content Area: 100% full height of remaining screen -->
    <main class="flex-1 p-3 sm:p-4 md:p-6 pb-1.5 sm:pb-2 md:pb-2 overflow-hidden flex flex-col min-h-0">
      <router-view v-slot="{ Component, route }">
        <AppPageTransition :component="Component" :route="route" />
      </router-view>
    </main>

    <!-- Alert Modal: Konfirmasi Keluar POS -->
    <AppModal v-model="isLogoutModalOpen" :title="t('pos.logoutModalTitle')" maxWidth="sm">
      <div class="space-y-3 py-2 text-center">
        <div
          class="w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-950/40 text-[#FD5454] flex items-center justify-center mx-auto mb-2">
          <AppIcon name="logout" :size="28" />
        </div>
        <h3 class="text-base font-bold text-[#202224] dark:text-white">{{ t('pos.logoutModalTitle') }}</h3>
        <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          {{ t('pos.logoutModalDesc') }}
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-3 w-full">
          <AppButton variant="outline" size="md" @click="isLogoutModalOpen = false" class="!rounded-lg flex-1">
            {{ t('pos.cancel') }}
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleLogout"
            class="!rounded-lg flex-1 !bg-[#FD5454] !text-white hover:!bg-[#E03E3E] !border-[#FD5454]">
            {{ t('pos.yesLogout') }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
