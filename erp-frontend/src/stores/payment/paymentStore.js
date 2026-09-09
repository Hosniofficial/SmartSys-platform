import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import paymentService from '@/services/payment';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const usePaymentStore = defineStore('payment', () => {

  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    paymentMethods: 10 * 60 * 1000, // 10 دقائق
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const paymentMethods          = ref([]);
  const paymentMethodsFetchedAt = ref(0);
  const paymentMethodsInFlight  = ref(null);

  const searchCache          = ref({});
  const searchCacheFetchedAt = ref({});

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  const normalizeArray = (res) => {
    const root = res?.data || res || {};
    const data = root?.data ?? root;
    if (Array.isArray(data))        return data;
    if (Array.isArray(data?.items)) return data.items;
    return [];
  };

  // ─── fetchPaymentMethods ──────────────────────────────────────────────────
  /**
   * يجيب طرق الدفع المتاحة مع caching
   * الـ component يستخدمها هكذا:
   *   const res = await paymentStore.fetchPaymentMethods();
   *   if (res.status === 'success') { methods.value = res.data; }
   */
  const fetchPaymentMethods = async ({ force = false } = {}) => {
    try {
      if (
        !force &&
        isFresh(paymentMethodsFetchedAt.value, TTL.paymentMethods) &&
        Array.isArray(paymentMethods.value) &&
        paymentMethods.value.length
      ) {
        return {
          status: 'success',
          data:   paymentMethods.value,
          message: null
        };
      }

      if (!force && paymentMethodsInFlight.value) {
        const result = await paymentMethodsInFlight.value;
        return {
          status: 'success',
          data:   result,
          message: null
        };
      }

      const promise = (async () => {
        try {
          const res  = await paymentService.getPaymentMethods();
          const list = normalizeArray(res);

          paymentMethods.value          = Array.isArray(list) ? list : [];
          paymentMethodsFetchedAt.value = nowMs();
          return paymentMethods.value;
        } catch {
          paymentMethods.value          = [];
          paymentMethodsFetchedAt.value = 0;
          return [];
        } finally {
          paymentMethodsInFlight.value = null;
        }
      })();

      paymentMethodsInFlight.value = promise;
      const result = await promise;

      return {
        status: 'success',
        data:   result,
        message: null
      };
    } catch (err) {
      console.error('fetchPaymentMethods failed:', err);
      return {
        status: 'error',
        data:   [],
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── fetchPayments ────────────────────────────────────────────────────────
  /**
   * يجيب سجل الدفعات/المقبوضات لعميل أو مورد
   * الـ component يستخدمها هكذا:
   *   const res = await paymentStore.fetchPayments({ contactId, contactType: 'customers' });
   *   if (res.status === 'success') { payments.value = res.data.items; }
   */
  const fetchPayments = async ({
    contactId,
    contactType,
    page    = 1,
    perPage = 50,
    force   = false,
    signal  = null,
  } = {}) => {
    try {
      if (!contactId || !contactType) {
        return {
          status: 'error',
          data:   { items: [], total: 0, page: 1, perPage },
          message: 'Contact ID and type are required'
        };
      }

      const cacheKey = `payments_${contactType}_${contactId}_${page}_${perPage}`;
      const cached   = searchCache.value[cacheKey];
      const cachedAt = searchCacheFetchedAt.value[cacheKey];

      if (!force && cached && isFresh(cachedAt, TTL.paymentMethods)) {
        return {
          status: 'success',
          data:   cached,
          message: null
        };
      }

      const endpoint  = contactType === 'customers' ? '/receipts' : '/payments';
      const idKey     = contactType === 'customers' ? 'customer_id' : 'supplier_id';
      const params    = {
        page:     String(page),
        per_page: String(perPage),
        [idKey]:  String(contactId),
      };

      const response = await apiClient.get(endpoint, { params, signal });
      const raw      = response?.data?.data ?? response?.data ?? {};
      const list     = Array.isArray(raw) ? raw : (raw.items || []);

      // فلتر client-side لو الـ backend ما فلترش صح
      const filteredList = list.filter(item =>
        String(item[idKey]) === String(contactId)
      );

      const structuredResponse = {
        items:   filteredList,
        total:   parseInt(raw.total ?? raw.count ?? list.length) || 0,
        page:    parseInt(page)    || 1,
        perPage: parseInt(perPage) || 50,
        meta: {
          filtered:         filteredList.length !== list.length,
          backendFiltering: filteredList.length === list.length,
        },
      };

      searchCache.value[cacheKey]          = structuredResponse;
      searchCacheFetchedAt.value[cacheKey] = nowMs();

      return {
        status: 'success',
        data:   structuredResponse,
        message: null
      };
    } catch (err) {
      console.error('fetchPayments failed:', err);
      return {
        status: 'error',
        data:   { items: [], total: 0, page: 1, perPage },
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── createPaymentMethod ──────────────────────────────────────────────────
  /**
   * ينشئ طريقة دفع جديدة ويحدّث الـ cache
   * الـ component يستخدمها هكذا:
   *   const res = await paymentStore.createPaymentMethod(payload);
   *   if (res.status === 'success') { ... }
   */
  const createPaymentMethod = async (payload) => {
    try {
      await paymentService.createPaymentMethod(payload);
      await fetchPaymentMethods({ force: true });

      return {
        status: 'success',
        data:   null,
        message: null
      };
    } catch (err) {
      console.error('createPaymentMethod failed:', err);
      return {
        status: 'error',
        data:   null,
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── updatePaymentMethodKind ──────────────────────────────────────────────
  /**
   * يحدّث نوع طريقة الدفع والحساب المحاسبي ويحدّث الـ cache
   * الـ component يستخدمها هكذا:
   *   const res = await paymentStore.updatePaymentMethodKind(id, kind, accountId);
   *   if (res.status === 'success') { ... }
   */
  const updatePaymentMethodKind = async (id, kind, accountId = null) => {
    try {
      await paymentService.updatePaymentMethodKind(id, kind, accountId);
      await fetchPaymentMethods({ force: true });

      return {
        status: 'success',
        data:   null,
        message: null
      };
    } catch (err) {
      console.error('updatePaymentMethodKind failed:', err);
      return {
        status: 'error',
        data:   null,
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── Computed Getters ─────────────────────────────────────────────────────
  const methodsByKind = computed(() => ({
    cash:   paymentMethods.value.filter(m => m.kind === 'cash'),
    credit: paymentMethods.value.filter(m => m.kind === 'credit'),
  }));

  const getPaymentMethodsByKind = (kind) =>
    computed(() => methodsByKind.value[kind] || []);

  const getCashMethods   = () => computed(() => methodsByKind.value.cash);
  const getCreditMethods = () => computed(() => methodsByKind.value.credit);

  const getPaymentMethodById = (id) =>
    computed(() => paymentMethods.value.find(m => String(m.id) === String(id)));

  // ─── Cache Clearers ───────────────────────────────────────────────────────
  const clear = () => {
    paymentMethods.value          = [];
    paymentMethodsFetchedAt.value = 0;
    paymentMethodsInFlight.value  = null;
    searchCache.value          = {};
    searchCacheFetchedAt.value = {};
  };

  const clearSearchCache = () => {
    searchCache.value          = {};
    searchCacheFetchedAt.value = {};
  };

  // ─── Export to CSV ────────────────────────────────────────────────────────
  const exportToCsv = (filename, rows) => {
    const headers = Object.keys(rows[0] || {});
    const csv = [
      headers.join(','),
      ...rows.map(row => headers.map(h => {
        let v = row[h];
        if (v === null || v === undefined) return '';
        if (typeof v === 'string' && (v.includes(',') || v.includes('"') || v.includes('\n'))) {
          return `"${v.replace(/"/g, '""')}"`;
        }
        return v;
      }).join(','))
    ].join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // ─── Return ───────────────────────────────────────────────────────────────
  return {
    // State
    paymentMethods,

    // Computed
    methodsByKind,
    getPaymentMethodsByKind,
    getCashMethods,
    getCreditMethods,
    getPaymentMethodById,

    // Read Actions
    fetchPaymentMethods,
    fetchPayments,

    // Write Actions
    createPaymentMethod,
    updatePaymentMethodKind,

    // Export
    exportToCsv,

    // Cache
    clear,
    clearSearchCache,
  };
});