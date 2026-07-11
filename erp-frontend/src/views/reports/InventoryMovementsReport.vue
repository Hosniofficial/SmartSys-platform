<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-truck-ramp-box text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تقرير حركة المخزون</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع دقيق لكافة عمليات الوارد والمنصرف وتسويات الأرصدة</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="exportCsv" class="px-6 py-2.5 rounded-xl text-xs font-black text-emerald-600 hover:bg-emerald-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-file-csv"></i> تصدير البيانات (CSV)
        </button>
        <button @click="fetchReport" :disabled="isLoading" class="w-11 h-11 bg-white text-slate-400 rounded-xl flex items-center justify-center hover:text-blue-600 transition-all active:scale-90">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i>
        </button>
      </div>
    </div>

    <!-- Analytical Filters Panel -->
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
          <label class="filter-label">نوع الحركة المخزنية</label>
          <select v-model="movementType" class="form-select-modern font-black text-sm cursor-pointer">
            <option value="all">كل أنواع الحركات</option>
            <option value="purchase">مشتريات (وارد)</option>
            <option value="sale">مبيعات (صادر)</option>
            <option value="return">مرتجعات (تسوية)</option>
            <option value="adjustment">تسويات يدوية</option>
          </select>
        </div>

        <div class="space-y-2">
          <label class="filter-label">الفرع / المستودع</label>
          <select v-model="selectedBranch" class="form-select-modern font-black text-sm cursor-pointer">
            <option value="all">كافة الفروع</option>
            <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>

        <div class="flex gap-2">
           <button @click="setDateRange('week')" class="quick-range-btn flex-1">آخر 7 أيام</button>
           <button @click="setDateRange('month')" class="quick-range-btn flex-1">هذا الشهر</button>
        </div>
      </div>

      <!-- Product Autocomplete Filter -->
      <div class="pt-6 border-t border-slate-50 relative group">
        <label class="filter-label">البحث عن منتج محدد (اختياري)</label>
        <div class="relative max-w-xl">
          <input 
            type="text" 
            v-model="productQuery" 
            class="form-input-modern pr-11 h-12 font-black" 
            placeholder="ابحث بالاسم، الباركود أو الكود..." 
            @input="onProductInput" 
            @focus="onProductFocus"
          />
          <i class="fas fa-box absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
          
          <button v-if="selectedProduct" @click="clearSelectedProduct" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 transition-colors">
            <i class="fas fa-times-circle text-lg"></i>
          </button>

          <!-- Product Dropdown Results -->
          <transition name="fade">
            <div v-if="showProductDropdown && productResults.length" class="dropdown-list custom-scroll">
              <div v-for="p in productResults" :key="p.id" @click="selectProduct(p)" class="dropdown-item">
                <div class="flex flex-col">
                  <span class="font-black text-slate-800 text-sm leading-none">{{ p.name }}</span>
                  <span class="text-[10px] text-slate-400 mt-1.5 uppercase font-mono tracking-tighter">{{ p.barcode || '-' }} • {{ p.code || '-' }}</span>
                </div>
                <i class="fas fa-plus text-[10px] text-blue-500"></i>
              </div>
            </div>
          </transition>
        </div>
        <p v-if="selectedProduct" class="text-[10px] font-black text-blue-600 uppercase mt-3 flex items-center gap-2">
           <span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-pulse"></span>
           العرض الحالي مقيد بمنتج: {{ selectedProduct.name }}
        </p>
      </div>
    </div>

    <!-- Summary & Visual Analytics -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
      
      <!-- Summary Vertical KPI Cards -->
      <div class="lg:col-span-1 space-y-4">
        <div v-for="summary in summaryData" :key="summary.title" class="kpi-mini-box group" :class="summary.valueClass.replace('text', 'border-r-4 border-r')">
          <div :class="[summary.iconClass, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110']">
            <i :class="summary.icon"></i>
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1.5">{{ summary.title }}</p>
            <p :class="[summary.valueClass, 'text-xl font-black font-mono leading-none tracking-tighter']">
              {{ summary.format === 'currency' ? formatCurrency(summary.value) : formatNumber(summary.value) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Trend Chart Card -->
      <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500 flex flex-col relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50/30 rounded-full translate-x-16 -translate-y-16"></div>
        
        <div class="flex items-center justify-between mb-8 relative z-10">
           <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
             <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
             منحنى تقلبات الرصيد
           </h2>
           <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white px-3 py-1 rounded-lg border border-slate-100 shadow-sm">تحديثات لحظية</span>
        </div>

        <div class="flex-grow relative z-10 min-h-[300px]">
          <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-3xl z-20">
             <BaseSpinner color="#2563eb" />
          </div>
          <LineChart v-if="movements.length" :data="chartData" :options="chartOptions" />
          <div v-else class="h-full flex flex-col items-center justify-center opacity-30">
             <i class="fas fa-chart-line text-4xl mb-3"></i>
             <p class="font-black text-xs uppercase tracking-widest">لا توجد بيانات كافية للتحليل البياني</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Movements Detailed Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">سجل حركات المخزون التفصيلي</h3>
         <div class="flex items-center gap-3 text-[10px] font-black text-slate-300 uppercase tracking-widest">
            <span>الحركات: {{ movements.length }}</span>
            <span class="mx-1">•</span>
            <span>الفرز: {{ sortOrder === 'asc' ? 'تصاعدي' : 'تنازلي' }}</span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm font-cairo">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th @click="handleSort('date')" class="px-6 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                تاريخ الحركة <i :class="sortKey === 'date' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th class="px-4 py-5">المستند / المرجع</th>
              <th @click="handleSort('product')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                اسم الصنف <i :class="sortKey === 'product' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort opacity-20'" class="mr-1"></i>
              </th>
              <th class="px-4 py-5 text-center">النوع</th>
              <th class="px-4 py-5 text-center">وارد (+)</th>
              <th class="px-4 py-5 text-center">منصرف (-)</th>
              <th class="px-4 py-5 text-center">الرصيد التراكمي</th>
              <th class="px-4 py-5 text-left">قيمة الحركة</th>
              <th class="px-6 py-5">ملاحظات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!sortedMovements.length" class="text-center py-20">
              <td colspan="9" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-boxes-stacked text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase tracking-widest">لا توجد حركات مخزنية في هذه الفترة</p>
                </div>
              </td>
            </tr>
            <tr v-for="movement in sortedMovements" :key="movement.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-6 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ formatDate(movement.date) }}</td>
              <td class="px-4 py-4 font-black">
                <router-link v-if="getReferenceRoute(movement)" :to="getReferenceRoute(movement)" class="text-blue-600 hover:underline decoration-blue-200 underline-offset-4">
                  {{ movement.reference }}
                </router-link>
                <span v-else class="text-slate-400 font-mono text-xs">{{ movement.reference }}</span>
              </td>
              <td class="px-4 py-4 text-slate-800 leading-tight">{{ movement.product }}</td>
              <td class="px-4 py-4 text-center">
                <span :class="getMovementTypeClass(movement.type)" class="status-badge">{{ getMovementTypeLabel(movement.type) }}</span>
              </td>
              <td class="px-4 py-4 text-center font-mono font-black text-emerald-600 tracking-tighter">
                {{ movement.in > 0 ? formatNumber(movement.in) : '—' }}
              </td>
              <td class="px-4 py-4 text-center font-mono font-black text-rose-600 tracking-tighter">
                {{ movement.out > 0 ? formatNumber(movement.out) : '—' }}
              </td>
              <td class="px-4 py-4 text-center font-mono font-black text-slate-900 text-base border-x border-slate-50/50 bg-slate-50/30 group-hover:bg-white transition-all">
                {{ formatNumber(movement.balance) }}
              </td>
              <td class="px-4 py-4 text-left font-black font-mono tracking-tighter text-indigo-500">
                {{ formatCurrency(movementValue(movement)) }}
              </td>
              <td class="px-6 py-4 text-xs text-slate-400 italic font-medium max-w-xs truncate" :title="movement.notes">{{ movement.notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Line as LineChart } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler } from 'chart.js';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { getLocalDateISO, getLocalTimestamp } from '@/utils/date';
import { useReportsStore } from '@/stores/reports';
import { useBranchStore } from '@/stores/branch';
import { useInventoryStore } from '@/stores/inventory/inventoryStore';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';

// --- Logic ---
ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler);

const reportsStore = useReportsStore();
const branchStore = useBranchStore();
const inventoryStore = useInventoryStore();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const isLoading = ref(true);
const error = ref(null);

// Filters (Logic Strictly Preserved)
const startDate = ref(getLocalDateISO(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)));
const endDate = ref(getLocalDateISO());
const startDateRef = ref(null);
const endDateRef = ref(null);
const movementType = ref('all');
let debounceTimer = null;

// Data Refs
const movements = ref([]);
const openingBalance = ref(0);
const branches = computed(() => branchStore.branches);
// Reports support optional 'all branches' - default to selected branch
const selectedBranch = computed({
  get: () => branchStore.selectedBranchId || 'all',
  set: (val) => branchStore.setSelectedBranch(val === 'all' ? null : val)
});
const productQuery = ref('');
const productResults = ref([]);
const selectedProduct = ref(null);
const showProductDropdown = ref(false);

const sortKey = ref('date');
const sortOrder = ref('desc');

// Logic: Summary (Preserved)
const summaryData = computed(() => {
  const totalIn = movements.value.reduce((sum, item) => sum + (item.in || 0), 0);
  const totalOut = movements.value.reduce((sum, item) => sum + (item.out || 0), 0);
  let finalBalance = movements.value.length > 0 ? movements.value[0].balance : openingBalance.value;
  let totalCost = 0; let costCount = 0;
  movements.value.forEach(item => { if (item.cost && item.cost > 0) { totalCost += item.cost; costCount++; } });
  const avgCost = costCount > 0 ? totalCost / costCount : 0;
  return [
    { title: 'الرصيد قبل الفترة', value: openingBalance.value, icon: 'fas fa-box-open', iconClass: 'bg-indigo-50 text-indigo-600', valueClass: 'text-indigo-600', format: 'number' },
    { title: 'إجمالي الوارد (أصناف)', value: totalIn, icon: 'fas fa-arrow-down', iconClass: 'bg-emerald-50 text-emerald-600', valueClass: 'text-emerald-600', format: 'number' },
    { title: 'إجمالي المنصرف (أصناف)', value: totalOut, icon: 'fas fa-arrow-up', iconClass: 'bg-rose-50 text-rose-600', valueClass: 'text-rose-600', format: 'number' },
    { title: 'القيمة الحالية التقديرية', value: Math.max(0, finalBalance * avgCost), icon: 'fas fa-coins', iconClass: 'bg-blue-50 text-blue-600', valueClass: 'text-blue-600', format: 'currency' }
  ];
});

// Logic: Sorting & Chart (Preserved)
const sortedMovements = computed(() => {
  return [...movements.value].sort((a, b) => {
    let vA = a[sortKey.value], vB = b[sortKey.value];
    if (sortKey.value === 'date') { vA = new Date(vA); vB = new Date(vB); }
    if (vA < vB) return sortOrder.value === 'asc' ? -1 : 1;
    if (vA > vB) return sortOrder.value === 'asc' ? 1 : -1;
    return 0;
  });
});

const chartData = computed(() => {
  const reversed = [...sortedMovements.value].reverse();
  return {
    labels: reversed.map(m => formatDate(m.date, { day: 'numeric', month: 'short' })),
    datasets: [{ label: 'الرصيد', backgroundColor: 'rgba(37, 99, 235, 0.1)', borderColor: '#2563eb', data: reversed.map(m => m.balance), fill: true, tension: 0.4, pointRadius: 2 }]
  };
});

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } }, x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } } } };

// Logic: API Handlers (STRICTLY PRESERVED)
const fetchReport = async () => {
  isLoading.value = true;
  try {
    const pId = selectedProduct.value?.id || null;
    const bId = selectedBranch.value !== 'all' ? String(selectedBranch.value) : undefined;
    
    console.log('[InventoryMovements] Fetching:', { startDate: startDate.value, endDate: endDate.value, pId, movementType: movementType.value, bId });
    
    let apiData = await reportsStore.fetchInventoryMovements(startDate.value, endDate.value, pId, movementType.value, bId);
    
    console.log('[InventoryMovements] API Response:', apiData);
    
    // Handle double nesting from store
    if (apiData?.data) apiData = apiData.data;
    if (apiData?.data) apiData = apiData.data;
    
    console.log('[InventoryMovements] After extraction:', apiData);
    console.log('[InventoryMovements] Is Array?', Array.isArray(apiData));
    console.log('[InventoryMovements] items:', apiData?.items);
    
    if (Array.isArray(apiData)) { 
        movements.value = apiData; 
        openingBalance.value = 0; 
    } else { 
        movements.value = apiData?.items || apiData?.movements || [];
        openingBalance.value = apiData?.opening_balance || 0;
    }
    
    console.log('[InventoryMovements] Final movements:', movements.value);
    console.log('[InventoryMovements] Final openingBalance:', openingBalance.value);
  } catch (err) { 
    console.error('[InventoryMovements] Error:', err);
    movements.value = []; 
  } finally { 
    isLoading.value = false; 
  }
};

const exportCsv = async () => {
  try {
    // استخدام الـ store method من reportsStore
    const result = await reportsStore.fetchInventoryMovements(startDate.value, endDate.value, selectedProduct.value?.id || null, movementType.value, selectedBranch.value !== 'all' ? String(selectedBranch.value) : undefined);
    if (result.status === 'success' && result.data) {
      // حفظ البيانات كـ CSV
      const csvContent = Array.isArray(result.data) 
        ? result.data.map(m => `${m.date},${m.product},${m.in},${m.out},${m.balance}`).join('\n')
        : '';
      const a = document.createElement('a'); 
      a.href = window.URL.createObjectURL(new Blob([csvContent])); 
      a.download = `inventory_${getLocalTimestamp()}.csv`; 
      a.click();
    }
  } catch {}
};

// Utils & Handlers
const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');
const formatDate = (v, o = {}) => v ? new Date(v).toLocaleDateString('en-US', o) : '';
const movementValue = (mv) => (Number(mv.in || 0) - Number(mv.out || 0)) * Number(mv.cost || 0);
const getMovementTypeClass = (t) => ({ purchase: 'bg-emerald-100 text-emerald-700', sale: 'bg-blue-100 text-blue-700', return: 'bg-amber-100 text-amber-700', adjustment: 'bg-purple-100 text-purple-700' }[t] || 'bg-slate-100');
const getMovementTypeLabel = (t) => ({ purchase: 'مشتريات', sale: 'مبيعات', return: 'مرتجع', adjustment: 'تسوية', opening_balance: 'رصيد أول' }[t] || t);
const handleSort = (key) => { if (sortKey.value === key) sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'; else { sortKey.value = key; sortOrder.value = 'asc'; } };
const setDateRange = (r) => { const end = new Date(); let start = new Date(); if (r === 'week') start.setDate(end.getDate() - 7); else start.setMonth(end.getMonth() - 1); endDate.value = getLocalDateISO(end); startDate.value = getLocalDateISO(start); };

// Product Search Logic (Preserved)
let productSearchTimer = null;
const onProductInput = async () => { 
  showProductDropdown.value = true; 
  clearTimeout(productSearchTimer); 
  productSearchTimer = setTimeout(async () => { 
    try { 
      // استخدام inventoryStore للبحث عن المنتجات
      const result = await inventoryStore.fetchProducts(1, 50, productQuery.value);
      if (result.status === 'success' && Array.isArray(result.data)) {
        productResults.value = result.data;
      } else {
        productResults.value = [];
      }
    } catch { 
      productResults.value = []; 
    } 
  }, 300); 
};
const onProductFocus = () => { if (!productResults.value.length && productQuery.value) onProductInput(); showProductDropdown.value = true; };
const selectProduct = (p) => { selectedProduct.value = p; productQuery.value = p.name; showProductDropdown.value = false; fetchReport(); };
const clearSelectedProduct = () => { selectedProduct.value = null; productQuery.value = ''; showProductDropdown.value = false; fetchReport(); };

const getReferenceRoute = (m) => {
  const ref = m.reference || ''; if (!ref.includes('#')) return null;
  const [kind, id] = ref.split('#');
  const map = { sale: 'SalesHistory', return: 'ReturnsHistory', purchase: 'PurchaseHistory' };
  return map[kind] ? { name: map[kind], query: { id: Number(id) } } : null;
};

// Lifecycle
onMounted(async () => {
  await Promise.all([fetchSettings(), branchStore.fetchBranches().catch(() => {})]);
  fetchReport();
});

watch([startDate, endDate, movementType, selectedBranch], () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchReport, 700); });
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-mini-box { @apply bg-white p-6 rounded-[1.8rem] shadow-sm border border-slate-100 flex items-center gap-5 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tighter; }

/* Modern UI Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.quick-range-btn { @apply px-3 py-1.5 rounded-xl bg-white text-slate-500 text-[10px] font-black hover:text-blue-600 transition-all border-none; }

.status-badge { @apply px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-tighter shadow-sm border border-transparent; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

/* Dropdown Results */
.dropdown-list { @apply absolute z-50 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-60 overflow-auto py-2; }
.dropdown-item { @apply px-6 py-3 cursor-pointer hover:bg-blue-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0; }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>