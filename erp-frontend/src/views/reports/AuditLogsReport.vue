<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center shadow-xl shadow-slate-200 text-white shrink-0">
          <i class="fas fa-shield-halved text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">سجل التدقيق والمراقبة</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع كافة التغييرات، العمليات، وحركات المستخدمين داخل النظام</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="fetchLogs" :disabled="isLoading" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث السجلات
        </button>
      </div>
    </div>

    <!-- Integrity Overview KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600"><i class="fas fa-fingerprint"></i></div>
          <div><p class="kpi-label uppercase">إجمالي العمليات</p><p class="kpi-value text-slate-800">{{ total }}</p></div>
        </div>
      </div>
      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fas fa-plus-circle"></i></div>
          <div><p class="kpi-label uppercase">عمليات الإضافة</p><p class="kpi-value text-emerald-600">{{ logs.filter(l => l.action === 'create').length }}</p></div>
        </div>
      </div>
      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600"><i class="fas fa-pen-to-square"></i></div>
          <div><p class="kpi-label uppercase">عمليات التعديل</p><p class="kpi-value text-amber-600">{{ logs.filter(l => l.action === 'update').length }}</p></div>
        </div>
      </div>
      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600"><i class="fas fa-trash-can"></i></div>
          <div><p class="kpi-label uppercase">عمليات الحذف</p><p class="kpi-value text-rose-600">{{ logs.filter(l => l.action === 'delete').length }}</p></div>
        </div>
      </div>
    </section>

    <!-- Professional Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6 items-end">
        <div class="space-y-2 group">
          <label class="filter-label">من تاريخ</label>
          <div class="relative">
            <input ref="startDateRef" type="date" v-model="startDate" class="form-input-modern font-bold text-sm" />
            <i class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="startDateRef.showPicker()"></i>
          </div>
        </div>

        <div class="space-y-2 group">
          <label class="filter-label">إلى تاريخ</label>
          <div class="relative">
            <input ref="endDateRef" type="date" v-model="endDate" class="form-input-modern font-bold text-sm" />
            <i class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="endDateRef.showPicker()"></i>
          </div>
        </div>

        <div class="space-y-2">
          <label class="filter-label">نوع العملية</label>
          <select v-model="actionType" class="form-select-modern font-black text-sm">
            <option value="all">كل العمليات</option>
            <option value="create">إضافة</option>
            <option value="update">تعديل</option>
            <option value="delete">حذف</option>
            <option value="login">دخول</option>
          </select>
        </div>

        <div class="space-y-2">
          <label class="filter-label">المستخدم / الموظف</label>
          <select v-model="userId" class="form-select-modern font-black text-sm">
            <option value="all">كل المستخدمين</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <div class="flex gap-2">
           <button @click="setDateRange('today')" class="quick-range-btn flex-1">اليوم</button>
           <button @click="setDateRange('week')" class="quick-range-btn flex-1">آخر ٧ أيام</button>
        </div>
      </div>
    </div>
    
    <!-- Analytics Charts Section -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">
        <div class="lg:col-span-8 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
             <h3 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3 mb-8">
               <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
               تحليل نشاط المستخدمين
             </h3>
             <div class="h-72">
               <BarChart v-if="!isLoading && logs.length" :data="chartDataByUser" :options="chartOptions" />
               <div v-else class="h-full flex items-center justify-center opacity-30 italic font-bold">بانتظار البيانات...</div>
             </div>
        </div>
        <div class="lg:col-span-4 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
             <h3 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3 mb-8">
               <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
               توزيع أنواع العمليات
             </h3>
             <div class="h-72">
               <DoughnutChart v-if="!isLoading && logs.length" :data="chartDataByAction" :options="doughnutOptions" />
             </div>
        </div>
    </section>

    <!-- Audit Logs Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">سجل التغييرات التفصيلي</h3>
         <div class="flex items-center gap-3 text-[10px] font-black text-slate-300 uppercase tracking-widest">
            <span>إجمالي السجلات: {{ total }}</span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm font-cairo">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th @click="handleSort('timestamp')" class="px-6 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                التاريخ والوقت <i :class="sortKey === 'timestamp' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th @click="handleSort('userName')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                المستخدم <i :class="sortKey === 'userName' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th class="px-4 py-5 text-center">العملية</th>
              <th class="px-4 py-5">الوحدة / المرجع</th>
              <th class="px-8 py-5 text-center">التفاصيل</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="12rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!sortedLogs.length" class="text-center py-20">
              <td colspan="5" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-search-nodes text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase uppercase tracking-widest">لا توجد سجلات مطابقة للبحث</p>
                </div>
              </td>
            </tr>
            <tr v-for="log in sortedLogs" :key="log.id" class="hover:bg-blue-50/30 transition-all group font-bold">
              <td class="px-6 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ formatDateTime(log.timestamp) }}</td>
              <td class="px-4 py-4">
                 <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center text-[10px] group-hover:bg-white transition-all font-black">{{ (log.userName || 'U').charAt(0) }}</div>
                    <span class="font-black text-slate-800 leading-none">{{ log.userName }}</span>
                 </div>
              </td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', getActionTypeClass(log.action)]">{{ getActionTypeLabel(log.action) }}</span>
              </td>
              <td class="px-4 py-4">
                 <div class="flex flex-col">
                    <span class="font-black text-slate-700 text-xs">{{ log.module }}</span>
                    <span class="text-[9px] text-slate-300 font-mono mt-1 tracking-widest uppercase">REC ID: #{{ log.recordId }}</span>
                 </div>
              </td>
              <td class="px-8 py-4 text-center">
                <button v-if="log.details && Object.keys(log.details).length" @click="showDetailsModal(log)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95">
                  <i class="fas fa-magnifying-glass-chart text-xs"></i>
                </button>
                <span v-else class="text-slate-200">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Log Details Modal (Deep Analysis) -->
    <transition name="modal">
      <div v-if="showModal && selectedLog" class="modal-overlay">
        <div class="modal-content-modern max-w-4xl animate-modalIn border border-white">
          <!-- Modal Header -->
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center shadow-lg shrink-0">
                <i class="fas fa-file-shield text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">تفاصيل سجل التدقيق #{{ selectedLog.id }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">{{ formatDateTime(selectedLog.timestamp) }}</p>
              </div>
            </div>
            <button @click="showModal = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>

          <!-- Modal Body -->
          <div class="p-8 overflow-y-auto custom-scroll max-h-[75vh] space-y-8">
            <!-- Summary Header Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 font-bold"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">المستخدم المسئول</p><p class="text-xs text-slate-800 leading-none">{{ selectedLog.userName }}</p></div>
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 font-bold"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">طبيعة العملية</p><span :class="['status-badge mt-1', getActionTypeClass(selectedLog.action)]">{{ getActionTypeLabel(selectedLog.action) }}</span></div>
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 font-bold"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">عنوان الـ IP</p><p class="text-xs text-slate-800 font-mono tracking-tighter">{{ selectedLog.ipAddress }}</p></div>
               <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 text-white font-black"><p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">النظام / الوحدة</p><p class="text-xs uppercase tracking-widest">{{ selectedLog.module }}</p></div>
            </div>

            <!-- Deep Analysis: Changes Diff Section -->
            <div v-if="selectedLog.action === 'update' && selectedLog.details.changes" class="space-y-4">
                 <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2"><i class="fas fa-shuffle text-blue-500"></i> تحليل التغييرات المكتشفة</h4>
                 <div class="grid grid-cols-1 gap-4">
                    <div v-for="(change, field) in selectedLog.details.changes" :key="field" class="bg-white rounded-[1.5rem] border border-slate-100 p-5 shadow-sm transition-all hover:border-blue-100 group">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-50 pb-3">
                           <span class="text-xs font-black text-slate-800 uppercase tracking-tighter bg-slate-100 px-3 py-1 rounded-lg">{{ fieldLabels[field] || field }}</span>
                           <i class="fas fa-arrows-rotate text-[10px] text-slate-200 group-hover:text-blue-500 transition-colors"></i>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 relative overflow-hidden">
                               <p class="text-[8px] font-black text-rose-300 uppercase tracking-widest mb-1">القيمة السابقة</p>
                               <p class="text-sm font-bold text-rose-600 line-through">{{ change.old }}</p>
                            </div>
                            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 relative overflow-hidden">
                               <p class="text-[8px] font-black text-emerald-300 uppercase tracking-widest mb-1">القيمة الجديدة</p>
                               <p class="text-sm font-black text-emerald-700">{{ change.new }}</p>
                            </div>
                        </div>
                    </div>
                 </div>
            </div>

            <!-- Fallback: Full JSON View -->
            <div v-else class="space-y-4">
                 <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2"><i class="fas fa-database text-indigo-500"></i> بيانات السجل الخام (Raw Data)</h4>
                 <div class="bg-slate-900 rounded-[2rem] p-6 shadow-2xl relative overflow-hidden border border-slate-800">
                    <div class="absolute top-0 left-0 w-32 h-32 bg-white/5 rounded-full -translate-x-12 -translate-y-12"></div>
                    <pre class="text-[11px] font-bold text-blue-100 font-mono whitespace-pre-wrap leading-relaxed relative z-10">{{ formatDetails(selectedLog.details) }}</pre>
                 </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3">
             <button @click="showModal = false" class="px-10 py-3 rounded-xl bg-slate-900 text-white text-xs font-black uppercase tracking-widest shadow-xl shadow-slate-200 hover:bg-black transition-all active:scale-95">إتمام المراجعة</button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useUserStore } from '@/stores/user/userStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { useReportsStore } from '@/stores/reports';
import { Bar as BarChart, Doughnut as DoughnutChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';;
import { getLocalDateISO } from '@/utils/date';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const reportsStore = useReportsStore();
const authStore = useAuthStore();
const paymentStore = usePaymentStore();
const userStore = useUserStore();
const supplierStore = useSupplierStore();
const isLoading = ref(true);
const error = ref(null);

const startDate = ref(getLocalDateISO(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)));
const endDate = ref(getLocalDateISO());
const startDateRef = ref(null);
const endDateRef = ref(null);
const actionType = ref('all');
const userId = ref('all');
let debounceTimer = null;

const logs = ref([]);
const total = computed(() => logs.value.length);
const users = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);
const sortKey = ref('timestamp');
const sortOrder = ref('desc');
const showModal = ref(false);
const selectedLog = ref(null);
const usersCache = ref({});
const suppliersCache = ref({});
const paymentMethodsCache = ref({});

const sortedLogs = computed(() => {
  return [...logs.value].sort((a, b) => {
    let valA = a[sortKey.value], valB = b[sortKey.value];
    if (sortKey.value === 'timestamp') { valA = new Date(valA); valB = new Date(valB); }
    if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
    if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
    return 0;
  });
});

const chartDataByUser = computed(() => {
    const counts = logs.value.reduce((acc, log) => { acc[log.userName] = (acc[log.userName] || 0) + 1; return acc; }, {});
    return { labels: Object.keys(counts), datasets: [{ label: 'عدد العمليات', data: Object.values(counts), backgroundColor: '#3b82f6', borderRadius: 8, maxBarThickness: 32 }] };
});

const chartDataByAction = computed(() => {
    const counts = logs.value.reduce((acc, log) => { const label = getActionTypeLabel(log.action); acc[label] = (acc[label] || 0) + 1; return acc; }, {});
    return { labels: Object.keys(counts), datasets: [{ data: Object.values(counts), backgroundColor: ['#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#64748b'] }] };
});

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } }, x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { family: 'Cairo', size: 10 }, usePointStyle: true } } } };

const formatDateTime = (ts) => new Date(ts).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' });

const fieldLabels = {
  // General fields
  'id': 'المعرف',
  'tenant_id': 'معرف المستأجر',
  'user_id': 'المستخدم',
  'supplier_id': 'المورد',
  'branch_id': 'المخزن',
  'invoice_number': 'رقم الفاتورة',
  'invoice_date': 'تاريخ الفاتورة',
  'total_amount': 'إجمالي المبلغ',
  'tax_amount': 'قيمة الضريبة',
  'discount_value': 'قيمة الخصم',
  'paid_amount': 'المبلغ المدفوع',
  'payment_method_id': 'طريقة الدفع',
  'total_items': 'إجمالي الأصناف',
  'status': 'الحالة',
  'created_at': 'تاريخ الإنشاء',
  'updated_at': 'تاريخ التحديث',
  'notes': 'ملاحظات',
  
  // Purchase specific
  'purchase_id': 'رقم المشتريات',
  'payment_id': 'رقم الدفعة',
  'amount': 'المبلغ',
  'payment_date': 'تاريخ الدفعة',
  'paid_amount_total': 'إجمالي المدفوع',
  'session_id': 'رقم الجلسة',
  
  // Sale specific
  'sale_id': 'رقم المبيعات',
  'customer_id': 'العميل',
  
  // Inventory specific
  'product_id': 'المنتج',
  'quantity': 'الكمية',
  'unit_price': 'سعر الوحدة',
  'subtotal': 'المجموع الفرعي',
  'reference': 'المرجع',
  'type': 'النوع',
  'adjustment': 'التعديل',
  'reason': 'السبب',
  'from_branch': 'من مخزن',
  'to_branch': 'إلى مخزن',
  'transfer_date': 'تاريخ النقل',
  'counted_quantity': 'الكمية المعدودة',
  'difference': 'الفرق',
  'count_date': 'تاريخ الجرد'
};

const getActionTypeLabel = (action) => ({
  // General
  'create': 'إضافة', 
  'update': 'تعديل', 
  'delete': 'حذف', 
  'login': 'دخول', 
  'logout': 'خروج',

  // Purchase
  'purchase_created': 'إنشاء فاتورة مشتريات',
  'purchase_updated': 'تعديل فاتورة مشتريات',
  'purchase_deleted': 'حذف فاتورة مشتريات',
  'purchase_payment_added': 'إضافة دفعة مشتريات',
  'purchase_payment_updated': 'تعديل دفعة مشتريات',
  'purchase_payment_deleted': 'حذف دفعة مشتريات',

  // Sales
  'sale_created': 'إنشاء فاتورة مبيعات',
  'sale_updated': 'تعديل فاتورة مبيعات',
  'sale_deleted': 'حذف فاتورة مبيعات',
  'sale_payment_added': 'إضافة دفعة مبيعات',
  'sale_payment_updated': 'تعديل دفعة مبيعات',
  'sale_payment_deleted': 'حذف دفعة مبيعات',

  // Inventory
  'stock_adjusted': 'تعديل المخزون',
  'stock_transferred': 'تحويل مخزون',
  'stock_counted': 'جرد مخزون'

}[action] || action);

const getActionTypeClass = (a) => ({ create: 'bg-emerald-100 text-emerald-700', update: 'bg-blue-100 text-blue-700', delete: 'bg-rose-100 text-rose-700', login: 'bg-purple-100 text-purple-700' }[a] || 'bg-slate-100 text-slate-500');

const fetchLogs = async () => {
    isLoading.value = true; error.value = null;
    try {
        const payload = await reportsStore.fetchAuditLogs({ startDate: startDate.value, endDate: endDate.value, actionType: actionType.value, userId: userId.value, page: currentPage.value });
        const items = Array.isArray(payload?.data) ? payload.data : (payload?.items || []);
        logs.value = items.map(it => ({ id: it.id, timestamp: it.timestamp || it.created_at, userName: it.userName || it.user_name || 'غير معروف', action: it.action, module: it.module, recordId: it.recordId || '-', ipAddress: it.ipAddress || '-', details: it.details || {} }));
        users.value = (payload?.users || []).map(u => ({ id: u.id, name: u.name }));
        totalPages.value = payload?.pagination?.total_pages || 1;
    } catch { error.value = 'فشل في تحميل السجلات.'; } finally { isLoading.value = false; }
};

const handleSort = (k) => { if (sortKey.value === k) sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'; else { sortKey.value = k; sortOrder.value = 'asc'; } };
const setDateRange = (r) => { const end = new Date(); let start = new Date(); if (r === 'today') start.setHours(0,0,0,0); else start.setDate(end.getDate() - 7); endDate.value = getLocalDateISO(end); startDate.value = getLocalDateISO(start); };
const showDetailsModal = (log) => { selectedLog.value = log; showModal.value = true; };

const formatFieldValue = (k, v) => { if (v == null) return 'غير محدد'; if (k.includes('_date') || k.includes('_at')) return new Date(v).toLocaleString('en-US'); if (typeof v === 'number' && (k.includes('amount') || k.includes('price'))) return v.toLocaleString('en-US', { style: 'currency', currency: 'EGP' }); return v; };

const formatDetails = (details) => {
  try {
    let parsed = typeof details === 'string' ? JSON.parse(details) : details;
    const formatted = {};
    for (const [k, v] of Object.entries(parsed)) {
      const label = fieldLabels[k] || k;
      if (k === 'changes') {
          formatted[label] = {};
          for (const [f, c] of Object.entries(v)) { formatted[label][fieldLabels[f] || f] = { old: formatFieldValue(f, c.old), new: formatFieldValue(f, c.new) }; }
      } else { formatted[label] = formatFieldValue(k, v); }
    }
    return JSON.stringify(formatted, null, 2).replace(/[{}"]/g, '');
  } catch { return JSON.stringify(details); }
};

const fetchAndCacheData = async () => {
  try {
    // Users
    const userResponse = await userStore.fetchUsers();
    if (userResponse.status === 'success') {
      usersCache.value = userStore.users.reduce((acc, user) => {
        acc[user.id] = user.name || `User ${user.id}`;
        return acc;
      }, {});
    }

    // Suppliers
    await supplierStore.fetchSuppliers();
    suppliersCache.value = supplierStore.suppliers.reduce((acc, supplier) => {
      acc[supplier.id] = supplier.name || `Supplier ${supplier.id}`;
      return acc;
    }, {});

    // Payment Methods
    await paymentStore.fetchPaymentMethods();
    const items = paymentStore.paymentMethods;

    paymentMethodsCache.value = items.reduce((acc, method) => {
      acc[method.id] = method.name || `طريقة ${method.id}`;
      return acc;
    }, {});
  } catch (error) {
    console.error('Error fetching reference data:', error);
  }
};

onMounted(async () => { await Promise.all([fetchLogs(), fetchAndCacheData()]); });
watch([startDate, endDate, actionType, userId], () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchLogs, 700); });
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight; }

/* Modern UI Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.quick-range-btn { @apply px-3 py-1.5 rounded-xl bg-white text-slate-500 text-[10px] font-black hover:text-blue-600 hover:shadow-sm transition-all border-none; }

.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm border border-transparent; }

/* Modal & Transitions */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 lg:p-8; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>