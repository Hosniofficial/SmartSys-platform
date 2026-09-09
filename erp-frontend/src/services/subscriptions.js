import apiClient from '@/config/axios';

export const SubscriptionsService = {
  async getPlans() {
    const res = await apiClient.get('/plans', { meta: { skipLoader: true } });
    return res.data?.data || [];
  },
  async getAvailablePlans() {
    const res = await apiClient.get('/plans/available', { meta: { skipLoader: true } });
    return res.data?.data || [];
  },
  async getMySubscription() {
    const res = await apiClient.get('/subscription/me');
    return res.data?.data?.current || null;
  }
};

export default SubscriptionsService;
