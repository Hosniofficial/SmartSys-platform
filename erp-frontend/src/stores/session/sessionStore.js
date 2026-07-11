import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useSessionStore = defineStore('session', () => {
  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    sessions: 2 * 60 * 1000,       // دقيقتان
    currentSession: 1 * 60 * 1000, // دقيقة واحدة
    summary: 1 * 60 * 1000,        // دقيقة واحدة
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const sessions = ref([]);
  const currentSession = ref(null);
  const sessionSummary = ref(null);
  const sessionsFetchedAt = ref(null);
  const currentSessionFetchedAt = ref(null);
  const summaryFetchedAt = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── Getters ──────────────────────────────────────────────────────────────
  const isSessionOpen = computed(() => !!currentSession.value);
  const totalSessions = computed(() => sessions.value.length);

  // ─── Actions ──────────────────────────────────────────────────────────────

  /**
   * جلب الجلسة الحالية
   */
  const getCurrentSession = async (branchId, userId, deviceId, force = false) => {
    try {
      if (!force && currentSession.value && isFresh(currentSessionFetchedAt.value, TTL.currentSession)) {
        return {
          status: 'success',
          data: currentSession.value,
        };
      }

      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get('/sessions/current', {
        params: {
          branch_id: branchId,
          user_id: userId,
          device_id: deviceId,
        },
      });

      const _d = response?.data?.data;
      currentSession.value = (_d && typeof _d === 'object' && !Array.isArray(_d) && _d.id) ? _d : null;
      // 200 + null means no open session — treat as closed
      currentSessionFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: currentSession.value,
      };
    } catch (err) {
      // 404 يعني لا توجد جلسة مفتوحة
      if (err.response?.status === 404) {
        currentSession.value = null;
        return {
          status: 'success',
          data: null,
        };
      }

      error.value = err.response?.data?.message || err.message || 'فشل جلب الجلسة الحالية';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * فتح جلسة جديدة
   */
  const openSession = async (payload) => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.post('/sessions/open', payload);
      currentSession.value = response?.data?.data || response?.data;
      currentSessionFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: currentSession.value,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل فتح الجلسة';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * إغلاق الجلسة الحالية
   */
  const closeSession = async (sessionId, closingAmount, reason = '') => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.post(`/sessions/${sessionId}/close`, {
        closing_cash_amount: closingAmount,
        variance_reason: reason,
      });

      const result = response?.data?.data || response?.data;
      currentSession.value = null;
      currentSessionFetchedAt.value = null;

      return {
        status: 'success',
        data: result,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل إغلاق الجلسة';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * جلب ملخص الجلسة
   */
  const getSessionSummary = async (sessionId, force = false) => {
    try {
      // Note: cache only applies for single-session fetch (not bulk background loads)
      if (!force && sessionSummary.value?.session?.id === sessionId && isFresh(summaryFetchedAt.value, TTL.summary)) {
        return {
          status: 'success',
          data: sessionSummary.value,
        };
      }

      const response = await apiClient.get(`/sessions/${sessionId}/summary`);
      const data = response?.data?.data || response?.data;

      // Only cache if this is the single-session use case (not overwrite shared ref during bulk loads)
      if (!force) {
        sessionSummary.value = data;
        summaryFetchedAt.value = nowMs();
      }

      return {
        status: 'success',
        data,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل جلب ملخص الجلسة';
      return {
        status: 'error',
        message: error.value,
      };
    }
  };

  /**
   * جلب قائمة الجلسات
   */
  const fetchSessions = async (params = {}, force = false) => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get('/sessions', { params });
      const resData = response?.data?.data;
      const items   = Array.isArray(resData) ? resData : (resData?.items || []);
      const total   = resData?.total ?? items.length;

      sessions.value = items;
      sessionsFetchedAt.value = nowMs();

      return {
        status: 'success',
        data:   items,
        total,
        page:        resData?.page        ?? 1,
        total_pages: resData?.total_pages ?? 1,
        kpi:         resData?.kpi         ?? null,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل تحميل الجلسات';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * مسح الـ Cache
   */
  const clearCache = () => {
    sessions.value = [];
    currentSession.value = null;
    sessionSummary.value = null;
    sessionsFetchedAt.value = null;
    currentSessionFetchedAt.value = null;
    summaryFetchedAt.value = null;
  };

  return {
    // State
    sessions: computed(() => sessions.value),
    currentSession: computed(() => currentSession.value),
    sessionSummary: computed(() => sessionSummary.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),

    // Getters
    isSessionOpen,
    totalSessions,

    // Actions
    getCurrentSession,
    openSession,
    closeSession,
    getSessionSummary,
    fetchSessions,
    clearCache,
  };
});
