import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/config/axios';

export const useUsersStore = defineStore('users', () => {
  const users = ref([]);
  const loading = ref(false);
  const error = ref(null);

  const fetchUsers = async (params = {}) => {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await apiClient.get('/users/list', { 
        params: {
          page: 1,
          limit: 100,
          ...params
        }
      });
      users.value = response.data?.data?.items || [];
      return {
        status: 'success',
        data: users.value,
        message: ''
      };
    } catch (err) {
      console.error('Error fetching users:', err);
      error.value = err.message || 'فشل جلب المستخدمين';
      return {
        status: 'error',
        data: [],
        message: err?.response?.data?.message || err.message || 'فشل جلب المستخدمين'
      };
    } finally {
      loading.value = false;
    }
  };

  const clearUsers = () => {
    users.value = [];
    error.value = null;
  };

  return {
    users,
    loading,
    error,
    fetchUsers,
    clearUsers
  };
});
