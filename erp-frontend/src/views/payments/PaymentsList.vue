<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">

    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-file-invoice-dollar text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">سجل المدفوعات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">عرض مركزي لكافة المقبوضات والمدفوعات المالية</p>
        </div>
      </div>

      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="load" :disabled="isLoading" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث
        </button>
        <button @click="exportCsv" class="px-5 py-2.5 rounded-xl text-xs font-black text-emerald-600 hover:bg-emerald-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-file-csv"></i> تصدير CSV
        </button>
        <button @click="exportPdf" class="px-5 py-2.5 rounded-xl text-xs font-black text-blue-600 hover:bg-blue-50 transition-all flex items-center gap-2">
          <i class="fas fa-print"></i> طباعة PDF
        </button>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="space-y-2">
          <label class="filter-label">من تاريخ</label>
          <div class="relative">
            <input ref="dateFromRef" type="date" v-model="dateFrom" @change="page=1; load();" class="form-input-modern font-bold" />
            <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="dateFromRef.showPicker()"></i>
          </div>
        </div>
        <div class="space-y-2">
          <label class="filter-label">إلى تاريخ</label>
          <div class="relative">
            <input ref="dateToRef" type="date" v-model="dateTo" @change="page=1; load();" class="form-input-modern font-bold" />
            <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="dateToRef.showPicker()"></i>
          </div>
        </div>
        <div class="space-y-2 text-right">
          <label class="filter-label">نوع السند</label>
          <select v-model="type" @change="page=1; load();" class="form-select-modern font-black">
            <option value="">كل الأنواع</option>
            <option value="receipt">سند قبض (وارد)</option>
            <option value="payment">سند دفع (صادر)</option>
          </select>
        </div>
        <div class="space-y-2 text-right">
          <label class="filter-label">حالة العملية</label>
          <select v-model="status" @change="page=1; load();" class="form-select-modern font-black">
            <option value="">كل الحالات</option>
            <option value="paid">مدفوعة</option>
            <option value="partial">جزئية</option>
            <option value="pending">قيد الانتظار</option>
            <option value="completed">مكتملة</option>
            <option value="canceled">ملغاة</option>
            <option value="draft">مسودة</option>
            <option value="unpaid">غير مدفوعة</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end">
        <div class="space-y-2 text-right">
          <label class="filter-label">طريقة الدفع</label>
          <select v-model="paymentMethodId" @change="page=1; load();" class="form-select-modern font-black">
            <option value="">كل الطرق</option>
            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </div>

        <!-- Searchable Customer -->
        <div class="relative space-y-2 text-right" ref="customerWrap">
          <label class="filter-label">تصفية بالعميل</label>
          <div class="relative group">
            <input type="text" v-model="customerQuery"
                   @focus="showCustomerList = true"
                   @input="showCustomerList = true"
                   @keydown.esc.prevent="showCustomerList = false"
                   class="form-input-modern pr-10 font-bold" placeholder="ابحث بالاسم..." />
            <i class="fas fa-user-tag absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            <button v-if="customerQuery || customerId"
                    @mousedown.prevent="customerId = ''; customerQuery = ''; page = 1; load();"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 transition-colors">
              <i class="fas fa-times-circle"></i>
            </button>
            <div v-if="showCustomerList" class="dropdown-list custom-scroll">
              <div v-if="!filteredCustomers.length" class="p-4 text-center text-[10px] font-black text-slate-400 uppercase">لا توجد نتائج</div>
              <button v-for="c in filteredCustomers" :key="c.id"
                      @mousedown.prevent="customerId = c.id; customerQuery = c.name || String(c.id); page = 1; load(); showCustomerList = false;"
                      class="dropdown-item">
                <i class="fas fa-user-circle text-blue-500"></i> {{ c.name || ('#' + c.id) }}
              </button>
            </div>
          </div>
        </div>

        <!-- Searchable Supplier -->
        <div class="relative space-y-2 text-right" ref="supplierWrap">
          <label class="filter-label">تصفية بالمورد</label>
          <div class="relative group">
            <input type="text" v-model="supplierQuery"
                   @focus="showSupplierList = true"
                   @input="showSupplierList = true"
                   @keydown.esc.prevent="showSupplierList = false"
                   class="form-input-modern pr-10 font-bold" placeholder="ابحث بالمورد..." />
            <i class="fas fa-truck absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
            <button v-if="supplierQuery || supplierId"
                    @mousedown.prevent="supplierId = ''; supplierQuery = ''; page = 1; load();"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 transition-colors">
              <i class="fas fa-times-circle"></i>
            </button>
            <div v-if="showSupplierList" class="dropdown-list custom-scroll">
              <div v-if="!filteredSuppliers.length" class="p-4 text-center text-[10px] font-black text-slate-400 uppercase">لا توجد نتائج</div>
              <button v-for="s in filteredSuppliers" :key="s.id"
                      @mousedown.prevent="supplierId = s.id; supplierQuery = s.name || String(s.id); page = 1; load(); showSupplierList = false;"
                      class="dropdown-item">
                <i class="fas fa-truck-field text-indigo-500"></i> {{ s.name || ('#' + s.id) }}
              </button>
            </div>
          </div>
        </div>

        <!-- Searchable User (أنشئ بواسطة) — كانت مفقودة من الجديدة -->
        <div class="relative space-y-2 text-right" ref="userWrap">
          <label class="filter-label">أنشئ بواسطة</label>
          <div class="relative group">
            <input type="text" v-model="userQuery"
                   @focus="showUserList = true"
                   @input="showUserList = true"
                   @keydown.esc.prevent="showUserList = false"
                   class="form-input-modern pr-10 font-bold" placeholder="ابحث بالمستخدم..." />
            <i class="fas fa-user-shield absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-purple-500 transition-colors"></i>
            <button v-if="userQuery || createdBy"
                    @mousedown.prevent="createdBy = ''; userQuery = ''; page = 1; load();"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 transition-colors">
              <i class="fas fa-times-circle"></i>
            </button>
            <div v-if="showUserList" class="dropdown-list custom-scroll">
              <div v-if="!filteredUsers.length" class="p-4 text-center text-[10px] font-black text-slate-400 uppercase">لا توجد نتائج</div>
              <button v-for="u in filteredUsers" :key="u.id"
                      @mousedown.prevent="createdBy = u.id; userQuery = u.name || String(u.id); page = 1; load(); showUserList = false;"
                      class="dropdown-item">
                <i class="fas fa-user-circle text-purple-500"></i> {{ u.name || ('#' + u.id) }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 3: Reset + Per Page -->
      <div class="flex items-center gap-3 mt-6">
        <button @click="resetFilters" class="h-11 px-6 rounded-2xl bg-slate-100 text-slate-600 font-black text-xs hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
          <i class="fas fa-broom"></i> مسح الكل
        </button>
        <div class="w-36">
          <select v-model.number="perPage" class="form-select-modern font-black text-xs">
            <option :value="10">10 / صفحة</option>
            <option :value="20">20 / صفحة</option>
            <option :value="50">50 / صفحة</option>
            <option :value="100">100 / صفحة</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">تاريخ السند</th>
              <th class="px-4 py-5">المرجع / المستند</th>
              <th class="px-4 py-5 text-center">نوع السند</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-4 py-5">طريقة الدفع</th>
              <th class="px-4 py-5">القيمة</th>
              <th class="px-4 py-5">جهة الاتصال</th>
              <th class="px-6 py-5 text-center">بواسطة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!items.length">
              <td colspan="8" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-receipt text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد عمليات مسجلة</p>
                </div>
              </td>
            </tr>
            <tr v-for="p in items" :key="p.id" class="hover:bg-blue-50/30 transition-all group font-bold">
              <td class="px-6 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter group-hover:text-slate-900 transition-colors">
                {{ p.payment_date ? new Date(p.payment_date).toLocaleDateString('en-GB') : '-' }}
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col">
                  <router-link v-if="p.sale_id" :to="{ name: 'SalesHistory', query: { id: p.sale_id } }" class="text-blue-600 font-black hover:underline underline-offset-4">
                    {{ p.reference || '#' + p.id }}
                  </router-link>
                  <router-link v-else-if="p.purchase_id" :to="{ name: 'PurchaseHistory', query: { id: p.purchase_id } }" class="text-indigo-600 font-black hover:underline underline-offset-4">
                    {{ p.reference || '#' + p.id }}
                  </router-link>
                  <span v-else class="font-black text-slate-800">{{ p.reference || '-' }}</span>
                  <span class="text-[9px] text-slate-300 font-black uppercase tracking-widest mt-1">ID: #{{ p.id }}</span>
                </div>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="px-3 py-1 bg-slate-50 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest group-hover:bg-white transition-all border border-transparent group-hover:border-slate-100 shadow-sm">
                  {{ p.reference_label || (String(p.reference || '').split('#')[0] || 'سند') }}
                </span>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="status-badge" :class="badgeClass(p.status || p.status_code)">
                  {{ p.status_label || (p.status || p.status_code || '-') }}
                </span>
              </td>
              <td class="px-4 py-4">
                <span class="px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm"
                      :class="{
                        'bg-emerald-100 text-emerald-700': (p.payment_method_name||'').toLowerCase().includes('cash') || (p.payment_method_name||'').includes('نقد'),
                        'bg-blue-100 text-blue-700': (p.payment_method_name||'').toLowerCase().includes('card') || (p.payment_method_name||'').includes('بطاقة'),
                        'bg-indigo-100 text-indigo-700': (p.payment_method_name||'').toLowerCase().includes('bank') || (p.payment_method_name||'').includes('بنك'),
                        'bg-amber-100 text-amber-700': (p.payment_method_name||'').toLowerCase().includes('credit') || (p.payment_method_name||'').includes('آجل'),
                        'bg-slate-100 text-slate-500': true
                      }">
                  {{ p.payment_method_name || '-' }}
                </span>
              </td>
              <td class="px-4 py-4 font-black text-slate-900 text-base font-mono tracking-tighter">
                {{ formatCurrency(p.amount || 0) }}
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col text-xs">
                  <router-link v-if="p.customer_id" :to="{ name: 'ContactDetails', params: { type: 'customers', id: p.customer_id } }" class="text-blue-500 font-bold hover:underline">
                    {{ p.customer_name || p.customer_id }}
                  </router-link>
                  <router-link v-else-if="p.supplier_id" :to="{ name: 'ContactDetails', params: { type: 'suppliers', id: p.supplier_id } }" class="text-indigo-500 font-bold hover:underline">
                    {{ p.supplier_name || p.supplier_id }}
                  </router-link>
                  <span v-else class="text-slate-300 italic">—</span>
                </div>
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex flex-col items-center">
                  <span class="text-[10px] font-black text-slate-800 leading-none">{{ p.created_by_name || '-' }}</span>
                  <span class="text-[9px] font-bold text-slate-400 mt-1 uppercase">
                    {{ p.created_at ? new Date(p.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '' }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          إجمالي المسجل: <span class="text-slate-800">{{ meta.total }}</span> عملية
        </div>
        <div class="flex items-center gap-1">
          <button @click="prevPage" :disabled="page <= 1" class="pagination-btn">
            <i class="fas fa-angle-right"></i>
          </button>
          <div class="px-6 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm">
            {{ page }} / {{ Math.max(1, Math.ceil(meta.total / perPage)) }}
          </div>
          <button @click="nextPage" :disabled="page * perPage >= meta.total" class="pagination-btn">
            <i class="fas fa-angle-left"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import getLocalDateISO from '@/utils/date';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { useUserStore } from '@/stores/user/userStore';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useBootstrapStore } from '@/stores/bootstrap';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';

// --- State ---
const isLoading = ref(false);
const items = ref([]);
const meta = ref({ total: 0, page: 1, per_page: 50 });

// Filters
const dateFrom = ref('');
const dateTo = ref('');

// Date input refs
const dateFromRef = ref(null);
const dateToRef = ref(null);
const type = ref('');
const status = ref('');
const paymentMethodId = ref('');
const perPage = ref(50);
const page = ref(1);

const { formatCurrency, fetchSettings } = useCompanyCurrency();
const bootstrapStore = useBootstrapStore();
const paymentStore = usePaymentStore();
const paymentMethods = computed(() => paymentStore.paymentMethods);
const customerStore = useCustomerStore();
const customers = computed(() => customerStore.customers);
const supplierStore = useSupplierStore();
const userStore = useUserStore();
const suppliers = computed(() => supplierStore.suppliers);
const users = computed(() => userStore.users);

const customerId = ref('');
const supplierId = ref('');
const createdBy = ref('');

const customerQuery = ref('');
const supplierQuery = ref('');
const userQuery = ref('');

const showCustomerList = ref(false);
const showSupplierList = ref(false);
const showUserList = ref(false);

const customerWrap = ref(null);
const supplierWrap = ref(null);
const userWrap = ref(null);

// --- Badge helper ---
const badgeClass = (s) => {
  const v = String(s || '').toLowerCase();
  if (['paid', 'posted', 'completed'].includes(v)) return 'bg-emerald-100 text-emerald-700';
  if (['partial', 'partially_paid'].includes(v)) return 'bg-amber-100 text-amber-700';
  if (['unpaid', 'pending', 'pending_payment'].includes(v)) return 'bg-yellow-100 text-yellow-700';
  if (['rejected', 'canceled', 'cancelled'].includes(v)) return 'bg-rose-100 text-rose-700';
  return 'bg-slate-100 text-slate-500';
};

// --- Filtered dropdown lists ---
const filteredUsers = computed(() => {
  const q = (userQuery.value || '').toLowerCase().trim();
  const arr = users.value || [];
  if (!q) return arr.slice(0, 50);
  return arr.filter(u => String(u.name || '').toLowerCase().includes(q) || String(u.id).includes(q)).slice(0, 50);
});
const filteredCustomers = computed(() => {
  const q = (customerQuery.value || '').toLowerCase().trim();
  const arr = customers.value || [];
  if (!q) return arr.slice(0, 50);
  return arr.filter(c => String(c.name || '').toLowerCase().includes(q) || String(c.id).includes(q)).slice(0, 50);
});
const filteredSuppliers = computed(() => {
  const q = (supplierQuery.value || '').toLowerCase().trim();
  const arr = suppliers.value || [];
  if (!q) return arr.slice(0, 50);
  return arr.filter(s => String(s.name || '').toLowerCase().includes(q) || String(s.id).includes(q)).slice(0, 50);
});

// --- Query params ---
const queryParams = computed(() => {
  const p = { per_page: perPage.value, page: page.value };
  if (dateFrom.value) p.date_from = dateFrom.value;
  if (dateTo.value) p.date_to = dateTo.value;
  if (type.value) p.type = type.value;
  if (status.value) p.status = status.value;
  if (paymentMethodId.value) p.payment_method_id = Number(paymentMethodId.value);
  if (customerId.value) p.customer_id = Number(customerId.value);
  if (supplierId.value) p.supplier_id = Number(supplierId.value);
  if (createdBy.value) p.created_by = Number(createdBy.value);
  return p;
});

// --- Load data ---
async function load() {
  isLoading.value = true;
  try {
    const result = await paymentStore.fetchPayments(queryParams.value);
    if (result.status === 'success') {
      const { items: rows, meta: m } = result.data || { items: [], meta: {} };
      items.value = rows || [];
      meta.value = m || { total: (rows || []).length, page: 1, per_page: (rows || []).length };
    } else {
      items.value = [];
      meta.value = { total: 0, page: 1, per_page: perPage.value };
    }
  } catch (e) {
    console.error('Failed to load payments', e);
    items.value = [];
    meta.value = { total: 0, page: 1, per_page: perPage.value };
  } finally {
    isLoading.value = false;
  }
}

// --- Reset filters ---
function resetFilters() {
  dateFrom.value = '';
  dateTo.value = '';
  type.value = '';
  status.value = '';
  paymentMethodId.value = '';
  createdBy.value = '';
  customerId.value = '';
  supplierId.value = '';
  userQuery.value = '';
  customerQuery.value = '';
  supplierQuery.value = '';
  page.value = 1;
  load();
}

// --- Export CSV ---
function exportCsv() {
  const rows = (items.value || []).map(r => ({
    id: r.id,
    date: r.payment_date,
    reference: r.reference,
    reference_label: r.reference_label,
    status: r.status_code || r.status,
    status_label: r.status_label,
    amount: r.amount,
    payment_method_id: r.payment_method_id,
    payment_method_name: r.payment_method_name,
    created_by: r.created_by_name,
    customer: r.customer_name || r.customer_id,
    supplier: r.supplier_name || r.supplier_id,
    sale_id: r.sale_id,
    purchase_id: r.purchase_id,
    created_at: r.created_at,
  }));
  paymentStore.exportToCsv(`payments_${getLocalDateISO()}.csv`, rows);
}

// --- Export PDF (full columns like original) ---
function exportPdf() {
  const rows = items.value || [];
  const win = window.open('', '_blank');
  if (!win) return;
  const style = `
    <style>
      body { font-family: 'Cairo', Arial, sans-serif; direction: rtl; padding: 20px; }
      h1 { font-size: 18px; margin-bottom: 8px; }
      .summary { font-size: 12px; color: #555; margin-bottom: 12px; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: center; }
      thead { background: #f8fafc; font-weight: 900; }
      tr:nth-child(even) { background: #f9fafb; }
    </style>
  `;
  const header = `
    <tr>
      <th>التاريخ</th>
      <th>المعرف</th>
      <th>المرجع</th>
      <th>النوع</th>
      <th>الحالة</th>
      <th>طريقة الدفع</th>
      <th>أنشئ بواسطة</th>
      <th>القيمة</th>
      <th>عميل</th>
      <th>مورد</th>
    </tr>
  `;
  const body = rows.map(r => `
    <tr>
      <td>${r.payment_date ? new Date(r.payment_date).toLocaleDateString('en-GB') : '-'}</td>
      <td>#${r.id}</td>
      <td>${r.reference || '-'}</td>
      <td>${r.reference_label || (String(r.reference || '').split('#')[0] || '')}</td>
      <td>${r.status_label || r.status || r.status_code || '-'}</td>
      <td>${r.payment_method_name || '-'}</td>
      <td>${r.created_by_name || '-'}</td>
      <td>${Number(r.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td>${r.customer_name || r.customer_id || '-'}</td>
      <td>${r.supplier_name || r.supplier_id || '-'}</td>
    </tr>
  `).join('');
  const summary = `
    <div class="summary">
      <div><strong>الفترة:</strong> ${dateFrom.value || '-'} إلى ${dateTo.value || '-'}</div>
      <div><strong>النوع:</strong> ${type.value || 'الكل'} | <strong>الحالة:</strong> ${status.value || 'الكل'} | <strong>طريقة الدفع:</strong> ${paymentMethodId.value || 'الكل'}</div>
      <div><strong>أنشئ بواسطة:</strong> ${userQuery.value || '-'} | <strong>عميل:</strong> ${customerQuery.value || '-'} | <strong>مورد:</strong> ${supplierQuery.value || '-'}</div>
    </div>
  `;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8">${style}</head><body>`);
  win.document.write(`<h1>تقرير المدفوعات</h1>${summary}`);
  win.document.write(`<table><thead>${header}</thead><tbody>${body}</tbody></table>`);
  win.document.write('</body></html>');
  win.document.close();
  win.focus();
  win.print();
}

// --- Pagination ---
function nextPage() { if (page.value * perPage.value < meta.value.total) { page.value += 1; load(); } }
function prevPage() { if (page.value > 1) { page.value -= 1; load(); } }

// --- Lifecycle ---
const cleanup = ref(() => {});

onMounted(async () => {
  // Use bootstrap API to fetch all required data in a single request
  try {
    const data = await bootstrapStore.fetchPaymentsData();
    
    // Map bootstrap data to individual stores
    if (data.paymentMethods) paymentStore.paymentMethods = data.paymentMethods;
    if (data.customers) customerStore.customers = data.customers;
    if (data.suppliers) supplierStore.suppliers = data.suppliers;
    if (data.users) userStore.users = data.users;
    if (data.settings) {
      // Apply settings to currency composable if needed
      if (data.settings.currency || data.settings.currency_symbol) {
        // Settings are already handled by fetchSettings, but we can use cached values
      }
    }
    
    console.log('[PaymentsList] Bootstrap data loaded successfully');
  } catch (e) {
    console.warn('[PaymentsList] Bootstrap API failed, falling back to individual requests', e);
    
    // Fallback to individual API calls if bootstrap fails
    try { await fetchSettings(); } catch { /* ignore */ }
    try { await paymentStore.fetchPaymentMethods(); } catch { /* ignore */ }
    try { await customerStore.fetchCustomers(); } catch { /* ignore */ }
    try { await supplierStore.fetchSuppliers(); } catch { /* ignore */ }
    try { await userStore.fetchUsers(); } catch { /* ignore */ }
  }

  await load();

  // Close dropdowns on outside click
  const onDocClick = (e) => {
    if (!customerWrap.value?.contains(e.target)) showCustomerList.value = false;
    if (!supplierWrap.value?.contains(e.target)) showSupplierList.value = false;
    if (!userWrap.value?.contains(e.target)) showUserList.value = false;
  };
  // Close dropdowns on scroll (preserved from original)
  const onScroll = () => {
    showCustomerList.value = false;
    showSupplierList.value = false;
    showUserList.value = false;
  };

  document.addEventListener('click', onDocClick, true);
  window.addEventListener('scroll', onScroll, true);

  cleanup.value = () => {
    document.removeEventListener('click', onDocClick, true);
    window.removeEventListener('scroll', onScroll, true);
  };
});

onBeforeUnmount(() => {
  try { cleanup.value && cleanup.value(); } catch {}
});

watch([perPage], () => { page.value = 1; load(); });
</script>

<style scoped>



/* Modern UI Components */
.form-input-modern,
.form-select-modern {
  @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm;
}
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

/* Searchable Dropdowns */
.dropdown-list { @apply absolute z-50 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-64 overflow-auto py-2; }
.dropdown-item { @apply w-full text-right px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-all flex items-center gap-3; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>