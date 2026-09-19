---
name: Unified Pagination Standard
description: Standardized pagination pattern used across all list pages (SalesHistory, PurchaseHistory, ReturnsHistory, SalesApprovals)
inclusion: auto
---

# Unified Pagination Standard

This document defines the standardized pagination component used across SmartSys Frontend for all list/history pages.

## Standard Pagination Structure

```vue
<!-- Footer Pagination -->
<div class="px-6 py-4 bg-slate-50/50 border-t border-slate-200 flex items-center justify-between">
  <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
    صفحة <span class="text-slate-900">{{ filters.page.value }}</span> من <span class="text-slate-900">{{ totalPages }}</span>
    <span class="mx-2 text-slate-200">|</span>
    إجمالي <span class="text-slate-900">{{ filters.total.value }}</span> [ENTITY_TYPE]
  </div>
  <div class="flex items-center gap-3">
     <div class="flex items-center gap-2">
       <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">النتائج:</span>
       <select v-model.number="filters.perPage.value" class="h-8 border border-slate-200 rounded px-2 text-[10px] font-bold outline-none">
         <option :value="10">10</option>
         <option :value="20">20</option>
         <option :value="50">50</option>
       </select>
     </div>
     <div class="flex items-center gap-1">
       <button @click="filters.previousPage()" :disabled="filters.page.value<=1" class="pagination-btn-v2"><i class="fas fa-chevron-right"></i></button>
       <button @click="filters.nextPage(totalPages)" :disabled="filters.page.value>=totalPages" class="pagination-btn-v2"><i class="fas fa-chevron-left"></i></button>
     </div>
  </div>
</div>
```

## Component Variables

| Variable | Source | Type | Example |
|----------|--------|------|---------|
| `filters.page.value` | Composable (useHistoryFilters) | Number | 1, 2, 3... |
| `filters.total.value` | API response | Number | 250, 1000... |
| `filters.perPage.value` | User selection | Number | 10, 20, 50 |
| `totalPages` | Computed | Number | `Math.ceil(total / perPage)` |
| `[ENTITY_TYPE]` | Context-specific | String | "فاتورة" (Sales), "فاتورة" (Purchase), "سجل" (Returns), "طلب معلق" (Approvals) |

## Entity Type Labels (Replace `[ENTITY_TYPE]`)

- **SalesHistory**: فاتورة
- **PurchaseHistory**: فاتورة
- **ReturnsHistory**: سجل
- **SalesApprovals**: طلب معلق
- **WarrantyManagement**: طلب ضمان
- **PaymentsList**: عملية
- Other pages: Use appropriate singular/plural form

## Button References

- **Left Arrow (Previous)**: `<i class="fas fa-chevron-right"></i>`
- **Right Arrow (Next)**: `<i class="fas fa-chevron-left"></i>`
- Note: Icons are reversed for RTL layout

## Styling Classes

| Class | Purpose |
|-------|---------|
| `pagination-btn-v2` | Standard button style (w-8 h-8, border, slate colors, hover states) |
| `px-6 py-4` | Footer padding |
| `bg-slate-50/50` | Light background |
| `border-t border-slate-200` | Top border separator |
| `h-8` | Select height |
| `text-[10px]` | Label/select font size |
| `font-bold` | Weight emphasis |

## Composable Integration

Use `useHistoryFilters` composable for consistent filter/pagination management:

```vue
<script setup>
import { useHistoryFilters } from '@/composables/useHistoryFilters';

const filters = useHistoryFilters();
const totalPages = computed(() => Math.max(1, Math.ceil(filters.total.value / filters.perPage.value)));
</script>
```

## Pages Using This Standard

✅ SalesHistory.vue
✅ PurchaseHistory.vue
✅ ReturnsHistory.vue
✅ SalesApprovals.vue (updated)
✅ WarrantyManagement.vue (can be added)
✅ PaymentsList.vue (can be added)

## Design Decisions

1. **Placement**: Bottom footer of data table (not top toolbar)
2. **Layout**: Left: metadata info | Right: controls (select + buttons)
3. **RTL Support**: Icons reversed, Arabic labels, right-aligned
4. **Accessibility**: Disabled states on edge cases (page <= 1, page >= totalPages)
5. **Options**: 10, 20, 50 items per page (covers most use cases)

## Future Enhancements

- [ ] Add "Go to page" input
- [ ] Display current items count (e.g., "Showing 1-20 of 250")
- [ ] Add page number quick-jump buttons
- [ ] Remember user's last page size preference
