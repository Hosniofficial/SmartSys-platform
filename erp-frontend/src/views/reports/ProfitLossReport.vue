<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center shadow-xl shadow-slate-200 text-white shrink-0">
          <i class="fas fa-chart-pie text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تقرير الأرباح والخسائر</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل القوائم المالية، هوامش الربح، والمقارنات الدورية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="fetchReport" :disabled="isLoading" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث التقرير
        </button>
      </div>
    </div>

    <!-- Smart Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8">
        <div class="flex flex-wrap gap-6 items-end">
          <div class="space-y-2 group">
            <label class="filter-label">من تاريخ</label>
            <div class="relative">
              <input ref="startDateRef" type="date" v-model="startDate" class="form-input-modern font-bold text-sm" />
              <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="startDateRef.showPicker()"></i>
            </div>
          </div>

          <div class="space-y-2 group">
            <label class="filter-label">إلى تاريخ</label>
            <div class="relative">
              <input ref="endDateRef" type="date" v-model="endDate" class="form-input-modern font-bold text-sm" />
              <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="endDateRef.showPicker()"></i>
            </div>
          </div>

          <div class="space-y-2">
            <label class="filter-label">نطاقات زمنية سريعة</label>
            <div class="flex items-center gap-2 bg-slate-50 p-1 rounded-xl border border-slate-100">
              <button @click="setDateRange('month')" class="quick-range-btn">هذا الشهر</button>
              <button @click="setDateRange('quarter')" class="quick-range-btn">آخر 3 أشهر</button>
              <button @click="setDateRange('year')" class="quick-range-btn">هذا العام</button>
            </div>
          </div>
        </div>

        <div class="pb-1">
          <label class="relative flex items-center gap-4 p-4 rounded-2xl bg-slate-900 text-white shadow-xl shadow-slate-200 border border-slate-800 cursor-pointer transition-all hover:scale-[1.02] group">
            <div class="flex items-center gap-3">
               <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform"><i class="fas fa-layer-group"></i></div>
               <span class="text-xs font-black uppercase tracking-tight">مقارنة بالفترة السابقة</span>
            </div>
            <input type="checkbox" v-model="compareEnabled" class="w-5 h-5 rounded-lg border-white/20 bg-white/10 text-blue-500 focus:ring-0 cursor-pointer transition-all" />
          </label>
        </div>
      </div>
    </div>

    <!-- Summary Performance Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div v-for="card in summaryCards" :key="card.title" class="kpi-card group" :class="card.value >= 0 || card.title.includes('المصروفات') ? 'border-r-4' : 'border-r-4 border-rose-500'">
        <div class="flex items-center justify-between mb-4">
          <div :class="[card.iconClass, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110']">
            <i :class="card.icon"></i>
          </div>
          <div v-if="compareEnabled" :class="[card.change >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600']" class="px-2.5 py-1 rounded-lg text-[10px] font-black tracking-tighter shadow-sm border border-white">
            <i class="fas" :class="card.change >= 0 ? 'fa-caret-up' : 'fa-caret-down'"></i>
            {{ formatPercentage(Math.abs(card.change)) }}
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">{{ card.title }}</p>
        <p class="text-2xl font-black tracking-tight leading-none font-mono" :class="[card.title === 'إجمالي المصروفات' ? 'text-slate-800' : (card.value >= 0 ? 'text-slate-800' : 'text-rose-600')]">
          {{ card.format === 'percentage' ? formatPercentage(card.value) : formatCurrency(card.value) }}
        </p>
        <div v-if="compareEnabled" class="mt-4 pt-3 border-t border-slate-50 text-[9px] font-bold text-slate-400 uppercase">مقارنة بالأداء السابق</div>
      </div>
    </section>

    <!-- Analytical Charts Area -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">
      <div class="lg:col-span-8 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
            الأداء المالي عبر الزمن
          </h2>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-lg border border-slate-100">مقارنة ذكية</span>
        </div>
        <div class="h-[350px] relative">
          <LineChart v-if="!isLoading" :data="lineChartData" :options="lineChartOptions" />
        </div>
      </div>

      <div class="lg:col-span-4 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
            توزيع المصروفات
          </h2>
        </div>
        <div class="h-[350px] relative">
          <DoughnutChart v-if="!isLoading" :data="doughnutChartData" :options="doughnutChartOptions" />
        </div>
      </div>
    </section>

    <!-- Detailed Financial Statement Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">قائمة الدخل التفصيلية</h3>
         <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">مستخرج من السجل المحاسبي</span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <!-- Section: Revenue -->
          <thead class="bg-slate-50/50">
            <tr><th colspan="3" class="px-8 py-4 text-emerald-700 font-black uppercase tracking-widest text-xs border-b border-emerald-50">١. سجل الإيرادات</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="item in currentPeriodData.revenue" :key="item.name" class="hover:bg-slate-50/50 transition-colors group">
              <td class="px-8 py-4 font-black text-slate-700">{{ item.name }}</td>
              <td class="px-6 py-4 font-black font-mono tracking-tighter text-left text-base">{{ formatCurrency(item.amount) }}</td>
              <td class="px-8 py-4 text-left w-48">
                <span v-if="compareEnabled" :class="calculateChange(item.amount, (previousPeriodData.revenue.find(p => p.name === item.name) || {amount:0}).amount) >= 0 ? 'text-emerald-500' : 'text-rose-500'" class="text-[10px] font-black bg-white px-2 py-1 rounded-lg border border-slate-100 shadow-sm">
                  {{ formatPercentage(calculateChange(item.amount, (previousPeriodData.revenue.find(p => p.name === item.name) || {amount:0}).amount)) }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-emerald-50/30 font-black border-y border-emerald-50">
            <tr>
              <td class="px-8 py-5 text-emerald-800 text-sm">إجمالي الإيرادات (Total Revenue)</td>
              <td class="px-6 py-5 text-emerald-600 font-mono text-left text-lg">{{ formatCurrency(currentSummary.totalRevenue) }}</td>
              <td class="px-8 py-5 text-left">
                <span v-if="compareEnabled" :class="calculateChange(currentSummary.totalRevenue, previousSummary.totalRevenue) >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-mono text-xs">
                  {{ formatPercentage(calculateChange(currentSummary.totalRevenue, previousSummary.totalRevenue)) }}
                </span>
              </td>
            </tr>
          </tfoot>

          <!-- Section: COGS -->
          <thead class="bg-slate-50/50">
            <tr><th colspan="3" class="px-8 py-4 text-amber-700 font-black uppercase tracking-widest text-xs border-b border-amber-50">٢. تكلفة البضاعة المباعة (COGS)</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr class="font-bold">
              <td class="px-8 py-5 text-slate-800">تكلفة المبيعات المباشرة</td>
              <td class="px-6 py-5 text-amber-600 font-mono text-left text-base">{{ formatCurrency(currentSummary.totalRevenue - currentSummary.grossProfit) }}</td>
              <td class="px-8 py-5 text-left">
                <span v-if="compareEnabled" class="text-amber-500 font-mono text-xs">
                  {{ formatPercentage(calculateChange(currentSummary.totalRevenue - currentSummary.grossProfit, previousSummary.totalRevenue - previousSummary.grossProfit)) }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-amber-50/20 font-black border-y border-amber-50/50">
            <tr>
              <td class="px-8 py-5 text-slate-900">إجمالي الربح (Gross Profit)</td>
              <td class="px-6 py-5 text-slate-900 font-mono text-left text-lg">{{ formatCurrency(currentSummary.grossProfit) }}</td>
              <td class="px-8 py-5 text-left">
                <span v-if="compareEnabled" :class="calculateChange(currentSummary.grossProfit, previousSummary.grossProfit) >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-mono text-xs">
                  {{ formatPercentage(calculateChange(currentSummary.grossProfit, previousSummary.grossProfit)) }}
                </span>
              </td>
            </tr>
          </tfoot>

          <!-- Section: Expenses -->
          <thead class="bg-slate-50/50">
            <tr><th colspan="3" class="px-8 py-4 text-rose-700 font-black uppercase tracking-widest text-xs border-b border-rose-50">٣. سجل المصروفات التشغيلية</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold">
            <tr v-for="item in currentPeriodData.expenses" :key="item.name" class="hover:bg-slate-50/50 transition-colors group">
              <td class="px-8 py-4 text-slate-600">{{ item.name }}</td>
              <td class="px-6 py-4 font-mono tracking-tighter text-left text-slate-800">{{ formatCurrency(item.amount) }}</td>
              <td class="px-8 py-4 text-left w-48">
                <span v-if="compareEnabled" class="text-rose-400 font-mono text-[10px]">
                  {{ formatPercentage(calculateChange(item.amount, (previousPeriodData.expenses.find(p => p.name === item.name) || {amount:0}).amount)) }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-rose-50/30 font-black border-y border-rose-50">
            <tr>
              <td class="px-8 py-5 text-rose-800 text-sm">إجمالي المصروفات (Total Expenses)</td>
              <td class="px-6 py-5 text-rose-700 font-mono text-left text-lg">{{ formatCurrency(currentSummary.totalExpenses) }}</td>
              <td class="px-8 py-5 text-left">
                 <span v-if="compareEnabled" class="text-rose-600 font-mono text-xs">
                   {{ formatPercentage(calculateChange(currentSummary.totalExpenses, previousSummary.totalExpenses)) }}
                 </span>
              </td>
            </tr>
          </tfoot>

          <!-- Section: Final Net Profit -->
          <tfoot class="bg-slate-900 text-white font-black">
            <tr class="border-t-4 border-white/5">
              <td class="px-8 py-8 text-lg">صافي الربح النهائي (Net Profit)</td>
              <td class="px-6 py-8 text-blue-400 font-mono text-left text-3xl tracking-tighter">{{ formatCurrency(currentSummary.netProfit) }}</td>
              <td class="px-8 py-8 text-left">
                 <div v-if="compareEnabled" class="flex flex-col items-start gap-1">
                    <span :class="calculateChange(currentSummary.netProfit, previousSummary.netProfit) >= 0 ? 'text-emerald-400' : 'text-rose-400'" class="text-sm font-mono leading-none">
                      {{ formatPercentage(calculateChange(currentSummary.netProfit, previousSummary.netProfit)) }}
                    </span>
                    <span class="text-[9px] text-white/30 uppercase tracking-widest leading-none mt-1">تغير الصافي</span>
                 </div>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useReportsStore } from '../../stores/reports';
import { Line as LineChart, Doughnut as DoughnutChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, ArcElement } from 'chart.js';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { getLocalDateISO } from '@/utils/date';

// --- Chart.js Registration ---
ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, ArcElement);

// --- Store & State ---
const reportsStore = useReportsStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

// --- Filters State ---
const today = new Date();
const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const startDate = ref(getLocalDateISO(firstDayOfMonth)); // Start of current month (local)
const endDate = ref(getLocalDateISO(today));
const startDateRef = ref(null);
const endDateRef = ref(null);
const compareEnabled = ref(false);
let debounceTimer = null;

// --- Data State ---
const currentPeriodData = ref({ revenue: [], expenses: [] });
const previousPeriodData = ref({ revenue: [], expenses: [] });

// --- Computed Properties for Data Analysis ---
const createSummary = (data) => {
  // استخدام البيانات من meta إذا كانت موجودة، وإلا احسبها من المصفوفات
  const totalRevenue = Number(data.meta?.totalRevenue ?? data.revenue.reduce((sum, item) => sum + (item.amount || 0), 0));
  const grossProfit = Number(data.meta?.grossProfit ?? 0);
  const cogs = Number(data.meta?.cogs ?? 0);
  
  // إجمالي المصروفات - استخدم من meta أو احسبها
  const totalExpenses = Number(data.meta?.totalExpenses ?? data.expenses.reduce((sum, item) => sum + (item.amount || 0), 0));
  
  // صافي الربح - استخدم من meta أو احسبه
  const netProfit = Number(data.meta?.netProfit ?? (grossProfit - totalExpenses));
  
  const grossProfitMargin = totalRevenue > 0 ? (grossProfit / totalRevenue) * 100 : 0;
  const netProfitMargin = totalRevenue > 0 ? (netProfit / totalRevenue) * 100 : 0;
  
  return { 
    totalRevenue, 
    totalExpenses, 
    grossProfit, 
    cogs,
    netProfit, 
    grossProfitMargin, 
    netProfitMargin 
  };
};

const currentSummary = computed(() => createSummary(currentPeriodData.value));
const previousSummary = computed(() => createSummary(previousPeriodData.value));

const calculateChange = (current, previous) => {
  if (previous === 0) return current > 0 ? 100 : 0; // Avoid division by zero
  return ((current - previous) / previous) * 100;
};

// --- Summary Cards with Comparison (أهم 4 مؤشرات فقط) ---
const summaryCards = computed(() => {
    const { totalRevenue, totalExpenses, netProfit, netProfitMargin } = currentSummary.value;
    const prev = previousSummary.value;
    return [
        { title: 'إجمالي الإيرادات', value: totalRevenue, change: calculateChange(totalRevenue, prev.totalRevenue), format: 'currency', icon: 'fas fa-chart-line', iconClass: 'bg-green-100 text-green-700' },
        { title: 'إجمالي المصروفات', value: totalExpenses, change: calculateChange(totalExpenses, prev.totalExpenses), format: 'currency', icon: 'fas fa-arrow-trend-down', iconClass: 'bg-red-100 text-red-700' },
        { title: 'صافي الربح', value: netProfit, change: calculateChange(netProfit, prev.netProfit), format: 'currency', icon: 'fas fa-wallet', iconClass: 'bg-blue-100 text-blue-700' },
        { title: 'هامش صافي الربح', value: netProfitMargin, change: netProfitMargin - prev.netProfitMargin, format: 'percentage', icon: 'fas fa-percent', iconClass: 'bg-purple-100 text-purple-700' }
    ];
});

// --- Chart Data & Options ---
const lineChartData = computed(() => ({
    labels: ['الفترة السابقة', 'الفترة الحالية'],
    datasets: [
        { label: 'الإيرادات', data: [previousSummary.value.totalRevenue, currentSummary.value.totalRevenue], borderColor: '#10B981', backgroundColor: '#A7F3D0', tension: 0.1 },
        { label: 'المصروفات', data: [previousSummary.value.totalExpenses, currentSummary.value.totalExpenses], borderColor: '#EF4444', backgroundColor: '#FECACA', tension: 0.1 },
        { label: 'صافي الربح', data: [previousSummary.value.netProfit, currentSummary.value.netProfit], borderColor: '#3B82F6', backgroundColor: '#BFDBFE', tension: 0.1, type: 'line', fill: false, borderWidth: 2 }
    ]
}));

const doughnutChartData = computed(() => {
    const labels = currentPeriodData.value.expenses.map(e => e.name);
    const data = currentPeriodData.value.expenses.map(e => e.amount);
    return {
        labels,
        datasets: [{
            data,
            backgroundColor: ['#6366F1', '#A855F7', '#D946EF', '#EC4899', '#F97316', '#F59E0B'],
        }]
    };
});

const lineChartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
const doughnutChartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'left' } } };

// --- Helper Methods ---
const formatCurrency = (value) => formatCurrencyLocale(value, 0);
const formatPercentage = (value) => `${(Number(value) || 0).toFixed(1)}%`;

// --- Actions & API ---
// Helper to map API shape to component shape
const mapApiToPeriodData = (apiData) => {
    // Handle nested data structure from store
    let data = apiData;
    
    // If apiData has status/data structure from store
    if (data && data.data) {
        data = data.data;
    }
    
    // If there's another nested data level
    if (data && data.data) {
        data = data.data;
    }
    
    console.log('[mapApiToPeriodData] Processing data:', data);
    
    // Extract expenses from account_transactions
    const expensesRaw = data?.account_transactions || data?.expenses || [];
    const expenses = expensesRaw.map(e => ({
        name: e.expense_category || e.name || 'مصروفات أخرى',
        amount: Number(e.total ?? e.amount ?? 0)
    }));
    
    // Extract revenue data - try both revenue and summary paths
    const totalRevenue = Number(
        data?.revenue?.total_revenue ?? 
        data?.summary?.total_revenue ?? 
        0
    );
    
    const grossProfit = Number(
        data?.revenue?.gross_profit ?? 
        data?.summary?.gross_profit ?? 
        0
    );
    
    const cogs = Number(
        data?.revenue?.cogs ?? 
        data?.summary?.cogs ?? 
        0
    );
    
    const totalExpenses = Number(
        data?.summary?.total_expenses ?? 
        0
    );
    
    const netProfit = Number(
        data?.summary?.net_profit ?? 
        0
    );
    
    console.log('[mapApiToPeriodData] Extracted:', { 
        totalRevenue, grossProfit, cogs, totalExpenses, netProfit 
    });
    
    const revenue = [
        { name: 'إجمالي الإيرادات', amount: totalRevenue }
    ];
    
    return { 
        revenue, 
        expenses, 
        meta: { 
            totalRevenue, 
            grossProfit, 
            cogs,
            totalExpenses,
            netProfit
        } 
    };
};

const fetchReport = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        console.log('[ProfitLoss] Fetching report:', { 
            startDate: startDate.value, 
            endDate: endDate.value 
        });
        
        // استدعاء البيانات من الواجهة الخلفية للفترة الحالية
        const current = await reportsStore.fetchProfitLossReport(startDate.value, endDate.value);
        console.log('[ProfitLoss] API Response:', current);
        
        const mappedData = mapApiToPeriodData(current);
        console.log('[ProfitLoss] Mapped Data:', mappedData);
        
        currentPeriodData.value = mappedData;

        // إذا كان وضع المقارنة مفعل، اجلب بيانات الفترة السابقة
        if (compareEnabled.value) {
            // احسب فترة المقارنة (نفس عدد الأيام السابقة)
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);
            const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            const prevEnd = new Date(start);
            prevEnd.setDate(start.getDate() - 1);
            const prevStart = new Date(start);
            prevStart.setDate(start.getDate() - diffDays);
            // الصيغة yyyy-mm-dd
            const prevStartStr = getLocalDateISO(prevStart);
            const prevEndStr = getLocalDateISO(prevEnd);
            
            console.log('[ProfitLoss] Previous period:', { 
                prevStartStr, 
                prevEndStr, 
                diffDays 
            });
            
            const previous = await reportsStore.fetchProfitLossReport(prevStartStr, prevEndStr);
            previousPeriodData.value = mapApiToPeriodData(previous);
        } else {
            previousPeriodData.value = { revenue: [], expenses: [], meta: {} };
        }
    } catch (err) {
        error.value = 'فشل في تحميل التقرير.';
        console.error(err);
    } finally {
        isLoading.value = false;
    }
};

const setDateRange = (range) => {
    const end = new Date();
    let start = new Date();

    if (range === 'month') {
        start = new Date(end.getFullYear(), end.getMonth(), 1);
    } else if (range === 'quarter') {
        start.setMonth(end.getMonth() - 3);
    } else if (range === 'year') {
        start = new Date(end.getFullYear(), 0, 1);
    }
    
    endDate.value = getLocalDateISO(end);
    startDate.value = getLocalDateISO(start);
};

// --- Watchers for Auto-Update ---
watch([startDate, endDate, compareEnabled], () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fetchReport, 700);
});

// --- Lifecycle Hook ---
onMounted(async () => { await Promise.all([fetchSettings(), fetchReport()]); });

</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Cards */
.kpi-card { @apply bg-white p-7 rounded-[2.5rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight; }

/* Modern UI Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.quick-range-btn { @apply px-3 py-1.5 rounded-xl text-[10px] font-black text-slate-500 hover:bg-white hover:text-blue-600 hover:shadow-sm transition-all; }

/* Table Badges */
.status-badge { @apply shadow-sm inline-block; }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>