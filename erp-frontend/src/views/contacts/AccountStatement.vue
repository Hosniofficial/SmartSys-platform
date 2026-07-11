<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">

    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-file-invoice text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">{{ title }}</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل مالي شامل للحركات والأرصدة</p>
        </div>
      </div>

      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="backToContact" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-100">
          <i class="fas fa-arrow-right"></i> رجوع
        </button>
        <button @click="exportPdf" :disabled="!data" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-blue-600 shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fas fa-print"></i> طباعة / PDF
        </button>
      </div>
    </div>

    <!-- Collapsible Filters Area -->
    <details class="bg-white rounded-[2rem] shadow-sm border border-slate-100 mb-8 overflow-hidden group">
      <summary class="px-8 py-5 font-black text-sm text-slate-800 cursor-pointer flex items-center justify-between hover:bg-slate-50/50 transition-colors list-none">
        <div class="flex items-center gap-3">
          <i class="fas fa-filter text-blue-500"></i>
          <span>الفلاتر وخيارات العرض المتقدمة</span>
        </div>
        <i class="fas fa-chevron-down text-slate-300 group-open:rotate-180 transition-transform"></i>
      </summary>

      <div class="px-8 pb-8 pt-4 space-y-6 border-t border-slate-50">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="space-y-2">
            <label class="filter-label">من تاريخ</label>
            <div class="relative">
              <input ref="startDateRef" type="date" v-model="startDate" @change="applyFilter" class="form-input-modern font-bold" />
              <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="startDateRef.showPicker()"></i>
            </div>
          </div>
          <div class="space-y-2">
            <label class="filter-label">إلى تاريخ</label>
            <div class="relative">
              <input ref="endDateRef" type="date" v-model="endDate" @change="applyFilter" class="form-input-modern font-bold" />
              <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="endDateRef.showPicker()"></i>
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="filter-label">نطاقات زمنية سريعة</label>
            <div class="flex flex-wrap gap-2">
              <button @click="setQuickRange('last7')" class="quick-range-btn">آخر 7 أيام</button>
              <button @click="setQuickRange('thisMonth')" class="quick-range-btn">هذا الشهر</button>
              <button @click="setQuickRange('prevMonth')" class="quick-range-btn">الشهر السابق</button>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <div class="lg:col-span-2 space-y-2">
            <label class="filter-label">بحث سريع</label>
            <div class="flex items-center gap-3 bg-white p-4 rounded-[1.5rem] border border-slate-200 shadow-sm">
              <i class="fas fa-search text-slate-400"></i>
              <input v-model="searchText" type="text" placeholder="ابحث عن رقم فاتورة أو مبلغ أو وصف..." class="flex-1 outline-none text-sm bg-white placeholder-slate-400" />
              <button v-if="searchText" @click="searchText = ''" class="text-slate-400 hover:text-slate-600 text-xs"><i class="fas fa-times"></i></button>
            </div>
          </div>
          <div class="space-y-2">
            <label class="filter-label">نوع العملية</label>
            <select v-model="typeFilter" @change="applyFilter" class="form-select-modern font-bold">
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="filter-label">حالة الدفع</label>
            <select v-model="statusFilter" @change="applyFilter" class="form-select-modern font-bold">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="filter-label">طريقة الدفع</label>
            <select v-model="paymentMethodId" @change="applyFilter" class="form-select-modern font-bold">
              <option value="">الكل</option>
              <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="filter-label">الفرع / المخزن</label>
            <select v-model="selectedBranch" @change="applyFilter" class="form-select-modern font-bold">
              <option value="">الكل</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name || w.branch_name }}</option>
            </select>
          </div>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-50">
          <div class="flex items-center gap-6">
            <label class="inline-flex items-center gap-2 cursor-pointer group">
              <input type="checkbox" v-model="fillGaps" @change="applyFilter" class="w-5 h-5 rounded-lg border-slate-200 text-blue-600 focus:ring-blue-100 transition-all">
              <span class="text-xs font-black text-slate-500 group-hover:text-slate-700 transition-colors">عرض كل الأيام (سد الفجوات)</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer group">
              <input type="checkbox" v-model="onlyNonZero" @change="applyFilter" class="w-5 h-5 rounded-lg border-slate-200 text-blue-600 focus:ring-blue-100 transition-all">
              <span class="text-xs font-black text-slate-500 group-hover:text-slate-700 transition-colors">إخفاء الأيام الصفرية</span>
            </label>
          </div>
          <button @click="exportCsv" class="px-6 py-2.5 rounded-xl border-2 border-emerald-100 text-emerald-600 text-xs font-black hover:bg-emerald-50 transition-all flex items-center gap-2">
            <i class="fas fa-file-excel"></i> تصدير سجل CSV
          </button>
        </div>
      </div>
    </details>

    <!-- Loading State -->
    <div v-if="statementLoading" class="py-32 text-center">
      <BaseSpinner :size="40" color="#2563eb" />
      <p class="text-xs font-black text-slate-400 uppercase tracking-widest mt-4 animate-pulse">جاري جلب وتحليل الحركات المالية...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data" class="py-32 text-center bg-white rounded-[3rem] shadow-sm border border-slate-100 px-8">
      <div class="w-20 h-20 bg-slate-50 text-slate-200 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-search-dollar text-4xl"></i>
      </div>
      <p class="text-slate-500 font-black text-lg uppercase tracking-widest leading-relaxed">لا توجد بيانات متاحة للعرض حالياً</p>
      <p class="text-slate-400 text-sm mt-2">يرجى تعديل الفلاتر أو تحديد نطاق زمني مختلف</p>
    </div>

    <!-- Main Report Body -->
    <div v-else class="report-main-container space-y-8">

      <!-- Report Professional Header -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-32 h-32 bg-blue-50/50 rounded-full -translate-x-16 -translate-y-16"></div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start gap-8">
          <div class="space-y-4 max-w-sm">
            <div class="flex items-center gap-4">
              <div v-if="companyInfo.logo" class="w-16 h-16 rounded-2xl border border-slate-100 bg-white p-2 shadow-sm flex items-center justify-center">
                <img :src="companyInfo.logo" alt="logo" class="max-h-full object-contain" />
              </div>
              <h2 class="text-2xl font-black text-slate-900 leading-none">{{ companyInfo.name || 'الشركة' }}</h2>
            </div>
            <div class="text-xs font-bold text-slate-400 space-y-1.5 leading-relaxed">
              <p v-if="companyInfo.address"><i class="fas fa-map-marker-alt text-blue-500 ml-2"></i> {{ companyInfo.address }}</p>
              <p v-if="companyInfo.phone"><i class="fas fa-phone-alt text-blue-500 ml-2 text-[10px]"></i> {{ companyInfo.phone }}</p>
              <p v-if="companyInfo.email"><i class="fas fa-envelope text-blue-500 ml-2 text-[10px]"></i> {{ companyInfo.email }}</p>
            </div>
          </div>

          <div class="bg-slate-900 p-8 rounded-[2rem] text-white min-w-[280px] shadow-2xl shadow-slate-900/20 text-left">
            <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-4 text-right">كشف حساب موجه لـ</p>
            <h3 class="text-xl font-black mb-2">{{ partyInfo.name || data.account?.name }}</h3>
            <div class="space-y-1 text-xs font-bold text-white/50">
              <p v-if="partyInfo.phone">{{ partyInfo.phone }}</p>
              <p v-if="partyInfo.address">{{ partyInfo.address }}</p>
              <p v-if="partyInfo.email">{{ partyInfo.email }}</p>
              <p class="pt-3 mt-3 border-t border-white/5 text-[10px] uppercase font-black tracking-widest text-blue-400 text-right">رقم الحساب: {{ data.account?.code }}</p>
            </div>
          </div>
        </div>

        <!-- Period & Stats Banner -->
        <div class="mt-10 pt-10 border-t border-slate-50 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shadow-sm shrink-0"><i class="far fa-calendar-alt"></i></div>
            <div>
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">فترة التقرير</p>
              <p class="text-sm font-black text-slate-800 leading-none mt-1">{{ startDate }} ↔ {{ endDate }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shadow-sm shrink-0"><i class="fas fa-clock-rotate-left"></i></div>
            <div>
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">تاريخ الاستخراج</p>
              <p class="text-sm font-black text-slate-800 leading-none mt-1">{{ formatDateEn(new Date()) }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shadow-sm shrink-0"><i class="fas fa-check-double"></i></div>
            <div>
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">حالة الحساب</p>
              <p class="text-sm font-black text-emerald-600 leading-none mt-1 uppercase tracking-tight">نشط</p>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Summary Area -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 bg-white py-6 rounded-2xl border border-slate-100 shadow-sm">
        <div class="kpi-box border-l-4 border-l-slate-400">
          <p class="kpi-label-modern">الرصيد الافتتاحي</p>
          <p class="kpi-val-modern">{{ formatPriceEn(data.opening_balance) }} <span class="text-xs text-slate-300 font-bold ml-1">{{ currencySymbol }}</span></p>
        </div>
        <div class="kpi-box border-l-4 border-l-rose-500">
          <p class="kpi-label-modern text-rose-500">إجمالي الحركات المدينة</p>
          <p class="kpi-val-modern text-rose-600">{{ formatPriceEn(data.total_debit) }} <span class="text-xs text-rose-300 font-bold ml-1">{{ currencySymbol }}</span></p>
        </div>
        <div class="kpi-box border-l-4 border-l-emerald-500">
          <p class="kpi-label-modern text-emerald-500">إجمالي الحركات الدائنة</p>
          <p class="kpi-val-modern text-emerald-600">{{ formatPriceEn(data.total_credit) }} <span class="text-xs text-emerald-300 font-bold ml-1">{{ currencySymbol }}</span></p>
        </div>
        <div class="kpi-box border-l-4 border-l-blue-500">
          <p class="kpi-label-modern">الرصيد الختامي</p>
          <p class="kpi-val-modern text-blue-700">{{ formatPriceEn(data.closing_balance) }} <span class="text-xs text-blue-300 font-bold ml-1">{{ currencySymbol }}</span></p>
        </div>
      </div>

      <!-- Navigation Tabs - Professional Cards Grid -->
      <div class="space-y-3">
        <div class="text-[11px] font-black text-slate-500 uppercase tracking-widest px-2">اختر عرض التقرير:</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          
          <!-- Accounting Tab Card -->
          <button @click="activeTab = 'accounting'" :class="activeTab === 'accounting' ? 'tab-card-active-accounting' : 'tab-card'" class="group">
            <div class="flex items-start gap-3">
              <div :class="activeTab === 'accounting' ? 'tab-icon-active' : 'tab-icon'">
                <i class="fas fa-list-ul text-lg"></i>
              </div>
              <div class="text-right flex-1">
                <h3 class="font-black text-sm text-slate-900 group-hover:text-blue-600 transition-colors">تفاصيل الحركات</h3>
                <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">كل القيود المحاسبية مع الرصيد المتراكم</p>
              </div>
              <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="activeTab === 'accounting' ? 'border-blue-600 bg-blue-50' : 'border-slate-200 group-hover:border-blue-300'">
                <i v-if="activeTab === 'accounting'" class="fas fa-check text-xs text-blue-600"></i>
              </div>
            </div>
          </button>

          <!-- Daily Tab Card -->
          <button @click="activeTab = 'daily'" :class="activeTab === 'daily' ? 'tab-card-active-daily' : 'tab-card'" class="group">
            <div class="flex items-start gap-3">
              <div :class="activeTab === 'daily' ? 'tab-icon-active' : 'tab-icon'">
                <i class="fas fa-calendar-alt text-lg"></i>
              </div>
              <div class="text-right flex-1">
                <h3 class="font-black text-sm text-slate-900 group-hover:text-emerald-600 transition-colors">الملخص اليومي</h3>
                <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">تجميع الحركات اليومية والأرصدة الختامية</p>
              </div>
              <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="activeTab === 'daily' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 group-hover:border-emerald-300'">
                <i v-if="activeTab === 'daily'" class="fas fa-check text-xs text-emerald-600"></i>
              </div>
            </div>
          </button>

          <!-- References Tab Card -->
          <button @click="activeTab = 'references'" :class="activeTab === 'references' ? 'tab-card-active-references' : 'tab-card'" class="group">
            <div class="flex items-start gap-3">
              <div :class="activeTab === 'references' ? 'tab-icon-active' : 'tab-icon'">
                <i class="fas fa-project-diagram text-lg"></i>
              </div>
              <div class="text-right flex-1">
                <h3 class="font-black text-sm text-slate-900 group-hover:text-purple-600 transition-colors">الفواتير والسندات</h3>
                <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">سجل الفواتير والعمليات المرجعية</p>
              </div>
              <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="activeTab === 'references' ? 'border-purple-600 bg-purple-50' : 'border-slate-200 group-hover:border-purple-300'">
                <i v-if="activeTab === 'references'" class="fas fa-check text-xs text-purple-600"></i>
              </div>
            </div>
          </button>
          
        </div>
      </div>

      <!-- Overdue Alert Banner (Customers Only) -->
      <transition name="fade">
        <div v-if="hasOverdueAlert" class="bg-rose-50 border-2 border-rose-200 rounded-[2rem] p-6 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-rose-600 shadow-sm shrink-0">
            <i class="fas fa-exclamation-triangle text-xl"></i>
          </div>
          <div class="flex-1">
            <p class="font-black text-slate-900 mb-1">⚠️ تنبيه: فواتير متأخرة</p>
            <p class="text-sm text-slate-600">يوجد <strong>{{ overdueInvoices.length }}</strong> فاتورة متأخرة أكثر من 30 يوم بإجمالي <strong>{{ formatPriceEn(overdueInvoices.reduce((s, it) => s + Number(it.outstanding || it.net_total_amount - (it.paid_amount || 0)), 0)) }} {{ currencySymbol }}</strong></p>
          </div>
          <button @click="activeTab = 'references'; referencesSubTab = 'invoices'" class="px-6 py-2 rounded-xl bg-rose-600 text-white font-black text-xs hover:bg-rose-700 transition-all shrink-0">
            عرض الفواتير
          </button>
        </div>
      </transition>

      <!-- Tab Content Area -->
      <div class="min-h-[500px]">

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Accounting (تفاصيل الحركات المحاسبية)
             ═══════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'accounting'" class="space-y-8">

          <!-- View Modes Selection - Professional Grid -->
          <div class="space-y-3">
            <div class="text-[11px] font-black text-slate-500 uppercase tracking-widest px-2">اختر نوع العرض:</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              
              <!-- Detailed Mode Card -->
              <button @click="viewMode = 'detailed'; applyFilter();" :class="viewMode === 'detailed' ? 'vmode-card-active' : 'vmode-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="viewMode === 'detailed' ? 'vmode-icon-active' : 'vmode-icon'">
                    <i class="fas fa-list-ul text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-blue-600 transition-colors">تفصيلي</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">كافة القيود المحاسبية سطر بسطر مع الرصيد المتراكم</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="viewMode === 'detailed' ? 'border-blue-600 bg-blue-50' : 'border-slate-200 group-hover:border-blue-300'">
                    <i v-if="viewMode === 'detailed'" class="fas fa-check text-xs text-blue-600"></i>
                  </div>
                </div>
              </button>

              <!-- Daily Mode Card -->
              <button @click="viewMode = 'daily'; applyFilter();" :class="viewMode === 'daily' ? 'vmode-card-active' : 'vmode-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="viewMode === 'daily' ? 'vmode-icon-active' : 'vmode-icon'">
                    <i class="fas fa-calendar-alt text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-emerald-600 transition-colors">يومي مجمع</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">تجميع حركات اليوم الواحد في قيد مجمع</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="viewMode === 'daily' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 group-hover:border-emerald-300'">
                    <i v-if="viewMode === 'daily'" class="fas fa-check text-xs text-emerald-600"></i>
                  </div>
                </div>
              </button>

              <!-- By Type Mode Card -->
              <button @click="viewMode = 'by_type'; applyFilter();" :class="viewMode === 'by_type' ? 'vmode-card-active' : 'vmode-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="viewMode === 'by_type' ? 'vmode-icon-active' : 'vmode-icon'">
                    <i class="fas fa-layer-group text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-purple-600 transition-colors">حسب النوع</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">تجميع الحركات حسب نوع المستند</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="viewMode === 'by_type' ? 'border-purple-600 bg-purple-50' : 'border-slate-200 group-hover:border-purple-300'">
                    <i v-if="viewMode === 'by_type'" class="fas fa-check text-xs text-purple-600"></i>
                  </div>
                </div>
              </button>
              
            </div>
          </div>

          <!-- Detailed Transactions Table -->
          <div v-if="viewMode === 'detailed'" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-right text-sm">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                    <th class="px-6 py-5">التاريخ</th>
                    <th class="px-4 py-5">المستند</th>
                    <th class="px-4 py-5">المرجع</th>
                    <th class="px-4 py-5">نوع الحركة</th>
                    <th class="px-6 py-5">البيان / الوصف</th>
                    <th class="px-4 py-5">مدين (+)</th>
                    <th class="px-4 py-5">دائن (-)</th>
                    <th class="px-6 py-5">الرصيد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="!data.transactions || data.transactions.length === 0">
                    <td colspan="8" class="text-center py-12 text-slate-400 font-bold">
                      لا توجد حركات ضمن الفترة المحددة. تأكد من صحة التواريخ أو نوع الحساب.
                    </td>
                  </tr>
                  <tr v-for="(t, idx) in pagedTransactions" :key="idx" class="hover:bg-blue-50/20 transition-all" :class="rowClass(t)">
                    <td class="px-6 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ formatDateEn(t.date) }}</td>
                    <td class="px-4 py-4">
                      <RouterLink v-if="invoiceLink(t)" :to="invoiceLink(t)" class="font-black text-blue-600 hover:underline">
                        {{ t.invoice_number || ('#' + (t.id || refNumber(t.reference))) }}
                      </RouterLink>
                      <span v-else class="font-black text-slate-700">{{ t.invoice_number || refNumber(t.reference) || '-' }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-black text-slate-800 leading-none">{{ t.reference || '-' }}</div>
                      <div class="text-[9px] text-slate-400 mt-1 uppercase font-black tracking-widest">{{ t.reference_label || refTypeLabel(t.reference) }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="['status-badge', badgeClass(t.type || t.transaction_type || t.reference_type || (t.reference || '').split('#')[0])]">
                        {{ getTransactionTypeLabel(t.type || t.transaction_type || t.reference_type || (t.reference || '').split('#')[0]) || 'حركة' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-500 max-w-xs">
                      <div class="truncate" :title="t.description">{{ t.description || '-' }}</div>
                      <div v-if="getAllocatedInvoice(t)" class="text-[9px] mt-1.5 px-1.5 py-0.5 bg-cyan-50 text-cyan-700 rounded font-black tracking-tight w-fit">
                        ← تسوية: {{ getAllocatedInvoice(t) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 text-rose-600 font-black font-mono tracking-tighter">{{ t.debit > 0 ? formatPriceEn(t.debit) : '—' }}</td>
                    <td class="px-4 py-4 text-emerald-600 font-black font-mono tracking-tighter">{{ t.credit > 0 ? formatPriceEn(t.credit) : '—' }}</td>
                    <td class="px-6 py-4 font-black font-mono tracking-tighter text-base" :class="getBalanceColorClass(t)">
                      <span class="flex items-center gap-1">
                        <i v-if="String(t.balance_nature || 'zero').toLowerCase() === 'debit'" class="fas fa-arrow-up text-blue-600 text-[10px]"></i>
                        <i v-else-if="String(t.balance_nature || 'zero').toLowerCase() === 'credit'" class="fas fa-arrow-down text-amber-600 text-[10px]"></i>
                        {{ getBalanceWithNature(t) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50/80 font-black">
                  <tr class="border-t-2 border-slate-100">
                    <td colspan="5" class="px-6 py-4 text-xs text-slate-400 uppercase tracking-widest" title="شامل: فواتير + دفعات + مردودات + تسويات">✓ إجمالي حركات دفتر اليومية</td>
                    <td class="px-4 py-4 text-rose-600 font-mono">{{ formatPriceEn(pageTotals.debit) }}</td>
                    <td class="px-4 py-4 text-emerald-600 font-mono">{{ formatPriceEn(pageTotals.credit) }}</td>
                    <td class="px-6 py-4 text-slate-900 font-mono text-base">{{ formatPriceEn(pageTotals.closing || 0) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Pagination Controls -->
            <div class="px-8 py-5 bg-slate-50/50 flex items-center justify-between border-t border-slate-50">
              <div class="flex items-center gap-3">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                  عرض {{ pagedTransactions.length }} من <strong>{{ visibleTransactions.length }}</strong> حركة
                  <span v-if="searchText" class="ml-2 text-blue-600">(البحث: "{{ searchText }}")</span>
                </span>
                <select v-model.number="txPerPage" class="form-select-modern w-20 text-xs" @change="txPage = 1; applyFilter()">
                  <option :value="10">10</option>
                  <option :value="20">20</option>
                  <option :value="50">50</option>
                </select>
              </div>
              <div class="flex items-center gap-1">
                <button @click="txPage=Math.max(1, txPage-1); applyFilter();" :disabled="txPage<=1" class="page-btn">
                  <i class="fas fa-angle-right"></i>
                </button>
                <div class="px-4 py-2 bg-white rounded-xl border border-slate-100 text-xs font-black">صفحة {{ txPage }} / {{ txPages }}</div>
                <button @click="txPage=Math.min(txPages, txPage+1); applyFilter();" :disabled="txPage>=txPages" class="page-btn">
                  <i class="fas fa-angle-left"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Daily Summary Table -->
          <div v-else-if="viewMode === 'daily'" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-right text-sm">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 font-black uppercase border-b border-slate-100">
                    <th class="px-8 py-5">تاريخ الحركة</th>
                    <th class="px-4 py-5">رصيد الافتتاح</th>
                    <th class="px-4 py-5 text-rose-500">إجمالي مدين (+)</th>
                    <th class="px-4 py-5 text-emerald-500">إجمالي دائن (-)</th>
                    <th class="px-4 py-5">رصيد الإغلاق</th>
                    <th class="px-8 py-5 text-center">عدد العمليات</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="!dailyBalances.length">
                    <td colspan="6" class="text-center py-12 text-slate-400 font-bold">لا توجد حركات يومية ضمن الفترة المحددة.</td>
                  </tr>
                  <tr v-for="d in dailyBalances" :key="d.date" class="hover:bg-slate-50 transition-all font-bold">
                    <td class="px-8 py-4 text-slate-900 font-black font-mono tracking-tighter">{{ formatDateEn(d.date) }}</td>
                    <td class="px-4 py-4 text-slate-400 font-mono">{{ formatPriceEn(d.opening_balance) }}</td>
                    <td class="px-4 py-4 text-rose-600 font-mono tracking-tighter">{{ formatPriceEn(d.day_debit) }}</td>
                    <td class="px-4 py-4 text-emerald-600 font-mono tracking-tighter">{{ formatPriceEn(d.day_credit) }}</td>
                    <td class="px-4 py-4 font-black font-mono text-base" :class="d.closing_balance === 0 ? 'text-slate-500' : (d.closing_balance < 0 ? 'text-emerald-700' : 'text-rose-700')">{{ formatPriceEn(d.closing_balance) }}</td>
                    <td class="px-8 py-4 text-center">
                      <span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-lg text-[10px] font-black">{{ new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(d.transaction_count || 0)) }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Grouped by Type Table -->
          <div v-else-if="viewMode === 'by_type'" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-right text-sm">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 font-black uppercase border-b border-slate-100">
                    <th class="px-8 py-5">نوع المستند</th>
                    <th class="px-8 py-5 text-center">عدد السجلات</th>
                    <th class="px-4 py-5">إجمالي مدين (+)</th>
                    <th class="px-4 py-5">إجمالي دائن (-)</th>
                    <th class="px-4 py-5">صافي الحركة</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="!groupedByType.length">
                    <td colspan="5" class="text-center py-12 text-slate-400 font-bold">لا توجد حركات ضمن الفترة المحددة.</td>
                  </tr>
                  <tr v-for="g in groupedByType" :key="g.type" class="hover:bg-slate-50 transition-all">
                    <td class="px-8 py-4 font-black text-slate-800">{{ g.label }}</td>
                    <td class="px-8 py-4 text-center">
                      <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-xs font-black">{{ new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(g.count || 0)) }}</span>
                    </td>
                    <td class="px-4 py-4 text-rose-600 font-black font-mono">{{ formatPriceEn(g.debit) }}</td>
                    <td class="px-4 py-4 text-emerald-600 font-black font-mono">{{ formatPriceEn(g.credit) }}</td>
                    <td class="px-4 py-4 font-black font-mono text-base">{{ formatPriceEn((g.debit || 0) - (g.credit || 0)) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Aging Summary (Customers Only) -->
          <div v-if="isCustomer && data.aging_summary" class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-6 flex items-center gap-2">
              <i class="fas fa-history text-amber-500"></i> تحليل أعمار الديون (Aging Report)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
              <div class="aging-card bg-emerald-50 border-emerald-100 text-emerald-700">
                <p class="text-[9px] font-black uppercase tracking-widest mb-1">حالي (Current)</p>
                <p class="text-xl font-black">{{ formatPrice(data.aging_summary.current) }} <span class="text-[10px] font-bold opacity-50">{{ currencySymbol }}</span></p>
                <span class="text-[9px] font-bold opacity-60">({{ new Intl.NumberFormat('en-US').format(agingCounts.current || 0) }} فواتير)</span>
              </div>
              <div class="aging-card bg-amber-50 border-amber-100 text-amber-700">
                <p class="text-[9px] font-black uppercase tracking-widest mb-1">1-30 يوم</p>
                <p class="text-xl font-black">{{ formatPrice(data.aging_summary['1_30_days']) }} <span class="text-[10px] font-bold opacity-50">{{ currencySymbol }}</span></p>
                <span class="text-[9px] font-bold opacity-60">({{ new Intl.NumberFormat('en-US').format(agingCounts['1_30_days'] || 0) }} فواتير)</span>
              </div>
              <div class="aging-card bg-orange-50 border-orange-100 text-orange-700">
                <p class="text-[9px] font-black uppercase tracking-widest mb-1">31-60 يوم</p>
                <p class="text-xl font-black">{{ formatPrice(data.aging_summary['31_60_days']) }} <span class="text-[10px] font-bold opacity-50">{{ currencySymbol }}</span></p>
                <span class="text-[9px] font-bold opacity-60">({{ new Intl.NumberFormat('en-US').format(agingCounts['31_60_days'] || 0) }} فواتير)</span>
              </div>
              <div class="aging-card bg-rose-50 border-rose-100 text-rose-700">
                <p class="text-[9px] font-black uppercase tracking-widest mb-1">+60 يوم</p>
                <p class="text-xl font-black">{{ formatPrice(data.aging_summary['60_plus_days']) }} <span class="text-[10px] font-bold opacity-50">{{ currencySymbol }}</span></p>
                <span class="text-[9px] font-bold opacity-60">({{ new Intl.NumberFormat('en-US').format(agingCounts['60_plus_days'] || 0) }} فواتير)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: Daily Balances (الملخص اليومي)
             ═══════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'daily'" class="space-y-6">

          <!-- Daily Balances Table -->
          <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex items-center justify-between">
              <h3 class="font-black text-slate-800">الملخص اليومي</h3>
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ dailyBalances.length }} يوم عمل</span>
            </div>
          <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
              <thead class="bg-slate-50/50 text-slate-500 font-black uppercase border-b border-slate-50">
                <tr>
                  <th class="px-8 py-5">التاريخ</th>
                  <th class="px-4 py-5 text-slate-400">الرصيد السابق</th>
                  <th class="px-4 py-5 text-rose-500">مدين (+)</th>
                  <th class="px-4 py-5 text-emerald-500">دائن (-)</th>
                  <th class="px-4 py-5">صافي اليوم</th>
                  <th class="px-8 py-5 text-center">الرصيد الختامي</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-if="!dailyBalances.length">
                  <td colspan="6" class="text-center py-12 text-slate-400 font-bold">لا توجد حركات يومية ضمن الفترة المحددة.</td>
                </tr>
                <tr v-for="d in dailyBalances" :key="d.date" class="hover:bg-slate-50/50 transition-colors font-bold">
                  <td class="px-8 py-4 text-slate-900 font-black font-mono">{{ formatDateEn(d.date) }}</td>
                  <td class="px-4 py-4 text-slate-300 font-mono">{{ formatPriceEn(d.opening_balance) }}</td>
                  <td class="px-4 py-4 text-rose-600 font-mono">{{ formatPriceEn(d.day_debit) }}</td>
                  <td class="px-4 py-4 text-emerald-600 font-mono">{{ formatPriceEn(d.day_credit) }}</td>
                  <td class="px-4 py-4 text-slate-600 font-mono">{{ formatPriceEn(d.day_debit - d.day_credit) }}</td>
                  <td class="px-8 py-4 text-center font-black font-mono text-base" :class="d.closing_balance === 0 ? 'text-slate-500' : (d.closing_balance < 0 ? 'text-emerald-700' : 'text-rose-700')">{{ formatPriceEn(d.closing_balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             TAB: References (سجل المراجع - فواتير وسندات)
             ═══════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'references'" class="space-y-6">

          <!-- Sub-tab toggle (Professional Grid) -->
          <div class="space-y-3">
            <div class="text-[11px] font-black text-slate-500 uppercase tracking-widest px-2">اختر نوع الحركة:</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              
              <!-- Invoices Card -->
              <button @click="referencesSubTab = 'invoices'" :class="referencesSubTab === 'invoices' ? 'subtab-card-active-invoices' : 'subtab-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="referencesSubTab === 'invoices' ? 'subtab-icon-active' : 'subtab-icon'">
                    <i class="fas fa-file-invoice-dollar text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-blue-600 transition-colors">{{ isCustomer ? 'فواتير العميل' : 'فواتير المورد' }}</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">سجل كامل الفواتير والعمليات البيعية/الشرائية</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="referencesSubTab === 'invoices' ? 'border-blue-600 bg-blue-50' : 'border-slate-200 group-hover:border-blue-300'">
                    <i v-if="referencesSubTab === 'invoices'" class="fas fa-check text-xs text-blue-600"></i>
                  </div>
                </div>
              </button>

              <!-- Vouchers Card -->
              <button @click="referencesSubTab = 'vouchers'" :class="referencesSubTab === 'vouchers' ? 'subtab-card-active-vouchers' : 'subtab-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="referencesSubTab === 'vouchers' ? 'subtab-icon-active' : 'subtab-icon'">
                    <i class="fas fa-list-check text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-emerald-600 transition-colors">السندات المرجعية</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">كل الحركات المحاسبية والعمليات الداخلية</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="referencesSubTab === 'vouchers' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 group-hover:border-emerald-300'">
                    <i v-if="referencesSubTab === 'vouchers'" class="fas fa-check text-xs text-emerald-600"></i>
                  </div>
                </div>
              </button>

              <!-- Payments Card -->
              <button @click="referencesSubTab = 'payments'" :class="referencesSubTab === 'payments' ? 'subtab-card-active-payments' : 'subtab-card'" class="group">
                <div class="flex items-start gap-3">
                  <div :class="referencesSubTab === 'payments' ? 'subtab-icon-active' : 'subtab-icon'">
                    <i class="fas fa-hand-holding-usd text-lg"></i>
                  </div>
                  <div class="text-right flex-1">
                    <h3 class="font-black text-sm text-slate-900 group-hover:text-purple-600 transition-colors">المدفوعات</h3>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug group-hover:text-slate-700 transition-colors">سجل كامل الدفعات والتحويلات المالية</p>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all" :class="referencesSubTab === 'payments' ? 'border-purple-600 bg-purple-50' : 'border-slate-200 group-hover:border-purple-300'">
                    <i v-if="referencesSubTab === 'payments'" class="fas fa-check text-xs text-purple-600"></i>
                  </div>
                </div>
              </button>
              
            </div>
          </div>

          <!-- Loading indicator for references -->
          <div v-if="showReferencesLoading" class="py-12 text-center">
            <BaseSpinner :size="32" color="#2563eb" />
          </div>

          <!-- Invoices Sub-tab -->
          <div v-if="referencesSubTab === 'invoices' && !showReferencesLoading" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <h3 class="font-black text-slate-800">{{ isCustomer ? 'فواتير العميل' : 'فواتير المورد' }} ({{ referencesItems.length }} فاتورة)</h3>
              <div v-if="salesOnly" class="flex flex-wrap gap-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                <span>عدد الفواتير: {{ salesOnly.count || (salesOnly.items?.length || 0) }}</span>
                <span>إجمالي: {{ formatPriceEn(invoicesTotals.total) }} {{ currencySymbol }}</span>
                <span>المتبقي: <span class="text-rose-500">{{ formatPriceEn(invoicesTotals.due) }}</span> {{ currencySymbol }}</span>
                <span v-if="(salesOnly.items?.length || 0) > 0">
                  متأخرة: {{ salesOnly.items.filter(s => getItemOutstanding(s) > 0 && daysBetween(s.date) > 0).length }}
                </span>
              </div>
            </div>

            <div v-if="salesOnly && salesOnlySorted.length" class="overflow-x-auto">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-500 font-black uppercase border-b border-slate-50">
                    <th class="px-6 py-5">التاريخ</th>
                    <th class="px-4 py-5">رقم الفاتورة</th>
                    <th class="px-4 py-5">المرجع</th>
                    <th class="px-4 py-5">الحالة</th>
                    <th class="px-4 py-5 text-right">الأصناف</th>
                    <th class="px-4 py-5 text-right">الصافي</th>
                    <th class="px-4 py-5 text-right">الضريبة</th>
                    <th class="px-4 py-5 text-right">الخصم</th>
                    <th class="px-4 py-5 text-right">الإجمالي</th>
                    <th class="px-4 py-5 text-right">المدفوع</th>
                    <th class="px-4 py-5 text-right">المتبقي</th>
                    <th class="px-6 py-5 text-center">أيام التأخير</th>
                    <th class="px-4 py-5">قيد محاسبي؟</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="s in salesOnlySorted" :key="s.id" class="hover:bg-slate-50/50 transition-all font-bold">
                    <td class="px-6 py-4 text-slate-400 font-mono">{{ formatDateEn(s.date) }}</td>
                    <td class="px-4 py-4 font-black">
                      <RouterLink v-if="invoiceLink(s)" :to="invoiceLink(s)" class="text-blue-600 hover:underline">
                        {{ s.invoice_number || ('#' + s.id) }}
                      </RouterLink>
                      <span v-else>{{ s.invoice_number || ('#' + s.id) }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-semibold">{{ s.reference || '-' }}</div>
                      <div class="text-[9px] text-slate-400 mt-0.5">{{ s.reference_label || refTypeLabel(s.reference) }}</div>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="['status-badge', badgeClass(displayStatusCode(s, s.status || s.status_code))]">
                        {{ displayStatusLabel(s, (s.status || s.status_code), s.status_label) }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-right font-mono">{{ new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(s.items_count ?? 0)) }}</td>
                    <td class="px-4 py-4 text-right font-mono text-slate-500">{{ formatPriceEn(s.net_total_amount) }}</td>
                    <td class="px-4 py-4 text-right font-mono text-slate-500">{{ formatPriceEn(s.tax_amount) }}</td>
                    <td class="px-4 py-4 text-right">
                      <span v-if="(s.discount_value || 0) > 0">
                        <template v-if="s.discount_type === 'percentage'">{{ Number(s.discount_value).toFixed(2) }}%</template>
                        <template v-else>{{ formatPriceEn(s.discount_value) }}</template>
                      </span>
                      <span v-else class="text-slate-300">-</span>
                    </td>
                    <td class="px-4 py-4 text-right font-mono text-slate-900">{{ formatPriceEn(s.total_amount) }}</td>
                    <td class="px-4 py-4 text-right font-mono text-emerald-600">{{ formatPriceEn(s.paid_amount) }}</td>
                    <td class="px-4 py-4 text-right font-mono" :class="getItemOutstanding(s) > 0 ? 'text-rose-600 font-black' : 'text-slate-300'">
                      {{ formatPriceEn(getItemOutstanding(s)) }}
                    </td>
                    <td class="px-6 py-4 text-center">
                      <span v-if="getItemOutstanding(s) > 0 && daysBetween(s.date) > 0" class="text-rose-500 font-black">
                        {{ new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(daysBetween(s.date)) }} ي
                      </span>
                      <span v-else class="text-slate-200">—</span>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="s.has_journal" class="text-emerald-600 font-semibold text-[10px] flex items-center gap-1">
                        <i class="fas fa-check-circle"></i>
                        قيد #{{ s.journal_entry_id || '-' }}
                        <span class="text-slate-400">({{ formatDateEn(s.journal_date || s.date) }})</span>
                      </span>
                      <span v-else class="text-slate-300 text-[10px]">لا</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50/80 font-black border-t-2 border-slate-100">
                  <tr>
                    <td class="px-6 py-4 text-[11px] text-slate-400 uppercase tracking-widest" colspan="6">
                      <i class="fas fa-chart-bar"></i> ملخص الفواتير
                    </td>
                    <td colspan="5" class="px-6 py-4">
                      <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="p-3 bg-slate-100/50 rounded-lg">
                          <div class="text-[10px] text-slate-500 font-black uppercase tracking-wider mb-1">إجمالي الفواتير</div>
                          <div class="text-lg font-black text-slate-900 font-mono">{{ formatPriceEn(invoicesTotals.total) }}</div>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
                          <div class="text-[10px] text-emerald-600 font-black uppercase tracking-wider mb-1">المدفوع</div>
                          <div class="text-lg font-black text-emerald-700 font-mono">{{ formatPriceEn(invoicesTotals.paid) }}</div>
                        </div>
                        <div class="p-3 bg-rose-50 rounded-lg border border-rose-100">
                          <div class="text-[10px] text-rose-600 font-black uppercase tracking-wider mb-1">المتبقي</div>
                          <div class="text-lg font-black text-rose-700 font-mono">{{ formatPriceEn(invoicesTotals.due) }}</div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div v-else class="text-center text-slate-400 p-12 font-bold">لا توجد فواتير لعرضها في هذه الفترة.</div>
          </div>

          <!-- Vouchers Sub-tab -->
          <div v-if="referencesSubTab === 'vouchers' && !showReferencesLoading" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <h3 class="font-black text-slate-800">السندات المرجعية ({{ referencesItems.length }} سند)</h3>
              <div class="flex items-center gap-3">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">عدد العناصر: {{ referencesData?.count || (referencesData?.items?.length || 0) }}</span>
                <button v-if="referencesItems.length" @click="exportReferencesCsv" class="px-4 py-2 rounded-xl bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 hover:text-white transition-all">
                  تصدير CSV
                </button>
                <div v-if="referencesItems.length" class="flex items-center gap-1">
                  <button class="page-btn" :disabled="refsPage <= 1" @click="refsPage = Math.max(1, refsPage - 1)"><i class="fas fa-angle-right text-xs"></i></button>
                  <span class="px-3 py-1.5 bg-white rounded-xl border border-slate-100 text-[10px] font-black">{{ refsPage }} / {{ referencesPages }}</span>
                  <button class="page-btn" :disabled="refsPage >= referencesPages" @click="refsPage = Math.min(referencesPages, refsPage + 1)"><i class="fas fa-angle-left text-xs"></i></button>
                </div>
              </div>
            </div>

            <div v-if="pagedReferences.length" class="overflow-x-auto">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-500 font-black uppercase border-b border-slate-50">
                    <th class="px-6 py-5">التاريخ</th>
                    <th class="px-4 py-5">النوع</th>
                    <th class="px-4 py-5">رقم السند</th>
                    <th class="px-4 py-5">المرجع</th>
                    <th class="px-4 py-5 text-right">المبلغ</th>
                    <th class="px-6 py-5">قيد محاسبي؟</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="(r, idx) in pagedReferences" :key="idx" class="hover:bg-slate-50 transition-all font-bold">
                    <td class="px-6 py-4 text-slate-400 font-mono">{{ formatDateEn(r.date) }}</td>
                    <td class="px-4 py-4">
                      <div class="flex flex-col gap-1">
                        <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest">
                          {{ resolvedRefType(r) }}
                        </span>
                        <span v-if="getTransactionSubtypeLabel(r)" class="text-[8px] text-slate-400 font-semibold px-1">
                          {{ getTransactionSubtypeLabel(r) }}
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-4 font-black">
                      {{ r.reference || r.invoice_number || ('#' + r.id) }}
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-[9px] mt-0.5" :class="isReturnLinkedReceipt(r) ? 'text-rose-400 font-black' : 'text-slate-400'">{{ resolvedRefLabel(r) }}</div>
                      <div v-if="getAllocatedInvoice(r)" class="text-[9px] mt-1.5 px-2 py-0.5 bg-cyan-50 text-cyan-700 rounded font-black tracking-tight">
                        ← تسوية: {{ getAllocatedInvoice(r) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 text-right font-mono text-slate-900 font-black">{{ formatPriceEn(r.total_amount || r.paid_amount || 0) }}</td>
                    <td class="px-6 py-4">
                      <span v-if="r.has_journal" class="text-emerald-600 flex items-center gap-1.5 text-[10px] font-black">
                        <i class="fas fa-check-circle"></i> مرحل - قيد #{{ r.journal_entry_id || '-' }}
                        <span class="text-slate-400">({{ formatDateEn(r.journal_date || r.date) }})</span>
                      </span>
                      <span v-else class="text-slate-300 text-[10px]">غير مرحل</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50/80 font-black border-t-2 border-slate-100">
                  <tr>
                    <td class="px-6 py-4 text-[11px] text-slate-400 uppercase tracking-widest" colspan="4">
                      <i class="fas fa-calculator"></i> إجمالي الصفحة
                    </td>
                    <td colspan="4" class="px-6 py-4">
                      <div class="flex items-center justify-end gap-6 text-sm font-mono">
                        <div class="text-center">
                          <span class="text-[10px] text-slate-400 font-black block mb-0.5">الصافي</span>
                          <span class="text-base font-black text-slate-700">{{ formatPriceEn(referenceItemsTotals.net) }}</span>
                        </div>
                        <div class="text-center">
                          <span class="text-[10px] text-slate-400 font-black block mb-0.5">الإجمالي</span>
                          <span class="text-base font-black text-slate-900">{{ formatPriceEn(referenceItemsTotals.total) }}</span>
                        </div>
                        <div class="text-center">
                          <span class="text-[10px] text-emerald-600 font-black block mb-0.5">المدفوع</span>
                          <span class="text-base font-black text-emerald-700">{{ formatPriceEn(referenceItemsTotals.paid) }}</span>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div v-else class="text-center text-slate-400 p-12 font-bold">لا توجد بيانات مرجعية للعرض.</div>
          </div>

          <!-- Payments Sub-tab -->
          <div v-if="referencesSubTab === 'payments' && !showReferencesLoading" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <h3 class="font-black text-slate-800">سجل المدفوعات والتحويلات ({{ referencesItems.length }} عملية)</h3>
              <div class="flex flex-wrap gap-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                <span>عدد العمليات: {{ referencesItems.length }}</span>
                <span v-if="referencesItems.length">إجمالي: {{ formatPriceEn(referencesItems.reduce((s, p) => s + Number(p.total_amount || p.paid_amount || 0), 0)) }} {{ currencySymbol }}</span>
              </div>
            </div>

            <div v-if="referencesItems && referencesItems.length" class="overflow-x-auto">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-500 font-black uppercase border-b border-slate-50">
                    <th class="px-6 py-5">التاريخ</th>
                    <th class="px-4 py-5">رقم المرجع</th>
                    <th class="px-4 py-5">نوع العملية</th>
                    <th class="px-4 py-5">الفاتورة</th>
                    <th class="px-4 py-5 text-right">المبلغ</th>
                    <th class="px-4 py-5">قيد محاسبي</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="p in pagedReferences" :key="p.id" class="hover:bg-slate-50/50 transition-all font-bold">
                    <td class="px-6 py-4 text-slate-400 font-mono">{{ formatDateEn(p.date) }}</td>
                    <td class="px-4 py-4 font-black">{{ p.reference || ('#' + p.id) }}</td>
                    <td class="px-4 py-4">
                      <span :class="['px-2 py-1 rounded-lg text-[9px] font-black', p.type === 'receipt' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700']">{{ p.type_label || (p.type === 'receipt' ? 'سند قبض' : 'سند صرف') }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="p.invoice_number" class="text-blue-600">{{ p.invoice_number }}</span>
                      <span v-else class="text-slate-300">—</span>
                    </td>
                    <td class="px-4 py-4 text-right font-mono font-black text-purple-600">{{ formatPriceEn(p.total_amount || p.paid_amount) }}</td>
                    <td class="px-6 py-4">
                      <span v-if="p.has_journal" class="text-emerald-600 flex items-center gap-1.5 text-[10px] font-black">
                        <i class="fas fa-check-circle"></i> قيد #{{ p.journal_entry_id || '-' }}
                      </span>
                      <span v-else class="text-slate-300 text-[10px]">غير مرحل</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50/80 font-black border-t-2 border-slate-100">
                  <tr>
                    <td class="px-6 py-4 text-[11px] text-slate-400 uppercase tracking-widest" colspan="4">
                      <i class="fas fa-receipt"></i> إجمالي الصفحة
                    </td>
                    <td colspan="2" class="px-6 py-4">
                      <div class="flex items-center justify-end gap-6 text-sm font-mono">
                        <div class="text-center">
                          <span class="text-[10px] text-purple-600 font-black block mb-0.5">المجموع</span>
                          <span class="text-base font-black text-purple-700">{{ formatPriceEn(pagedReferences.reduce((s, p) => s + Number(p.total_amount || p.paid_amount || 0), 0)) }}</span>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div v-else class="text-center text-slate-400 p-12 font-bold">لا توجد عمليات دفع وتحويلات للعرض في هذه الفترة.</div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStatementData } from '@/composables/useStatementData';
import { useStatementRBAC } from '@/composables/useStatementRBAC';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useSettingsStore } from '@/stores/settings/settingsStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { useToast } from '@/composables/useToast';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useDateValidation } from '@/composables/useDateValidation';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import { getLocalDateISO } from '@/utils/date';
import { getImageUrl } from '@/utils/imageHelpers';

// --- Core Composables ---
const route = useRoute();
const router = useRouter();
const { showToast } = useToast();
const { currencySymbol, formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { validateDateRange } = useDateValidation();

// --- Unified Statement Data Composable ---
const {
  fetchStatementData,
  loading: statementLoading,
  error: statementError,
  data: statementData,
  invoices,
  payments,
  transactions,
  returns,
  totals,
  agingAnalysis,
  availableCredit,
  alertStatus,
  page,           // ← جديد: pagination
  perPage,        // ← جديد: pagination
  totalRecords,   // ← جديد: pagination
  totalPages      // ← جديد: pagination
} = useStatementData();

// --- Stores ---
const authStore = useAuthStore();
const branchStore = useBranchStore();
const paymentStore = usePaymentStore();
const settingsStore = useSettingsStore();
const customerStore = useCustomerStore();
const supplierStore = useSupplierStore();

// --- Component State ---
const loading = ref(false);
const activeTab = ref('accounting');
const viewMode = ref(route.query.view || 'detailed');
const companyInfo = ref({ name: '', address: '', phone: '', email: '', logo: '' });
const partyInfo = ref({ name: '', address: '', phone: '', email: '' });

// --- Route Params & Defaults ---
const type = computed(() => route.params.type);
const id = computed(() => Number(route.params.id));

// --- RBAC Composable (must come AFTER type definition) ---
const {
  canViewStatement,
  canExport,
  canViewSensitiveData,
  canViewAgingAnalysis,
  logAccess,
  logDenial
} = useStatementRBAC(type.value);

// Use unified data from composable
const data = computed(() => statementData.value);

const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const startOfMonth = `${yyyy}-${mm}-01`;
const startOfYear = `${yyyy}-01-01`;  // ← Current year start
const todayString = `${yyyy}-${mm}-${dd}`; // اليوم الحالي

// --- Filters State ---
// ✅ IMPROVED: Use current year start as default (same as ContactDetails)
// This balances performance with keeping relevant current-year data
const startDate = ref(route.query.start_date || startOfYear);  // ← Changed to startOfYear
const endDate = ref(route.query.end_date || todayString);
const searchText = ref(''); // للبحث النصي

// Date input refs
const startDateRef = ref(null);
const endDateRef = ref(null);
const typeFilter = ref(route.query.type || '');
const fillGaps = ref(typeof route.query.fill_gaps !== 'undefined' ? route.query.fill_gaps !== '0' : false);
const onlyNonZero = ref(route.query.only_nonzero === '1');
const statusFilter = ref(route.query.status || 'any');
const paymentMethodId = ref(route.query.payment_method_id || '');
// For reports, support optional branch filtering - default to selected branch
const selectedBranch = computed({
  get: () => route.query.branch_id || branchStore.selectedBranchId || '',
  set: (val) => {
    const query = { ...route.query, branch_id: val || undefined };
    router.replace({ query: Object.fromEntries(Object.entries(query).filter(([_, v]) => v !== undefined)) });
  }
});

// --- Static Options ---
const typeOptions = [
    { value: '', label: 'كل العمليات' },
    { value: 'sale', label: 'فواتير المبيعات' },
    { value: 'receipt', label: 'مدفوعات/تحصيل' },
    { value: 'sales_return', label: 'مرتجعات مبيعات' },
    { value: 'purchase', label: 'فواتير المشتريات' },
    { value: 'payment', label: 'مدفوعات للمورد' },
    { value: 'purchase_return', label: 'مرتجعات مشتريات' },
    { value: 'journal', label: 'قيود يومية' }
];
const statusOptions = [
    { value: 'any', label: 'كل الحالات' },
    { value: 'paid', label: 'مدفوع' },
    { value: 'partial', label: 'مدفوع جزئياً' },
    { value: 'unpaid', label: 'غير مدفوع' }
];

// --- Data for Selects ---
const paymentMethods = computed(() => paymentStore.paymentMethods);
const branches = computed(() => branchStore.branches);

// --- Pagination ---
const txPerPage = ref(Number(route.query.tx_per_page) || 20);
const txPage = ref(Number(route.query.tx_page || 1));
const refsPerPage = ref(20);
const refsPage = ref(1);

// --- References State ---
const showReferencesLoading = ref(false);
const referencesSubTab = ref('invoices');

// ─── Computed: Transactions ────────────────────────────────────────────────

const visibleTransactions = computed(() => {
    const all = transactions.value || [];
    const sf = (statusFilter?.value || '').toString();
    const filtered = sf && sf !== 'any' ? all : all.filter(t => {
        const HIDE = new Set(['rejected', 'canceled', 'cancelled']);
        return !HIDE.has(String(t.status || t.invoice_status || '').toLowerCase());
    });
    
    // تطبيق البحث النصي
    if (!searchText.value) return filtered;
    const search = String(searchText.value || '').toLowerCase();
    return filtered.filter(t => {
        const refNo = String(t.reference || t.invoice_number || '').toLowerCase();
        const desc = String(t.description || '').toLowerCase();
        const debit = String(t.debit || '').toLowerCase();
        const credit = String(t.credit || '').toLowerCase();
        return refNo.includes(search) || desc.includes(search) || debit.includes(search) || credit.includes(search);
    });
});

const txPages = computed(() => Math.max(1, Math.ceil(visibleTransactions.value.length / txPerPage.value)));

const pagedTransactions = computed(() => {
    const arr = visibleTransactions.value;
    const start = (txPage.value - 1) * txPerPage.value;
    return arr.slice(start, start + txPerPage.value);
});

const pageTotals = computed(() => {
    const arr = pagedTransactions.value || [];
    let debit = 0, credit = 0, closing = null;
    for (const t of arr) {
        debit += Number(t.debit || 0);
        credit += Number(t.credit || 0);
        closing = t.balance;
    }
    return { debit, credit, closing };
});

// ─── Computed: Daily Balances ──────────────────────────────────────────────

const dailyBalances = computed(() => {
    const backend = data.value?.daily_balances;
    if (Array.isArray(backend) && backend.length) return backend;
    // Fallback: derive from transactions in composable
    const txs = (transactions.value || []).slice().sort((a, b) => new Date(a.date) - new Date(b.date));
    if (!txs.length) return [];
    const byDate = {};
    for (const t of txs) {
        const key = getLocalDateISO(new Date(t.date));
        if (!byDate[key]) byDate[key] = { date: key, opening_balance: 0, day_debit: 0, day_credit: 0, closing_balance: 0, transaction_count: 0 };
        byDate[key].day_debit += Number(t.debit || 0);
        byDate[key].day_credit += Number(t.credit || 0);
        byDate[key].transaction_count += 1;
    }
    const keys = Object.keys(byDate).sort();
    let running = Number(data.value?.opening_balance || 0);
    for (const k of keys) {
        const rec = byDate[k];
        rec.opening_balance = running;
        rec.closing_balance = running + rec.day_debit - rec.day_credit;
        running = rec.closing_balance;
    }
    return keys.map(k => byDate[k]);
});

// ─── Computed: References & Invoices ──────────────────────────────────────

const referencesData = computed(() => data.value?.references || null);
const salesOnly = computed(() => ({
    items: invoices.value || []
}));

const salesOnlySorted = computed(() => {
    const arr = salesOnly.value?.items || [];
    return [...arr].sort((a, b) => new Date(a?.date || 0).getTime() - new Date(b?.date || 0).getTime());
});

const invoicesTotals = computed(() => {
    const arr = salesOnlySorted.value || [];
    let net = 0, tax = 0, disc = 0, total = 0, paid = 0, due = 0;
    for (const s of arr) {
        net += Number(s.net_total_amount || 0);
        tax += Number(s.tax_amount || 0);
        const dAmt = (s.discount_amount != null)
            ? Number(s.discount_amount)
            : (String(s.discount_type || '').toLowerCase() === 'percentage'
                ? (Number(s.discount_value || 0) / 100) * Number(s.net_total_amount || 0)
                : Number(s.discount_value || 0));
        disc += Number(dAmt || 0);
        total += Number(s.total_amount || 0);
        paid += Number(s.paid_amount || 0);
        
        // FIX: Don't count closed_by_return invoices in due amount
        // A closed_by_return means the debt was cleared by a return, NOT by payment
        const status = String(s.status || s.status_code || '').toLowerCase();
        if (status === 'closed_by_return') {
            // Skip: don't add to due
            continue;
        }
        
        const rawDue = (s.outstanding != null) ? Number(s.outstanding) : (s.due_amount != null ? Number(s.due_amount) : (Number(s.net_total_amount || 0) - Number(s.paid_amount || 0)));
        due += Math.max(0, Number(rawDue || 0));
    }
    return { net, tax, disc, total, paid, due };
});

const referenceItemsTotals = computed(() => {
    const arr = pagedReferences.value || [];
    let total = 0, paid = 0, net = 0;
    for (const r of arr) {
        net += Number(r.net_total_amount || 0);
        total += Number(r.total_amount || grossTotal(r) || 0);
        paid += Number(r.paid_amount || r.amount || 0);
    }
    return { total, paid, net };
});

const isVoucherItem = (it) => {
    const prefix = String(it?.reference || '').split('#')[0];
    const t = String(it?.type || '').toLowerCase();
    // Vouchers: receipts, payments, cash vouchers, returns, refunds, journal entries (non-sale/purchase)
    const voucherTypes = ['receipt', 'payment', 'cash_voucher', 'refund', 'sales_return', 'purchase_return'];
    return voucherTypes.includes(prefix) || voucherTypes.some(vt => t.includes(vt));
};

// IDs of journal entries linked to returns — used to detect return-payment receipts
const returnJournalIds = computed(() => {
    const items = referencesData.value?.items || [];
    return new Set(
        items
            .filter(r => r.type === 'sales_return' || r.type === 'purchase_return')
            .map(r => r.journal_entry_id)
            .filter(Boolean)
    );
});

const isReturnLinkedReceipt = (r) =>
    (r.type === 'receipt' || r.type === 'payment') &&
    r.journal_entry_id &&
    returnJournalIds.value.has(r.journal_entry_id);

const isPaymentItem = (it) => {
    const t = String(it?.type || '').toLowerCase();
    const prefix = String(it?.reference || '').split('#')[0];
    // Payments: receipts (سند قبض) and refunds (سند صرف) only - no invoices or returns
    const paymentTypes = ['receipt', 'refund'];
    return paymentTypes.includes(prefix.toLowerCase()) || paymentTypes.some(pt => t.includes(pt));
};

const referencesItems = computed(() => {
    const arr = referencesData.value?.items || [];
    let filtered = arr;
    if (referencesSubTab.value === 'vouchers') {
        filtered = arr.filter(isVoucherItem);
    } else if (referencesSubTab.value === 'payments') {
        filtered = arr.filter(isPaymentItem);
    } else {
        // invoices - exclude vouchers and payments
        filtered = arr.filter(it => !isVoucherItem(it) && !isPaymentItem(it));
    }
    return [...filtered].sort((a, b) => new Date(a?.date || 0).getTime() - new Date(b?.date || 0).getTime());
});

const referencesPages = computed(() => Math.max(1, Math.ceil(referencesItems.value.length / refsPerPage.value)));

const pagedReferences = computed(() => {
    const start = (refsPage.value - 1) * refsPerPage.value;
    return referencesItems.value.slice(start, start + refsPerPage.value);
});

// ─── Computed: Labels & UI ────────────────────────────────────────────────

const title = computed(() => type.value === 'customers' ? 'كشف حساب عميل' : 'كشف حساب مورد');
const isCustomer = computed(() => type.value === 'customers');
const referencesTabLabel = computed(() => isCustomer.value ? 'مرجع الفواتير والسندات للعميل' : 'مرجع فواتير ومدفوعات المورد');

const groupedByType = computed(() => {
    if (!transactions.value || !Array.isArray(transactions.value)) return [];
    const map = {};
    for (const t of transactions.value) {
        // ✅ FIX: Use transaction_type instead of reference_type to get the actual type
        // This ensures settlement lines (receipt payments) are grouped correctly
        const key = String(t.transaction_type || t.reference || '').split('#')[0] || 'other';
        // ✅ CRITICAL FIX: Use transaction_type for label, not reference_label
        // reference_label might be based on reference_type (sale) instead of actual transaction_type (receipt)
        const label = getTransactionTypeLabel(t.transaction_type || t.reference_type || (t.reference || '').split('#')[0]) || 'حركة';
        if (!map[key]) map[key] = { type: key, label: label, debit: 0, credit: 0, count: 0 };
        map[key].debit += Number(t.debit || 0);
        map[key].credit += Number(t.credit || 0);
        map[key].count += 1;
    }
    return Object.values(map);
});

// ─── Computed: Aging ──────────────────────────────────────────────────────

const agingCounts = computed(() => {
    const counts = data.value?.aging_counts || null;
    if (counts) return counts;
    const c = { current: 0, '1_30_days': 0, '31_60_days': 0, '60_plus_days': 0 };
    const items = referencesData.value?.items || salesOnly.value?.items || [];
    const now = new Date();
    for (const it of items) {
        const itType = it.type || String(it.reference || '').split('#')[0];
        const outstanding = Number((it.outstanding ?? (it.net_total_amount - (it.paid_amount || 0))) || 0);
        if (itType !== 'sale' || !(outstanding > 0)) continue;
        const diff = Math.floor((now - new Date(it.date)) / (1000 * 60 * 60 * 24));
        if (diff <= 0) c.current++;
        else if (diff <= 30) c['1_30_days']++;
        else if (diff <= 60) c['31_60_days']++;
        else c['60_plus_days']++;
    }
    return c;
});

// ─── Computed: Overdue Alerts ──────────────────────────────────────────

const overdueInvoices = computed(() => {
    const items = referencesData.value?.items || salesOnly.value?.items || [];
    const now = new Date();
    return items.filter(it => {
        const itType = it.type || String(it.reference || '').split('#')[0];
        const outstanding = Number((it.outstanding ?? (it.net_total_amount - (it.paid_amount || 0))) || 0);
        if (itType !== 'sale' || !(outstanding > 0)) return false;
        const diff = Math.floor((now - new Date(it.date)) / (1000 * 60 * 60 * 24));
        return diff > 30; // متأخر أكثر من 30 يوم
    });
});

const hasOverdueAlert = computed(() => isCustomer.value && overdueInvoices.value.length > 0);

// ─── Computed: Invoice Status Map ─────────────────────────────────────────

const invoiceStatusMap = computed(() => {
    const map = {};
    try {
        const items = referencesData.value?.items || salesOnly.value?.items || [];
        for (const it of items) {
            const itType = it.type || String(it.reference || '').split('#')[0];
            if (itType !== 'sale' && itType !== 'purchase') continue;
            const itId = it.id || it.sale_id || it.purchase_id || Number(String(it.reference || '').split('#')[1] || 0) || null;
            const refNo = String(it.reference || '').includes('#') ? String(it.reference).split('#')[1] : '';
            const code = paymentStatusFromAmounts(it);
            if (!code) continue;
            const label = code === 'paid' ? 'مدفوعة' : code === 'settled' ? 'مسددة' : code === 'partial' ? 'مدفوعة جزئياً' : 'غير مدفوعة';
            if (itId) map[`${itType}:${itId}`] = { code, label };
            if (refNo) map[`${itType}#${refNo}`] = { code, label };
        }
    } catch { /* ignore */ }
    return map;
});

// ─── Helper Functions ─────────────────────────────────────────────────────

const paymentMethodDisplay = (r) => {
    try {
        const typeStr = String(r.type || '').trim();
        const refStr = String(r.reference || '').trim();
        const hashPrefix = String(r.reference || r.type || '').split('#')[0];
        const isVoucher = ['receipt', 'payment', 'cash_voucher', 'expense', 'income'].includes(hashPrefix)
            || /^(CV|PV|RV|BNK|CRD|POS|TRF|TRX|CASH|BANK|CARD|VCH)-/i.test(refStr)
            || /سند\s*(قبض|دفع)/.test(typeStr);
        if (!isVoucher) return '-';

        const direct = r.payment_method_name || r.method_name || r.method_title || r.payment_method_title
            || r.method_display_name || r.payment_method_display_name || r.method || r.payment_method
            || r.channel_name || r.channel || r.gateway_name || r.gateway || '';
        if (direct) return direct;

        const nested = r.method_details?.name || r.payment_method_details?.name
            || r.meta?.payment_method_name || r.meta?.method_name || r.meta?.channel_name || r.meta?.gateway_name
            || r.details?.payment_method_name || r.details?.channel_name || r.details?.gateway_name
            || r.payment?.method_name || r.payment?.channel_name || r.payment?.gateway_name
            || r.payment_method?.name || r.payment_method?.title || r.payment_method?.display_name || r.payment_method?.label
            || r.method_info?.name || r.method_info?.title || r.method_info?.display_name || '';
        if (nested) return nested;

        const refCode = (refStr.match(/^([A-Za-z]+)-/) || [])[1];
        if (refCode) {
            const codeMap = { CV: 'Cash', CASH: 'Cash', BNK: 'Bank', BANK: 'Bank', CRD: 'Card', CARD: 'Card', POS: 'Card', TRF: 'Transfer', TRX: 'Transfer' };
            const m = codeMap[refCode.toUpperCase()];
            if (m) return m;
        }

        const desc = String(r.description || '').toLowerCase();
        if (/(cash|نقد)/.test(desc)) return 'Cash';
        if (/(card|بطاقة|visa|master)/.test(desc)) return 'Card';
        if (/(bank|بنك|تحويل|حوالة|transfer)/.test(desc)) return 'Bank';

        const pmId = r.payment_method_id || r.method_id || r.meta?.payment_method_id || r.details?.payment_method_id || r.payment_method?.id;
        const pmCode = r.payment_method_code || r.method_code || r.meta?.payment_method_code || r.payment_method?.code || r.channel_code || r.gateway_code;
        if (Array.isArray(paymentMethods?.value) && paymentMethods.value.length) {
            if (pmId) {
                const byId = paymentMethods.value.find(m => Number(m.id) === Number(pmId));
                if (byId?.name) return byId.name;
            }
            if (pmCode) {
                const byCode = paymentMethods.value.find(m => String(m.code || m.key || m.slug || '').toLowerCase() === String(pmCode).toLowerCase());
                if (byCode?.name) return byCode.name;
            }
        }
    } catch (_) {}
    return '-';
};

const taxAmount = (r) => {
    try {
        const direct = Number(
            r.tax_amount ?? r.vat_amount ?? r.total_tax ?? r.tax ?? r.vat ??
            r.tax_value ?? r.vat_value ?? r.total_vat ?? r.total_tax_amount ??
            r.taxAmount ?? r.vatAmount ?? 0
        );
        if (!Number.isNaN(direct) && direct > 0) return direct;
        if (Array.isArray(r.taxes) && r.taxes.length) {
            const sum = r.taxes.reduce((s, t) => s + Number(t.amount || t.value || 0), 0);
            if (sum > 0) return sum;
        }
        const net = Number(r.net_total_amount || 0);
        const rate = Number(r.tax_rate || r.vat_rate || 0);
        if (!Number.isNaN(rate) && rate > 0) {
            const calc = +(net * rate / 100).toFixed(2);
            if (calc > 0) return calc;
        }
        const total = Number(r.total_amount || 0);
        let diff = total - net;
        if (diff <= 0) {
            const paid = Number(r.paid_amount || 0);
            const status = String(r.status || r.status_code || '').toLowerCase();
            if (paid > net || ['paid', 'completed', 'posted'].includes(status)) {
                const paidDiff = paid - net;
                if (paidDiff > 0 && paidDiff < net * 2) return paidDiff;
            }
        }
        return diff > 0 ? diff : 0;
    } catch (_) { return 0; }
};

const grossTotal = (r) => {
    const net = Number(r.net_total_amount || r.net || 0);
    const tax = taxAmount(r);
    const total = Number(r.total_amount || r.total || 0);
    return Math.max(net + tax, total);
};

const statusTextLocal = (s) => {
    const v = String(s || '').toLowerCase();
    if (v === 'paid') return 'مدفوعة';
    if (v === 'settled') return 'مسددة';
    if (v === 'partial' || v === 'partially_paid') return 'مدفوعة جزئياً';
    if (v === 'unpaid' || v === 'pending' || v === 'pending_payment') return 'آجل';
    if (v === 'closed_by_return') return 'مرتجعة';
    if (v === 'settled_by_return') return 'مسواة بمرتجع';
    if (v === 'settled_by_credit') return 'مسوّاة بمرتجع';
    if (v === 'posted') return 'مرحلة';
    if (v === 'approved') return 'معتمد';
    if (v === 'draft') return 'مسودة';
    if (v === 'rejected') return 'مرفوضة';
    if (v === 'canceled' || v === 'cancelled') return 'ملغاة';
    if (v === 'completed') return 'مكتملة';
    return '';
};

const paymentStatusFromAmounts = (item) => {
    try {
        const total = Number(item?.net_total_amount ?? item?.total_amount ?? item?.grand_total ?? 0);
        const paid = Number(item?.paid_amount ?? 0);
        const outstanding = item?.outstanding != null ? Number(item.outstanding) : (total - paid);
        if (total > 0) {
            if (outstanding <= 0) return 'paid';
            if (paid > 0 && outstanding > 0) return 'partial';
            return 'unpaid';
        }
        return null;
    } catch { return null; }
};

const getItemOutstanding = (item) => {
    try {
        if (item?.outstanding != null && String(item.outstanding).trim() !== '') {
            return Number(item.outstanding) || 0;
        }
        const total = Number(item?.net_total_amount ?? item?.total_amount ?? item?.grand_total ?? 0);
        const paid = Number(item?.paid_amount ?? 0);
        return Math.max(0, total - paid);
    } catch {
        return 0;
    }
};

const displayStatusCode = (item, fallback) => {
    // Check API status first (takes priority over calculated status)
    const apiStatus = String(item?.status || item?.status_code || '').toLowerCase();
    // ✅ All special statuses first
    if (apiStatus === 'closed_by_return')  return 'closed_by_return';
    if (apiStatus === 'settled_by_return') return 'settled_by_return';
    if (apiStatus === 'settled_by_credit') return 'settled_by_credit';
    if (apiStatus === 'settled_mixed')     return 'settled_mixed';
    if (apiStatus === 'returned')          return 'returned';
    if (apiStatus === 'paid')              return 'paid';
    if (apiStatus === 'partial' || apiStatus === 'partially_paid') return 'partial';
    // If API claims unpaid/pending but amounts show partial, prefer partial
    if (apiStatus === 'pending_payment' || apiStatus === 'unpaid') {
      const calc = paymentStatusFromAmounts(item);
      if (calc === 'partial') return 'partial';
      return 'unpaid';
    }
    if (apiStatus === 'approved')          return 'approved';
    if (apiStatus === 'draft')             return 'draft';
    // Otherwise calculate from amounts
    return paymentStatusFromAmounts(item) || String(fallback || '').toLowerCase();
};

const displayStatusLabel = (item, fallbackCode, fallbackLabel) => {
    // Check API status first (takes priority over calculated status)
    const apiStatus = String(item?.status || item?.status_code || '').toLowerCase();
    
    // ✅ All special statuses first — no fallback override
    if (apiStatus === 'closed_by_return')  return 'مرتجعة';
    if (apiStatus === 'settled_by_return') return 'مسواة بمرتجع';
    if (apiStatus === 'settled_by_credit') return 'مسوّاة بمرتجع';
    if (apiStatus === 'settled_mixed')     return 'مسوّاة بمزيج نقدي/إشعار دائن';
    if (apiStatus === 'returned')          return 'مرتجعة';
    if (apiStatus === 'paid')              return 'مدفوعة';
    if (apiStatus === 'partial' || apiStatus === 'partially_paid') return 'مدفوعة جزئياً';
    if (apiStatus === 'pending_payment' || apiStatus === 'unpaid') {
      const calc = paymentStatusFromAmounts(item);
      if (calc === 'partial') return 'مدفوعة جزئياً';
      return 'آجل';
    }
    if (apiStatus === 'approved')          return 'معتمدة';
    if (apiStatus === 'draft')             return 'مسودة';
    
    // Calculate status from amounts only as last resort
    const code = paymentStatusFromAmounts(item);
    if (code === 'paid')    return 'مدفوعة';
    if (code === 'partial') return 'مدفوعة جزئياً';
    if (code === 'unpaid')  return 'غير مدفوعة';
    
    return fallbackLabel || statusTextLocal(fallbackCode) || '-';
};

const derivedTxStatus = (t) => {
    try {
        const prefix = String(t.reference || '').split('#')[0];
        if (['return', 'cash_voucher', 'receipt', 'payment', 'journal'].includes(prefix)) {
            const labelMap = { return: 'مرتجع', cash_voucher: 'سند', receipt: 'سند', payment: 'سند', journal: 'قيد يومية' };
            return { code: 'n/a', label: labelMap[prefix] || '—' };
        }
        const refNo = String(t.reference || '').includes('#') ? String(t.reference).split('#')[1] : '';
        const rawId = t.sale_id || t.purchase_id || String(t.reference || '').split('#')[1] || t.id || '';
        const itId = Number(rawId.toString());
        if (!isNaN(itId) && (prefix === 'sale' || prefix === 'purchase')) {
            const hitId = invoiceStatusMap.value[`${prefix}:${itId}`];
            if (hitId) return hitId;
            if (refNo) {
                const hitRef = invoiceStatusMap.value[`${prefix}#${refNo}`];
                if (hitRef) return hitRef;
            }
        }
    } catch { /* ignore */ }
    return null;
};

// ─── Formatting Helpers ───────────────────────────────────────────────────

const formatDate = (date) => new Date(date).toLocaleDateString('en-GB');
const formatDateEn = (date) => date ? new Date(date).toLocaleDateString('en-GB') : '';
const formatPrice = (amount) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount || 0));
const formatPriceEn = (amount) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount || 0));
const refNumber = (reference) => String(reference || '-').split('#').pop();

const refTypeLabel = (reference) => {
    const t = String(reference || '').includes('#') ? String(reference).split('#')[0] : '';
    switch (t) {
        case 'sale': return 'فاتورة بيع';
        case 'purchase': return 'فاتورة شراء';
        case 'receipt': return 'قبض';
        case 'payment': return 'صرف';
        case 'sales_return': return 'مرتجع بيع';
        case 'purchase_return': return 'مرتجع شراء';
        case 'return': return 'مرتجع';
        case 'cash_voucher': return 'سند قبض';
        case 'journal': return 'قيد يومية';
        default: return t || '-';
    }
};

const getTransactionTypeLabel = (type) => {
    if (!type) return 'حركة';
    const t = String(type || '').toLowerCase();
    switch (t) {
        case 'sale': return 'فاتورة بيع';
        case 'sales': return 'فاتورة بيع';
        case 'purchase': return 'فاتورة شراء';
        case 'purchases': return 'فاتورة شراء';
        case 'receipt': return 'قبض';
        case 'payment': return 'صرف';
        case 'refund': return 'صرف';
        case 'sales_return': return 'مرتجع بيع';
        case 'sale_return': return 'مرتجع بيع';
        case 'return_sale': return 'مرتجع بيع';
        case 'purchase_return': return 'مرتجع شراء';
        case 'return_purchase': return 'مرتجع شراء';
        case 'return': return 'مرتجع';
        case 'cash_voucher': return 'سند نقدي';
        case 'journal': return 'قيد يومية';
        case 'return_payment': return 'سداد مرتجع';
        case 'sales_return_refund': return 'استرداد عميل';
        case 'purchase_return_refund': return 'استرجاع مورد';
        default: return t || 'حركة';
    }
};

const resolvedRefType = (r) => {
    // Check if this is a return-linked receipt
    if (isReturnLinkedReceipt(r)) return 'صرف مرتجع';

    if (r.transaction_subtype) {
        const subtypeDisplayMap = {
            'sales_return_only':          'مرتجع بيع (بدون صرف)',
            'sales_return_refund':        'مرتجع بيع + صرف',
            'sales_return_bank_refund':   'مرتجع بيع + تحويل',
            'purchase_return_only':       'مرتجع شراء (بدون صرف)',
            'purchase_return_refund':     'مرتجع شراء + صرف',
            'purchase_return_bank_refund':'مرتجع شراء + تحويل',
        };
        if (subtypeDisplayMap[r.transaction_subtype]) {
            return subtypeDisplayMap[r.transaction_subtype];
        }
    }

    // Fallback to type or reference parsing
    const type = String(r.type || '').toLowerCase();
    if (type) {
        return getTransactionTypeLabel(type);
    }
    const ref = String(r.reference || '').split('#')[0];
    return getTransactionTypeLabel(ref) || 'مستند';
};

const resolvedRefLabel = (r) => {
    // Check if this is a return-linked receipt
    if (isReturnLinkedReceipt(r)) return 'سند صرف مرتجع';
    // Use reference_label from backend if available
    if (r.reference_label) {
        return r.reference_label;
    }
    // Fallback to reference parsing
    const ref = String(r.reference || '').split('#')[0];
    return refTypeLabel(ref) || '-';
};

const getTransactionSubtypeLabel = (transaction) => {
    if (!transaction) return '';
    const groupId = transaction.return_group_id;
    if (!groupId) return '';
    return `ربط #${groupId}`;
};

const badgeClass = (type) => {
    const t = String(type || '').toLowerCase();
    if (t === 'closed_by_return')  return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    if (t === 'settled_by_return') return 'bg-teal-50 text-teal-700 border border-teal-200';
    if (t === 'settled_by_credit') return 'bg-cyan-50 text-cyan-700 border border-cyan-200';
    if (t === 'settled_mixed')     return 'bg-purple-50 text-purple-700 border border-purple-200';
    if (t === 'returned')          return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    if (t === 'paid')              return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if (t === 'partial')           return 'bg-amber-50 text-amber-700 border border-amber-200';
    if (t === 'unpaid')            return 'bg-rose-50 text-rose-700 border border-rose-200';
    if (['sale', 'sales', 'purchase', 'purchases'].includes(t)) return 'bg-rose-50 text-rose-700 border border-rose-200';
    if (['receipt', 'refund', 'payment'].includes(t)) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if (['sales_return', 'return_sale', 'purchase_return', 'return_purchase', 'return'].includes(t)) return 'bg-blue-50 text-blue-700 border border-blue-200';
    if (['journal', 'cash_voucher'].includes(t)) return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-slate-50 text-slate-700 border border-slate-200';
};

const getBalanceWithNature = (transaction) => {
    if (!transaction) return '-';
    const balance = Number(transaction.balance || 0);
    const nature = String(transaction.balance_nature || 'zero').toLowerCase();
    if (nature === 'debit') {
        return `${formatPriceEn(balance)} مدين`;
    } else if (nature === 'credit') {
        return `${formatPriceEn(Math.abs(balance))} دائن`;
    }
    return formatPriceEn(balance);
};

const getBalanceColorClass = (transaction) => {
    if (!transaction) return 'text-slate-600';
    const nature = String(transaction.balance_nature || 'zero').toLowerCase();
    if (nature === 'debit') return 'text-blue-700 font-bold';
    if (nature === 'credit') return 'text-amber-700 font-bold';
    return 'text-slate-600';
};

const invoiceLink = (item) => {
    try {
        const ref = String(item.reference || item.type || '');
        if (!ref.includes('#')) return null;
        const [rawPrefix, rawId] = ref.split('#');
        const prefix = String(rawPrefix || '').toLowerCase();
        const linkId = Number(item.id || item.sale_id || item.purchase_id || rawId);
        if (!linkId || Number.isNaN(linkId)) return null;
        if (prefix === 'sale' || prefix === 'sales') return { name: 'SalesHistory', query: { id: linkId } };
        if (prefix === 'purchase' || prefix === 'purchases') return { name: 'PurchaseHistory', query: { id: linkId } };
        if (['return', 'sales_return', 'sale_return', 'return_sale', 'purchase_return', 'return_purchase'].includes(prefix)) {
            return { name: 'ReturnsHistory', query: { id: linkId } };
        }
        if (['receipt', 'payment', 'cash_voucher'].includes(prefix)) return { name: 'CashVouchers', query: { id: linkId } };
        return null;
    } catch { return null; }
};

// Find the invoice settled by this return/credit allocation
const getAllocatedInvoice = (returnItem) => {
    if (!returnItem || !['sales_return', 'return', 'purchase_return'].includes(String(returnItem.type || '').toLowerCase())) {
        return null;
    }
    const groupId = returnItem.return_group_id;
    if (!groupId) return null;
    
    // Search in sales_only for a settled_by_credit invoice
    if (salesOnly.value?.items) {
        const settled = salesOnly.value.items.find(s => 
            String(s.status || '').toLowerCase() === 'settled_by_credit' && 
            (s.return_group_id === groupId || s.id === returnItem.sale_id)
        );
        return settled?.invoice_number || null;
    }
    return null;
};

const daysBetween = (d) => {
    if (!d) return 0;
    const start = new Date(typeof d === 'string' || typeof d === 'number' ? d : String(d));
    return Math.max(0, Math.floor((new Date() - start) / (1000 * 60 * 60 * 24)));
};

const coveredInvoice = (r) => {
    return (
        r.covers_invoice_number || r.applies_to_invoice_number || r.invoice_number_covered ||
        (r.applies_to && (r.applies_to.invoice_number || r.applies_to.reference)) ||
        r.sale_invoice_number || r.sale_number || r.sale_no ||
        r.sale_reference || r.applies_to_reference || r.reference_target || ''
    );
};

const rowClass = (t) => {
    const txType = String(t.reference || '').split('#')[0];
    if (txType.includes('sale') || txType.includes('purchase')) return 'border-r-4 border-r-rose-200';
    if (txType.includes('receipt') || txType.includes('payment')) return 'border-r-4 border-r-emerald-200';
    if (txType.includes('return')) return 'border-r-4 border-r-blue-200';
    return '';
};

// ─── Navigation ───────────────────────────────────────────────────────────

const backToContact = () => {
    try {
        if (type.value === 'customers') { router.push({ name: 'CustomersManagement' }); return; }
        if (type.value === 'suppliers') { router.push({ name: 'SuppliersManagement' }); return; }
    } catch (_) { /* ignore, fallback below */ }
    router.back();
};

// ─── Data Fetching ────────────────────────────────────────────────────────

const fetchStatement = async () => {
    if (!id.value || !['customers', 'suppliers'].includes(type.value)) {
        showToast('مسار غير صحيح', 'error');
        return;
    }

    // Use the unified composable - it handles all data fetching
    const result = await fetchStatementData(type.value, id.value, {
        start_date: startDate.value,
        end_date: endDate.value,
        status: statusFilter.value || 'any',
        fill_gaps: fillGaps.value ? 1 : 0,
        include_references: 1,
        include_types: typeFilter.value || undefined,
        payment_method_id: paymentMethodId.value || undefined,
        branch_id: selectedBranch.value || undefined,
        only_nonzero: onlyNonZero.value ? 1 : 0,
    });

    if (result.status !== 'success') {
        showToast(result.error?.message || 'فشل في تحميل كشف الحساب', 'error');
    }
};

// ─── Double Fetch Prevention Flag ────────────────────────────────────────

let _isApplyingFilter = false;

const applyFilter = () => {
    // Validate date range before applying filter
    if (!validateDateRange(startDate.value, endDate.value)) {
        // Toast error is shown automatically by useDateValidation
        return;
    }

    _isApplyingFilter = true;
    router.replace({
        query: {
            start_date: startDate.value,
            end_date: endDate.value,
            fill_gaps: fillGaps.value ? '1' : '0',
            only_nonzero: onlyNonZero.value ? '1' : '0',
            type: typeFilter.value || undefined,
            status: statusFilter.value || undefined,
            payment_method_id: paymentMethodId.value || undefined,
            branch_id: selectedBranch.value || undefined,
            view: viewMode.value || undefined,
            tx_per_page: String(txPerPage.value),
            tx_page: String(txPage.value),
        }
    });
    fetchStatement();
    
    // Reset flag after a short delay to allow route.query watcher to detect the set flag
    setTimeout(() => { _isApplyingFilter = false; }, 100);
};

const setQuickRange = (kind) => {
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
    }
    applyFilter();
};

// ─── Export Functions ─────────────────────────────────────────────────────

const exportPdf = () => {
    if (!data.value) return;
    const titleText = `${title.value} (${startDate.value} → ${endDate.value})`;
    const rowsHtml = (data.value.transactions || []).map(t => `
        <tr>
            <td>${formatDate(t.date)}</td>
            <td>${refNumber(t.reference)}</td>
            <td>${t.reference_label || refTypeLabel(t.reference)}</td>
            <td style="text-align:right">${(t.description || '-').toString().replace(/</g, '&lt;')}</td>
            <td style="color:#b91c1c">${t.debit > 0 ? Number(t.debit).toFixed(2) : '-'}</td>
            <td style="color:#065f46">${t.credit > 0 ? Number(t.credit).toFixed(2) : '-'}</td>
            <td style="font-weight:600">${Number(t.balance || 0).toFixed(2)}</td>
        </tr>
    `).join('');
    const totalsHtml = `
        <div style="margin-top:12px;font-size:12px;color:#374151">
            <span style="margin-inline:8px">إجمالي مدين: <b>${Number(data.value.total_debit || 0).toFixed(2)}</b></span>
            <span style="margin-inline:8px">إجمالي دائن: <b>${Number(data.value.total_credit || 0).toFixed(2)}</b></span>
            <span style="margin-inline:8px">رصيد ختامي: <b>${Number(data.value.closing_balance || 0).toFixed(2)}</b></span>
        </div>`;
    const html = `<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8"/>
        <title>${titleText}</title>
        <style>
            body{font-family:Tajawal,Arial,Segoe UI,Helvetica,sans-serif;color:#111827;margin:24px}
            h1{font-size:18px;margin:0 0 12px 0}
            .meta{font-size:12px;color:#6b7280;margin-bottom:12px}
            table{width:100%;border-collapse:collapse;font-size:12px}
            thead{background:#f9fafb}
            th,td{border:1px solid #e5e7eb;padding:8px;text-align:center}
            .kpis{display:flex;gap:12px;margin:12px 0}
            .kpis div{background:#f3f4f6;padding:8px 12px;border-radius:6px}
            @media print{@page{size:A4;margin:12mm}}
        </style>
        </head><body>
        <h1>${titleText}</h1>
        <div class="meta">رقم الحساب: ${data.value.account?.code || ''} • الاسم: ${data.value.account?.name || ''}</div>
        <div class="kpis">
            <div>رصيد افتتاحي: <b>${Number(data.value.opening_balance || 0).toFixed(2)}</b></div>
            <div>مدين: <b>${Number(data.value.total_debit || 0).toFixed(2)}</b></div>
            <div>دائن: <b>${Number(data.value.total_credit || 0).toFixed(2)}</b></div>
            <div>رصيد ختامي: <b>${Number(data.value.closing_balance || 0).toFixed(2)}</b></div>
        </div>
        <table>
            <thead><tr>
                <th>التاريخ</th><th>رقم العملية</th><th>نوع العملية</th>
                <th>الوصف</th><th>مدين</th><th>دائن</th><th>الرصيد</th>
            </tr></thead>
            <tbody>${rowsHtml}</tbody>
        </table>
        ${totalsHtml}
        </body></html>`;
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.open();
    win.document.write(html);
    win.document.close();
    try {
        win.onload = () => {
            win.print();
            setTimeout(() => { try { win.close(); } catch (_) {} }, 300);
        };
    } catch (_) {}
};

const exportCsv = () => {
    if (!data.value || !Array.isArray(data.value.transactions)) return;
    const rows = [['التاريخ', 'رقم العملية', 'نوع العملية', 'الوصف', 'مدين', 'دائن', 'الرصيد']];
    data.value.transactions.forEach(t => {
        rows.push([
            formatDate(t.date),
            refNumber(t.reference),
            (t.reference_label || refTypeLabel(t.reference)),
            t.description || '-',
            t.debit > 0 ? Number(t.debit).toFixed(2) : '0.00',
            t.credit > 0 ? Number(t.credit).toFixed(2) : '0.00',
            Number(t.balance || 0).toFixed(2)
        ]);
    });
    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `account_statement_${startDate.value}_${endDate.value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportReferencesCsv = () => {
    const items = referencesItems.value || [];
    if (!items.length) return;
    const rows = [['التاريخ', 'النوع', 'المرجع', 'رقم الفاتورة', 'الحالة', 'الصافي', 'الضريبة', 'الإجمالي', 'المدفوع', 'المتبقي', 'قيد محاسبي']];
    items.forEach(r => {
        rows.push([
            r.date || '',
            r.reference ? refTypeLabel(r.reference) : (r.type || ''),
            r.reference || '',
            r.invoice_number || '',
            r.status_label || r.status || '',
            Number(r.net_total_amount || 0).toFixed(2),
            Number(r.tax_amount || 0).toFixed(2),
            Number(r.total_amount || grossTotal(r) || 0).toFixed(2),
            Number(r.paid_amount || 0).toFixed(2),
            Number(r.outstanding || 0).toFixed(2),
            r.has_journal ? '1' : '0',
        ]);
    });
    const escapeCsv = (v) => {
        if (v === null || v === undefined) return '';
        const s = String(v);
        return /[\n\r",]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const csv = rows.map(row => row.map(escapeCsv).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const party = String(partyInfo.value?.name || '').trim() || 'party';
    a.href = url;
    a.download = `references_${party}_${referencesSubTab.value}_${startDate.value}_${endDate.value}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
};

// ─── Lifecycle Hooks ──────────────────────────────────────────────────────

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

    fetchSettings();

    // Restore persisted UI state
    try {
        const sTab = localStorage.getItem('references_sub_tab');
        if (sTab && (sTab === 'invoices' || sTab === 'vouchers' || sTab === 'payments')) referencesSubTab.value = sTab;
    } catch (_) {}

    // Load company info from localStorage first, then fallback to API
    try {
        companyInfo.value.name = localStorage.getItem('pos_company_name') || localStorage.getItem('company_name') || '';
        companyInfo.value.phone = localStorage.getItem('pos_company_phone') || localStorage.getItem('company_phone') || '';
        companyInfo.value.logo = getImageUrl(localStorage.getItem('pos_company_logo') || localStorage.getItem('company_logo')) || '';
        companyInfo.value.email = localStorage.getItem('pos_company_email') || localStorage.getItem('company_email') || '';
        const addrPartsLS = [
            localStorage.getItem('pos_company_address') || localStorage.getItem('company_address') || '',
            localStorage.getItem('pos_company_city') || localStorage.getItem('company_city') || '',
            localStorage.getItem('pos_company_state') || localStorage.getItem('company_state') || '',
            localStorage.getItem('pos_company_country') || localStorage.getItem('company_country') || ''
        ].filter(Boolean);
        companyInfo.value.address = addrPartsLS.join(', ');

        if (!companyInfo.value.name || !companyInfo.value.address || !companyInfo.value.email || !companyInfo.value.phone) {
            try {
                const s2 = await settingsStore.fetchSettings();
                const raw = s2?.data || s2?.settings || s2 || {};
                const get = (k) => raw[k] ?? raw[k.replace(/\./g, '_')] ?? '';
                const addrParts2 = [get('company.address'), get('company.city'), get('company.state'), get('company.country')].filter(Boolean);
                companyInfo.value.name = get('company.name') || companyInfo.value.name;
                companyInfo.value.address = addrParts2.join(', ') || companyInfo.value.address;
                companyInfo.value.phone = get('company.phone') || companyInfo.value.phone;
                companyInfo.value.email = get('company.email') || companyInfo.value.email;
                if (!companyInfo.value.logo) companyInfo.value.logo = get('company.logo') || get('company.logo_url') || '';
            } catch (_) { /* ignore */ }
        }
    } catch (_) { /* ignore */ }

    // Load party info (customer / supplier)
    try {
        if (type.value === 'customers') {
            let c = null;
            try {
                const r1 = await customerStore.fetchCustomerById(id.value);
                c = r1;
            } catch (_) {}
            if (!c) {
                try {
                    const r2 = await customerStore.fetchCustomers();
                    const arr2 = r2.data || [];
                    if (Array.isArray(arr2)) c = arr2.find(x => Number(x.id) === Number(id.value)) || null;
                } catch (_) {}
            }
            if (c) {
                partyInfo.value = {
                    name: c.name || c.company_name || c.contact_name || '',
                    address: c.address || c.billing_address || '',
                    phone: c.phone || c.mobile || '',
                    email: c.email || ''
                };
            }
        } else if (type.value === 'suppliers') {
            let s = null;
            try {
                const r1 = await supplierStore.fetchSupplierById(id.value);
                s = r1;
            } catch (_) {}
            if (!s) {
                try {
                    const r2 = await supplierStore.fetchSuppliers();
                    const arr2 = r2.data || [];
                    if (Array.isArray(arr2)) s = arr2.find(x => Number(x.id) === Number(id.value)) || null;
                } catch (_) {}
            }
            if (s) {
                partyInfo.value = {
                    name: s.name || s.company_name || s.contact_name || '',
                    address: s.address || s.billing_address || '',
                    phone: s.phone || s.mobile || '',
                    email: s.email || ''
                };
            }
        }
    } catch (_) { /* ignore */ }

    // Load payment methods and branches
    try {
        await paymentStore.fetchPaymentMethods();
        // Payment methods are now cached in paymentStore
    } catch (_) { /* paymentMethods computed from paymentStore */ }
    try {
        await branchStore.fetchBranches();
        // branches computed from branchStore
    } catch (_) { /* branches computed from branchStore */ }

    fetchStatement();
});

// ─── Combined Pagination Watcher (prevent double fetch) ───────────────────
// مراقبة موحدة لتجنب Double Fetch
const previousFilter = ref({ 
  startDate: startDate.value, 
  endDate: endDate.value 
});

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
        page: page.value,
        per_page: perPage.value
    });

    if (result.status !== 'success') {
        handleStatementError(result);
    }
}, { deep: true });

// Persist sub-tab choice
watch(referencesSubTab, (v) => {
    try { localStorage.setItem('references_sub_tab', v); } catch (_) {}
    refsPage.value = 1;
});

// ─── Error Handler (from unified composable) ──────────────────────────────

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

    if (type === 'server_error') {
        showToast('حدث خطأ في السيرفر. يرجى المحاولة لاحقاً.', 'error');
        return;
    }

    showToast(message || 'فشل تحميل البيانات', 'error');
};

// Re-fetch when route query changes (browser back/forward, external navigation)
watch(() => route.query, (q) => {
    // Skip if this is a programmatic update from applyFilter
    if (_isApplyingFilter) return;
    
    if (q.start_date) startDate.value = q.start_date;
    if (q.end_date) endDate.value = q.end_date;
    if (typeof q.fill_gaps !== 'undefined') fillGaps.value = q.fill_gaps !== '0';
    if (typeof q.only_nonzero !== 'undefined') onlyNonZero.value = q.only_nonzero === '1';
    if (typeof q.type !== 'undefined') typeFilter.value = q.type;
    if (typeof q.status !== 'undefined') statusFilter.value = q.status;
    if (typeof q.payment_method_id !== 'undefined') paymentMethodId.value = q.payment_method_id;
    if (typeof q.branch_id !== 'undefined') selectedBranch.value = q.branch_id;
    if (typeof q.view !== 'undefined') viewMode.value = q.view;
    if (typeof q.tx_per_page !== 'undefined') txPerPage.value = Number(q.tx_per_page) || 20;
    if (typeof q.tx_page !== 'undefined') txPage.value = Number(q.tx_page) || 1;
    fetchStatement();
}, { deep: true });
</script>

<style scoped>



/* ── Form Controls ── */
.form-input-modern,
.form-select-modern {
    @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm text-sm;
}
.filter-label {
    @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1 block;
}
.quick-range-btn {
    @apply px-3 py-1.5 rounded-xl bg-slate-50 text-slate-500 text-[10px] font-black hover:bg-slate-100 transition-all border border-transparent hover:border-slate-200;
}

/* ── KPI Cards ── */
.kpi-box {
    @apply bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all;
}
.kpi-label-modern {
    @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1;
}
.kpi-val-modern {
    @apply text-xl font-black font-mono tracking-tighter;
}

/* ── Tabs & View Mode Buttons ── */
.tab-pill {
    @apply px-6 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-2;
}

/* ── Tab Cards (Professional Grid) ── */
.tab-card {
    @apply w-full p-4 rounded-[1.5rem] border border-slate-200 bg-white/50 hover:bg-white transition-all cursor-pointer text-right;
}
.tab-card-active-accounting {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-blue-300 bg-white shadow-md transition-all cursor-pointer text-right;
}
.tab-card-active-daily {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-emerald-300 bg-white shadow-md transition-all cursor-pointer text-right;
}
.tab-card-active-references {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-purple-300 bg-white shadow-md transition-all cursor-pointer text-right;
}
.tab-icon {
    @apply w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 transition-all flex-shrink-0;
}
.tab-icon-active {
    @apply w-10 h-10 rounded-xl flex items-center justify-center transition-all flex-shrink-0;
}
.tab-card-active-accounting .tab-icon-active {
    background-color: #dbeafe;
    color: #3b82f6;
}
.tab-card-active-daily .tab-icon-active {
    background-color: #d1fae5;
    color: #16a34a;
}
.tab-card-active-references .tab-icon-active {
    background-color: #f3e8ff;
    color: #9333ea;
}
.tab-card:hover .tab-icon {
    background-color: #e2e8f0;
}

.vmode-btn {
    @apply px-4 py-1.5 rounded-xl text-[10px] font-black text-slate-400 hover:text-slate-600 transition-all;
}
.vmode-btn-active {
    @apply px-5 py-1.5 rounded-xl bg-white text-blue-600 shadow-sm font-black ring-1 ring-slate-100;
}

/* ── View Mode Cards (Professional Grid) ── */
.vmode-card {
    @apply w-full p-4 rounded-[1.5rem] border border-slate-200 bg-white/50 hover:bg-white transition-all cursor-pointer text-right;
}
.vmode-card-active {
    @apply w-full p-4 rounded-[1.5rem] border-2 bg-white shadow-md transition-all cursor-pointer text-right;
}
.vmode-card-active.text-blue-600 {
    border-color: #3b82f6;
}
.vmode-icon {
    @apply w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 transition-all flex-shrink-0;
}
.vmode-icon-active {
    @apply w-10 h-10 rounded-xl flex items-center justify-center transition-all flex-shrink-0;
}
.vmode-card-active:has(.vmode-icon-active.text-blue-600) .vmode-icon-active {
    background-color: #dbeafe;
    color: #3b82f6;
}
.vmode-card-active:has(.vmode-icon-active.text-emerald-600) .vmode-icon-active {
    background-color: #d1fae5;
    color: #16a34a;
}
.vmode-card-active:has(.vmode-icon-active.text-purple-600) .vmode-icon-active {
    background-color: #f3e8ff;
    color: #9333ea;
}
.vmode-card:hover .vmode-icon {
    background-color: #e2e8f0;
}

/* ── Sub-Tab Cards (Professional Grid) ── */
.subtab-card {
    @apply w-full p-4 rounded-[1.5rem] border border-slate-200 bg-white/50 hover:bg-white transition-all cursor-pointer text-right;
}
.subtab-card-active-invoices {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-blue-300 bg-white shadow-md transition-all cursor-pointer text-right;
}
.subtab-card-active-vouchers {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-emerald-300 bg-white shadow-md transition-all cursor-pointer text-right;
}

.subtab-card-active-payments {
    @apply w-full p-4 rounded-[1.5rem] border-2 border-purple-300 bg-white shadow-md transition-all cursor-pointer text-right;
}
.subtab-icon {
    @apply w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 transition-all flex-shrink-0;
}
.subtab-icon-active {
    @apply w-10 h-10 rounded-xl flex items-center justify-center transition-all flex-shrink-0;
}
.subtab-card-active-invoices .subtab-icon-active {
    background-color: #dbeafe;
    color: #3b82f6;
}
.subtab-card-active-vouchers .subtab-icon-active {
    background-color: #d1fae5;
    color: #16a34a;
}
.subtab-card:hover .subtab-icon {
    background-color: #e2e8f0;
}

.page-btn {
    @apply w-9 h-9 rounded-xl bg-white border border-slate-100 flex items-center justify-center hover:bg-slate-50 disabled:opacity-30 transition-all;
}

/* ── Status Badge ── */
.status-badge {
    @apply inline-flex items-center px-2.5 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-tighter;
}

/* ── Aging Cards ── */
.aging-card {
    @apply p-5 rounded-[1.5rem] border-2 text-center transition-all hover:scale-105;
}

/* ── Row color coding ── */
.border-r-rose-200   { border-right-color: #fecaca; }
.border-r-emerald-200 { border-right-color: #a7f3d0; }
.border-r-blue-200   { border-right-color: #bfdbfe; }

/* ── Sticky table headers ── */
table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    box-shadow: 0 1px 0 rgba(0,0,0,0.06);
}

/* ── Layout ── */
.report-main-container {
    @apply w-full;
}

/* ── Animation ── */
.animate-fadeIn {
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Print ── */
@media print {
    .animate-fadeIn, details, .sticky, .page-btn,
    .tab-pill, .vmode-btn, .vmode-btn-active,
    .quick-range-btn, .filter-label { display: none !important; }
    .bg-\[#f8fafc\] { background: white !important; }
    .report-main-container { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .bg-white, .rounded-\[2\.5rem\], .rounded-\[2rem\] { box-shadow: none !important; border: none !important; }
}
</style>