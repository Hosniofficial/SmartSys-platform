import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { listUsers } from '@/services/users';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useUserStore = defineStore('user', () => {

  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    users: 15 * 60 * 1000, // 15 دقيقة
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const users             = ref([]);
  const total             = ref(0);
  const usersFetchedAt    = ref(0);
  const usersInFlight     = ref(null);
  const usersCache        = ref({});
  const usersCacheFetchedAt = ref({});
  const searchCache       = ref({});
  const searchCacheFetchedAt = ref({});

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── fetchUsers ───────────────────────────────────────────────────────────
  const fetchUsers = async ({ force = false, page = 1, limit = 1000 } = {}) => {
    try {
      const cacheKey = `${page}_${limit}`;
      const cached   = usersCache.value[cacheKey];
      const cachedAt = usersCacheFetchedAt.value[cacheKey];

      if (!force && cached && isFresh(cachedAt, TTL.users)) {
        return {
          status: 'success',
          data: cached,
          message: null
        };
      }
      
      if (!force && usersInFlight.value?.[cacheKey]) {
        const result = await usersInFlight.value[cacheKey];
        return {
          status: 'success',
          data: result,
          message: null
        };
      }

      const promise = (async () => {
        try {
          const response = await listUsers({ page, limit });
          const data = response?.data?.data || response?.data || {};
          const list = Array.isArray(data) ? data : (data?.items || []);

          if (page === 1 && limit >= 1000) {
            users.value        = list;
            usersFetchedAt.value = nowMs();
          }

          usersCache.value[cacheKey]          = list;
          usersCacheFetchedAt.value[cacheKey] = nowMs();
          return list;
        } finally {
          if (usersInFlight.value?.[cacheKey])
            delete usersInFlight.value[cacheKey];
        }
      })();

      if (!usersInFlight.value) usersInFlight.value = {};
      usersInFlight.value[cacheKey] = promise;
      const result = await promise;
      
      return {
        status: 'success',
        data: result,
        message: null
      };
    } catch (error) {
      console.error('fetchUsers failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── getUserById ──────────────────────────────────────────────────────────
  /**
   * يرجع المستخدم من الـ cache المحلي أولاً، ثم من الـ API لو مش موجود
   * الـ component يستخدمها هكذا:
   *   const res = await userStore.getUserById(userId);
   *   if (res.status === 'success') { ... }
   */
  const getUserById = async (id) => {
    try {
      if (!id) {
        return {
          status: 'error',
          data: null,
          message: 'User ID is required'
        };
      }

      // Check cache first
      const cached = users.value.find(u => String(u.id) === String(id));
      if (cached) {
        return {
          status: 'success',
          data: cached,
          message: null
        };
      }

      // Fetch from API if not in cache
      const response = await apiClient.get(`/users/${id}`);
      const user = response?.data?.data || response?.data;

      if (user) {
        const index = users.value.findIndex(u => String(u.id) === String(id));
        if (index >= 0) {
          users.value[index] = user;
        } else {
          users.value.push(user);
        }
      }

      return {
        status: 'success',
        data: user,
        message: null
      };
    } catch (err) {
      console.error('getUserById failed:', err);
      return {
        status: 'error',
        data: null,
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── getUserByIdComputed ─────────────────────────────────────────────────────
  /**
   * computed getter من الـ cache المحلي فقط (sync)
   * لو محتاج API call استخدم getUserById
   */
  const getUserByIdComputed = (id) =>
    computed(() => users.value.find(u => String(u.id) === String(id)));

  // ─── createUser ───────────────────────────────────────────────────────────
  /**
   * creates a new user and clears cache
   * returns standardized response like settingsStore
   */
  const createUser = async (payload) => {
    try {
      const response = await apiClient.post('/users', payload);

      // clear cache so list updates on next fetch
      clearUsersCache();
      clearSearchCache();

      return {
        status: response?.data?.status === 'success' ? 'success' : 'error',
        data: response?.data?.data || response?.data || {},
        message: response?.data?.message
      };
    } catch (error) {
      console.error('createUser failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── updateUser ───────────────────────────────────────────────────────────
  /**
   * updates existing user and clears cache
   * returns standardized response like settingsStore
   */
  const updateUser = async (userId, payload) => {
    try {
      if (!userId) {
        return {
          status: 'error',
          data: null,
          message: 'User ID is required'
        };
      }

      const response = await apiClient.put(`/users/${userId}`, payload);

      // update user in local state if available (without waiting for refetch)
      const idx = users.value.findIndex(u => String(u.id) === String(userId));
      if (idx !== -1) {
        users.value[idx] = {
          ...users.value[idx],
          ...(response?.data?.data || response?.data || {}),
        };
      }

      clearUsersCache();
      clearSearchCache();

      return {
        status: response?.data?.status === 'success' ? 'success' : 'error',
        data: response?.data?.data || response?.data || {},
        message: response?.data?.message
      };
    } catch (error) {
      console.error('updateUser failed:', error);
      return {
        status: 'error',
        data: null,
        message: error.response?.data?.message || error.message
      };
    }
  };

  // ─── searchUsers ──────────────────────────────────────────────────────────
  const searchUsers = async (query, { force = false } = {}) => {
    try {
      if (!query || query.trim().length < 2) {
        return {
          status: 'success',
          data: [],
          message: null
        };
      }

      const q        = query.trim().toLowerCase();
      const cacheKey = q;
      const cached   = searchCache.value[cacheKey];
      const cachedAt = searchCacheFetchedAt.value[cacheKey];

      if (!force && cached && isFresh(cachedAt, TTL.users)) {
        return {
          status: 'success',
          data: cached,
          message: null
        };
      }

      // local filter if we have the full users list
      if (users.value.length > 0 && !force) {
        const filtered = users.value.filter(u =>
          u.name?.toLowerCase().includes(q)     ||
          u.email?.toLowerCase().includes(q)    ||
          u.phone?.toLowerCase().includes(q)    ||
          u.username?.toLowerCase().includes(q)
        );
        searchCache.value[cacheKey]          = filtered;
        searchCacheFetchedAt.value[cacheKey] = nowMs();
        return {
          status: 'success',
          data: filtered,
          message: null
        };
      }

      // API fallback
      const response = await listUsers({ q, per_page: 50 });
      const data     = response?.data?.data || response?.data || [];
      const results  = Array.isArray(data) ? data : (data?.items || []);
      
      searchCache.value[cacheKey]          = results;
      searchCacheFetchedAt.value[cacheKey] = nowMs();
      
      return {
        status: 'success',
        data: results,
        message: null
      };
    } catch (err) {
      console.error('searchUsers failed:', err);
      const cacheKey = query?.trim?.().toLowerCase() || '';
      searchCache.value[cacheKey]          = [];
      searchCacheFetchedAt.value[cacheKey] = 0;
      return {
        status: 'error',
        data: null,
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── Computed Getters ─────────────────────────────────────────────────────
  const getUsersByRole = (roleId) =>
    computed(() => users.value.filter(u => String(u.role_id) === String(roleId)));

  // ─── Cache Clearers ───────────────────────────────────────────────────────
  const clearUsersCache = () => {
    usersCache.value          = {};
    usersCacheFetchedAt.value = {};
    usersInFlight.value       = null;
  };

  const clearSearchCache = () => {
    searchCache.value          = {};
    searchCacheFetchedAt.value = {};
  };

  const clear = () => {
    users.value          = [];
    total.value          = 0;
    usersFetchedAt.value = 0;
    clearUsersCache();
    clearSearchCache();
  };

  // ─── Return ───────────────────────────────────────────────────────────────
  return {
    // State
    users,
    total,

    // Read Actions
    fetchUsers,
    searchUsers,
    getUserById,
    getUserByIdComputed,
    getUsersByRole,

    // Write Actions
    createUser,
    updateUser,

    // Cache
    clear,
    clearUsersCache,
    clearSearchCache,
  };
});