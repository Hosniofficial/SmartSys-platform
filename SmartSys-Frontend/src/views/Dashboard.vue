<template>
  <!-- Main Canvas: Using a neutral Zinc/Slate palette for a high-end SaaS feel -->
  <div class="min-h-screen bg-[#fafafa] text-slate-900 font-sans antialiased selection:bg-blue-100" dir="rtl">
    
    <!-- Top Progress/Loading Bar (Subtle) -->
    <div v-if="isLoading || isRefreshing" class="fixed top-0 left-0 right-0 h-0.5 bg-blue-600/10 z-50">
      <div class="h-full bg-blue-600 animate-[loading_2s_ease-in-out_infinite] w-1/3"></div>
    </div>

    <div class="max-w-[1600px] mx-auto p-6 lg:p-10 space-y-8">
      
      <!-- Error State: Stripe-inspired Alert -->
      <transition name="slide-down">
        <div v-if="error" class="bg-white border border-red-200 rounded-lg p-4 shadow-sm flex items-center justify-between group">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center text-red-600">
              <i class="fas fa-exclamation-circle text-sm"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ error.message }}</p>
              <p class="text-xs text-slate-500">{{ error.details }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button @click="retry" class="text-xs font-medium px-3 py-1.5 bg-slate-900 text-white rounded-md hover:bg-slate-800 transition-colors">
              إعادة المحاولة
            </button>
            <button @click="error = null" class="p-1.5 text-slate-400 hover:text-slate-600">
              <i class="fas fa-times text-xs"></i>
            </button>
          </div>
        </div>
      </transition>

      <!-- Page Header -->
      <PageHeader
        :breadcrumb="{ parent: { label: 'الرئيسية', path: '/' }, current: { label: 'لوحة التحكم', path: '/dashboard' } }"
        :title="`مرحباً، ${username}`"
        :description="`نظرة عامة على أداء المتجر والعمليات الحالية. آخر تحديث: ${lastUpdated || '---'}`"
        :branches="branches"
        :selectedBranch="selectedBranch"
        :hasExplicitSelection="hasExplicitBranchSelection"
        @branch-changed="onBranchChange"
      >
        <template #controls>
          <!-- Date Range Toggles -->
          <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200/50">
            <button v-for="range in ['today', 'week', 'month']" :key="range" 
              @click="setDateRange(range)" 
              :class="[dateRange === range ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700']"
              class="px-4 py-1.5 rounded-md text-xs font-semibold transition-all">
              {{ range === 'today' ? 'اليوم' : range === 'week' ? 'الأسبوع' : 'الشهر' }}
            </button>
          </div>

          <button @click="refresh" :disabled="isRefreshing || isLoading" 
            class="h-9 w-9 flex items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 hover:text-blue-600 hover:border-blue-200 disabled:opacity-50 transition-all shadow-sm">
            <i class="fas fa-sync-alt text-xs" :class="{ 'animate-spin': isRefreshing }"></i>
          </button>
        </template>
      </PageHeader>

      <!-- KPI Grid: Supabase-inspired Data Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
        <template v-if="isLoading">
          <div v-for="i in 4" :key="i" class="h-32 bg-white border border-slate-200 rounded-xl animate-pulse"></div>
        </template>
        <template v-else>
          <div v-for="kpi in kpis" :key="kpi.title" class="group bg-white border border-slate-200 p-6 rounded-xl hover:border-blue-500/30 hover:shadow-md hover:shadow-blue-500/5 transition-all duration-300">
            <div class="flex justify-between items-start mb-4">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">{{ kpi.title }}</span>
              <div :class="[kpi.iconClass, 'w-8 h-8 rounded-lg flex items-center justify-center text-sm bg-opacity-10 opacity-80 group-hover:opacity-100 group-hover:scale-110 transition-transform']">
                <i :class="kpi.icon"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2">
              <h3 :class="[kpi.valueClass || 'text-slate-900']" class="text-2xl font-bold tracking-tight">{{ kpi.value }}</h3>
              <div v-if="kpi.change !== 0" 
                :class="[kpi.change > 0 ? 'text-emerald-600' : 'text-red-600']"
                class="text-[10px] font-bold flex items-center">
                <i :class="kpi.change > 0 ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" class="ml-0.5"></i>
                {{ Math.abs(kpi.change).toFixed(1) }}%
              </div>
            </div>
            <!-- Sparkline visualization (Decoration only) -->
            <div class="mt-4 h-1 w-full bg-slate-50 rounded-full overflow-hidden">
              <div :class="[kpi.change >= 0 ? 'bg-emerald-500' : 'bg-red-500']" class="h-full opacity-20" :style="{ width: '100%' }"></div>
            </div>
          </div>
        </template>
      </section>

      <!-- Main Analytics: GitHub/Vercel Layout -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Sales Chart -->
        <div class="lg:col-span-8 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold flex items-center gap-2">
              <i class="fas fa-chart-area text-blue-500"></i>
              اتجاهات المبيعات
            </h2>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">محدث بشكل لحظي</span>
          </div>
          <div class="p-6 h-[400px]">
            <canvas ref="salesChart"></canvas>
          </div>
        </div>

        <!-- Top Products: Side Widget -->
        <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold flex items-center gap-2">
              <i class="fas fa-trophy text-amber-500"></i>
              الأكثر مبيعاً
            </h2>
          </div>
          <div class="p-6 h-[400px]">
            <canvas ref="topProductsChart"></canvas>
          </div>
        </div>
      </section>

      <!-- Bottom Grid: Inventory & Staff -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Low Stock Table -->
        <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
            <h2 class="text-sm font-bold flex items-center gap-2">
              <i class="fas fa-boxes text-red-500"></i>
              تنبيهات المخزون
            </h2>
            <span class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold">حرج</span>
          </div>
          <div class="flex-1 overflow-y-auto custom-scroll">
            <div v-if="lowStockItems.length" class="divide-y divide-slate-100">
              <div v-for="item in lowStockItems" :key="item.id" 
                class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors group">
                <div>
                  <p class="text-sm font-bold text-slate-800">{{ item.name }}</p>
                  <p class="text-[10px] text-slate-500">الحد الأدنى: {{ item.min_quantity }} وحدة</p>
                </div>
                <div class="flex items-center gap-4">
                  <div class="text-left">
                    <p class="text-sm font-black text-red-600">{{ item.quantity }}</p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase">متبقي</p>
                  </div>
                  <button @click="handleLowStockClick(item)" class="p-2 rounded-md border border-slate-200 text-slate-400 hover:text-blue-600 hover:bg-white transition-all shadow-sm">
                    <i class="fas fa-external-link-alt text-[10px]"></i>
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="h-full flex flex-col items-center justify-center py-12 text-slate-300">
              <i class="fas fa-check-circle text-4xl mb-2 opacity-20"></i>
              <p class="text-xs font-bold uppercase tracking-widest">المخزون ممتاز</p>
            </div>
          </div>
        </div>

        <!-- POS Performance -->
        <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
          <div class="p-6 border-b border-slate-100">
            <h2 class="text-sm font-bold flex items-center gap-2">
              <i class="fas fa-users text-indigo-500"></i>
              تحليل أداء البائعين
            </h2>
          </div>
          <div class="p-6 h-[320px]">
            <canvas ref="posPerformanceChart"></canvas>
          </div>
        </div>
      </section>

      <!-- Quick Access: Action Bar -->
      <section class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
        <div class="mb-8 flex items-center gap-4">
          <h2 class="text-sm font-bold text-slate-900">الوصول السريع</h2>
          <div class="h-px bg-slate-200 flex-1"></div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <router-link v-for="link in quickAccessLinks" :key="link.to" :to="link.to" 
            :class="[link.highlighted ? 'bg-blue-50 border-blue-300 shadow-md shadow-blue-100' : 'bg-slate-50 border-slate-200']"
            class="group relative p-5 rounded-xl transition-all border hover:bg-blue-50 hover:border-blue-300">
            <div :class="[link.highlighted ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600']" 
              class="w-10 h-10 rounded-lg flex items-center justify-center mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
              <i :class="link.icon" class="text-sm"></i>
            </div>
            <span class="text-xs font-bold leading-snug text-slate-900">{{ link.text }}</span>
            <i class="fas fa-arrow-left absolute bottom-5 left-5 text-[10px] text-slate-300 opacity-0 group-hover:opacity-100 transition-all -translate-x-2 group-hover:translate-x-0"></i>
          </router-link>
        </div>
      </section>

    </div>
  </div>
</template>

<script setup>
// ⚠️ تحذير: تم تصحيح أخطاء regression في النسخة الجديدة:
// 1. استرجاع فحص 'CanceledError' بالإضافة إلى 'AbortError'
// 2. تصحيح رسالة الخطأ الخارجية
// 3. إزالة ازدواجية استدعاء fetchDashboardData عند تغيير الفرع
// 4. استرجاع عرض lastUpdated وتفعيل الخصائص المرئية

import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import Chart from 'chart.js/auto'
import { useAuthStore } from '../stores/auth'
import { useReportsStore } from '../stores/reports'
import { useAnalyticsStore } from '../stores/analytics'
import { useRouter } from 'vue-router'
import { useCompanyCurrency } from '../composables/useCompanyCurrency'
import { useBranchStore } from '../stores/branch'
import { useSessionExemption } from '../composables/useCashierSessionGuard'
import PageHeader from '../components/PageHeader.vue'

const authStore = useAuthStore()
const reportsStore = useReportsStore()
const analyticsStore = useAnalyticsStore()
const router = useRouter()
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency()
const branchStore = useBranchStore()
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption()

const branches = computed(() => branchStore.branches)
const selectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
})

// ✅ يتتبع الاختيار اليدوي للفرع (النمط A — اتساق مع بقية الصفحات)
const userChoseBranch = ref(
  localStorage.getItem('selectedBranchId') !== null
  && localStorage.getItem('selectedBranchId') !== 'all'
)
const hasExplicitBranchSelection = computed(() => userChoseBranch.value && branchStore.selectedBranchId !== null)

const salesChart = ref(null)
const topProductsChart = ref(null)
const posPerformanceChart = ref(null)
const salesChartInstance = ref(null)
const topProductsChartInstance = ref(null)
const posPerformanceChartInstance = ref(null)

const username = computed(() => authStore.user?.name || 'مدير المحل')
const isAdmin = computed(() => authStore.isAdmin)

const isLoading = ref(false)
const isRefreshing = ref(false)
const error = ref(null)
const lastUpdated = ref('')
const dateRange = ref('week')
const lowStockItems = ref([])

const kpis = ref([
  { title: 'إجمالي المبيعات', value: '---', change: 0, icon: 'fa fa-dollar-sign', iconClass: 'bg-blue-500 text-blue-600', valueClass: 'text-slate-900' },
  { title: 'صافي الأرباح',    value: '---', change: 0, icon: 'fa fa-wallet',       iconClass: 'bg-emerald-500 text-emerald-600',    valueClass: 'text-emerald-600'  },
  { title: 'عدد الفواتير',    value: '0',   change: 0, icon: 'fa fa-receipt',      iconClass: 'bg-amber-500 text-amber-600',  valueClass: 'text-slate-900' },
  { title: 'متوسط الفاتورة',  value: '---', change: 0, icon: 'fa fa-calculator',   iconClass: 'bg-indigo-500 text-indigo-600',valueClass: 'text-slate-900' }
])

const quickAccessLinks = ref([
  { text: 'الأرباح والخسائر',  icon: 'fa fa-chart-line',     to: '/reports/profit-loss' },
  { text: 'حركة المخزون',      icon: 'fa fa-boxes',           to: '/reports/inventory-movements' },
  { text: 'تحليلات المبيعات',  icon: 'fa fa-chart-bar',       to: '/reports/sales-analytics' },
  { text: 'نقطة البيع',        icon: 'fa fa-cash-register',   to: '/sales/point' },
  { text: 'إدارة المخزون',     icon: 'fa fa-warehouse',       to: '/inventory' },
  { text: 'كل التقارير',       icon: 'fa fa-chart-pie',       to: '/reports/sales-analytics', highlighted: true }
])

function formatCurrency(value) {
  return formatCurrencyLocale(value, 2)
}

function setDefaultKpiValues() {
  kpis.value.forEach(kpi => {
    kpi.value = formatCurrency(0)
    kpi.change = 0
  })
  kpis.value[2].value = '0'
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

function initChart(chartRef, type, data, instanceRef) {
  if (!chartRef.value) return
  const ctx = chartRef.value.getContext('2d')
  if (instanceRef.value) { instanceRef.value.destroy(); instanceRef.value = null }

  if (!data?.labels?.length || !data?.data?.length) {
    ctx.clearRect(0, 0, chartRef.value.width, chartRef.value.height)
    ctx.save(); ctx.fillStyle = '#94a3b8'; ctx.font = '12px Cairo'; ctx.textAlign = 'center';
    ctx.fillText('لا توجد بيانات', chartRef.value.width / 2, chartRef.value.height / 2); ctx.restore()
    return
  }

  // 🔧 تصحيح: استخدام labels ديناميكية حسب نوع الرسم
  const labelMap = {
    line: 'إجمالي الإيرادات',
    doughnut: 'المبيعات',
    bar: 'أداء البائعين'
  }

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: type === 'doughnut', position: 'bottom', labels: { boxWidth: 12, font: { family: 'Cairo', size: 10 } } },
      tooltip: { backgroundColor: '#0f172a', padding: 12, bodyFont: { family: 'Cairo' }, titleFont: { family: 'Cairo' } }
    },
    scales: type === 'doughnut' ? {} : {
      y: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { font: { size: 10, family: 'Cairo' }, color: '#94a3b8' } },
      x: { grid: { display: false }, ticks: { font: { size: 10, family: 'Cairo' }, color: '#94a3b8' } }
    }
  }

  const chartData = {
    labels: data.labels,
    datasets: [{
      label: labelMap[type] || 'القيمة',
      data: data.data,
      backgroundColor: type === 'line' ? 'rgba(59, 130, 246, 0.05)' : (type === 'bar' ? 'rgba(99,102,241,0.7)' : ['#3b82f6', '#10b981', '#6366f1', '#f59e0b', '#f43f5e']),
      borderColor: type === 'line' ? '#3b82f6' : 'transparent',
      borderWidth: type === 'line' ? 2 : 0,
      tension: 0.4,
      fill: true,
      pointRadius: 0,
      pointHoverRadius: 4,
      borderRadius: type === 'bar' ? 4 : 0
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

let dashboardAbortCtrl = null

async function fetchDashboardData(refresh = false) {
    if (dashboardAbortCtrl) dashboardAbortCtrl.abort()
    dashboardAbortCtrl = new AbortController()
    const signal = dashboardAbortCtrl.signal

    if (refresh) { isRefreshing.value = true; await fetchSettings(true) }
    else { isLoading.value = true; await fetchSettings() }

    error.value = null

    try {
      const { startDate, endDate } = calcRange(dateRange.value)
      const branchId = selectedBranch.value ? String(selectedBranch.value) : null

      try {
        const [salesResponse, inventoryResponse, posResponse] = await Promise.all([
          analyticsStore.fetchSalesAnalytics({ startDate, endDate, branchId, force: refresh }),
          reportsStore.fetchInventoryAnalytics({ signal, branchId }),
          reportsStore.fetchPosPerformance(startDate, endDate, { signal, branchId })
        ])

        const salesData = salesResponse?.data || salesResponse || {}

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

      if (Array.isArray(salesData.daily_sales) && salesData.daily_sales.length) {
        updateSalesChart({
          labels: salesData.daily_sales.map(item => item.date || ''),
          data:   salesData.daily_sales.map(item => Number(item.total_revenue ?? item.total) || 0)
        })
      } else {
        updateSalesChart({ labels: [], data: [] })
      }

      const topProducts = Array.isArray(salesData?.top_products) ? salesData.top_products : []
      const tpLabels = [], tpData = []
      topProducts.slice(0, 5).forEach(p => {
        if (!p) return
        const name = p.name || p.product_name || p.title || 'منتج غير معروف'
        const value = p.total_revenue ? parseFloat(p.total_revenue) : Number(p.quantity ?? p.qty ?? p.count ?? p.amount ?? 0)
        if (name && !isNaN(value) && value > 0) { tpLabels.push(name); tpData.push(value) }
      })
      updateTopProductsChart(tpLabels.length ? { labels: tpLabels, data: tpData } : { labels: [], data: [] })

      try {
        const posData = Array.isArray(posResponse?.data) ? posResponse.data : []
        if (posData.length) {
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
      // 🔧 تصحيح: استرجاع فحص 'CanceledError' لمنع معاملة الطلبات المُلغاة كأخطاء حقيقية
      const isAborted = err?.name === 'AbortError' || err?.name === 'CanceledError'
      if (!isAborted) {
        error.value = { message: 'فشل تحميل بيانات لوحة التحكم', details: err.message || 'حدث خطأ في الاتصال' }
        setDefaultKpiValues(); updateSalesChart({ labels: [], data: [] }); updateTopProductsChart({ labels: [], data: [] }); updatePosPerformanceChart({ labels: [], data: [] })
      }
    }

  } catch (err) {
    error.value = { message: 'فشل تحميل بيانات لوحة التحكم', details: err.message || 'حدث خطأ في الاتصال' }
  } finally {
    isLoading.value = false; isRefreshing.value = false
    lastUpdated.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  }
}

function setDateRange(range) { dateRange.value = range; fetchDashboardData() }
async function refresh() { await fetchDashboardData(true) }
async function retry() { await fetchDashboardData() }
// 🔧 تصحيح: إزالة الاستدعاء المباشر لـ fetchDashboardData داخل onBranchChange لتجنب ازدواجية الطلب
// watch(selectedBranch) سيتولى التحديث تلقائياً بعد setSelectedBranch
const onBranchChange = (id) => {
  branchStore.setSelectedBranch(id)
  userChoseBranch.value = (id !== null && id !== '' && id !== 'all')
}

watch(selectedBranch, () => { fetchDashboardData(true) })  // ✅ force=true

onMounted(async () => {
  await authStore.initialize?.();
  if (!authStore.isAdmin) { router.push('/cashier-dashboard'); return; }
  await ensureExemptionLoaded().catch(() => {})
  // ✅ FIX: تهيئة branch context قبل أول API call (النمط A)
  const hadPriorBranchChoice = localStorage.getItem('selectedBranchId') !== null
                                && localStorage.getItem('selectedBranchId') !== 'all';
  branchStore.loadFromStorage?.()
  await branchStore.fetchBranches().catch(() => {})
  // بعد fetchBranches: أعد تعيين الـ flag بما كان موجوداً قبل الكتابة
  userChoseBranch.value = hadPriorBranchChoice;
  await fetchDashboardData()
})

onBeforeUnmount(() => {
  [salesChartInstance, topProductsChartInstance, posPerformanceChartInstance].forEach(i => i.value?.destroy())
  if (dashboardAbortCtrl) dashboardAbortCtrl.abort()
})
</script>

<style scoped>
@keyframes loading { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease-out; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }
</style>