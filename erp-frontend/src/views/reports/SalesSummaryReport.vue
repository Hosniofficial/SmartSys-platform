<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-chart-line text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">ملخص المبيعات اليومي</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">متابعة أداء المبيعات، الربحية، وحركة النقدية في نقاط البيع</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="viewYesterday" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all border-l border-slate-50">عرض يوم أمس</button>
        <button @click="fetchSalesSummary" :disabled="isLoading" class="w-11 h-11 bg-white text-slate-400 rounded-xl flex items-center justify-center hover:text-blue-600 transition-all active:scale-90">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i>
        </button>
      </div>
    </div>

    <!-- Analytical Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-lg border border-slate-200 p-8 mb-8 overflow-visible">
      <!-- Filter Header -->
      <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-100">
          <i class="fas fa-filter text-white text-sm"></i>
        </div>
        <div>
          <h3 class="text-base font-black text-slate-900 leading-none">فلاتر التقرير</h3>
          <p class="text-[10px] text-slate-400 mt-1 font-bold">تخصيص عرض البيانات حسب احتياجاتك</p>
        </div>
        
        <!-- Clear Filters -->
        <button 
          v-if="hasActiveFilters" 
          @click="clearFilters" 
          class="mr-auto px-4 py-2 rounded-xl text-xs font-black text-rose-600 hover:bg-rose-50 transition-all flex items-center gap-2 border border-rose-200"
        >
          <i class="fas fa-times-circle"></i>
          <span>مسح الفلاتر</span>
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6 items-end">
        
        <!-- Date Selector -->
        <div class="lg:col-span-2 space-y-2">
          <label class="filter-label flex items-center gap-2">
            <i class="fas fa-calendar-alt text-blue-500 text-xs"></i>
            تاريخ التقرير
          </label>
          <div class="relative group">
            <input 
              ref="selectedDateRef" 
              type="date" 
              v-model="selectedDate" 
              class="form-input-modern font-bold text-sm h-12 pr-11" 
            />
            <div class="absolute right-4 inset-y-0 flex items-center pointer-events-none">
              <i class="fas fa-calendar-day text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>
        </div>

        <!-- POS Multi-selector -->
        <div class="lg:col-span-3 space-y-2">
          <label class="filter-label flex items-center gap-2">
            <i class="fas fa-store text-emerald-500 text-xs"></i>
            نقاط البيع (POS)
            <span v-if="posIds.length" class="mr-auto px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-black">
              {{ posIds.length }} محدد
            </span>
          </label>
          <div class="relative" v-click-outside="() => posDropdownOpen = false">
            <div 
              @click="posDropdownOpen = !posDropdownOpen"
              class="bg-slate-50 border border-slate-200 rounded-2xl px-4 h-12 flex items-center cursor-pointer hover:border-emerald-400 focus-within:bg-white focus-within:ring-4 focus-within:ring-emerald-50 focus-within:border-emerald-500 transition-all"
              :class="{'border-emerald-500 ring-4 ring-emerald-50': posDropdownOpen}"
            >
              <i class="fas fa-store text-slate-400 ml-2"></i>
              <span class="flex-grow text-xs font-black text-slate-700" v-if="posIds.length">
                {{ posIds.length === posOptions.length ? 'جميع نقاط البيع' : `${posIds.length} نقطة بيع محددة` }}
              </span>
              <span class="flex-grow text-xs font-bold text-slate-400" v-else>اختر نقاط البيع...</span>
              <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform" :class="{'rotate-180': posDropdownOpen}"></i>
            </div>
            
            <!-- Multi-select Dropdown -->
            <transition name="dropdown">
              <div v-if="posDropdownOpen" class="absolute z-50 mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-64 overflow-hidden flex flex-col">
                <!-- Search -->
                <div class="p-3 border-b border-slate-100">
                  <div class="relative">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input 
                      type="text" 
                      v-model="posSearch" 
                      class="w-full pr-9 pl-3 py-2 text-xs font-bold border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" 
                      placeholder="ابحث في نقاط البيع..."
                      @click.stop
                    />
                  </div>
                </div>
                
                <!-- Select All -->
                <div class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 transition-colors cursor-pointer" @click="toggleAllPos">
                  <div class="flex items-center gap-3">
                    <input 
                      type="checkbox" 
                      :checked="posIds.length === posOptions.length && posOptions.length > 0" 
                      class="w-4 h-4 rounded text-emerald-600 focus:ring-2 focus:ring-emerald-500" 
                      @click.stop="toggleAllPos"
                    />
                    <span class="text-xs font-black text-slate-700">تحديد الكل</span>
                  </div>
                </div>
                
                <!-- Options List -->
                <div class="overflow-y-auto custom-scroll flex-grow">
                  <div 
                    v-for="opt in filteredPosOptions" 
                    :key="opt.id" 
                    class="px-4 py-2.5 flex items-center gap-3 hover:bg-emerald-50 transition-colors cursor-pointer"
                    @click="togglePos(opt.id)"
                  >
                    <input 
                      type="checkbox" 
                      :value="opt.id" 
                      v-model="posIds" 
                      class="w-4 h-4 rounded text-emerald-600 focus:ring-2 focus:ring-emerald-500" 
                      @click.stop
                    />
                    <span class="text-xs font-black text-slate-700">{{ opt.label }}</span>
                  </div>
                  <div v-if="filteredPosOptions.length === 0" class="p-6 text-center">
                    <i class="fas fa-search-minus text-slate-300 text-2xl mb-2"></i>
                    <p class="text-[10px] font-black text-slate-300 uppercase">لا توجد نتائج</p>
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- Payment Kind -->
        <div class="lg:col-span-2 space-y-2">
          <label class="filter-label flex items-center gap-2">
            <i class="fas fa-credit-card text-amber-500 text-xs"></i>
            نوع الدفع
          </label>
          <div class="relative">
            <select v-model="paymentKind" class="form-select-modern font-black text-sm h-12 pr-11 appearance-none cursor-pointer">
              <option value="">كل طرق الدفع</option>
              <option value="cash">💵 نقدي</option>
              <option value="card">💳 بطاقة</option>
              <option value="wallet">📱 محفظة</option>
              <option value="bank">🏦 تحويل</option>
              <option value="credit">📝 آجل</option>
            </select>
            <div class="absolute right-4 inset-y-0 flex items-center pointer-events-none">
              <i class="fas fa-credit-card text-slate-400"></i>
            </div>
            <div class="absolute left-4 inset-y-0 flex items-center pointer-events-none">
              <i class="fas fa-chevron-down text-slate-400 text-xs"></i>
            </div>
          </div>
        </div>

        <!-- Category Filter -->
        <div class="lg:col-span-2 space-y-2">
          <label class="filter-label flex items-center gap-2">
            <i class="fas fa-tags text-purple-500 text-xs"></i>
            فلترة بالتصنيف
          </label>
          <div class="relative">
            <input 
              v-model="categoryId" 
              type="number" 
              class="form-input-modern h-12 font-bold text-sm pr-11" 
              placeholder="رقم التصنيف" 
            />
            <div class="absolute right-4 inset-y-0 flex items-center pointer-events-none">
              <i class="fas fa-tags text-slate-400"></i>
            </div>
          </div>
        </div>

        <!-- Product Filter -->
        <div class="lg:col-span-2 space-y-2">
          <label class="filter-label flex items-center gap-2">
            <i class="fas fa-box text-indigo-500 text-xs"></i>
            فلترة بالمنتج
          </label>
          <div class="relative">
            <input 
              v-model="productId" 
              type="number" 
              class="form-input-modern h-12 font-bold text-sm pr-11" 
              placeholder="رقم المنتج" 
            />
            <div class="absolute right-4 inset-y-0 flex items-center pointer-events-none">
              <i class="fas fa-box text-slate-400"></i>
            </div>
          </div>
        </div>

        <!-- Apply Button -->
        <button 
          @click="fetchSalesSummary" 
          :disabled="isLoading"
          class="lg:col-span-1 h-12 w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl font-black shadow-xl shadow-blue-200 transition-all flex items-center justify-center active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed group"
        >
          <i class="fas fa-search group-hover:scale-110 transition-transform" :class="{'animate-spin fa-spinner': isLoading, 'fa-search': !isLoading}"></i>
        </button>
      </div>
    </div>

    <!-- Summary Performance Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div v-for="card in summaryCards" :key="card.title" class="kpi-card group" :class="card.color.replace('text', 'border-r-4 border-r')">
        <div class="flex items-center justify-between mb-4">
          <div :class="[card.bg, card.color, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition-transform duration-500']">
            <i :class="card.icon"></i>
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">{{ card.title }}</p>
        <p :class="[card.color, 'text-2xl font-black tracking-tight leading-none font-mono']">
          {{ card.format === 'currency' ? formatCurrency(card.value) : (card.format === 'number' ? formatNumber(card.value) : card.value) }}
        </p>
      </div>
    </section>

    <!-- Visual Analytics Grid -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">
        
        <!-- POS Sales Distribution -->
        <div class="lg:col-span-4 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500 flex flex-col relative overflow-hidden">
             <div class="absolute top-0 left-0 w-24 h-24 bg-blue-50 rounded-full -translate-x-12 -translate-y-12"></div>
             <h3 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-3 mb-8 relative z-10">
               <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
               المبيعات حسب نقطة البيع
             </h3>
             <div class="flex-grow flex items-center justify-center min-h-[300px] relative z-10">
                <BarChart v-if="!isLoading && salesSummary.salesByPos.length" :data="salesByPosChart" :options="chartOptions" />
                <div v-else class="text-slate-300 font-bold italic text-xs uppercase tracking-widest">لا توجد بيانات نقاط بيع</div>
             </div>
        </div>

        <!-- Payment Method Distribution -->
        <div class="lg:col-span-3 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500 flex flex-col relative overflow-hidden">
             <h3 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-3 mb-8">
               <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
               توزيع طرق الدفع
             </h3>
             <div class="flex-grow flex items-center justify-center min-h-[300px]">
               <DoughnutChart v-if="!isLoading && salesSummary.salesByPayment.length" :data="salesByPaymentChart" :options="doughnutOptions" />
             </div>
             <div class="mt-6 pt-6 border-t border-slate-50 flex flex-wrap gap-3 justify-center">
                <div v-for="p in salesSummary.salesByPayment" :key="p.name" class="flex items-center gap-2">
                   <span class="w-2 h-2 rounded-full bg-slate-200"></span>
                   <span class="text-[9px] font-black text-slate-400 uppercase">{{ p.name }}</span>
                </div>
             </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="lg:col-span-5 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
             <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                  <i class="fas fa-clock-rotate-left text-blue-500"></i> آخر العمليات
                </h2>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest leading-none">مباشر (Live)</span>
             </div>

             <!-- Smart Cost Warning -->
             <transition name="slide-fade">
               <div v-if="shouldShowCostWarning" class="mx-6 mt-4 p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-start gap-3 animate-pulse">
                 <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5"></i>
                 <div class="flex-grow">
                   <p class="text-[10px] font-black text-amber-800 uppercase leading-none">تنبيه تكلفة المخزون (COGS)</p>
                   <p class="text-[9px] font-bold text-amber-600 mt-1.5 leading-relaxed italic">قد تظهر التكلفة بصفر لعدم وجود أرصدة افتتاحية أو مشتريات مسجلة مسبقاً.</p>
                 </div>
                 <button @click="hideCostWarning = true" class="text-slate-300 hover:text-rose-500"><i class="fas fa-times"></i></button>
               </div>
             </transition>

             <div class="flex-grow overflow-y-auto custom-scroll p-4">
                <table class="w-full text-right text-xs font-cairo">
                    <thead>
                      <tr class="text-slate-400 font-black uppercase tracking-tighter">
                        <th class="px-4 py-3">الوقت</th>
                        <th class="px-4 py-3">المبلغ</th>
                        <th class="px-4 py-3">الربح الصافي</th>
                        <th class="px-4 py-3">طريقة الدفع</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
                        <!-- Skeleton loading for table (GPU-accelerated) -->
                        <template v-if="isLoading">
                          <tr v-for="row in 5" :key="row">
                            <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                            <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                            <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                            <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                          </tr>
                        </template>
                        <tr v-else-if="!salesSummary.recentTransactions.length" class="text-center py-20 opacity-20">
                          <td colspan="4" class="py-12 font-black uppercase tracking-widest">لا توجد عمليات مسجلة</td>
                        </tr>
                        <tr v-for="tx in salesSummary.recentTransactions" :key="tx.id" class="hover:bg-slate-50 transition-all">
                            <td class="px-4 py-4 whitespace-nowrap text-slate-400 font-mono tracking-tighter">{{ formatTime(tx.time) }}</td>
                            <td class="px-4 py-4 font-black text-slate-900 text-sm font-mono tracking-tighter">{{ formatCurrency(tx.amount) }}</td>
                            <td class="px-4 py-4 font-black font-mono tracking-tighter" :class="tx.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ formatCurrency(tx.profit) }}
                            </td>
                            <td class="px-4 py-4">
                               <span :class="['status-pill px-2.5 py-1 rounded-lg text-[9px] font-black uppercase shadow-sm border border-white', getPaymentMethodStyle(tx.paymentMethod)]">{{ tx.paymentMethod }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
             </div>
        </div>
    </section>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Bar as BarChart, Doughnut as DoughnutChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useReportsStore } from '@/stores/reports';
import { getLocalDateISO } from '@/utils/date';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

// Click outside directive
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event);
      }
    };
    document.addEventListener('click', el._clickOutside);
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside);
  }
};

const reportsStore = useReportsStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

const selectedDate = ref(getLocalDateISO());
const selectedDateRef = ref(null);

const salesSummary = ref({
  kpis: { totalSales: 0, orderCount: 0, avgOrderValue: 0, topPaymentMethod: 'N/A' },
  salesByPos: [],
  salesByPayment: [],
  recentTransactions: []
});

const summaryCards = computed(() => {
  const k = salesSummary.value.kpis || {}
  return [
    { title: 'إجمالي المبيعات', value: k.totalSales, format: 'currency', icon: 'fas fa-shopping-cart', color: 'text-emerald-700', bg: 'bg-emerald-50' },
    { title: 'إجمالي المرتجعات', value: k.totalReturns, format: 'currency', icon: 'fas fa-undo', color: 'text-rose-700', bg: 'bg-rose-50' },
    { title: 'صافي المبيعات', value: k.netSales, format: 'currency', icon: 'fas fa-sack-dollar', color: 'text-blue-700', bg: 'bg-blue-50' },
    { title: 'عدد الفواتير', value: k.orderCount || 0, format: 'number', icon: 'fas fa-receipt', color: 'text-indigo-700', bg: 'bg-indigo-50' },
    { title: 'إجمالي التكلفة (COGS)', value: k.cogsTotal, format: 'currency', icon: 'fas fa-box', color: 'text-amber-700', bg: 'bg-amber-50' },
    { title: 'الربح الإجمالي', value: k.grossProfit, format: 'currency', icon: 'fas fa-chart-line', color: 'text-emerald-700', bg: 'bg-emerald-100' },
  ]
})

const salesByPosChart = computed(() => ({
  labels: salesSummary.value.salesByPos.map(p => p.name),
  datasets: [{ label: 'المبيعات', data: salesSummary.value.salesByPos.map(p => p.totalSales), backgroundColor: ['#3b82f6', '#6366f1', '#8b5cf6', '#d946ef'], borderRadius: 12, maxBarThickness: 40 }]
}));

const salesByPaymentChart = computed(() => ({
  labels: salesSummary.value.salesByPayment.map(p => p.name),
  datasets: [{ data: salesSummary.value.salesByPayment.map(p => p.totalSales), backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'], borderWidth: 0 }]
}));

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, indexAxis: 'y', scales: { x: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } }, y: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '75%' };

const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');
const formatTime = (ts) => ts ? new Date(ts).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';

const getPaymentMethodStyle = (m) => {
    const s = String(m || '').toLowerCase();
    if (s.includes('cash') || s.includes('نقد')) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    if (s.includes('card') || s.includes('بطاقة')) return 'bg-blue-50 text-blue-600 border-blue-100';
    return 'bg-slate-50 text-slate-500 border-slate-100';
};

const paymentKind = ref(''); 
const categoryId = ref(''); 
const productId = ref(''); 
const posIds = ref([]); 
const posOptions = ref([]); 
const posSearch = ref('');
const posDropdownOpen = ref(false);

const filteredPosOptions = computed(() => { 
  const q = (posSearch.value || '').toLowerCase(); 
  return posOptions.value.filter(o => o.label.toLowerCase().includes(q)); 
});

const hasActiveFilters = computed(() => {
  return paymentKind.value !== '' || 
         categoryId.value !== '' || 
         productId.value !== '' || 
         posIds.value.length > 0;
});

const clearFilters = () => {
  paymentKind.value = '';
  categoryId.value = '';
  productId.value = '';
  posIds.value = [];
  posSearch.value = '';
  fetchSalesSummary();
};

const toggleAllPos = () => {
  if (posIds.value.length === posOptions.value.length) {
    posIds.value = [];
  } else {
    posIds.value = posOptions.value.map(p => p.id);
  }
};

const togglePos = (id) => {
  const index = posIds.value.indexOf(id);
  if (index > -1) {
    posIds.value.splice(index, 1);
  } else {
    posIds.value.push(id);
  }
};

const loadPosOptions = async () => {
  try {
    const result = await reportsStore.fetchPosAnalytics(selectedDate.value, selectedDate.value);
    const data = result.status === 'success' ? result.data : [];
    posOptions.value = (Array.isArray(data) ? data : (data?.data || [])).map(r => ({ id: r.pos_id, label: r.pos_name || `POS #${r.pos_id}` }));
  } catch { posOptions.value = []; }
}

const fetchSalesSummary = async () => {
    isLoading.value = true; error.value = null;
    try {
        const result = await reportsStore.fetchSalesSummary(selectedDate.value);
        if (result.status === 'success') {
            const apiData = result.data || {};
            
            // Extract totals
            const totalSales = Number(apiData.total_sales_amount ?? 0);
            const totalReturns = Number(apiData.total_returns_amount ?? 0);
            const cogsTotal = Number(apiData.cogs_total ?? 0);
            const orderCount = Number(apiData.order_count ?? 0);
            const netSales = totalSales - totalReturns;
            const grossProfit = netSales - cogsTotal;

            salesSummary.value = {
                kpis: { 
                    totalSales, 
                    totalReturns, 
                    netSales, 
                    orderCount, 
                    cogsTotal, 
                    grossProfit 
                },
                salesByPos: (apiData.salesByPos || []).map(p => ({ 
                    name: p.name || `POS #${p.pos_id}`, 
                    totalSales: Number(p.totalSales || 0) 
                })),
                salesByPayment: (apiData.salesByPayment || []).map(p => ({ 
                    name: p.name || '-', 
                    totalSales: Number(p.totalSales || 0) 
                })),
                recentTransactions: (apiData.recentTransactions || []).map(t => ({ 
                    id: t.id, 
                    time: t.time || t.created_at, 
                    posName: t.posName || '-', 
                    amount: Number(t.amount || 0), 
                    cogs: Number(t.cogs || 0), 
                    profit: Number(t.profit || 0), 
                    paymentMethod: t.paymentMethod || '-' 
                }))
            };
        } else {
            error.value = result.message || "فشل التحميل";
        }
    } catch (e) { 
        error.value = "فشل التحميل"; 
        console.error(e); 
    } finally { 
        isLoading.value = false; 
    }
};

const viewYesterday = () => { const y = new Date(selectedDate.value); y.setDate(y.getDate() - 1); selectedDate.value = getLocalDateISO(y); };
const hideCostWarning = ref(false);
const shouldShowCostWarning = computed(() => !hideCostWarning.value && Number(salesSummary.value.kpis.totalSales) > 0 && Number(salesSummary.value.kpis.cogsTotal) === 0);

watch(selectedDate, () => { loadPosOptions(); fetchSalesSummary(); });
onMounted(async () => { await Promise.all([fetchSettings(), loadPosOptions(), fetchSalesSummary()]); });
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
.form-input-modern { @apply w-full h-11 bg-slate-50 border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-slate-50 border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none cursor-pointer; }
.filter-label { @apply block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5 px-1; }

.status-pill { @apply px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-tighter shadow-sm; }

/* Custom Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
.custom-scroll::-webkit-scrollbar-track { @apply bg-transparent; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { 
  from { opacity: 0; transform: translateY(10px); } 
  to { opacity: 1; transform: translateY(0); } 
}

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }

/* Dropdown Animation */
.dropdown-enter-active {
  animation: dropdownIn 0.2s ease-out;
}
.dropdown-leave-active {
  animation: dropdownOut 0.15s ease-in;
}
@keyframes dropdownIn {
  from {
    opacity: 0;
    transform: translateY(-8px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
@keyframes dropdownOut {
  from {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  to {
    opacity: 0;
    transform: translateY(-8px) scale(0.95);
  }
}
</style>