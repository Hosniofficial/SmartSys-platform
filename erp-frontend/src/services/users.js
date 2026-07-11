import apiClient from '@/config/axios';

export async function listUsers(params = {}) {
  // Backend route is GET /users/list (see api/v1/index.php)
  const { data } = await apiClient.get('/users/list', { params });
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  if (Array.isArray(data?.items)) return data.items;
  if (Array.isArray(data?.data?.items)) return data.data.items;
  return [];
}
