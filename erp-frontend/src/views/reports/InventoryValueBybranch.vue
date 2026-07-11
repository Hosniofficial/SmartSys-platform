<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-chart-pie text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">قيمة المخزون حسب الفرع</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل القيمة الرأسمالية وتوزيع السيولة المخزنية عبر كافة الفروع</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-center gap-2 px-3">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">أساس التقييم:</span>
          <select v-model="valuation" @change="loadData" class="form-select-modern h-9 text-xs font-black w-32 border-none bg-slate-50 shadow-none">
            <option value="sale">سعر البيع</option>
            <option value="cost">سعر التكلفة</option>
          </select>
        </div>
        <div class="h-6 w-px bg-slate-100"></div>
        <button @click="loadData" :disabled="loading" class="w-11 h-11 text-slate-400 hover:text-blue-600 transition-all active:scale-90">
          <i class="fas fa-sync-alt" :class="{'animate-spin': loading}"></i>
        </button>
      </div>
    </div>

    <!-- Summary Performance Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div class="kpi-card group border-r-4 border-r-indigo-600">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-chart-line"></i>
          </div>
          <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">مجمّع</span>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">القيمة الإجمالية للمخزون</p>
        <p class="text-2xl font-black tracking-tight leading-none font-mono text-indigo-600">
          {{ formatCurrency(totals.total_value || 0) }}
        </p>
      </div>

      <div class="kpi-card group border-r-4 border-r-blue-500">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-boxes-stacked"></i>
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">إجمالي عدد الأصناف</p>
        <p class="text-2xl font-black tracking-tight leading-none text-slate-800">
          {{ totals.total_products || 0 }}
        </p>
      </div>

      <div class="kpi-card group border-r-4 border-r-amber-500">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">منتجات منخفضة المخزون</p>
        <p class="text-2xl font-black tracking-tight leading-none text-amber-600">
          {{ totals.low_stock_count || 0 }}
        </p>
      </div>

      <div class="kpi-card group border-r-4 border-r-rose-500">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-transform duration-500 group-hover:scale-110">
            <i class="fas fa-exclamation-circle"></i>
          </div>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none">أصناف نفدت كميتها</p>
        <p class="text-2xl font-black tracking-tight leading-none text-rose-600">
          {{ totals.out_of_stock_count || 0 }}
        </p>
      </div>
    </section>

    <!-- Visual Analytics: Value by Branch -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-10 hover:shadow-xl transition-shadow duration-500 relative overflow-hidden">
      <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50/50 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
      
      <div class="flex items-center justify-between mb-10 relative z-10">
         <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
           <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
           توزيع القيمة المالية حسب الفرع
         </h2>
         <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-lg border border-slate-100 shadow-sm">تحليل الفروع</span>
      </div>

      <div class="h-[380px] relative z-10">
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-3xl z-20">
           <BaseSpinner color="#2563eb" />
        </div>
        <BarChart v-if="chartData && chartData.labels.length" :data="chartData" :options="chartOptions" />
        <div v-else class="h-full flex flex-col items-center justify-center opacity-30 italic font-bold">بانتظار مزامنة البيانات...</div>
      </div>
    </section>
    
    <!-- Branch Details Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">تفاصيل تقييم الفروع</h3>
         <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">الأساس المستخدم:</span>
            <span class="status-badge bg-blue-50 text-blue-600 border border-blue-100">
               {{ totals.valuation_basis === 'purchase_price' ? 'سعر التكلفة' : 'سعر البيع' }}
            </span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm font-cairo">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">اسم الفرع / المستودع</th>
              <th class="px-4 py-5 text-center">عدد الأصناف</th>
              <th class="px-4 py-5 text-center">نواقص (Low)</th>
              <th class="px-4 py-5 text-center">نافذ (Out)</th>
              <th class="px-4 py-5 text-center">إجمالي الكمية</th>
              <th class="px-8 py-5 text-center">صافي قيمة المخزون</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="loading && !rows.length">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="rows.length === 0" class="text-center py-20">
              <td colspan="6" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-warehouse text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase tracking-widest">لا توجد سجلات متاحة للفروع</p>
                </div>
              </td>
            </tr>
            <tr v-for="(r, idx) in rows" :key="idx" class="hover:bg-blue-50/30 transition-all group border-r-4 border-r-transparent hover:border-r-blue-600">
              <td class="px-8 py-4 whitespace-nowrap">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 border border-slate-100 group-hover:bg-white group-hover:border-blue-200 transition-all">
                    <i class="fas fa-store text-sm"></i>
                  </div>
                  <span class="font-black text-slate-800 leading-none group-hover:text-blue-600 transition-colors">{{ r.branch_name }}</span>
                </div>
              </td>
              <td class="px-4 py-4 text-center">
                 <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg text-[10px] font-black">{{ r.items_count }}</span>
              </td>
              <td class="px-4 py-4 text-center">
                 <span v-if="r.low_stock_count > 0" class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-lg text-[10px] font-black">{{ r.low_stock_count }}</span>
                 <span v-else class="text-slate-200">—</span>
              </td>
              <td class="px-4 py-4 text-center">
                 <span v-if="r.out_of_stock_count > 0" class="bg-rose-100 text-rose-700 px-2.5 py-1 rounded-lg text-[10px] font-black">{{ r.out_of_stock_count }}</span>
                 <span v-else class="text-slate-200">—</span>
              </td>
              <td class="px-4 py-4 text-center font-black font-mono text-base text-slate-900 tracking-tighter">{{ r.total_qty }}</td>
              <td class="px-8 py-4 text-center">
                <span class="font-black text-blue-600 text-lg font-mono tracking-tighter leading-none">{{ formatCurrency(r.total_value) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Footer Info -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex items-center justify-between">
         <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">إجمالي الفروع المكتشفة: {{ rows.length }}</span>
         </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useReportsStore } from '@/stores/reports'
import { Bar as BarChart } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';

// --- Logic ---
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const reportsStore = useReportsStore()
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency()
const valuation = ref('sale')
const rows = ref([])
const totals = ref({})
const loading = ref(false)

// Logic: Currency Formatter (Preserved with dynamic locale)
const formatCurrency = (n) => formatCurrencyLocale(n, 2)

// Logic: Data Fetching (STRICTLY PRESERVED)
const loadData = async () => {
  loading.value = true
  try {
    const result = await reportsStore.fetchInventoryValue({ valuation: valuation.value })
    if (result.status === 'success') {
      const apiResponse = result.data || {}
      const payload = apiResponse?.data ?? apiResponse
      const list = Array.isArray(payload)
        ? payload
        : (Array.isArray(payload?.data)
            ? payload.data
            : (Array.isArray(payload?.items)
                ? payload.items
                : []))
      rows.value = list
      totals.value = apiResponse?.totals || payload?.totals || {}
    } else {
      rows.value = []
      totals.value = {}
    }
  } catch (e) {
    rows.value = []
    totals.value = {}
  } finally {
    loading.value = false
  }
}

// Logic: Chart Data Generator (STRICTLY PRESERVED)
const chartData = computed(() => {
  const labels = rows.value.map(r => r.branch_name)
  const values = rows.value.map(r => Number(r.total_value) || 0)
  return {
    labels,
    datasets: [
      {
        label: 'قيمة المخزون',
        data: values,
        backgroundColor: '#2563eb',
        borderRadius: 12,
        maxBarThickness: 50
      }
    ]
  }
})

const chartOptions = { 
  responsive: true, 
  maintainAspectRatio: false, 
  plugins: { 
    legend: { display: false },
    tooltip: {
      backgroundColor: '#0f172a',
      titleFont: { family: 'Cairo', weight: 'bold' },
      bodyFont: { family: 'Cairo' },
      padding: 12,
      cornerRadius: 12,
      rtl: true
    }
  }, 
  scales: { 
    y: { 
      grid: { color: '#f1f5f9' },
      ticks: { 
        font: { family: 'Cairo', size: 10 },
        callback: (v) => new Intl.NumberFormat('en-US').format(v) 
      } 
    },
    x: {
      grid: { display: false },
      ticks: { font: { family: 'Cairo', size: 10 } }
    }
  } 
}

onMounted(() => { Promise.all([fetchSettings(), loadData()]); })

watch(valuation, () => loadData())
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight font-mono; }

/* Modern Components */
.form-select-modern { @apply h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm inline-block; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>