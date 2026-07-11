import { createRouter, createWebHistory } from 'vue-router'
import Login from '../components/Login.vue'

// ⚠️ DO NOT call useAuthStore() or useSubscriptionStore() at module level here.
// Stores must only be accessed inside navigation guards (after app.use(pinia)).
// Importing the factory functions is fine — calling them is not.
import { useAuthStore } from '../stores/auth'
import { useSubscriptionStore } from '@/stores/subscriptions/subscriptionStore'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: Login,
    meta: { requiresGuest: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('../views/auth/Register.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('../views/auth/ForgotPassword.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/verify-email',
    name: 'VerifyEmail',
    component: () => import('../views/auth/VerifyEmail.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('../views/auth/ResetPassword.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/setup',
    name: 'Setup',
    component: () => import('../views/Setup.vue'),
    meta: { 
      requiresAuth: true, 
      requiresOwner: true,
      skipSubscriptionCheck: true
    }
  },
  {
    path: '/payments',
    name: 'PaymentsList',
    component: () => import('../views/payments/PaymentsList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin-dashboard',
    name: 'AdminDashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/cashier-dashboard',
    name: 'CashierDashboard',
    component: () => import('../views/CashierDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/sessions',
    name: 'SessionsList',
    component: () => import('../views/sessions/SessionsList.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/sales/point',
    name: 'SalesPoint',
    component: () => import('../views/sales/SalesPoint.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/sales/history',
    name: 'SalesHistory',
    component: () => import('../views/sales/SalesHistory.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/sales/:id',
    meta: { requiresAuth: true },
    beforeEnter: (to, from, next) => {
      next({ name: 'SalesHistory', query: { id: to.params.id } });
    }
  },
  {
    path: '/sales/approvals',
    name: 'SalesApprovals',
    component: () => import('../views/sales/SalesApprovals.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/sales/returns',
    name: 'ReturnsManagement',
    component: () => import('../views/sales/ReturnsManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/returns/history',
    name: 'ReturnsHistory',
    component: () => import('../views/returns/ReturnsHistory.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/products',
    name: 'Products',
    component: () => import('../views/products/ProductManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/branches',
    name: 'Branches',
    component: () => import('../views/branches/BranchManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/branches/:id',
    name: 'BranchDetails',
    component: () => import('../views/branches/BranchDetails.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/branches/bulk-distribution',
    name: 'BulkDistribution',
    component: () => import('../views/branches/BulkDistribution.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/purchases',
    name: 'Purchases',
    component: () => import('../views/purchases/PurchaseManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/purchases/history',
    name: 'PurchaseHistory',
    component: () => import('../views/purchases/PurchaseHistory.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/purchases/:id',
    meta: { requiresAuth: true },
    beforeEnter: (to, from, next) => {
      next({ name: 'PurchaseHistory', query: { id: to.params.id } });
    }
  },
  {
    path: '/inventory',
    name: 'InventoryManagement',
    component: () => import('../views/inventory/InventoryManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/customers',
    name: 'CustomersManagement',
    component: () => import('../views/contacts/CustomersManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/suppliers',
    name: 'SuppliersManagement',
    component: () => import('../views/contacts/SuppliersManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/contacts/:type/:id/details',
    name: 'ContactDetails',
    component: () => import('../views/contacts/ContactDetails.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/users',
    name: 'UserManagement',
    component: () => import('../views/admin/UserManagement.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/settings',
    redirect: '/settings/details?tab=general-settings'
  },
  {
    path: '/settings/details',
    name: 'SettingsView',
    component: () => import('../views/settings/SettingsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/branches-accounting',
    name: 'BranchesAccounting',
    component: () => import('../views/settings/BranchesAccounting.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/upgrade',
    name: 'Upgrade',
    component: () => import('../views/Upgrade.vue'),
    meta: { requiresAuth: true, allowExpired: true }
  },
  {
    path: '/admin/payment-methods',
    name: 'PaymentMethodsKindAdmin',
    component: () => import('../views/admin/PaymentMethodsKind.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/admin/subscriptions',
    name: 'AdminSubscriptions',
    component: () => import('../views/admin/Subscriptions.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  },
  {
    path: '/admin/plans',
    name: 'AdminPlans',
    component: () => import('../views/admin/Plans.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('../views/admin/Profile.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/forbidden',
    name: 'Forbidden',
    component: () => import('../views/Forbidden.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/reports/profit-loss',
    name: 'ProfitLossReport',
    component: () => import('../views/reports/ProfitLossReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/inventory-movements',
    name: 'InventoryMovementsReport',
    component: () => import('../views/reports/InventoryMovementsReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/audit-logs',
    name: 'AuditLogsReport',
    component: () => import('../views/reports/AuditLogsReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/sales-summary',
    name: 'SalesSummaryReport',
    component: () => import('../views/reports/SalesSummaryReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/inventory-value',
    name: 'InventoryValueReport',
    component: () => import('../views/reports/InventoryValueReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/inventory-value/by-branch',
    name: 'InventoryValueBybranch',
    component: () => import('../views/reports/InventoryValueBybranch.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/sales-analytics',
    name: 'SalesAnalyticsReport',
    component: () => import('../views/reports/SalesAnalyticsReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/settings/terminals',
    name: 'TerminalsManagement',
    component: () => import('../views/settings/TerminalsManagement.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/warranty',
    name: 'WarrantyManagement',
    component: () => import('../views/warranty/WarrantyManagement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/warranty/new',
    redirect: { path: '/warranty' }
  },
  {
    path: '/warranty/:id',
    meta: { requiresAuth: true },
    beforeEnter: (to, from, next) => {
      next({ path: '/warranty', query: { id: to.params.id } });
    }
  },
  {
    path: '/reports/cash-vouchers',
    name: 'CashVouchersReport',
    component: () => import('@/views/reports/CashVouchersReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/setup/opening-balance',
    name: 'OpeningBalance',
    component: () => import('../views/setup/OpeningBalance.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/vouchers',
    name: 'CashVouchers',
    component: () => import('@/views/vouchers/CashVouchers.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reports/sessions-summary',
    name: 'SessionsSummaryReport',
    component: () => import('../views/reports/SessionsSummaryReport.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports/accounting',
    name: 'AccountingReports',
    component: () => import('../views/reports/AccountingReports.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/accounting/periods',
    name: 'AccountingPeriods',
    component: () => import('../views/accounting/AccountingPeriods.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/admin/data-integrity',
    name: 'DataIntegrity',
    component: () => import('../views/admin/DataIntegrity.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/contacts/:type/:id/statement',
    name: 'AccountStatement',
    component: () => import('../views/contacts/AccountStatement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('../views/NotFound.vue'),
    meta: { requiresAuth: false }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 };
  }
});

// Navigation guard
// Note: Subscription freshness is tracked inside the subscription store itself
// (via `isCacheValid` + `lastFetched`). The store's `reset()` is called on
// logout/login (in authStore), which correctly invalidates the cache for the
// next user. We delegate freshness decisions to the store — no router-level
// cache that would leak across users/sessions.
router.beforeEach(async (to, from, next) => {
  if (import.meta.env.DEV) {
    console.log(`[Router Guard] Navigation: ${from.path} → ${to.path}`);
  }
  
  const authStore = useAuthStore();
  const subscriptionStore = useSubscriptionStore();
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin);
  const requiresSuperAdmin = to.matched.some(record => record.meta.requiresSuperAdmin);
  const requiresGuest = to.matched.some(record => record.meta.requiresGuest);
  const requiresOwner = to.matched.some(record => record.meta.requiresOwner);
  const allowExpired = to.matched.some(record => record.meta.allowExpired);

  // Skip auth check for routes that don't require authentication
  if (!requiresAuth) {
    // For guest-only routes (login page), try silent refresh so already-logged-in
    // users get redirected to dashboard instead of seeing the login form.
    if (requiresGuest) {
      const authStore2 = useAuthStore();
      if (authStore2.user) {
        // user data exists in localStorage — try to restore the session silently
        const restored = await authStore2.checkAuthStatus();
        if (restored && authStore2.isAuthenticated) {
          return next(authStore2.isAdmin ? '/admin-dashboard' : '/cashier-dashboard');
        }
      }
    }
    return next();
  }

  // Check authentication status if required
  if (requiresAuth) {
    const isValid = await authStore.checkAuthStatus();
    if (!isValid) {
      if (import.meta.env.DEV) console.log('Redirecting to login: Authentication required');
      authStore.returnUrl = to.fullPath;
      next('/');
      return;
    }
  }

  // Check subscription status for authenticated users (unless allowExpired)
  if (requiresAuth && authStore.isAuthenticated && !allowExpired) {
    // Check if subscription expired flag was set by axios interceptor
    if (authStore.isSubscriptionExpired && to.path !== '/upgrade') {
      if (import.meta.env.DEV) {
        console.log(`[Router Guard] Redirecting to upgrade: subscriptionExpired flag set`, authStore.subscriptionMessage);
      }
      next({
        path: '/upgrade',
        query: { reason: authStore.subscriptionMessage }
      });
      return;
    }

    const skipSubscriptionCheck = to.matched.some(r => r.meta.skipSubscriptionCheck);
    
    if (!skipSubscriptionCheck) {
      try {
        if (to.path === from.path) return next();

        if (subscriptionStore.loading) return next();

        // The store decides whether a server round-trip is needed based on its
        // internal TTL + lastFetched. After login/logout, reset() clears both,
        // so the first post-login navigation always fetches fresh data.
        if (import.meta.env.DEV) {
          const usingCache = subscriptionStore.isCacheValid && subscriptionStore.loaded;
          console.log('Checking subscription status...', usingCache ? '(cached)' : '(refreshing)');
        }
        await subscriptionStore.fetchSubscription(false);

        // Redirect to upgrade if subscription is expired/inactive
        if (!subscriptionStore.canAccess) {
          if (import.meta.env.DEV) console.log('Redirecting to upgrade: Subscription expired or inactive');
          next('/upgrade');
          return;
        }
      } catch (error) {
        if (import.meta.env.DEV) console.error('Error checking subscription:', error);
        // On error, don't block - let the API middleware handle it with 402 response
        // This prevents accidental lockout
      }
    } else {
      if (import.meta.env.DEV) console.log(`✅ Skipping subscription check on ${to.path} (skipSubscriptionCheck meta)`);
    }
  }

  if (requiresGuest && authStore.isAuthenticated) {
    next(authStore.isAdmin ? '/admin-dashboard' : '/cashier-dashboard');
  } else if (requiresSuperAdmin && !authStore.isSuperAdmin) {
    next({ path: '/forbidden', query: { reason: 'super_admin' } });
  } else if (requiresAdmin && !authStore.isAdmin) {
    next({ path: '/forbidden', query: { reason: 'admin' } });
  } else if (requiresOwner && !authStore.isOwner) {
    next({ path: '/forbidden', query: { reason: 'owner' } });
  } else if (requiresAuth && authStore.isAuthenticated && to.path !== '/setup' && !to.query.force) {
    if (authStore.isOwner && !authStore.user?.is_setup_complete) {
      next('/setup');
      return;
    }
    next();
  } else {
    next();
  }
});

export default router;
