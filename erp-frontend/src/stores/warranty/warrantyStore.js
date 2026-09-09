import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useWarrantyStore = defineStore('warranty', () => {
  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    warranties: 5 * 60 * 1000,    // 5 دقائق
    details: 10 * 60 * 1000,      // 10 دقائق
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const warranties = ref([]);
  const details = ref(null);
  const warrantiesFetchedAt = ref(null);
  const detailsFetchedAt = ref({});
  const isLoading = ref(false);
  const error = ref(null);

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── Getters ──────────────────────────────────────────────────────────────
  const totalWarranties = computed(() => warranties.value.length);
  const openWarranties = computed(() => 
    warranties.value.filter(w => ['open', 'in_progress', 'pending_customer'].includes(w.status))
  );
  const closedWarranties = computed(() =>
    warranties.value.filter(w => ['resolved', 'closed'].includes(w.status))
  );

  // ─── Actions ──────────────────────────────────────────────────────────────

  /**
   * جلب قائمة طلبات الضمان
   */
  const fetchWarranties = async (params = {}, force = false) => {
    try {
      if (!force && warranties.value.length > 0 && isFresh(warrantiesFetchedAt.value, TTL.warranties)) {
        return {
          status: 'success',
          data: warranties.value,
        };
      }

      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get('/warranty', { params });
      const data = response?.data?.data || [];
      warranties.value = Array.isArray(data) ? data : (data?.items || []);
      warrantiesFetchedAt.value = nowMs();

      return {
        status: 'success',
        data: warranties.value,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل تحميل طلبات الضمان';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * جلب تفاصيل طلب ضمان معين
   */
  const fetchWarrantyDetails = async (id, force = false) => {
    try {
      const cacheKey = String(id);
      if (!force && details.value?.id === id && isFresh(detailsFetchedAt.value[cacheKey], TTL.details)) {
        return {
          status: 'success',
          data: details.value,
        };
      }

      isLoading.value = true;
      error.value = null;

      const response = await apiClient.get(`/warranty/${id}`);
      details.value = response?.data?.data || response?.data;
      detailsFetchedAt.value[cacheKey] = nowMs();

      return {
        status: 'success',
        data: details.value,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل تحميل التفاصيل';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * إنشاء طلب ضمان جديد
   */
  const createWarranty = async (payload) => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.post('/warranty', payload);
      const newWarranty = response?.data?.data || response?.data;

      // إضافة إلى القائمة المخزنة
      warranties.value.unshift(newWarranty);

      return {
        status: 'success',
        data: newWarranty,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل إنشاء الطلب';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * تغيير حالة طلب الضمان
   */
  const updateWarrantyStatus = async (id, status, note = '') => {
    try {
      isLoading.value = true;
      error.value = null;

      const response = await apiClient.put(`/warranty/${id}/status`, { 
        status, 
        note 
      });
      const updated = response?.data?.data || response?.data;

      // تحديث الـ details إذا كان هو المفتوح حالياً
      if (details.value?.id === id) {
        details.value = updated;
      }

      // تحديث القائمة
      const index = warranties.value.findIndex(w => w.id === id);
      if (index >= 0) {
        warranties.value[index] = updated;
      }

      return {
        status: 'success',
        data: updated,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل تحديث الحالة';
      return {
        status: 'error',
        message: error.value,
      };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * إضافة ملاحظة للطلب
   */
  const addNote = async (id, content, isInternal = false) => {
    try {
      error.value = null;

      const response = await apiClient.post(`/warranty/${id}/notes`, {
        content,
        is_internal: isInternal,
      });
      const updated = response?.data?.data || response?.data;

      if (details.value?.id === id) {
        details.value = updated;
      }

      return {
        status: 'success',
        data: updated,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل إضافة الملاحظة';
      return {
        status: 'error',
        message: error.value,
      };
    }
  };

  /**
   * رفع مرفق للطلب
   */
  const uploadAttachment = async (id, file) => {
    try {
      error.value = null;

      const formData = new FormData();
      formData.append('file', file);

      const response = await apiClient.post(`/warranty/${id}/attachments`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      const updated = response?.data?.data || response?.data;

      if (details.value?.id === id) {
        details.value = updated;
      }

      return {
        status: 'success',
        data: updated,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل رفع المرفق';
      return {
        status: 'error',
        message: error.value,
      };
    }
  };

  /**
   * حذف مرفق
   */
  const deleteAttachment = async (warrantyId, attachmentId) => {
    try {
      error.value = null;

      const response = await apiClient.delete(`/warranty/${warrantyId}/attachments/${attachmentId}`);
      const updated = response?.data?.data || response?.data;

      if (details.value?.id === warrantyId) {
        details.value = updated;
      }

      return {
        status: 'success',
        data: updated,
      };
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'فشل حذف المرفق';
      return {
        status: 'error',
        message: error.value,
      };
    }
  };

  /**
   * مسح الـ Cache
   */
  const clearCache = () => {
    warranties.value = [];
    details.value = null;
    warrantiesFetchedAt.value = null;
    detailsFetchedAt.value = {};
  };

  return {
    // State
    warranties: computed(() => warranties.value),
    details: computed(() => details.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),

    // Getters
    totalWarranties,
    openWarranties,
    closedWarranties,

    // Actions
    fetchWarranties,
    fetchWarrantyDetails,
    createWarranty,
    updateWarrantyStatus,
    addNote,
    uploadAttachment,
    deleteAttachment,
    clearCache,
  };
});
