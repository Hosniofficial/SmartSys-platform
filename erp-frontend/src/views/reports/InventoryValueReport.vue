<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-coins text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تقرير قيمة المخزون</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل القيمة الرأسمالية للبضاعة المتوفرة وتوزيعها المالي</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-center gap-2 px-3">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">أساس التقييم:</span>
          <select v-model="valuation" class="form-select-modern h-9 text-xs font-black w-32 border-none bg-slate-50 shadow-none">
            <option value="sale">سعر البيع</option>
            <option value="cost">سعر التكلفة</option>
          </select>
        </div>
        <div class="h-6 w-px bg-slate-100"></div>
        <button @click="fetchInventoryValue" :disabled="isLoading" class="w-11 h-11 text-slate-400 hover:text-blue-600 transition-all active:scale-90">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i>
        </button>
      </div>
    </div>

    <!-- Summary Performance Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
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

    <!-- Visual Analytics: Value by Category -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-10 hover:shadow-xl transition-shadow duration-500 relative overflow-hidden">
      <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50/50 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
      
      <div class="flex items-center justify-between mb-10 relative z-10">
         <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
           <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
           توزيع القيمة المالية حسب التصنيف (أعلى ٥)
         </h2>
         <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-lg border border-slate-100 shadow-sm">تحليل فئات المخزون</span>
      </div>

      <div class="h-[350px] relative z-10">
        <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-3xl z-20">
           <BaseSpinner color="#2563eb" />
        </div>
        <BarChart v-if="inventoryItems.length" :data="valueByCategoryChart" :options="chartOptions" />
        <div v-else class="h-full flex flex-col items-center justify-center opacity-30 italic font-bold">بانتظار مزامنة البيانات...</div>
      </div>
    </section>
    
    <!-- Detailed Inventory Value Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
      <!-- Table Filter Bar -->
      <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-6 bg-slate-50/30">
          <div class="relative group w-full md:w-96">
            <input v-model="searchQuery" type="text" class="form-input-modern pr-11 h-12 font-black" placeholder="ابحث بالاسم أو الباركود..." />
            <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
          </div>

          <div class="flex items-center gap-3">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">عرض فئة:</label>
            <select v-model="selectedCategory" class="form-select-modern font-black text-sm h-11 w-56 border-slate-200">
                <option value="all">كل التصنيفات</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
      </div>

      <!-- Table Body -->
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm font-cairo">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th @click="handleSort('name')" class="px-8 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                المنتج والترميز <i :class="sortKey === 'name' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th @click="handleSort('quantity')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors text-center">
                الكمية <i :class="sortKey === 'quantity' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th @click="handleSort('costPrice')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors text-center">التكلفة</th>
              <th @click="handleSort('salePrice')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors text-center">سعر البيع</th>
              <th @click="handleSort('totalValue')" class="px-8 py-5 cursor-pointer hover:text-blue-600 transition-colors text-center">إجمالي القيمة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading && !inventoryItems.length">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!sortedItems.length" class="text-center py-20">
              <td colspan="5" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-box-open text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase tracking-widest">لا توجد منتجات مطابقة للبحث</p>
                </div>
              </td>
            </tr>
            <tr v-for="item in sortedItems" :key="item.id" class="transition-all group border-r-4 border-r-transparent" :class="getRowClass(item)">
              <td class="px-8 py-4">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 border border-slate-100 group-hover:bg-white group-hover:border-blue-200 transition-all">
                    <i class="fas fa-barcode text-sm"></i>
                  </div>
                  <div class="flex flex-col">
                    <span class="font-black text-slate-800 leading-none group-hover:text-blue-600 transition-colors">{{ item.name }}</span>
                    <span class="text-[9px] text-slate-400 mt-1.5 uppercase font-mono tracking-tighter">REF: {{ item.barcode || '--' }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-center font-mono font-black text-base" :class="item.quantity === 0 ? 'text-rose-600' : (item.quantity <= item.lowStockThreshold ? 'text-amber-600' : 'text-slate-900')">
                {{ formatNumber(item.quantity) }}
              </td>
              <td class="px-4 py-4 text-center font-mono text-slate-400">{{ formatCurrency(item.costPrice) }}</td>
              <td class="px-4 py-4 text-center font-mono text-slate-400">{{ formatCurrency(item.salePrice) }}</td>
              <td class="px-8 py-4 text-center">
                <span class="font-black text-blue-600 text-lg font-mono tracking-tighter">{{ formatCurrency(item.totalValue) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Footer Info -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex items-center justify-between">
         <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">إجمالي الأصناف المعروضة: {{ sortedItems.length }}</span>
         </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useReportsStore } from '../../stores/reports';
import { Bar as BarChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const reportsStore = useReportsStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

const inventoryItems = ref([]);
const categories = ref([]);
const totals = ref({ total_value: 0, total_products: 0, low_stock_count: 0, out_of_stock_count: 0 });
const valuation = ref('sale');

const searchQuery = ref('');
const selectedCategory = ref('all');
const sortKey = ref('totalValue');
const sortOrder = ref('desc');

const filteredItems = computed(() => {
    let items = [...inventoryItems.value];
    if (selectedCategory.value !== 'all') items = items.filter(item => item.categoryId == selectedCategory.value);
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(item => item.name.toLowerCase().includes(q) || (item.barcode && item.barcode.toLowerCase().includes(q)));
    }
    return items;
});

const sortedItems = computed(() => {
    return [...filteredItems.value].sort((a, b) => {
        let valA = a[sortKey.value], valB = b[sortKey.value];
        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const summaryCards = computed(() => {
    const tv = Number(totals.value.total_value ?? 0);
    const tp = Number(totals.value.total_products ?? inventoryItems.value.length);
    const ls = Number(totals.value.low_stock_count ?? 0);
    const os = Number(totals.value.out_of_stock_count ?? 0);
    const fallbackTotal = inventoryItems.value.reduce((sum, item) => sum + (item.totalValue || 0), 0);
    const fallbackLow = inventoryItems.value.filter(item => item.quantity > 0 && item.quantity <= item.lowStockThreshold).length;
    const fallbackOut = inventoryItems.value.filter(item => item.quantity === 0).length;

    return [
        { title: 'قيمة المخزون الإجمالية', value: tv || fallbackTotal, format: 'currency', icon: 'fas fa-money-bill-trend-up', color: 'text-blue-600', bg: 'bg-blue-50' },
        { title: 'إجمالي عدد المنتجات', value: tp, format: 'number', icon: 'fas fa-boxes-stacked', color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { title: 'منتجات منخفضة المخزون', value: ls || fallbackLow, format: 'number', icon: 'fas fa-triangle-exclamation', color: 'text-amber-600', bg: 'bg-amber-50' },
        { title: 'أصناف نفدت كميتها', value: os || fallbackOut, format: 'number', icon: 'fas fa-circle-exclamation', color: 'text-rose-600', bg: 'bg-rose-50' }
    ];
});

const valueByCategoryChart = computed(() => {
    const categoryValues = categories.value.map(cat => {
        const value = inventoryItems.value.filter(item => item.categoryId === cat.id).reduce((sum, item) => sum + item.totalValue, 0);
        return { name: cat.name, value };
    }).sort((a, b) => b.value - a.value).slice(0, 5);

    return { labels: categoryValues.map(c => c.name), datasets: [{ label: 'قيمة المخزون', data: categoryValues.map(c => c.value), backgroundColor: ['#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#f43f5e'], borderRadius: 12, maxBarThickness: 40 }] };
});

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } }, x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } } } };

const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');
const getRowClass = (item) => {
    if (item.quantity === 0) return 'bg-rose-50/50 border-r-rose-500';
    if (item.quantity <= item.lowStockThreshold) return 'bg-amber-50/50 border-r-amber-500';
    return '';
};

const fetchInventoryValue = async () => {
    isLoading.value = true; error.value = null;
    try {
        const resp = await reportsStore.fetchInventoryValue({ search: searchQuery.value || undefined, category_id: selectedCategory.value !== 'all' ? selectedCategory.value : undefined, valuation: valuation.value });
        let apiData = resp?.data ?? resp; totals.value = resp?.totals || totals.value;
        if (Array.isArray(apiData)) inventoryItems.value = apiData;
        else { inventoryItems.value = apiData.items || apiData.inventory || []; categories.value = apiData.categories || categories.value || []; }
    } catch { error.value = "فشل التحميل"; } finally { isLoading.value = false; }
};

const handleSort = (k) => { if (sortKey.value === k) sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'; else { sortKey.value = k; sortOrder.value = 'asc'; } };

onMounted(async () => { await Promise.all([fetchSettings(), fetchInventoryValue()]); });
watch([searchQuery, selectedCategory, valuation], () => fetchInventoryValue());
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight font-mono; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none; }

/* Table Badges & Layout */
.status-badge { @apply shadow-sm inline-block; }

/* Custom Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>