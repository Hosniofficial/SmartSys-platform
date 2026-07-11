import { ref, computed } from 'vue';
import apiClient from '@/config/axios';
import { useAuthStore } from '@/stores/auth';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useBranchStore } from '@/stores/branch';

export function useUserBranchPreference(storageKey = 'selectedBranch') {
  const authStore = useAuthStore();
  const { isExempt } = useSessionExemption();
  const branchStore = useBranchStore();

  // Use computed branches from store to avoid duplication
  const branches = computed(() => branchStore.branches);
  const isLoadingPreferences = ref(false);

  const selectedBranchId = computed({
    get: () => branchStore.selectedBranchId,
    set: (val) => branchStore.setSelectedBranch(val)
  });

  const resolveDefaultBranchId = () => {
    // Delegate to store to avoid duplication
    return String(branchStore.resolveDefaultBranchId?.(branches.value) || '');
  };

  const hasBranch = (id) => {
    if (!id) return false;
    return branches.value.some(b => String(b.id) === String(id));
  };

  const loadBranches = async () => {
    try {
      await branchStore.fetchBranches();
    } catch (err) {
      console.error('Error loading branches:', err);
    }
  };

  const loadUserPreference = async () => {
    isLoadingPreferences.value = true;
    try {

      // Non-exempt users: auto-assign to their branch
      if (!isExempt.value) {
        const userBranchId = authStore?.user?.branch_id;
        if (userBranchId) {
          branchStore.setSelectedBranch(userBranchId);
          return;
        }
      }

      // Try to load from database first
      try {
        const response = await apiClient.get('/user/preferences');
        if (response.data?.selected_branch_id) {
          const candidate = String(response.data.selected_branch_id);
          if (hasBranch(candidate)) {
            branchStore.setSelectedBranch(candidate);
            // Sync to localStorage for quick access
            localStorage.setItem(storageKey, candidate);
            localStorage.setItem('selectedBranchId', candidate);
            return;
          }
        }
      } catch (dbErr) {
        console.warn('Failed to load preferences from database:', dbErr);
        // Continue to fallback options
      }

      // Fallback to localStorage
      const saved = localStorage.getItem(storageKey) || localStorage.getItem('selectedBranchId');
      if (saved === 'all') {
        branchStore.setSelectedBranch(null); // احترم اختيار "كل الفروع"
      } else if (saved && hasBranch(saved)) {
        branchStore.setSelectedBranch(saved);
      } else {
        const def = resolveDefaultBranchId();
        if (def) {
          branchStore.setSelectedBranch(def);
          localStorage.setItem(storageKey, def);
          localStorage.setItem('selectedBranchId', def);
        }
      }
    } finally {
      isLoadingPreferences.value = false;
    }
  };

  const saveBranchPreference = async (branchId) => {
    if (!branchId) {
      localStorage.removeItem(storageKey);
      localStorage.removeItem('selectedBranchId');
      // Also remove from database
      try {
        await apiClient.post('/user/preferences', { selected_branch_id: null });
      } catch (err) {
        console.warn('Failed to clear preference in database:', err);
      }
      return;
    }

    const normalized = String(branchId);
    branchStore.setSelectedBranch(normalized);
    localStorage.setItem(storageKey, normalized);
    localStorage.setItem('selectedBranchId', normalized);

    // Save to database for cross-device persistence
    try {
      await apiClient.post('/user/preferences', { selected_branch_id: branchId });
    } catch (err) {
      console.warn('Failed to save preference to database:', err);
      // Still have localStorage as fallback
    }
  };

  const currentBranchName = computed(() => {
    if (!branchStore.selectedBranchId) return 'غير محدد';
    const branch = branches.value.find(b => String(b.id) === String(branchStore.selectedBranchId));
    return branch?.name || 'غير محدد';
  });

  const currentBranch = computed(() => {
    return branches.value.find(b => String(b.id) === String(branchStore.selectedBranchId));
  });

  const initializePreferences = async () => {
    await loadBranches();
    await loadUserPreference();
  };

  return {
    branches,
    selectedBranchId,
    isLoadingPreferences,
    loadBranches,
    loadUserPreference,
    saveBranchPreference,
    initializePreferences,
    currentBranchName,
    currentBranch,
  };
}