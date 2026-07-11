import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useTransactionStore = defineStore('transaction', () => {
  // Cache TTL
  const TTL = {
    transactions: 2 * 60 * 1000,     // 2 minutes (changes frequently)
    transactionDetails: 5 * 60 * 1000, // 5 minutes (individual transaction details)
  };

  // State
  const transactionsList = ref({});
  const transactionsListFetchedAt = ref({});
  const transactionsListInFlight = ref(null);

  // Details cache
  const detailsCache = ref({});
  const detailsCacheFetchedAt = ref({});

  // Search cache
  const searchCache = ref({});
  const searchCacheFetchedAt = ref({});

  // Helpers
  const isFresh = (fetchedAt, ttl) => !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // Fetch transactions list with filters
  const fetchTransactions = async ({ 
    contactId,
    contactType, // 'customers' or 'suppliers'
    page = 1, 
    perPage = 50,
    branchId = null,
    status = null,
    dateFrom = null,
    dateTo = null,
    force = false,
    signal = null
  } = {}) => {
    if (!contactId || !contactType) throw new Error('Contact ID and type are required');
    
    const cacheKey = `${contactType}_${contactId}_${page}_${perPage}_${branchId || 'all'}_${status || 'all'}_${dateFrom || 'all'}_${dateTo || 'all'}`;
    const cached = transactionsList.value[cacheKey];
    const cachedAt = transactionsListFetchedAt.value[cacheKey];

    if (!force && cached && isFresh(cachedAt, TTL.transactions)) {
      return cached;
    }

    if (!force && transactionsListInFlight.value?.[cacheKey]) return await transactionsListInFlight.value[cacheKey];

    const promise = (async () => {
      try {
        // Try primary endpoint first
        const primaryEndpoint = contactType === 'customers' ? `/customers/${contactId}/transactions` : `/suppliers/${contactId}/transactions`;
        const params = {
          page: String(page),
          per_page: String(perPage)
        };
        
        if (branchId) params.branch_id = String(branchId);
        if (status) params.status = status;
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;

        const response = await apiClient.get(primaryEndpoint, { params, signal });

        const raw = response?.data?.data ?? response?.data ?? {};
        const list = Array.isArray(raw) ? raw : (raw.items || []);
        
        // Separate parsing of data and metadata
        const total = parseInt(raw.total ?? raw.count ?? list.length) || 0;
        
        // Return structured response with pagination metadata
        const structuredResponse = {
          items: list,
          total: total || list.length,
          page: parseInt(page) || 1,
          perPage: parseInt(perPage) || 50,
        };
        
        transactionsList.value[cacheKey] = structuredResponse;
        transactionsListFetchedAt.value[cacheKey] = nowMs();
        return structuredResponse;
      } catch (error) {
        console.error('Fetch transactions error:', error);
        return { items: [], total: 0, page: 1, perPage: parseInt(perPage) || 50 };
      } finally {
        if (transactionsListInFlight.value?.[cacheKey]) {
          delete transactionsListInFlight.value[cacheKey];
        }
      }
    })();

    if (!transactionsListInFlight.value) transactionsListInFlight.value = {};
    transactionsListInFlight.value[cacheKey] = promise;
    return await promise;
  };

  // Fetch transaction details
  const fetchTransactionDetails = async (transactionId, { force = false, signal = null } = {}) => {
    if (!transactionId) throw new Error('Transaction ID is required');
    
    const cacheKey = `transaction_${transactionId}`;
    const cached = detailsCache.value[cacheKey];
    const cachedAt = detailsCacheFetchedAt.value[cacheKey];

    if (!force && cached && isFresh(cachedAt, TTL.transactionDetails)) {
      return cached;
    }

    const promise = (async () => {
      try {
        const response = await apiClient.get(`/transactions/${transactionId}`, { signal });
        const data = response?.data?.data || response?.data;
        
        detailsCache.value[cacheKey] = data;
        detailsCacheFetchedAt.value[cacheKey] = nowMs();
        return data;
      } catch (error) {
        console.error('Fetch transaction details error:', error);
        return null;
      }
    })();

    return await promise;
  };

  // Search transactions
  const searchTransactions = async ({ 
    query,
    contactType,
    branchId = null,
    limit = 50,
    force = false,
    signal = null
  } = {}) => {
    if (!query || query.trim().length < 2) return [];
    
    const q = query.trim();
    const cacheKey = `${contactType || 'all'}_${q}_${branchId || 'all'}_${limit}`;
    const cached = searchCache.value[cacheKey];
    const cachedAt = searchCacheFetchedAt.value[cacheKey];

    if (!force && cached && isFresh(cachedAt, TTL.transactions)) {
      return cached;
    }

    const promise = (async () => {
      try {
        const params = { q, limit: String(limit) };
        if (branchId) params.branch_id = String(branchId);
        if (contactType) params.contact_type = contactType;
        
        const response = await apiClient.get('/transactions/search', { params, signal });
        const data = response?.data?.data || response?.data || [];
        const list = Array.isArray(data) ? data : (data?.items || []);
        
        searchCache.value[cacheKey] = list;
        searchCacheFetchedAt.value[cacheKey] = nowMs();
        return list;
      } catch (error) {
        console.error('Search transactions error:', error);
        return [];
      }
    })();

    return await promise;
  };

  // Get transaction details by ID (computed)
  const getTransactionById = (transactionId) => {
    return computed(() => detailsCache.value[`transaction_${transactionId}`]);
  };

  // Current transactions state for UI convenience
  const currentTransactions = ref({ items: [], total: 0 });

  // Clear all caches
  const clear = () => {
    transactionsList.value = {};
    transactionsListFetchedAt.value = {};
    transactionsListInFlight.value = null;
    detailsCache.value = {};
    detailsCacheFetchedAt.value = {};
    searchCache.value = {};
    searchCacheFetchedAt.value = {};
  };

  // Clear transactions list cache only
  const clearTransactionsListCache = () => {
    transactionsList.value = {};
    transactionsListFetchedAt.value = {};
    transactionsListInFlight.value = null;
  };

  // Clear details cache only
  const clearDetailsCache = () => {
    detailsCache.value = {};
    detailsCacheFetchedAt.value = {};
  };

  // Clear search cache only
  const clearSearchCache = () => {
    searchCache.value = {};
    searchCacheFetchedAt.value = {};
  };

  return {
    // State
    transactionsList,
    currentTransactions,
    
    // Actions
    fetchTransactions,
    fetchTransactionDetails,
    searchTransactions,
    getTransactionById,
    clear,
    clearTransactionsListCache,
    clearDetailsCache,
    clearSearchCache,
  };
});
