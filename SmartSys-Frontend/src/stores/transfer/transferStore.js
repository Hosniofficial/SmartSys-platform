import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';
import { useProductStore } from '@/stores/product/productStore';

export const useTransferStore = defineStore('transfer', () => {

  // ─── State ────────────────────────────────────────────────────────────────
  const transfers              = ref([]);
  const transfersFetchedAt     = ref({});
  const transferDetails        = ref({});
  const transferDetailsFetchedAt = ref({});
  const transfersInFlight      = ref({});

  // ─── Cache Configuration ───────────────────────────────────────────────────
  const CACHE_TTL = {
    transfers: 2 * 60 * 1000,      // 2 دقائق
    details: 5 * 60 * 1000,         // 5 دقائق
  };

  // ─── searchTransfers ──────────────────────────────────────────────────────
  /**
   * يبحث عن تحويلات المخزون حسب الفرع
   * يحتفظ بـ cache 2 دقيقة لتحسين الأداء
   */
  const searchTransfers = async (branchId, params = {}) => {
    if (!branchId) return { status: 'error', data: [], message: 'Branch ID required' };

    const bid = String(branchId);
    const cacheKey = `transfers_${bid}_${JSON.stringify(params)}`;
    const now = Date.now();

    // تحقق من الـ cache
    if (transfers.value[cacheKey] && transfersFetchedAt.value[cacheKey]) {
      const cacheAge = now - transfersFetchedAt.value[cacheKey];
      if (cacheAge < CACHE_TTL.transfers) {
        return {
          status: 'success',
          data: transfers.value[cacheKey],
          cached: true
        };
      }
    }

    // تجنب في الوقت نفسه in-flight requests
    if (transfersInFlight.value[cacheKey]) {
      return { status: 'pending', data: [] };
    }

    try {
      transfersInFlight.value[cacheKey] = true;

      const response = await apiClient.get(`/branches/${branchId}/transfers`, {
        params
      });

      const data = response?.data?.data || response?.data || [];
      transfers.value[cacheKey] = Array.isArray(data) ? data : (data?.items || []);
      transfersFetchedAt.value[cacheKey] = now;

      return {
        status: 'success',
        data: transfers.value[cacheKey],
        message: null
      };
    } catch (err) {
      console.error('searchTransfers failed:', err);
      return {
        status: 'error',
        data: [],
        message: err.response?.data?.message || err.message
      };
    } finally {
      transfersInFlight.value[cacheKey] = false;
    }
  };

  // ─── fetchTransferDetails ──────────────────────────────────────────────────
  /**
   * يجيب تفاصيل تحويل معين
   */
  const fetchTransferDetails = async (transferId) => {
    if (!transferId) return { status: 'error', data: null, message: 'Transfer ID required' };

    const tid = String(transferId);
    const cacheKey = `details_${tid}`;
    const now = Date.now();

    if (transferDetails.value[cacheKey] && transferDetailsFetchedAt.value[cacheKey]) {
      const cacheAge = now - transferDetailsFetchedAt.value[cacheKey];
      if (cacheAge < CACHE_TTL.details) {
        return {
          status: 'success',
          data: transferDetails.value[cacheKey],
          cached: true
        };
      }
    }

    try {
      const response = await apiClient.get(`/transfers/${transferId}`);

      const data = response?.data?.data || response?.data || {};
      transferDetails.value[cacheKey] = data;
      transferDetailsFetchedAt.value[cacheKey] = now;

      return {
        status: 'success',
        data,
        message: null
      };
    } catch (err) {
      console.error('fetchTransferDetails failed:', err);
      return {
        status: 'error',
        data: null,
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── createTransfer ───────────────────────────────────────────────────────
  /**
   * ينقل مخزون من فرع إلى فرع آخر
   * 
   * الـ payload يجب أن يحتوي على:
   *   {
   *     from_branch_id: number,
   *     to_branch_id: number,
   *     items: [{ product_id, quantity, unit_id }],
   *     notes: string (optional)
   *   }
   */
  const createTransfer = async (payload) => {
    try {
      const response = await apiClient.post('/branches/transfers', payload);

      // ✅ امسح cache لكلا الفرعين المتأثران
      const productStore = useProductStore();
      if (payload?.from_branch_id) {
        productStore.invalidateCacheForBranch(payload.from_branch_id);
      }
      if (payload?.to_branch_id) {
        productStore.invalidateCacheForBranch(payload.to_branch_id);
      }

      // امسح transfer cache للفرعين
      clearTransfersCache(payload?.from_branch_id);
      clearTransfersCache(payload?.to_branch_id);

      return {
        status: response?.data?.status === 'success' ? 'success' : 'error',
        data: response?.data?.data || response?.data || {},
        message: response?.data?.message
      };
    } catch (error) {
      console.error('createTransfer failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── approveTransfer ──────────────────────────────────────────────────────
  /**
   * توافق على تحويل مخزون معلق
   */
  const approveTransfer = async (transferId) => {
    try {
      const response = await apiClient.post(`/transfers/${transferId}/approve`);

      // جلب التفاصيل لمعرفة الفروع المتأثرة
      const transfer = response?.data?.data || response?.data || {};
      const productStore = useProductStore();

      if (transfer?.from_branch_id) {
        productStore.invalidateCacheForBranch(transfer.from_branch_id);
      }
      if (transfer?.to_branch_id) {
        productStore.invalidateCacheForBranch(transfer.to_branch_id);
      }

      // امسح transfer details
      delete transferDetails.value[`details_${transferId}`];
      delete transferDetailsFetchedAt.value[`details_${transferId}`];

      return {
        status: response?.data?.status === 'success' ? 'success' : 'error',
        data: transfer,
        message: response?.data?.message
      };
    } catch (error) {
      console.error('approveTransfer failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── rejectTransfer ───────────────────────────────────────────────────────
  /**
   * رفض تحويل مخزون معلق
   */
  const rejectTransfer = async (transferId, reason) => {
    try {
      const response = await apiClient.post(`/transfers/${transferId}/reject`, { reason });

      // لا نحتاج لمسح product cache لأن الكميات لم تتغير
      // لكن نمسح transfer details
      delete transferDetails.value[`details_${transferId}`];
      delete transferDetailsFetchedAt.value[`details_${transferId}`];

      return {
        status: response?.data?.status === 'success' ? 'success' : 'error',
        data: response?.data?.data || response?.data || {},
        message: response?.data?.message
      };
    } catch (error) {
      console.error('rejectTransfer failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── Cache Clearers ───────────────────────────────────────────────────────
  /**
   * يمسح cache التحويلات للفرع المحدد
   */
  const clearTransfersCache = (branchId) => {
    if (!branchId) return;

    const bid = String(branchId);
    Object.keys(transfers.value).forEach(key => {
      if (key.includes(`_${bid}_`) || key.includes(`_${bid}`)) {
        delete transfers.value[key];
        delete transfersFetchedAt.value[key];
      }
    });
  };

  /**
   * يمسح جميع caches
   */
  const clear = () => {
    transfers.value = {};
    transfersFetchedAt.value = {};
    transferDetails.value = {};
    transferDetailsFetchedAt.value = {};
    transfersInFlight.value = {};
  };

  // ─── Return ───────────────────────────────────────────────────────────────
  return {
    // State
    transfers,
    transferDetails,

    // Read Actions
    searchTransfers,
    fetchTransferDetails,

    // Write Actions
    createTransfer,
    approveTransfer,
    rejectTransfer,

    // Cache
    clear,
    clearTransfersCache,
  };
});
