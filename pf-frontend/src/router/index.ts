import { createRouter, createWebHistory } from 'vue-router'

// Layouts
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import CustomerLayout from '@/layouts/CustomerLayout.vue'
import PosLayout from '@/layouts/PosLayout.vue'
import KdsLayout from '@/layouts/KdsLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Redirect root to dashboard
    {
      path: '/',
      redirect: '/dashboard',
    },

    // ================= AUTH ROUTES =================
    {
      path: '/auth',
      component: AuthLayout,
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/views/auth/LoginPage.vue'),
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
      ],
    },

    // ================= OWNER DASHBOARD ROUTES =================
    {
      path: '/dashboard',
      component: DashboardLayout,
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
        },
      ],
    },

    // 404 Catch-All
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard',
    },
  ],
})

export default router
