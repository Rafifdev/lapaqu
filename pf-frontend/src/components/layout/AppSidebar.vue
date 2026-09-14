<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'
import { useSettingsModal } from '@/composables/useSettingsModal'

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
const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || 'Cabang Senopati Utama')
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || 'Kopi Kenangan Senopati')

onMounted(async () => {
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
const { openSettingsModal } = useSettingsModal()

const menuExpanded = ref(true)
const bahanBakuExpanded = ref(false)
const stokExpanded = ref(false)
const laporanExpanded = ref(false)
const isProfileMenuOpen = ref(false)
const isNotificationMenuOpen = ref(false)
const isLogoutModalOpen = ref(false)

// Notifications List
const notifications = ref([
  {
    id: '1',
    title: 'Pesanan Masuk #ORD-260830-001',
    message: 'Pelanggan Meja M03 menyelesaikan pembayaran QRIS Rp 72.000.',
    time: '5m yang lalu',
    type: 'order',
    isRead: false
  },
  {
    id: '2',
    title: 'Stok Menipis: Sirup Karamel',
    message: 'Sisa stok tinggal 2 botol. Harap segera restock inventori.',
    time: '25m yang lalu',
    type: 'warning',
    isRead: false
  },
  {
    id: '3',
    title: 'Pengajuan Refund #REF-012',
    message: 'Kasir Budi mengajukan refund pesanan #ORD-089 Rp 35.000.',
    time: '1j yang lalu',
    type: 'refund',
    isRead: false
  },
  {
    id: '4',
    title: 'Settlement Pembayaran QRIS',
    message: 'Dana settlement Xendit Rp 1.450.000 telah masuk rekening.',
    time: '3j yang lalu',
    type: 'success',
    isRead: true
  }
])

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
        <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-9 h-9 sm:w-10 sm:h-10 object-contain shrink-0" />
        <div class="flex flex-col min-w-0 flex-1 justify-center">
          <span
            class="text-sm sm:text-[15px] font-black tracking-tight font-sans text-[#1E293B] dark:text-white leading-tight truncate"
            :title="tenantName">
            {{ tenantName }}
          </span>
          <span
            class="text-[10px] sm:text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8] leading-none mt-1 truncate"
            :title="outletName">
            {{ outletName }}
          </span>
        </div>
      </router-link>

      <!-- Expand / Collapse Toggle Button (Claude PanelLeft Icon, positioned to the right of logo) -->
      <button type="button" @click="handleToggle"
        class="w-9 h-9 rounded-xl flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-all duration-300 cursor-pointer shrink-0"
        :title="collapsed ? 'Perluas Sidebar' : 'Ciutkan Sidebar'">
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
        class="absolute rounded-xl bg-[#E2EAF8] dark:bg-[#334155] pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0 overflow-hidden"
        :style="{
          top: `${indicatorTop}px`,
          left: `${indicatorLeft}px`,
          width: `${indicatorWidth}px`,
          height: `${indicatorHeight}px`,
        }">
        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-[3.5px] h-6.5 bg-[#4880FF] rounded-r-sm" />
      </div>

      <!-- Main Dashboard -->
      <button type="button" @click="navigate('/dashboard')" :data-active="isRouteActive('/dashboard')" :class="[
        'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteActive('/dashboard')
          ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
      ]">
        <AppIcon name="dashboard" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Dashboard</span>
      </button>

      <!-- Section Title: Operasional -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)]',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">Operasional</span>
      </div>

      <!-- POS Kasir -->
      <button type="button" @click="navigate('/pos/orders')" :data-active="isRouteGroupActive('/pos')" :class="[
        'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteGroupActive('/pos')
          ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
      ]">
        <AppIcon name="point_of_sale" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Kasir POS</span>
      </button>

      <!-- KDS -->
      <button type="button" @click="navigate('/kds/queue')" :data-active="isRouteGroupActive('/kds')" :class="[
        'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
        isRouteGroupActive('/kds')
          ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
          : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
      ]">
        <AppIcon name="soup_kitchen" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Dapur (KDS)</span>
      </button>

      <!-- Tables QR -->
      <button type="button" @click="navigate('/dashboard/tables')" :data-active="isRouteActive('/dashboard/tables')"
        :class="[
          'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/tables')
            ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
        ]">
        <AppIcon name="table_restaurant" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Meja & QR Code</span>
      </button>

      <!-- Section Title: Owner Resto -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)]',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">Owner Resto</span>
      </div>

      <!-- 1. Menu & Kategori (Expandable) -->
      <div>
        <button type="button" @click="menuExpanded = !menuExpanded"
          :data-active="isRouteGroupActive('/dashboard/menu') ? 'true' : undefined" :class="[
            'w-full flex items-center text-base font-bold transition-all duration-350 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/menu')
              ? 'text-[#4880FF] dark:text-[#93C5FD]'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="restaurant_menu" :size="18" class="shrink-0 transition-colors" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">Menu & Kategori</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 text-slate-400 shrink-0 ml-auto"
            :class="{ 'rotate-180': menuExpanded }">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Menu & Kategori -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="menuExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/menu/items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/menu/items')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/menu/items') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Daftar Menu</span>
            </button>
            <button type="button" @click="navigate('/dashboard/menu/categories')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/menu/categories')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/menu/categories') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Kategori Menu</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Bahan Baku (Expandable) -->
      <div>
        <button type="button" @click="bahanBakuExpanded = !bahanBakuExpanded"
          :data-active="isRouteGroupActive('/dashboard/ingredients') ? 'true' : undefined" :class="[
            'w-full flex items-center text-base font-bold transition-all duration-350 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/ingredients')
              ? 'text-[#4880FF] dark:text-[#93C5FD]'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="egg" :size="18" class="shrink-0 transition-colors" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">Bahan Baku</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 text-slate-400 shrink-0 ml-auto"
            :class="{ 'rotate-180': bahanBakuExpanded }">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Bahan Baku -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="bahanBakuExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/ingredients/items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/items')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/items') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Daftar Bahan Baku</span>
            </button>
            <button type="button" @click="navigate('/dashboard/ingredients/categories')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/categories')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/categories') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Kategori Bahan Baku</span>
            </button>
            <button type="button" @click="navigate('/dashboard/ingredients/recipes')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/ingredients/recipes')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/ingredients/recipes') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Resep Menu</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 3. Stok (Expandable) -->
      <div>
        <button type="button" @click="stokExpanded = !stokExpanded"
          :data-active="isRouteGroupActive('/dashboard/stock') ? 'true' : undefined" :class="[
            'w-full flex items-center text-base font-bold transition-all duration-350 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/stock')
              ? 'text-[#4880FF] dark:text-[#93C5FD]'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="inventory_2" :size="18" class="shrink-0 transition-colors" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">Stok</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 text-slate-400 shrink-0 ml-auto"
            :class="{ 'rotate-180': stokExpanded }">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Stok -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="stokExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/stock/current')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/stock/current')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/current') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Stok Saat Ini</span>
            </button>
            <button type="button" @click="navigate('/dashboard/stock/opname')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/stock/opname')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/opname') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Stok Opname</span>
            </button>
            <button type="button" @click="navigate('/dashboard/stock/history')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/stock/history')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/stock/history') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Riwayat Stok</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Laporan & Analitik (Expandable) -->
      <div>
        <button type="button" @click="laporanExpanded = !laporanExpanded"
          :data-active="isRouteGroupActive('/dashboard/reports')" :class="[
            'w-full flex items-center text-base font-bold transition-all duration-350 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
            collapsed ? 'justify-center px-0' : 'justify-between px-3.5',
            isRouteGroupActive('/dashboard/reports')
              ? 'text-[#4880FF] dark:text-[#93C5FD]'
              : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
          ]">
          <div class="flex items-center gap-3 min-w-0">
            <AppIcon name="bar_chart_4_bars" :size="18" class="shrink-0 transition-colors" />
            <span :class="[
              'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
              collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
            ]">Laporan & Analitik</span>
          </div>
          <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="transition-transform duration-200 text-slate-400 shrink-0 ml-auto"
            :class="{ 'rotate-180': laporanExpanded }">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Submenu: Laporan & Analitik -->
        <div class="grid transition-all duration-300 ease-[cubic-bezier(0.34,1.3,0.64,1)] pl-9 pr-2 overflow-hidden"
          :class="laporanExpanded && !collapsed ? 'grid-rows-[1fr] opacity-100 py-1' : 'grid-rows-[0fr] opacity-0 py-0'">
          <div class="min-h-0 space-y-1">
            <button type="button" @click="navigate('/dashboard/reports/sales')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/reports/sales')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/sales') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Laporan Penjualan</span>
            </button>
            <button type="button" @click="navigate('/dashboard/reports/top-items')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/reports/top-items')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/top-items') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Menu Terlaris</span>
            </button>
            <button type="button" @click="navigate('/dashboard/reports/peak-hours')"
              :class="['w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer text-left',
                isRouteActive('/dashboard/reports/peak-hours')
                  ? 'bg-blue-50/80 text-[#4880FF] dark:bg-blue-900/30 dark:text-blue-300'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/40']">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="isRouteActive('/dashboard/reports/peak-hours') ? 'bg-[#4880FF]' : 'bg-slate-300 dark:bg-slate-600'" />
              <span class="truncate">Jam Ramai</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Staff -->
      <button type="button" @click="navigate('/dashboard/staff')" :data-active="isRouteActive('/dashboard/staff')"
        :class="[
          'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/staff')
            ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
        ]">
        <AppIcon name="group" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Staff & Role</span>
      </button>

      <!-- Refunds -->
      <button type="button" @click="navigate('/dashboard/refunds')" :data-active="isRouteActive('/dashboard/refunds')"
        :class="[
          'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/refunds')
            ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
        ]">
        <AppIcon name="currency_exchange" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Persetujuan Refund</span>
      </button>

      <!-- Outlets -->
      <button type="button" @click="navigate('/dashboard/outlets')" :data-active="isRouteActive('/dashboard/outlets')"
        :class="[
          'w-full flex items-center text-base font-bold transition-all duration-350 relative z-10 group cursor-pointer h-11 rounded-lg whitespace-nowrap overflow-hidden bg-transparent text-left',
          collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3',
          isRouteActive('/dashboard/outlets')
            ? 'text-[#4880FF] dark:text-[#93C5FD] font-extrabold'
            : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white',
        ]">
        <AppIcon name="storefront" :size="18" class="shrink-0 transition-colors" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Cabang & Outlet</span>
      </button>

      <!-- Customer Demo Link -->
      <div :class="[
        'whitespace-nowrap overflow-hidden transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)]',
        collapsed ? 'opacity-0 max-h-0 py-0 -translate-x-3 pointer-events-none' : 'opacity-100 max-h-10 pt-3 pb-1 px-3 translate-x-0 text-left'
      ]">
        <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">Customer View</span>
      </div>
      <button type="button" @click="navigate('/order/outlet-001/M03')" :class="[
        'w-full flex items-center text-base font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] transition-all duration-350 cursor-pointer h-11 relative z-10 group rounded-xl whitespace-nowrap overflow-hidden bg-transparent text-left',
        collapsed ? 'justify-center px-0' : 'justify-start px-3.5 gap-3'
      ]">
        <AppIcon name="phone_android" :size="18" class="group-hover:text-[#4880FF] shrink-0" />
        <span :class="[
          'whitespace-nowrap overflow-hidden text-ellipsis transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] text-left',
          collapsed ? 'opacity-0 -translate-x-4 max-w-0 pointer-events-none' : 'opacity-100 translate-x-0'
        ]">Scan QR per Meja</span>
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
          <button type="button" @click="toggleProfileMenu"
            class="w-full flex items-center gap-2 p-1 rounded-xl hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer text-left group">
            <!-- Floral / Geometric Avatar matching Claude screenshot -->
            <div
              class="w-8 h-8 rounded-full bg-[#60A5FA] dark:bg-[#3B82F6] text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                <circle cx="12" cy="7" r="3.5" opacity="0.9" />
                <circle cx="12" cy="17" r="3.5" opacity="0.9" />
                <circle cx="7" cy="12" r="3.5" opacity="0.9" />
                <circle cx="17" cy="12" r="3.5" opacity="0.9" />
                <circle cx="12" cy="12" r="2" fill="white" />
              </svg>
            </div>

            <div class="flex items-center gap-1 min-w-0 flex-1">
              <span class="text-sm font-bold text-[#1E293B] dark:text-white truncate">
                {{ authStore.currentUser?.name?.split(' ')[0] || 'Yoapipp' }}
              </span>
              <span class="text-xs text-slate-400 dark:text-slate-500 font-medium whitespace-nowrap">· Free</span>
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round"
                class="text-slate-400 shrink-0 ml-auto mr-0.5 group-hover:text-slate-600 dark:group-hover:text-slate-300">
                <path d="m6 9 6 6 6-9" />
              </svg>
            </div>
          </button>

          <!-- Profile Popup Menu (Claude style) -->
          <div v-if="isProfileMenuOpen"
            class="absolute bottom-full left-0 mb-2 w-64 bg-white dark:bg-[#1E293B] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700/80 p-1.5 z-50 animate-in fade-in zoom-in-95 duration-150 ring-1 ring-black/5 dark:ring-white/10">
            <!-- Top Header: Only user email -->
            <div class="px-3 pt-2 pb-1.5">
              <p class="text-xs font-normal text-slate-500 dark:text-slate-400 truncate select-all">
                {{ authStore.currentUser?.email || 'owner@kopisenopati.id' }}
              </p>
            </div>

            <!-- Group 1: Settings & Upgrade plan -->
            <div class="space-y-0.5">
              <button type="button" @click="isProfileMenuOpen = false; openSettingsModal()"
                class="w-full text-left px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-lg flex items-center gap-2.5 transition-colors cursor-pointer group/item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  class="text-slate-400 dark:text-slate-400 group-hover/item:text-slate-700 dark:group-hover/item:text-slate-200 shrink-0">
                  <path
                    d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <span>Settings</span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono ml-auto">Ctrl ,</span>
              </button>

              <button type="button" @click="isProfileMenuOpen = false; openSettingsModal('billing')"
                class="w-full text-left px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-lg flex items-center gap-2.5 transition-colors cursor-pointer group/item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  class="text-slate-400 dark:text-slate-400 group-hover/item:text-slate-700 dark:group-hover/item:text-slate-200 shrink-0">
                  <circle cx="12" cy="12" r="10" />
                  <path d="m16 12-4-4-4 4" />
                  <path d="M12 16V8" />
                </svg>
                <span>Upgrade plan</span>
              </button>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-100 dark:border-slate-700/60 my-1"></div>

            <!-- Group 3: Log out -->
            <div>
              <button type="button" @click="isProfileMenuOpen = false; isLogoutModalOpen = true"
                class="w-full text-left px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-lg flex items-center gap-2.5 transition-colors cursor-pointer group/item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  class="text-slate-400 dark:text-slate-400 group-hover/item:text-slate-700 dark:group-hover/item:text-slate-200 shrink-0">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <polyline points="16 17 21 12 16 7" />
                  <line x1="21" x2="21" y1="12" y2="9" />
                </svg>
                <span>Log out</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Action Icons (Notification & Search) -->
        <div class="flex items-center gap-0.5 shrink-0 overflow-visible">
          <!-- Notification Icon Button & Dropdown Container -->
          <div class="relative overflow-visible">
            <button type="button" @click="toggleNotificationMenu" :class="[
              'w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer relative',
              isNotificationMenuOpen
                ? 'text-[#4880FF] bg-blue-50 dark:bg-blue-900/30'
                : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155]'
            ]" title="Notifikasi">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
              </svg>
              <!-- Notification badge dot -->
              <span v-if="unreadCount > 0"
                class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#4880FF] rounded-full ring-2 ring-white dark:ring-[#273142]"></span>
            </button>

            <!-- Notification Dropdown Popup (Left edge above icon, extends predominantly to the right) -->
            <div v-if="isNotificationMenuOpen"
              class="absolute bottom-full left-0 mb-3 w-[360px] bg-white dark:bg-[#1E293B] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700/80 p-0 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150 text-left ring-1 ring-black/5 dark:ring-white/10">
              <!-- Header -->
              <div
                class="px-3.5 py-2.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
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
                <div v-for="n in notifications" :key="n.id" @click="n.isRead = true" :class="[
                  'p-3 flex items-start gap-2.5 transition-colors cursor-pointer text-left',
                  n.isRead ? 'hover:bg-slate-50 dark:hover:bg-slate-800/40 opacity-75' : 'bg-blue-50/40 dark:bg-blue-950/20 hover:bg-blue-50/70 dark:hover:bg-blue-950/30'
                ]">
                  <!-- Icon by type -->
                  <div :class="[
                    'w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                    n.type === 'order' ? 'bg-blue-100 dark:bg-blue-900/40 text-[#4880FF]' :
                      n.type === 'warning' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-600' :
                        n.type === 'refund' ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-600' :
                          'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600'
                  ]">
                    <svg v-if="n.type === 'order'" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                      stroke-linejoin="round">
                      <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                      <path d="M3 6h18" />
                      <path d="M16 10a4 4 0 0 1-8 0" />
                    </svg>
                    <svg v-else-if="n.type === 'warning'" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                      stroke-linejoin="round">
                      <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                      <path d="M12 9v4" />
                      <path d="M12 17h.01" />
                    </svg>
                    <svg v-else-if="n.type === 'refund'" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                      stroke-linejoin="round">
                      <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                      <path d="M21 3v5h-5" />
                      <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                      <path d="M8 16H3v5" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                      fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20 6 9 17l-5-5" />
                    </svg>
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
                class="p-2 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 text-center">
                <button type="button" @click="isNotificationMenuOpen = false; navigate('/dashboard/notifications')"
                  class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-[#4880FF] dark:hover:text-[#4880FF] transition-colors cursor-pointer">
                  Lihat Semua Notifikasi
                </button>
              </div>
            </div>
          </div>

          <!-- Search Icon Button -->
          <button type="button" @click="navigate('/dashboard')"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
            title="Cari">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
          </button>
        </div>
      </div>

      <!-- When Collapsed -->
      <div v-show="collapsed" class="flex flex-col items-center justify-center gap-2 transition-opacity duration-300"
        :class="!collapsed ? 'opacity-0 pointer-events-none' : 'opacity-100'">
        <button type="button" @click="openSettingsModal('account')"
          class="w-10 h-10 rounded-xl flex items-center justify-center hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          title="Pengaturan Akun (Ctrl ,)">
          <div
            class="w-8 h-8 rounded-full bg-[#60A5FA] dark:bg-[#3B82F6] text-white flex items-center justify-center font-bold text-xs shadow-xs">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <circle cx="12" cy="7" r="3.5" opacity="0.9" />
              <circle cx="12" cy="17" r="3.5" opacity="0.9" />
              <circle cx="7" cy="12" r="3.5" opacity="0.9" />
              <circle cx="17" cy="12" r="3.5" opacity="0.9" />
              <circle cx="12" cy="12" r="2" fill="white" />
            </svg>
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
    <AppModal v-model="isLogoutModalOpen" title="Konfirmasi Keluar Akun" size="sm">
      <div class="space-y-3">
        <p class="text-xs text-slate-600 dark:text-slate-400">
          Apakah Anda yakin ingin keluar dari akun Lapaqu? Anda dapat masuk kembali kapan saja dengan email dan kata
          sandi Anda.
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="secondary" size="sm" @click="isLogoutModalOpen = false">
            Batal
          </AppButton>
          <AppButton variant="danger" size="sm" @click="handleLogout">
            Keluar
          </AppButton>
        </div>
      </template>
    </AppModal>
  </aside>
</template>
