import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useShiftStore = defineStore('shift', () => {
  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    shifts: 2 * 60 * 1000,        // دقيقتان
    currentShift: 1 * 60 * 1000,  // دقيقة واحدة
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const shifts = ref([]);
  const currentShift = ref(null);
  const shiftsFetchedAt = ref(null);
  const currentShiftFetchedAt = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── Getters ──────────────────────────────────────────────────────────────
  const isShiftOpen = computed(() => !!currentShift.value);
  const totalShifts = computed(() => shifts.value.length);

  // ─── Actions ──────────────────────────────────────────────────────────────

  /**
   * جلب الوردية الحالية
   */
  const getCurrentShift = async (branchId, terminalId, force = false) => {
    try {
      if (!force && currentShift.value && isFresh(currentShiftFetchedAt.value, TTL.currentShift)) {
        return {
          status: 'success',
          data: currentShift.value,
        };
      }

      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get('/shifts/current', {
        params: {
          branch_id: branchId,
          terminal_id: terminalId,
        },
      });

      currentShift.value = response?.data?.data || null;
      currentShiftFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: currentShift.value,
      };
    } catch (err) {
      // 404 يعني لا توجد وردية مفتوحة
      if (err.response?.status === 404) {
        currentShift.value = null;
        return {
          status: 'success',
          data: null,
        };
      }

      error.value = err.response?.data?.message || err.message || 'فشل جلب الوردية الحالية';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * فتح وردية جديدة
   */
  const openShift = async (payload) => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.post('/shifts/open', payload);
      currentShift.value = response?.data?.data || response?.data;
      currentShiftFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: currentShift.value,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل فتح الوردية';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * إغلاق الوردية الحالية
   */
  const closeShift = async (payload) => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.post('/shifts/close', payload);
      const result = response?.data?.data || response?.data;
      currentShift.value = null;
      currentShiftFetchedAt.value = null;

      return {
        status: 'success',
        data: result,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل إغلاق الوردية';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * جلب قائمة الورديات
   */
  const fetchShifts = async (params = {}, force = false) => {
    try {
      if (!force && shifts.value.length > 0 && isFresh(shiftsFetchedAt.value, TTL.shifts)) {
        return {
          status: 'success',
          data: shifts.value,
        };
      }

      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get('/shifts', { params });
      const data = response?.data?.data || [];
      shifts.value = Array.isArray(data) ? data : (data?.items || []);
      shiftsFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: shifts.value,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل تحميل الورديات';
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
    shifts.value = [];
    currentShift.value = null;
    shiftsFetchedAt.value = null;
    currentShiftFetchedAt.value = null;
  };

  return {
    // State
    shifts: computed(() => shifts.value),
    currentShift: computed(() => currentShift.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),

    // Getters
    isShiftOpen,
    totalShifts,

    // Actions
    getCurrentShift,
    openShift,
    closeShift,
    fetchShifts,
    clearCache,
  };
});
