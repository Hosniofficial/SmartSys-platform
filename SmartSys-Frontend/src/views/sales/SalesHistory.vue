<template>
  <div class="min-h-screen bg-[#fafafa] text-slate-900 font-sans antialiased selection:bg-blue-100" dir="rtl">
    
    <!-- Top Progress Bar -->
    <div v-if="isLoadingList || isLoadingDetails || isMarkingPaid" class="fixed top-0 left-0 right-0 h-0.5 bg-blue-600/10 z-[110]">
      <div class="h-full bg-blue-600 animate-[loading_2s_ease-in-out_infinite] w-1/3"></div>
    </div>

    <div class="max-w-[1600px] mx-auto p-6 lg:p-10 space-y-8">
      
      <!-- Data Stale Warning Banner -->
      <transition name="slide-down">
        <div v-if="isDataStale" class="p-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 flex items-start gap-3">
          <i class="fas fa-exclamation-triangle text-amber-600 text-lg mt-0.5"></i>
          <div class="flex-1">
            <p class="text-sm font-bold text-amber-900">⚠️ تحذير: البيانات قد تكون قديمة</p>
            <p class="text-xs text-amber-700 mt-1">فشل تحديث البيانات. البيانات المعروضة أسفله قد لا تعكس أحدث التغييرات. <span v-if="lastSuccessfulUpdate" class="text-amber-600 font-bold">آخر تحديث ناجح: {{ formatDateTime(lastSuccessfulUpdate) }}</span></p>
            <button @click="fetchSalesHistory" class="mt-2 px-3 py-1 bg-amber-600 text-white text-xs font-bold rounded hover:bg-amber-700 transition-colors">
              <i class="fas fa-redo ml-1"></i> إعادة محاولة
            </button>
          </div>
        </div>
      </transition>
      
      <!-- Page Header -->
      <PageHeader
        :breadcrumb="breadcrumb"
        title="سجل عمليات البيع"
        description="تتبع، فلترة، وإدارة جميع الفواتير الصادرة من النظام."
        :branches="branches"
        :selectedBranch="selectedBranch"
        :hasExplicitSelection="hasExplicitBranchSelection"
        @branch-changed="onBranchChange"
      >
        <template #controls>
          <button 
            @click="filters.showFilters.value = !filters.showFilters.value" 
            :class="[filters.showFilters.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border-slate-200']"
            class="h-9 px-4 rounded-md border text-xs font-bold transition-all flex items-center gap-2 shadow-sm"
          >
            <i class="fas fa-filter text-[10px]"></i>
            {{ filters.showFilters.value ? 'إخفاء أدوات التصفية' : 'تصفية النتائج' }}
          </button>
        </template>
      </PageHeader>

      <!-- KPI Summary: Supabase-inspired Data Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
        <div v-for="kpi in [
          { label: 'عدد الفواتير', val: filters.total.value, icon: 'fa-clipboard-list', color: 'text-blue-600', bg: 'bg-blue-50', show: true },
          { label: 'إجمالي المبيعات', val: formatPrice(filters.kpiSum.value), icon: 'fa-file-invoice-dollar', color: 'text-emerald-600', bg: 'bg-emerald-50', show: true },
          { label: 'إجمالي الضريبة', val: formatPrice(filters.kpiTax.value), icon: 'fa-percent', color: 'text-indigo-600', bg: 'bg-indigo-50', show: filters.kpiTax.value != null },
          { label: 'إجمالي الخصومات', val: formatPrice(filters.kpiDiscount.value), icon: 'fa-tags', color: 'text-rose-600', bg: 'bg-rose-50', show: filters.kpiDiscount.value != null }
        ].filter(k => k.show)" :key="kpi.label" class="bg-white border border-slate-200 p-5 rounded-xl flex items-center justify-between group hover:border-slate-300 transition-all">
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">{{ kpi.label }}</p>
            <p class="text-xl font-bold text-slate-900">{{ kpi.val }}</p>
          </div>
          <div :class="[kpi.bg, kpi.color]" class="w-10 h-10 rounded-lg flex items-center justify-center text-sm opacity-80 group-hover:opacity-100 transition-opacity">
            <i :class="['fas', kpi.icon]"></i>
          </div>
        </div>
      </section>

      <!-- Filters Panel: Linear-style high density inputs -->
      <transition name="slide-down">
        <div v-if="filters.showFilters.value" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden animate-fadeIn">
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">بحث سريع</label>
                <div class="relative">
                  <input v-model="filters.searchQuery.value" type="text" class="filter-input" style="padding-right: 2rem;" placeholder="رقم الفاتورة أو العميل..." />
                  <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">من تاريخ</label>
                <div class="relative">
                  <input ref="dateFromRefLocal" type="date" v-model="filters.dateFrom.value" class="filter-input" style="padding-left: 2rem;" />
                  <i @click="dateFromRefLocal?.showPicker?.()" class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-300 hover:text-slate-500 transition-colors text-[10px]"></i>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">إلى تاريخ</label>
                <div class="relative">
                  <input ref="dateToRefLocal" type="date" v-model="filters.dateTo.value" class="filter-input" style="padding-left: 2rem;" />
                  <i @click="dateToRefLocal?.showPicker?.()" class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-300 hover:text-slate-500 transition-colors text-[10px]"></i>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">حالة السداد</label>
                <select v-model="filters.statusFilter.value" class="filter-input appearance-none">
                  <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end pt-4 border-t border-slate-50">
              <div class="md:col-span-2 space-y-1.5 relative">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">تصفية حسب العميل</label>
                <div class="relative">
                  <input v-model="filters.customerSearch.value" type="text" class="filter-input" style="padding-right: 2rem;" placeholder="ابحث عن عميل..." @focus="filters.showCustomerDropdown.value = true" @blur="filters.hideCustomerDropdown()" />
                  <i class="fas fa-user-circle absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 text-[10px] pointer-events-none"></i>
                  
                  <div v-if="filters.showCustomerDropdown.value" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl max-h-48 overflow-auto">
                    <button @mousedown.prevent="filters.clearCustomerFilter()" class="w-full text-right px-4 py-2 text-[11px] hover:bg-slate-50 border-b border-slate-50 text-blue-600 font-bold">جميع العملاء</button>
                    <button v-for="c in filteredCustomers" :key="c.id" @mousedown.prevent="filters.setCustomerFilter(c.id, c.name || c.customer_name)" class="w-full text-right px-4 py-2 text-[11px] hover:bg-slate-50 border-b border-slate-50 last:border-0 font-medium">
                      {{ c.name || c.customer_name }}
                    </button>
                  </div>
                </div>
              </div>

              <button @click="filters.clearAllFilters()" class="h-9 w-full rounded-md bg-slate-100 text-slate-600 text-[11px] font-bold hover:bg-slate-200 transition-all">
                إعادة تعيين الفلاتر
              </button>
            </div>
          </div>
        </div>
      </transition>

      <!-- Active Filter Chips -->
      <div v-if="filters.hasActiveFilters.value || filters.customerFilter.value || (isExempt.value && hasExplicitBranchSelection)" class="flex flex-wrap gap-2 animate-fadeIn">
        <div v-for="chip in [
          { show: filters.searchQuery.value, label: filters.searchQuery.value, clear: () => filters.searchQuery.value = '' },
          { show: filters.dateFrom.value, label: `من: ${filters.dateFrom.value}`, clear: () => filters.dateFrom.value = '' },
          { show: filters.dateTo.value, label: `إلى: ${filters.dateTo.value}`, clear: () => filters.dateTo.value = '' },
          { show: filters.statusFilter.value, label: getStatusLabel(filters.statusFilter.value), clear: () => filters.statusFilter.value = '' },
          { show: filters.customerFilter.value, label: filters.customerSearch.value, clear: () => filters.clearCustomerFilter() },
          { show: (isExempt.value && hasExplicitBranchSelection), label: `الفرع: ${branches.find(b => b.id == selectedBranch)?.name || selectedBranch}`, clear: () => onBranchChange(null) }
        ].filter(c => c.show)" :key="chip.label" class="inline-flex items-center gap-2 px-2.5 py-1 bg-blue-50 border border-blue-100 rounded-md text-[10px] font-bold text-blue-700">
          {{ chip.label }}
          <i @click="chip.clear" class="fas fa-times cursor-pointer hover:text-blue-900 opacity-60"></i>
        </div>
      </div>

      <!-- Main Data Table -->
      <div class="bg-white rounded-xl overflow-hidden shadow-sm relative" :class="[
        isDataStale ? 'border-2 border-amber-300' : 'border border-slate-200'
      ]">
        
        <!-- Bulk Actions: Dark SaaS overlay -->
        <transition name="slide-up">
          <div v-if="filters.selectedIds.value.length" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-[50] bg-slate-900 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-6 border border-white/10">
            <div class="flex items-center gap-3 border-l border-white/10 pl-6">
              <span class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-[10px] font-bold">{{ filters.selectedIds.value.length }}</span>
              <span class="text-xs font-bold uppercase tracking-wider">فاتورة محددة</span>
            </div>
            <div class="flex items-center gap-4">
              <button @click="markSelectedAsPaid" :disabled="isMarkingPaid" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 disabled:opacity-50 transition-colors">
                <i class="fas fa-money-check-alt ml-1.5"></i> تسديد المحدد
              </button>
              <button @click="exportSelectedCsv" class="text-xs font-bold text-blue-300 hover:text-blue-200 transition-colors">
                <i class="fas fa-download ml-1.5"></i> تصدير CSV
              </button>
              <button @click="printSelected" class="text-xs font-bold text-slate-300 hover:text-white transition-colors">
                <i class="fas fa-print ml-1.5"></i> طباعة
              </button>
              <button @click="filters.clearSelection()" class="text-xs font-bold text-slate-500 hover:text-slate-300 transition-colors">إلغاء</button>
            </div>
          </div>
        </transition>

        <div class="overflow-x-auto">
          <table class="w-full text-right border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200">
                <th class="px-6 py-4 w-12">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" />
                </th>
                <th @click="toggleSort('id')" class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest cursor-pointer group">
                  الفاتورة <i :class="filters.sortKey.value==='id' ? (filters.sortAsc.value? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'" class="fas ml-1 opacity-20 group-hover:opacity-100 transition-opacity"></i>
                </th>
                <th @click="toggleSort('created_at')" class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest cursor-pointer group">
                  التاريخ <i :class="filters.sortKey.value==='created_at' ? (filters.sortAsc.value? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'" class="fas ml-1 opacity-20 group-hover:opacity-100 transition-opacity"></i>
                </th>
                <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">العميل</th>
                <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">الأصناف</th>
                <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">الإجمالي</th>
                <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">الحالة</th>
                <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-if="isLoadingList">
                <tr v-for="n in 5" :key="n" class="animate-pulse">
                  <td v-for="m in 8" :key="m" class="px-6 py-4"><div class="h-3 bg-slate-100 rounded w-full"></div></td>
                </tr>
              </template>
              <tr v-else-if="!sales.length">
                <td colspan="8" class="py-20 text-center text-slate-300">
                   <i class="fas fa-receipt text-3xl mb-4 opacity-20"></i>
                   <p class="text-xs font-bold uppercase tracking-widest">لا توجد فواتير مبيعات حتى الآن</p>
                </td>
              </tr>
              <tr v-for="sale in sales" :key="sale.id" class="hover:bg-blue-50/20 transition-all group">
                <td class="px-6 py-4">
                  <input type="checkbox" :value="sale.id" v-model="filters.selectedIds.value" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" />
                </td>
                <td class="px-4 py-4 text-xs font-bold text-slate-900">{{ sale.invoice_number || '#' + sale.id }}</td>
                <td class="px-4 py-4 text-[10px] font-mono text-slate-400">{{ formatDateTime(sale.created_at) }}</td>
                <td class="px-4 py-4 text-xs font-bold text-slate-700 truncate max-w-[150px]">{{ sale.customer_name || 'عميل نقدي' }}</td>
                <td class="px-4 py-4 text-center">
                  <span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-bold text-slate-500">{{ sale.total_items }}</span>
                </td>
                <td class="px-4 py-4 text-xs font-bold text-blue-600">{{ formatPrice(getSaleGross(sale)) }}</td>
                <td class="px-4 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <span :class="[getDynamicStatusClass(sale.dynamic_status)]" class="px-2 py-0.5 rounded text-[9px] font-bold border">
                      {{ getStatusLabel(sale.dynamic_status) }}
                    </span>
                    <span v-if="getAgeBadge(sale)" :class="[getAgeBadge(sale).cls]" class="px-1.5 py-0.5 rounded text-[8px] font-bold border">
                      {{ getAgeBadge(sale).label }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-4 text-center">
                  <button @click="viewSaleDetails(sale.id)" :disabled="isLoadingDetails" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all flex items-center justify-center mx-auto disabled:opacity-50">
                    <i class="fas fa-eye text-[10px]"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-200 flex items-center justify-between">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
            صفحة <span class="text-slate-900">{{ filters.page.value }}</span> من <span class="text-slate-900">{{ totalPages }}</span>
            <span class="mx-2 text-slate-200">|</span>
            إجمالي <span class="text-slate-900">{{ filters.total.value }}</span> فاتورة
          </div>
          <div class="flex items-center gap-3">
             <div class="flex items-center gap-2">
               <span class="text-[10px] font-bold text-slate-400 uppercase">النتائج:</span>
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
      </div>
    </div>

    <!-- Details Modal -->
    <BaseModal :show="showDetailsModal && !!selectedSale" @close="showDetailsModal = false" maxWidth="4xl">
      <template #header>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white text-xs"><i class="fas fa-receipt"></i></div>
          <h3 class="text-sm font-bold text-slate-900 uppercase">تفاصيل الفاتورة {{ selectedSale?.invoice_number || '#' + selectedSale?.id }}</h3>
        </div>
      </template>

      <div class="space-y-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div v-for="info in [
            { l: 'العميل', v: selectedSale?.customer_name || 'عميل نقدي' },
            { l: 'طريقة الدفع', v: getPaymentMethodName(selectedSalePaymentMethodId) },
            { l: 'الفرع', v: selectedSale?.branch_name || '-' },
            { l: 'حالة الفاتورة', v: getStatusLabel(selectedSale?.dynamic_status) },
            { l: 'التاريخ', v: formatDateTime(selectedSale?.created_at) }
          ]" :key="info.l" class="space-y-1">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ info.l }}</p>
            <p class="text-xs font-bold text-slate-800">{{ info.v }}</p>
          </div>
        </div>

        <!-- Status Message if Available -->
        <div v-if="getStatusMessage(selectedSale?.dynamic_status)" class="p-4 rounded-lg border-l-4 border-blue-400 bg-blue-50">
          <p class="text-xs font-bold text-blue-700">{{ getStatusMessage(selectedSale?.dynamic_status) }}</p>
        </div>

        <div class="border border-slate-100 rounded-xl overflow-hidden">
          <table class="w-full text-right text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100">
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">المنتج</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase text-center">الكمية</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">سعر الوحدة</th>
                <th class="px-4 py-3 text[10px] font-bold text-slate-400 uppercase">الإجمالي الأصلي</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">الخصم</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">الضريبة</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">الصافي للوحدة</th>
                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">الإجمالي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="item in selectedSale?.items" :key="item.id">
                <td class="px-4 py-3">
                  <p class="font-bold text-slate-800">{{ item.product_name }}</p>
                </td>
                <td class="px-4 py-3 text-center font-bold text-slate-500">{{ item.quantity }}</td>
                <td class="px-4 py-3 font-bold text-slate-700">{{ formatPrice(item.sale_price) }}</td>
                <td class="px-4 py-3 font-bold text-slate-700">{{ formatPrice((item.sale_price || 0) * (item.quantity || 0)) }}</td>
                <td class="px-4 py-3 font-bold text-rose-500">{{ formatPrice(item.discount_value || 0) }}</td>
                <td class="px-4 py-3 font-bold text-indigo-500">{{ formatPrice((item.net_price || 0) * (taxRate / 100)) }}</td>
                <td class="px-4 py-3 font-bold text-slate-700">{{ formatPrice(item.net_price || 0) }}</td>
                <td class="px-4 py-3 font-bold text-slate-900">{{ formatPrice(item.net_total * (1 + taxRate / 100)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col md:flex-row gap-8">
          <div class="flex-1 p-5 rounded-xl bg-slate-50/50 border border-slate-100">
            <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">ملاحظات الفاتورة</p>
            <p class="text-xs text-slate-600 italic">{{ selectedSale?.notes || 'لا توجد ملاحظات.' }}</p>
          </div>
          <div class="w-full md:w-72 space-y-3 bg-slate-900 p-6 rounded-xl text-white">
            <div class="flex justify-between text-[11px] font-medium text-slate-400"><span>الإجمالي الفرعي</span><span>{{ formatPrice(subTotalNet) }}</span></div>
            <div class="flex justify-between text-[11px] font-medium text-slate-400"><span>الخصومات</span><span class="text-rose-400">- {{ formatPrice(totalDiscount) }}</span></div>
            <div class="flex justify-between text-[11px] font-medium text-slate-400 pb-3 border-b border-white/10"><span>الضريبة ({{ taxRateDisplay }}%)</span><span>{{ formatPrice(finalNetTotal * (taxRate / 100)) }}</span></div>
            <div class="flex justify-between items-end pt-1">
              <span class="text-xs font-bold uppercase tracking-widest text-blue-400">الصافي</span>
              <span class="text-2xl font-bold tracking-tighter">{{ formatPrice(finalNetTotal * (1 + taxRate / 100)) }}</span>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <span class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-2 ml-auto mr-0">
          <i class="fas fa-shield-alt text-emerald-500"></i> مستند رسمي معتمد
        </span>
        <button @click="showDetailsModal = false" class="px-4 h-9 text-xs font-bold text-slate-500">إغلاق</button>
        <button @click="printSaleDetails" class="px-6 h-9 bg-blue-600 text-white rounded-md text-xs font-bold shadow-lg shadow-blue-900/20"><i class="fas fa-print ml-1.5"></i> طباعة الفاتورة</button>
      </template>
    </BaseModal>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import BaseModal from '@/components/BaseModal.vue';
import { useRoute } from 'vue-router';
import { useHistoryFilters } from '@/composables/useHistoryFilters';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useBranchIsolation } from '@/composables/useBranchIsolation';
import { useBreadcrumb } from '@/composables/useBreadcrumb';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useSalesStore } from '@/stores/sales/salesStore';
import { useSettingsStore } from '@/stores/settings/settingsStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useBootstrapStore } from '@/stores/bootstrap';
import AlertService from '@/services/AlertService';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { getLocalDateISO } from '@/utils/date';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { getBuilderByTemplate } from '@/utils/printTemplates';
import { printDocument } from '@/utils/PrintService';
import PageHeader from '@/components/PageHeader.vue';

const { showToast } = useToast();
const { showLoader, hideLoader } = useLoader();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const branchIsolation = useBranchIsolation();
const salesStore = useSalesStore();
const settingsStore = useSettingsStore();
const customerStore = useCustomerStore();
const paymentStore = usePaymentStore();
const bootstrapStore = useBootstrapStore();
const route = useRoute();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { breadcrumb } = useBreadcrumb();
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);

const taxRate = ref(0);
const taxRateDisplay = computed(() => taxRate.value.toFixed(0));

const sales = ref([]);
const paymentMethods = computed(() => paymentStore.paymentMethods);
const customers = computed(() => customerStore.customers);
const branches = computed(() => branchStore.branches);

const selectedBranch = computed(() => branchStore.selectedBranchId);

// ✅ يتتبع الاختيار اليدوي للفرع — لا يعتمد على localStorage مباشرة
// لأن fetchBranches() يكتب default branch في localStorage تلقائياً عند أول دخول
const userChoseBranch = ref(
  localStorage.getItem('selectedBranchId') !== null
  && localStorage.getItem('selectedBranchId') !== 'all'
);
const hasExplicitBranchSelection = computed(() => userChoseBranch.value && branchStore.selectedBranchId !== null);

const onBranchChange = (newBranchId) => {
  branchStore.setSelectedBranch(newBranchId);
  userChoseBranch.value = (newBranchId !== null && newBranchId !== '' && newBranchId !== 'all');
  filters.page.value = 1;
  fetchSalesHistory(true);  // ✅ force=true لإعادة جلب البيانات عند تغيير الفرع
};

const cashMethodId = computed(() => {
  const m = (paymentMethods.value || []).find(pm => String(pm.kind || '').toLowerCase() === 'cash');
  return m?.id || null;
});

const statusOptions = [
  { value: '', label: 'كل الحالات' },
  { value: 'paid', label: 'مدفوعة' },
  { value: 'partial', label: 'جزئي' },
  { value: 'unpaid', label: 'مستحقة' }
];

const filters = useHistoryFilters('sales_hist_filters');
filters.loadFromLocalStorage();

const filteredCustomers = computed(() => {
  const q = (filters.customerSearch.value || '').toLowerCase();
  const list = customers.value || [];
  if (!q) return list.slice(0, 50);
  return list.filter(c => String(c.name || c.customer_name || '').toLowerCase().includes(q)).slice(0, 50);
});

const totalPages = computed(() => Math.max(1, Math.ceil(filters.total.value / filters.perPage.value)));

const isLoadingList = ref(false);
const isLoadingDetails = ref(false);
const isMarkingPaid = ref(false);
const isDataStale = ref(false);
const lastSuccessfulUpdate = ref(null);
let listAbortCtrl = null;
let detailsAbortCtrl = null;
let searchTimer = null;

const dateFromRefLocal = ref(null);
const dateToRefLocal = ref(null);

const allSelected = computed(() => sales.value.length > 0 && filters.selectedIds.value.length === sales.value.length);
const toggleSelectAll = (e) => { 
  filters.toggleSelectAll(e?.target?.checked ? sales.value : []);
};

const subTotalNet = computed(() => {
  if (!selectedSale.value?.items) return 0;
  return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
});

const totalDiscount = computed(() => {
  if (!selectedSale.value?.items) return 0;
  return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.discount_value) || 0), 0);
});

const finalNetTotal = computed(() => {
  if (selectedSale.value?.sale?.net_total_amount !== undefined) return parseFloat(selectedSale.value.sale.net_total_amount) || 0;
  if (selectedSale.value?.items) return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.net_total) || 0), 0);
  return 0;
});

const selectedSalePaymentMethodId = computed(() => {
  const s = selectedSale.value || {};
  return s.payment_method_id || (s.payments && s.payments[0]?.payment_method_id) || null;
});

const showDetailsModal = ref(false);
const selectedSale = ref(null);

watch(showDetailsModal, (val) => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = val ? 'hidden' : '';
  }
});

const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return '';
  const date = dateTimeString instanceof Date ? dateTimeString : new Date(dateTimeString);
  return date.toLocaleString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getStatusLabel = (status) => {
  const map = {
    paid: 'مدفوعة',
    settled: 'مسددة',
    unpaid: 'غير مدفوعة',
    partial: 'مدفوعة جزئياً',
    pending_payment: 'قيد الدفع',
    returned: 'مرتجعة',
    settled_by_return: 'مسددة',
    closed_by_return: 'مرتجعة',
    settled_by_credit: 'مسوّاة بمرتجع',
    settled_mixed: 'مسوّاة نقدي/إشعار دائن',
  };
  return map[status] || status;
};

const getStatusMessage = (status) => {
  const messages = {
    settled_by_return: 'مسددة بإشعار دائن ناتج عن مرتجع.',
    settled_by_credit: 'مسددة بمرتجع.',
    settled_mixed: 'مسددة جزئياً نقدي وجزئياً بإشعار دائن.',
  };
  return messages[status] || null;
};

const getDynamicStatusClass = (status) => {
  const classMap = {
    paid: 'status-paid-v2',
    settled: 'status-paid-v2',
    unpaid: 'status-unpaid-v2',
    partial: 'status-partial-v2',
    pending_payment: 'status-partial-v2',
    returned: 'status-unpaid-v2',
    settled_by_return: 'status-paid-v2',
    closed_by_return: 'status-unpaid-v2',
    settled_by_credit: 'status-paid-v2',
    settled_mixed: 'status-paid-v2',
  };
  return classMap[status] || 'status-partial-v2';
};

const getSaleGross = (s) => {
  if (!s) return 0;
  const net = parseFloat(s.net_total_amount ?? s.total_amount ?? 0) || 0;
  if (s.grand_total !== undefined && s.grand_total !== null) return parseFloat(s.grand_total) || net;
  const tax = (s.tax_amount !== undefined && s.tax_amount !== null) ? (parseFloat(s.tax_amount) || 0) : (net * (taxRate.value / 100));
  return net + tax;
};

const getPaymentMethodName = (id) => {
  if (!id) return 'غير محددة';
  const m = paymentMethods.value.find(p => Number(p.id) === Number(id));
  return m?.name || 'غير محددة';
};

const getAgeBadge = (sale) => {
  if (!sale || ['paid', 'returned', 'settled_by_return', 'closed_by_return', 'settled_by_credit', 'settled_mixed'].includes(sale.dynamic_status)) return null;
  const base = sale.due_date || sale.created_at;
  if (!base) return null;
  const days = Math.max(0, Math.floor((Date.now() - new Date(base).getTime()) / (1000 * 60 * 60 * 24)));
  if (days <= 7) return { cls: 'bg-emerald-50 text-emerald-600 border-emerald-100', label: `${days} يوم` };
  if (days <= 30) return { cls: 'bg-amber-50 text-amber-600 border-amber-100', label: `${days} يوم` };
  return { cls: 'bg-rose-50 text-rose-600 border-rose-100', label: `${days} يوم` };
};

const fetchSalesHistory = async (forceRefresh = false) => {
  if (listAbortCtrl) listAbortCtrl.abort();
  listAbortCtrl = new AbortController();
  isLoadingList.value = true;
  showLoader();
  try {
    const params = filters.getApiParams({ includeTotals: true, force: forceRefresh });
    if (!isExempt.value) {
      try {
        const wid = branchIsolation.getRequiredBranchId();
        params.branchId = String(wid);
      } catch (e) {
        sales.value = []; filters.total.value = 0; filters.kpiSum.value = 0; filters.kpiTax.value = null; filters.kpiDiscount.value = null;
        isDataStale.value = false;
        showToast(e.message || 'لم يتم تعيين مخزن لحسابك.', 'error'); return;
      }
    } else if (branchStore.selectedBranchId) {
      params.branchId = String(branchStore.selectedBranchId);
    }
    
    // ✅ Log: تحقق من القيم قبل الإرسال
    console.log('[fetchSalesHistory] API Call params:', {
      branchId: params.branchId,
      selectedBranchId: branchStore.selectedBranchId,
      isExempt: isExempt.value,
      paramsPayload: params
    });
    
    const response = await salesStore.fetchSalesList(params);
    const resData = response?.data || response;
    if (resData) {
      sales.value = resData.items || [];
      filters.total.value = resData.total || 0;
      filters.kpiSum.value = resData.kpiSum || 0;
      filters.kpiTax.value = resData.kpiTax ?? null;
      filters.kpiDiscount.value = resData.kpiDiscount ?? null;
      isDataStale.value = false;
      lastSuccessfulUpdate.value = new Date();
    } else {
      sales.value = []; filters.total.value = 0; filters.kpiSum.value = 0; filters.kpiTax.value = null; filters.kpiDiscount.value = null;
      isDataStale.value = false;
    }
  } catch (e) {
    const isAborted = e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.message === 'canceled';
    if (!isAborted) {
      // احتفظ بالبيانات القديمة مع تنبيه واضح
      isDataStale.value = true;
      showToast('⚠️ فشل تحديث البيانات. البيانات المعروضة قد تكون قديمة. حاول مرة أخرى.', 'warning');
    }
  } finally { isLoadingList.value = false; hideLoader(); }
};

const viewSaleDetails = async (saleId) => {
  if (detailsAbortCtrl) detailsAbortCtrl.abort();
  detailsAbortCtrl = new AbortController();
  isLoadingDetails.value = true;
  showLoader();
  try {
    const payload = await salesStore.fetchSaleDetails(saleId);
    const saleData = payload?.data || payload;
    selectedSale.value = (saleData && saleData.sale) ? saleData.sale : saleData;
    showDetailsModal.value = true;
  } catch (e) {
    const isAborted = e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.message === 'canceled';
    if (!isAborted) showToast('فشل في تحميل تفاصيل الفاتورة', 'error');
  } finally { isLoadingDetails.value = false; hideLoader(); }
};

const toggleSort = (key) => {
  if (filters.sortKey.value === key) filters.sortAsc.value = !filters.sortAsc.value;
  else { filters.sortKey.value = key; filters.sortAsc.value = true; }
  try { localStorage.setItem('sales_hist_sortKey', filters.sortKey.value); localStorage.setItem('sales_hist_sortAsc', filters.sortAsc.value ? '1' : '0'); } catch {}
  filters.page.value = 1; fetchSalesHistory();
};

const markSelectedAsPaid = async () => {
  if (!filters.selectedIds.value.length || isMarkingPaid.value) return;
  const rows = (sales.value || []).filter(s => new Set(filters.selectedIds.value).has(s.id));
  const payable = rows.filter(s => {
    const net = parseFloat(s.net_total_amount ?? s.total_amount ?? 0);
    const paid = parseFloat(s.paid_amount ?? 0);
    return s.status !== 'paid' && (net - paid > 0.0001);
  });
  if (!payable.length) { showToast('لا توجد فواتير قابلة للتحصيل ضمن المحدد', 'info'); return; }
  const confirmed = await AlertService.confirm(`سيتم تعليم ${payable.length} فاتورة كمسددة. هل تريد المتابعة؟`, 'تسديد الفواتير');
  if (!confirmed) return;
  isMarkingPaid.value = true; showLoader();
  let ok = 0, fail = 0;
  for (const s of payable) {
    try {
      const remaining = Math.max(0, parseFloat(s.net_total_amount ?? s.total_amount ?? 0) - parseFloat(s.paid_amount ?? 0));
      if (remaining <= 0) { ok++; continue; }
      const res = await salesStore.payDebt({ sale_id: s.id, customer_id: s.customer_id, amount: remaining, payment_method_id: cashMethodId.value ?? (paymentMethods.value[0]?.id || 1) });
      if (res?.status === 'success') ok++; else fail++;
    } catch { fail++; }
  }
  hideLoader(); isMarkingPaid.value = false; showToast(`تمت معالجة ${ok} بنجاح، وفشل ${fail}`, fail ? 'warning' : 'success');
  filters.clearSelection(); fetchSalesHistory();
};

const exportSelectedCsv = () => {
  const rows = (sales.value || []).filter(s => new Set(filters.selectedIds.value).has(s.id));
  if (!rows.length) { showToast('لا توجد فواتير محددة للتصدير', 'info'); return; }
  
  const headers = ['رقم الفاتورة', 'التاريخ', 'العميل', 'عدد الأصناف', 'الإجمالي', 'الحالة', 'طريقة الدفع'];
  const data = rows.map(s => [
    s.invoice_number || `#${s.id}`,
    formatDateTime(s.created_at),
    s.customer_name || 'عميل نقدي',
    s.total_items,
    formatPrice(getSaleGross(s)),
    getStatusLabel(s.dynamic_status),
    getPaymentMethodName(s.payment_method_id)
  ]);
  
  const csvContent = [
    headers.join(','),
    ...data.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
  ].join('\n');
  
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `sales-export-${new Date().getTime()}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showToast(`تم تصدير ${rows.length} فاتورة بنجاح`, 'success');
};

const printSelected = () => {
  const rows = (sales.value || []).filter(s => new Set(filters.selectedIds.value).has(s.id));
  if (!rows.length) return;
  const win = window.open('', '_blank');
  const totalSelected = rows.reduce((sum, s) => sum + parseFloat(getSaleGross(s) || 0), 0);
  const html = `<!DOCTYPE html><html lang="ar"><head><meta charset="utf-8"/><title>طباعة فواتير</title><style>body{font-family:Tahoma,Arial,sans-serif;direction:rtl}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:center}th{background:#f5f5f5}</style></head><body><h3>فواتير مختارة</h3><table><thead><tr><th>#</th><th>التاريخ</th><th>العميل</th><th>الأصناف</th><th>الإجمالي</th><th>الحالة</th></tr></thead><tbody>${rows.map(s => `<tr><td>${s.id}</td><td>${formatDateTime(s.created_at)}</td><td>${s.customer_name || 'عميل نقدي'}</td><td>${s.total_items}</td><td>${formatPrice(getSaleGross(s))}</td><td>${getStatusLabel(s.dynamic_status)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="3">الملخص</th><th>${rows.length} فاتورة</th><th>${formatPrice(totalSelected)}</th><th></th></tr></tfoot></table></body></html>`;
  win.document.open(); win.document.write(html); win.document.close();
  win.onload = () => { win.print(); setTimeout(() => { try { win.close(); } catch {} }, 300); };
};

const getSelectedPrintTemplate = () => {
  let t = (localStorage.getItem('pos_print_template') || '').toLowerCase();
  if (t === 'thermal') t = 'thermal-compact';
  if (t === 'a4') t = 'a4-simple';
  return new Set(['thermal-compact', 'thermal-detailed', 'a4-simple', 'a4-professional']).has(t) ? t : 'thermal-compact';
};

const printSaleDetails = async () => {
  if (!selectedSale.value) return;
  const html = getBuilderByTemplate(getSelectedPrintTemplate())(selectedSale.value);
  await printDocument(html);
};

// isMounting flag: يمنع الـ watches من إطلاق fetchSalesHistory() أثناء onMounted
let isMounting = true;

watch([() => filters.dateFrom.value, () => filters.dateTo.value, () => filters.statusFilter.value, () => filters.customerFilter.value, () => filters.perPage.value], () => {
  if (isMounting) return;
  try {
    localStorage.setItem('sales_hist_dateFrom', filters.dateFrom.value || '');
    localStorage.setItem('sales_hist_dateTo', filters.dateTo.value || '');
    localStorage.setItem('sales_hist_status', filters.statusFilter.value || '');
    localStorage.setItem('sales_hist_customer', filters.customerFilter.value || '');
    localStorage.setItem('sales_hist_perPage', filters.perPage.value.toString());
  } catch {}
  filters.page.value = 1; fetchSalesHistory();
});

watch(() => filters.page.value, () => {
  if (isMounting) return;
  try { localStorage.setItem('sales_hist_page', filters.page.value.toString()); } catch {}
  fetchSalesHistory();
});

// selectedBranch مُزال من watch — تغيير الفرع يُعالج عبر onBranchChange() مباشرة
// إبقاؤه يُسبب race condition: fetchBranches() تُغيّر selectedBranchId أثناء onMounted فيُطلق watch مبكراً

watch(() => filters.searchQuery.value, () => {
  if (isMounting) return;
  if (searchTimer) clearTimeout(searchTimer);
  filters.page.value = 1; searchTimer = setTimeout(() => fetchSalesHistory(), 400);
});

watch(() => filters.customerFilter.value, (nv) => {
  const selected = (customers.value || []).find(c => String(c.id) === String(nv));
  filters.customerSearch.value = selected ? (selected.name || selected.customer_name || '') : '';
});

watch(() => route.query.id, async (nv) => {
  if (Number(nv || 0) > 0) { try { await viewSaleDetails(Number(nv)); } catch {} }
});

onMounted(async () => {
  try {
    const data = await bootstrapStore.fetchPaymentsData();
    if (data.customers) customerStore.customers = data.customers;
    if (data.paymentMethods) paymentStore.paymentMethods = data.paymentMethods;
    await Promise.all([fetchSettings(), settingsStore.fetchTaxSettings().catch(() => {}), ensureExemptionLoaded().catch(() => {})]);
  } catch (bootstrapError) {
    await Promise.all([fetchSettings(), settingsStore.fetchTaxSettings().catch(() => {}), ensureExemptionLoaded().catch(() => {}), customerStore.fetchCustomers().catch(() => {}), paymentStore.fetchPaymentMethods().catch(() => {})]);
  }
  try { taxRate.value = settingsStore.isTaxEnabled.value ? settingsStore.getTaxRate.value : 0; } catch {}
  
  // ✅ FIX: تهيئة/استعادة branch context قبل أول API call
  // سجّل ما إذا كان المستخدم اختار فرعاً في جلسة سابقة (قبل fetchBranches يكتب default)
  const hadPriorBranchChoice = localStorage.getItem('selectedBranchId') !== null
                                && localStorage.getItem('selectedBranchId') !== 'all';
  try {
    branchStore.loadFromStorage();
    if (!branchStore.branches || branchStore.branches.length === 0) {
      await branchStore.fetchBranches();
    }
  } catch (err) {
    console.error('[SalesHistory] Failed to initialize branches:', err);
    showToast('فشل في تحميل قائمة الفروع', 'error');
    return;
  }
  // بعد fetchBranches: أعد تعيين الـ flag بما كان موجوداً قبل الكتابة
  userChoseBranch.value = hadPriorBranchChoice;
  
  // التحقق من أن selectedBranchId جاهز فعلياً
  let resolvedBranchId = branchStore.selectedBranchId;
  if (!isExempt.value) {
    try {
      resolvedBranchId = branchIsolation.getRequiredBranchId();
    } catch (err) {
      console.error('[SalesHistory] Failed to resolve branch ID:', err);
      showToast(err.message || 'لم يتم تعيين الفرع', 'error');
      return;
    }
  }
  
  console.log('[SalesHistory] Before first API call:', {
    selectedBranchId: branchStore.selectedBranchId,
    selectedBranch: branchStore.selectedBranch?.name || null,
    isExempt: isExempt.value,
    resolvedBranchId,
    branchesCount: branchStore.branches.length
  });
  
  const selected = customers.value.find(c => String(c.id) === String(filters.customerFilter.value));
  filters.customerSearch.value = selected ? (selected.name || selected.customer_name || '') : '';
  filters.page.value = 1;
  isMounting = false;
  fetchSalesHistory();
  if (Number(route.query.id || 0) > 0) { try { await viewSaleDetails(Number(route.query.id)); } catch {} }
});

onUnmounted(() => {
  if (listAbortCtrl) listAbortCtrl.abort();
  if (detailsAbortCtrl) detailsAbortCtrl.abort();
  if (searchTimer) clearTimeout(searchTimer);
  if (typeof window !== 'undefined') document.body.style.overflow = '';
});
</script>

<style scoped>
@keyframes loading { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

.filter-input {
  @apply h-9 w-full bg-white border border-slate-200 rounded-md px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all;
}

.status-paid-v2 { @apply bg-emerald-50 text-emerald-600 border-emerald-100; }
.status-unpaid-v2 { @apply bg-rose-50 text-rose-600 border-rose-100; }
.status-partial-v2 { @apply bg-amber-50 text-amber-600 border-amber-100; }

.pagination-btn-v2 {
  @apply w-8 h-8 flex items-center justify-center rounded border border-slate-200 bg-white text-slate-500 hover:text-blue-600 hover:border-blue-200 disabled:opacity-40 transition-all;
}

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translate(-50%, 20px); }

@keyframes modalIn { from { opacity: 0; transform: scale(0.98) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.animate-modalIn { animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
</style>