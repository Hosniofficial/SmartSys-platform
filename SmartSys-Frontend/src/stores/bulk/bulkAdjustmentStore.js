import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useBulkAdjustmentStore = defineStore('bulkAdjustment', () => {
  // ─── State ────────────────────────────────────────────────────────
  const products = ref([]);
  const productsCache = ref(null);
  const productsCacheTTL = 5 * 60 * 1000; // 5 minutes
  const inFlightRequests = ref({});

  // ─── Search Products ──────────────────────────────────────────────
  const searchProducts = async (query = '') => {
    const cacheKey = 'search_' + (query || 'all');
    
    if (inFlightRequests.value[cacheKey]) {
      return inFlightRequests.value[cacheKey];
    }

    if (productsCache.value && nowMs() - productsCache.value.timestamp < productsCacheTTL) {
      return { status: 'success', data: productsCache.value.data, message: null };
    }

    const promise = (async () => {
      try {
        const { data } = await apiClient.get('/inventory/products/search', {
          params: { query }
        });
        
        const result = {
          status: 'success',
          data: Array.isArray(data) ? data : (data?.data || []),
          message: null
        };

        productsCache.value = {
          data: result.data,
          timestamp: nowMs()
        };

        return result;
      } catch (error) {
        return {
          status: 'error',
          data: null,
          message: error?.response?.data?.message || 'Failed to search products'
        };
      } finally {
        delete inFlightRequests.value[cacheKey];
      }
    })();

    inFlightRequests.value[cacheKey] = promise;
    return promise;
  };

  // ─── Bulk Adjust Product ──────────────────────────────────────────
  const adjustProduct = async (productId, adjustments) => {
    try {
      const { data } = await apiClient.post('/inventory/bulk-adjust', {
        product_id: productId,
        adjustments: adjustments
      });

      return {
        status: 'success',
        data: data?.data || data,
        message: data?.message || null
      };
    } catch (error) {
      return {
        status: 'error',
        data: null,
        message: error?.response?.data?.message || 'Failed to adjust product'
      };
    }
  };

  // ─── Bulk Adjust from CSV ─────────────────────────────────────────
  const adjustFromCsv = async (csvFile, defaultProductId) => {
    try {
      const formData = new FormData();
      formData.append('file', csvFile);
      if (defaultProductId) {
        formData.append('default_product_id', defaultProductId);
      }

      const { data } = await apiClient.post('/inventory/bulk-adjust/csv', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });

      return {
        status: 'success',
        data: data?.data || data,
        message: data?.message || null,
        summary: data?.summary || {}
      };
    } catch (error) {
      return {
        status: 'error',
        data: null,
        message: error?.response?.data?.message || 'Failed to import CSV',
        summary: {}
      };
    }
  };

  // ─── Clear Cache ──────────────────────────────────────────────────
  const clearCache = () => {
    productsCache.value = null;
    inFlightRequests.value = {};
  };

  return {
    products,
    searchProducts,
    adjustProduct,
    adjustFromCsv,
    clearCache
  };
});
