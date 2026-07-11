<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">

    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div :class="[type === 'customers' ? 'bg-blue-600 shadow-blue-100' : 'bg-indigo-600 shadow-indigo-100']"
             class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-xl text-white shrink-0">
          <i :class="[type === 'customers' ? 'fas fa-user-tie' : 'fas fa-truck-ramp-box', 'text-2xl']"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تفاصيل ملف الجهة</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">عرض شامل للبيانات، الحركات المجمعة، والفواتير</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <RouterLink v-if="type && id"
                    :to="`/contacts/${type}/${id}/statement`"
                    class="px-6 py-2.5 rounded-xl text-xs font-black text-blue-600 bg-blue-50 border border-blue-100 shadow-sm hover:bg-blue-100 transition-all flex items-center gap-2 active:scale-95">
          <i class="fas fa-file-chart-line"></i> كشف الحساب التفصيلي
        </RouterLink>
        <button @click="goBack"
                class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-600 bg-white border border-slate-100 shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2 active:scale-95">
          <i class="fas fa-arrow-right"></i> رجوع للقائمة
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-32 text-center">
      <BaseSpinner :size="40" :color="type === 'customers' ? '#2563eb' : '#4f46e5'" />
      <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mt-4 animate-pulse">جاري تحليل ملف البيانات...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!contact" class="py-32 text-center bg-white rounded-[3rem] shadow-sm border border-slate-100">
      <div class="w-20 h-20 bg-slate-50 text-slate-200 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-user-slash text-4xl"></i>
      </div>
      <p class="text-slate-500 font-black text-lg uppercase tracking-widest">عذراً، لم يتم العثور على البيانات</p>
      <button @click="goBack" class="mt-6 text-blue-600 font-black text-xs hover:underline">العودة للخلف</button>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-8 w-full">

      <!-- ═══════════════════════════════════════════════════════════
           Collapsible Filters Area
           ═══════════════════════════════════════════════════════════ -->
      <details class="bg-white rounded-[2rem] shadow-sm border border-slate-100 mb-8 overflow-hidden group">
        <summary class="px-8 py-5 font-black text-sm text-slate-800 cursor-pointer flex items-center justify-between hover:bg-slate-50/50 transition-colors list-none">
          <div class="flex items-center gap-3">
            <i class="fas fa-filter text-blue-500"></i>
            <span>الفترة الزمنية وخيارات التحليل المتقدمة</span>
          </div>
          <i class="fas fa-chevron-down text-slate-300 group-open:rotate-180 transition-transform"></i>
        </summary>

        <div class="px-8 pb-8 pt-4 space-y-6 border-t border-slate-50">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="space-y-2">
              <label class="filter-label">من تاريخ</label>
              <div class="relative">
                <input ref="startDateRef" type="date" v-model="startDate" @change="applyFilter" class="form-input-modern font-bold w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" />
                <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="startDateRef?.showPicker()"></i>
              </div>
            </div>
            <div class="space-y-2">
              <label class="filter-label">إلى تاريخ</label>
              <div class="relative">
                <input ref="endDateRef" type="date" v-model="endDate" @change="applyFilter" class="form-input-modern font-bold w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" />
                <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="endDateRef?.showPicker()"></i>
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="filter-label">نطاقات زمنية سريعة</label>
              <div class="flex flex-wrap gap-2">
                <button @click="setQuickRange('last7')" class="quick-range-btn px-3 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition-all text-slate-600">آخر 7 أيام</button>
                <button @click="setQuickRange('thisMonth')" class="quick-range-btn px-3 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition-all text-slate-600">هذا الشهر</button>
                <button @click="setQuickRange('prevMonth')" class="quick-range-btn px-3 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition-all text-slate-600">الشهر السابق</button>
                <button @click="setQuickRange('thisYear')" class="quick-range-btn px-3 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition-all text-slate-600">هذا العام</button>
                <button @click="setQuickRange('allTime')" class="quick-range-btn px-3 py-1.5 rounded-xl text-[10px] font-black bg-white border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50 transition-all text-slate-600">كل التاريخ</button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-50">
            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
              <i class="fas fa-info-circle ml-1"></i>
              الفترة المحددة: <span class="text-blue-600">{{ startDate }}</span> ↔ <span class="text-blue-600">{{ endDate }}</span>
            </div>
            <button @click="applyFilter" :disabled="isLoading" class="px-6 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-black hover:bg-blue-700 disabled:opacity-50 transition-all flex items-center gap-2 active:scale-95">
              <i :class="isLoading ? 'fas fa-spinner animate-spin' : 'fas fa-search'"></i>
              {{ isLoading ? 'جاري التحديث...' : 'تحديث البيانات' }}
            </button>
          </div>
        </div>
      </details>

      <!-- Contact Header Profile Card -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-hidden group">
        <div :class="[type === 'customers' ? 'bg-blue-50/50' : 'bg-indigo-50/50']"
             class="absolute top-0 left-0 w-40 h-40 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>

        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8">
          <div class="flex items-center gap-6">
            <div :class="[type === 'customers' ? 'bg-blue-50 text-blue-600' : 'bg-indigo-50 text-indigo-600']"
                 class="w-20 h-20 rounded-[2rem] flex items-center justify-center text-3xl shadow-sm border border-white shrink-0">
              <i :class="type === 'customers' ? 'fas fa-user' : 'fas fa-truck'"></i>
            </div>
            <div>
              <h3 class="text-2xl font-black text-slate-900 leading-tight mb-2">{{ contact.name }}</h3>
              <div class="flex flex-wrap gap-4 text-xs font-bold text-slate-400">
                <span class="flex items-center gap-2">
                  <i class="fas fa-phone-alt text-blue-500 text-[10px]"></i>
                  {{ contact.phone || 'بدون هاتف' }}
                </span>
                <span class="flex items-center gap-2">
                  <i class="fas fa-envelope text-blue-500 text-[10px]"></i>
                  {{ contact.email || 'بدون بريد' }}
                </span>
                <span v-if="contact.address" class="flex items-center gap-2">
                  <i class="fas fa-map-marker-alt text-blue-500 text-[10px]"></i>
                  {{ contact.address }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="canViewSensitiveData" class="bg-slate-900 px-10 py-6 rounded-[2rem] text-white shadow-2xl shadow-slate-900/20 text-center min-w-[240px]">
            <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-2">الرصيد الحسابي الحالي</p>
            <div class="flex items-baseline justify-center gap-2">
              <span class="text-3xl font-black tracking-tighter">{{ formatPrice(Math.abs(contact.balance)) }}</span>
              <span class="text-[11px] font-bold text-white/40 uppercase tracking-widest">{{ currencySymbol }}</span>
            </div>
            <div :class="[type === 'customers'
                ? (Number(contact.balance) > 0 ? 'text-rose-400' : 'text-emerald-400')
                : (Number(contact.balance) > 0 ? 'text-emerald-400' : 'text-rose-400')]"
                 class="mt-3 text-[10px] font-black uppercase tracking-widest">
              <i class="fas fa-info-circle ml-1"></i>
              {{ type === 'customers'
                ? (contact.balance > 0 ? 'ذمم مدينة (عليه)' : (contact.balance < 0 ? 'رصيد دائن (له)' : 'حساب مستقر'))
                : (contact.balance > 0 ? 'ذمم دائنة (له)' : (contact.balance < 0 ? 'رصيد مدين (لنا)' : 'حساب مستقر')) }}
            </div>
          </div>
          <div v-else class="bg-slate-900 px-10 py-6 rounded-[2rem] text-white shadow-2xl shadow-slate-900/20 text-center min-w-[240px]">
            <p class="text-[10px] font-black text-orange-400 uppercase tracking-[0.2em] mb-2">بيانات محدودة الوصول</p>
            <p class="text-sm font-black">🔒 لا توجد صلاحية للعرض</p>
          </div>
        </div>


      </div>

      <!-- ═══════════════════════════════════════════════════════════
           PHASE 2: Smart Alerts (#5)
           ═══════════════════════════════════════════════════════════ -->
      <div v-if="hasOutstanding || hasCredit" class="space-y-3">
        
        <!-- Outstanding Balance Alert -->
        <div v-if="hasOutstanding" class="bg-gradient-to-r from-rose-50 to-red-50 border-l-4 border-l-rose-500 rounded-2xl p-6 shadow-sm border border-rose-100">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="fas fa-exclamation-circle text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-black text-rose-800 mb-1">
                  <span v-if="type === 'customers'">🔴 يوجد رصيد مستحق</span>
                  <span v-else>🔴 يوجد رصيد مدين</span>
                </p>
                <p class="text-xs text-slate-600 leading-relaxed">
                  <span v-if="type === 'customers'">هناك مبلغ معلق بانتظار التحصيل من العميل بقيمة</span>
                  <span v-else>هناك مبلغ معلق بانتظار الدفع للمورد بقيمة</span>
                  <span class="font-black text-rose-700 mx-1">{{ formatPrice(outstandingAmount) }}</span>
                </p>
              </div>
            </div>
            <button class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-black flex items-center gap-2 transition-all active:scale-95 flex-shrink-0">
              <i class="fas fa-hand-holding-dollar text-sm"></i>
              <span v-if="type === 'customers'">تحصيل الآن</span>
              <span v-else>دفع الآن</span>
            </button>
          </div>
        </div>

        <!-- Overpayment Credit Alert -->
        <div v-if="hasCredit" class="bg-gradient-to-r from-emerald-50 to-teal-50 border-l-4 border-l-emerald-500 rounded-2xl p-6 shadow-sm border border-emerald-100">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="fas fa-check-circle text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-black text-emerald-800 mb-1">
                  <span v-if="type === 'customers'">🟢 رصيد دائن لصالح العميل</span>
                  <span v-else>🟢 رصيد دائن لصالحنا</span>
                </p>
                <p class="text-xs text-slate-600 leading-relaxed">
                  <span v-if="type === 'customers'">العميل يملك رصيد دائن معه بقيمة</span>
                  <span v-else>نملك رصيد دائن لدى المورد بقيمة</span>
                  <span class="font-black text-emerald-700 mx-1">{{ formatPrice(creditAmount) }}</span>
                  <span>يمكن استخدامه في فواتير قادمة</span>
                </p>
              </div>
            </div>
            <button v-if="type === 'customers'" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-black flex items-center gap-2 transition-all active:scale-95 flex-shrink-0">
              <i class="fas fa-file-invoice text-sm"></i>
              <span>فاتورة جديدة</span>
            </button>
            <button v-else class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-black flex items-center gap-2 transition-all active:scale-95 flex-shrink-0">
              <i class="fas fa-file-invoice text-sm"></i>
              <span>شراء جديد</span>
            </button>
          </div>
        </div>

      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 p-1.5 bg-white rounded-2xl border border-slate-100 shadow-sm w-fit mx-auto sticky top-4 z-40 backdrop-blur-md bg-white/90">
        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                :class="[activeTab === tab.id ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-500 hover:bg-slate-50']"
                class="tab-pill">
          <i :class="[tab.icon, 'text-[10px]']"></i>
          <span>{{ tab.name }}</span>
        </button>
      </div>

      <!-- Tab Content -->
      <div class="min-h-[400px]">

        <!-- ══════════════════════════════════════
             TAB: Overview (نظرة عامة)
             ══════════════════════════════════════ -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4">

            <!-- 1. عدد الفواتير -->
            <div class="overview-box border-l-4 border-l-slate-400">
              <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 bg-slate-50 text-slate-400 rounded-xl flex items-center justify-center">
                  <i class="fas fa-file-invoice"></i>
                </div>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">محاسبي</span>
              </div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">عدد الفواتير</p>
              <p class="text-2xl font-black text-slate-800">{{ formatNumber(contact.invoices_count ?? invoicesCount) }}</p>
            </div>

            <!-- 2. إجمالي قيمة الفواتير -->
            <div class="overview-box border-l-4 border-l-indigo-500" title="إجمالي القيمة الإجمالية للفواتير الأصلية">
              <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                  <i class="fas fa-receipt"></i>
                </div>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">إجمالي</span>
              </div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">إجمالي قيمة الفواتير</p>
              <p class="text-2xl font-black text-indigo-600">{{ formatPrice(contact.invoices_total ?? invoicesTotal) }}</p>
            </div>

            <!-- 3. المرتجعات الموسّعة -->
            <div class="overview-box border-l-4 border-l-orange-400">
              <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center">
                  <i class="fas fa-rotate-left"></i>
                </div>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">مرتجع</span>
              </div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">إجمالي قيمة المرتجعات</p>
              
              <!-- الرقم الرئيسي -->
              <p class="text-2xl font-black" :class="returnsTotalValue > 0 ? 'text-orange-500' : 'text-slate-300'">
                {{ formatPrice(returnsTotalValue) }}
              </p>
              
              <!-- تفاصيل مع توضيح المعادلة -->
              <div class="space-y-2 pt-3 border-t border-slate-50">
                <!-- Row 1: مسترد نقداً -->
                <div class="flex justify-between items-center">
                  <span class="text-[9px] font-bold text-slate-400 flex items-center gap-1">
                    <i class="fas fa-money-bill-wave text-rose-400 text-[8px]"></i>
                    مسترد نقداً من المرتجعات
                  </span>
                  <span class="text-[10px] font-black text-rose-500">
                    {{ formatPrice(refundsCash) }}
                  </span>
                </div>
                
                <!-- Row 2: مستخدم لتسوية فواتير (يظهر فقط إذا كان > 0) -->
                <div v-if="returnsAppliedToInvoices > 0" class="flex justify-between items-center">
                  <span class="text-[9px] font-bold text-slate-400 flex items-center gap-1">
                    <i class="fas fa-check-circle text-amber-400 text-[8px]"></i>
                    مستخدم لتسوية فواتير أخرى
                  </span>
                  <span class="text-[10px] font-black text-amber-500">
                    {{ formatPrice(returnsAppliedToInvoices) }}
                  </span>
                </div>
                
                <!-- Row 3: رصيد متاح (يظهر فقط إذا كان > 0) -->
                <div v-if="returnsTotal > 0" class="flex justify-between items-center">
                  <span class="text-[9px] font-bold text-slate-400 flex items-center gap-1">
                    <i class="fas fa-receipt text-teal-400 text-[8px]"></i>
                    رصيد دائن متاح (Available Credit)
                  </span>
                  <span class="text-[10px] font-black text-teal-600">
                    {{ formatPrice(returnsTotal) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- 4. إجمالي التحصيل -->
            <div class="overview-box border-l-4 border-l-emerald-500">
              <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                  <i class="fas fa-hand-holding-dollar"></i>
                </div>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">مدفوع</span>
              </div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">إجمالي التحصيل</p>
              <p class="text-2xl font-black text-emerald-600">{{ formatPrice(contact.payments_total ?? paymentsTotal) }}</p>
            </div>

            <!-- 5. إجمالي المتبقي -->
            <div class="overview-box border-l-4 border-l-rose-500">
              <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                  <i class="fas fa-hourglass-half"></i>
                </div>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">باقي</span>
              </div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">إجمالي المتبقي</p>
              <p class="text-2xl font-black text-rose-600">{{ formatPrice(contact.balance ?? remainingTotal) }}</p>
            </div>
          </div>

          <!-- Overpayment / Credit Alert -->
          <transition name="fade">
            <div v-if="overpaymentCredit > 0"
                 class="bg-emerald-50 border border-emerald-100 rounded-[1.5rem] p-6 flex items-center gap-6 shadow-sm shadow-emerald-50">
              <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-emerald-600 shadow-sm">
                <i class="fas fa-check-double text-xl"></i>
              </div>
              <div class="flex-grow">
                <h4 class="text-sm font-black text-emerald-900 leading-none">تنبيه الرصيد الدائن الفائض</h4>
                <p class="text-xs font-bold text-emerald-600 mt-2">
                  يوجد رصيد متاح في الحساب المحاسبي بقيمة
                  <span class="font-black text-lg mx-1 underline underline-offset-4 decoration-2">{{ formatPrice(overpaymentCredit) }}</span>
                  {{ type === 'customers' ? 'لصالح العميل (مقدمات دفع)' : 'لصالحكم لدى المورد' }}.
                </p>
              </div>
            </div>
          </transition>

          <!-- ═══════════════════════════════════════════════════════════
               PHASE 2: Aging Analysis (#2) - SAP/Oracle Style Chart (RBAC Protected)
               ═══════════════════════════════════════════════════════════ -->
          <div v-if="canViewAgingAnalysis" class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
              <h3 class="text-xl font-black text-slate-900 flex items-center gap-3">
                <span class="w-1.5 h-7 bg-gradient-to-b from-amber-500 to-rose-500 rounded-full"></span>
                <i class="fas fa-chart-bar text-amber-500"></i>
                تحليل أعمار الديون المستحقة
              </h3>
              <div class="text-xs font-black text-slate-400 bg-slate-50 px-4 py-2 rounded-xl">
                الإجمالي: <span class="text-slate-800">{{ formatPrice(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus) }}</span>
              </div>
            </div>

            <!-- SAP/Oracle Style Aging Chart -->
            <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
              <div class="p-8 space-y-8">
                
                <!-- Current/Not Due -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                      <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-600 shrink-0">
                        <i class="fas fa-check-circle text-sm"></i>
                      </div>
                      <div>
                        <p class="text-sm font-black text-slate-900">فواتير غير مستحقة الدفع (حالي)</p>
                        <p class="text-[11px] text-slate-500">0 - لم تستحق بعد</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-black text-emerald-600">{{ formatPrice(agingBreakdown.current) }}</p>
                      <p class="text-xs text-slate-400 mt-1">{{ Math.round((agingBreakdown.current / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100) }}%</p>
                    </div>
                  </div>
                  <div class="w-full h-6 bg-slate-100 rounded-lg overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-lg transition-all duration-500"
                         :style="{ width: Math.max((agingBreakdown.current / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100, 2) + '%' }">
                    </div>
                  </div>
                </div>

                <!-- 1-30 Days Overdue -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                      <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center text-amber-600 shrink-0">
                        <i class="fas fa-clock text-sm"></i>
                      </div>
                      <div>
                        <p class="text-sm font-black text-slate-900">متأخر 1-30 يوم</p>
                        <p class="text-[11px] text-slate-500">متأخر قليل - قريب من الاستحقاق</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-black text-amber-600">{{ formatPrice(agingBreakdown._1to30) }}</p>
                      <p class="text-xs text-slate-400 mt-1">{{ Math.round((agingBreakdown._1to30 / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100) }}%</p>
                    </div>
                  </div>
                  <div class="w-full h-6 bg-slate-100 rounded-lg overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-amber-400 to-amber-600 rounded-lg transition-all duration-500"
                         :style="{ width: Math.max((agingBreakdown._1to30 / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100, 2) + '%' }">
                    </div>
                  </div>
                </div>

                <!-- 31-60 Days Overdue -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                      <div class="w-8 h-8 bg-orange-50 rounded-lg flex items-center justify-center text-orange-600 shrink-0">
                        <i class="fas fa-exclamation-triangle text-sm"></i>
                      </div>
                      <div>
                        <p class="text-sm font-black text-slate-900">متأخر 31-60 يوم</p>
                        <p class="text-[11px] text-slate-500">متأخر متوسط - يحتاج متابعة</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-black text-orange-600">{{ formatPrice(agingBreakdown._31to60) }}</p>
                      <p class="text-xs text-slate-400 mt-1">{{ Math.round((agingBreakdown._31to60 / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100) }}%</p>
                    </div>
                  </div>
                  <div class="w-full h-6 bg-slate-100 rounded-lg overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-orange-400 to-orange-600 rounded-lg transition-all duration-500"
                         :style="{ width: Math.max((agingBreakdown._31to60 / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100, 2) + '%' }">
                    </div>
                  </div>
                </div>

                <!-- 60+ Days Overdue -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                      <div class="w-8 h-8 bg-rose-50 rounded-lg flex items-center justify-center text-rose-600 shrink-0">
                        <i class="fas fa-fire text-sm"></i>
                      </div>
                      <div>
                        <p class="text-sm font-black text-slate-900">متأخر 60+ يوم</p>
                        <p class="text-[11px] text-slate-500">متأخر حرج - يحتاج تدخل فوري</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-black text-rose-600">{{ formatPrice(agingBreakdown._60plus) }}</p>
                      <p class="text-xs text-slate-400 mt-1">{{ Math.round((agingBreakdown._60plus / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100) }}%</p>
                    </div>
                  </div>
                  <div class="w-full h-6 bg-slate-100 rounded-lg overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-rose-400 to-rose-600 rounded-lg transition-all duration-500"
                         :style="{ width: Math.max((agingBreakdown._60plus / Math.max(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus, 1)) * 100, 2) + '%' }">
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <!-- Summary Footer Bar -->
            <div class="bg-gradient-to-r from-slate-50 to-slate-100 rounded-2xl p-6 border border-slate-200">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                  <p class="text-[11px] font-black text-slate-500 uppercase tracking-wider mb-2">إجمالي الديون</p>
                  <p class="text-2xl font-black text-slate-900">{{ formatPrice(agingBreakdown.current + agingBreakdown._1to30 + agingBreakdown._31to60 + agingBreakdown._60plus) }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-black text-slate-500 uppercase tracking-wider mb-2">متأخر 1-30 يوم</p>
                  <p class="text-2xl font-black text-amber-600">{{ formatPrice(agingBreakdown._1to30) }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-black text-slate-500 uppercase tracking-wider mb-2">متأخر 31-60 يوم</p>
                  <p class="text-2xl font-black text-orange-600">{{ formatPrice(agingBreakdown._31to60) }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-black text-slate-500 uppercase tracking-wider mb-2">متأخر 60+ يوم</p>
                  <p class="text-2xl font-black text-rose-600">{{ formatPrice(agingBreakdown._60plus) }}</p>
                </div>
              </div>
            </div>
          </div>
          <!-- Permission Denied Message -->
          <div v-else class="bg-orange-50 border border-orange-200 rounded-2xl p-8 text-center">
            <i class="fas fa-lock text-5xl text-orange-400 mb-4 block"></i>
            <h4 class="text-lg font-black text-orange-900 mb-2">🔒 بيانات محدودة الوصول</h4>
            <p class="text-sm text-orange-700">ليس لديك صلاحية لعرض تحليل أعمار الديون المستحقة</p>
          </div>
        </div>

        <!-- ══════════════════════════════════════
             TAB: Transactions (الحركات المالية)
             ══════════════════════════════════════ -->
        <div v-else-if="activeTab === 'transactions'"
             class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fadeIn">
          <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
            <div class="flex items-center gap-3">
              <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
              <h3 class="font-black text-slate-800 uppercase tracking-tight">سجل حركات كشف الحساب</h3>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">حجم الصفحة:</label>
              <select v-model.number="txPageSize"
                      class="h-9 text-xs font-black border-slate-200 rounded-xl bg-white px-3 outline-none focus:ring-2 focus:ring-blue-100 transition-all border">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>

          <div class="overflow-x-auto max-h-[60vh] custom-scroll">
            <table class="w-full text-right text-sm">
              <thead>
                <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                  <th class="px-6 py-5">التاريخ</th>
                  <th class="px-4 py-5">نوع الحركة</th>
                  <th class="px-4 py-5 text-rose-500">مدين (+)</th>
                  <th class="px-4 py-5 text-emerald-500">دائن (-)</th>
                  <th class="px-6 py-5">البيان / الوصف</th>
                  <th class="px-4 py-5">المرجع</th>
                  <th class="px-6 py-5">الرصيد</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-if="isLoading">
                  <td colspan="7" class="py-20 text-center">
                    <BaseSpinner :size="32" :color="type === 'customers' ? '#2563eb' : '#4f46e5'" />
                  </td>
                </tr>
                <tr v-else-if="!transactions.length">
                  <td colspan="7" class="py-20 text-center text-slate-300">
                    <i class="fas fa-list-ol text-5xl mb-4 block"></i>
                    <p class="font-black text-sm uppercase">لا توجد حركات مالية مسجلة</p>
                  </td>
                </tr>
                <tr v-else v-for="t in transactions" :key="t.id" class="hover:bg-slate-50 transition-all">
                  <td class="px-6 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ formatDate(t.date) }}</td>
                  <td class="px-4 py-4 font-black text-slate-700 text-xs">{{ transactionTypeText(t.type || t.transaction_type) }}</td>
                  <td class="px-4 py-4 font-black font-mono tracking-tighter"
                      :class="t.debit > 0 ? 'text-rose-600' : 'text-slate-200'">
                    {{ t.debit > 0 ? formatPrice(t.debit) : '—' }}
                  </td>
                  <td class="px-4 py-4 font-black font-mono tracking-tighter"
                      :class="t.credit > 0 ? 'text-emerald-600' : 'text-slate-200'">
                    {{ t.credit > 0 ? formatPrice(t.credit) : '—' }}
                  </td>
                  <td class="px-6 py-4 text-xs font-bold text-slate-500 max-w-xs truncate" :title="t.description">
                    {{ t.description }}
                  </td>
                  <td class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-tighter">{{ t.reference || '-' }}</td>
                  <td class="px-6 py-4 font-black font-mono tracking-tighter text-base"
                      :class="t.balance === 0 ? 'text-slate-400' : (t.balance < 0 ? 'text-emerald-700' : 'text-rose-700')">
                    {{ formatPrice(t.balance) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="px-8 py-5 bg-slate-50/50 border-t border-slate-50 flex items-center justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
              ✓ إجمالي الحركات: {{ totalRecords }} (الصفحة الحالية: {{ transactions.length }})
            </span>
            <div class="flex items-center gap-2">
              <button 
                @click="page = Math.max(1, page - 1)" 
                :disabled="page <= 1" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-black hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2"
              >
                <i class="fas fa-angle-right"></i> السابق
              </button>
              
              <div class="px-5 py-2 bg-white rounded-xl border border-slate-100 text-xs font-black">
                صفحة {{ page }} من {{ totalPages || 1 }}
              </div>
              
              <button 
                @click="page++" 
                :disabled="page >= totalPages" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-black hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2"
              >
                التالي <i class="fas fa-angle-left"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════
             TAB: Invoices (الفواتير)
             ══════════════════════════════════════ -->
        <div v-else-if="activeTab === 'invoices'"
             class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fadeIn">
          <div class="p-8 border-b border-slate-50 bg-slate-50/30">
            <h3 class="font-black text-slate-800 uppercase tracking-tight">
              {{ type === 'customers' ? 'سجل فواتير المبيعات' : 'سجل فواتير الشراء والتوريد' }}
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
              <thead>
                <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                  <th class="px-6 py-5">تاريخ الفاتورة</th>
                  <th class="px-4 py-5">رقم الفاتورة</th>
                  <th class="px-4 py-5">الحالة</th>
                  <th class="px-4 py-5 text-right">الأصناف</th>
                  <th class="px-4 py-5 text-right">الإجمالي</th>
                  <th class="px-4 py-5 text-right">المدفوع</th>
                  <th class="px-6 py-5 text-right">المتبقي</th>
                  <th class="px-4 py-5 text-center">قيد محاسبي؟</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-if="isLoading">
                  <td colspan="8" class="py-20 text-center">
                    <BaseSpinner :size="32" :color="type === 'customers' ? '#2563eb' : '#4f46e5'" />
                  </td>
                </tr>
                <tr v-else-if="!invoices.length">
                  <td colspan="8" class="py-20 text-center text-slate-300">
                    <i class="fas fa-file-invoice text-5xl mb-4 block"></i>
                    <p class="font-black text-sm uppercase">لا توجد فواتير مسجلة</p>
                  </td>
                </tr>
                <tr v-else v-for="inv in sortedInvoices" :key="inv.id" class="hover:bg-slate-50 transition-all group font-bold">
                  <td class="px-6 py-4 text-xs text-slate-400 font-mono tracking-tighter">
                    {{ formatDate(inv.invoice_date || inv.created_at || inv.date) }}
                  </td>
                  <td class="px-4 py-4">
                    <RouterLink :to="invoiceRoute(inv)" class="text-blue-600 font-black hover:underline underline-offset-4">
                      {{ inv.invoice_number || ('#' + inv.id) }}
                    </RouterLink>
                  </td>
                  <td class="px-4 py-4">
                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter shadow-sm',
                      (inv.dynamic_status || inv.status) === 'returned' || (inv.dynamic_status || inv.status) === 'closed_by_return' ? 'bg-orange-100 text-orange-700'
                      : (inv.dynamic_status || inv.status) === 'paid'                                           ? 'bg-emerald-100 text-emerald-700'
                      : (inv.dynamic_status || inv.status) === 'settled_by_return'                              ? 'bg-teal-100 text-teal-700'
                      : (inv.dynamic_status || inv.status) === 'settled_by_credit'                              ? 'bg-cyan-100 text-cyan-700'
                      : ((inv.dynamic_status || inv.status) === 'partial' || (inv.dynamic_status || inv.status) === 'partially_paid' || (inv.dynamic_status || inv.status) === 'pending_payment') ? 'bg-amber-100 text-amber-700'
                      : ((inv.dynamic_status || inv.status) === 'rejected' || (inv.dynamic_status || inv.status) === 'canceled' || (inv.dynamic_status || inv.status) === 'cancelled') ? 'bg-red-100 text-red-700'
                      : 'bg-yellow-100 text-yellow-700']">
                      {{ inv.status_label || statusText(inv.dynamic_status || inv.status) }}
                    </span>
                  </td>
                  <!-- ✅ NEW: Items Count Column -->
                  <td class="px-4 py-4 text-right font-black text-slate-700">
                    {{ inv.items_count || 0 }}
                  </td>
                  <td class="px-4 py-4 text-right font-mono tracking-tighter text-slate-900">
                    {{ formatPriceEn((Number(inv.net_total_amount ?? inv.total_amount ?? 0) + Number(inv.tax_amount ?? 0))) }}
                  </td>
                  <td class="px-4 py-4 text-right font-mono tracking-tighter text-emerald-600">
                    {{ formatPriceEn(inv.actual_paid_amount ?? inv.paid ?? inv.paid_amount ?? inv.amount_paid) }}
                  </td>
                  <td class="px-6 py-4 text-right font-mono tracking-tighter"
                      :class="getInvoiceRemaining(inv) > 0 ? 'text-rose-600' : 'text-slate-300'">
                    {{ formatPriceEn(getInvoiceRemaining(inv)) }}
                  </td>
                  <!-- ✅ NEW: Journal Entry Column -->
                  <td class="px-4 py-4 text-center font-black">
                    <span v-if="inv.has_journal" class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-600 rounded-lg text-[10px]" title="قيد محاسبي موجود">
                      ✓
                    </span>
                    <span v-else class="text-slate-300 font-bold">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ══════════════════════════════════════
             TAB: Payments (القبض والمدفوعات)
             ══════════════════════════════════════ -->
        <div v-if="activeTab === 'payments'" class="space-y-4 animate-fadeIn">

          <!-- ── قسم 1: سندات القبض ─────────────────────────────── -->
          <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-50 bg-slate-50/30 flex items-center gap-3">
              <span class="w-1.5 h-5 bg-emerald-500 rounded-full"></span>
              <h3 class="font-black text-slate-800 uppercase tracking-tight">
                {{ type === 'customers' ? 'سجل عمليات القبض (سندات)' : 'سجل عمليات الدفع والتحويل' }}
              </h3>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-right text-sm">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                    <th class="px-6 py-5">تاريخ العملية</th>
                    <th class="px-4 py-5">المرجع / المستند</th>
                    <th class="px-4 py-5">طريقة الدفع</th>
                    <th class="px-6 py-5 text-left">المبلغ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="isLoading">
                    <td colspan="4" class="py-16 text-center">
                      <BaseSpinner :size="32" :color="type === 'customers' ? '#2563eb' : '#4f46e5'" />
                    </td>
                  </tr>
                  <tr v-else-if="!receiptPayments.length">
                    <td colspan="4" class="py-16 text-center text-slate-300">
                      <i class="fas fa-wallet text-4xl mb-3 block"></i>
                      <p class="font-black text-sm uppercase">لا توجد دفعات مالية مسجلة</p>
                    </td>
                  </tr>
                  <tr v-else v-for="p in sortedReceiptPayments" :key="p.id" class="hover:bg-slate-50 transition-all font-bold group">
                    <td class="px-6 py-4 text-xs text-slate-400 font-mono tracking-tighter group-hover:text-slate-800">
                      {{ formatDateEn(p.payment_date || p.date || p.created_at) }}
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-black text-slate-700 leading-none">{{ p.reference_label || paymentRef(p) }}</div>
                      <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        رقم العملية: {{ p.id }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="px-3 py-1 bg-slate-100 rounded-lg text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        {{ p.payment_method_name || p.method || p.payment_method || 'نقدي' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-left font-black font-mono tracking-tighter text-base text-emerald-600">
                      {{ formatPriceEn(p.amount) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ── قسم 2: مدفوعات المرتجعات (صرف للعميل) ────────── -->
          <div v-if="refundPayments.length" class="bg-white rounded-[2.5rem] shadow-sm border border-rose-100 overflow-hidden">
            <div class="p-6 border-b border-rose-50 bg-rose-50/30 flex items-center gap-3">
              <span class="w-1.5 h-5 bg-rose-400 rounded-full"></span>
              <h3 class="font-black text-slate-800 uppercase tracking-tight">مدفوعات المرتجعات — صرف للعميل</h3>
              <span class="text-xs text-rose-400 font-black">(عمليات صرف وليست تحصيل)</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-right text-sm">
                <thead>
                  <tr class="bg-rose-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-rose-50">
                    <th class="px-6 py-5">تاريخ العملية</th>
                    <th class="px-4 py-5">المرجع / المستند</th>
                    <th class="px-4 py-5">طريقة الدفع</th>
                    <th class="px-6 py-5 text-left">المبلغ المصروف</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-rose-50">
                  <tr v-for="p in sortedRefundPayments" :key="p.id" class="hover:bg-rose-50/50 transition-all font-bold group">
                    <td class="px-6 py-4 text-xs text-slate-400 font-mono tracking-tighter group-hover:text-slate-800">
                      {{ formatDateEn(p.payment_date || p.date || p.created_at) }}
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-black text-slate-700 leading-none">{{ p.reference_label || paymentRef(p) }}</div>
                      <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        رقم العملية: {{ p.id }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="px-3 py-1 bg-rose-100 rounded-lg text-[10px] font-black text-rose-500 uppercase tracking-widest">
                        {{ p.payment_method_name || p.method || p.payment_method || 'نقدي' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-left font-black font-mono tracking-tighter text-base text-rose-500">
                      - {{ formatPriceEn(p.amount) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from '@/composables/useToast';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useStatementData } from '@/composables/useStatementData';
import { useStatementRBAC } from '@/composables/useStatementRBAC';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { getLocalDateISO } from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// --- Core ---
const route = useRoute();
const router = useRouter();
const { showToast } = useToast();
const { fetchSettings, currencySymbol } = useCompanyCurrency();

const type = ref(route.params.type); // 'customers' | 'suppliers'
const id = ref(Number(route.params.id));

// --- Composables ---
const {
  fetchStatementData,
  loading: statementLoading,
  error: statementError,
  data: statementData,
  totals,
  invoices,
  payments,
  transactions,
  returns,
  agingAnalysis,
  availableCredit,
  alertStatus,
  page,           // ← جديد: pagination
  perPage,        // ← جديد: pagination
  totalRecords,   // ← جديد: pagination
  totalPages,     // ← جديد: pagination
  currencySymbol: _currencySymbol,
  formatCurrencyLocale
} = useStatementData();

// --- RBAC Composable ---
const {
  canViewStatement,
  canExport,
  canViewSensitiveData,
  canViewAgingAnalysis,
  logAccess,
  logDenial
} = useStatementRBAC(type.value);

// --- Stores ---
const customerStore = useCustomerStore();
const supplierStore = useSupplierStore();

// --- State ---
const isLoading = ref(false);
const contact = ref(null);
const activeTab = ref('overview');

// ─── Filter State ───────────────────────────────────────────────────────────
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const startOfMonth = `${yyyy}-${mm}-01`;
const startOfYear = `${yyyy}-01-01`;
const todayString = `${yyyy}-${mm}-${dd}`;

// ✅ IMPROVED: Use current year start as default
// This balances between:
// - Including all relevant current-year invoices (for aging analysis)
// - Not loading excessive historical data (better performance)
// Users can manually adjust if needed
const startDate = ref(startOfYear);  // ← Current year start (e.g., 2026-01-01)
const endDate = ref(todayString);
const startDateRef = ref(null);
const endDateRef = ref(null);
const previousFilter = ref({ startDate: startOfYear, endDate: todayString });  // ← fix double fetch

// Tabs definition (computed so names react to type changes)
const tabs = computed(() => [
  { id: 'overview',      name: 'نظرة عامة',                                     icon: 'fas fa-chart-pie' },
  { id: 'transactions',  name: 'الحركات المالية',                                icon: 'fas fa-exchange-alt' },
  { id: 'invoices',      name: type.value === 'customers' ? 'الفواتير' : 'فواتير الشراء', icon: 'fas fa-file-invoice' },
  { id: 'payments',      name: type.value === 'customers' ? 'القبض والتحصيل' : 'المدفوعات للمورد', icon: 'fas fa-wallet' },
]);

// Pagination for transactions (if needed)
const txPage = ref(1);
const txPageSize = ref(20);

// ─── Computed: Overview Totals (from unified composable) ────────────────────

const invoicesCount = computed(() => totals.value.invoicesCount);
const invoicesTotal = computed(() => totals.value.invoicesTotal);
const paymentsTotal = computed(() => totals.value.paymentsTotal);
const returnsTotal = computed(() => totals.value.returnsTotal);
const remainingTotal = computed(() => totals.value.remainingBalance);

// Debug: Watch statement data changes
watch(statementData, (newData) => {
  if (newData) {
    console.log('[ContactDetails] Statement data loaded:', {
      hasSalesOnly: !!newData.sales_only,
      hasReferences: !!newData.references,
      hasTransactions: !!newData.transactions,
      keys: Object.keys(newData),
      salesOnlyCount: newData.sales_only?.items?.length || 0,
      closingBalance: newData.closing_balance
    });
  }
}, { deep: true });

const returnsTotalValue = computed(() => {
  return returns.value.reduce((sum, r) => sum + Number(r.total_amount || 0), 0);
});

const refundsCash = computed(() => {
  return returns.value
    .filter(r => ['refund', 'return_payment'].includes(String(r.type).toLowerCase()))
    .reduce((sum, r) => sum + Number(r.total_amount || 0), 0);
});

const returnsAppliedToInvoices = computed(() => {
  const total = returnsTotalValue.value;
  const cash = refundsCash.value;
  const available = availableCredit.value;
  return Math.max(0, total - cash - available);
});

const overpaymentCredit = computed(() => availableCredit.value);

// ─── Computed: Smart Alerts ──────────────────────────────────────────────

const hasOutstanding = computed(() => {
  const bal = Number(contact.value?.balance ?? 0);
  return type.value === 'customers' ? bal > 0 : bal < 0;
});

const outstandingAmount = computed(() => {
  const bal = Number(contact.value?.balance ?? 0);
  if (type.value === 'customers') {
    return bal > 0 ? Math.abs(bal) : 0;
  } else {
    return bal < 0 ? Math.abs(bal) : 0;
  }
});

const hasCredit = computed(() => {
  const bal = Number(contact.value?.balance ?? 0);
  return type.value === 'customers' ? bal < 0 : bal > 0;
});

const creditAmount = computed(() => {
  const bal = Number(contact.value?.balance ?? 0);
  if (type.value === 'customers') {
    return bal < 0 ? Math.abs(bal) : 0;
  } else {
    return bal > 0 ? Math.abs(bal) : 0;
  }
});

// ─── Computed: Aging Analysis (from unified composable) ──────────────────

const agingBreakdown = computed(() => {
  const analysis = agingAnalysis.value || {};
  const result = {
    current: Number(analysis.current || 0),
    '_1to30': Number(analysis._1to30 || 0),
    '_31to60': Number(analysis._31to60 || 0),
    '_60plus': Number(analysis._60plus || 0)
  };
  console.log('[ContactDetails] Aging Analysis:', { analysis, result, invoicesCount: invoices.value.length });
  return result;
});

// Expose computed properties for template
watch([invoices, payments], () => {}, { deep: true });

// ─── Formatting Helpers ───────────────────────────────────────────────────

const formatDate    = (v) => v ? new Date(v).toLocaleDateString('en-GB') : '-';
const formatDateEn  = (v) => v ? new Date(v).toLocaleDateString('en-GB') : '-';
const formatPrice   = (v) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const formatPriceEn = (v) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const formatNumber  = (v) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(v) || 0);

const statusText = (s) => {
    const map = {
        'paid': 'مدفوعة',
        'settled': 'مسددة',
        'unpaid': 'غير مدفوعة',
        'partial': 'مدفوعة جزئياً',
        'pending_payment': 'آجل',
        'returned': 'مرتجعة',
        'settled_by_return': 'مسددة',
        'closed_by_return': 'مرتجعة',
        'settled_by_credit': 'مسوّاة بمرتجع',
        'settled_mixed': 'مسوّاة نقدي/إشعار دائن',
        'partially_paid': 'مدفوعة جزئياً',
        'rejected': 'مرفوضة',
        'canceled': 'ملغاة',
        'cancelled': 'ملغاة'
    };
    const v = String(s || '').toLowerCase();
    return map[v] || 'غير مدفوعة';
};

const transactionTypeText = (type) => {
    const map = {
        'sale': 'فاتورة بيع',
        'receipt': 'سند قبض',
        'cash_voucher': 'سند نقدي',
        'purchase': 'فاتورة شراء',
        'purchase_return': 'مرتجع شراء',
        'sales_return': 'مرتجع بيع',
        'refund': 'استرجاع',
        'payment': 'سند دفع',
        'journal': 'قيد محاسبي',
        'opening_balance': 'رصيد افتتاحي'
    };
    const v = String(type || '').toLowerCase().trim();
    return map[v] || v;
};

const getInvoiceRemaining = (inv) => {
  try {
    // Prefer explicit remaining_balance if provided by API
    if (inv?.remaining_balance != null && String(inv.remaining_balance).trim() !== '') {
      return Number(inv.remaining_balance) || 0;
    }

    // Fallback to outstanding if provided by statement API
    if (inv?.outstanding != null && String(inv.outstanding).trim() !== '') {
      return Number(inv.outstanding) || 0;
    }

    // Final fallback: compute from totals and paid fields
    const total = Number(inv?.net_total_amount ?? inv?.total_amount ?? inv?.grand_total ?? 0);
    const paid = Number(inv?.actual_paid_amount ?? inv?.paid ?? inv?.paid_amount ?? inv?.amount_paid ?? 0);
    return Math.max(0, total - paid);
  } catch {
    return 0;
  }
};

const invoiceRoute = (inv) => ({
    path: type.value === 'customers'
        ? `/sales/history?id=${inv.id}`
        : `/purchases/history?id=${inv.id}`
});

// Separate receipts from refunds
const receiptPayments = computed(() => payments.value.filter(p => p.type !== 'return_payment'));
const refundPayments  = computed(() => payments.value.filter(p => p.type === 'return_payment'));

// Sort data by date (oldest first) - fixed to match AccountStatement behavior
const sortedInvoices = computed(() => {
    if (!Array.isArray(invoices.value)) return [];
    return [...invoices.value].sort((a, b) => new Date(a?.date || a?.invoice_date || a?.created_at || 0).getTime() - new Date(b?.date || b?.invoice_date || b?.created_at || 0).getTime());
});

const sortedReceiptPayments = computed(() => {
    if (!Array.isArray(receiptPayments.value)) return [];
    return [...receiptPayments.value].sort((a, b) => new Date(a?.payment_date || a?.date || a?.created_at || 0).getTime() - new Date(b?.payment_date || b?.date || b?.created_at || 0).getTime());
});

const sortedRefundPayments = computed(() => {
    if (!Array.isArray(refundPayments.value)) return [];
    return [...refundPayments.value].sort((a, b) => new Date(a?.payment_date || a?.date || a?.created_at || 0).getTime() - new Date(b?.payment_date || b?.date || b?.created_at || 0).getTime());
});

// Full paymentRef with all reference-resolution strategies (from old file)
const paymentRef = (p) => {
    // 0) return_payment → صرف مرتجع label
    if (p?.type === 'return_payment') {
        const rid  = p?.return_id ?? p?.return_sale_id ?? p?.return?.id ?? p?.sales_return_id;
        const sid  = p?.sale_id ?? p?.invoice_id;
        if (rid)  return `صرف مرتجع #${rid}`;
        if (sid)  return `صرف مرتجع فاتورة #${sid}`;
        return `صرف مرتجع #${p?.id ?? '-'}`;
    }

    // 1) Explicit link fields
    const sid  = p?.sale_id ?? p?.invoice_id ?? p?.sale?.id;
    const prid = p?.purchase_id ?? p?.purchase?.id;
    const rid  = p?.return_id ?? p?.return_sale_id ?? p?.return?.id ?? p?.sales_return_id ?? p?.purchase_return_id;
    const cvId = p?.cash_voucher_id ?? p?.voucher_id ?? p?.cash_voucher?.id;

    if (sid)  return `فاتورة بيع #${sid}`;
    if (prid) return `فاتورة شراء #${prid}`;
    if (rid) {
        if (p?.purchase_id || /purchase/i.test(p?.reference || '')) return `مرتجع شراء #${rid}`;
        return `مرتجع بيع #${rid}`;
    }
    if (cvId) return `سند قبض #${cvId}`;

    // 2) Explicit numbers
    if (p?.receipt_number)  return `سند قبض #${p.receipt_number}`;
    if (p?.invoice_number)  return `فاتورة بيع #${p.invoice_number}`;
    if (p?.sale_number)     return `فاتورة بيع #${p.sale_number}`;
    if (p?.purchase_number) return `فاتورة شراء #${p.purchase_number}`;

    // 3) Parse raw reference prefix#id
    const raw = p?.reference || p?.reference_number || '';
    if (raw) {
        const [pref, num] = String(raw).split('#');
        const n   = num || raw;
        const key = String(pref || '').toLowerCase();
        if (key === 'sale' || key === 'sales')                               return `فاتورة بيع #${n}`;
        if (key === 'purchase' || key === 'purchases')                       return `فاتورة شراء #${n}`;
        if (key === 'purchase_return' || key === 'return_purchase')          return `مرتجع شراء #${n}`;
        if (key === 'return' || key === 'sales_return' || key === 'return_sale') return `مرتجع بيع #${n}`;
        if (key === 'cash_voucher')                                          return `سند قبض #${n}`;
        if (key === 'receipt')                                               return `سند قبض #${n}`;
        if (key === 'payment')                                               return `سند دفع #${n}`;
        return `#${n}`;
    }

    // 4) Last resort by id
    if (p?.id) return type.value === 'customers' ? `سند قبض #${p.id}` : `سند دفع #${p.id}`;
    return '-';
};

// Build flexible params covering all backend key variants
const contactParams = computed(() => {
    const pid = id.value;
    return type.value === 'customers'
        ? { contact_id: pid, customer_id: pid, party_id: pid }
        : { contact_id: pid, supplier_id: pid, party_id: pid };
});

// ─── Payload Extraction Helper ────────────────────────────────────────────

const extractContactPayload = (payload) => {
    if (!payload) return null;
    if (payload.id)          return payload;
    if (payload.customer?.id) return payload.customer;
    if (payload.supplier?.id) return payload.supplier;
    if (payload.data)        return extractContactPayload(payload.data);
    return null;
};

// ─── API: Fetch Contact (3-tier failover) ─────────────────────────────────

const fetchContact = async () => {
    isLoading.value = true;
    contact.value = null;

    // Use appropriate store based on contact type
    try {
        if (type.value === 'customers') {
            const response = await customerStore.fetchCustomers({ id: id.value });
            if (response.status === 'success') {
              const customer = customerStore.getCustomerById(id.value);
              if (customer) { 
                contact.value = customer; 
                isLoading.value = false; 
                return; 
              }
            }
        } else if (type.value === 'suppliers') {
            await supplierStore.fetchSuppliers({ id: id.value });
            const supplier = supplierStore.getSupplierById(id.value);
            if (supplier) { 
                contact.value = supplier; 
                isLoading.value = false; 
                return; 
            }
        }
    } catch (eStore) {
        console.error('store fetch failed', eStore);
        showToast('Failed to load contact data', 'error');
    }

    if (!contact.value) {
        showToast('لا توجد بيانات لهذه الجهة. تأكد من صحة الرابط أو الصلاحيات.', 'error');
    }
    isLoading.value = false;
};

// ─── Filter Functions ────────────────────────────────────────────────────────

const applyFilter = async () => {
    if (!startDate.value || !endDate.value) {
        showToast('يجب تحديد فترة زمنية صحيحة', 'warning');
        return;
    }
    
    const result = await fetchStatementData(type.value, id.value, {
        start_date: startDate.value,
        end_date: endDate.value,
        include_references: 1,
        status: 'any',
    });

    if (result.status !== 'success') {
        handleStatementError(result);
    }
};

const setQuickRange = async (kind) => {
    const d = new Date();
    if (kind === 'last7') {
        const s = new Date(d);
        s.setDate(d.getDate() - 6);
        startDate.value = getLocalDateISO(s);
        endDate.value = getLocalDateISO(d);
    } else if (kind === 'thisMonth') {
        // ✅ FIX: استخدم اليوم الحالي بدل آخر يوم من الشهر لتجنب التواريخ المستقبلية
        startDate.value = getLocalDateISO(new Date(d.getFullYear(), d.getMonth(), 1));
        endDate.value = getLocalDateISO(d);  // ← استخدم اليوم الحالي بدل آخر يوم من الشهر
    } else if (kind === 'prevMonth') {
        startDate.value = getLocalDateISO(new Date(d.getFullYear(), d.getMonth() - 1, 1));
        endDate.value = getLocalDateISO(new Date(d.getFullYear(), d.getMonth(), 0));
    } else if (kind === 'thisYear') {
        // ✅ FIX: استخدم اليوم الحالي بدل 31 ديسمبر لتجنب التواريخ المستقبلية
        startDate.value = getLocalDateISO(new Date(d.getFullYear(), 0, 1));
        endDate.value = getLocalDateISO(d);  // ← استخدم اليوم الحالي بدل نهاية السنة
    } else if (kind === 'allTime') {
        startDate.value = '2020-01-01';
        endDate.value = getLocalDateISO(d);
    }
    await applyFilter();
};

// ─── Error Handling ───────────────────────────────────────────────────────

/**
 * معالجة الأخطاء من جلب البيانات
 * يوفر تجربة مستخدم أفضل بناءً على نوع الخطأ
 */
const handleStatementError = (result) => {
    if (!result.error) return;

    const { type, message, retryable, showRetry } = result.error;

    if (type === 'session_expired') {
        showToast('انتهت جلستك. يرجى تسجيل الدخول من جديد.', 'error');
        setTimeout(() => router.push('/login'), 1500);
        return;
    }

    if (type === 'permission_denied') {
        showToast('ليس لديك صلاحية لعرض هذه البيانات.', 'error');
        return;
    }

    if (type === 'not_found') {
        showToast('البيانات المطلوبة غير موجودة.', 'error');
        return;
    }

    showToast(message || 'فشل تحميل البيانات', 'error');
};

// ─── Navigation ───────────────────────────────────────────────────────────

const goBack = () => {
    try {
        if (type.value === 'customers') { router.push({ name: 'CustomersManagement' }); return; }
        if (type.value === 'suppliers') { router.push({ name: 'SuppliersManagement' }); return; }
    } catch (_) { /* ignore, fallback below */ }
    router.back();
};

// ─── Lifecycle ────────────────────────────────────────────────────────────

onMounted(async () => {
    // Check RBAC: User must have permission to view statements
    if (!canViewStatement.value) {
        showToast('ليس لديك صلاحية لعرض كشوفات الحسابات', 'error');
        logDenial('view_statement', type.value, id.value, 'insufficient_permission');
        router.back();
        return;
    }

    // Log access for audit trail
    logAccess('view_statement', type.value, id.value);

    // Prefill from sessionStorage for instant UI
    try {
        const raw = sessionStorage.getItem('selectedContact');
        if (raw) { const cached = JSON.parse(raw); if (cached?.id) contact.value = cached; }
    } catch (_) {}

    // Load all data: settings and contact info first, then all unified data from statement API
    await Promise.all([fetchSettings(), fetchContact()]);
    
    // Single unified call replaces fetchInvoices + fetchPayments + fetchTransactions
    const result = await fetchStatementData(type.value, id.value, {
        start_date: startDate.value,
        end_date: endDate.value,
        include_references: 1,
        status: 'any',
    });

    if (result.status !== 'success') {
        handleStatementError(result);
    }
});

// Re-fetch everything when route params change (navigation between contacts)
watch(() => route.params, async (nv) => {
    type.value = nv.type;
    id.value = Number(nv.id);
    await fetchContact();
    
    const result = await fetchStatementData(type.value, id.value, {
        start_date: startDate.value,
        end_date: endDate.value,
        include_references: 1,
        status: 'any',
    });

    if (result.status !== 'success') {
        handleStatementError(result);
    }
});

// ─── Combined Watcher: Pagination + Filter ─────────────────────────────────
// مراقبة موحدة لتجنب Double Fetch
// عند تغيير الصفحة → Fetch بدون reset
// عند تغيير التصفية → Reset الصفحة ثم Fetch

watch([page, perPage, startDate, endDate], async () => {
    // اكتشف هل تغيّر الفلتر
    const filterChanged = 
        previousFilter.value.startDate !== startDate.value || 
        previousFilter.value.endDate !== endDate.value;
    
    if (filterChanged) {
        page.value = 1;  // Reset to page 1 only on filter change
        previousFilter.value = { startDate: startDate.value, endDate: endDate.value };
    }
    
    // Fetch once with all parameters
    const result = await fetchStatementData(type.value, id.value, {
        start_date: startDate.value,
        end_date: endDate.value,
        include_references: 1,
        status: 'any',
        page: page.value,
        per_page: perPage.value
    });

    if (result.status !== 'success') {
        handleStatementError(result);
    }
}, { deep: true });

// Reload the relevant data when tab changes
watch(activeTab, async (tab) => {
    // For tab changes, we don't need to refetch as all data is already loaded from fetchStatementData
    // But if needed for specific tabs, you can conditionally refetch here
});
</script>

<style scoped>



/* ── Overview Cards ── */
.overview-box {
    background: white;
    padding: 1.75rem;
    border-radius: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.overview-box:hover {
    box-shadow: 0 20px 25px rgba(0,0,0,0.1);
    transform: translateY(-4px);
}

/* ── Filter Styles ── */
.filter-label {
    font-size: 0.625rem;
    font-weight: 900;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.form-input-modern {
    width: 100%;
    padding: 0.625rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid #cbd5e1;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.form-input-modern:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-select-modern {
    width: 100%;
    padding: 0.625rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid #cbd5e1;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.form-select-modern:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.quick-range-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.625rem;
    font-weight: 900;
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
    transition: all 0.2s ease;
}

.quick-range-btn:hover {
    border-color: #93c5fd;
    background-color: #eff6ff;
}

/* ── Tab Styles ── */
.tab-pill {
    padding: 0.375rem 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.625rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
}

.tab-pill:active {
    transform: scale(0.95);
}

.tab-pill:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    opacity: 0.9;
}

/* ── KPI Cards ── */
.kpi-box {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
    flex: 1;
}

.kpi-label-modern {
    font-size: 0.5625rem;
    font-weight: 900;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.kpi-val-modern {
    font-size: 1.5rem;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: -0.02em;
}

/* ── Quick Action Buttons ── */
.quick-action-btn {
    @apply px-3 py-1.5 rounded-xl text-[10px] font-black flex items-center gap-1.5 transition-all active:scale-95 hover:shadow-md hover:opacity-90;
}

/* ── Tab Pills ── */
.tab-pill {
    @apply px-8 py-3 rounded-xl text-xs font-black transition-all flex items-center gap-3 active:scale-95;
}

/* ── Pagination ── */
.page-btn {
    @apply w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center hover:bg-slate-50 disabled:opacity-30 transition-all;
}

/* ── Sticky headers ── */
table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.06);
}

/* ── Custom Scrollbar ── */
.custom-scroll::-webkit-scrollbar       { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* ── Fade Transition ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from,  .fade-leave-to     { opacity: 0; }

/* ── Animation ── */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Print ── */
@media print {
    .animate-fadeIn, .sticky, .page-btn, .tab-pill { display: none !important; }
    .bg-\[#f8fafc\] { background: white !important; }
}
</style>