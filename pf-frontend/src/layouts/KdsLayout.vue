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

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const posStore = usePosStore()
const { t, translate } = usePosKdsI18n()

const isDark = ref(document.documentElement.classList.contains('dark'))
const isLogoutModalOpen = ref(false)
const isRefreshing = ref(false)

// Status counters for Top Bar badges
const allActiveCount = computed(() => {
  return posStore.orders.filter(o => ['confirmed', 'preparing', 'cooking', 'ready', 'awaiting_payment'].includes(o.status)).length
})

const confirmedCount = computed(() => {
  return posStore.orders.filter(o => o.status === 'confirmed' || o.status === 'awaiting_payment').length
})

const preparingCount = computed(() => {
  return posStore.orders.filter(o => o.status === 'preparing' || (o.status as any) === 'cooking').length
})

const readyCount = computed(() => {
  return posStore.orders.filter(o => o.status === 'ready').length
})

const isToday = (dateStr?: string) => {
  if (!dateStr) return false
  const d = new Date(dateStr)
  const now = new Date()
  return (
    d.getDate() === now.getDate() &&
    d.getMonth() === now.getMonth() &&
    d.getFullYear() === now.getFullYear()
  )
}

const completedCount = computed(() => {
  return posStore.orders.filter(
    o => (o.status === 'completed' || o.status === 'cancelled' || o.status === 'expired') && isToday(o.updatedAt || o.createdAt)
  ).length
})

const historyCount = computed(() => {
  return posStore.orders.length
})

const isTabActive = (targetPath: string, targetStatus?: string) => {
  if (targetStatus) {
    return route.path === targetPath && route.query.status === targetStatus
  }
  if (targetPath === '/kds/queue') {
    return route.path === targetPath && !route.query.status
  }
  return route.path === targetPath
}

const handleRefresh = () => {
  isRefreshing.value = true
  window.dispatchEvent(new CustomEvent('kds:refresh'))
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

// Recalculate indicator on route navigation or when order counters change
watch(
  [
    () => route.path,
    () => route.query.status,
    allActiveCount,
    confirmedCount,
    preparingCount,
    readyCount,
  ],
  () => {
    nextTick(() => {
      updateNavIndicator()
    })
  }
)

onMounted(() => {
  posStore.fetchOrders()
  nextTick(() => {
    updateNavIndicator()
    window.addEventListener('resize', updateNavIndicator)

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
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})

const handleLogout = async () => {
  await authStore.logout()
  isLogoutModalOpen.value = false
  router.push('/login')
}
</script>

<template>
  <div
    class="h-screen bg-[#F5F6FA] dark:bg-[#1B2431] flex flex-col font-sans overflow-hidden transition-colors duration-200">
    <!-- Top KDS Navigation Bar (70px height - Exact match with POS Topbar) -->
    <header
      class="h-[70px] bg-white dark:bg-[#273142] border-b border-[#E2E8F0] dark:border-[#334155] px-2 sm:px-4 md:px-5 lg:px-8 flex items-center justify-between gap-2 md:gap-3 sticky top-0 z-20 shrink-0 transition-colors duration-200">
      <!-- Left: Back Button (Owner only) + Logo Lapaqu -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <router-link v-if="authStore.isOwner" to="/dashboard"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] hover:text-[#4880FF] transition-colors cursor-pointer"
          :title="t('kds.backToDashboard')">
          <AppIcon name="arrow_back" :size="20" />
        </router-link>

        <div class="flex items-center gap-2 py-1">
          <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-9 h-9 sm:w-10 sm:h-10 object-contain shrink-0" />
        </div>
      </div>

      <!-- Center: KDS Navigation Tabs for Every Status (Sliding Blue Pill Indicator) -->
      <div
        class="flex-1 flex items-center justify-start lg:justify-center min-w-0 overflow-x-auto no-scrollbar py-1 px-1">
        <nav ref="navContainerRef" class="flex items-center gap-1 md:gap-1.5 lg:gap-2 relative select-none shrink-0">
          <!-- Sliding Blue Pill Indicator (iOS Spring Physics) -->
          <div v-show="isIndicatorVisible"
            class="absolute rounded-lg bg-[#4880FF] shadow-sm pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
            :style="{
              left: `${indicatorLeft}px`,
              top: `${indicatorTop}px`,
              width: `${indicatorWidth}px`,
              height: `${indicatorHeight}px`,
            }" />

          <!-- 1. Semua Antrean -->
          <router-link to="/kds/queue" :data-active="isTabActive('/kds/queue')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/queue')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="apps" :size="16" class="shrink-0" />
            <span>{{ t('kds.all') }} <span v-if="allActiveCount > 0">({{ allActiveCount }})</span></span>
          </router-link>

          <!-- 2. Menunggu Dimasak -->
          <router-link to="/kds/queue?status=confirmed" :data-active="isTabActive('/kds/queue', 'confirmed')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/queue', 'confirmed')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="schedule" :size="16" class="shrink-0" />
            <span>{{ t('kds.waiting') }} <span v-if="confirmedCount > 0">({{ confirmedCount }})</span></span>
          </router-link>

          <!-- 3. Sedang Dimasak -->
          <router-link to="/kds/queue?status=preparing" :data-active="isTabActive('/kds/queue', 'preparing')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/queue', 'preparing')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="skillet" :size="16" class="shrink-0" />
            <span>{{ t('kds.cooking') }} <span v-if="preparingCount > 0">({{ preparingCount }})</span></span>
          </router-link>

          <!-- 4. Siap Saji -->
          <router-link to="/kds/queue?status=ready" :data-active="isTabActive('/kds/queue', 'ready')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/queue', 'ready')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="room_service" :size="16" class="shrink-0" />
            <span>{{ t('kds.ready') }} <span v-if="readyCount > 0">({{ readyCount }})</span></span>
          </router-link>

          <!-- 5. Antrean Selesai (Selesai Hari Ini) -->
          <router-link to="/kds/completed" :data-active="isTabActive('/kds/completed')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/completed')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="task_alt" :size="16" class="shrink-0" />
            <span>{{ t('kds.completed', 'Antrean Selesai') }} <span v-if="completedCount > 0">({{ completedCount }})</span></span>
          </router-link>

          <!-- 6. Riwayat Pesanan (Seluruh Riwayat) -->
          <router-link to="/kds/history" :data-active="isTabActive('/kds/history')" :class="[
            'relative z-10 px-2.5 sm:px-3 md:px-3.5 lg:px-4 py-1.5 sm:py-2 rounded-lg text-xs md:text-xs lg:text-sm font-bold flex items-center gap-1.5 whitespace-nowrap transition-colors duration-200',
            isTabActive('/kds/history')
              ? 'text-white'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]',
          ]">
            <AppIcon name="history" :size="16" class="shrink-0" />
            <span>{{ t('kds.history', 'Riwayat Pesanan') }} <span v-if="historyCount > 0">({{ historyCount }})</span></span>
          </router-link>
        </nav>
      </div>

      <!-- Right Actions: Refresh, Theme Toggle, Logout Button -->
      <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 shrink-0">
        <!-- Refresh Button (Icon Only Circle Button on Left of Theme Switch) -->
        <button @click="handleRefresh"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] hover:text-[#4880FF] transition-colors cursor-pointer"
          :title="t('kds.refreshTitle')">
          <AppIcon name="refresh" :size="18" :class="{ 'animate-spin': isRefreshing }" />
        </button>

        <!-- Dark/Light Mode Switch -->
        <button @click="toggleTheme"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          :title="t('kds.themeTitle')">
          <AppIcon v-if="isDark" name="light_mode" :size="18" class="text-[#FBBF24]" />
          <AppIcon v-else name="dark_mode" :size="18" />
        </button>

        <!-- Logout Button (Icon Only) -->
        <button @click="isLogoutModalOpen = true"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#FD5454] hover:bg-[#FFEBEB] dark:hover:bg-[#FD5454]/15 transition-colors cursor-pointer"
          :title="t('kds.logoutTitle')">
          <AppIcon name="logout" :size="18" />
        </button>
      </div>
    </header>

    <!-- Main Content Area: 100% full height of remaining screen -->
    <main class="flex-1 p-3 sm:p-4 md:p-6 pb-1.5 sm:pb-2 md:pb-2 overflow-hidden flex flex-col min-h-0">
      <router-view v-slot="{ Component, route }">
        <AppPageTransition :component="Component" :route="route" />
      </router-view>
    </main>

    <!-- Alert Modal: Konfirmasi Keluar KDS -->
    <AppModal v-model="isLogoutModalOpen" :title="t('kds.logoutModalTitle')" maxWidth="sm">
      <div class="space-y-3 py-2 text-center">
        <div
          class="w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-950/40 text-[#FD5454] flex items-center justify-center mx-auto mb-2">
          <AppIcon name="logout" :size="28" />
        </div>
        <h3 class="text-base font-bold text-[#202224] dark:text-white">{{ t('kds.logoutModalTitle') }}</h3>
        <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          {{ t('kds.logoutModalDesc') }}
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-3 w-full">
          <AppButton variant="outline" size="md" @click="isLogoutModalOpen = false" class="!rounded-lg flex-1">
            {{ t('kds.cancel') }}
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleLogout"
            class="!rounded-lg flex-1 !bg-[#FD5454] !text-white hover:!bg-[#E03E3E] !border-[#FD5454]">
            {{ t('kds.yesLogout') }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.page-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>