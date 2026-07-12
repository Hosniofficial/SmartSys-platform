<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Error Banner -->
    <transition name="fade">
      <div v-if="error" class="mb-6 bg-rose-50 border border-rose-100 p-5 rounded-[1.5rem] shadow-xl shadow-rose-100/50 flex items-start gap-4">
        <div class="w-12 h-12 bg-rose-500 text-white rounded-2xl flex items-center justify-center shrink-0">
          <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <div class="flex-1 text-right">
          <h3 class="text-rose-900 font-black text-lg leading-none">{{ error.message }}</h3>
          <p class="text-rose-600 text-sm mt-2 font-bold">{{ error.details }}</p>
          <button @click="retry" class="mt-3 px-6 py-2 bg-rose-600 text-white rounded-xl hover:bg-rose-700 transition-all font-black text-xs flex items-center gap-2">
            <i class="fas fa-redo-alt"></i> إعادة المحاولة
          </button>
        </div>
        <button @click="error = null" class="text-rose-300 hover:text-rose-500 transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
    </transition>

    <!-- Header Section -->
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 bg-blue-600 rounded-[1.8rem] flex items-center justify-center shadow-2xl shadow-blue-200 text-white relative">
          <i class="fas fa-chart-pie text-2xl"></i>
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full animate-pulse"></span>
        </div>
        <div>
          <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">لوحة التحكم</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium">
            مرحباً بك، <span class="text-blue-600 font-black">{{ username }}</span> 👋
            <span v-if="lastUpdated" class="mr-3 text-slate-400 font-bold border-r border-slate-200 pr-3">
              <i class="far fa-clock ml-1"></i> {{ lastUpdated }}
            </span>
          </p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-[1.5rem] border border-slate-100 shadow-sm">
        <!-- Refresh Button -->
        <button @click="refresh" :disabled="isRefreshing || isLoading" class="w-11 h-11 bg-slate-50 text-slate-400 rounded-xl flex items-center justify-center hover:bg-blue-50 hover:text-blue-600 transition-all active:scale-90 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fas fa-sync-alt" :class="{ 'animate-spin': isRefreshing }"></i>
        </button>

        <div class="flex items-center gap-1 bg-slate-50 p-1 rounded-xl">
          <button v-for="range in ['today', 'week', 'month']" :key="range" @click="setDateRange(range)" 
            :class="[dateRange === range ? 'bg-white text-blue-600 shadow-md' : 'text-slate-400 hover:text-slate-600']"
            class="px-5 py-2 rounded-lg text-xs font-black transition-all">
            {{ range === 'today' ? 'اليوم' : range === 'week' ? 'الأسبوع' : 'الشهر' }}
          </button>
        </div>
      </div>
    </header>

    <!-- KPI Summary Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <!-- Loading Skeletons -->
      <template v-if="isLoading">
        <div v-for="i in 4" :key="i" class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-50 animate-pulse">
          <div class="flex justify-between items-start mb-4">
            <div class="w-12 h-12 bg-slate-100 rounded-2xl"></div>
            <div class="w-16 h-4 bg-slate-50 rounded-full"></div>
          </div>
          <div class="h-8 bg-slate-100 rounded-xl w-3/4 mb-3"></div>
          <div class="h-4 bg-slate-50 rounded-lg w-1/2"></div>
        </div>
      </template>

      <!-- Real KPIs -->
      <template v-else>
        <div v-for="kpi in kpis" :key="kpi.title" class="kpi-card group">
          <div class="flex items-center justify-between mb-4">
            <div :class="[kpi.iconClass, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition-transform duration-500']">
              <i :class="kpi.icon"></i>
            </div>
            <div v-if="typeof kpi.change === 'number' && kpi.change !== 0" 
              :class="[kpi.change > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600']"
              class="px-2 py-1 rounded-lg text-[10px] font-black tracking-tighter">
              <i :class="kpi.change > 0 ? 'fa fa-arrow-up' : 'fa fa-arrow-down'"></i>
              {{ Math.abs(kpi.change).toFixed(1) }}%
            </div>
          </div>
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-1">{{ kpi.title }}</p>
          <p :class="[kpi.valueClass, 'text-2xl font-black tracking-tight leading-none']">{{ kpi.value }}</p>
          <div class="mt-4 w-full h-1 bg-slate-50 rounded-full overflow-hidden">
            <div class="h-full bg-blue-500/20" :style="{ width: '40%' }"></div>
          </div>
        </div>
      </template>
    </section>

    <!-- Main Charts Section -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
      <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
            المبيعات اليومية
          </h2>
          <div class="flex gap-2">
            <span class="text-[10px] font-black text-slate-400 bg-slate-50 px-3 py-1 rounded-full border border-slate-100">تحليل الإيرادات</span>
          </div>
        </div>
        <div class="w-full h-[350px] relative">
          <canvas ref="salesChart"></canvas>
        </div>
      </div>
      
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-emerald-600 rounded-full"></span>
            أفضل المنتجات
          </h2>
        </div>
        <div class="w-full h-[350px] relative">
          <canvas ref="topProductsChart"></canvas>
        </div>
      </div>
    </section>
    
    <!-- Inventory & Performance Section -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-amber-500 rounded-full"></span>
            المنتجات المنخفضة
          </h2>
          <i class="fa fa-exclamation-triangle text-amber-500"></i>
        </div>
        
        <div class="max-h-[350px] overflow-y-auto custom-scroll pr-2">
          <div v-if="lowStockItems.length > 0" class="space-y-3">
            <div v-for="item in lowStockItems" :key="item.id" class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl group hover:bg-blue-50 transition-colors">
              <div class="flex flex-col">
                <span class="font-black text-sm text-slate-700 group-hover:text-blue-600 transition-colors">{{ item.name }}</span>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-[10px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-lg border border-rose-100">المخزون: {{ item.quantity }}</span>
                  <span class="text-[10px] font-bold text-slate-400">الحد الأدنى: {{ item.min_quantity }}</span>
                </div>
              </div>
              <button @click="handleLowStockClick(item)" class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm">
                <i class="fas fa-eye text-xs"></i>
              </button>
            </div>
          </div>
          <div v-else class="text-center py-12 flex flex-col items-center opacity-30">
            <i class="fas fa-check-circle text-4xl mb-3 text-emerald-500"></i>
            <p class="font-black text-sm uppercase">المخزون سليم بالكامل</p>
          </div>
        </div>
      </div>
      
      <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-shadow duration-500">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
            أداء نقاط البيع
          </h2>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">إجمالي المبيعات حسب المستخدم</span>
        </div>
        <div class="w-full h-[320px] relative">
          <canvas ref="posPerformanceChart"></canvas>
        </div>
      </div>
    </section>
    
    <!-- Quick Access Section -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
      <div class="flex items-center gap-3 mb-8">
        <h2 class="text-xl font-black text-slate-900 tracking-tight">الوصول السريع</h2>
        <div class="h-px bg-slate-100 flex-1"></div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-6">
        <router-link v-for="link in quickAccessLinks" :key="link.to" :to="link.to" 
          :class="[link.highlighted ? 'bg-blue-600 text-white shadow-xl shadow-blue-200 hover:bg-blue-700' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-100']" 
          class="flex flex-col items-center justify-center p-6 rounded-[2rem] transition-all duration-300 transform hover:-translate-y-2 group">
          <div :class="[link.highlighted ? 'bg-white/20' : 'bg-white shadow-sm group-hover:bg-blue-50']" class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-4 transition-all">
            <i :class="link.icon"></i>
          </div>
          <span class="text-[11px] font-black text-center uppercase tracking-widest">{{ link.text }}</span>
        </router-link>
      </div>
    </section>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import Chart from 'chart.js/auto'
import { useAuthStore } from '../stores/auth'
import { useReportsStore } from '../stores/reports'
import { useAnalyticsStore } from '../stores/analytics'
import { useRouter } from 'vue-router'
import { useCompanyCurrency } from '../composables/useCompanyCurrency'

// ─── Stores & Composables ─────────────────────────────────────────────────────
const authStore = useAuthStore()
const reportsStore = useReportsStore()
const analyticsStore = useAnalyticsStore()
const router = useRouter()
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency()

// ─── Chart Canvas Refs ────────────────────────────────────────────────────────
const salesChart = ref(null)
const topProductsChart = ref(null)
const posPerformanceChart = ref(null)

// ─── Chart Instance Refs ──────────────────────────────────────────────────────
const salesChartInstance = ref(null)
const topProductsChartInstance = ref(null)
const posPerformanceChartInstance = ref(null)

// ─── User ─────────────────────────────────────────────────────────────────────
const username = computed(() => authStore.user?.name || 'مدير المحل')
const isAdmin = computed(() => authStore.isAdmin)

// ─── State ────────────────────────────────────────────────────────────────────
const isLoading = ref(false)
const isRefreshing = ref(false)
const error = ref(null)
const lastUpdated = ref('')
const dateRange = ref('week')
const lowStockItems = ref([])

// ─── KPIs ─────────────────────────────────────────────────────────────────────
const kpis = ref([
  { title: 'إجمالي المبيعات', value: '---', change: 0, icon: 'fa fa-dollar-sign', iconClass: 'bg-emerald-100 text-emerald-600', valueClass: 'text-slate-800' },
  { title: 'صافي الأرباح',    value: '---', change: 0, icon: 'fa fa-wallet',       iconClass: 'bg-blue-100 text-blue-600',    valueClass: 'text-blue-600'  },
  { title: 'عدد الفواتير',    value: '0',   change: 0, icon: 'fa fa-receipt',      iconClass: 'bg-amber-100 text-amber-600',  valueClass: 'text-slate-800' },
  { title: 'متوسط الفاتورة',  value: '---', change: 0, icon: 'fa fa-calculator',   iconClass: 'bg-indigo-100 text-indigo-600',valueClass: 'text-slate-800' }
])

// ─── Quick Access ─────────────────────────────────────────────────────────────
const quickAccessLinks = ref([
  { text: 'الأرباح والخسائر',  icon: 'fa fa-chart-line',     to: '/reports/profit-loss' },
  { text: 'حركة المخزون',      icon: 'fa fa-boxes',           to: '/reports/inventory-movements' },
  { text: 'تحليلات المبيعات',  icon: 'fa fa-chart-bar',       to: '/reports/sales-analytics' },
  { text: 'نقطة البيع',        icon: 'fa fa-cash-register',   to: '/sales/point' },
  { text: 'إدارة المخزون',     icon: 'fa fa-warehouse',       to: '/inventory' },
  { text: 'كل التقارير',       icon: 'fa fa-chart-pie',       to: '/reports/sales-analytics', highlighted: true }
])

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatCurrency(value) {
  return formatCurrencyLocale(value, 2)
}

// ✅ يستخدم formatCurrency بدل hardcoded ريال
function setDefaultKpiValues() {
  kpis.value.forEach(kpi => {
    kpi.value = formatCurrency(0)
    kpi.change = 0
  })
  kpis.value[2].value = '0' // عدد الفواتير رقم مش عملة
}

function getLocalDateISO(d = new Date()) {
  const offset = d.getTimezoneOffset()
  const localDate = new Date(d.getTime() - offset * 60 * 1000)
  const yyyy = localDate.getFullYear()
  const mm = String(localDate.getMonth() + 1).padStart(2, '0')
  const dd = String(localDate.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function calcRange(range) {
  const today = new Date()
  const end = new Date(today)
  end.setHours(23, 59, 59, 999)
  const start = new Date(today)
  start.setHours(0, 0, 0, 0)
  if (range === 'week') start.setDate(start.getDate() - 6)
  else if (range === 'month') start.setDate(start.getDate() - 29)
  return { startDate: getLocalDateISO(start), endDate: getLocalDateISO(end) }
}

function handleLowStockClick(item) {
  try { router.push({ path: '/inventory', query: { product_id: item?.id } }) } catch {}
}

// ─── Charts ───────────────────────────────────────────────────────────────────
function initChart(chartRef, type, data, instanceRef) {
  if (!chartRef.value) return

  const ctx = chartRef.value.getContext('2d')

  // تنظيف الـ instance القديم
  if (instanceRef.value) { instanceRef.value.destroy(); instanceRef.value = null }

  // ✅ رسالة "لا توجد بيانات" لو الـ data فارغة
  if (!data?.labels?.length || !data?.data?.length) {
    ctx.clearRect(0, 0, chartRef.value.width, chartRef.value.height)
    ctx.save()
    ctx.fillStyle = '#cbd5e1'
    ctx.font = 'bold 13px Cairo, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('لا توجد بيانات للعرض', chartRef.value.width / 2, chartRef.value.height / 2)
    ctx.restore()
    return
  }

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: type !== 'line',
        position: 'bottom',
        labels: { font: { family: 'Cairo', size: 10 }, usePointStyle: true }
      }
    },
    scales: type === 'doughnut' ? {} : {
      y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } },
      x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } }
    }
  }

  const chartData = {
    labels: data.labels,
    datasets: [{
      label: type === 'line' ? 'المبيعات' : type === 'doughnut' ? 'الإيرادات' : 'الأداء',
      data: data.data,
      backgroundColor: type === 'line' ? 'rgba(59,130,246,0.08)' :
                       type === 'doughnut' ? ['#3b82f6','#10b981','#6366f1','#f59e0b','#f43f5e'] :
                       'rgba(99,102,241,0.7)',
      borderColor: type === 'line' ? '#3b82f6' : 'transparent',
      borderWidth: type === 'line' ? 3 : 0,
      tension: 0.4,
      fill: type === 'line',
      pointBackgroundColor: '#3b82f6',
      pointRadius: 0,
      pointHoverRadius: 6
    }]
  }

  instanceRef.value = new Chart(ctx, { type, data: chartData, options: chartOptions })
}

function updateChart(data, chartRef, instanceRef, chartType) {
  if (instanceRef.value) { instanceRef.value.destroy(); instanceRef.value = null }
  if (chartRef.value) initChart(chartRef, chartType, data || { labels: [], data: [] }, instanceRef)
}

const updateSalesChart       = (data) => updateChart(data, salesChart,        salesChartInstance,       'line')
const updateTopProductsChart = (data) => updateChart(data, topProductsChart,  topProductsChartInstance, 'doughnut')
const updatePosPerformanceChart = (data) => updateChart(data, posPerformanceChart, posPerformanceChartInstance, 'bar')

// ─── API ──────────────────────────────────────────────────────────────────────
// ✅ AbortController لمنع race condition
let dashboardAbortCtrl = null

async function fetchDashboardData(refresh = false) {
    // ✅ إلغاء الطلب السابق لو بيجري
    if (dashboardAbortCtrl) dashboardAbortCtrl.abort()
    dashboardAbortCtrl = new AbortController()
    const signal = dashboardAbortCtrl.signal

    if (refresh) { isRefreshing.value = true; await fetchSettings(true) }
    else { isLoading.value = true; await fetchSettings() }

    error.value = null

    try {
      const { startDate, endDate } = calcRange(dateRange.value)

      try {
        const [salesResponse, inventoryResponse, posResponse] = await Promise.all([
          analyticsStore.fetchSalesAnalytics({ startDate, endDate }),
          reportsStore.fetchInventoryAnalytics({ signal }),
          reportsStore.fetchPosPerformance(startDate, endDate, { signal })
        ])

        const salesData = salesResponse?.data || salesResponse || {}

      // ── KPIs ──
      try {
        const dailySales = Array.isArray(salesData.daily_sales) ? salesData.daily_sales : []
        const invoiceCount = dailySales.reduce((sum, day) => sum + (Number(day?.total_orders) || 0), 0)
        const grossProfit = parseFloat(salesData.gross_profit) || 0
        const netGrandTotal = parseFloat(salesData.net_grand_total) || parseFloat(salesData.grand_total) || 0
        const avgOrderValue = invoiceCount > 0 ? netGrandTotal / invoiceCount : 0

        kpis.value[0].value = formatCurrency(netGrandTotal)
        kpis.value[1].value = formatCurrency(grossProfit)
        kpis.value[2].value = invoiceCount.toLocaleString('en-US')
        kpis.value[3].value = formatCurrency(avgOrderValue)

        kpis.value[0].change = typeof salesData.sales_change === 'number' ? salesData.sales_change : 0
        kpis.value[1].change = typeof salesData.net_sales_change === 'number' ? salesData.net_sales_change : 0
        kpis.value[2].change = typeof salesData.invoice_count_change === 'number' ? salesData.invoice_count_change : 0
        kpis.value[3].change = typeof salesData.avg_order_value_change === 'number' ? salesData.avg_order_value_change : 0
      } catch { setDefaultKpiValues() }

      // ── Low Stock ──
      const inventoryData = inventoryResponse?.data || inventoryResponse || {}
      const branchProducts = inventoryData.branch_products || inventoryData.data?.branch_products || []
      if (Array.isArray(branchProducts) && branchProducts.length > 0) {
        lowStockItems.value = branchProducts
          .filter(it => {
            const status = (it?.stock_status || '').toLowerCase()
            const qty = Number(it?.quantity || 0)
            const minQty = Number(it?.min_quantity || 0)
            return status === 'low' || (minQty > 0 && qty <= minQty)
          })
          .map(it => ({ id: it.product_id || it.id, name: it.product_name || it.name, quantity: it.quantity, min_quantity: it.min_quantity }))
          .slice(0, 10)
      } else {
        lowStockItems.value = []
      }

      // ── Sales Chart ──
      if (Array.isArray(salesData.daily_sales) && salesData.daily_sales.length) {
        updateSalesChart({
          labels: salesData.daily_sales.map(item => item.date || ''),
          data:   salesData.daily_sales.map(item => Number(item.total_revenue ?? item.total) || 0)
        })
      } else {
        updateSalesChart({ labels: [], data: [] })
      }

      // ── Top Products Chart ──
      const topProducts = Array.isArray(salesData?.top_products) ? salesData.top_products : []
      const tpLabels = [], tpData = []
      topProducts.slice(0, 5).forEach(p => {
        if (!p) return
        const name = p.name || p.product_name || p.title || 'منتج غير معروف'
        const value = p.total_revenue ? parseFloat(p.total_revenue) : Number(p.quantity ?? p.qty ?? p.count ?? p.amount ?? 0)
        if (name && !isNaN(value) && value > 0) { tpLabels.push(name); tpData.push(value) }
      })
      updateTopProductsChart(tpLabels.length ? { labels: tpLabels, data: tpData } : { labels: [], data: [] })

      // ── POS Performance Chart ──
      try {
        // ✅ Use posResponse from Promise.all above (no duplicate call)
        const posData = posResponse?.data || posResponse || []
        if (Array.isArray(posData) && posData.length) {
          updatePosPerformanceChart({
            labels: posData.map(p => p.cashier_name || p.user_name || p.device_name || 'غير معروف'),
            data:   posData.map(p => Number(p.total_sales || p.total_amount || 0))
          })
        } else {
          updatePosPerformanceChart({ labels: [], data: [] })
        }
      } catch {
        updatePosPerformanceChart({ labels: [], data: [] })
      }

    } catch (err) {
      const isAborted = err?.name === 'AbortError' || err?.name === 'CanceledError'
      if (!isAborted) {
        error.value = { message: 'فشل تحميل بيانات لوحة التحكم', details: err.message || 'حدث خطأ غير متوقع' }
        setDefaultKpiValues()
        updateSalesChart({ labels: [], data: [] })
        updateTopProductsChart({ labels: [], data: [] })
        updatePosPerformanceChart({ labels: [], data: [] })
      }
    }

  } catch (err) {
    error.value = { message: 'فشل تحميل بيانات لوحة التحكم', details: err.message || 'حدث خطأ في الاتصال' }
  } finally {
    isLoading.value = false
    isRefreshing.value = false
    lastUpdated.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  }
}

function setDateRange(range) { dateRange.value = range; fetchDashboardData() }
async function refresh() { await fetchDashboardData(true) }
async function retry() { await fetchDashboardData() }

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await authStore.initialize?.();
  if (!authStore.isAdmin) { 
    router.push('/cashier-dashboard'); 
    return; 
  }
  await fetchDashboardData()
})

onBeforeUnmount(() => {
  if (salesChartInstance.value)        salesChartInstance.value.destroy()
  if (topProductsChartInstance.value)  topProductsChartInstance.value.destroy()
  if (posPerformanceChartInstance.value) posPerformanceChartInstance.value.destroy()
  // ✅ إلغاء أي API call معلق
  if (dashboardAbortCtrl) dashboardAbortCtrl.abort()
})
</script>

<style scoped>

.kpi-card { @apply bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl; }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>