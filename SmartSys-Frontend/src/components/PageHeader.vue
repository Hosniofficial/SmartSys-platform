<template>
  <!-- Page Header Area -->
  <header class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b border-slate-200">
    <div class="space-y-1">
      <!-- Breadcrumb Navigation -->
      <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wider">
        <RouterLink v-if="breadcrumb.parent?.path" :to="breadcrumb.parent.path" class="hover:text-slate-700 transition-colors">
          {{ breadcrumb.parent?.label || breadcrumb.parent }}
        </RouterLink>
        <span v-else>{{ breadcrumb.parent?.label || breadcrumb.parent }}</span>
        <i class="fas fa-chevron-left text-[8px]"></i>
        <span class="text-slate-900">{{ breadcrumb.current?.label || breadcrumb.current }}</span>
      </div>
      
      <!-- Page Title -->
      <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
      
      <!-- Page Description -->
      <p class="text-sm text-slate-500 font-medium">{{ description }}</p>
    </div>

    <!-- Right Side Controls (Slots) -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Branch Selector — يظهر فقط للـ exempt users (branches غير فارغة) -->
      <div v-if="branches && branches.length > 0" class="relative">
        <select 
          :value="selectedBranch" 
          @change="$emit('branch-changed', $event.target.value)"
          class="h-9 pr-9 pl-4 rounded-md border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-slate-300 focus:ring-2 focus:ring-blue-500/10 outline-none transition-all appearance-none cursor-pointer min-w-[180px]"
        >
          <option :value="null">جميع الفروع</option>
          <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
            {{ branch.name }}
          </option>
        </select>
        <i class="fas fa-building absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
      </div>

      <div v-if="branches && branches.length > 0" class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>

      <!-- Slot for Additional Controls -->
      <slot name="controls"></slot>
    </div>
  </header>
</template>

<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  breadcrumb: {
    type: Object,
    required: true,
    validator: (value) => {
      return 'parent' in value && 'current' in value
    }
  },
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  branches: {
    type: Array,
    required: true
  },
  selectedBranch: {
    type: [String, Number, null],
    default: null
  }
})

defineEmits(['branch-changed'])
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
