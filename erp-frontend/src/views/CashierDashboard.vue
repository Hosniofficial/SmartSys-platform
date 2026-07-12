<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn">
    
    <!-- Top Header Navigation -->
    <header class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-4 mb-8 sticky top-0 z-40 backdrop-blur-md bg-white/90">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- User & Status Info -->
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white shrink-0">
            <i class="fas fa-user-tie text-xl"></i>
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 leading-none">مرحباً، {{ cashierName }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
              <!-- Session Status Badge -->
              <div :class="[sessionStatus.bgColor, sessionStatus.color, 'px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter flex items-center gap-1.5 shadow-sm']">
                <i :class="sessionStatus.icon"></i>
                {{ sessionStatus.text }}
              </div>

              <!-- Shift Status Badge -->
              <div v-if="currentShift" class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase tracking-tighter border border-indigo-100">
                <i class="fas fa-clock ml-1"></i> وردية نشطة #{{ currentShift.id }}
              </div>
              <div v-else class="px-3 py-1 rounded-xl bg-slate-100 text-slate-400 text-[10px] font-black uppercase tracking-tighter">
                <i class="fas fa-minus-circle ml-1"></i> لا يوجد وردية
              </div>

              <!-- Shift Quick Actions -->
              <button v-if="!currentShift" @click="openShift" :disabled="isOpeningShift" class="px-3 py-1 rounded-xl bg-indigo-600 text-white text-[10px] font-black hover:bg-indigo-700 transition-all active:scale-95 disabled:opacity-50">
                <i class="fas fa-calendar-plus ml-1"></i> فتح وردية
              </button>
              <button v-else @click="closeShift" :disabled="isClosingShift" class="px-3 py-1 rounded-xl bg-indigo-600 text-white text-[10px] font-black hover:bg-indigo-700 transition-all active:scale-95 disabled:opacity-50">
                <i class="fas fa-calendar-check ml-1"></i> إغلاق الوردية
              </button>
            </div>
          </div>
        </div>

        <!-- Clock & Global Actions -->
        <div class="flex items-center gap-3">
          <div class="hidden lg:flex flex-col items-end px-4 border-r border-slate-100">
            <span class="text-lg font-black text-slate-800 leading-none font-mono tracking-tighter">{{ currentTime }}</span>
            <span class="text-[10px] font-bold text-slate-400 uppercase mt-1">{{ currentDate }}</span>
          </div>
          
          <div class="flex items-center gap-2">
            <button @click="handleRefresh" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-50 transition-all text-slate-400 hover:text-blue-600" title="تحديث البيانات">
              <i class="fas fa-sync-alt"></i>
            </button>

            <button @click="openRenameDevice" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-50 transition-all text-slate-400 hover:text-blue-600" title="تسمية الجهاز">
              <i class="fas fa-edit"></i>
            </button>
            
            <button v-if="!activeSessionId" @click="triggerOpenSession" class="px-6 h-11 bg-emerald-600 text-white rounded-xl text-xs font-black shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all active:scale-95 flex items-center gap-2">
              <i class="fas fa-play"></i> فتح جلسة
            </button>
            
            <button v-if="activeSessionId" @click="attemptEndShift" class="px-6 h-11 bg-rose-600 text-white rounded-xl text-xs font-black shadow-lg shadow-rose-100 hover:bg-rose-700 transition-all active:scale-95 flex items-center gap-2">
              <i class="fas fa-door-closed"></i> إغلاق الجلسة
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- KPI Summary Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div v-for="card in summaryCards" :key="card.id" class="kpi-card group">
        <div class="flex items-center justify-between mb-4">
          <div :class="[card.iconBg, card.iconColor, 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition-transform duration-500']">
            <i :class="card.icon"></i>
          </div>
          <span v-if="card.tooltip" class="text-[9px] font-black text-slate-300 uppercase tracking-widest text-left max-w-[80px]">{{ card.tooltip }}</span>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">{{ card.title }}</p>
        <p :class="[card.valueColor, 'text-2xl font-black tracking-tight']">{{ formatPrice(card.value) }}</p>
      </div>
    </section>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
      
      <!-- Left: Quick Actions & Recent Activities -->
      <div class="lg:col-span-4 space-y-8">
        <!-- Quick Actions -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-6">
          <h2 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-6 flex items-center gap-2">
            <i class="fas fa-bolt text-amber-500"></i> الإجراءات السريعة
          </h2>
          <div class="space-y-3">
            <button v-for="action in displayedQuickActions" :key="action.id" @click="handleQuickAction(action)"
              :class="[action.primary ? 'bg-blue-600 text-white shadow-xl shadow-blue-100' : 'bg-slate-50 text-slate-600 hover:bg-white hover:border-blue-200 border border-transparent']"
              class="w-full p-4 rounded-2xl flex items-center justify-between transition-all group active:scale-[0.98]">
              <div class="text-right">
                <p class="text-xs font-black leading-none">{{ action.title }}</p>
                <p class="text-[10px] mt-1.5 font-bold opacity-60">{{ action.description }}</p>
              </div>
              <i :class="[action.icon, 'text-lg transition-transform group-hover:scale-125']"></i>
            </button>
          </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col h-[480px]">
          <div class="p-6 border-b border-slate-50 flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
              <i class="fas fa-history text-indigo-500"></i> النشاطات الأخيرة
            </h2>
            <span class="text-[10px] font-black px-2 py-1 bg-slate-100 text-slate-500 rounded-lg">{{ recentActivities.length }}</span>
          </div>
          <div class="flex-grow overflow-y-auto custom-scroll p-4 space-y-3">
            <div v-if="recentActivities.length === 0" class="py-20 text-center opacity-20">
              <i class="fas fa-stream text-4xl mb-2"></i>
              <p class="text-xs font-black uppercase">لا توجد عمليات حالياً</p>
            </div>
            <div v-for="activity in recentActivities" :key="activity.id" @click="handleActivityClick(activity)"
              class="p-4 rounded-2xl bg-slate-50 border border-transparent hover:border-indigo-100 hover:bg-white hover:shadow-md transition-all cursor-pointer group">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div :class="[activity.colorClass, 'w-10 h-10 rounded-xl flex items-center justify-center text-sm shadow-sm transition-all group-hover:scale-110']">
                    <i :class="['fas', activity.icon]"></i>
                  </div>
                  <div>
                    <p class="text-xs font-black text-slate-800 leading-none">{{ activity.description }}</p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase mt-1" :title="activity.fullTime">{{ activity.time }}</p>
                  </div>
                </div>
                <div :class="[activity.amount > 0 ? 'text-emerald-600' : 'text-rose-600', 'text-sm font-black font-mono tracking-tighter']">
                  {{ formatPrice(activity.amount) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Charts & Top Products -->
      <div class="lg:col-span-8 space-y-8">
        <!-- Sales Chart -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3">
              <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
              تحليل مبيعات اليوم
            </h2>
          </div>
          <div class="w-full h-[320px] relative">
            <canvas ref="salesChart"></canvas>
          </div>
        </div>

        <!-- Top Products -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 flex flex-col">
            <h2 class="text-lg font-black text-slate-900 mb-6 flex items-center gap-3">
              <i class="fas fa-trophy text-amber-400"></i> الأعلى مبيعاً
            </h2>
            <div class="flex-grow flex items-center justify-center min-h-[200px]">
              <canvas ref="topProductsChart"></canvas>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
            <h2 class="text-lg font-black text-slate-900 mb-6">قائمة المنتجات الأكثر طلباً</h2>
            <div v-if="topProducts.length > 0" class="space-y-4">
              <div v-for="(product, index) in topProducts" :key="product.id" class="flex items-center justify-between group">
                <div class="flex items-center gap-3">
                  <div :class="[getProductColor(index).bg, getProductColor(index).text]" class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs shadow-sm">
                    {{ index + 1 }}
                  </div>
                  <div>
                    <p class="text-xs font-black text-slate-700 leading-none truncate max-w-[120px]">{{ product.name }}</p>
                    <p class="text-[10px] text-slate-400 font-bold mt-1">
                      {{ formatNumber(product.quantity) }} {{ product.unit }}
                      <span v-if="product.orderCount" class="text-slate-300"> · {{ formatNumber(product.orderCount) }} طلب</span>
                    </p>
                  </div>
                </div>
                <div class="text-left">
                  <p class="text-xs font-black text-slate-800 leading-none">{{ formatPrice(product.totalSales) }}</p>
                  <p v-if="product.avgPrice" class="text-[9px] text-slate-400 font-bold mt-1">{{ formatPrice(product.avgPrice) }} / {{ product.unit }}</p>
                </div>
              </div>
            </div>
            <div v-else class="py-12 text-center opacity-20">
              <i class="fas fa-box-open text-4xl mb-2"></i>
              <p class="text-xs font-black uppercase">لا توجد مبيعات أصناف</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payments Breakdown Section -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
      <h2 class="text-xl font-black text-slate-900 tracking-tight mb-8 flex items-center gap-3">
        <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
        توزيع المدفوعات والسيولة
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
        <div v-for="(data, label) in paymentBreakdownFull" :key="label"
          class="p-6 rounded-[2rem] bg-slate-50 border border-transparent hover:border-indigo-100 transition-all text-center group">
          <div :class="[data.color, 'text-2xl mb-3 group-hover:scale-110 transition-transform']">
            <i :class="data.icon"></i>
          </div>
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">{{ label }}</p>
          <p class="text-base font-black text-slate-800">{{ formatPrice(data.amount) }}</p>
        </div>
        
        <!-- Cash Drawer Actual -->
        <div class="p-6 rounded-[2rem] bg-slate-900 text-white text-center md:col-span-2 flex flex-col justify-center shadow-xl shadow-slate-200">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">الرصيد الفعلي بالخزينة</p>
          <p class="text-3xl font-black text-blue-400 tracking-tighter">{{ formatPrice(cashDrawerTotal) }}</p>
          <div class="mt-4 pt-4 border-t border-white/5 flex justify-around text-[10px] font-bold text-white/50 uppercase tracking-widest">
            <span>نقدي: {{ formatPrice(paymentBreakdown.cash_total) }}</span>
            <span>محفظة: {{ formatPrice(paymentBreakdown.bank_wallet_total) }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== MODALS ===== -->

    <!-- Open Session Modal -->
    <div v-if="openSessionModal" class="modal-overlay">
      <div class="modal-content-modern animate-modalIn">
        <div class="p-8 text-center border-b border-slate-50">
          <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-key text-2xl"></i>
          </div>
          <h3 class="text-xl font-black text-slate-800 leading-none">فتح جلسة كاشير جديدة</h3>
          <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">
            <span v-if="authStore.isAdmin">حدد الفرع للعمل</span>
            <span v-else>تأكيد بدء الجلسة</span>
          </p>
        </div>
        <div class="p-8 space-y-6">
          <!-- Branch Selector for Admin -->
          <div v-if="authStore.isAdmin && branches.length > 0">
            <label class="modal-label">الفرع / الفرع</label>
            <select v-model="selectedBranchId" class="form-select-modern font-bold">
              <option :value="null" disabled>-- اختر الفرع المطلوب --</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <!-- Auto-assigned Branch for Regular Users -->
          <div v-else-if="authStore.user?.branch_id && !authStore.isAdmin" class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <p class="text-sm font-bold text-blue-700">
              <i class="fas fa-check-circle ml-1"></i>
              تم تحديد فرعك: <strong>{{ branches.find(b => b.id === authStore.user?.branch_id)?.name || 'الفرع الرئيسي' }}</strong>
            </p>
          </div>
          <div v-if="terminals.length">
            <label class="modal-label">جهاز نقطة البيع (الترمينال)</label>
            <select v-model="selectedTerminalId" class="form-select-modern font-bold">
              <option v-for="t in terminals || []" :key="t?.id" :value="t?.id">{{ t?.code }} - {{ t?.name }}</option>
            </select>
          </div>
          <div>
            <label class="modal-label text-center block">المبلغ النقدي الافتتاحي (العهدة)</label>
            <input v-model.number="openingCashAmount" type="number" min="0" step="0.01"
              class="w-full h-16 rounded-[1.5rem] border-slate-100 bg-slate-50 text-3xl font-black text-center text-blue-600 focus:bg-white outline-none focus:ring-4 focus:ring-blue-50 transition-all"
              placeholder="0.00" />
          </div>
          <div class="bg-blue-50 p-3 rounded-xl text-xs text-blue-700 font-bold">
            <i class="fas fa-info-circle ml-1"></i>
            سيتم فتح الجلسة للمخزن المرتبط بالمستخدم الحالي.
          </div>
        </div>
        <div class="px-8 pb-8 flex gap-4">
          <button @click="openSessionModal = false" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-colors">إلغاء</button>
          <button @click="confirmOpenSession" :disabled="isOpeningSession" class="flex-[2] py-4 rounded-2xl bg-blue-600 text-white font-black shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 disabled:opacity-50">
            <span v-if="!isOpeningSession">تأكيد وبدء العمل</span>
            <BaseSpinner v-else size="sm" color="#fff" />
          </button>
        </div>
      </div>
    </div>

    <!-- End Shift Modal -->
    <div v-if="shiftState === 'ending'" class="modal-overlay">
      <div class="modal-content-modern max-w-lg animate-modalIn">
        <div class="p-8 border-b border-slate-50 bg-slate-50/50">
          <h3 class="text-xl font-black text-slate-900 tracking-tight leading-none">مراجعة وإغلاق الوردية</h3>
          <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">تقرير تصفية النقدية والعهدة</p>
        </div>
        
        <div class="p-8 space-y-6">
          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
              <p class="text-[9px] font-black text-emerald-600 uppercase mb-1">المبيعات</p>
              <p class="text-lg font-black text-emerald-700 leading-none">{{ formatPrice(dashboardStats.sessionData?.total_sales || dashboardStats.totalSales) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100">
              <p class="text-[9px] font-black text-rose-600 uppercase mb-1">المرتجعات</p>
              <p class="text-lg font-black text-rose-700 leading-none">{{ formatPrice(dashboardStats.totalReturns) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-100">
              <p class="text-[9px] font-black text-indigo-600 uppercase mb-1">الرصيد الافتتاحي</p>
              <p class="text-lg font-black text-indigo-700 leading-none">{{ formatPrice(dashboardStats.openingBalance) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 text-center">
              <p class="text-[9px] font-black text-slate-400 uppercase mb-1 tracking-[0.2em]">المتوقع بالخزينة</p>
              <p class="text-xl font-black text-blue-400 leading-none">{{ formatPrice(dashboardStats.sessionData?.expected_cash || expectedInDrawer) }}</p>
            </div>
          </div>

          <div class="space-y-4">
            <label class="text-xs font-black text-slate-500 uppercase tracking-widest block text-center">المبلغ الفعلي الموجود بالخزينة</label>
            <input v-model.number="closingCashInput" type="number" min="0" step="0.01"
              class="w-full h-16 rounded-[1.5rem] border-slate-100 bg-slate-50 text-3xl font-black text-center text-slate-800 focus:bg-white focus:ring-4 focus:ring-blue-50 transition-all outline-none"
              placeholder="0.00" />
            
            <transition name="fade">
              <div v-if="closingCashInput !== null"
                :class="[cashDifference === 0 ? 'bg-emerald-50 border-emerald-100 text-emerald-700' : 'bg-rose-50 border-rose-100 text-rose-700']"
                class="p-4 rounded-2xl border-2 text-center transition-all">
                <p class="text-xs font-black uppercase mb-1">{{ cashDifference === 0 ? 'المطابقة سليمة' : 'يوجد فرق نقدي' }}</p>
                <p class="text-lg font-black">{{ formatPrice(cashDifference) }}</p>
              </div>
            </transition>
          </div>

          <!-- Variance Reason -->
          <div v-if="closingCashInput !== null && Math.abs(cashDifference) > 0.01" class="space-y-3">
            <label class="modal-label">سبب العجز / الزيادة</label>
            <select v-model="selectedVarianceReason" @change="handleVarianceReasonChange" class="form-select-modern font-bold">
              <option v-for="reason in varianceReasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
            </select>
            <textarea v-if="selectedVarianceReason === 'other'" v-model="varianceReason" rows="2"
              class="w-full rounded-2xl border-slate-200 p-4 text-sm font-bold bg-slate-50 outline-none focus:bg-white focus:ring-4 focus:ring-blue-50"
              placeholder="يرجى كتابة التوضيح هنا..."></textarea>
          </div>
        </div>
        
        <div class="px-8 pb-8 flex gap-4">
          <button @click="shiftState = 'active'" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-colors">تراجع</button>
          <button @click="confirmEndShift" class="flex-[2] py-4 rounded-2xl bg-rose-600 text-white font-black shadow-xl shadow-rose-100 hover:bg-rose-700 transition-all active:scale-95">
            إنهاء الوردية وتصفية الخزينة
          </button>
        </div>
      </div>
    </div>

    <!-- Rename Device Modal -->
    <div v-if="showRenameDevice" class="modal-overlay">
      <div class="modal-content-modern animate-modalIn">
        <div class="p-8 border-b border-slate-50">
          <h3 class="text-xl font-black text-slate-800">تسمية جهاز نقطة البيع</h3>
          <p class="text-slate-400 text-xs mt-2 font-bold">يظهر الاسم في الجلسات والتقارير</p>
        </div>
        <div class="p-8 space-y-4">
          <label class="modal-label">اسم الجهاز</label>
          <input v-model.trim="deviceNameInput" type="text" maxlength="64" class="form-input-modern"
            placeholder="مثال: كاشير 1 - الفرع الرئيسي" />
          <p class="text-[10px] text-slate-400">اتركه فارغًا للعودة للاسم الافتراضي</p>
        </div>
        <div class="px-8 pb-8 flex gap-4">
          <button @click="showRenameDevice = false" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-colors">إلغاء</button>
          <button @click="saveDeviceName" class="flex-1 py-4 rounded-2xl bg-blue-600 text-white font-black hover:bg-blue-700 transition-all active:scale-95">حفظ</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useTerminalStore } from '@/stores/terminal/terminalStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useSessionStore } from '@/stores/session/sessionStore';
import { useShiftStore } from '@/stores/shift/shiftStore';
import { useAnalyticsStore } from '@/stores/analytics';
import { useSalesStore } from '@/stores/sales/salesStore';
import Chart from 'chart.js/auto';
import { localDateRangeToUTC, getLocalDateISO } from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// ─── Composables ──────────────────────────────────────────────────────────────
const router = useRouter();
const { showToast } = useToast();
const { showLoader } = useLoader();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const terminalStore = useTerminalStore();
const paymentStore = usePaymentStore();
const sessionStore = useSessionStore();
const shiftStore = useShiftStore();
const analyticsStore = useAnalyticsStore();
const salesStore = useSalesStore();

// ─── State ────────────────────────────────────────────────────────────────────
const cashierName = ref('مستخدم');
const currentTime = ref('');
const currentDate = ref('');
const branches = computed(() => branchStore.branches);
const selectedBranchId = ref(null);
const effectiveBranchId = computed(() => selectedBranchId.value || authStore.user?.branch_id || branchStore.selectedBranchId);
const terminals = computed(() => terminalStore.getTerminalsForBranch(effectiveBranchId.value).value || []);
const selectedTerminalId = ref(null);
const paymentMethods = computed(() => paymentStore.paymentMethods);
const activeSessionId = ref(null);
const openSessionModal = ref(false);
const isOpeningSession = ref(false);
const openingCashAmount = ref(0);

// Shift state
const shiftState = ref('active');
const closingCashInput = ref(null);
const currentShift = ref(null);
const isOpeningShift = ref(false);
const isClosingShift = ref(false);
const shiftOpeningAmount = ref(0);
const shiftClosingAmount = ref(0);
const shiftNotes = ref('');

// Dashboard data
const dashboardStats = ref({ openingBalance: 0, totalSales: 0, totalReturns: 0, cashInDrawer: 0, expenses: 0, sessionData: {} });
const paymentBreakdown = ref({});
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
const showRenameDevice = ref(false);
const deviceNameInput = ref('');

// Variance reasons (كاملة من القديم)
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
// ✅ AbortController لمنع race condition
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
  const opening = dashboardStats.value.openingBalance || 0;
  const cash = paymentBreakdown.value?.cash_total || 0;
  const returns = dashboardStats.value.totalReturns || 0;
  return opening + cash - returns;
});
const cashDifference = computed(() => (closingCashInput.value || 0) - (dashboardStats.value.sessionData?.expected_cash || expectedInDrawer.value));

const summaryCards = computed(() => [
  { id: 'total-sales', title: 'إجمالي المبيعات اليوم', value: dashboardStats.value.totalSales, icon: 'fas fa-chart-line', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600', valueColor: 'text-emerald-700' },
  { id: 'expenses', title: 'المصروفات والسحوبات', value: expenses.value, icon: 'fas fa-file-invoice-dollar', iconBg: 'bg-blue-50', iconColor: 'text-blue-600', valueColor: 'text-blue-700' },
  { id: 'total-returns', title: 'المرتجعات اليومية', value: dashboardStats.value.totalReturns, icon: 'fas fa-undo', iconBg: 'bg-rose-50', iconColor: 'text-rose-600', valueColor: 'text-rose-700' },
  { id: 'cash-drawer', title: 'السيولة المتوفرة بالدرج', value: actualCashDrawer.value, icon: 'fas fa-cash-register', iconBg: 'bg-amber-50', iconColor: 'text-amber-600', valueColor: 'text-amber-700', tooltip: 'شامل النقدي والمحفظة' }
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

const paymentBreakdownFull = computed(() => ({
  'نقدي':    { amount: paymentBreakdown.value.cash_total || 0,         icon: 'fas fa-money-bill-wave', color: 'text-emerald-600' },
  'بطاقة':   { amount: paymentBreakdown.value.card_total || 0,         icon: 'fas fa-credit-card',     color: 'text-blue-600' },
  'آجل':     { amount: paymentBreakdown.value.credit_total || 0,       icon: 'fas fa-file-invoice',    color: 'text-indigo-600' },
  'محفظة':   { amount: paymentBreakdown.value.bank_wallet_total || 0,  icon: 'fas fa-wallet',          color: 'text-amber-600' },
  'مرتجعات': { amount: paymentBreakdown.value.returns_total || 0,      icon: 'fas fa-undo',            color: 'text-rose-600' }
}));

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatPrice = (v) => formatCurrencyLocale(v, 2);
const formatNumber = (v) => (Number(v) || 0).toLocaleString('en-US');


// ✅ وقت نسبي احترافي من القديم
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
  const ctx = chartRef.value.getContext('2d');
  if (instanceRef.value) { instanceRef.value.destroy(); instanceRef.value = null; }

  if (!data?.labels?.length || !data?.data?.length) {
    ctx.clearRect(0, 0, chartRef.value.width, chartRef.value.height);
    ctx.save(); ctx.fillStyle = '#cbd5e1'; ctx.font = 'bold 12px Cairo, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('لا توجد بيانات', chartRef.value.width / 2, chartRef.value.height / 2);
    ctx.restore(); return;
  }

  instanceRef.value = new Chart(ctx, {
    type,
    data: {
      labels: data.labels,
      datasets: [{
        data: data.data,
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
              const product = topProducts.value[context.dataIndex];
              if (!product) return '';
              return [
                `الكمية: ${formatNumber(product.quantity)} ${product.unit}`,
                `عدد الطلبات: ${formatNumber(product.orderCount)}`,
                `متوسط الكمية: ${formatNumber(product.avgQuantityPerOrder)} لكل طلب`,
                `إجمالي المبيعات (شامل الضريبة): ${formatPrice(product.totalSales)}`,
                `متوسط سعر ${product.unit} (شامل الضريبة): ${formatPrice(product.avgPrice)}` 
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
  // Cancel previous request
  if (dashboardAbortCtrl) dashboardAbortCtrl.abort();
  dashboardAbortCtrl = new AbortController();

  try {
    const today = getLocalDateISO();
    const wid = authStore.user?.branch_id || null;

    // ✅ Use analyticsStore.fetchDashboardSummary ONLY (no separate dailyCash call)
    // This prevents duplicate /analytics/sales calls with different date formats
    let summary = null;
    try {
      const dashboardData = await analyticsStore.fetchDashboardSummary({
        branchId: wid,
        sessionId: activeSessionId.value,
        startDate: today + ' 00:00:00',
        endDate: today + ' 23:59:59'
      });
      
      if (dashboardData) {
        summary = dashboardData;
      }
    } catch (e) {
      const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError';
      if (!isAborted) showToast('فشل تحميل ملخص لوحة المعلومات', 'error');
      throw e;
    }

    // Load session summary if session exists (non-blocking)
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

    dashboardStats.value = {
      openingBalance: sCalc.opening_balance || data.openingBalance || 0,
      totalSales: sTotals.total_sales || data.net_grand_total || 0,
      totalReturns: data.returns_grand_total || 0,
      expenses: data.expenses || 0,
      sessionData: { total_sales: sTotals.total_sales || 0, expected_cash: sCalc.expected_cash || 0 },
      rawData: {
        total_sales_amount: data.total_sales_amount,
        total_tax_amount: data.total_tax_amount,
        grand_total: data.grand_total,
        net_grand_total: data.net_grand_total,
        cash_total: data.cash_total || 0,
        cash_invoices_count: data.cash_invoices_count || 0,
        credit_total: data.credit_total || 0,
        credit_invoices_count: data.credit_invoices_count || 0,
        returns_grand_total: data.returns_grand_total,
        returns_count: data.returns_count || 0,
        cash_in_drawer: data.cash_in_drawer,
        net_cash_balance: data.net_cash_balance || 0
      }
    };

    if (Array.isArray(data.recentActivities)) {
      const sorted = [...data.recentActivities].sort((a, b) =>
        new Date(b.created_at || b.time || 0) - new Date(a.created_at || a.time || 0)
      );
      recentActivities.value = sorted.map(activity => {
        const createdAt = activity.created_at || activity.time || null;
        const parsedAmount = parseFloat(activity.amount || 0) || 0;
        const signedAmount = activity.type === 'return' ? -Math.abs(parsedAmount) : parsedAmount;
        const reference = activity.reference_number || activity.reference_code || activity.reference_id || '';
        const base = {
          id: activity.id, reference_id: activity.reference_id, type: activity.type,
          amount: signedAmount, raw_amount: parsedAmount, reference,
          time: createdAt ? formatRelativeTime(new Date(createdAt)) : '',
          fullTime: createdAt ? new Date(createdAt).toLocaleString('en-US', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''
        };
        switch (activity.type) {
          case 'sale':       return { ...base, description: activity.description || `فاتورة مبيعات #${reference}`, icon: 'fa-receipt',        colorClass: 'text-emerald-600 bg-emerald-100' };
          case 'return':     return { ...base, description: activity.description || `مرتجع مبيعات #${reference}`,  icon: 'fa-undo',           colorClass: 'text-rose-600 bg-rose-100' };
          case 'withdrawal': return { ...base, description: activity.description || `سحب نقدي #${reference}`,      icon: 'fa-arrow-down',     colorClass: 'text-amber-600 bg-amber-100' };
          case 'deposit':    return { ...base, description: activity.description || `إيداع نقدي #${reference}`,    icon: 'fa-arrow-up',       colorClass: 'text-blue-600 bg-blue-100' };
          default:           return { ...base, description: activity.description || 'عملية غير معروفة',            icon: 'fa-question-circle', colorClass: 'text-slate-600 bg-slate-100' };
        }
      });
    } else {
      recentActivities.value = [];
    }

    await fetchPaymentBreakdown();
    updateSalesChart(data.salesChart || data.sales_chart || { labels: [], data: [] });

    // Top products
    try {
      const useLocal = activeSessionId.value !== null;
      let startDate, endDate;
      if (useLocal) {
        startDate = today + ' 00:00:00';
        endDate = today + ' 23:59:59';
      } else {
        const { startUtcIso, endUtcIso } = localDateRangeToUTC(today, today);
        startDate = startUtcIso || (today + ' 00:00:00');
        endDate = endUtcIso || (today + ' 23:59:59');
      }
      const salesResp = await analyticsStore.fetchSalesAnalytics({ 
        startDate, 
        endDate, 
        sessionId: activeSessionId.value,
        branchId: wid
      });
      const sData = salesResp?.data || salesResp || {};
      if (Array.isArray(sData.top_products) && sData.top_products.length) {
        topProducts.value = sData.top_products
          .map(p => ({
            id: p.id, name: p.name || 'منتج غير معروف',
            quantity: parseFloat(p.total_quantity || 0),
            totalSales: parseFloat(p.total_revenue || 0),
            avgPrice: parseFloat(p.total_quantity || 0) > 0 ? parseFloat(p.total_revenue || 0) / parseFloat(p.total_quantity) : 0,
            orderCount: parseInt(p.order_count || 0),
            avgQuantityPerOrder: parseInt(p.order_count || 0) > 0
              ? parseFloat(p.total_quantity || 0) / parseInt(p.order_count)
              : 0,
            unit: p.unit || 'قطعة'
          }))
          .filter(p => p.quantity > 0)
          .sort((a, b) => b.quantity - a.quantity)
          .slice(0, 5);
        updateTopProductsChart({ 
          labels: topProducts.value.map(p => {
            let label = p.name;
            if (p.code) label += ` (${p.code})`;
            return `${label} - ${formatNumber(p.quantity)} ${p.unit}`;
          }), 
          data: topProducts.value.map(p => p.quantity) 
        });
      } else {
        topProducts.value = [];
        updateTopProductsChart({ labels: [], data: [] });
      }
    } catch { topProducts.value = []; }

  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError';
    if (!isAborted) showToast('فشل تحميل البيانات', 'error');
  } finally {
    isLoadingData.value = false;
  }
};

const fetchPaymentBreakdown = async () => {
  try {
    const today = getLocalDateISO();
    const wid = authStore.user?.branch_id || null;

    const analyticsRes = await analyticsStore.fetchSalesAnalytics({
      startDate: today + ' 00:00:00',
      endDate: today + ' 23:59:59',
      branchId: wid,
      sessionId: activeSessionId.value
    });

    const data = analyticsRes?.data || {};
    paymentBreakdown.value = { 
      cash_total: data.cash_total || 0, 
      card_total: data.card_total || 0, 
      credit_total: data.credit_total || 0, 
      bank_wallet_total: data.bank_wallet_total || 0, 
      returns_total: data.returns_total || 0 
    };
    cashDrawerTotal.value = data.cash_total || 0;
  } catch { cashDrawerTotal.value = 0; }
};

// ─── Session Actions ──────────────────────────────────────────────────────────
const getUser = () => {
  try {
    return authStore.user || JSON.parse(localStorage.getItem('user') || 'null');
  } catch { 
    return null; 
  }
};

// Opens session modal with different UX based on user role
// Admin: Must select a branch (temporary choice for this session)
// Regular User: Auto-uses assigned branch, no branch selector shown
const triggerOpenSession = async () => {
  try {
    const user = getUser();
    const userId = user?.id || null;
    const branchId = user?.branch_id ?? null;

    if (!userId) return;

    // Validate: Non-admin users MUST have assigned branch
    if (!branchId && !authStore.isAdmin) {
      showToast('لا يوجد فرع مرتبط بالمستخدم. يرجى تعيين فرع للكاشير.', 'error');
      return;
    }

    // ✅ Check for existing open session BEFORE opening new one
    if (branchId) {
      const { device_id } = getDeviceIdentity();
      const result = await sessionStore.getCurrentSession(branchId, userId, device_id);
      if (result?.status === 'success' && result.data?.id) {
        activeSessionId.value = result.data.id;
        showToast('هناك جلسة مفتوحة بالفعل لهذا الفرع.', 'info');
        return;
      }
    }

    // Reset form and show modal
    openingCashAmount.value = 0;
    selectedBranchId.value = null; // Reset to force admin selection
    openSessionModal.value = true;
  } catch (error) {
    showToast(error?.response?.data?.message || 'تعذر بدء عملية فتح الجلسة', 'error');
  }
};

const confirmOpenSession = async () => {
  try {
    isOpeningSession.value = true;
    
    // Validation: Admin must select a branch
    if (authStore.isAdmin && !selectedBranchId.value) {
      showToast('الرجاء تحديد الفرع قبل المتابعة', 'error');
      isOpeningSession.value = false;
      return;
    }
    
    const wid = authStore.isAdmin ? selectedBranchId.value : (authStore.user?.branch_id || null);
    if (!wid) { 
      showToast('الرجاء تحديد الفرع', 'error'); 
      isOpeningSession.value = false;
      return; 
    }
    const { device_id, device_name } = getDeviceIdentity();
    const result = await sessionStore.openSession({
      branch_id: wid,
      opening_cash_amount: openingCashAmount.value,
      session_type: 'manual',
      device_id,
      device_name,
      terminal_id: selectedTerminalId.value
    });
    if (result?.status === 'success' && result.data?.id) {
      activeSessionId.value = result.data.id;
      openSessionModal.value = false;
      // تحديث الشفت الحالي بعد فتح الجلسة (قد يكون الشفت فُتح تلقائياً)
      try {
        const wid2 = authStore.isAdmin ? selectedBranchId.value : (authStore.user?.branch_id || null);
        if (wid2 && selectedTerminalId.value) {
          const shiftResult = await shiftStore.getCurrentShift(wid2, selectedTerminalId.value, true);
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

// ─── Shift Actions (من القديم كاملة) ─────────────────────────────────────────
const openShift = async () => {
  if (isOpeningShift.value) return;
  try {
    const user = authStore.user;
    const wid = authStore.isAdmin ? (branchStore.selectedBranchId || user?.branch_id) : (user?.branch_id || branchStore.selectedBranchId);
    if (!wid) { showToast('الرجاء اختيار الفرع قبل فتح الوردية', 'error'); return; }
    if (!selectedTerminalId.value) { showToast('الرجاء اختيار جهاز نقطة البيع', 'error'); return; }
    isOpeningShift.value = true;
    const result = await shiftStore.openShift({
      branch_id: String(wid),
      terminal_id: selectedTerminalId.value,
      opening_cash_amount: Number(shiftOpeningAmount.value) || 0,
      notes: shiftNotes.value || undefined
    });
    if (result?.status === 'success') {
      currentShift.value = result.data || null;
      shiftOpeningAmount.value = 0;
      shiftNotes.value = '';
      showToast('تم فتح الوردية بنجاح', 'success');
    } else {
      showToast(result?.message || 'تعذر فتح الوردية', 'error');
    }
  } catch (e) { showToast(e?.response?.data?.message || 'تعذر فتح الوردية', 'error'); }
  finally { isOpeningShift.value = false; }
};

const closeShift = async () => {
  if (isClosingShift.value || !currentShift.value?.id) { showToast('لا يوجد وردية مفتوحة', 'info'); return; }
  try {
    isClosingShift.value = true;
    const result = await shiftStore.closeShift({
      shift_id: currentShift.value.id,
      closing_cash_amount: Number(shiftClosingAmount.value) || 0,
      notes: shiftNotes.value || undefined
    });
    if (result?.status === 'success') {
      currentShift.value = null;
      shiftClosingAmount.value = 0;
      shiftNotes.value = '';
      showToast('تم إغلاق الوردية بنجاح', 'success');
    } else {
      showToast(result?.message || 'تعذر إغلاق الوردية', 'error');
    }
  } catch (e) { showToast(e?.response?.data?.message || 'تعذر إغلاق الوردية', 'error'); }
  finally { isClosingShift.value = false; }
};

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
      // تحديث حالة الشفت — قد يكون أُغلق تلقائياً (cascade) أو لا يزال مفتوحاً
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
const openRenameDevice = () => { deviceNameInput.value = (localStorage.getItem('pos_device_name') || '').trim(); showRenameDevice.value = true; };
const saveDeviceName = () => {
  const val = (deviceNameInput.value || '').trim();
  try { if (val) localStorage.setItem('pos_device_name', val.slice(0, 64)); else localStorage.removeItem('pos_device_name'); showToast('تم حفظ اسم الجهاز', 'success'); } catch { showToast('تعذر الحفظ', 'error'); }
  showRenameDevice.value = false;
};

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

//  Load payment methods (now using cached store)
const loadPaymentMethods = async () => {
  try {
    await paymentStore.fetchPaymentMethods();
  } catch (e) {
    console.error('Failed to load payment methods for dashboard', e);
  }
};

//  Ensure active cashier session with proper parameters
const ensureCashierSession = async () => {
  try {
    const user = getUser();
    const userId = user?.id || null;
    const branchId = user?.branch_id ?? null;
    if (!userId) {
      activeSessionId.value = null;
      return;
    }
    const { device_id } = getDeviceIdentity();
    // Pass required parameters: branchId, cashierId, deviceId
    const result = await sessionStore.getCurrentSession(branchId, userId, device_id);
    activeSessionId.value = result?.status === 'success' && result.data?.id ? result.data.id : null;
  } catch (e) {
    if (e?.response?.status !== 404) {
      console.error('Session check failed:', e);
    }
    activeSessionId.value = null;
  }
};

//  watch لـ selectedBranchId
watch(selectedBranchId, (val) => {
  if (val) localStorage.setItem('active_branch_id', val);
  else localStorage.removeItem('active_branch_id');
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
      const activeBranchId = localStorage.getItem('active_branch_id');
      if (activeBranchId && activeBranchId !== 'all') {
        branchStore.setSelectedBranch(activeBranchId);
      } else if (!branchStore.selectedBranchId && branches.value.length) {
        branchStore.setSelectedBranch(branches.value[0].id);
      }
    } catch {}
  }

  try {
    const wid = authStore.user?.branch_id || branchStore.selectedBranchId;
    await terminalStore.fetchTerminals(wid);
    if (!selectedTerminalId.value && terminals.value.length) selectedTerminalId.value = terminals.value[0].id;
  } catch { /* handled by store */ }

  try {
    const wid = authStore.user?.branch_id || branchStore.selectedBranchId;
    if (wid && selectedTerminalId.value) {
      const result = await shiftStore.getCurrentShift(wid, selectedTerminalId.value);
      currentShift.value = result?.status === 'success' && result.data?.id ? result.data : null;
    }
  } catch { currentShift.value = null; }

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
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

.kpi-card { @apply bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl; }

.form-input-modern, .form-select-modern {
  @apply w-full h-[46px] bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm;
}

.modal-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1; }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>