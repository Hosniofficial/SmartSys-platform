import apiClient from '@/config/axios';

export default {
  // جلب الحسابات مقسمة (مستأجر/عامة)
  async getAll() {
    const res = await apiClient.get('/accounts/grouped');
    const data = res?.data?.data || {};
    // نضمن هيكل موحد
    return {
      tenant_accounts: Array.isArray(data.tenant_accounts) ? data.tenant_accounts : (Array.isArray(data) ? data : []),
      global_accounts: Array.isArray(data.global_accounts) ? data.global_accounts : [],
    };
  },

  // توافق خلفي: تعيد كائن الاستجابة الكامل
  async getAccounts() {
    // توافق خلفي
    return await apiClient.get('/accounts/grouped');
  }
};
