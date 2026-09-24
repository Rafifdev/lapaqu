import { createRouter, createWebHistory } from 'vue-router'
import { isSessionExpired, clearSessionStorage } from '@/utils/session'

// Layouts
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import CustomerLayout from '@/layouts/CustomerLayout.vue'
import PosLayout from '@/layouts/PosLayout.vue'
import KdsLayout from '@/layouts/KdsLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const getPairedOutlet = () => {
  const saved = localStorage.getItem('lapaqu_paired_outlet')
  if (saved) {
    try {
      const parsed = JSON.parse(saved)
      if (parsed?.id) return parsed
    } catch {}
  }
  return null
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Default Root Route: jika perangkat sudah dipair, diarahkan ke pilihan staff
    {
      path: '/',
      redirect: () => {
        const paired = getPairedOutlet()
        return paired ? '/auth/outlet-staff' : '/auth/login'
      },
    },

    // Alias /login
    {
      path: '/login',
      redirect: () => {
        const paired = getPairedOutlet()
        return paired ? '/auth/outlet-staff' : '/auth/login'
      },
    },
    // Alias /register to /auth/register
    {
      path: '/register',
      redirect: '/auth/register',
    },
    // Alias /outlet/login
    {
      path: '/outlet/login',
      redirect: () => {
        const paired = getPairedOutlet()
        return paired ? '/auth/outlet-staff' : '/auth/outlet-login'
      },
    },
    {
      path: '/outlet/staff',
      redirect: '/auth/outlet-staff',
    },

    // ================= AUTH ROUTES =================
    {
      path: '/auth',
      component: AuthLayout,
      meta: { guestOnly: true },
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/views/auth/LoginPage.vue'),
        },
        {
          path: 'callback',
          name: 'auth-callback',
          component: () => import('@/views/auth/GoogleCallback.vue'),
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/views/auth/RegisterPage.vue'),
        },
        {
          path: 'outlet-login',
          name: 'outlet-login',
          component: () => import('@/views/auth/OutletLoginPage.vue'),
        },
        {
          path: 'outlet-staff',
          name: 'outlet-staff',
          component: () => import('@/views/auth/OutletStaffLoginPage.vue'),
        },
        {
          path: 'verify-2fa',
          name: 'verify-2fa',
          component: () => import('@/views/auth/Verify2FAPage.vue'),
        },
        {
          path: 'forgot-password',
          name: 'forgot-password',
          component: () => import('@/views/auth/ForgotPasswordPage.vue'),
        },
        {
          path: 'reset-password',
          name: 'reset-password',
          component: () => import('@/views/auth/ResetPasswordPage.vue'),
        },
        {
          path: 'setup-2fa',
          name: 'setup-2fa',
          component: () => import('@/views/auth/Setup2FAPage.vue'),
        },
      ],
    },

    // ================= CUSTOMER SELF-ORDER ROUTES =================
    {
      path: '/order/:outletId/:tableCode',
      component: CustomerLayout,
      meta: { requiresAuth: false },
      children: [
        {
          path: '',
          name: 'customer-menu',
          component: () => import('@/views/customer/MenuBrowsePage.vue'),
        },
        {
          path: 'landing',
          name: 'customer-landing',
          component: () => import('@/views/customer/ScanLandingPage.vue'),
        },
        {
          path: 'item/:id',
          name: 'customer-menu-detail',
          component: () => import('@/views/customer/MenuDetailPage.vue'),
        },
        {
          path: 'cart',
          redirect: to => ({ name: 'customer-my-order', params: to.params }),
        },
        {
          path: 'my-order',
          name: 'customer-my-order',
          component: () => import('@/views/customer/MyOrderPage.vue'),
        },
        {
          path: 'va-instructions',
          name: 'customer-va-instructions',
          component: () => import('@/views/customer/PaymentInstructionsPage.vue'),
        },
        {
          path: 'checkout',
          redirect: to => ({ name: 'customer-my-order', params: to.params }),
        },
        {
          path: 'status',
          name: 'customer-order-status',
          component: () => import('@/views/customer/OrderStatusPage.vue'),
        },
        {
          path: 'history',
          name: 'customer-order-history',
          component: () => import('@/views/customer/OrderHistoryPage.vue'),
        },
        {
          path: 'unavailable',
          name: 'customer-unavailable',
          component: () => import('@/views/customer/OutletUnavailablePage.vue'),
        },
      ],
    },



    // ================= POS KASIR ROUTES =================
    {
      path: '/pos',
      component: PosLayout,
      meta: { requiresAuth: true, roles: ['owner', 'kasir', 'superadmin'] },
      children: [
        {
          path: '',
          redirect: '/pos/orders',
        },
        {
          path: 'orders',
          name: 'pos-orders',
          component: () => import('@/views/pos/IncomingOrdersPage.vue'),
        },
        {
          path: 'manual',
          name: 'pos-manual',
          component: () => import('@/views/pos/ManualOrderPage.vue'),
        },
        {
          path: 'payment',
          name: 'pos-payment',
          component: () => import('@/views/pos/PaymentPage.vue'),
        },
        {
          path: 'history',
          name: 'pos-history',
          component: () => import('@/views/pos/TransactionHistoryPage.vue'),
        },
        {
          path: 'tables',
          name: 'pos-tables',
          component: () => import('@/views/pos/TablesPage.vue'),
        },
        {
          path: 'quick-toggle',
          name: 'pos-quick-toggle',
          component: () => import('@/views/pos/QuickMenuTogglePage.vue'),
        },
      ],
    },

    // ================= KDS KITCHEN ROUTES =================
    {
      path: '/kds',
      component: KdsLayout,
      meta: { requiresAuth: true, roles: ['owner', 'kitchen_staff', 'kasir', 'superadmin'] },
      children: [
        {
          path: '',
          redirect: '/kds/queue',
        },
        {
          path: 'queue',
          name: 'kds-queue',
          component: () => import('@/views/kds/OrderQueuePage.vue'),
        },
        {
          path: 'completed',
          name: 'kds-completed',
          component: () => import('@/views/kds/CompletedOrdersPage.vue'),
        },
        {
          path: 'history',
          name: 'kds-history',
          component: () => import('@/views/kds/OrderHistoryPage.vue'),
        },
      ],
    },

    // ================= OWNER DASHBOARD ROUTES =================
    {
      path: '/dashboard',
      component: DashboardLayout,
      meta: { requiresAuth: true, roles: ['owner', 'store_manager', 'superadmin'] },
      children: [
        {
          path: '',
          name: 'dashboard-home',
          component: () => import('@/views/dashboard/DashboardPage.vue'),
        },
        // Menu & Kategori
        {
          path: 'menu/items',
          name: 'dashboard-menu-items',
          component: () => import('@/views/dashboard/menu/MenuItemsPage.vue'),
        },
        {
          path: 'menu/categories',
          name: 'dashboard-categories',
          component: () => import('@/views/dashboard/menu/CategoriesPage.vue'),
        },

        // Bahan Baku
        {
          path: 'ingredients',
          redirect: '/dashboard/ingredients/items',
        },
        {
          path: 'ingredients/items',
          name: 'dashboard-ingredients',
          component: () => import('@/views/dashboard/ingredients/IngredientsPage.vue'),
        },
        {
          path: 'ingredients/categories',
          name: 'dashboard-ingredient-categories',
          component: () => import('@/views/dashboard/ingredients/IngredientCategoriesPage.vue'),
        },
        {
          path: 'ingredients/recipes',
          name: 'dashboard-recipes',
          component: () => import('@/views/dashboard/ingredients/RecipesPage.vue'),
        },

        // Stok
        {
          path: 'stock',
          redirect: '/dashboard/stock/current',
        },
        {
          path: 'menu/stock',
          redirect: '/dashboard/stock/current',
        },
        {
          path: 'stock/current',
          name: 'dashboard-current-stock',
          component: () => import('@/views/dashboard/stock/CurrentStockPage.vue'),
        },
        {
          path: 'stock/opname',
          name: 'dashboard-stock-opname',
          component: () => import('@/views/dashboard/stock/StockOpnamePage.vue'),
        },
        {
          path: 'stock/history',
          name: 'dashboard-stock-history',
          component: () => import('@/views/dashboard/stock/StockHistoryPage.vue'),
        },
        {
          path: 'tables',
          name: 'dashboard-tables',
          component: () => import('@/views/dashboard/tables/TablesQrPage.vue'),
        },
        {
          path: 'tables-qr',
          redirect: '/dashboard/tables',
        },
        {
          path: 'table-sessions',
          name: 'dashboard-table-sessions',
          component: () => import('@/views/dashboard/tables/TableSessionsPage.vue'),
        },
        {
          path: 'reports/sales',
          name: 'dashboard-sales-report',
          component: () => import('@/views/dashboard/reports/SalesReportPage.vue'),
        },
        {
          path: 'reports/top-items',
          name: 'dashboard-top-items',
          component: () => import('@/views/dashboard/reports/TopItemsPage.vue'),
        },
        {
          path: 'reports/peak-hours',
          name: 'dashboard-peak-hours',
          component: () => import('@/views/dashboard/reports/PeakHoursPage.vue'),
        },
        {
          path: 'staff',
          name: 'dashboard-staff',
          component: () => import('@/views/dashboard/staff/StaffPage.vue'),
        },
        {
          path: 'refunds',
          name: 'dashboard-refunds',
          component: () => import('@/views/dashboard/refunds/RefundsPage.vue'),
        },
        {
          path: 'notifications',
          name: 'dashboard-notifications',
          component: () => import('@/views/dashboard/notifications/NotificationsPage.vue'),
        },
        {
          path: 'outlets',
          name: 'dashboard-outlets',
          component: () => import('@/views/dashboard/outlets/OutletsPage.vue'),
          meta: { requiresAuth: true, roles: ['owner', 'superadmin'] },
        },
      ],
    },

    // 404 Catch-All
    {
      path: '/:pathMatch(.*)*',
      redirect: '/auth/login',
    },
  ],
})

// ================= NAVIGATION GUARDS =================
router.beforeEach((to, _from) => {
  // 0. Cek masa berlaku sesi login harian (Kedaluwarsa otomatis jam 23.59)
  if (isSessionExpired()) {
    const hadToken = !!localStorage.getItem('lapaqu_token')
    clearSessionStorage()
    if (hadToken && !to.path.startsWith('/auth') && !to.path.startsWith('/order')) {
      return {
        path: '/auth/login',
      }
    }
  }

  const token = sessionStorage.getItem('lapaqu_token') || localStorage.getItem('lapaqu_token')
  const userStr = sessionStorage.getItem('lapaqu_user') || localStorage.getItem('lapaqu_user')
  const isAuthenticated = !isSessionExpired() && !!token && !!userStr

  // 1. Proteksi Halaman Outlet Staff: Hanya perangkat yang sudah di-pair yang boleh mengakses!
  if (to.name === 'outlet-staff' || to.path === '/auth/outlet-staff' || to.path === '/outlet/staff') {
    const paired = getPairedOutlet()
    const deviceToken = localStorage.getItem('lapaqu_device_token')
    if (!paired || !deviceToken) {
      // Perangkat belum pairing -> tolak dan alihkan ke halaman pairing
      return {
        path: '/auth/outlet-login',
        query: { alert: 'unpaired' },
      }
    }
  }

  // 2. Auto-redirect perangkat terhubung (Paired Device) ke Halaman Pilih Staff
  const paired = getPairedOutlet()
  if (paired && !isAuthenticated) {
    // Jika perangkat sudah di-pair dan mencoba buka login umum atau pairing (bukan mode owner), langsung ke outlet-staff
    if (
      (to.name === 'login' || to.path === '/auth/login' || to.path === '/login' || to.name === 'outlet-login' || to.path === '/auth/outlet-login') &&
      to.query.mode !== 'owner' &&
      to.query.relink !== '1'
    ) {
      return '/auth/outlet-staff'
    }
  }
  let userRole: string | undefined

  if (userStr) {
    try {
      const user = JSON.parse(userStr)
      userRole = user?.role
    } catch (e) {}
  }

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  const guestOnly = to.matched.some(record => record.meta.guestOnly)

  // 1. Belum login mencoba akses halaman terproteksi
  if (requiresAuth && !isAuthenticated) {

    return {
      path: '/auth/login',
      query: { redirect: to.fullPath },
    }
  }

  // 2. Sudah login membuka rute tamu (login, forgot-pass, dll)
  if (guestOnly && isAuthenticated) {
    if (to.name === 'outlet-login' || to.name === 'outlet-staff') {
      return
    }
    if (userRole === 'kasir') return '/pos/orders'
    if (userRole === 'kitchen_staff') return '/kds/queue'
    return '/dashboard'
  }

  // 3. Otorisasi peran (Role-Based Access Control)
  if (requiresAuth && isAuthenticated) {
    const matchedRoleRecord = to.matched.find(record => Array.isArray(record.meta.roles))
    if (matchedRoleRecord) {
      const allowedRoles = matchedRoleRecord.meta.roles as string[]
      if (userRole && !allowedRoles.includes(userRole)) {
        // Alihkan user ke area kerjanya jika tidak berhak
        if (userRole === 'kasir') return '/pos/orders'
        if (userRole === 'kitchen_staff') return '/kds/queue'
        return '/dashboard'
      }
    }
  }

  return true
})

export default router
