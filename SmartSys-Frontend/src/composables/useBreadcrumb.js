import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { getBreadcrumbForPath, checkAccessForPath } from '@/config/menuConfig';
import { useAuthStore } from '@/stores/auth/authStore';

/**
 * Composable for managing breadcrumb from menuConfig (Single Source of Truth)
 * 
 * Returns:
 * - breadcrumb: { items: Array<{ label: string, path?: string }> } - Breadcrumb labels
 * - canAccess: boolean - Whether user can access this route
 * - isLoading: boolean - Auth store loading state
 * 
 * Usage in component:
 * const { breadcrumb, canAccess } = useBreadcrumb();
 * Then pass to PageHeader: :breadcrumb="breadcrumb"
 * 
 * If !canAccess, component should not render or show permission denied
 */
export function useBreadcrumb() {
  const route = useRoute();
  const authStore = useAuthStore();

  const breadcrumb = computed(() => {
    const info = getBreadcrumbForPath(route.path);
    
    if (info && Array.isArray(info.items)) {
      return {
        items: info.items
      };
    }

    // Fallback: no breadcrumb found
    return {
      items: []
    };
  });

  const canAccess = computed(() => {
    return checkAccessForPath(route.path, {
      isAdmin: authStore.isAdmin,
      isSuperAdmin: authStore.isSuperAdmin,
      isOwner: authStore.isOwner,
      isAuthenticated: authStore.isAuthenticated
    });
  });

  return {
    breadcrumb,
    canAccess,
    isLoading: computed(() => authStore.isLoading)
  };
}
