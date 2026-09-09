/**
 * Subscription store — moved from src/store/modules/subscription.js
 * Week 3 refactor: consolidating all stores under src/stores/
 *
 * Import path: '@/stores/subscriptions/subscriptionStore'
 * The old path '@/store/modules/subscription' still works via the
 * re-export shim at src/store/modules/subscription.js
 */
import { defineStore } from 'pinia'
import apiClient from '@/config/axios'

export const useSubscriptionStore = defineStore('subscription', {
  state: () => ({
    current:      null,
    loading:      false,
    error:        null,
    lastFetched:  null,
    loaded:       false,
    promise:      null,
    cacheTimeout: 5 * 60 * 1000, // 5 minutes
  }),

  getters: {
    isActive:   (state) => !!state.current && state.current.status === 'active'  && state.current.days_left > 0,
    isTrial:    (state) => !!state.current && state.current.status === 'trial'   && state.current.days_left > 0,
    isExpired:  (state) => !!state.current && (state.current.status === 'expired' || state.current.days_left <= 0),
    isInactive: (state) => !state.current  || state.current.status === 'inactive',

    canAccess: (state) => {
      if (!state.current) return false
      return ['active', 'trial'].includes(state.current.status) && state.current.days_left > 0
    },

    statusText: (state) => {
      if (!state.current) return 'No Subscription'
      const { status, days_left } = state.current
      const map = {
        active:   days_left > 0 ? `Active (${days_left} days left)` : 'Expired',
        trial:    days_left > 0 ? `Trial (${days_left} days left)`  : 'Trial Expired',
        expired:  'Expired',
        stopped:  'Stopped',
        inactive: 'Inactive',
      }
      return map[status] ?? 'Unknown'
    },

    isCacheValid: (state) =>
      !!state.lastFetched && (Date.now() - state.lastFetched) < state.cacheTimeout,
  },

  actions: {
    async fetchSubscription(forceRefresh = false) {
      if (this.loading && !forceRefresh) return this.promise || this.current

      if (!forceRefresh && this.loaded && this.isCacheValid && this.current) {
        return this.current
      }

      this.loading = true
      this.error   = null

      this.promise = (async () => {
        try {
          if (import.meta.env.DEV) {
            const { useAuthStore } = await import('@/stores/auth');
            const authStore = useAuthStore();
            console.log('[SubscriptionStore] Fetching /subscription/me');
            console.log('  authStore.token:', authStore.token ? '✓ Present' : '✗ Missing');
            console.log('  authStore.isAuthenticated:', authStore.isAuthenticated);
          }
          // Skip token refresh during subscription fetch to avoid 401 on new auto-logged-in users
          const response = await apiClient.get('/subscription/me', {
            meta: { skipTokenRefresh: true }
          })
          if (response.data.status === 'success') {
            this.current     = response.data.data.current
            this.lastFetched = Date.now()
            this.loaded      = true
            return this.current
          }
          throw new Error(response.data.message || 'Failed to fetch subscription')
        } catch (error) {
          this.error = error.message || 'Failed to fetch subscription'
          if (!this.current) {
            this.current = { status: 'inactive', plan_code: null, plan_name: null, price: 0, currency: 'USD', billing_cycle_days: 30, end_date: null, days_left: 0 }
          }
          this.loaded = true
          throw error
        } finally {
          this.loading = false
          this.promise = null
        }
      })()

      return this.promise
    },

    clearCache() { this.lastFetched = null },

    reset() {
      this.current     = null
      this.loading     = false
      this.error       = null
      this.lastFetched = null
      this.loaded      = false
      this.promise     = null
    },

    async refresh() { return this.fetchSubscription(true) },

    /**
     * Generate smart subscription alerts
     * Returns an array of notification objects based on days remaining
     * Triggered every day to create escalating alerts
     */
    generateSubscriptionAlerts() {
      if (!this.current || this.current.status === 'inactive' || this.current.days_left <= 0) {
        return []
      }

      const alerts = []
      const daysLeft = this.current.days_left
      const isTrialPlan = this.current.status === 'trial'
      const planName = this.current.plan_name || (isTrialPlan ? 'باقة تجريبية' : 'اشتراك سنوي')

      // Alert 1: Last 7 days - Yellow/Warning
      if (daysLeft <= 7 && daysLeft > 3) {
        alerts.push({
          type: 'subscription_warning',
          title: `تنبيه: متبقي ${daysLeft} أيام`,
          message: `متبقي ${daysLeft} أيام على انتهاء صلاحية ${planName}. يرجى الترقية لضمان استمرار الخدمة.`,
          priority: 'warning',
          color: 'yellow',
          icon: '⏳',
          action: { label: 'ترقية الآن', path: '/upgrade' },
          dismissible: false,
        })
      }

      // Alert 2: Last 3 days - Orange/Important
      if (daysLeft <= 3 && daysLeft > 1) {
        alerts.push({
          type: 'subscription_important',
          title: `تنبيه مهم: متبقي ${daysLeft} أيام فقط`,
          message: `متبقي ${daysLeft} أيام فقط! تأكد من الترقية لضمان عدم توقف الخدمة.`,
          priority: 'important',
          color: 'orange',
          icon: '⚠️',
          action: { label: 'ترقية الآن', path: '/upgrade' },
          dismissible: false,
        })
      }

      // Alert 3: Last day - Red/Critical
      if (daysLeft === 1) {
        alerts.push({
          type: 'subscription_critical',
          title: '🔴 آخر يوم! الترقية مطلوبة فوراً',
          message: 'هذا هو آخر يوم في صلاحية الاشتراك! يرجى الترقية فوراً لتجنب قطع الخدمة.',
          priority: 'critical',
          color: 'red',
          icon: '🚨',
          action: { label: 'ترقية الآن', path: '/upgrade' },
          dismissible: false,
        })
      }

      return alerts
    },
  },
})
