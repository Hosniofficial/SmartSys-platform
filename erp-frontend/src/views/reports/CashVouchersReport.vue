<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-file-invoice-dollar text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تقرير الصرف والقبض</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل السندات المالية وحركة السيولة النقدية خلال الفترة</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="fetchReport" :disabled="isLoading" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث البيانات
        </button>
        <button class="px-6 py-2.5 rounded-xl text-xs font-black text-blue-600 hover:bg-blue-50 transition-all flex items-center gap-2">
          <i class="fas fa-print"></i> طباعة التقرير
        </button>
      </div>
    </div>

    <!-- Analytical Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end">
        <div class="space-y-2 group">
          <label class="filter-label">من تاريخ</label>
          <div class="relative">
            <input ref="startDateRef" type="date" v-model="startDate" class="form-input-modern font-bold text-sm h-12" />
            <i class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="startDateRef.showPicker()"></i>
          </div>
        </div>

        <div class="space-y-2 group">
          <label class="filter-label">إلى تاريخ</label>
          <div class="relative">
            <input ref="endDateRef" type="date" v-model="endDate" class="form-input-modern font-bold text-sm h-12" />
            <i class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="endDateRef.showPicker()"></i>
          </div>
        </div>

        <div class="space-y-2">
          <label class="filter-label">نوع السند المالي</label>
          <select v-model="voucherType" class="form-select-modern font-black text-sm h-12 cursor-pointer">
            <option value="all">كل أنواع السندات</option>
            <option value="قبض">سندات القبض (وارد)</option>
            <option value="صرف">سندات الصرف (صادر)</option>
          </select>
        </div>

        <div class="space-y-2 group">
          <label class="filter-label">البحث في الوصف أو المرجع</label>
          <div class="relative">
            <input v-model="search" type="text" class="form-input-modern pr-11 h-12 font-bold" placeholder="ابحث بالكلمات المفتاحية..." />
            <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Financial Summary Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
      <div class="kpi-card group border-r-4 border-r-emerald-500 bg-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-arrow-down"></i>
          </div>
          <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">إيرادات نقدية</span>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">إجمالي سندات القبض</p>
        <p class="text-2xl font-black tracking-tight leading-none font-mono text-emerald-600">
          {{ formatCurrency(summary.totalIn) }}
        </p>
      </div>

      <div class="kpi-card group border-r-4 border-r-rose-500 bg-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-arrow-up"></i>
          </div>
          <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">مصروفات نقدية</span>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">إجمالي سندات الصرف</p>
        <p class="text-2xl font-black tracking-tight leading-none font-mono text-rose-600">
          {{ formatCurrency(summary.totalOut) }}
        </p>
      </div>

      <div class="kpi-card group border-r-4 border-r-blue-600 bg-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-scale-balanced"></i>
          </div>
          <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">الصافي</span>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">صافي التدفق المالي (الرصيد)</p>
        <p class="text-2xl font-black tracking-tight leading-none font-mono text-slate-800">
          {{ formatCurrency(summary.balance) }}
        </p>
      </div>
    </section>

    <!-- Movements Detailed Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">سجل الحركات المالية التفصيلي</h3>
         <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">إجمالي السجلات: {{ vouchers.length }}</span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm font-cairo">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">تاريخ السند</th>
              <th class="px-4 py-5">الرقم المرجعي</th>
              <th class="px-4 py-5 text-center">نوع السند</th>
              <th class="px-4 py-5">الحساب المتأثر</th>
              <th class="px-4 py-5 text-left">قيمة السند</th>
              <th class="px-8 py-5">البيان / الوصف</th>
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
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!vouchers.length" class="text-center py-20">
              <td colspan="6" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-receipt text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase tracking-widest">لا توجد عمليات مسجلة في هذه الفترة</p>
                </div>
              </td>
            </tr>
            <tr v-for="voucher in vouchers" :key="voucher.id" class="hover:bg-blue-50/30 transition-all group border-r-4 border-r-transparent" :class="voucher.type === 'receipt' ? 'hover:border-r-emerald-500' : 'hover:border-r-rose-500'">
              <td class="px-8 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter group-hover:text-slate-900 transition-colors">
                {{ formatDate(voucher.date) }}
              </td>
              <td class="px-4 py-4 font-black text-slate-800 font-mono">
                {{ voucher.reference || `#${voucher.id}` }}
              </td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm', voucherTypeClass(voucher.type)]">
                   <i :class="[ (voucher.type === 'receipt' || voucher.type === 'قبض') ? 'fas fa-arrow-down' : 'fas fa-arrow-up', 'ml-1.5 text-[8px]' ]"></i>
                   {{ voucherTypeLabel(voucher.type) }}
                </span>
              </td>
              <td class="px-4 py-4">
                 <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-300 flex items-center justify-center text-[10px] group-hover:bg-white transition-all shadow-inner"><i class="fas fa-university"></i></div>
                    <span class="text-slate-700 leading-none truncate max-w-[180px]">{{ voucher.account_name }}</span>
                 </div>
              </td>
              <td class="px-4 py-4 text-left">
                <span class="font-black text-base font-mono tracking-tighter" :class=" (voucher.type === 'receipt' || voucher.type === 'قبض') ? 'text-emerald-600' : 'text-rose-600' ">
                  {{ formatCurrency(voucher.amount) }}
                </span>
              </td>
              <td class="px-8 py-4 text-xs text-slate-400 italic font-medium max-w-xs truncate" :title="voucher.description">
                {{ voucher.description || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useReportsStore } from '../../stores/reports';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement } from 'chart.js';
import { Line as LineChart } from 'vue-chartjs';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { getLocalDateISO } from '@/utils/date';
import { useVoucherStore } from '@/stores/voucher/voucherStore';

// --- Logic Initialization (STRICTLY PRESERVED) ---
ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement);

const reportsStore = useReportsStore();
const voucherStore = useVoucherStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

const startDate = ref(getLocalDateISO(new Date(new Date().setDate(1))));
const endDate = ref(getLocalDateISO());
const startDateRef = ref(null);
const endDateRef = ref(null);
const voucherType = ref('all'); 
const search = ref('');
let debounceTimer = null;

const vouchers = ref([]);

// Logic: Summary Calculation (STRICTLY PRESERVED)
const summary = computed(() => {
  let totalIn = 0, totalOut = 0;
  for (const v of vouchers.value) {
    if (v.type === 'receipt' || v.type === 'قبض') totalIn += Number(v.amount) || 0;
    else if (v.type === 'payment' || v.type === 'صرف') totalOut += Number(v.amount) || 0;
  }
  return { totalIn, totalOut, balance: totalIn - totalOut };
});

// Logic: API Data Fetching (STRICTLY PRESERVED)
const fetchReport = async () => {
  isLoading.value = true; error.value = null;
  try {
    const params = { start_date: startDate.value, end_date: endDate.value };
    if (voucherType.value !== 'all') params.type = voucherType.value;
    if (search.value) params.search = search.value;
    const list = await voucherStore.fetchVouchersList({ 
      type: voucherType.value !== 'all' ? voucherType.value : undefined,
      dateFrom: startDate.value,
      dateTo: endDate.value,
      search: search.value || undefined
    });
    vouchers.value = Array.isArray(list) ? list : [];
  } catch (e) {
    error.value = 'حدث خطأ أثناء تحميل البيانات';
  } finally {
    isLoading.value = false;
  }
};

// --- Handlers & Formatting ---
const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatDate = (v) => v ? new Date(v).toLocaleDateString('en-US') : '';
const voucherTypeLabel = (t) => (t === 'receipt' || t === 'قبض') ? 'سند قبض' : 'سند صرف';
const voucherTypeClass = (t) => (t === 'receipt' || t === 'قبض') ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700';

watch([startDate, endDate, voucherType, search], () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fetchReport, 500);
});

onMounted(async () => { await Promise.all([fetchSettings(), fetchReport()]); });
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-card { @apply p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight font-mono; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply h-12 bg-white border border-slate-200 rounded-2xl px-4 outline-none appearance-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.status-badge { @apply shadow-sm inline-flex items-center; }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>