<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-chart-line text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تحليلات المبيعات المتقدمة</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">رؤية شاملة لأداء المنتجات، حركة المبيعات، وتحليل الربحية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="fetchAnalytics" :disabled="isLoading" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث البيانات
        </button>
      </div>
    </div>

    <!-- Analytical Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8">
        <div class="flex flex-wrap gap-6 items-end">
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
            <label class="filter-label">نطاقات زمنية سريعة</label>
            <div class="flex items-center gap-2 bg-slate-50 p-1 rounded-xl border border-slate-100">
              <button @click="setDateRange('week')" class="quick-range-btn">آخر 7 أيام</button>
              <button @click="setDateRange('month')" class="quick-range-btn">آخر 30 يوماً</button>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 bg-blue-50 px-4 py-2 rounded-xl border border-blue-100">
           <div class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>
           <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest leading-none">مباشر: تحليل البيانات اللحظي</span>
        </div>
      </div>
    </div>

    <!-- Summary Statistics Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
      <div v-for="card in summaryCards" :key="card.title" class="kpi-card group" :class="card.color.replace('text', 'border-r-4 border-r')">
        <div class="flex items-center justify-between mb-4">
          <div :class="[card.bg, card.color, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110']">
            <i :class="card.icon"></i>
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">{{ card.title }}</p>
        <p :class="[card.color, 'text-2xl font-black tracking-tight leading-none font-mono']">
          {{ card.format === 'currency' ? formatCurrency(card.value) : formatNumber(card.value) }}
        </p>
      </div>
    </section>

    <!-- Main Performance Chart -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-10 hover:shadow-xl transition-shadow duration-500 relative overflow-hidden">
      <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50/50 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
      
      <div class="flex items-center justify-between mb-10 relative z-10">
         <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
           <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
           أداء المبيعات اليومي (Trend)
         </h2>
         <div class="flex items-center gap-2">
            <span class="text-[9px] font-black text-slate-300 uppercase tracking-[0.2em] border border-slate-100 px-3 py-1 rounded-lg">إيرادات الفترة</span>
         </div>
      </div>

      <div class="h-[380px] relative z-10">
        <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-3xl z-20">
           <BaseSpinner color="#2563eb" />
        </div>
        <LineChart v-if="analyticsData.salesOverTime.length" :data="salesOverTimeChart" :options="chartOptions" />
        <div v-else class="h-full flex flex-col items-center justify-center opacity-30 italic">بانتظار مزامنة البيانات...</div>
      </div>
    </section>
    
    <!-- Distribution & Insights Grid -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">
        <!-- Top Products Analysis -->
        <div class="lg:col-span-8 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
             <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                  <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
                  الأصناف الأكثر مبيعاً
                </h3>
             </div>
             <div class="grid grid-cols-1 xl:grid-cols-2 gap-10">
                 <!-- Bar Chart -->
                 <div class="h-[300px]">
                    <BarChart v-if="!isLoading" :data="topProductsChart" :options="chartOptions" />
                 </div>
                 <!-- Mini Table -->
                 <div class="bg-slate-50/50 rounded-[1.5rem] border border-slate-50 overflow-hidden">
                    <table class="w-full text-right text-xs">
                        <thead>
                          <tr class="bg-slate-100/50 text-slate-500 font-black uppercase tracking-tighter">
                            <th class="px-5 py-4">اسم المنتج</th>
                            <th class="px-5 py-4 text-center">الكمية</th>
                            <th class="px-5 py-4 text-left">قيمة المبيعات</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <tr v-for="product in analyticsData.topProducts.slice(0, 8)" :key="product.id" class="hover:bg-white transition-colors">
                                <td class="px-5 py-3.5 text-slate-700 leading-tight">{{ product.name }}</td>
                                <td class="px-5 py-3.5 text-center text-emerald-600 font-mono tracking-tighter">{{ formatNumber(product.quantity) }}</td>
                                <td class="px-5 py-3.5 text-left text-blue-600 font-mono tracking-tighter">{{ formatCurrency(product.totalSales) }}</td>
                            </tr>
                        </tbody>
                    </table>
                 </div>
             </div>
        </div>

        <!-- Category Distribution -->
        <div class="lg:col-span-4 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500 flex flex-col">
             <div class="flex items-center justify-between mb-8">
                <h3 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                  <span class="w-1.5 h-6 bg-emerald-600 rounded-full"></span>
                  توزيع الفئات
                </h3>
             </div>
             <div class="flex-grow flex items-center justify-center min-h-[300px]">
               <DoughnutChart v-if="!isLoading" :data="salesByCategoryChart" :options="doughnutOptions" />
             </div>
             <div class="mt-6 pt-6 border-t border-slate-50 grid grid-cols-2 gap-4">
                <div v-for="(cat, idx) in analyticsData.salesByCategory.slice(0, 4)" :key="cat.id" class="flex items-center gap-2">
                   <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'][idx % 4] }"></span>
                   <span class="text-[10px] font-black text-slate-400 uppercase truncate">{{ cat.name }}</span>
                </div>
             </div>
        </div>
    </section>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useReportsStore } from '../../stores/reports';
import { useAnalyticsStore } from '../../stores/analytics';
import { Line as LineChart, Bar as BarChart, Doughnut as DoughnutChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, BarElement, CategoryScale, LinearScale, PointElement, ArcElement, Filler } from 'chart.js';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { getLocalDateISO } from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// --- Logic ---
ChartJS.register(Title, Tooltip, Legend, LineElement, BarElement, CategoryScale, LinearScale, PointElement, ArcElement, Filler);

const reportsStore = useReportsStore();
const analyticsStore = useAnalyticsStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

const startDate = ref(getLocalDateISO(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)));
const endDate = ref(getLocalDateISO());
const startDateRef = ref(null);
const endDateRef = ref(null);
let debounceTimer = null;

const analyticsData = ref({
  summary: { totalSales: 0, totalReturns: 0, totalRevenue: 0, totalTax: 0, grandTotal: 0, netProfit: 0 },
  salesOverTime: [],
  topProducts: [],
  salesByCategory: []
});

// Logic: KPI Cards (Calculations Preserved)
const summaryCards = computed(() => [
  { title: 'إجمالي المبيعات', value: analyticsData.value.summary.totalSales, format: 'currency', icon: 'fas fa-shopping-cart', color: 'text-emerald-700', bg: 'bg-emerald-50' },
  { title: 'إجمالي المرتجعات', value: analyticsData.value.summary.totalReturns, format: 'currency', icon: 'fas fa-undo', color: 'text-rose-700', bg: 'bg-rose-50' },
  { title: 'صافي المبيعات (قبل الضريبة)', value: analyticsData.value.summary.totalRevenue, format: 'currency', icon: 'fas fa-chart-line', color: 'text-blue-700', bg: 'bg-blue-50' },
  { title: 'ضريبة القيمة المضافة', value: analyticsData.value.summary.totalTax, format: 'currency', icon: 'fas fa-receipt', color: 'text-indigo-700', bg: 'bg-indigo-50' },
  { title: 'الإجمالي (شامل الضريبة)', value: analyticsData.value.summary.grandTotal, format: 'currency', icon: 'fas fa-dollar-sign', color: 'text-slate-800', bg: 'bg-slate-100' },
  { title: 'صافي الأرباح التشغيلية', value: analyticsData.value.summary.netProfit, format: 'currency', icon: 'fas fa-wallet', color: 'text-amber-700', bg: 'bg-amber-50' }
]);

// Logic: Charts Data Generation (STRICTLY PRESERVED)
const salesOverTimeChart = computed(() => ({
  labels: analyticsData.value.salesOverTime.map(d => formatDate(d.date, { month: 'short', day: 'numeric' })),
  datasets: [{ label: 'المبيعات اليومية', data: analyticsData.value.salesOverTime.map(d => d.total), borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.08)', fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6 }]
}));

const topProductsChart = computed(() => ({
    labels: analyticsData.value.topProducts.slice(0, 5).map(p => p.name),
    datasets: [{ label: 'الإيرادات', data: analyticsData.value.topProducts.slice(0, 5).map(p => p.totalSales), backgroundColor: ['#2563eb', '#4f46e5', '#7c3aed', '#db2777', '#ea580c'], borderRadius: 12, maxBarThickness: 32 }]
}));

const salesByCategoryChart = computed(() => ({
    labels: analyticsData.value.salesByCategory.map(c => c.name),
    datasets: [{ data: analyticsData.value.salesByCategory.map(c => c.totalSales), backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#6366f1', '#8b5cf6'], borderWidth: 0 }]
}));

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } }, x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '75%' };

const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');
const formatDate = (v, o = {}) => v ? new Date(v).toLocaleDateString('en-US', o) : '';

// Logic: API Call (STRICTLY PRESERVED)
const fetchAnalytics = async () => {
    isLoading.value = true; error.value = null;
    try {
        let apiData = await analyticsStore.fetchSalesAnalytics({ 
        startDate: startDate.value, 
        endDate: endDate.value 
    });
        if (apiData && apiData.data) apiData = apiData.data;
        
        const summary = apiData.summary || {};
        const revenue = apiData.revenue || {};
        const totalSales = apiData.total_sales_amount ?? 0;
        const totalReturns = apiData.total_returns_amount ?? 0;
        const totalRevenue = apiData.net_sales ?? (totalSales - totalReturns) ?? 0;
        const totalTax = apiData.total_tax_amount ?? 0;
        const netTax = totalTax - (apiData.total_returns_tax ?? 0);
        const grandTotal = apiData.net_grand_total ?? (totalRevenue + netTax) ?? 0;
        const grossProfit = summary.gross_profit ?? revenue.gross_profit ?? apiData.gross_profit ?? 0;
        const netProfit = summary.net_profit ?? apiData.net_profit ?? grossProfit;

        analyticsData.value = {
            summary: { totalSales, totalReturns, totalRevenue, totalTax: netTax, grandTotal, netProfit, grossProfit },
            salesOverTime: (apiData.daily_sales || apiData.salesOverTime || []).map(day => ({ date: day.date, total: day.total_revenue ?? day.total ?? 0 })),
            topProducts: (apiData.top_products || apiData.topProducts || []).map(p => ({ id: p.id, name: p.name, totalSales: p.total_revenue ?? p.totalSales ?? 0, quantity: p.total_quantity ?? p.quantity ?? 0 })),
            salesByCategory: (apiData.top_categories || apiData.salesByCategory || []).map(c => ({ id: c.id, name: c.name, totalSales: c.total_revenue ?? c.totalSales ?? 0 }))
        };
    } catch { error.value = "خطأ في التحميل"; } finally { isLoading.value = false; }
};

const setDateRange = (range) => {
    const end = new Date(); let start = new Date();
    if (range === 'week') start.setDate(end.getDate() - 7);
    else if (range === 'month') start.setMonth(end.getMonth() - 1);
    endDate.value = getLocalDateISO(end); startDate.value = getLocalDateISO(start);
};

watch([startDate, endDate], () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchAnalytics, 700); });
onMounted(async () => { await Promise.all([fetchSettings(), fetchAnalytics()]); });
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight font-mono; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.quick-range-btn { @apply px-4 py-2 rounded-xl text-[10px] font-black text-slate-400 hover:bg-white hover:text-blue-600 transition-all border-none; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>