<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-rose-600 rounded-2xl flex items-center justify-center shadow-xl shadow-rose-100 text-white">
          <i class="fas fa-undo-alt text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none">سجل المرتجعات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع وإدارة عمليات إرجاع المبيعات والمشتريات</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="filters.showFilters.value = !filters.showFilters.value" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-2', filters.showFilters.value ? 'bg-rose-600 text-white shadow-lg shadow-rose-200' : 'bg-transparent text-slate-600 hover:bg-slate-50']">
          <i class="fas fa-filter"></i>
          {{ filters.showFilters.value ? 'إخفاء الفلاتر' : 'البحث والتصفية' }}
        </button>
      </div>
    </div>

    <!-- Filters Panel (Collapsible) - ABOVE KPI -->
    <transition name="slide">
      <div v-if="filters.showFilters.value" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible transition-all">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="relative group">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
            <div class="relative">
              <input v-model="filters.searchQuery.value" type="text" class="form-input-modern pr-10" placeholder="ابحث بـ: رقم المرتجع أو الفاتورة..." />
              <i class="fas fa-search absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-rose-500 transition-colors pointer-events-none"></i>
              <button v-if="filters.searchQuery.value" @click="filters.searchQuery.value = ''" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح البحث">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="dateFromRef" type="date" v-model="filters.dateFrom.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateFromRef.showPicker()"
              ></i>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">إلى تاريخ</label>
            <div class="relative">
              <input ref="dateToRef" type="date" v-model="filters.dateTo.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateToRef.showPicker()"
              ></i>
            </div>
          </div>

          <div class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">نوع المرتجع</label>
            <select v-model="type" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option value="sales">مرتجعات المبيعات</option>
              <option value="purchases">مرتجعات المشتريات</option>
            </select>
            <i class="fas fa-exchange-alt absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
          <div class="relative md:col-span-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">
              {{ type === 'sales' ? 'العميل المستهدف' : 'المورد المستهدف' }}
            </label>
            <div class="relative">
              <input
                type="text"
                v-model="filters.customerSearch.value"
                class="form-input-modern pr-10 font-bold text-sm"
                :placeholder="type === 'sales' ? 'ابحث عن عميل...' : 'ابحث عن مورد...'"
                @focus="filters.showCustomerDropdown.value = true"
                @blur="hidePartyDropdown"
                @input="filters.showCustomerDropdown.value = true"
              />
              <i class="fas fa-user-circle absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
              <button v-if="filters.customerFilter.value" @click="filters.clearCustomerFilter();" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح التصفية">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>

              <div v-if="filters.showCustomerDropdown.value" class="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-60 overflow-auto py-2">
                <button @mousedown.prevent="filters.clearCustomerFilter();" class="w-full text-right px-5 py-3 text-xs font-black text-rose-600 hover:bg-slate-50 border-b border-slate-50 transition-colors">
                  {{ type === 'sales' ? 'كل العملاء' : 'كل الموردين' }}
                </button>
                <button v-for="p in filteredParties" :key="p.id" @mousedown.prevent="selectParty(p)" class="w-full text-right px-5 py-3 text-xs font-bold text-slate-700 hover:bg-rose-50 transition-colors">
                  {{ p.name || p.customer_name || p.supplier_name }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="isExempt" class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع / المستودع</label>
            <select v-model="filters.selectedBranch.value" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option value="">كل الفروع</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name || w.branch_name }}</option>
            </select>
            <i class="fas fa-warehouse absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>

          <button @click="resetFilters" class="h-[46px] w-full rounded-2xl bg-slate-100 text-slate-600 font-black text-xs hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-sync-alt"></i> إعادة تعيين
          </button>
        </div>

        <!-- Filter Chips -->
        <div class="flex flex-wrap gap-2 mt-6 pt-6 border-t border-slate-50" v-if="filters.hasActiveFilters.value || filters.customerFilter.value || (isExempt && filters.selectedBranch.value)">
          <div class="flex items-center gap-2 w-full">
            <div class="flex items-center gap-2 text-rose-600 bg-rose-50 px-3 py-2 rounded-lg border border-rose-100">
              <i class="fas fa-check-circle text-xs"></i>
              <span class="text-[10px] font-black uppercase tracking-wider">تصفية نشطة</span>
            </div>
            <div class="flex flex-wrap gap-2 flex-1">
              <span v-if="filters.searchQuery.value" class="filter-chip group">
                <i class="fas fa-search ml-1 text-[9px]"></i>{{ filters.searchQuery.value }}
                <i @click="filters.searchQuery.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.dateFrom.value" class="filter-chip group">
                <i class="fas fa-calendar-left ml-1 text-[9px]"></i>{{ filters.dateFrom.value }}
                <i @click="filters.dateFrom.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.dateTo.value" class="filter-chip group">
                <i class="fas fa-calendar-right ml-1 text-[9px]"></i>{{ filters.dateTo.value }}
                <i @click="filters.dateTo.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.customerFilter.value" class="filter-chip group">
                <i :class="['ml-1 text-[9px]', type === 'sales' ? 'fas fa-user' : 'fas fa-briefcase']"></i>{{ filters.customerSearch.value }}
                <i @click="filters.clearCustomerFilter();" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="isExempt && filters.selectedBranch.value" class="filter-chip group">
                <i class="fas fa-warehouse ml-1 text-[9px]"></i>{{ (branches.find(w=>String(w.id)===String(filters.selectedBranch.value))?.name) || '...' }}
                <i @click="filters.selectedBranch.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div>
            <p class="kpi-label">عدد المرتجعات</p>
            <p class="kpi-value text-slate-800">{{ kpiCount }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي القيمة</p>
            <p class="kpi-value text-rose-600">{{ formatPrice(kpiSum) }}</p>
          </div>
        </div>
      </div>

      <div v-if="kpiTax != null" class="kpi-card group border-l-4 border-l-indigo-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="fas fa-percent"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الضريبة</p>
            <p class="kpi-value text-indigo-600">{{ formatPrice(kpiTax) }}</p>
          </div>
        </div>
      </div>

      <div v-if="kpiDiscount != null" class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
            <i class="fas fa-tags"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الخصومات</p>
            <p class="kpi-value text-amber-600">{{ formatPrice(kpiDiscount) }}</p>
          </div>
        </div>
      </div>
    </div>



    <!-- Main Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">رقم المرتجع</th>
              <th class="px-4 py-5">رقم الفاتورة</th>
              <th class="px-4 py-5">التاريخ</th>
              <th class="px-4 py-5">قيمة المرتجع</th>
              <th class="px-4 py-5 text-center">النوع</th>
              <th class="px-4 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">

            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoadingReturns">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else-if="!rows.length" class="text-center">
              <td colspan="6" class="py-20">
                <div class="flex flex-col items-center opacity-20">
                  <i class="fas fa-box-open text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد مرتجعات مسجلة</p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr v-else v-for="r in rows" :key="r.id" class="hover:bg-rose-50/20 transition-all group">
              <td class="px-6 py-4 font-black text-slate-800 font-mono tracking-wider">
                {{ r.return_number || ('RET-' + String(r.id).padStart(4, '0')) }}
              </td>
              <td class="px-4 py-4 text-xs font-bold text-slate-400 font-mono">
                {{ r.invoice_number || '-' }}
              </td>
              <td class="px-4 py-4 text-xs font-bold text-slate-500 tracking-tighter">
                {{ formatDate(r.return_date || r.created_at) }}
              </td>
              <td class="px-4 py-4">
                <span class="font-black text-rose-600 text-base leading-none">
                  {{ formatPrice(r.grand_total || r.total_amount || r.total || 0) }}
                </span>
              </td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', type === 'sales' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700']">
                  {{ type === 'sales' ? 'مرتجع بيع' : 'مرتجع شراء' }}
                </span>
              </td>
              <td class="px-4 py-4 text-center">
                <button @click="viewReturnDetails(r)" :disabled="isLoadingDetails" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95 disabled:opacity-50" title="عرض التفاصيل">
                  <i class="fas fa-eye text-sm"></i>
                </button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

      <transition name="modal">
        <div v-if="showDetailsModal && selectedReturn" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm" @click.self="showDetailsModal = false">
          <div class="bg-white w-full max-w-3xl rounded-[2.5rem] shadow-2xl overflow-hidden max-h-[90vh] animate-modalIn border border-white">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">تفاصيل المرتجع {{ selectedReturn.return_number || ('RET-' + String(selectedReturn.id).padStart(4, '0')) }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">{{ type === 'sales' ? 'مرتجع بيع' : 'مرتجع شراء' }}</p>
              </div>
              <button @click="showDetailsModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>
            <div class="p-8 overflow-y-auto custom-scroll">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">فاتورة مرتبطة</p>
                  <p class="text-sm font-bold text-slate-800">{{ selectedReturn.invoice_number || '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">التاريخ</p>
                  <p class="text-sm font-bold text-slate-800">{{ formatDate(selectedReturn.return_date || selectedReturn.created_at) }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">القيمة</p>
                  <p class="text-sm font-bold text-rose-600">{{ formatPrice(selectedReturn.grand_total || selectedReturn.total_amount || selectedReturn.total || 0) }}</p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الطرف</p>
                  <p class="text-sm font-bold text-slate-800">
                    {{ type === 'sales' ? (selectedReturn.customer_name || selectedReturn.customer || '-') : (selectedReturn.supplier_name || selectedReturn.supplier || '-') }}
                  </p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الحالة</p>
                  <p class="text-sm font-bold text-slate-800">{{ selectedReturn.status || selectedReturn.state || '-' }}</p>
                </div>
              </div>

              <div v-if="selectedReturn.notes || selectedReturn.remarks" class="bg-slate-50 p-4 rounded-2xl border border-slate-100 mb-6">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">ملاحظات</p>
                <p class="text-sm text-slate-600">{{ selectedReturn.notes || selectedReturn.remarks }}</p>
              </div>

              <div v-if="selectedReturn.items && selectedReturn.items.length" class="border border-slate-100 rounded-3xl overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-100">
                      <th class="px-4 py-4 uppercase">الصنف</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-4 py-4 text-right">السعر</th>
                      <th class="px-4 py-4 text-right">الإجمالي</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50">
                    <tr v-for="item in selectedReturn.items" :key="item.id || item.product_id" class="hover:bg-slate-50/50 transition-colors">
                      <td class="px-4 py-4 font-bold text-slate-800">{{ item.product_name || item.name || item.title || 'غير معروف' }}</td>
                      <td class="px-4 py-4 text-center font-black text-slate-500">{{ item.quantity || item.qty || '-' }}</td>
                      <td class="px-4 py-4 text-right font-bold text-slate-700">{{ formatPrice(item.sale_price || item.unit_price || item.price || 0) }}</td>
                      <td class="px-4 py-4 text-right font-black text-slate-900">{{ formatPrice(item.total || item.net_total || item.amount || 0) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex items-center justify-end gap-3">
              <button @click="showDetailsModal = false" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-500 hover:bg-white hover:text-rose-500 transition-all">إغلاق</button>
            </div>
          </div>
        </div>
      </transition>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          صفحة <span class="text-slate-800">{{ filters.page.value }}</span> من <span class="text-slate-800">{{ totalPages }}</span>
          (إجمالي <span class="text-slate-800">{{ filters.total.value }}</span> مرتجع)
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400">النتائج:</span>
            <select v-model.number="filters.perPage.value" class="h-10 text-xs font-black border-slate-200 rounded-xl bg-white px-3 outline-none border">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-center gap-1">
            <button @click="filters.previousPage()" :disabled="filters.page.value<=1" class="pagination-btn">
              <i class="fas fa-angle-right"></i> السابق
            </button>
            <button @click="filters.nextPage(totalPages)" :disabled="filters.page.value>=totalPages" class="pagination-btn">
              التالي <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useHistoryFilters } from '@/composables/useHistoryFilters';
import { useLoader } from '@/composables/useLoader';
import { useToast } from '@/composables/useToast';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { useReturnStore } from '@/stores/return/returnStore';
import { useBootstrapStore } from '@/stores/bootstrap';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';

// ─── Composables ──────────────────────────────────────────────────────────────
const { showLoader, hideLoader } = useLoader();
const { showToast } = useToast();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const customerStore = useCustomerStore();
const returnStore = useReturnStore();
const supplierStore = useSupplierStore();
const bootstrapStore = useBootstrapStore();

const route = useRoute();
const detailsAbortCtrl = ref(null);
const showDetailsModal = ref(false);
const selectedReturn = ref(null);

// ─── Use History Filters Composable (موحد مع SalesHistory) ─────────────────────
const filters = useHistoryFilters('returns_hist_filters');
filters.loadFromLocalStorage();

// ─── Type Toggle (منفصل عن useHistoryFilters) ───────────────────────────────────
const type = ref('sales');

// ─── State المحلية ───────────────────────────────────────────────────────────────
const rows = ref([]);
const isLoadingReturns = ref(false);
const isLoadingDetails = ref(false);

// ─── Branch Filter من useHistoryFilters ──────────────────────────────────────────
const branches = computed(() => branchStore.branches);

// ─── Party Filter (Customer / Supplier) ──────────────────────────────────────────
const customers = computed(() => customerStore.customers);
const suppliers = computed(() => supplierStore.suppliers);

const filteredParties = computed(() => {
  const list = type.value === 'sales' ? (customers.value || []) : (suppliers.value || []);
  const q = (filters.customerSearch.value || '').toLowerCase();
  const getName = type.value === 'sales'
    ? (c => c.name || c.customer_name || '')
    : (s => s.name || s.supplier_name || '');
  if (!q) return list.slice(0, 50);
  return list.filter(item => String(getName(item)).toLowerCase().includes(q)).slice(0, 50);
});

const selectParty = (p) => {
  filters.setCustomerFilter(p.id, p.name || p.customer_name || p.supplier_name || '');
};

// ✅ blur مع delay للسماح بالـ mousedown
const hidePartyDropdown = () => filters.hideCustomerDropdown();

// ─── KPI ──────────────────────────────────────────────────────────────────────
const kpiCount = computed(() => filters.total.value || rows.value.length);
const kpiSum = computed(() => rows.value.reduce((s, r) => s + parseFloat(r.grand_total || r.total_amount || r.total || 0), 0));
const kpiTax = computed(() => {
  const any = rows.value.some(r => r.tax_amount != null);
  return any ? rows.value.reduce((s, r) => s + parseFloat(r.tax_amount || 0), 0) : null;
});
const kpiDiscount = computed(() => {
  const any = rows.value.some(r => r.discount_amount != null || r.discount_value != null);
  return any ? rows.value.reduce((s, r) => s + parseFloat((r.discount_amount ?? r.discount_value) || 0), 0) : null;
});

const totalPages = computed(() => Math.max(1, Math.ceil((filters.total.value || 0) / filters.perPage.value)));

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';

// ─── API ──────────────────────────────────────────────────────────────────────
let returnsAbortCtrl = null; // ✅ AbortController

const fetchReturns = async () => {
  // Cancel previous request
  if (returnsAbortCtrl) returnsAbortCtrl.abort();
  returnsAbortCtrl = new AbortController();
  const currentCtrl = returnsAbortCtrl;

  isLoadingReturns.value = true;
  try {
    // ✅ Set branch filter based on user type
    if (!isExempt.value) {
      // Regular user - must have branch assigned
      const wid = authStore?.user?.branch_id;
      if (!wid) {
        rows.value = [];
        filters.total.value = 0;
        showToast('لم يتم تعيين الفرع لحسابك.', 'error');
        return;
      }
      filters.selectedBranch.value = String(wid);
    }
    // If admin (isExempt) and no branch selected → show all branches (branchId will be null)

    // استخدم getApiParams من useHistoryFilters
    const params = filters.getApiParams({
      type: type.value,
      partyId: filters.customerFilter.value || undefined
    });

    const response = await returnStore.fetchReturnsList(params);

    // Verify controller hasn't changed
    if (currentCtrl !== returnsAbortCtrl) return;

    if (response?.status === 'success') {
      const resData = response.data;
      const list = Array.isArray(resData) ? resData : (resData?.items || []);
      rows.value = list;
      filters.total.value = resData?.total || list.length;
    } else {
      rows.value = [];
      filters.total.value = 0;
    }
  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError' || e?.code === 'ERR_CANCELED';
    if (!isAborted) {
      showToast('فشل في تحميل سجل المرتجعات', 'error');
      rows.value = [];
      filters.total.value = 0;
    }
  } finally {
    if (currentCtrl === returnsAbortCtrl) isLoadingReturns.value = false;
    hideLoader();
  }
};

const resetFilters = () => {
  filters.resetFilters();
  type.value = 'sales';
  fetchReturns();
};

// previousPage و nextPage مُدارتان من filters.previousPage/nextPage من useHistoryFilters

const viewReturnDetails = async (returnRow) => {
  if (!returnRow || !returnRow.id) return;
  if (detailsAbortCtrl.value) detailsAbortCtrl.value.abort();
  detailsAbortCtrl.value = new AbortController();
  const currentCtrl = detailsAbortCtrl.value;

  isLoadingDetails.value = true;
  showLoader();
  try {
    const response = await returnStore.fetchReturnDetails(returnRow.id, type.value, { signal: currentCtrl.signal });
    if (currentCtrl !== detailsAbortCtrl.value) return;

    if (response?.status === 'success') {
      selectedReturn.value = response.data || response;
      showDetailsModal.value = true;
    } else {
      showToast('فشل في تحميل تفاصيل المرتجع', 'error');
    }
  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError' || e?.code === 'ERR_CANCELED';
    if (!isAborted) {
      showToast('فشل في تحميل تفاصيل المرتجع', 'error');
    }
  } finally {
    if (currentCtrl === detailsAbortCtrl.value) isLoadingDetails.value = false;
    hideLoader();
  }
};

// ─── Watchers ─────────────────────────────────────────────────────────────────

// ✅ debounce على searchQuery بدل fetch فوري
let searchDebounceTimer = null;
watch(filters.searchQuery, () => {
  clearTimeout(searchDebounceTimer);
  filters.page.value = 1;
  searchDebounceTimer = setTimeout(fetchReturns, 400);
});

// الفلاتر الأخرى فورية (من useHistoryFilters)
watch([filters.customerFilter, type, filters.dateFrom, filters.dateTo, filters.selectedBranch], () => {
  filters.page.value = 1;
  fetchReturns();
});

// الصفحة منفصلة
watch(filters.page, fetchReturns);

// ✅ sync نص الـ party لما الـ ID أو النوع يتغير
watch([filters.customerFilter, type, customers, suppliers], () => {
  const list = type.value === 'sales' ? (customers.value || []) : (suppliers.value || []);
  const found = list.find(x => String(x.id) === String(filters.customerFilter.value));
  filters.customerSearch.value = found ? (found.name || found.customer_name || found.supplier_name || '') : '';
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Try Bootstrap API first for better performance
  try {
    const data = await bootstrapStore.fetchPaymentsData();
    
    // Apply bootstrap data to stores
    if (data.customers) {
      customerStore.customers = data.customers;
    }
    if (data.suppliers) {
      supplierStore.suppliers = data.suppliers;
    }
    if (data.branches && isExempt.value) {
      branchStore.setBranches(data.branches);
    }
    
    // Still need these separately
    await Promise.all([
      fetchSettings(),
      ensureExemptionLoaded()
    ]);
    
    console.log('[ReturnsHistory] Bootstrap data loaded successfully');
  } catch (bootstrapError) {
    console.warn('[ReturnsHistory] Bootstrap API failed, using fallback', bootstrapError);
    
    // Fallback: Load data individually
    await Promise.all([fetchSettings(), ensureExemptionLoaded()]);
    
    // جلب الفروع إذا كان المستخدم معفى
    if (isExempt.value) {
      try { 
        await branchStore.fetchBranches();
      } catch (e) { 
        console.error('خطأ في جلب الفروع:', e); 
      }
    }

    // Load customers
    try {
      await customerStore.fetchCustomers();
      // customers computed from customerStore
    } catch { /* customers computed from customerStore */ }

    // Load suppliers
    try {
      await supplierStore.fetchSuppliers();
    } catch { /* ignore */ }
  }

  // تأخير صغير للتأكد من تحميل البيانات
  await new Promise(resolve => setTimeout(resolve, 100));

  fetchReturns();

  const qid = Number(route.query.id || 0);
  if (qid > 0) {
    try {
      await viewReturnDetails({ id: qid });
    } catch (err) {
      console.error('Failed to load return details from query.id', err);
    }
  }
});

// ✅ cleanup عند الخروج من الصفحة
onUnmounted(() => {
  if (returnsAbortCtrl) returnsAbortCtrl.abort();
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});
</script>

<style scoped>

.kpi-card { @apply bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

.form-input-modern, .form-select-modern {
  @apply w-full h-[46px] bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-rose-500 focus:ring-4 focus:ring-rose-50 shadow-sm;
}

.filter-chip { @apply inline-flex items-center gap-2 bg-rose-50 text-rose-600 px-3 py-1.5 rounded-xl text-[10px] font-black border border-rose-100 shadow-sm transition-all; }
.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply h-10 px-4 rounded-xl bg-white border border-slate-200 flex items-center gap-1 text-xs font-black hover:bg-slate-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-enter-active, .slide-leave-active { transition: all 0.3s ease-out; max-height: 500px; overflow: hidden; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0; }
</style>