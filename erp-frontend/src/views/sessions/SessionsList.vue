<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-cash-register text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة جلسات الكاشير</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">مراقبة الورديات، تصفية الجلسات، وتحليل الفروقات النقدية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="showFilters = !showFilters" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-2', showFilters ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-transparent text-slate-600 hover:bg-slate-50']">
          <i class="fas fa-filter"></i>
          {{ showFilters ? 'إخفاء الفلاتر' : 'البحث والتصفية' }}
        </button>
        <button @click="fetchData" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-sync-alt" :class="{'animate-spin': loading}"></i> تحديث البيانات
        </button>
        <button @click="openRenameDevice" class="px-5 py-2.5 rounded-xl text-xs font-black text-blue-600 hover:bg-blue-50 transition-all flex items-center gap-2">
          <i class="fas fa-edit"></i> تسمية الجهاز
        </button>
      </div>
    </div>

    <!-- Filters Panel (Collapsible) - ABOVE KPI -->
    <transition name="slide">
      <div v-if="showFilters" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible transition-all">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الجهاز / نقطة البيع</label>
            <select class="form-select-modern font-bold text-xs" v-model="deviceFilter" @change="applyFilters">
              <option value="">كل الاجهزة</option>
              <option v-for="d in deviceOptions" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">نوع الوردية</label>
            <select class="form-select-modern font-bold text-xs" v-model="sessionType" @change="applyFilters">
              <option value="">كل الورديات</option>
              <option value="morning">صباحية</option>
              <option value="evening">مسائية</option>
              <option value="daily">يومية</option>
              <option value="manual">يدوية</option>
            </select>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">حالة الجلسة</label>
            <select class="form-select-modern font-bold text-xs" v-model="filters.statusFilter.value" @change="applyFilters">
              <option value="">كل الحالات</option>
              <option value="open">مفتوحة حالياً</option>
              <option value="closed">مغلقة ومصفاة</option>
            </select>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفروقات النقدية</label>
            <select class="form-select-modern font-bold text-xs" v-model="hasVariance" @change="applyFilters">
              <option value="">الكل</option>
              <option value="yes">يوجد عجز/زيادة</option>
              <option value="no">مطابق تماماً</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div v-if="isExempt" class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع / المستودع</label>
            <select class="form-select-modern font-bold text-xs" v-model="filters.selectedBranch.value" @change="applyFilters">
              <option value="">كل الفروع</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="fromDateRef" type="date" v-model="dateFrom" class="form-input-modern font-bold text-xs" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="fromDateRef.showPicker()"
              ></i>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">إلى تاريخ</label>
            <div class="relative">
              <input ref="toDateRef" type="date" v-model="dateTo" class="form-input-modern font-bold text-xs" />
              <i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="toDateRef.showPicker()"
              ></i>
            </div>
          </div>

          <div class="flex items-end">
            <button @click="resetFilters" class="h-[46px] w-full rounded-2xl bg-slate-100 text-slate-600 font-black text-xs hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
              <i class="fas fa-sync-alt"></i> إعادة تعيين
            </button>
          </div>
        </div>

        <!-- Filter Chips -->
        <div class="flex flex-wrap gap-2 mt-6 pt-6 border-t border-slate-50" v-if="hasActiveSessionFilters">
          <div class="flex items-center gap-2 w-full">
            <div class="flex items-center gap-2 text-blue-600 bg-blue-50 px-3 py-2 rounded-lg border border-blue-100">
              <i class="fas fa-check-circle text-xs"></i>
              <span class="text-[10px] font-black uppercase tracking-wider">تصفية نشطة</span>
            </div>
            <div class="flex flex-wrap gap-2 flex-1">
              <span v-if="deviceFilter" class="filter-chip group">
                <i class="fas fa-desktop ml-1 text-[9px]"></i>{{ deviceFilter }}
                <i @click="deviceFilter = ''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="sessionType" class="filter-chip group">
                <i class="fas fa-briefcase ml-1 text-[9px]"></i>{{ sessionType }}
                <i @click="sessionType = ''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.statusFilter.value" class="filter-chip group">
                <i class="fas fa-info-circle ml-1 text-[9px]"></i>{{ filters.statusFilter.value }}
                <i @click="filters.statusFilter.value = ''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="dateFrom" class="filter-chip group">
                <i class="fas fa-calendar-left ml-1 text-[9px]"></i>{{ dateFrom }}
                <i @click="dateFrom = ''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="dateTo" class="filter-chip group">
                <i class="fas fa-calendar-right ml-1 text-[9px]"></i>{{ dateTo }}
                <i @click="dateTo = ''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div>
            <p class="kpi-label">عدد الجلسات</p>
            <p class="kpi-value text-slate-800">{{ total }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي النقد</p>
            <p class="kpi-value text-emerald-600">{{ formatCurrency(totalCash) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
            <i class="fas fa-balance-scale"></i>
          </div>
          <div>
            <p class="kpi-label">عدد الفروقات</p>
            <p class="kpi-value text-amber-600">{{ sessionsWithVariance }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Results Table Section -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
        <div class="flex items-center gap-3">
          <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
          <h2 class="text-lg font-black text-slate-800 leading-none">سجل الجلسات</h2>
        </div>
        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm">
          إجمالي النتائج: {{ total }} جلسة
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">الجلسة والكاشير</th>
              <th class="px-4 py-5">الجهاز والمحطة</th>
              <th class="px-4 py-5">التوقيت (فتح / إغلاق)</th>
              <th class="px-4 py-5">المخلص المالي للدرج</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-4 py-5 text-center">الفرق</th>
              <th class="px-6 py-5 text-center">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="loading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!loading && items.length === 0" class="text-center py-20">
              <td colspan="7" class="py-24 opacity-20 text-slate-400 flex flex-col items-center">
                <i class="fas fa-history text-6xl mb-4"></i>
                <p class="font-black text-sm uppercase">لا توجد جلسات مسجلة</p>
              </td>
            </tr>
            <tr v-for="row in items" :key="row.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-6 py-4">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-blue-600 group-hover:bg-white transition-all"><i class="fas fa-receipt text-sm"></i></div>
                  <div class="flex flex-col">
                    <span class="font-black text-slate-900 leading-none">#{{ row.id }}</span>
                    <span class="text-[10px] text-slate-400 font-bold mt-1.5">{{ row.cashier_name || 'كاشير' }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-xs font-black text-slate-500 uppercase tracking-tight">
                 <div class="flex items-center gap-2"><i class="fas fa-desktop text-slate-300"></i> {{ deviceLabel(row) }}</div>
                 <div class="text-[9px] text-slate-300 mt-1">{{ formatCode(row) }}</div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-1">
                  <div class="flex items-center gap-2 text-[10px] font-black text-emerald-600 uppercase tracking-tighter">
                    <i class="fas fa-door-open text-[8px]"></i> {{ formatDateTime(row.start_time) }}
                  </div>
                  <div v-if="row.end_time" class="flex items-center gap-2 text-[10px] font-black text-rose-500 uppercase tracking-tighter">
                    <i class="fas fa-door-closed text-[8px]"></i> {{ formatDateTime(row.end_time) }}
                  </div>
                  <span v-else class="text-[9px] font-black text-amber-500 uppercase px-2 py-0.5 bg-amber-50 rounded-lg w-fit">نشطة حالياً</span>
                </div>
              </td>
              <td class="px-4 py-4">
                <div class="min-w-[160px] space-y-2">
                  <div v-if="!summaries[row.id]" class="animate-pulse flex items-center gap-2"><div class="w-2 h-2 bg-slate-200 rounded-full"></div><div class="h-2 bg-slate-100 rounded-full w-20"></div></div>
                  <template v-else>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-tight">
                       <span class="text-slate-400">المدفوعات:</span>
                       <span class="text-blue-600">{{ formatCurrency(summaries[row.id]?.totals?.payments || 0) }}</span>
                    </div>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-tight pt-1 border-t border-slate-50">
                       <span class="text-slate-400">النقد المتوقع:</span>
                       <span class="text-slate-900 font-mono tracking-tighter">{{ formatCurrency(summaries[row.id]?.calculated?.expected_cash || 0) }}</span>
                    </div>
                  </template>
                </div>
              </td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', statusClass(row.status)]">{{ statusLabel(row.status) }}</span>
              </td>
              <td class="px-4 py-4 text-center font-black font-mono tracking-tighter text-base">
                <template v-if="summaries[row.id] && !summaries[row.id]?._partial">
                  <!-- Full summary loaded: show calculated variance (closing - expected) -->
                  <span v-if="summaries[row.id]?.calculated?.variance_amount !== null && summaries[row.id]?.calculated?.variance_amount !== undefined"
                        :class="summaries[row.id].calculated.variance_amount > 0 ? 'text-emerald-600' : summaries[row.id].calculated.variance_amount < 0 ? 'text-rose-600' : 'text-slate-400'">
                    {{ formatCurrency(summaries[row.id].calculated.variance_amount) }}
                  </span>
                  <span v-else class="text-slate-200">—</span>
                </template>
                <template v-else>
                  <!-- Partial / loading: show list variance_amount as approximation -->
                  <span v-if="row.variance_amount !== null && row.variance_amount !== undefined && row.variance_amount !== 0"
                        :class="row.variance_amount > 0 ? 'text-emerald-600' : 'text-rose-600'">
                    {{ formatCurrency(row.variance_amount) }}
                  </span>
                  <span v-else-if="summaries[row.id]?._partial" class="text-slate-300 text-xs animate-pulse">...</span>
                  <span v-else class="text-slate-200">—</span>
                </template>
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="toggleDetails(row)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="عرض التفاصيل"><i class="fas fa-ellipsis-v"></i></button>
                  <button class="w-9 h-9 rounded-xl bg-slate-50 text-slate-300 flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all active:scale-95 shadow-sm" title="طباعة التقرير"><i class="fas fa-print"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          صفحة <span class="text-slate-800">{{ currentPage }}</span> من <span class="text-slate-800">{{ totalPages }}</span>
          (إجمالي <span class="text-slate-800">{{ total }}</span> جلسة)
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400">النتائج:</span>
            <select v-model.number="perPage" @change="applyFilters" class="h-10 text-xs font-black border-slate-200 rounded-xl bg-white px-3 outline-none border">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-center gap-1">
            <button @click="goToPreviousPage" :disabled="currentPage <= 1" class="pagination-btn">
              <i class="fas fa-angle-right"></i> السابق
            </button>
            <button @click="goToNextPage" :disabled="currentPage >= totalPages" class="pagination-btn">
              التالي <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Details Sidebar Drawer -->
    <transition name="drawer">
      <div v-if="detailsSession" class="fixed inset-0 z-[110] flex justify-end bg-slate-900/60 backdrop-blur-sm" @click="detailsSession = null">
        <div class="w-full max-w-2xl h-full bg-white shadow-2xl flex flex-col overflow-hidden" @click.stop>
          <div class="px-8 py-7 border-b border-slate-50 flex items-center justify-between shrink-0 bg-slate-50/50">
            <div class="flex items-center gap-4">
               <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center text-white"><i class="fas fa-info-circle text-xl"></i></div>
               <div>
                  <h2 class="text-xl font-black text-slate-900 leading-none">تفاصيل الجلسة #{{ detailsSession.id }}</h2>
                  <p class="text-[10px] text-slate-400 font-bold uppercase mt-2 tracking-widest">{{ getbranchName(detailsSession.branch_id) }} | محطة: {{ deviceLabel(detailsSession) }}</p>
               </div>
            </div>
            <button @click="detailsSession = null" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 hover:text-rose-500 transition-all"><i class="fas fa-times text-2xl"></i></button>
          </div>

          <div class="flex-grow overflow-y-auto custom-scroll p-8 space-y-10">
            <!-- Session Status Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 p-6 rounded-[2rem] bg-slate-900 text-white shadow-xl">
               <div class="flex items-center gap-3">
                  <span :class="[detailsSession.status === 'closed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400']" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest flex items-center gap-2 border border-white/5">
                    <i :class="detailsSession.status === 'closed' ? 'fas fa-check-circle' : 'fas fa-clock'"></i>
                    {{ detailsSession.status === 'closed' ? 'مغلقة' : 'مفتوحة' }}
                  </span>
                  <div class="text-[10px] font-bold text-white/50 uppercase tracking-tighter border-r border-white/10 pr-3 mr-1">
                    <i class="far fa-calendar-alt ml-1"></i> {{ formatDate(detailsSession.start_time) }}
                  </div>
               </div>
               <div class="text-xs font-black font-mono tracking-tighter text-blue-400">
                  {{ formatTime(detailsSession.start_time) }} <i class="fas fa-caret-left mx-2"></i> {{ detailsSession.end_time ? formatTime(detailsSession.end_time) : 'الآن' }}
               </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
               <div class="p-6 rounded-3xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-2">الرصيد الافتتاحي</p><p class="text-xl font-black text-slate-800">{{ formatCurrency(summaries[detailsSession.id]?.calculated?.opening_balance || 0) }}</p></div>
               <div class="p-6 rounded-3xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-2">إجمالي المبيعات</p><p class="text-xl font-black text-emerald-600">+ {{ formatCurrency(summaries[detailsSession.id]?.totals?.cash_in || 0) }}</p></div>
               <div class="p-6 rounded-3xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-2">إجمالي المصروفات</p><p class="text-xl font-black text-rose-600">- {{ formatCurrency(summaries[detailsSession.id]?.totals?.cash_out || 0) }}</p></div>
               <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 shadow-lg"><p class="text-[9px] font-black text-blue-400 uppercase mb-2">النقد المتوقع بالدرج</p><p class="text-xl font-black text-white">{{ formatCurrency(summaries[detailsSession.id]?.calculated?.expected_cash || 0) }}</p></div>
            </div>

            <!-- Transactions Detailed Table -->
            <div class="space-y-4">
              <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2"><i class="fas fa-exchange-alt text-blue-600"></i> سجل المعاملات التفصيلي</h3>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                      <th class="px-6 py-4">الوقت</th>
                      <th class="px-4 py-4">النوع</th>
                      <th class="px-4 py-4 text-left">القيمة</th>
                      <th class="px-6 py-4">الملاحظات</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-for="tx in transactions[detailsSession.id]" :key="tx.id" class="hover:bg-slate-50 transition-all font-bold">
                      <td class="px-6 py-4 text-slate-400 font-mono tracking-tighter">{{ formatTime(tx.created_at) }}</td>
                      <td class="px-4 py-4"><span :class="getTransactionTypeClass(tx.type)">{{ getTransactionTypeLabel(tx.type) }}</span></td>
                      <td class="px-4 py-4 text-left font-mono font-black" :class="isExpense(tx.type) ? 'text-rose-600' : 'text-emerald-600'">{{ isExpense(tx.type) ? '-' : '+' }}{{ formatCurrency(tx.amount) }}</td>
                      <td class="px-6 py-4 text-slate-400 italic max-w-[150px] truncate" :title="tx.notes">{{ tx.notes || '—' }}</td>
                    </tr>
                    <tr v-if="!transactions[detailsSession.id]?.length">
                       <td colspan="4" class="py-12 text-center text-slate-300 font-black uppercase tracking-widest">لا توجد حركات مسجلة</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Variance and Closing Reason -->
            <div v-if="summaries[detailsSession.id]?.calculated?.variance_amount" class="p-8 rounded-[2rem] bg-amber-50 border-2 border-amber-100 space-y-4">
               <div class="flex items-center justify-between">
                  <h4 class="text-xs font-black text-amber-800 uppercase tracking-widest">تقرير الفروقات والتصفية</h4>
                  <span class="text-lg font-black text-amber-900 font-mono tracking-tighter">{{ formatCurrency(summaries[detailsSession.id].calculated.variance_amount) }}</span>
               </div>
               <p class="text-xs font-bold text-amber-700 leading-relaxed italic">{{ summaries[detailsSession.id]?.session?.variance_reason || 'تم إغلاق الجلسة دون تبرير للفروقات المذكورة.' }}</p>
            </div>
          </div>
          
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
             <button @click="detailsSession = null" class="px-10 py-3 rounded-2xl bg-slate-900 text-white font-black text-xs uppercase tracking-widest shadow-xl shadow-slate-200 active:scale-95 transition-all">إغلاق النافذة</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Rename Device Modal (Logic Preserved) -->
    <transition name="modal">
      <div v-if="showRenameDevice" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-10 text-center">
             <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-[2.5rem] flex items-center justify-center mx-auto mb-6 shadow-sm"><i class="fas fa-edit text-3xl"></i></div>
             <h3 class="text-xl font-black text-slate-900 leading-none">تسمية جهاز نقطة البيع</h3>
             <p class="text-slate-400 text-sm mt-4 leading-relaxed font-bold">أدخل اسماً مخصصاً لهذا الجهاز (محلياً) ليظهر في سجلات الجلسات والورديات.</p>
             
             <div class="mt-8 space-y-4 text-right">
                <label class="modal-label">اسم الجهاز المفضل</label>
                <input v-model.trim="deviceNameInput" type="text" class="form-input-modern h-12 font-black" placeholder="مثال: كاشير الاستقبال - الدور الأول" />
             </div>

             <div class="grid grid-cols-2 gap-4 mt-10">
                <button @click="showRenameDevice = false" class="h-12 rounded-2xl border-2 border-slate-50 font-black text-slate-400 text-xs">إلغاء</button>
                <button @click="saveDeviceName" class="h-12 rounded-2xl bg-blue-600 text-white font-black text-xs shadow-xl active:scale-95 transition-all">حفظ الاسم</button>
             </div>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useSessionStore } from '@/stores/session/sessionStore';
import { useBranchStore } from '@/stores/branch';
import { useAuthStore } from '@/stores/auth';
import { useBootstrapStore } from '@/stores/bootstrap';
import { useToast } from '@/composables/useToast';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useDateValidation } from '@/composables/useDateValidation';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useHistoryFilters } from '@/composables/useHistoryFilters';
import { getLocalDateISO } from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';

// --- Stores & Composables ---
const branchStore = useBranchStore();
const authStore   = useAuthStore();
const bootstrapStore = useBootstrapStore();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const { showToast } = useToast();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { validateDateRange } = useDateValidation();
const formatCurrency = (amount) => formatCurrencyLocale(amount, 2);
// ─── useHistoryFilters for common filters (date, page, perPage, branch, status, showFilters) ───
const filters = useHistoryFilters('sessions_hist_filters');
filters.loadFromLocalStorage();

// Expose common filter aliases for readability
const currentPage  = filters.page;
const perPage      = filters.perPage;
const dateFrom     = filters.dateFrom;
const dateTo       = filters.dateTo;
const showFilters  = filters.showFilters;
// selectedBranch is used via filters.selectedBranch
// statusFilter is reused for session status (open/closed)

const branches = computed(() => branchStore.branches);

// --- Session-specific filters (not in any composable — unique to this page) ---
const sessionType  = ref('');
const deviceFilter = ref('');
const hasVariance  = ref('');

// --- State ---
const loading    = ref(false);
const items      = ref([]);
const summaries  = ref({});
const transactions = ref({});
const detailsSession = ref(null);
const totalCount = ref(0);

// ✅ KPI from backend — aggregated over full filtered set (not just current page)
const kpiTotalExpectedCash    = ref(0);
const kpiSessionsWithVariance = ref(0);

const fromDateRef = ref(null);
const toDateRef   = ref(null);

// --- Computed ---
const deviceOptions = computed(() => {
  const set = new Set();
  for (const it of items.value) {
    if (it.device_name)       set.add(String(it.device_name));
    else if (it.device_id)   set.add(String(it.device_id));
    else if (it.device)      set.add(String(it.device));
  }
  try {
    const custom = (localStorage.getItem('pos_device_name') || '').trim();
    if (custom) set.add(custom);
  } catch {}
  return Array.from(set).filter(Boolean);
});

const total      = computed(() => totalCount.value);
const totalPages = computed(() => Math.max(1, Math.ceil((totalCount.value || 0) / perPage.value)));

// ✅ KPI refs — set from backend response, not computed from current page items
const totalCash           = computed(() => kpiTotalExpectedCash.value);
const sessionsWithVariance = computed(() => kpiSessionsWithVariance.value);

const hasActiveSessionFilters = computed(() => !!(
  deviceFilter.value     ||
  sessionType.value      ||
  filters.statusFilter.value ||
  dateFrom.value         ||
  dateTo.value           ||
  filters.selectedBranch.value ||
  hasVariance.value
));

// --- Device rename ---
const showRenameDevice = ref(false);
const deviceNameInput  = ref('');

function openRenameDevice() {
  try { deviceNameInput.value = localStorage.getItem('pos_device_name') || ''; } catch { deviceNameInput.value = ''; }
  showRenameDevice.value = true;
}

function saveDeviceName() {
  const val = String(deviceNameInput.value || '').trim();
  try {
    if (val) localStorage.setItem('pos_device_name', val.slice(0, 64));
    else     localStorage.removeItem('pos_device_name');
    showRenameDevice.value = false;
    items.value = items.value.slice(); // force deviceOptions recompute
    showToast(val ? 'تم حفظ اسم الجهاز' : 'تمت إعادة الاسم الافتراضي', 'success');
  } catch (_) {
    showToast('تعذر حفظ اسم الجهاز محليًا', 'error');
  }
}

// --- Pagination ---
function goToNextPage() {
  if (currentPage.value < totalPages.value) { currentPage.value++; fetchData(); }
}
function goToPreviousPage() {
  if (currentPage.value > 1) { currentPage.value--; fetchData(); }
}

function applyFilters() {
  currentPage.value = 1;
  fetchData();
}

function resetFilters() {
  filters.resetFilters();          // resets date, page, perPage, status, branch
  sessionType.value  = '';
  deviceFilter.value = '';
  hasVariance.value  = '';
  fetchData();
}

// --- Data fetching ---
async function fetchData() {
  if (dateFrom.value && dateTo.value) {
    if (!validateDateRange(dateFrom.value, dateTo.value)) {
      loading.value = false;
      return;
    }
  }

  loading.value = true;
  try {
    // Build common params via getApiParams, then merge session-specific ones
    const baseParams = filters.getApiParams();

    const params = {
      page:         baseParams.page,
      per_page:     baseParams.perPage,
      status:       baseParams.status         || undefined,
      // ✅ Branch enforcement: non-exempt → force user's branch; exempt → use selected filter
      branch_id:    !isExempt.value
                      ? (authStore.user?.branch_id || undefined)
                      : (baseParams.branchId || undefined),
      from_date:    baseParams.dateFrom        || undefined,
      to_date:      baseParams.dateTo          || undefined,
      // session-specific
      session_type: sessionType.value          || undefined,
      device:       deviceFilter.value         || undefined,
      has_variance: hasVariance.value          || undefined,
    };

    const sessionStore = useSessionStore();
    const result = await sessionStore.fetchSessions(params);
    if (result.status === 'success') {
      items.value      = result.data || [];
      totalCount.value = result.total ?? result.data?.length ?? 0;

      // ✅ Set KPI from backend aggregation
      kpiTotalExpectedCash.value    = result.kpi?.total_expected_cash    ?? 0;
      kpiSessionsWithVariance.value = result.kpi?.sessions_with_variance ?? 0;

      // Pre-populate partial summaries from list for immediate variance column display
      for (const item of items.value) {
        if (!summaries.value[item.id] || summaries.value[item.id]?._partial) {
          summaries.value[item.id] = {
            variance_amount: item.variance_amount ?? null,
            calculated: { opening_balance: parseFloat(item.opening_cash_amount || 0), expected_cash: null },
            totals: { payments: 0, cash_in: 0, cash_out: 0 },
            _partial: true,
          };
        }
      }

      // 🚀 Load full summaries in background using BATCH API (fixes N+1 problem)
      const sessionIds = items.value.map(item => item.id);
      if (sessionIds.length > 0) {
        sessionStore.getSessionSummaries(sessionIds).then(result => {
          if (result.status === 'success' && result.data) {
            // Merge batch results into summaries
            Object.assign(summaries.value, result.data);
            // Force reactivity update
            items.value = [...items.value];
          }
        }).catch(err => {
          console.warn('Failed to load batch summaries:', err);
        });
      }
    }

  } catch (e) {
    console.error('Failed to load sessions', e);
  } finally {
    loading.value = false;
  }
}

async function toggleDetails(row) {
  const sessionStore = useSessionStore();
  detailsSession.value = row;
  // Fetch full summary if not loaded yet, or if we only have partial list data
  if (!summaries.value[row.id] || summaries.value[row.id]?._partial) {
    try {
      const result = await sessionStore.getSessionSummary(row.id);
      if (result.status === 'success') summaries.value[row.id] = result.data;
    } catch {}
  }
  transactions.value[row.id] = summaries.value[row.id]?.transactions || [];
}

// --- Watchers ---
// dateFrom/dateTo/perPage are watched by useHistoryFilters base (via useTableFilters internally)
// But useHistoryFilters doesn't have an onFilterChange callback, so we watch manually:
watch([dateFrom, dateTo, perPage, () => filters.selectedBranch.value, () => filters.statusFilter.value], () => {
  currentPage.value = 1;
  fetchData();
});

// session-specific filter changes
watch([sessionType, deviceFilter, hasVariance], () => {
  currentPage.value = 1;
  fetchData();
});

// page changes (triggered by pagination buttons)
watch(currentPage, fetchData);

// --- UI Helpers ---
const formatDateTime = (v) => v ? new Date(v).toLocaleString('en-US') : '—';
const formatDate     = (v) => v ? new Date(v).toLocaleDateString('en-US') : '';
const formatTime     = (v) => v ? new Date(v).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
const formatCode     = (r) => r.start_time ? `${deviceLabel(r)}/${getLocalDateISO(new Date(r.start_time)).replaceAll('-', '/')}/${r.id}` : r.id;

const deviceLabel = (row) =>
  row?.device_name || (localStorage.getItem('pos_device_name') || '').trim() || (row?.device_id ? `Device ${row.device_id}` : '—');

const statusLabel = (s) => ({ open: 'مفتوحة', closed: 'مغلقة', cancelled: 'ملغاة' }[s] || s);
const statusClass = (s) => ({ open: 'bg-emerald-100 text-emerald-700', closed: 'bg-slate-100 text-slate-500', cancelled: 'bg-rose-100 text-rose-700' }[s] || 'bg-slate-100');
const getbranchName = (id) => branches.value.find(b => b.id === id)?.name || 'الفرع الرئيسي';

const getTransactionTypeLabel = (t) => ({
  sale: 'عملية بيع', expense: 'مصروف', income: 'إيراد إضافي',
  return: 'مرتجع مبيعات', withdrawal: 'سحب نقدي', deposit: 'إيداع نقدي',
  payment: 'دفعة', refund: 'استرداد', discount: 'خصم', tax: 'ضريبة'
}[t] || t);

const getTransactionTypeClass = (t) => ({
  sale: 'text-emerald-600', expense: 'text-rose-600',
  return: 'text-amber-600', withdrawal: 'text-rose-600', deposit: 'text-blue-600'
}[t] || '');

const isExpense = (t) => ['expense', 'withdrawal', 'refund'].includes(t);

// --- Lifecycle ---
onMounted(async () => {
  // Use bootstrap API to fetch all required data in a single request
  try {
    const data = await bootstrapStore.fetchSessionsData();
    
    // Map bootstrap data to individual stores
    if (data.branches) branchStore.branches = data.branches;
    if (data.users) {
      // Users data is available but not currently used in SessionsList
      // Store it if needed for future features
    }
    
    console.log('[SessionsList] Bootstrap data loaded successfully');
  } catch (e) {
    console.warn('[SessionsList] Bootstrap API failed, falling back to individual requests', e);
    
    // Fallback to individual API calls if bootstrap fails
    await Promise.all([
      fetchSettings(),
      branchStore.fetchBranches().catch(e => console.warn('Failed to load branches', e))
    ]);
  }
  
  // Load exemption status
  await ensureExemptionLoaded();

  // ✅ Enforce branch for non-exempt users — mirrors pattern from ReturnsManagement & CashVouchers
  if (!isExempt.value && authStore.user?.branch_id) {
    filters.selectedBranch.value = String(authStore.user.branch_id);
  }

  fetchData();
});
</script>

<style scoped>

/* Dashboard UI Components */
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern, .form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30 px-3 font-black text-xs gap-1; }

/* KPI Cards */
.kpi-card { @apply p-6 rounded-3xl bg-white border border-slate-100 shadow-sm flex items-center justify-between hover:shadow-md transition-all; }
.kpi-icon { @apply w-14 h-14 rounded-[1.75rem] flex items-center justify-center text-lg shadow-md; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Filter Chips */
.filter-chip { @apply inline-flex items-center gap-2 bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest; }

/* Transitions */
.slide-enter-active, .slide-leave-active { @apply transition-all duration-300; }
.slide-enter-from { @apply max-h-0 overflow-hidden opacity-0; }
.slide-leave-to { @apply max-h-0 overflow-hidden opacity-0; }

/* Custom Dropdown Styling */
.dropdown-list { @apply absolute z-50 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-2xl overflow-hidden; }
.dropdown-item { @apply w-full text-right px-6 py-3 text-xs font-black text-slate-700 hover:bg-blue-50 transition-colors; }

/* Sidebar Drawer Animation */
.drawer-enter-active, .drawer-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.drawer-enter-from, .drawer-leave-to { transform: translateX(-100%); }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>