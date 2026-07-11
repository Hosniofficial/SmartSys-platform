<template>
  <div v-if="showBreadcrumb" class="flex items-center gap-3 px-6 py-3 bg-slate-50/50 border-b border-slate-100 text-xs font-bold text-slate-600 tracking-wide">
    <!-- Home Icon -->
    <i class="fas fa-home text-slate-400"></i>
    
    <!-- Page Name -->
    <span class="text-slate-800">{{ pageName }}</span>
    
    <!-- Separator -->
    <i class="fas fa-angle-left text-slate-300 text-[10px]"></i>
    
    <!-- Branch Indicator -->
    <div class="flex items-center gap-1.5">
      <i class="fas fa-building text-blue-500"></i>
      <span class="text-blue-600 font-black">{{ currentBranchName }}</span>
    </div>
    
    <!-- Last Modified (optional) -->
    <div v-if="showLastModified" class="mr-auto pl-4 border-l border-slate-200 text-slate-400 text-[10px]">
      تم التحديث: {{ lastModified }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  pageName: {
    type: String,
    required: true,
  },
  currentBranchName: {
    type: String,
    required: true,
  },
  showBreadcrumb: {
    type: Boolean,
    default: true,
  },
  showLastModified: {
    type: Boolean,
    default: false,
  },
  lastModified: {
    type: String,
    default: '',
  },
});

const formattedBranchName = computed(() => {
  return props.currentBranchName || 'جميع الفروع';
});
</script>

<style scoped>
</style>
