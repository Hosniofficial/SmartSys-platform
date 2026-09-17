<template>
  <div class="min-h-screen bg-[#fafafa] text-slate-900 font-sans antialiased selection:bg-blue-100" dir="rtl">

    <!-- Global Loading Progress -->
    <div v-if="isLoadingData" class="fixed top-0 left-0 right-0 h-0.5 bg-blue-600/10 z-50">
      <div class="h-full bg-blue-600 animate-[loading_2s_ease-in-out_infinite] w-1/3"></div>
    </div>

    <!-- Sticky Header: Glassmorphism & High-Contrast Navigation -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200 px-4 lg:px-6 py-3">
      <div class="max-w-[1600px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <!-- User Identity & Status -->
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-white shadow-sm shrink-0 transition-transform hover:scale-105">
            <i class="fas fa-user-tie text-sm"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-sm font-bold text-slate-900 leading-none">مرحباً، {{ cashierName }}</h1>
              <!-- Shift Status Badge -->
              <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-indigo-50 border border-indigo-100 text-[9px] font-bold text-indigo-600 uppercase tracking-tighter">
                <span>الوردية الحالية</span>
                <span class="font-mono">#{{ currentShift?.id || '—' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2 mt-1.5">
              <span :class="[activeSessionId ? 'text-emerald-600' : 'text-amber-600']" class="text-[10px] font-bold flex items-center gap-1.5">
                <span class="relative flex h-1.5 w-1.5">
                  <span v-if="activeSessionId" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-1.5 w-1.5" :class="activeSessionId ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                </span>
                {{ sessionStatus.text }}
              </span>
            </div>
          </div>
        </div>

        <!-- Global Controls -->
        <div class="flex items-center gap-3">
          <!-- Time/Clock Display -->
          <div class="hidden xl:flex items-center gap-4 px-4 border-l border-slate-200">
            <div class="text-left">
              <p class="text-[11px] font-bold text-slate-900 leading-none font-mono tracking-tighter">{{ currentTime }}</p>
              <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ currentDate }}</p>
            </div>
          </div>

          <!-- Branch Selector (AdminPatterns) -->
          <div v-if="authStore.isAdmin && branches.length" class="relative">
            <select
              v-model="adminSelectedBranch"
              @change="handleAdminBranchChange"
              class="h-9 pr-9 pl-4 rounded-md border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-slate-300 focus:ring-4 focus:ring-blue-500/5 outline-none transition-all appearance-none cursor-pointer min-w-[160px]"
            >
              <option :value="null" disabled>اختر الفرع</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <i class="fas fa-warehouse absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
          </div>

          <div class="flex items-center gap-2">
            <button @click="handleRefresh" class="h-9 w-9 flex items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 hover:text-blue-600 transition-colors shadow-sm" title="تحديث البيانات">
              <i class="fas fa-sync-alt text-xs" :class="{ 'animate-spin': isLoadingData }"></i>
            </button>

            <!-- Operational Decision Buttons -->
            <button v-if="!activeSessionId" @click="triggerOpenSession" class="h-9 px-5 bg-emerald-600 text-white rounded-md text-xs font-bold shadow-lg shadow-emerald-900/20 hover:bg-emerald-700 transition-all flex items-center gap-2 active:scale-95">
              <i class="fas fa-play text-[9px]"></i> فتح جلسة
            </button>
            <button v-else @click="attemptEndShift" class="h-9 px-5 bg-rose-600 text-white rounded-md text-xs font-bold shadow-lg shadow-rose-900/20 hover:bg-rose-700 transition-all flex items-center gap-2 active:scale-95">
              <i class="fas fa-power-off text-[9px]"></i> إغلاق الجلسة
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-[1600px] mx-auto p-6 lg:p-8 space-y-8">
      
      <!-- KPI Grid: Supabase-inspired Data Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <template v-if="isLoadingData && !hasLoadedOnce">
          <div v-for="n in 4" :key="n" class="h-28 bg-white border border-slate-200 rounded-xl animate-pulse"></div>
        </template>
        <template v-else>
          <div v-for="card in summaryCards" :key="card.id" class="group bg-white border border-slate-200 p-6 rounded-xl hover:border-blue-500/30 transition-all shadow-sm">
            <div class="flex justify-between items-start mb-3">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ card.title }}</span>
              <div :class="[card.iconBg, card.iconColor, 'w-8 h-8 rounded-lg flex items-center justify-center text-xs opacity-80 group-hover:opacity-100 transition-opacity shadow-inner']">
                <i :class="card.icon"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2">
              <h3 :class="[card.valueColor, 'text-2xl font-bold tracking-tight font-mono']">{{ formatPrice(card.value) }}</h3>
              <span v-if="card.tooltip" class="text-[9px] font-bold text-slate-300 uppercase tracking-tighter">{{ card.tooltip }}</span>
            </div>
          </div>
        </template>
      </section>

      <!-- Cash Reconciliation: Stripe-inspired High Density Card -->
      <section class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <h2 class="text-sm font-bold flex items-center gap-2 text-slate-900 uppercase tracking-tight">
            <i class="fas fa-vault text-slate-400"></i>
            تسوية المدفوعات والسيولة (Reconciliation)
          </h2>
          <div class="flex items-center gap-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> نقدي</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-cyan-400"></span> إلكتروني</span>
          </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2">
          <!-- Physical Cash: The Bottom Line -->
          <div class="p-10 border-l border-slate-100 flex flex-col items-center justify-center text-center space-y-4">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.3em]">النقدية الفعلية بالخزينة</p>
            <div class="text-5xl font-bold text-slate-900 tracking-tighter font-mono">
              {{ formatPrice(dashboardStats.cashControl?.cashSales || cashDrawerTotal) }}
            </div>
            <p class="text-[10px] font-medium text-slate-400 italic">✓ تشمل المبيعات النقدية والعهد الافتتاحية والمصروفات</p>
          </div>

          <!-- Electronic Breakdown: Modern Ledger Style -->
          <div class="p-10 bg-slate-50/30 flex flex-col justify-center">
            <div class="grid grid-cols-3 gap-6 mb-8">
              <div v-for="(val, key) in { 'البطاقات': 'card', 'المحفظة': 'wallet', 'آجل': 'credit' }" :key="key" class="space-y-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ key }}</p>
                <p class="text-xl font-bold text-slate-800 font-mono tracking-tighter">{{ formatPrice(dashboardStats.electronicSettlements?.[val] || 0) }}</p>
              </div>
            </div>
            <div class="pt-6 border-t border-slate-200 flex justify-between items-end">
              <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">إجمالي التسويات الرقمية</p>
                <p class="text-2xl font-bold text-blue-600 font-mono tracking-tighter">{{ formatPrice(dashboardStats.electronicSettlements?.total || 0) }}</p>
              </div>
              <span class="text-[8px] font-bold text-slate-300 uppercase tracking-widest">Settled Automatically</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Main Operational Area -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Operations & Activity -->
        <div class="lg:col-span-4 space-y-8">
          <!-- Quick Actions: Functional Tiles -->
          <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-widest mb-6 flex items-center gap-2">
              <i class="fas fa-bolt text-amber-500"></i> اختصارات سريعة
            </h3>
            <div class="grid grid-cols-1 gap-2.5">
              <button v-for="action in displayedQuickActions" :key="action.id" @click="handleQuickAction(action)"
                :class="[action.primary ? 'bg-slate-900 text-white shadow-lg shadow-slate-200' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50']"
                class="w-full px-4 py-3.5 border rounded-lg flex items-center justify-between transition-all group active:scale-95">
                <div class="flex items-center gap-3">
                  <i :class="[action.icon, action.primary ? 'text-blue-400' : 'text-slate-400 group-hover:text-blue-600']" class="text-sm transition-colors"></i>
                  <div class="text-right">
                    <p class="text-xs font-bold">{{ action.title }}</p>
                    <p class="text-[9px] opacity-50 font-medium">{{ action.description }}</p>
                  </div>
                </div>
                <i class="fas fa-chevron-left text-[8px] opacity-30 group-hover:opacity-100 transition-opacity"></i>
              </button>
            </div>
          </section>

          <!-- Activity Feed: Professional Timeline -->
          <section class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <h3 class="text-xs font-bold text-slate-900 uppercase tracking-widest">النشاطات الأخيرة</h3>
              <span class="px-2 py-0.5 rounded bg-white border border-slate-200 text-[10px] font-bold text-slate-500 font-mono">{{ recentActivities.length }}</span>
            </div>
            <div class="flex-1 overflow-y-auto custom-scroll divide-y divide-slate-50 max-h-[500px]">
              <div v-for="activity in recentActivities" :key="activity.id" @click="handleActivityClick(activity)"
                class="p-4 hover:bg-blue-50/30 transition-colors cursor-pointer group flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div :class="[activity.colorClass, 'w-9 h-9 rounded-lg flex items-center justify-center text-xs bg-opacity-10']">
                    <i :class="['fas', activity.icon]"></i>
                  </div>
                  <div>
                    <p class="text-xs font-bold text-slate-800 leading-snug group-hover:text-blue-600 transition-colors">{{ activity.description }}</p>
                    <p class="text-[9px] text-slate-400 font-medium uppercase mt-1" :title="activity.fullTime">{{ activity.time }}</p>
                  </div>
                </div>
                <span :class="[activity.amount > 0 ? 'text-emerald-600' : 'text-rose-600']" class="text-xs font-bold font-mono tracking-tighter">
                  {{ formatPrice(activity.amount) }}
                </span>
              </div>
              <div v-if="!recentActivities.length" class="py-20 text-center text-slate-300">
                <i class="fas fa-stream text-2xl mb-2 opacity-20 block mx-auto"></i>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em]">لا توجد عمليات مسجلة</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Right Column: Visual Insights -->
        <div class="lg:col-span-8 space-y-8">
          <!-- Real-time Sales Chart Card -->
          <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col overflow-hidden relative group">
            <div class="absolute top-0 left-0 w-40 h-40 bg-blue-500/5 rounded-full -translate-x-12 -translate-y-12 group-hover:scale-110 transition-transform duration-1000"></div>
            <div class="flex items-center justify-between mb-8 relative z-10">
              <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-chart-line text-blue-500"></i> تحليل مبيعات الجلسة
              </h3>
              <span class="text-[9px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded border border-slate-100">Live Trend</span>
            </div>
            <div class="h-[320px] relative z-10">
              <canvas ref="salesChart"></canvas>
            </div>
          </section>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Top Selling Products -->
            <section class="bg-white border border-slate-200 rounded-xl p-6">
              <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-8">توزيع مبيعات الأصناف</h3>
              <div class="h-[240px]">
                <canvas ref="topProductsChart"></canvas>
              </div>
            </section>

            <!-- Leaderboard Table -->
            <section class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col">
              <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-6">الأصناف الأكثر طلباً</h3>
              <div class="flex-grow space-y-4">
                <div v-for="(product, index) in topProducts" :key="product.id" class="flex items-center justify-between group">
                  <div class="flex items-center gap-3">
                    <div :class="[getProductColor(index).bg, getProductColor(index).text]" class="w-7 h-7 rounded-md flex items-center justify-center font-black text-[10px] shadow-sm">
                      {{ index + 1 }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-slate-800 truncate max-w-[140px] group-hover:text-blue-600 transition-colors">{{ product.name }}</p>
                      <p class="text-[9px] text-slate-400 font-medium font-mono uppercase">{{ formatNumber(product.quantity) }} {{ product.unit }}</p>
                    </div>
                  </div>
                  <p class="text-xs font-bold text-slate-900 font-mono tracking-tighter">{{ formatPrice(product.totalSales) }}</p>
                </div>
                <div v-if="!topProducts.length" class="h-full flex flex-col items-center justify-center py-10 opacity-20">
                   <i class="fas fa-box-open text-3xl mb-2"></i>
                   <p class="text-[10px] font-black uppercase tracking-widest text-center">لا توجد بيانات</p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </main>

    <!-- Modals Section: High-End Functional Overlays -->
    <Teleport to="body">
      <div v-if="openSessionModal || shiftState === 'ending'" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
        
        <!-- Open Session Modal -->
        <div v-if="openSessionModal" ref="openSessionModalRef" class="bg-white w-full max-w-md rounded-xl shadow-2xl overflow-hidden border border-slate-200 animate-modalIn" role="dialog" aria-modal="true" tabindex="-1">
          <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center text-white"><i class="fas fa-key text-sm"></i></div>
              <h3 class="text-sm font-bold text-slate-900 uppercase">بدء جلسة عمل جديدة</h3>
            </div>
            <button @click="openSessionModal = false" class="text-slate-400 hover:text-slate-900 transition-colors"><i class="fas fa-times text-lg"></i></button>
          </div>
          
          <div class="p-8 space-y-6">
            <div v-if="authStore.isAdmin" class="space-y-1.5">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">الفرع المحدد</label>
              <div class="h-10 px-4 rounded-lg bg-blue-50 border border-blue-100 flex items-center text-xs font-bold text-blue-700">
                <i class="fas fa-building ml-2 opacity-50"></i> {{ branches.find(b => b.id === adminSelectedBranch)?.name || '-' }}
              </div>
            </div>

            <div v-if="terminals && terminals.length > 0" class="space-y-1.5">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">جهاز نقطة البيع</label>
              <select v-model="selectedTerminalId" class="h-10 w-full border border-slate-200 rounded-lg px-3 text-xs font-bold focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 outline-none transition-all">
                <option :value="null" disabled>-- اختر الجهاز --</option>
                <option v-for="t in terminals" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">العهدة الافتتاحية</label>
              <div class="relative">
                <input v-model.number="openingCashAmount" type="number" class="h-14 w-full border-2 border-slate-100 rounded-xl px-4 text-3xl font-bold text-center text-blue-600 focus:border-blue-500 outline-none transition-all" />
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase">{{ formatCurrencyLocale(0,0).replace(/[0-9]/g, '').trim() }}</span>
              </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button @click="openSessionModal = false" class="flex-1 h-10 text-xs font-bold text-slate-500">إلغاء</button>
              <button @click="confirmOpenSession" :disabled="isOpeningSession || !selectedTerminalId" class="flex-2 h-10 bg-blue-600 text-white rounded-lg text-xs font-bold shadow-lg shadow-blue-900/20 active:scale-95 transition-all">
                {{ isOpeningSession ? 'جاري المعالجة...' : 'تأكيد البدء' }}
              </button>
            </div>
          </div>
        </div>

        <!-- End Session Modal: Reconciliation Form -->
        <div v-if="shiftState === 'ending'" ref="endShiftModalRef" class="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden border border-slate-200 animate-modalIn" role="dialog" aria-modal="true" tabindex="-1">
          <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase">إغلاق وتصفية الجلسة</h3>
            <button @click="shiftState = 'active'" class="text-slate-400 hover:text-slate-900 transition-colors"><i class="fas fa-times text-lg"></i></button>
          </div>

          <div class="p-8 space-y-8">
            <div class="grid grid-cols-2 gap-4">
              <div v-for="it in [
                {l:'إجمالي المبيعات', v:dashboardStats.sessionData?.total_sales, c:'text-emerald-600'},
                {l:'الرصيد الافتتاحي', v:dashboardStats.openingBalance, c:'text-slate-500'},
                {l:'المرتجعات', v:dashboardStats.totalReturns, c:'text-rose-500'}
              ]" :key="it.l" class="p-4 bg-slate-50 rounded-lg border border-slate-100">
                <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">{{ it.l }}</p>
                <p :class="[it.c, 'text-sm font-bold font-mono tracking-tighter']">{{ formatPrice(it.v) }}</p>
              </div>
              <div class="p-4 bg-slate-900 rounded-lg text-center flex flex-col justify-center shadow-lg">
                <p class="text-[9px] font-bold text-white/30 uppercase mb-1">النقد المتوقع</p>
                <p class="text-sm font-bold text-blue-400 font-mono tracking-tighter">{{ formatPrice(dashboardStats.sessionData?.expected_cash || expectedInDrawer) }}</p>
              </div>
            </div>

            <div class="text-center space-y-4">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">جرد الخزينة (المبلغ الفعلي)</label>
              <input v-model.number="closingCashInput" type="number" class="h-16 w-full border-2 border-slate-100 rounded-xl px-4 text-4xl font-bold text-center text-slate-800 focus:border-blue-500 outline-none transition-all font-mono" placeholder="0.00" />
              
              <transition name="fade">
                <div v-if="closingCashInput !== null" :class="[cashDifference === 0 ? 'text-emerald-600 bg-emerald-50 border-emerald-100' : 'text-rose-600 bg-rose-50 border-rose-100']" class="p-3 rounded-lg border text-xs font-bold font-mono">
                  الفارق: {{ formatPrice(cashDifference) }}
                </div>
              </transition>
            </div>

            <div v-if="closingCashInput !== null && Math.abs(cashDifference) > 0.01" class="space-y-3 animate-fadeIn">
               <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">سبب التباين</label>
               <select v-model="selectedVarianceReason" @change="handleVarianceReasonChange" class="h-10 w-full border border-slate-200 rounded-lg px-3 text-xs font-bold outline-none">
                 <option v-for="r in varianceReasons" :key="r.value" :value="r.value">{{ r.label }}</option>
               </select>
               <textarea v-if="selectedVarianceReason === 'other'" v-model="varianceReason" rows="2" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-lg text-xs font-medium italic outline-none focus:bg-white transition-all" placeholder="ملاحظات توضيحية للفارق..."></textarea>
            </div>

            <div class="flex gap-3">
              <button @click="shiftState = 'active'" class="flex-1 h-11 text-xs font-bold text-slate-500">تراجع</button>
              <button @click="confirmEndShift" class="flex-2 h-11 bg-rose-600 text-white rounded-lg text-xs font-bold shadow-lg shadow-rose-900/20 active:scale-95 transition-all">تأكيد التصفية والإغلاق</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/composables/useToast';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useTerminalStore } from '@/stores/terminal/terminalStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useSessionStore } from '@/stores/session/sessionStore';
import { useShiftStore } from '@/stores/shift/shiftStore';
import { useAnalyticsStore } from '@/stores/analytics';
import Chart from 'chart.js/auto';
import { localDateRangeToUTC, getLocalDateISO } from '@/utils/date';
import { useBranchIsolation } from '@/composables/useBranchIsolation';

// ─── Composables ──────────────────────────────────────────────────────────────
const router = useRouter();
const { showToast } = useToast();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const terminalStore = useTerminalStore();
const branchIsolation = useBranchIsolation();
const paymentStore = usePaymentStore();
const sessionStore = useSessionStore();
const shiftStore = useShiftStore();
const analyticsStore = useAnalyticsStore();

// ─── State ────────────────────────────────────────────────────────────────────
const cashierName = ref('مستخدم');
const currentTime = ref('');
const currentDate = ref('');
const branches = computed(() => branchStore.branches);

// ✅ Computed مشترك واحد لتحديد الفرع الفعّال بغض النظر عن نوع المستخدم
const effectiveBranchId = computed(() => {
  return authStore.isAdmin 
    ? branchStore.selectedBranchId  // للأدمن: الفرع المختار من القائمة العلوية
    : branchIsolation.currentBranchId.value;  // للكاشير: فرعه الثابت
});

const terminals = computed(() => {
  try {
    const branchId = effectiveBranchId.value;
    if (!branchId) {
      return [];
    }
    
    const termsComputed = terminalStore.getTerminalsForBranch(branchId);
    const terms = termsComputed?.value || [];
    return Array.isArray(terms) ? terms : [];
  } catch (err) {
    return [];
  }
});

const selectedTerminalId = ref(null);
const paymentMethods = computed(() => paymentStore.paymentMethods);
const activeSessionId = ref(null);
const openSessionModal = ref(false);
const isOpeningSession = ref(false);
const openingCashAmount = ref(0);

const adminSelectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
});

const handleAdminBranchChange = async () => {
  const newBranchId = adminSelectedBranch.value;
  
  // تحديث الفرع في الـ store فوراً
  if (newBranchId !== branchStore.selectedBranchId) {
    branchStore.setSelectedBranch(newBranchId);
  }
  
  selectedTerminalId.value = null;
  activeSessionId.value = null;
  currentShift.value = null;
  
  try {
    const wid = newBranchId;
    if (wid) {
      const loadedTerminals = await terminalStore.fetchTerminals(wid);
      if (loadedTerminals && loadedTerminals.length) {
        selectedTerminalId.value = loadedTerminals[0].id;
      }
    }
  } catch (e) {
    // ignore
  }
  
  await ensureCashierSession();
  await fetchDashboardData();
};

// Shift state
const shiftState = ref('active');
const closingCashInput = ref(null);
const currentShift = ref(null);

// Dashboard data
const dashboardStats = ref({
  openingBalance: 0,
  totalSales: 0,
  totalReturns: 0,
  cashInDrawer: 0,
  expenses: 0,
  sessionData: {},
  electronicSettlements: { card: 0, wallet: 0, credit: 0, total: 0 },
  cashControl: { cashSales: 0, cashRefunds: 0, manualCashIn: 0, manualCashOut: 0 }
});
const cashDrawerTotal = ref(0);
const recentActivities = ref([]);
const topProducts = ref([]);

// Charts
const salesChart = ref(null);
const topProductsChart = ref(null);
const salesChartInstance = ref(null);
const topProductsChartInstance = ref(null);

// UI
const isLoadingData = ref(false);
const hasLoadedOnce = ref(false);

const openSessionModalRef = ref(null);
const endShiftModalRef = ref(null);

const varianceReason = ref('');
const selectedVarianceReason = ref('');
const varianceReasons = [
  { value: '', label: '-- اختر سبب الفرق --' },
  { value: 'counting_error', label: 'خطأ في العد' },
  { value: 'unrecorded_income', label: 'إيراد غير مسجل' },
  { value: 'manual_payment', label: 'دفع يدوي' },
  { value: 'other', label: 'سبب آخر' }
];

let dateTimeInterval = null;
let dashboardAbortCtrl = null;

// ─── Product Colors ───────────────────────────────────────────────────────────
const getProductColor = (i) => [
  { bg: 'bg-blue-100', text: 'text-blue-600' },
  { bg: 'bg-indigo-100', text: 'text-indigo-600' },
  { bg: 'bg-emerald-100', text: 'text-emerald-600' },
  { bg: 'bg-amber-100', text: 'text-amber-600' },
  { bg: 'bg-rose-100', text: 'text-rose-600' }
][i % 5];

// ─── Computeds ────────────────────────────────────────────────────────────────
const expenses = computed(() => dashboardStats.value.expenses || 0);
const actualCashDrawer = computed(() => cashDrawerTotal.value || 0);

const expectedInDrawer = computed(() => {
  if (dashboardStats.value.sessionData?.expected_cash !== undefined && dashboardStats.value.sessionData?.expected_cash !== null) {
    return dashboardStats.value.sessionData.expected_cash;
  }
  const opening = dashboardStats.value.openingBalance || 0;
  const cashSales = dashboardStats.value.cashControl?.cashSales || 0;
  const cashRefunds = dashboardStats.value.cashControl?.cashRefunds || 0;
  const cashIn = dashboardStats.value.cashControl?.manualCashIn || 0;
  const cashOut = dashboardStats.value.cashControl?.manualCashOut || 0;
  return opening + cashIn + cashSales - cashRefunds - cashOut;
});

const cashDifference = computed(() => (closingCashInput.value || 0) - expectedInDrawer.value);

const summaryCards = computed(() => [
  { id: 'total-sales', title: 'إجمالي المبيعات اليوم', value: dashboardStats.value.totalSales, icon: 'fas fa-chart-line', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600', valueColor: 'text-emerald-700' },
  { id: 'expenses', title: 'المصروفات والسحوبات', value: expenses.value, icon: 'fas fa-file-invoice-dollar', iconBg: 'bg-blue-50', iconColor: 'text-blue-600', valueColor: 'text-blue-700' },
  { id: 'total-returns', title: 'المرتجعات اليومية', value: dashboardStats.value.totalReturns, icon: 'fas fa-undo', iconBg: 'bg-rose-50', iconColor: 'text-rose-600', valueColor: 'text-rose-700' },
  { id: 'cash-drawer', title: 'السيولة المتوفرة بالدرج', value: actualCashDrawer.value, icon: 'fas fa-cash-register', iconBg: 'bg-amber-50', iconColor: 'text-amber-600', valueColor: 'text-amber-700', tooltip: 'نقدي فقط (فعلي)' }
]);

const displayedQuickActions = computed(() => {
  const actions = [
    { id: 'pos', title: 'نقطة البيع', description: 'إصدار فاتورة بيع جديدة', icon: 'fas fa-cash-register', route: '/sales/point', primary: true },
    { id: 'cash-ops', title: 'عمليات نقدية', description: 'سحب أو إيداع يدوي', icon: 'fas fa-hand-holding-usd', route: '/payments' },
    { id: 'refund', title: 'مرتجع سريع', description: 'إرجاع صنف من عميل', icon: 'fas fa-undo', route: '/sales/returns' },
    { id: 'history', title: 'آخر فاتورة', description: 'مراجعة آخر الفواتير', icon: 'fas fa-receipt', route: '/sales/history', params: { show_last: 'true' } },
    { id: 'reports', title: 'التقارير', description: 'عرض تقارير اليوم', icon: 'fas fa-chart-bar', route: '/reports/sales-analytics', adminOnly: true }
  ];
  return actions.filter(a => !a.adminOnly || authStore.isAdmin);
});

const sessionStatus = computed(() => activeSessionId.value
  ? { active: true, text: 'الجلسة مفتوحة', color: 'text-emerald-600', bgColor: 'bg-emerald-100', icon: 'fas fa-check-circle' }
  : { active: false, text: 'الجلسة مغلقة', color: 'text-amber-600', bgColor: 'bg-amber-100', icon: 'fas fa-exclamation-triangle' }
);

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatPrice = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');

const resolveQueryRange = (today) => {
  const useLocal = activeSessionId.value !== null;
  if (useLocal) {
    return { startDate: `${today} 00:00:00`, endDate: `${today} 23:59:59`, useLocal: true };
  }
  const { startUtcIso, endUtcIso } = localDateRangeToUTC(today, today);
  return {
    startDate: startUtcIso || `${today} 00:00:00`,
    endDate: endUtcIso || `${today} 23:59:59`,
    useLocal: false
  };
};

const formatRelativeTime = (date) => {
  try {
    const now = new Date();
    const d = typeof date === 'string' ? new Date(date) : (date instanceof Date ? date : new Date());
    const diffSec = Math.round((now - d) / 1000);
    const diffMin = Math.round(diffSec / 60);
    const diffHour = Math.round(diffMin / 60);
    const diffDay = Math.round(diffHour / 24);
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    if (Math.abs(diffSec) < 60) return rtf.format(-diffSec, 'second');
    if (Math.abs(diffMin) < 60) return rtf.format(-diffMin, 'minute');
    if (Math.abs(diffHour) < 48) return rtf.format(-diffHour, 'hour');
    return rtf.format(-diffDay, 'day');
  } catch {
    try { return new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }); } catch { return ''; }
  }
};

const updateDateTime = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  currentDate.value = now.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' });
};

const getDeviceIdentity = () => {
  let id = localStorage.getItem('pos_device_id');
  if (!id) { id = 'dev-' + Math.random().toString(36).slice(2, 8) + '-' + Date.now().toString(36).slice(-6); try { localStorage.setItem('pos_device_id', id); } catch {} }
  const custom = (localStorage.getItem('pos_device_name') || '').trim();
  const nameBase = custom || (typeof navigator !== 'undefined' ? (navigator.platform || navigator.userAgent) : 'POS Device');
  return { device_id: id, device_name: (custom || `POS ${nameBase}`).slice(0, 64) };
};

// ─── Charts ───────────────────────────────────────────────────────────────────
function initChart(chartRef, type, data, instanceRef) {
  if (!chartRef.value) return;
  
  // ✅ FIX: Defensive check - تأكد من أن data وخصائصه موجودة وليست undefined
  if (!data || typeof data !== 'object') {
    data = { labels: [], data: [] };
  }
  
  const safeLabels = Array.isArray(data.labels) ? data.labels : [];
  const safeData = Array.isArray(data.data) ? data.data.map(d => d || 0) : [];
  
  const ctx = chartRef.value.getContext('2d');
  if (instanceRef.value) { instanceRef.value.destroy(); instanceRef.value = null; }

  if (!safeLabels.length || !safeData.length) {
    ctx.clearRect(0, 0, chartRef.value.width, chartRef.value.height);
    ctx.save(); ctx.fillStyle = '#cbd5e1'; ctx.font = 'bold 12px Cairo, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('لا توجد بيانات', chartRef.value.width / 2, chartRef.value.height / 2);
    ctx.restore(); return;
  }

  instanceRef.value = new Chart(ctx, {
    type,
    data: {
      labels: safeLabels,
      datasets: [{
        data: safeData.map(d => {
          // ✅ FIX: التأكد من أن كل عنصر في data هو رقم صحيح وليس undefined
          const val = parseFloat(d) || 0;
          return isFinite(val) ? val : 0;
        }),
        backgroundColor: type === 'line' ? 'rgba(59,130,246,0.08)' : ['#3b82f6', '#6366f1', '#10b981', '#f59e0b', '#f43f5e'],
        borderColor: type === 'line' ? '#3b82f6' : 'transparent',
        borderWidth: type === 'line' ? 2 : 0,
        fill: type === 'line', tension: 0.4,
        pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#3b82f6'
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      indexAxis: type === 'bar' ? 'y' : undefined,
      plugins: {
        legend: { display: type !== 'line', position: 'bottom', labels: { font: { family: 'Cairo', size: 10 }, usePointStyle: true } },
        tooltip: type === 'bar' ? {
          callbacks: {
            label: (context) => {
              // ✅ FIX #1: التحقق الصريح من وجود context وخصائصه
              if (!context || typeof context !== 'object' || context.dataIndex === undefined) {
                return '';
              }
              
              // ✅ FIX #2: التحقق من topProducts قبل الوصول
              if (!topProducts.value || !Array.isArray(topProducts.value)) {
                return '';
              }
              
              // ✅ FIX #3: استخراج product مع null-checking
              const product = topProducts.value[context.dataIndex] || null;
              
              // ✅ FIX #4: استخدام optional chaining على جميع properties
              if (!product || typeof product !== 'object') {
                return '';
              }
              
              return [
                `الكمية: ${formatNumber(product?.quantity || 0)} ${product?.unit || 'قطعة'}`,
                `عدد الطلبات: ${formatNumber(product?.orderCount || 0)}`,
                `متوسط الكمية: ${formatNumber(product?.avgQuantityPerOrder || 0)} لكل طلب`,
                `إجمالي المبيعات (شامل الضريبة): ${formatPrice(product?.totalSales || 0)}`,
                `متوسط سعر ${product?.unit || 'قطعة'} (شامل الضريبة): ${formatPrice(product?.avgPrice || 0)}`
              ];
            }
          }
        } : {}
      },
      scales: type === 'line' ? {
        y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Cairo', size: 10 } } },
        x: { grid: { display: false }, ticks: { font: { family: 'Cairo', size: 10 } } }
      } : type === 'bar' ? {
        x: { grid: { display: false }, ticks: { font: { family: 'Cairo' } } },
        y: { grid: { display: false }, ticks: { font: { family: 'Cairo' } } }
      } : {}
    }
  });
}

const updateSalesChart = (d) => initChart(salesChart, 'line', d, salesChartInstance);
const updateTopProductsChart = (d) => initChart(topProductsChart, 'bar', d, topProductsChartInstance);

// ─── API ──────────────────────────────────────────────────────────────────────
const fetchDashboardData = async () => {
  if (dashboardAbortCtrl) dashboardAbortCtrl.abort();
  dashboardAbortCtrl = new AbortController();

  isLoadingData.value = true;

  try {
    const today = getLocalDateISO();
    const wid = branchStore.selectedBranchId;

    if (!wid) {
      console.log('No branch selected, skipping dashboard data');
      return;
    }

    let summary = null;
    try {
      const dashboardData = await analyticsStore.fetchDashboardSummary({
        branchId: wid,
        sessionId: activeSessionId.value,
        startDate: today + ' 00:00:00',
        endDate: today + ' 23:59:59',
        force: true,
        signal: dashboardAbortCtrl.signal
      });

      if (dashboardData) {
        summary = dashboardData.data;
      }
    } catch (e) {
      const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError';
      if (!isAborted) showToast('فشل تحميل ملخص لوحة المعلومات', 'error');
      throw e;
    }

    if (activeSessionId.value) {
      try {
        const summaryRes = await sessionStore.getSessionSummary(activeSessionId.value);
        if (summaryRes?.status === 'success' && summaryRes.data) {
          summary = summaryRes.data;
        }
      } catch (e) {
        if (e?.response?.status !== 404) {
          console.error('Session summary failed:', e);
        }
      }
    }

    const data = summary || {};
    const sTotals = summary?.totals || {};
    const sCalc = summary?.calculated || {};
    const sSalesSum = summary?.sales_summary || {};
    const sCashCtrl = summary?.cash_control || {};
    const sElectronicSettle = summary?.electronic_settlements || {};

    dashboardStats.value = {
      openingBalance: sCashCtrl.opening_float || sCalc.opening_balance || data.openingBalance || 0,
      totalSales: sSalesSum.gross_sales || sTotals.total_sales || data.net_grand_total || 0,
      totalReturns: data.returns_grand_total || 0,
      expenses: data.expenses || 0,
      sessionData: {
        total_sales: sSalesSum.gross_sales || sTotals.total_sales || 0,
        expected_cash: sCashCtrl.expected_physical_cash || sCalc.expected_cash || 0,
        actual_physical_cash: sCashCtrl.actual_physical_cash || sCalc.closing_balance || null,
        variance: sCashCtrl.variance || sCalc.variance_amount || null
      },
      electronicSettlements: {
        card: sElectronicSettle.card || 0,
        wallet: sElectronicSettle.wallet || 0,
        credit: sElectronicSettle.credit || 0,
        total: sElectronicSettle.total_electronic || sElectronicSettle.total || 0
      },
      cashControl: {
        cashSales: sCashCtrl.cash_sales || 0,
        cashRefunds: sCashCtrl.cash_refunds || 0,
        manualCashIn: sCashCtrl.manual_cash_in || 0,
        manualCashOut: sCashCtrl.manual_cash_out || 0
      }
    };

    if (Array.isArray(data.recentActivities) && data.recentActivities.length > 0) {
      recentActivities.value = data.recentActivities.map(activity => {
        const createdAt = activity.created_at || null;
        const parsedAmount = parseFloat(activity.amount || 0) || 0;
        const type = activity.type || 'sale';
        const reference = activity.reference_code || activity.reference_id || '';
        
        // Determine icon and color based on type
        let icon = 'fa-receipt';
        let colorClass = 'text-emerald-600 bg-emerald-100';
        
        if (type === 'return') {
          icon = 'fa-undo';
          colorClass = 'text-rose-600 bg-rose-100';
        } else if (type === 'withdrawal') {
          icon = 'fa-hand-holding-usd';
          colorClass = 'text-amber-600 bg-amber-100';
        } else if (type === 'deposit') {
          icon = 'fa-money-bill-wave';
          colorClass = 'text-blue-600 bg-blue-100';
        }
        
        return {
          id: activity.id,
          reference_id: activity.reference_id,
          type: type,
          amount: parsedAmount,
          raw_amount: parsedAmount,
          reference,
          description: activity.description || `عملية #${reference}`,
          icon: icon,
          colorClass: colorClass,
          time: createdAt ? formatRelativeTime(new Date(createdAt)) : '',
          fullTime: createdAt ? new Date(createdAt).toLocaleString('en-US', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''
        };
      });
    } else {
      recentActivities.value = [];
    }

    await fetchPaymentBreakdown();
    updateSalesChart(data.salesChart || data.sales_chart || { labels: [], data: [] });

    // Top products
    try {
      const { startDate, endDate } = resolveQueryRange(today);
      const salesResp = await analyticsStore.fetchSalesAnalytics({
        startDate,
        endDate,
        sessionId: activeSessionId.value,
        branchId: wid,
        force: true,
        signal: dashboardAbortCtrl.signal
      });
      const sData = salesResp?.data || salesResp || {};
      
      // ✅ FIX: التحقق الدقيق من أن top_products موجود وليس undefined
      if (Array.isArray(sData.top_products) && sData.top_products.length > 0) {
        topProducts.value = sData.top_products
          .filter(p => p && p.id) // ✅ تصفية null/undefined entries
          .map(p => ({
            id: p.id, 
            name: p.name || 'منتج غير معروف',
            code: p.code || '',
            quantity: parseFloat(p.total_quantity || 0) || 0, // ✅ تأكد من أن النتيجة ليست NaN
            totalSales: parseFloat(p.total_revenue || 0) || 0,
            avgPrice: (parseFloat(p.total_quantity || 0) || 0) > 0 
              ? (parseFloat(p.total_revenue || 0) || 0) / (parseFloat(p.total_quantity || 0) || 1)
              : 0,
            orderCount: parseInt(p.order_count || 0) || 0,
            avgQuantityPerOrder: (parseInt(p.order_count || 0) || 0) > 0
              ? (parseFloat(p.total_quantity || 0) || 0) / (parseInt(p.order_count || 0) || 1)
              : 0,
            unit: p.unit || 'قطعة'
          }))
          .filter(p => p.quantity > 0) // ✅ تصفية المنتجات بـ quantity = 0
          .sort((a, b) => b.quantity - a.quantity)
          .slice(0, 5);
        
        // ✅ تحديث الـ chart فقط إذا كانت هناك بيانات صحيحة
        if (topProducts.value && topProducts.value.length > 0) {
          updateTopProductsChart({
            labels: topProducts.value.map(p => {
              const name = p?.name || 'منتج غير معروف';
              const code = p?.code ? ` (${p.code})` : '';
              const qty = formatNumber(p?.quantity || 0);
              const unit = p?.unit || 'قطعة';
              return `${name}${code} - ${qty} ${unit}`;
            }),
            data: topProducts.value.map(p => {
              // ✅ FIX: تأكد من أن quantity هو رقم صحيح وليس undefined/NaN
              const qty = parseFloat(p?.quantity) || 0;
              return isFinite(qty) ? qty : 0;
            })
          });
        } else {
          updateTopProductsChart({ labels: [], data: [] }); // ✅ تنظيف الـ chart
        }
      } else {
        topProducts.value = [];
        updateTopProductsChart({ labels: [], data: [] });
      }
    } catch (e) { 
      console.error('Error fetching sales analytics:', e);
      topProducts.value = []; 
      updateTopProductsChart({ labels: [], data: [] }); // ✅ تنظيف الـ chart عند الخطأ
    }

  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError';
    if (!isAborted) showToast('فشل تحميل البيانات', 'error');
  } finally {
    isLoadingData.value = false;
    hasLoadedOnce.value = true;
  }
};

const fetchPaymentBreakdown = async () => {
  try {
    const today = getLocalDateISO();
    const wid = branchStore.selectedBranchId || authStore.user?.branch_id || null;

    const { startDate, endDate } = resolveQueryRange(today);

    // استخدام fetchDashboardSummary بدلاً من fetchPaymentBreakdownData
    const breakdownRes = await analyticsStore.fetchDashboardSummary({
      branchId: wid,
      sessionId: activeSessionId.value,
      startDate,
      endDate,
      force: true
    });

    const data = breakdownRes?.data || {};
    const pb = data.paymentBreakdown || data.payment_breakdown || {};

    // ✅ FIX #4: استخدام اسم الحقل الصحيح cash_drawer بدل cash_total/cash
    cashDrawerTotal.value = pb.cash_drawer || 0;
  } catch (err) {
    console.error('Error in fetchPaymentBreakdown:', err);
    cashDrawerTotal.value = 0;
  }
};

// ─── Session Actions ──────────────────────────────────────────────────────────
const getUser = () => {
  try {
    return authStore.user || JSON.parse(localStorage.getItem('user') || 'null');
  } catch {
    return null;
  }
};

const triggerOpenSession = async () => {
  try {
    const user = getUser();
    const userId = user?.id || null;
    const branchId = user?.branch_id ?? null;

    if (!userId) return;

    if (!branchId && !authStore.isAdmin) {
      showToast('لا يوجد فرع مرتبط بالمستخدم. يرجى تعيين فرع للكاشير.', 'error');
      return;
    }

    if (branchId) {
      const { device_id } = getDeviceIdentity();
      const result = await sessionStore.getCurrentSession(branchId, userId, device_id);
      if (result?.status === 'success' && result.data?.id) {
        activeSessionId.value = result.data.id;
        showToast('هناك جلسة مفتوحة بالفعل لهذا الفرع.', 'info');
        return;
      }
    }

    // ✅ تحديد الفرع المراد فتح جلسة له
    let targetBranchId = null;
    if (authStore.isAdmin) {
      // ✅ للإدمن، استخدم adminSelectedBranch (من header)
      targetBranchId = adminSelectedBranch.value;
      if (!targetBranchId) {
        showToast('يرجى اختيار الفرع من القائمة بالأعلى أولاً', 'error');
        return;
      }
    } else {
      // ✅ للكاشيرين، استخدم currentBranchId (آمن)
      targetBranchId = branchIsolation.currentBranchId.value;
      if (!targetBranchId) {
        showToast('لم يتم تعيين فرع لحسابك', 'error');
        return;
      }
    }

    // ✅ تحميل الأجهزة قبل فتح modal
    try {
      console.log('[CashierDashboard] About to fetch terminals for branch:', targetBranchId);
      const loadedTerminals = await terminalStore.fetchTerminals(targetBranchId, { force: true });
      console.log('[CashierDashboard] Fetch completed. Terminals count:', loadedTerminals?.length || 0);
      
      // تأكد من وجود أجهزة
      if (!loadedTerminals || loadedTerminals.length === 0) {
        showToast('لا توجد أجهزة نقاط بيع مسجلة لهذا الفرع. يرجى إضافة جهاز أولاً.', 'error');
        return;
      }
      
      // اختر أول جهاز إذا لم يكن هناك جهاز محدد
      if (!selectedTerminalId.value) {
        selectedTerminalId.value = loadedTerminals[0].id;
      }
    } catch (e) {
      console.error('[CashierDashboard] Error fetching terminals:', e);
      showToast('فشل تحميل أجهزة نقاط البيع', 'error');
      return;
    }

    openingCashAmount.value = 0;
    openSessionModal.value = true;
  } catch (error) {
    showToast(error?.response?.data?.message || 'تعذر بدء عملية فتح الجلسة', 'error');
  }
};

const confirmOpenSession = async () => {
  try {
    isOpeningSession.value = true;

    // ✅ التحقق من terminal_id قبل المتابعة
    if (!selectedTerminalId.value) {
      showToast('يرجى اختيار جهاز نقطة البيع (Terminal) قبل فتح الجلسة', 'error');
      isOpeningSession.value = false;
      return;
    }

    let wid = null;

    if (authStore.isAdmin) {
      // ✅ للإدمن، استخدم adminSelectedBranch (من header)
      wid = adminSelectedBranch.value;
      if (!wid) {
        showToast('الرجاء تحديد الفرع من القائمة بالأعلى', 'error');
        isOpeningSession.value = false;
        return;
      }
    } else {
      // ✅ للكاشيرين، استخدم currentBranchId (آمن)
      wid = branchIsolation.currentBranchId.value;
      if (!wid) {
        showToast('لم يتم تعيين فرع لحسابك', 'error');
        isOpeningSession.value = false;
        return;
      }
    }

    const { device_id, device_name } = getDeviceIdentity();
    
    // ✅ التحقق النهائي من terminal_id
    if (!selectedTerminalId.value) {
      showToast('يرجى اختيار جهاز نقطة البيع قبل المتابعة', 'error');
      isOpeningSession.value = false;
      return;
    }

    const result = await sessionStore.openSession({
      branch_id: wid,
      opening_cash_amount: openingCashAmount.value,
      session_type: 'manual',
      device_id,
      device_name,
      terminal_id: Number(selectedTerminalId.value)  // ✅ تحويل إلى رقم
    });
    if (result?.status === 'success' && result.data?.id) {
      activeSessionId.value = result.data.id;
      openSessionModal.value = false;
      try {
        if (wid && selectedTerminalId.value) {
          const shiftResult = await shiftStore.getCurrentShift(wid, selectedTerminalId.value, true);
          currentShift.value = shiftResult?.status === 'success' && shiftResult.data?.id ? shiftResult.data : currentShift.value;
        }
      } catch { /* non-critical */ }
      fetchDashboardData();
      showToast('تم فتح الجلسة', 'success');
    } else {
      showToast(result?.message || 'تعذر فتح الجلسة', 'error');
    }
  } catch (e) {
    showToast(e?.response?.data?.message || 'تعذر فتح الجلسة', 'error');
  }
  finally {
    isOpeningSession.value = false;
  }
};

// ─── Shift Actions (اُزيلت الدوال اليدوية — الوردية أوتوماتيك 100%) ──

const attemptEndShift = async () => {
  await fetchDashboardData();
  closingCashInput.value = null;
  varianceReason.value = '';
  selectedVarianceReason.value = '';
  shiftState.value = 'ending';
};

const handleVarianceReasonChange = () => {
  if (selectedVarianceReason.value !== 'other') varianceReason.value = selectedVarianceReason.value;
};

const confirmEndShift = async () => {
  if (closingCashInput.value === null || closingCashInput.value < 0) { showToast('الرجاء إدخال المبلغ الفعلي', 'error'); return; }
  const hasVariance = Math.abs(cashDifference.value) > 0.01;
  if (hasVariance && !varianceReason.value && !selectedVarianceReason.value) { showToast('الرجاء تحديد سبب الفرق', 'warning'); return; }
  try {
    const reasonToSend = selectedVarianceReason.value === 'other' ? varianceReason.value : selectedVarianceReason.value;
    const result = await sessionStore.closeSession(activeSessionId.value, closingCashInput.value, reasonToSend);
    if (result?.status === 'success') {
      activeSessionId.value = null;
      const variance = result.data?.closing?.variance ?? null;
      showToast(typeof variance === 'number' && variance !== 0 ? `تم الإغلاق. فرق الخزينة: ${variance.toFixed(2)}` : 'تم إغلاق الجلسة بنجاح.', variance !== 0 ? 'warning' : 'success');
      try {
        const wid = authStore.user?.branch_id || branchStore.selectedBranchId;
        if (wid && selectedTerminalId.value) {
          const shiftResult = await shiftStore.getCurrentShift(wid, selectedTerminalId.value, true);
          currentShift.value = shiftResult?.status === 'success' && shiftResult.data?.id ? shiftResult.data : null;
        }
      } catch { /* non-critical */ }
    } else {
      showToast(result?.message || 'تعذر الإغلاق', 'error');
    }
  } catch (e) { showToast(e?.response?.data?.message || 'تعذر الإغلاق', 'error'); }
  shiftState.value = 'active';
  closingCashInput.value = null;
  varianceReason.value = '';
  selectedVarianceReason.value = '';
  await fetchDashboardData();
};

// ─── Device ───────────────────────────────────────────────────────────────────

// ─── Event Handlers ───────────────────────────────────────────────────────────
const handleQuickAction = (action) => {
  if (action.adminOnly && !authStore.isAdmin) { showToast('هذه الصفحة للمشرفين فقط', 'error'); return; }
  if (action.route) router.push(action.params ? { path: action.route, query: action.params } : action.route);
};

const handleActivityClick = (activity) => {
  switch (activity.type) {
    case 'sale':       router.push({ path: '/sales/history', query: { id: activity.reference_id } }); break;
    case 'return':     router.push({ path: '/sales/returns', query: { id: activity.reference_id } }); break;
    case 'withdrawal':
    case 'deposit':    router.push({ path: '/payments', query: { id: activity.reference_id } }); break;
    default:           showToast('لا يمكن عرض تفاصيل هذه العملية', 'info');
  }
};

const handleRefresh = () => fetchDashboardData();

const loadPaymentMethods = async () => {
  try {
    await paymentStore.fetchPaymentMethods();
  } catch (e) {
    console.error('Failed to load payment methods for dashboard', e);
  }
};

const ensureCashierSession = async () => {
  try {
    const user = getUser();
    const userId = user?.id || null;

    let branchId;
    try {
      branchId = effectiveBranchId.value;
    } catch (e) {
      activeSessionId.value = null;
      return;
    }

    if (!userId) {
      activeSessionId.value = null;
      return;
    }
    const { device_id } = getDeviceIdentity();
    const result = await sessionStore.getCurrentSession(branchId, userId, device_id);
    activeSessionId.value = result?.status === 'success' && result.data?.id ? result.data.id : null;
  } catch (e) {
    if (e?.response?.status !== 404) {
      console.error('Session check failed:', e);
    }
    activeSessionId.value = null;
  }
};

watch(openSessionModal, (val) => {
  if (val) nextTick(() => openSessionModalRef.value?.focus());
});
watch(() => shiftState.value === 'ending', (val) => {
  if (val) nextTick(() => endShiftModalRef.value?.focus());
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  updateDateTime();
  dateTimeInterval = setInterval(updateDateTime, 1000);

  const stored = localStorage.getItem('user');
  if (stored) { try { const u = JSON.parse(stored); cashierName.value = u.name || u.username || 'مستخدم'; } catch {} }

  await fetchSettings();

  if (authStore.isAdmin) {
    try {
      await branchStore.fetchBranches();
    } catch {}
  }

  try {
    const wid = effectiveBranchId.value;
    if (wid) {
      const loadedTerminals = await terminalStore.fetchTerminals(wid);
      if (!selectedTerminalId.value && loadedTerminals && loadedTerminals.length) {
        selectedTerminalId.value = loadedTerminals[0].id;
      }
    }
  } catch (e) {
    // ignore
  }

  try {
    const wid = effectiveBranchId.value;
    if (wid && selectedTerminalId.value) {
      const result = await shiftStore.getCurrentShift(wid, selectedTerminalId.value);
      currentShift.value = result?.status === 'success' && result.data?.id ? result.data : null;
    }
  } catch (e) {
    currentShift.value = null;
  }

  await loadPaymentMethods();
  await ensureCashierSession();
  await fetchDashboardData();
});

onUnmounted(() => {
  if (dateTimeInterval) clearInterval(dateTimeInterval);
});

onBeforeUnmount(() => {
  if (salesChartInstance.value) salesChartInstance.value.destroy();
  if (topProductsChartInstance.value) topProductsChartInstance.value.destroy();
  if (dashboardAbortCtrl) dashboardAbortCtrl.abort();
});
</script>

<style scoped>
@keyframes loading { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.98) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.animate-modalIn { animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>