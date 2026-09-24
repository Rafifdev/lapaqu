<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import lapaquLogo from '@/assets/brand_logo/lapaqu-logo.png'
import AppModal from '@/components/ui/AppModal.vue'
import AppDropdownMotion from '@/components/ui/AppDropdownMotion.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'
import { useSettingsModal } from '@/composables/useSettingsModal'
import { useDashboardI18n } from '@/i18n'
import avatarManager from '@/assets/roles/avatar-manager.jpg'
import avatarCashier from '@/assets/roles/avatar-cashier.jpg'
import avatarKitchen from '@/assets/roles/avatar-kitchen.jpg'

interface Props {
  collapsed?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'toggle-collapse'): void
}>()

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || (authStore.currentUser as any)?.outlet?.name || '')
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || (authStore.currentUser as any)?.tenant?.name || 'Lapaqu')

const customAvatarUrl = ref(localStorage.getItem('lapaqu_custom_avatar') || '')

const userAvatar = computed(() => {
  if (customAvatarUrl.value) return customAvatarUrl.value
  if (authStore.currentUser?.avatarUrl) return authStore.currentUser.avatarUrl
  const role = authStore.currentUser?.role
  if (role === 'kasir') return avatarCashier
  if (role === 'kitchen_staff') return avatarKitchen
  return avatarManager
})

onMounted(async () => {
  window.addEventListener('lapaqu:avatar-updated', (e: any) => {
    if (e.detail?.avatarUrl) customAvatarUrl.value = e.detail.avatarUrl
  })
  try {
    const res = await apiClient.get('/dashboard/overview', { timeout: 3000 })
    if (res.data?.outlet?.name) {
      outletName.value = res.data.outlet.name
      localStorage.setItem('lapaqu_outlet_name', res.data.outlet.name)
    }
    if (res.data?.outlet?.tenant) {
      tenantName.value = res.data.outlet.tenant
      localStorage.setItem('lapaqu_tenant_name', res.data.outlet.tenant)
    }
  } catch {
    // fallback
  }
})
const restaurantLogo = ref(localStorage.getItem('lapaqu_restaurant_logo') || '')

const handleBrandingUpdated = (e: any) => {
  if (e.detail?.tenantName) tenantName.value = e.detail.tenantName
  if (e.detail?.outletName) outletName.value = e.detail.outletName
  if (e.detail?.logo !== undefined) restaurantLogo.value = e.detail.logo
}

onMounted(() => {
  window.addEventListener('lapaqu:branding-updated', handleBrandingUpdated)
})

onBeforeUnmount(() => {
  window.removeEventListener('lapaqu:branding-updated', handleBrandingUpdated)
})

const { openSettingsModal } = useSettingsModal()
const { t, translate } = useDashboardI18n()

const menuExpanded = ref(true)
const bahanBakuExpanded = ref(false)
const stokExpanded = ref(false)
const laporanExpanded = ref(false)
const isProfileMenuOpen = ref(false)
const isNotificationMenuOpen = ref(false)
const isLogoutModalOpen = ref(false)

interface SidebarNotification {
  id: string
  title: string
  message: string
  time: string
  type: 'order' | 'warning' | 'refund' | 'success'
  isRead: boolean
}

// Notifications List
const notifications = ref<SidebarNotification[]>([])

const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length)

const markAllRead = () => {
  notifications.value.forEach(n => n.isRead = true)
}

const toggleNotificationMenu = () => {
  isNotificationMenuOpen.value = !isNotificationMenuOpen.value
  if (isNotificationMenuOpen.value) {
    isProfileMenuOpen.value = false
  }
}

const toggleProfileMenu = () => {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
  if (isProfileMenuOpen.value) {
    isNotificationMenuOpen.value = false
  }
}

const handleLogout = () => {
  isProfileMenuOpen.value = false
  isLogoutModalOpen.value = false
  authStore.logout()
  router.push('/login')
}

const navContainerRef = ref<HTMLElement | null>(null)
const indicatorTop = ref(0)
const indicatorLeft = ref(0)
const indicatorWidth = ref(0)
const indicatorHeight = ref(44)
const isIndicatorVisible = ref(false)

const updateIndicator = () => {
  if (!navContainerRef.value) return

  // Find active main element marked with data-active="true"
  const activeEl = navContainerRef.value.querySelector<HTMLElement>('[data-active="true"]')
  if (activeEl) {
    indicatorTop.value = activeEl.offsetTop
    indicatorLeft.value = activeEl.offsetLeft
    indicatorWidth.value = activeEl.offsetWidth
    indicatorHeight.value = activeEl.offsetHeight
    isIndicatorVisible.value = true
  } else {
    isIndicatorVisible.value = false
  }
}

// Auto-expand parent submenu when navigating to its child route
watch(
  () => route.path,
  (newPath) => {
    if (newPath.includes('/dashboard/menu/')) menuExpanded.value = true
    if (newPath.includes('/dashboard/ingredients/')) bahanBakuExpanded.value = true
    if (newPath.includes('/dashboard/stock/')) stokExpanded.value = true
    if (newPath.includes('/dashboard/reports')) laporanExpanded.value = true
  },
  { immediate: true }
)

// Update position when route or sidebar state changes with harmonic timing intervals
const triggerSmoothIndicatorUpdate = () => {
  nextTick(() => {
    updateIndicator()
    const timers = [40, 100, 180, 280, 360]
    timers.forEach(t => setTimeout(updateIndicator, t))
  })
}

watch(
  [
    () => route.path,
    () => props.collapsed,
    menuExpanded,
    bahanBakuExpanded,
    stokExpanded,
    laporanExpanded
  ],
  () => {
    triggerSmoothIndicatorUpdate()
  }
)

onMounted(() => {
  nextTick(() => {
    updateIndicator()
    window.addEventListener('resize', updateIndicator)
  })
})

const navigate = (path: string) => {
  router.push(path)
}

const openCustomerPreview = () => {
  const activeOutletId = localStorage.getItem('lapaqu_outlet_id') || localStorage.getItem('lapaqu_active_outlet_id') || (authStore.currentUser as any)?.outletId || (authStore.currentUser as any)?.outlet_id
  if (activeOutletId) {
    navigate(`/order/${activeOutletId}/T01`)
  } else {
    navigate('/dashboard/tables')
  }
}

const handleToggle = () => {
  emit('toggle-collapse')
}

const isRouteActive = (path: string) => route.path === path
const isRouteGroupActive = (prefix: string) => route.path.startsWith(prefix)

// ==========================================
// RESIZABLE SIDEBAR LOGIC (Drag with cursor, max +50% from initial 260px -> 390px)
// ==========================================
const INITIAL_SIDEBAR_WIDTH = 260
const MAX_SIDEBAR_WIDTH = 390 // 260 + 50% (130px) = 390px
const MIN_SIDEBAR_WIDTH = 240

const savedWidth = localStorage.getItem('sidebar_custom_width')
const sidebarWidth = ref(savedWidth ? Math.min(Math.max(Number(savedWidth), MIN_SIDEBAR_WIDTH), MAX_SIDEBAR_WIDTH) : INITIAL_SIDEBAR_WIDTH)
const isResizing = ref(false)

const handleMouseMove = (e: MouseEvent) => {
  if (!isResizing.value) return
  const newWidth = Math.min(Math.max(e.clientX, MIN_SIDEBAR_WIDTH), MAX_SIDEBAR_WIDTH)
  sidebarWidth.value = newWidth
  updateIndicator()
}

const handleMouseUp = () => {
  if (isResizing.value) {
    isResizing.value = false
    document.body.style.removeProperty('user-select')
    document.body.style.removeProperty('cursor')
    window.removeEventListener('mousemove', handleMouseMove)
    window.removeEventListener('mouseup', handleMouseUp)
    localStorage.setItem('sidebar_custom_width', String(sidebarWidth.value))
    nextTick(updateIndicator)
  }
}

const startResize = (e: MouseEvent) => {
  if (props.collapsed) return
  e.preventDefault()
  isResizing.value = true
  document.body.style.userSelect = 'none'
  document.body.style.cursor = 'col-resize'
  window.addEventListener('mousemove', handleMouseMove)
  window.addEventListener('mouseup', handleMouseUp)
}

const resetSidebarWidth = () => {
  if (props.collapsed) return
  sidebarWidth.value = INITIAL_SIDEBAR_WIDTH
  localStorage.setItem('sidebar_custom_width', String(INITIAL_SIDEBAR_WIDTH))
  nextTick(updateIndicator)
}
</script>

<template>
  <aside :class="[
    'bg-white dark:bg-[#273142] border-r border-[#E2E8F0] dark:border-[#334155] flex flex-col z-30 shrink-0 h-screen sticky top-0 relative',
    collapsed ? 'overflow-hidden' : 'overflow-visible',
    isResizing ? 'transition-none select-none' : 'transition-[width] duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)]',
  ]" :style="{ width: collapsed ? '80px' : `${sidebarWidth}px` }">
    <!-- Logo & Header with Expand / Collapse Toggle on the Right of Logo -->
    <div :class="[
      'h-[70px] flex items-center transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] overflow-hidden shrink-0',
      collapsed ? 'justify-center px-2' : 'justify-between pl-4 pr-3 gap-2.5'
    ]">
      <!-- Logo & Brand with Tenant & Outlet Name (Ellipsis dinamis mengikuti ukuran sidebar) -->
      <router-link to="/dashboard" :class="[
        'flex items-center gap-2.5 overflow-hidden py-1 transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] flex-1 min-w-0',
        collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
      ]">
        <img :src="restaurantLogo || lapaquLogo" alt="Logo"
          class="w-9 h-9 sm:w-10 sm:h-10 object-contain rounded-lg shrink-0" />
        <div class="flex flex-col min-w-0 flex-1 justify-center">
          <span
            class="text-sm sm:text-base font-bold tracking-tight font-sans text-[#1E293B] dark:text-white leading-tight truncate"
            :title="tenantName">
            {{ tenantName }}
          </span>
          <span
            class="text-xs sm:text-sm font-medium text-[#64748B] dark:text-[#94A3B8] leading-none mt-1 truncate"
            :title="outletName">
            {{ outletName }}
          </span>
        </div>
      </router-link>

      <!-- Expand / Collapse Toggle Button (Claude PanelLeft Icon, positioned to the right of logo) -->
      <button type="button" @click="handleToggle"
        class="w-9 h-9 rounded-xl flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-all duration-300 cursor-pointer shrink-0"
        :title="collapsed ? t('sidebar.expandSidebar') : t('sidebar.collapseSidebar')">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="transition-transform duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)]"
          :class="{ 'rotate-180': collapsed }">
          <rect width="18" height="18" x="3" y="3" rx="2" />
          <path d="M9 3v18" />
        </svg>
      </button>
    </div>

    <!-- Navigation Links with Sliding iOS Spring Indicator -->
    <nav ref="navContainerRef" class="flex-1 min-h-0 py-2 px-2.5 space-y-1 relative overflow-y-auto overflow-x-hidden">
      <!-- Shared iOS Spring-Physics Sliding Pill -->
      <div v-show="isIndicatorVisible"
        class="absolute rounded-xl bg-[#4880FF] shadow-sm pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
        :style="{
          top: `${indicatorTop}px`,
          left: `${indicatorLeft}px`,
          width: `${indicatorWidth}px`,
          height: `${indicatorHeight}px`,
        }">
      </div>

      <!-- Main Dashboard -->
      <button type="button" @click="navigate('/dashboard')" :data-active="isRouteActive('/dashboard')" :class="[
        'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteActive('/dashboard')
          ? 'text-white font-bold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
      ]">
        <AppIcon name="dashboard" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.overview') }}</span>
      </button>

      <!-- Section Title: Operasional -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-[opacity,max-height,padding,transform] duration-300 ease-in-out',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">{{ t('sidebar.sectionOperations') }}</span>
      </div>

      <!-- POS Kasir -->
      <button type="button" @click="navigate('/pos/orders')" :data-active="isRouteGroupActive('/pos')" :class="[
        'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteGroupActive('/pos')
          ? 'text-white font-bold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
      ]">
        <AppIcon name="point_of_sale" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.posKasir') }}</span>
      </button>

      <!-- KDS -->
      <button type="button" @click="navigate('/kds/queue')" :data-active="isRouteGroupActive('/kds')" :class="[
        'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteGroupActive('/kds')
          ? 'text-white font-bold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
      ]">
        <AppIcon name="soup_kitchen" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.dapurKds') }}</span>
      </button>

      <!-- Tables QR -->
      <button type="button" @click="navigate('/dashboard/tables')" :data-active="isRouteActive('/dashboard/tables')"
        :class="[
          'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/tables')
            ? 'text-white font-bold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
        ]">
        <AppIcon name="table_restaurant" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.tables') }}</span>
      </button>

      <!-- Section Title: Owner Resto -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-[opacity,max-height,padding,transform] duration-300 ease-in-out',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">{{ t('sidebar.sectionManagement') }}</span>
      </div>

      <!-- 1. Menu & Kategori (Expandable) -->
      <div>
        <button type="button" @click="menuExpanded = !menuExpanded"
          :data-active="isRouteGroupActive('/dashboard/menu') ? 'true' : undefined" :class="[
            'w-full flex items-center text-sm font-bold transition-colors duration-150 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/menu')
              ? 'text-white font-bold'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="restaurant_menu" :size="18" class="shrink-0 transition-colors duration-150" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">{{ t('sidebar.menuTitle') }}</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 shrink-0 ml-auto"
            :class="[
              isRouteGroupActive('/dashboard/menu') ? 'text-white' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200',
              { 'rotate-180': menuExpanded }
            ]" >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Menu & Kategori -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="menuExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/menu/items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/menu/items')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/menu/items') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.menuItems') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/menu/categories')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/menu/categories')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/menu/categories') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.menuCategories') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Bahan Baku (Expandable) -->
      <div>
        <button type="button" @click="bahanBakuExpanded = !bahanBakuExpanded"
          :data-active="isRouteGroupActive('/dashboard/ingredients') ? 'true' : undefined" :class="[
            'w-full flex items-center text-sm font-bold transition-colors duration-150 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/ingredients')
              ? 'text-white font-bold'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="egg" :size="20" class="shrink-0 transition-colors duration-150" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">{{ t('sidebar.rawMaterials') }}</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 shrink-0 ml-auto"
            :class="[
              isRouteGroupActive('/dashboard/ingredients') ? 'text-white' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200',
              { 'rotate-180': bahanBakuExpanded }
            ]" >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Bahan Baku -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="bahanBakuExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/ingredients/items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/items')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/items') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.ingredientsList') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/ingredients/categories')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/categories')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/categories') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.ingredientCategories') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/ingredients/recipes')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/recipes')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/recipes') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.recipes') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 3. Stok (Expandable) -->
      <div>
        <button type="button" @click="stokExpanded = !stokExpanded"
          :data-active="isRouteGroupActive('/dashboard/stock') ? 'true' : undefined" :class="[
            'w-full flex items-center text-sm font-bold transition-colors duration-150 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/stock')
              ? 'text-white font-bold'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="inventory_2" :size="18" class="shrink-0 transition-colors duration-150" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">{{ t('sidebar.stock') }}</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 shrink-0 ml-auto"
            :class="[
              isRouteGroupActive('/dashboard/stock') ? 'text-white' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200',
              { 'rotate-180': stokExpanded }
            ]" >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Stok -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="stokExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/stock/current')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/stock/current')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/current') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.currentStock') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/stock/opname')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/stock/opname')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/opname') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.stockOpname') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/stock/history')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/stock/history')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/history') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.stockHistory') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Laporan & Analitik (Expandable) -->
      <div>
        <button type="button" @click="laporanExpanded = !laporanExpanded"
          :data-active="isRouteGroupActive('/dashboard/reports')" :class="[
            'w-full flex items-center text-sm font-bold transition-colors duration-150 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/reports')
              ? 'text-white font-bold'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="bar_chart_4_bars" :size="20" class="shrink-0 transition-colors duration-150" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">{{ t('sidebar.reports') }}</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 shrink-0 ml-auto"
            :class="[
              isRouteGroupActive('/dashboard/reports') ? 'text-white' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200',
              { 'rotate-180': laporanExpanded }
            ]" >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Laporan & Analitik -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="laporanExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/reports/sales')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/reports/sales')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/sales') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.salesReport') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/reports/top-items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/reports/top-items')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/top-items') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.topItems') }}</span>
            </button>
            <button type="button" @click="navigate('/dashboard/reports/peak-hours')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-colors duration-150 cursor-pointer text-left',
                isRouteActive('/dashboard/reports/peak-hours')
                  ? 'bg-[#4880FF] text-white font-bold shadow-xs'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/peak-hours') ? 'bg-white' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">{{ t('sidebar.peakHours') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Staff -->
      <button type="button" @click="navigate('/dashboard/staff')" :data-active="isRouteActive('/dashboard/staff')"
        :class="[
          'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/staff')
            ? 'text-white font-bold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
        ]">
        <AppIcon name="group" :size="20" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.staff') }}</span>
      </button>

      <!-- Refunds -->
      <button type="button" @click="navigate('/dashboard/refunds')" :data-active="isRouteActive('/dashboard/refunds')"
        :class="[
          'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/refunds')
            ? 'text-white font-bold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
        ]">
        <AppIcon name="currency_exchange" :size="20" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.refunds') }}</span>
      </button>

      <!-- Outlets -->
      <button v-if="authStore.isOwner" type="button" @click="navigate('/dashboard/outlets')"
        :data-active="isRouteActive('/dashboard/outlets')" :class="[
          'w-full flex items-center text-sm font-bold transition-colors duration-150 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/outlets')
            ? 'text-white font-bold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]',
        ]">
        <AppIcon name="storefront" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.outlets') }}</span>
      </button>

      <!-- Customer Demo Link -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-[opacity,max-height,padding,transform] duration-300 ease-in-out',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">{{ t('sidebar.sectionCustomerView') }}</span>
      </div>
      <button type="button" @click="openCustomerPreview" :class="[
        'w-full flex items-center text-sm font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors duration-150 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3'
      ]">
        <AppIcon name="phone_android" :size="18" class="shrink-0 transition-colors duration-150" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-[opacity,transform,max-width] duration-300 ease-in-out text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">{{ t('sidebar.scanQrDemo') }}</span>
      </button>
    </nav>

    <!-- Click backdrop to close profile menu & notification menu -->
    <div v-if="isProfileMenuOpen || isNotificationMenuOpen"
      @click="isProfileMenuOpen = false; isNotificationMenuOpen = false" class="fixed inset-0 z-40 bg-transparent">
    </div>

    <!-- Bottom Profile & Action Bar (Claude-style footer) -->
    <div
      class="p-3 border-t border-[#E2E8F0] dark:border-[#334155] shrink-0 relative bg-white dark:bg-[#273142] overflow-visible">
      <!-- When Expanded -->
      <div v-show="!collapsed"
        class="flex items-center justify-between gap-1.5 overflow-visible transition-opacity duration-300"
        :class="collapsed ? 'opacity-0 pointer-events-none' : 'opacity-100'">
        <!-- Profile / Account trigger with dropdown -->
        <div class="relative flex-1 min-w-0">
          <button type="button" @click.stop="toggleProfileMenu"
            class="w-full flex items-center gap-2 p-1 rounded-lg hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer text-left group">
            <!-- User Profile Avatar Image -->
            <div class="w-8 h-8 rounded-full overflow-hidden shrink-0 ring-1 ring-slate-200 dark:ring-slate-700 shadow-xs">
              <img :src="userAvatar" :alt="authStore.currentUser?.name || 'User'" class="w-full h-full object-cover" />
            </div>

            <div class="flex items-center gap-1.5 min-w-0 flex-1">
              <span class="text-sm font-bold text-[#1E293B] dark:text-white truncate">
                {{ authStore.currentUser?.name?.split(' ')[0] || 'Yoapipp' }}
              </span>
              <span class="text-xs text-slate-400 dark:text-slate-500 font-medium whitespace-nowrap">
                · {{ (authStore.currentUser as any)?.plan || (authStore.currentUser as any)?.tenant?.plan?.name ||
                  'Free' }}
              </span>
              <AppIcon name="expand_more" :size="18"
                class="text-slate-400 shrink-0 ml-auto mr-0.5 origin-center transition-transform duration-200 group-hover:text-slate-600 dark:group-hover:text-slate-300"
                :class="{ 'rotate-180': isProfileMenuOpen }" />
            </div>
          </button>

          <!-- Settings Popup Menu with Reusable Transition Animation (-10% compact scale) -->
          <AppDropdownMotion placement="top">
            <div v-if="isProfileMenuOpen"
              class="absolute bottom-full left-0 mb-2 w-[260px] bg-white dark:bg-[#1E293B] rounded-xl border border-[#E2E8F0] dark:border-[#334155] shadow-xl p-1.5 z-50 text-left origin-bottom-left">
              <!-- Top Header: User Profile Info with Avatar -->
              <div class="px-3 pt-2 pb-2.5 border-b border-[#E2E8F0] dark:border-[#334155] mb-1 flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 ring-1 ring-slate-200 dark:ring-slate-700 shadow-xs">
                  <img :src="userAvatar" :alt="authStore.currentUser?.name || 'User'" class="w-full h-full object-cover" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-bold text-slate-900 dark:text-white truncate">
                    {{ authStore.currentUser?.name || 'User Lapaqu' }}
                  </p>
                  <p class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate mt-0.5 select-all">
                    {{ authStore.currentUser?.email || '' }}
                  </p>
                </div>
              </div>

              <!-- Menu Items -->
              <div class="space-y-0.5">
                <button type="button" @click="isProfileMenuOpen = false; openSettingsModal()"
                  class="w-full h-9.5 px-3 flex items-center gap-2.5 text-sm font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] rounded-lg transition-colors duration-150 cursor-pointer text-left group">
                  <AppIcon name="settings" :size="18"
                    class="shrink-0 transition-colors duration-150 text-[#475569] dark:text-[#CBD5E1] group-hover:text-slate-900 dark:group-hover:text-white" />
                  <span class="truncate">{{ t('sidebar.settings') }}</span>
                </button>

                <button type="button" @click="isProfileMenuOpen = false; openSettingsModal('billing')"
                  class="w-full h-9.5 px-3 flex items-center gap-2.5 text-sm font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] rounded-lg transition-colors duration-150 cursor-pointer text-left group">
                  <AppIcon name="arrow_circle_up" :size="18"
                    class="shrink-0 transition-colors duration-150 text-[#475569] dark:text-[#CBD5E1] group-hover:text-slate-900 dark:group-hover:text-white" />
                  <span class="truncate">{{ t('sidebar.subscription') }}</span>
                </button>
              </div>

              <!-- Divider -->
              <div class="border-t border-[#E2E8F0] dark:border-[#334155] my-1"></div>

              <!-- Group 3: Log out -->
              <div>
                <button type="button" @click="isProfileMenuOpen = false; isLogoutModalOpen = true"
                  class="w-full h-9.5 px-3 flex items-center gap-2.5 text-sm font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors duration-150 cursor-pointer text-left group">
                  <AppIcon name="logout" :size="18"
                    class="shrink-0 transition-colors duration-150 text-rose-600 dark:text-rose-400 group-hover:text-rose-700 dark:group-hover:text-rose-300" />
                  <span class="truncate">{{ t('sidebar.logout') }}</span>
                </button>
              </div>
            </div>
          </AppDropdownMotion>
        </div>

        <!-- Action Icons (Notification & Search) -->
        <div class="flex items-center gap-0.5 shrink-0 overflow-visible">
          <!-- Notification Icon Button & Dropdown Container -->
          <div class="relative overflow-visible">
            <button type="button" @click.stop="toggleNotificationMenu" :class="[
              'w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer relative',
              isNotificationMenuOpen
                ? 'text-[#4880FF] bg-blue-50 dark:bg-blue-900/30'
                : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 hover:bg-[#F1F5F9] dark:hover:bg-[#334155]'
            ]" title="Notifikasi">
              <AppIcon name="notifications" :size="18" />
              <!-- Notification badge dot -->
              <span v-if="unreadCount > 0"
                class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#4880FF] rounded-full ring-2 ring-white dark:ring-[#273142]"></span>
            </button>

            <!-- Notification Dropdown Popup (rounded-lg, matching background) -->
            <div v-if="isNotificationMenuOpen"
              class="absolute bottom-full right-0 mb-3 w-[360px] bg-slate-50 dark:bg-[#1B2431] rounded-xl border border-slate-200/90 dark:border-slate-700/80 shadow-xl shadow-slate-900/10 dark:shadow-black/40 p-0 z-50 overflow-hidden text-left">
              <!-- Header -->
              <div
                class="px-3.5 py-2.5 border-b border-slate-200/70 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-900 dark:text-white">Notifikasi</span>
                  <span v-if="unreadCount > 0"
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-[#4880FF] dark:bg-blue-900/50 dark:text-blue-300">
                    {{ unreadCount }} Baru
                  </span>
                </div>
                <button v-if="unreadCount > 0" type="button" @click="markAllRead"
                  class="text-[11px] font-semibold text-[#4880FF] hover:underline cursor-pointer">
                  Tandai dibaca
                </button>
              </div>

              <!-- Notification items list -->
              <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
                <div v-if="notifications.length === 0"
                  class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  Tidak ada notifikasi baru
                </div>
                <div v-else v-for="n in notifications" :key="n.id" @click="n.isRead = true" :class="[
                  'p-3 flex items-start gap-2.5 transition-colors cursor-pointer text-left',
                  n.isRead ? 'hover:bg-slate-100/60 dark:hover:bg-slate-800/40 opacity-75' : 'bg-blue-50/40 dark:bg-blue-950/20 hover:bg-blue-50/70 dark:hover:bg-blue-950/30'
                ]">
                  <!-- Icon by type -->
                  <div :class="[
                    'w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                    n.type === 'order' ? 'bg-blue-100 dark:bg-blue-900/40 text-[#4880FF]' :
                      n.type === 'warning' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-600' :
                        n.type === 'refund' ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-600' :
                          'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600'
                  ]">
                    <AppIcon v-if="n.type === 'order'" name="shopping_bag" :size="16" />
                    <AppIcon v-else-if="n.type === 'warning'" name="warning" :size="16" />
                    <AppIcon v-else-if="n.type === 'refund'" name="replay" :size="16" />
                    <AppIcon v-else name="check" :size="16" />
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1">
                      <h5 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ n.title }}</h5>
                      <span v-if="!n.isRead" class="w-1.5 h-1.5 rounded-full bg-[#4880FF] shrink-0"></span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2 leading-relaxed">{{
                      n.message }}</p>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 block">{{ n.time }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div
                class="p-2 border-t border-slate-200/70 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-900/30 text-center">
                <button type="button" @click="isNotificationMenuOpen = false; navigate('/dashboard/notifications')"
                  class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-900 transition-colors cursor-pointer">
                  Lihat Semua Notifikasi
                </button>
              </div>
            </div>
          </div>

          <!-- Search Icon Button -->
          <button type="button" @click="navigate('/dashboard')"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
            title="Cari">
            <AppIcon name="search" :size="18" />
          </button>
        </div>
      </div>

      <!-- When Collapsed -->
      <div v-show="collapsed" class="flex flex-col items-center justify-center gap-2 transition-opacity duration-300"
        :class="!collapsed ? 'opacity-0 pointer-events-none' : 'opacity-100'">
        <button type="button" @click="openSettingsModal('account')"
          class="w-10 h-10 rounded-xl flex items-center justify-center hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          title="Pengaturan Akun">
          <div class="w-8 h-8 rounded-full overflow-hidden shrink-0 ring-1 ring-slate-200 dark:ring-slate-700 shadow-xs">
            <img :src="userAvatar" :alt="authStore.currentUser?.name || 'User'" class="w-full h-full object-cover" />
          </div>
        </button>
      </div>
    </div>

    <!-- Right Border Resize Handle (Thin hairline stroke, transparent hit area) -->
    <div v-if="!collapsed" @mousedown="startResize" @dblclick="resetSidebarWidth"
      class="absolute top-0 -right-1 w-2.5 h-full cursor-col-resize z-40 group select-none flex justify-end"
      title="Geser untuk mengatur lebar sidebar (Maksimal +50%). Klik 2x untuk reset ke 260px.">
      <!-- Hairline 1.5px stroke indicator on hover and active drag -->
      <div class="w-[1.5px] h-full bg-transparent group-hover:bg-[#4880FF] transition-colors duration-150"
        :class="{ '!bg-[#4880FF]': isResizing }"></div>
    </div>

    <!-- Invisible full-screen overlay during active drag to guarantee continuous mouse tracking -->
    <div v-if="isResizing" class="fixed inset-0 z-[9999] cursor-col-resize select-none bg-transparent"
      @mousemove="handleMouseMove" @mouseup="handleMouseUp"></div>

    <!-- Logout Confirmation Modal -->
    <AppModal v-model="isLogoutModalOpen" :title="t('sidebar.logoutConfirmTitle')" maxWidth="sm">
      <div class="space-y-3">
        <p class="text-sm text-slate-600 dark:text-slate-400">
          {{ t('sidebar.logoutConfirmDesc') }}
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="secondary" size="md" @click="isLogoutModalOpen = false">
            {{ t('sidebar.cancel') }}
          </AppButton>
          <AppButton variant="danger" size="md" @click="handleLogout">
            {{ t('sidebar.yesLogout') }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </aside>
</template>
