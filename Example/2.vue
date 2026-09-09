<template>
  <aside
    v-if="section"
    class="section-panel fixed top-14 md:top-16 z-30 h-[calc(100vh-3.5rem)] md:h-[calc(100vh-4rem)] w-[min(320px,calc(100vw-72px))] bg-white border-l border-slate-200 shadow-lg flex flex-col transition-[right] duration-200"
    :style="{ right: sidebarCollapsed ? '72px' : '256px' }"
    aria-label="التنقل داخل القسم"
  >
    <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-slate-100 shrink-0">
      <div class="flex items-center gap-2 min-w-0">
        <component :is="section.icon" :size="18" class="text-blue-600 shrink-0" />
        <h2 class="text-sm font-bold text-slate-900 truncate">{{ section.name }}</h2>
      </div>
      <button
        type="button"
        class="w-8 h-8 flex items-center justify-center rounded-md text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors shrink-0"
        aria-label="إغلاق لوحة القسم"
        @click="$emit('close')"
      >
        <X :size="16" :stroke-width="2.5" />
      </button>
    </div>

    <div v-if="section.items?.length > 8" class="px-3 pt-3 shrink-0">
      <label class="sr-only" :for="`section-search-${section.key}`">البحث في {{ section.name }}</label>
      <input
        :id="`section-search-${section.key}`"
        v-model="searchQuery"
        type="search"
        :placeholder="`ابحث في ${section.name}...`"
        class="w-full h-9 px-3 rounded-md border border-slate-200 text-[11px] text-slate-700 outline-none focus:border-blue-500"
      />
    </div>

    <nav class="flex-1 overflow-y-auto p-3 custom-scroll" :aria-label="section.name">
      <div v-for="group in groups" :key="group.key" class="mb-4 last:mb-0">
        <h3 v-if="group.name" class="px-2 mb-1.5 text-[10px] font-bold text-slate-400">{{ group.name }}</h3>
        <div v-if="group.name" class="h-px bg-slate-100 mb-1.5"></div>
        <router-link
          v-for="child in group.items"
          :key="child.path"
          :to="child.path"
          class="flex items-center gap-2.5 min-h-10 px-3 py-2 rounded-md text-[11px] font-bold transition-colors"
          :class="isLeafActive(child) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
          @click="$emit('navigate', child)"
        >
          <component :is="child.icon" :size="15" class="shrink-0" />
          <span class="flex-1 min-w-0 truncate">{{ child.name }}</span>
          <span v-if="child.badge && badgeCounts[child.badge]" class="bg-blue-600 text-white text-[9px] px-1.5 py-0.5 rounded-full shrink-0">
            {{ badgeCounts[child.badge] }}
          </span>
        </router-link>
      </div>

      <p v-if="section.items?.length > 8 && groups.length === 0" class="px-2 py-6 text-center text-[11px] text-slate-400">
        لا توجد نتائج مطابقة
      </p>
    </nav>
  </aside>
</template>

<script setup>
import { computed, ref } from 'vue'
import { X } from 'lucide-vue-next'
import { useActiveRoute } from '@/composables/useActiveRoute'

const props = defineProps({
  section: { type: Object, default: null },
  badgeCounts: { type: Object, default: () => ({}) },
  sidebarCollapsed: { type: Boolean, default: true }
})

defineEmits(['close', 'navigate'])

// Single shared implementation — fixes the route.path vs route.fullPath
// inconsistency that previously existed between this file and Layout.vue.
const { isLeafActive } = useActiveRoute()

const searchQuery = ref('')
const groupNames = {
  master: 'البيانات الأساسية',
  operations: 'التشغيل',
  reports: 'التقارير',
  sales: 'تقارير المبيعات',
  financial: 'التقارير المالية',
  inventory: 'تقارير المخزون',
  system: 'تقارير النظام',
  settings: 'الضبط',
  analytics: 'التحليلات',
  general: 'عام'
}

const groups = computed(() => {
  if (!props.section?.items) return []
  const query = searchQuery.value.trim().toLocaleLowerCase()
  const grouped = new Map()
  props.section.items.forEach(item => {
    if (query && !item.name.toLocaleLowerCase().includes(query)) return
    const key = item.group || 'general'
    if (!grouped.has(key)) grouped.set(key, [])
    grouped.get(key).push(item)
  })
  const result = Array.from(grouped, ([key, items]) => ({ key, name: groupNames[key] || key, items }))

  // ✅ إذا كان هناك مجموعة واحدة فقط وهي المجموعة الافتراضية 'general'
  // (يعني لا يوجد تصنيف حقيقي لهذا القسم)، نخفي العنوان
  if (result.length === 1 && result[0].key === 'general') {
    return [{ ...result[0], name: null }]
  }
  return result
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }

@media (prefers-reduced-motion: reduce) {
  .section-panel, .section-panel * { transition: none !important; }
}
</style>
