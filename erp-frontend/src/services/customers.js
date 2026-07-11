import apiClient from '@/config/axios';

export async function listCustomers(params = {}) {
  const { data } = await apiClient.get('/customers', { params });
  // Some APIs return {status, data: {items}}; others return array directly
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  if (Array.isArray(data?.items)) return data.items;
  if (Array.isArray(data?.data?.items)) return data.data.items;
  return [];
}
