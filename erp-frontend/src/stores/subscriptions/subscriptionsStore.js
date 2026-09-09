import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useSubscriptionsStore = defineStore('subscriptions', () => {
  // ─── State ────────────────────────────────────────────────────────
  const plans = ref([]);
  const currentPlan = ref(null);
  const plansCache = ref(null);
  const plansCacheTTL = 30 * 60 * 1000; // 30 minutes
  const inFlightRequests = ref({});
  const loading = ref(false);
  const error = ref(null);

  // ─── Computed ─────────────────────────────────────────────────────
  const totalPlans = computed(() => plans.value.length);
  const activePlans = computed(() => plans.value.filter(p => p.is_active));
  const getPlanById = computed(() => (planId) => plans.value.find(p => p.id === planId));

  // ─── Fetch Available Plans ────────────────────────────────────────
  const fetchAvailablePlans = async () => {
    const cacheKey = 'available_plans';
    
    if (inFlightRequests.value[cacheKey]) {
      return inFlightRequests.value[cacheKey];
    }

    if (plansCache.value && nowMs() - plansCache.value.timestamp < plansCacheTTL) {
      return {
        status: 'success',
        data: plansCache.value.data,
        message: null
      };
    }

    const promise = (async () => {
      loading.value = true;
      error.value = null;
      try {
        const { data } = await apiClient.get('/plans');
        
        const result = {
          status: 'success',
          data: Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []),
          message: null
        };

        plans.value = result.data;

        plansCache.value = {
          data: result.data,
          timestamp: nowMs()
        };

        return result;
      } catch (err) {
        error.value = err?.response?.data?.message || 'Failed to fetch plans';
        return {
          status: 'error',
          data: null,
          message: error.value
        };
      } finally {
        loading.value = false;
        delete inFlightRequests.value[cacheKey];
      }
    })();

    inFlightRequests.value[cacheKey] = promise;
    return promise;
  };

  // ─── Fetch Plans (Alias) ──────────────────────────────────────────
  const fetchPlans = fetchAvailablePlans;

  // ─── Get Current Subscription ─────────────────────────────────────
  const getCurrentSubscription = async () => {
    const cacheKey = 'current_subscription';
    
    if (inFlightRequests.value[cacheKey]) {
      return inFlightRequests.value[cacheKey];
    }

    const promise = (async () => {
      try {
        const { data } = await apiClient.get('/subscription/me');
        
        const result = {
          status: 'success',
          data: data?.data || null,
          message: null
        };

        currentPlan.value = result.data;
        return result;
      } catch (err) {
        return {
          status: 'error',
          data: null,
          message: err?.response?.data?.message || 'Failed to fetch subscription'
        };
      } finally {
        delete inFlightRequests.value[cacheKey];
      }
    })();

    inFlightRequests.value[cacheKey] = promise;
    return promise;
  };

  // ─── Subscribe to Plan ────────────────────────────────────────────
  const subscribeToPlan = async (planId, paymentMethodId = null) => {
    try {
      const { data } = await apiClient.post('/subscription/upgrade', {
        plan_id: planId,
        payment_method_id: paymentMethodId
      });

      currentPlan.value = data?.data || null;

      return {
        status: 'success',
        data: data?.data || { plan_id: planId },
        message: data?.message || 'Subscription successful'
      };
    } catch (error) {
      return {
        status: 'error',
        data: null,
        message: error?.response?.data?.message || 'Failed to subscribe'
      };
    }
  };

  // ─── Cancel Subscription ──────────────────────────────────────────
  const cancelSubscription = async (reason = '') => {
    try {
      const { data } = await apiClient.post('/subscription/cancel', { reason });

      currentPlan.value = null;

      return {
        status: 'success',
        data: data?.data || null,
        message: data?.message || 'Subscription cancelled'
      };
    } catch (error) {
      return {
        status: 'error',
        data: null,
        message: error?.response?.data?.message || 'Failed to cancel subscription'
      };
    }
  };

  // ─── Clear Cache ──────────────────────────────────────────────────
  const clearCache = () => {
    plansCache.value = null;
    inFlightRequests.value = {};
  };

  return {
    plans,
    currentPlan,
    loading,
    error,
    totalPlans,
    activePlans,
    getPlanById,
    fetchAvailablePlans,
    fetchPlans,
    getCurrentSubscription,
    subscribeToPlan,
    cancelSubscription,
    clearCache
  };
});
