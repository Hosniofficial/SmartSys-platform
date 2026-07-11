import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useRoleStore = defineStore('role', () => {

  // ─── Cache TTL ────────────────────────────────────────────────────────────
  const TTL = {
    roles: 60 * 60 * 1000, // 60 دقيقة (نادراً ما يتغير)
  };

  // ─── State ────────────────────────────────────────────────────────────────
  const roles          = ref([]);
  const rolesFetchedAt = ref(0);
  const rolesInFlight  = ref(null);

  const searchCache          = ref({});
  const searchCacheFetchedAt = ref({});

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── fetchRoles ───────────────────────────────────────────────────────────
  /**
   * يجيب كل الأدوار مع caching
   * الـ component يستخدمها هكذا:
   *   const res = await roleStore.fetchRoles();
   *   if (res.status === 'success') { roles.value = res.data; }
   */
  const fetchRoles = async ({ force = false } = {}) => {
    try {
      if (
        !force &&
        isFresh(rolesFetchedAt.value, TTL.roles) &&
        Array.isArray(roles.value) &&
        roles.value.length
      ) {
        return {
          status: 'success',
          data:   roles.value,
          message: null
        };
      }

      if (!force && rolesInFlight.value) {
        const result = await rolesInFlight.value;
        return {
          status: 'success',
          data:   result,
          message: null
        };
      }

      const promise = (async () => {
        try {
          const response = await apiClient.get('/rbac/roles');
          const list     = response?.data?.data || response?.data || [];

          roles.value          = Array.isArray(list)
            ? list.map(r => ({ id: r.id, name: r.name, ...r }))
            : [];
          rolesFetchedAt.value = nowMs();
          return roles.value;
        } catch {
          roles.value          = [];
          rolesFetchedAt.value = 0;
          return [];
        } finally {
          rolesInFlight.value = null;
        }
      })();

      rolesInFlight.value = promise;
      const result = await promise;

      return {
        status: 'success',
        data:   result,
        message: null
      };
    } catch (err) {
      console.error('fetchRoles failed:', err);
      return {
        status: 'error',
        data:   [],
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── searchRoles ──────────────────────────────────────────────────────────
  /**
   * يبحث في الأدوار محلياً لو البيانات موجودة، وإلا يكلم الـ API
   * الـ component يستخدمها هكذا:
   *   const res = await roleStore.searchRoles(query);
   *   if (res.status === 'success') { results.value = res.data; }
   */
  const searchRoles = async (query, { force = false } = {}) => {
    try {
      if (!query || query.trim().length < 2) {
        return {
          status: 'success',
          data:   [],
          message: null
        };
      }

      const q        = query.trim().toLowerCase();
      const cacheKey = q;
      const cached   = searchCache.value[cacheKey];
      const cachedAt = searchCacheFetchedAt.value[cacheKey];

      if (!force && cached && isFresh(cachedAt, TTL.roles)) {
        return {
          status: 'success',
          data:   cached,
          message: null
        };
      }

      // فلتر محلي لو البيانات موجودة
      if (roles.value.length > 0 && !force) {
        const filtered = roles.value.filter(r =>
          r.name?.toLowerCase().includes(q)         ||
          r.description?.toLowerCase().includes(q)  ||
          r.display_name?.toLowerCase().includes(q)
        );
        searchCache.value[cacheKey]          = filtered;
        searchCacheFetchedAt.value[cacheKey] = nowMs();
        return {
          status: 'success',
          data:   filtered,
          message: null
        };
      }

      // وإلا ابحث عبر الـ API مع fallback للفلتر المحلي
      try {
        const response = await apiClient.get('/rbac/roles', { params: { q } });
        const results  = response?.data?.data || response?.data || [];
        const list     = Array.isArray(results)
          ? results.map(r => ({ id: r.id, name: r.name, ...r }))
          : [];

        searchCache.value[cacheKey]          = list;
        searchCacheFetchedAt.value[cacheKey] = nowMs();

        return {
          status: 'success',
          data:   list,
          message: null
        };
      } catch {
        // لو الـ endpoint مش موجود — فلتر من الـ cache المتاح
        const fallback = roles.value.filter(r =>
          r.name?.toLowerCase().includes(q)         ||
          r.description?.toLowerCase().includes(q)  ||
          r.display_name?.toLowerCase().includes(q)
        );
        searchCache.value[cacheKey]          = fallback;
        searchCacheFetchedAt.value[cacheKey] = fallback.length ? nowMs() : 0;

        return {
          status: 'success',
          data:   fallback,
          message: null
        };
      }
    } catch (err) {
      console.error('searchRoles failed:', err);
      return {
        status: 'error',
        data:   [],
        message: err.response?.data?.message || err.message
      };
    }
  };

  // ─── Computed Getters ─────────────────────────────────────────────────────
  const getRoleById = (id) =>
    computed(() => roles.value.find(r => String(r.id) === String(id)));

  const roleOptions = computed(() =>
    roles.value.map(r => ({ id: r.id, name: r.name, label: r.name || r.display_name }))
  );

  // ─── Cache Clearers ───────────────────────────────────────────────────────
  const clear = () => {
    roles.value          = [];
    rolesFetchedAt.value = 0;
    rolesInFlight.value  = null;
    searchCache.value          = {};
    searchCacheFetchedAt.value = {};
  };

  const clearSearchCache = () => {
    searchCache.value          = {};
    searchCacheFetchedAt.value = {};
  };

  // ─── Return ───────────────────────────────────────────────────────────────
  return {
    // State
    roles,

    // Computed
    roleOptions,
    getRoleById,

    // Read Actions
    fetchRoles,
    searchRoles,

    // Cache
    clear,
    clearSearchCache,
  };
});