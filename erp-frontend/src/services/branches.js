import apiClient from '@/config/axios';

export const BranchesService = {
  async getAll() {
    const res = await apiClient.get('/branches');
    return res.data?.data || [];
  },

  async list() {
    const res = await apiClient.get('/branches');
    return res.data;
  },

  async create(payload) {
    const res = await apiClient.post('/branches', payload);
    return res.data;
  },

  async update(id, payload) {
    const res = await apiClient.put(`/branches/${id}`, payload);
    return res.data;
  }
};

export default BranchesService;
