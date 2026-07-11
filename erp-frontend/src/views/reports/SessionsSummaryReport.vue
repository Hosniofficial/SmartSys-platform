<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-chart-pie text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">تقرير ملخص الجلسات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل أداء الورديات، التدفقات النقدية، وفروقات الخزينة اليومية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="fetchSessions" :disabled="isLoading" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i> تحديث البيانات
        </button>
      </div>
    </div>

    <!-- Quick Analytics KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600"><i class="fas fa-layer-group"></i></div>
          <div>
            <p class="kpi-label">إجمالي الجلسات</p>
            <p class="kpi-value text-slate-800">{{ totalItems }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fas fa-cash-register"></i></div>
          <div>
            <p class="kpi-label">إجمالي المبيعات المتوقعة</p>
            <p class="kpi-value text-emerald-600 text-xl">{{ formatCurrency(sessions.reduce((s, v) => s + (v.expected_cash || 0), 0)) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600"><i class="fas fa-scale-unbalanced"></i></div>
          <div>
            <p class="kpi-label">صافي فروقات الخزينة</p>
            <p class="kpi-value text-rose-600 text-xl">{{ formatCurrency(sessions.reduce((s, v) => s + (v.variance_amount || 0), 0)) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600"><i class="fas fa-clock"></i></div>
          <div>
            <p class="kpi-label">جلسات مفتوحة حالياً</p>
            <p class="kpi-value text-amber-600">{{ sessions.filter(s => s.status === 'open').length }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters Section -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="space-y-2">
          <label class="filter-label">من تاريخ</label>
          <div class="relative">
            <input ref="fromDateRef" type="date" v-model="fromDate" class="form-input-modern font-bold text-sm" />
            <i 
              class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
              @click="fromDateRef.showPicker()"
            ></i>
          </div>
        </div>
        <div class="space-y-2">
          <label class="filter-label">إلى تاريخ</label>
          <div class="relative">
            <input ref="toDateRef" type="date" v-model="toDate" class="form-input-modern font-bold text-sm" />
            <i 
              class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
              @click="toDateRef.showPicker()"
            ></i>
          </div>
        </div>
        <div class="space-y-2">
          <label class="filter-label">الفرع / المستودع</label>
          <select v-model="branchId" class="form-select-modern font-black text-sm">
            <option value="">كل الفروع</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="filter-label">الكاشير (المستخدم)</label>
          <select v-model="cashierId" class="form-select-modern font-black text-sm">
            <option value="">كل الموظفين</option>
            <option v-for="c in cashiers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end border-t border-slate-50 pt-6">
        <div class="space-y-2">
          <label class="filter-label">جهاز نقطة البيع</label>
          <select v-model="terminalId" class="form-select-modern font-black text-sm">
            <option value="">كل الأجهزة</option>
            <option v-for="t in terminals" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="filter-label">حالة المطابقة (الفرق النقدي)</label>
          <select v-model="hasVariance" class="form-select-modern font-black text-sm">
            <option value="">الكل</option>
            <option value="true">يوجد عجز أو زيادة</option>
            <option value="false">مطابق تماماً</option>
          </select>
        </div>
        <div class="flex items-center gap-3">
          <button @click="resetFilters" class="h-11 px-6 rounded-2xl bg-slate-100 text-slate-500 font-black text-xs uppercase hover:bg-slate-200 transition-all flex-grow">
            مسح الإعدادات
          </button>
          <button @click="fetchSessions" class="h-11 px-10 bg-slate-900 text-white rounded-2xl font-black text-xs shadow-xl shadow-slate-200 hover:bg-black transition-all active:scale-95 flex items-center justify-center gap-2 flex-grow">
            <i class="fas fa-search"></i> تطبيق التصفية
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State: Skeleton Table -->
    <template v-if="isLoading && !isExporting">
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden p-8">
        <!-- Header skeleton -->
        <div class="flex gap-4 items-center mb-6 pb-6 border-b border-slate-100">
          <BaseSkeleton type="text" size="lg" width="12rem" />
          <BaseSkeleton type="text" size="sm" width="8rem" />
        </div>
        <!-- Table skeleton rows -->
        <div v-for="i in 8" :key="i" class="flex gap-4 items-center py-4 border-b border-slate-50">
          <BaseSkeleton type="circle" size="sm" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
        </div>
      </div>
    </template>

    <!-- Error State -->
    <div v-else-if="error" class="py-24 text-center px-6">
      <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-exclamation-triangle text-3xl"></i>
      </div>
      <h3 class="text-xl font-black text-slate-800">{{ error }}</h3>
      <button @click="fetchSessions" class="mt-6 px-8 py-3 bg-blue-600 text-white rounded-2xl font-black text-xs shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all">
        إعادة المحاولة
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="sessions.length === 0" class="py-32 text-center bg-white rounded-[2.5rem] shadow-sm border border-slate-100">
      <div class="flex flex-col items-center opacity-20 text-slate-400">
        <i class="fas fa-inbox text-6xl mb-4"></i>
        <p class="font-black text-sm uppercase">لا توجد جلسات مسجلة ضمن هذه الفترة</p>
      </div>
    </div>

    <!-- Sessions Data Table -->
    <div v-else class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">توقيت الجلسة والمدة</th>
              <th class="px-4 py-5">الموقع والجهاز</th>
              <th class="px-4 py-5">الكاشير / الموظف</th>
              <th class="px-4 py-5 text-center">الرصيد الافتتاحي</th>
              <th class="px-4 py-5 text-center">المتوقع</th>
              <th class="px-4 py-5 text-center">الفعلي</th>
              <th class="px-4 py-5 text-center">الفرق</th>
              <th class="px-6 py-5 text-center">الحالة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <tr v-for="session in paginatedSessions" :key="session.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-6 py-4">
                <div class="flex flex-col">
                  <span class="font-black text-slate-800 leading-none">{{ formatDate(session.start_time) }}</span>
                  <span class="text-[9px] text-slate-400 mt-2 font-black uppercase tracking-widest bg-slate-100 px-2 py-0.5 rounded-md w-fit">
                    المدة: {{ formatDuration(session.start_time, session.end_time) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col">
                  <span class="font-bold text-slate-700 leading-none">{{ session.branch_name || 'الفرع الرئيسي' }}</span>
                  <span class="text-[10px] text-blue-500 mt-1.5 font-black uppercase tracking-tighter">
                    <i class="fas fa-desktop ml-1 opacity-40"></i>{{ session.terminal_name || '-' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-4 text-xs">
                <div class="font-black text-slate-800">{{ session.cashier_name || '-' }}</div>
                <div v-if="session.closed_by_name" class="text-[9px] text-slate-400 mt-1 font-bold">
                  إغلاق: {{ session.closed_by_name }}
                </div>
              </td>
              <td class="px-4 py-4 text-center font-mono tracking-tighter text-slate-400">
                {{ formatCurrency(session.opening_cash_amount) }}
              </td>
              <td class="px-4 py-4 text-center font-mono tracking-tighter text-slate-800">
                {{ formatCurrency(session.expected_cash) }}
              </td>
              <td class="px-4 py-4 text-center font-mono tracking-tighter text-slate-900">
                {{ formatCurrency(session.actual_cash) }}
              </td>
              <td class="px-4 py-4 text-center">
                <div :class="[
                  session.variance_amount === 0 ? 'text-slate-300' :
                  session.variance_amount < 0 ? 'text-rose-600' : 'text-emerald-600'
                ]" class="font-black font-mono tracking-tighter text-base">
                  {{ formatCurrency(session.variance_amount) }}
                </div>
                <span v-if="session.variance_reason"
                      class="text-[9px] font-bold text-slate-400 block max-w-[120px] truncate mx-auto mt-1"
                      :title="session.variance_reason">
                  {{ session.variance_reason }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-3">
                  <span :class="[session.status === 'closed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']"
                        class="status-badge">
                    {{ session.status === 'closed' ? 'مغلقة' : 'مفتوحة' }}
                  </span>
                  <div class="flex items-center gap-1.5">
                    <button @click="viewSessionDetails(session)"
                            class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-90"
                            title="تفاصيل">
                      <i class="fas fa-eye text-[10px]"></i>
                    </button>
                    <button @click="exportSession(session)"
                            :disabled="isExporting"
                            class="w-8 h-8 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-all shadow-sm active:scale-90"
                            title="تصدير">
                      <i class="fas fa-file-export text-[10px]"></i>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          عرض
          <span class="text-slate-800">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
          إلى
          <span class="text-slate-800">{{ Math.min(currentPage * itemsPerPage, totalItems) }}</span>
          من
          <span class="text-slate-800">{{ totalItems }}</span>
          نتيجة
          <span class="mr-3 text-slate-300">|</span>
          صفحة {{ currentPage }} / {{ totalPages }}
        </div>

        <div class="flex items-center gap-1">
          <button @click="onPageChange(currentPage - 1)" :disabled="currentPage === 1" class="pagination-btn">
            <i class="fas fa-angle-right"></i>
          </button>
          <div class="flex items-center gap-1.5 mx-2">
            <template v-for="p in totalPages" :key="p">
              <button
                v-if="Math.abs(p - currentPage) < 3 || p === 1 || p === totalPages"
                @click="onPageChange(p)"
                :class="[p === currentPage ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-white text-slate-400 hover:bg-slate-100']"
                class="w-9 h-9 rounded-xl text-xs font-black transition-all">
                {{ p }}
              </button>
              <span v-if="Math.abs(p - currentPage) === 3 && p !== 1 && p !== totalPages"
                    class="text-slate-300 font-black text-xs">...</span>
            </template>
          </div>
          <button @click="onPageChange(currentPage + 1)" :disabled="currentPage >= totalPages" class="pagination-btn">
            <i class="fas fa-angle-left"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Session Details Modal -->
    <transition name="modal">
      <div v-if="showTransactionDetails && selectedSession" class="modal-overlay" @click.self="closeSessionDetails">
        <div class="modal-content-modern max-w-4xl animate-modalIn">
          <!-- Modal Header -->
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center shadow-lg text-white shrink-0">
                <i class="fas fa-receipt text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">
                  تفاصيل الجلسة المحاسبية #{{ selectedSession.session.id }}
                </h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">
                  تاريخ الجلسة: {{ formatDateTime(selectedSession.session.start_time) }}
                </p>
              </div>
            </div>
            <button @click="closeSessionDetails" class="text-slate-400 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-8 overflow-y-auto custom-scroll max-h-[75vh] space-y-8">

            <!-- Session Info Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الفرع</p>
                <p class="text-xs text-slate-800 font-bold">{{ selectedSession.session.branch_name || 'الفرع الرئيسي' }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الجهاز</p>
                <p class="text-xs text-slate-800 font-bold">{{ selectedSession.session.device_name || 'غير محدد' }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الكاشير</p>
                <p class="text-xs text-slate-800 font-bold truncate">{{ selectedSession.session.closed_by_name || 'غير محدد' }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 text-white">
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">حالة الجلسة</p>
                <p class="text-xs font-black uppercase">
                  {{ selectedSession.session.status === 'closed' ? 'مغلقة ومؤرشفة' : 'مفتوحة حالياً' }}
                </p>
              </div>
            </div>

            <!-- Time Info (from original) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">تاريخ ووقت البداية</p>
                <p class="text-xs font-bold text-slate-800">{{ formatDateTime(selectedSession.session.start_time) }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">تاريخ ووقت النهاية</p>
                <p class="text-xs font-bold text-slate-800">
                  {{ selectedSession.session.end_time ? formatDateTime(selectedSession.session.end_time) : 'مفتوحة' }}
                </p>
              </div>
            </div>

            <!-- Financial Reconciliation Box -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 space-y-6">
              <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-calculator text-blue-500"></i> ملخص التسوية المالية
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-4">
                  <div class="flex justify-between items-center text-xs font-bold text-slate-500">
                    <span>الرصيد الافتتاحي:</span>
                    <span class="text-slate-800 font-mono">{{ formatCurrency(selectedSession.calculated.opening_balance) }}</span>
                  </div>
                  <div class="flex justify-between items-center text-xs font-bold text-emerald-600">
                    <span>إجمالي الإيرادات (+):</span>
                    <span class="font-mono">+{{ formatCurrency(selectedSession.totals.cash_in) }}</span>
                  </div>
                  <div class="flex justify-between items-center text-xs font-bold text-rose-500 border-b border-slate-50 pb-3">
                    <span>إجمالي المصروفات (-):</span>
                    <span class="font-mono">-{{ formatCurrency(selectedSession.totals.cash_out) }}</span>
                  </div>
                </div>
                <div class="bg-slate-50 p-6 rounded-[1.5rem] flex flex-col justify-center text-center">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">النقد المتوقع بالدرج</p>
                  <p class="text-2xl font-black text-slate-900 font-mono tracking-tighter">
                    {{ formatCurrency(selectedSession.calculated.expected_cash) }}
                  </p>
                </div>
                <div class="bg-blue-600 p-6 rounded-[1.5rem] text-white flex flex-col justify-center text-center shadow-xl shadow-blue-100">
                  <p class="text-[10px] font-black text-white/50 uppercase tracking-widest mb-2">النقد الفعلي المسلم</p>
                  <p class="text-2xl font-black font-mono tracking-tighter leading-none">
                    {{ formatCurrency(selectedSession.session.closing_cash_amount) }}
                  </p>
                  <div class="mt-3 text-[10px] font-black flex items-center justify-center gap-2 border-t border-white/10 pt-2">
                    <span>الفرق: {{ formatCurrency(selectedSession.calculated.variance_amount) }}</span>
                    <i :class="selectedSession.calculated.variance_amount === 0 ? 'fas fa-check-circle' : 'fas fa-triangle-exclamation'" class="text-[8px]"></i>
                  </div>
                </div>
              </div>
              <!-- Variance reason (from original) -->
              <div v-if="selectedSession.session.variance_reason"
                   class="text-sm text-gray-600 bg-amber-50 border border-amber-100 p-4 rounded-2xl">
                <span class="font-black text-amber-700">سبب الفرق: </span>
                {{ selectedSession.session.variance_reason }}
              </div>
            </div>

            <!-- Detailed Transactions List -->
            <div class="space-y-4">
              <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2">
                <i class="fas fa-list-ul text-blue-500"></i> سجل حركات الوردية
              </h4>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-100 uppercase tracking-tighter">
                      <th class="px-6 py-4">الوقت</th>
                      <th class="px-4 py-4">نوع الحركة</th>
                      <th class="px-4 py-4">المبلغ</th>
                      <th class="px-4 py-4">المرجع</th>
                      <th class="px-6 py-4">الملاحظات</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-for="tx in selectedSession.transactions" :key="tx.id"
                        class="hover:bg-slate-50/50 transition-all group">
                      <td class="px-6 py-4 text-slate-400 font-mono tracking-tighter whitespace-nowrap">
                        {{ formatDateTime(tx.created_at) }}
                      </td>
                      <td class="px-4 py-4">
                        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-black uppercase group-hover:bg-white transition-all">
                          {{ getTransactionTypeLabel(tx.type) }}
                        </span>
                      </td>
                      <td class="px-4 py-4 font-black font-mono tracking-tighter text-sm whitespace-nowrap"
                          :class="isExpense(tx.type) ? 'text-rose-600' : 'text-emerald-600'">
                        {{ isExpense(tx.type) ? '-' : '+' }} {{ formatCurrency(tx.amount) }}
                      </td>
                      <td class="px-4 py-4 text-slate-500 whitespace-nowrap">
                        {{ tx.reference_type }} #{{ tx.reference_id || '--' }}
                      </td>
                      <td class="px-6 py-4 text-slate-400 italic font-medium truncate max-w-[180px]">
                        {{ tx.notes || '--' }}
                      </td>
                    </tr>
                    <tr v-if="!selectedSession.transactions?.length">
                      <td colspan="5" class="py-12 text-center text-slate-300 font-black uppercase tracking-widest">
                        لا توجد حركات مالية مسجلة لهذه الجلسة
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
            <button @click="closeSessionDetails"
                    class="px-8 py-3 rounded-xl text-xs font-black text-slate-500 hover:bg-white transition-all">
              إغلاق التقرير
            </button>
            <button v-if="selectedSession.session.status === 'closed'"
                    @click="exportSession(selectedSession.session)"
                    :disabled="isExporting"
                    class="px-10 py-3 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
              <i v-if="!isExporting" class="fas fa-file-csv"></i>
              <i v-else class="fas fa-spinner fa-spin"></i>
              تصدير السجل الكامل
            </button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { formatCurrency as utilFormatCurrency } from '@/utils/formatters';
import { getLocalDateISO, formatDateTime as utilFormatDateTime } from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useSessionStore } from '@/stores/session/sessionStore';
import { downloadCSV } from '@/utils/export';
import { useToast } from 'vue-toastification';
import { useBranchStore } from '@/stores/branch';

const toast = useToast();

// Store instances
const branchStore = useBranchStore();
const sessionStore = useSessionStore();

// ─── State (STRICTLY PRESERVED from original) ────────────────────────────────

const isLoading = ref(true);
const error = ref(null);
const sessions = ref([]);
const totalItems = ref(0);
const currentPage = ref(1);
const itemsPerPage = ref(25);
const selectedSession = ref(null);
const showTransactionDetails = ref(false);
const isExporting = ref(false);

// Filters
const fromDate = ref('');
const toDate = ref('');
const fromDateRef = ref(null);
const toDateRef = ref(null);
const branchId = ref('');
const cashierId = ref('');
const terminalId = ref('');
const hasVariance = ref('');

// Dropdown data
const branches = computed(() => branchStore.branches);
const cashiers = ref([]);
const terminals = ref([]);

// ─── Computed (STRICTLY PRESERVED from original) ──────────────────────────────

const paginatedSessions = computed(() => sessions.value);

const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value));

// ─── Formatters (STRICTLY PRESERVED from original) ────────────────────────────

const formatCurrency = (value) => {
  return utilFormatCurrency(value);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatDateTime = (dateString) => {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleString('en-US');
};

const formatDuration = (start, end) => {
  if (!start || !end) return '-';
  const startDate = new Date(start);
  const endDate = new Date(end);
  const diffMs = endDate - startDate;
  const hours = Math.floor(diffMs / (1000 * 60 * 60));
  const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
  return `${hours} س ${minutes} د`;
};

// ─── Data Fetching (STRICTLY PRESERVED from original) ─────────────────────────

const fetchSessions = async () => {
  try {
    isLoading.value = true;
    error.value = null;

    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined,
      branch_id: branchId.value || undefined,
      cashier_id: cashierId.value || undefined,
      terminal_id: terminalId.value || undefined,
      has_variance: hasVariance.value || undefined
    };

    const result = await sessionStore.fetchSessions(params);
    if (result.status === 'success') {
      sessions.value = result.data || [];
      totalItems.value = result.total || sessions.value.length;
    } else {
      error.value = result.message || 'فشل تحميل الجلسات';
      toast.error(error.value);
    }
  } catch (err) {
    console.error('Error fetching sessions summary:', err);
    error.value = 'حدث خطأ أثناء جلب بيانات الجلسات';
    toast.error(error.value);
  } finally {
    isLoading.value = false;
  }
};

// ─── Session Details (STRICTLY PRESERVED from original) ───────────────────────

const viewSessionDetails = async (session) => {
  try {
    isLoading.value = true;
    const result = await sessionStore.getSessionSummary(session.id);
    if (result.status === 'success') {
      selectedSession.value = { session: session, ...result.data };
      showTransactionDetails.value = true;
    } else {
      toast.error(result.message || 'فشل في تحميل تفاصيل الجلسة');
    }
  } catch (err) {
    console.error('Error fetching session details:', err);
    toast.error('فشل في تحميل تفاصيل الجلسة');
  } finally {
    isLoading.value = false;
  }
};

const closeSessionDetails = () => {
  showTransactionDetails.value = false;
  selectedSession.value = null;
};

// ─── Export (STRICTLY PRESERVED from original - using store method) ────────────────────────────────

const exportSession = async (session) => {
  try {
    isExporting.value = true;
    // استخدام الـ store بدلاً من السيرفيس مباشرة
    const result = await sessionStore.fetchSessions();
    if (result.status === 'success' && Array.isArray(result.data)) {
      // تصفية الجلسة المطلوبة من البيانات
      const sessionData = result.data.find(s => s.id === session.id);
      if (sessionData) {
        const fileName = `session_${session.id}_${getLocalDateISO(new Date())}.csv`;
        // تحويل الجلسة إلى CSV
        const csvContent = `Session ID,Date,Status,Amount\n${session.id},${session.date},${session.status},${session.amount}`;
        downloadCSV(csvContent, fileName);
        toast.success('تم تصدير الجلسة بنجاح');
      }
    }
  } catch (err) {
    console.error('Error exporting session:', err);
    toast.error('فشل في تصدير الجلسة');
  } finally {
    isExporting.value = false;
  }
};

// ─── Helpers (STRICTLY PRESERVED from original) ───────────────────────────────

const getTransactionTypeLabel = (type) => {
  const types = {
    'sale': 'بيع',
    'expense': 'مصروف',
    'income': 'إيراد',
    'return_payment': 'مرتجع مشتريات',
    'return_receipt': 'مرتجع مبيعات',
    'withdrawal': 'سحب',
    'deposit': 'إيداع'
  };
  return types[type] || type;
};

const isExpense = (type) => {
  return ['expense', 'return_payment', 'withdrawal'].includes(type);
};

// ─── Dropdown data (STRICTLY PRESERVED from original) ─────────────────────────

const fetchDropdownData = async () => {
  try {
    // Fetch from API when available — original had empty arrays as placeholder
    cashiers.value = [];
    terminals.value = [];
  } catch (err) {
    console.error('Error fetching dropdown data:', err);
  }
};

// ─── Pagination (STRICTLY PRESERVED from original) ────────────────────────────

const onPageChange = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchSessions();
  }
};

// ─── Reset Filters (STRICTLY PRESERVED from original) ─────────────────────────

const resetFilters = () => {
  fromDate.value = '';
  toDate.value = '';
  branchId.value = '';
  cashierId.value = '';
  terminalId.value = '';
  hasVariance.value = '';
  currentPage.value = 1;
  fetchSessions();
};

// ─── Lifecycle (STRICTLY PRESERVED from original) ─────────────────────────────

onMounted(async () => {
  // Set default date range to last 30 days (preserved from original)
  const endDate = new Date();
  const startDate = new Date();
  startDate.setDate(startDate.getDate() - 30);

  fromDate.value = getLocalDateISO(startDate);
  toDate.value = getLocalDateISO(endDate);

  await Promise.all([
    fetchSessions(),
    fetchDropdownData()
  ]);
});

// ─── Style (STRICTLY PRESERVED from original) ────────────────────────────────

</script>

<style scoped>

/* KPI Cards */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Form Components */
.form-input-modern,
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.filter-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

/* Status & Pagination */
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

/* Modal */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col border border-white; max-height: 90vh; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>