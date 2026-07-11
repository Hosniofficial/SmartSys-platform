<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white">
          <i class="fas fa-file-invoice-dollar text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none">سجل المبيعات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع وإدارة عمليات البيع والفواتير</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="filters.showFilters.value = !filters.showFilters.value" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-2', filters.showFilters.value ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-transparent text-slate-600 hover:bg-slate-50']">
          <i class="fas fa-filter"></i>
          {{ filters.showFilters.value ? 'إخفاء الفلاتر' : 'البحث والتصفية' }}
        </button>
      </div>
    </div>

    <!-- Filters Panel (Collapsible) - ABOVE KPI -->
    <transition name="slide">
      <div v-if="filters.showFilters.value" class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible transition-all">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="relative group">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
            <div class="relative">
              <input v-model="filters.searchQuery.value" type="text" class="form-input-modern pr-10" placeholder="ابحث بـ: رقم الفاتورة أو العميل..." />
              <i class="fas fa-search absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
              <button v-if="filters.searchQuery.value" @click="filters.searchQuery.value = ''" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح البحث">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="dateFromRefLocal" type="date" v-model="filters.dateFrom.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateFromRefLocal.showPicker()"
              ></i>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">إلى تاريخ</label>
            <div class="relative">
              <input ref="dateToRefLocal" type="date" v-model="filters.dateTo.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateToRefLocal.showPicker()"
              ></i>
            </div>
          </div>

          <div class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">حالة السداد</label>
            <select v-model="filters.statusFilter.value" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <i class="fas fa-money-bill absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
          <div class="relative md:col-span-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">العميل المستهدف</label>
            <div class="relative">
              <input
                type="text"
                v-model="filters.customerSearch.value"
                class="form-input-modern pr-10 font-bold text-sm"
                placeholder="ابحث عن عميل..."
                @focus="filters.showCustomerDropdown.value = true"
                @blur="filters.hideCustomerDropdown()"
                @input="filters.showCustomerDropdown.value = true"
              />
              <i class="fas fa-user-circle absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
              <button v-if="filters.customerFilter.value" @click="filters.clearCustomerFilter();" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح تصفية العميل">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>

              <div v-if="filters.showCustomerDropdown.value" class="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-60 overflow-auto py-2">
                <button @mousedown.prevent="filters.clearCustomerFilter();" class="w-full text-right px-5 py-3 text-xs font-black text-blue-600 hover:bg-slate-50 border-b border-slate-50 transition-colors">
                  كل العملاء
                </button>
                <button v-for="c in filteredCustomers" :key="c.id" @mousedown.prevent="filters.setCustomerFilter(c.id, c.name || c.customer_name);" class="w-full text-right px-5 py-3 text-xs font-bold text-slate-700 hover:bg-blue-50 transition-colors">
                  {{ c.name || c.customer_name }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="isExempt" class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع / المستودع</label>
            <select v-model="filters.selectedBranch.value" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option value="">كل الفروع</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name || w.branch_name }}</option>
            </select>
            <i class="fas fa-warehouse absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>

          <button @click="filters.clearAllFilters()" class="h-[46px] w-full rounded-2xl bg-slate-100 text-slate-600 font-black text-xs hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-sync-alt"></i> إعادة تعيين
          </button>
        </div>

        <!-- Filter Chips -->
        <div class="flex flex-wrap gap-2 mt-6 pt-6 border-t border-slate-50" v-if="filters.hasActiveFilters.value || filters.customerFilter.value || (isExempt && filters.selectedBranch.value)">
          <div class="flex items-center gap-2 w-full">
            <div class="flex items-center gap-2 text-blue-600 bg-blue-50 px-3 py-2 rounded-lg border border-blue-100">
              <i class="fas fa-check-circle text-xs"></i>
              <span class="text-[10px] font-black uppercase tracking-wider">تصفية نشطة</span>
            </div>
            <div class="flex flex-wrap gap-2 flex-1">
              <span v-if="filters.searchQuery.value" class="filter-chip group">
                <i class="fas fa-search ml-1 text-[9px]"></i>{{ filters.searchQuery.value }}
                <i @click="filters.searchQuery.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.dateFrom.value" class="filter-chip group">
                <i class="fas fa-calendar-left ml-1 text-[9px]"></i>{{ filters.dateFrom.value }}
                <i @click="filters.dateFrom.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.dateTo.value" class="filter-chip group">
                <i class="fas fa-calendar-right ml-1 text-[9px]"></i>{{ filters.dateTo.value }}
                <i @click="filters.dateTo.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.statusFilter.value" class="filter-chip group">
                <i class="fas fa-money-bill ml-1 text-[9px]"></i>{{ getStatusLabel(filters.statusFilter.value) }}
                <i @click="filters.statusFilter.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.customerFilter.value" class="filter-chip group">
                <i class="fas fa-user ml-1 text-[9px]"></i>{{ filters.customerSearch.value }}
                <i @click="filters.clearCustomerFilter();" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="isExempt && filters.selectedBranch.value" class="filter-chip group">
                <i class="fas fa-warehouse ml-1 text-[9px]"></i>{{ (branches.find(w=>String(w.id)===String(filters.selectedBranch.value))?.name) || '...' }}
                <i @click="filters.selectedBranch.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div>
            <p class="kpi-label">عدد الفواتير</p>
            <p class="kpi-value text-slate-800">{{ filters.total.value }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي المبيعات</p>
            <p class="kpi-value text-emerald-600">{{ formatPrice(filters.kpiSum.value) }}</p>
          </div>
        </div>
      </div>

      <div v-if="filters.kpiTax.value != null" class="kpi-card group border-l-4 border-l-indigo-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="fas fa-percent"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الضريبة</p>
            <p class="kpi-value text-indigo-600">{{ formatPrice(filters.kpiTax.value) }}</p>
          </div>
        </div>
      </div>

      <div v-if="filters.kpiDiscount.value != null" class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-tags"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الخصومات</p>
            <p class="kpi-value text-rose-600">{{ formatPrice(filters.kpiDiscount.value) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Invoices Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      
      <!-- Bulk Actions Bar -->
      <transition name="fade">
        <div v-if="filters.selectedIds.value.length" class="bg-blue-600 px-8 py-4 flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-4 text-white">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center font-black">{{ filters.selectedIds.value.length }}</div>
            <span class="text-sm font-bold uppercase tracking-widest">فاتورة محددة</span>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button @click="markSelectedAsPaid" :disabled="isMarkingPaid" class="bg-emerald-500 hover:bg-emerald-600 px-5 py-2.5 rounded-xl text-xs font-black text-white transition-all flex items-center gap-2 shadow-lg disabled:opacity-60">
              <i class="fas fa-money-check-alt"></i> {{ isMarkingPaid ? 'جارٍ المعالجة...' : 'تسديد المتبقي فوراً' }}
            </button>
            <button @click="printSelected" class="bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl text-xs font-black text-white border border-white/20 transition-all flex items-center gap-2">
              <i class="fas fa-print"></i> طباعة المحدد
            </button>
            <button @click="exportSelectedCsv" class="bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl text-xs font-black text-white border border-white/20 transition-all flex items-center gap-2">
              <i class="fas fa-file-export"></i> تصدير CSV
            </button>
            <button @click="filters.clearSelection()" class="text-white/70 hover:text-white px-2 py-2 text-xs font-bold transition-all">إلغاء التحديد</button>
          </div>
        </div>
      </transition>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5 w-12 text-center">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4 cursor-pointer" />
              </th>
              <th v-if="visibleColumns.id" @click="toggleSort('id')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                رقم الفاتورة <i :class="filters.sortKey.value==='id' ? (filters.sortAsc.value? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'" class="ml-1 opacity-40"></i>
              </th>
              <th v-if="visibleColumns.created_at" @click="toggleSort('created_at')" class="px-4 py-5 cursor-pointer hover:text-blue-600 transition-colors">
                التاريخ والوقت <i :class="filters.sortKey.value==='created_at' ? (filters.sortAsc.value? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'" class="ml-1 opacity-40"></i>
              </th>
              <th v-if="visibleColumns.customer" class="px-4 py-5">العميل</th>
              <th v-if="visibleColumns.items" class="px-4 py-5 text-center">عدد المنتجات</th>
              <th v-if="visibleColumns.total" class="px-4 py-5">الإجمالي</th>
              <th v-if="visibleColumns.status" class="px-4 py-5 text-center">الحالة</th>
              <th v-if="visibleColumns.actions" class="px-6 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoadingList">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!sales.length">
              <td :colspan="10" class="py-20">
                <div class="flex flex-col items-center opacity-20">
                  <i class="fas fa-receipt text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد فواتير مطابقة للمعايير</p>
                </div>
              </td>
            </tr>
            <tr v-for="sale in sales" :key="sale.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-6 py-4 text-center">
                <input type="checkbox" :value="sale.id" v-model="filters.selectedIds.value" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4 cursor-pointer" />
              </td>
              <td class="px-4 py-4 font-black text-slate-800">{{ sale.invoice_number || '#' + sale.id }}</td>
              <td class="px-4 py-4 text-xs font-bold text-slate-500 font-mono tracking-tighter">{{ formatDateTime(sale.created_at) }}</td>
              <td class="px-4 py-4">
                <span class="font-bold text-slate-700 truncate max-w-[180px] block">{{ sale.customer_name || 'عميل نقدي' }}</span>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="bg-slate-100 text-slate-500 px-2.5 py-1 rounded-lg text-[10px] font-black">{{ sale.total_items }}</span>
              </td>
              <td class="px-4 py-4">
                <span class="font-black text-blue-600 text-base leading-none">{{ formatPrice(getSaleGross(sale)) }}</span>
              </td>
              <td class="px-4 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <span :class="['status-badge', getDynamicStatusClass(sale.dynamic_status)]">
                    {{ getStatusLabel(sale.dynamic_status) }}
                  </span>
                  <span v-if="getAgeBadge(sale)" :class="['text-[9px] font-black px-1.5 py-0.5 rounded-md border', getAgeBadge(sale).cls]">
                    {{ getAgeBadge(sale).label }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-center">
                <button @click="viewSaleDetails(sale.id)" :disabled="isLoadingDetails" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95 disabled:opacity-50" title="عرض التفاصيل">
                  <i class="fas fa-eye text-sm"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          صفحة <span class="text-slate-800">{{ filters.page.value }}</span> من <span class="text-slate-800">{{ totalPages }}</span>
          (إجمالي <span class="text-slate-800">{{ filters.total.value }}</span> فاتورة)
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400">النتائج:</span>
            <select v-model.number="filters.perPage.value" class="h-10 text-xs font-black border-slate-200 rounded-xl bg-white px-3 outline-none border">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-center gap-1">
            <button @click="filters.previousPage()" :disabled="filters.page.value<=1" class="pagination-btn">
              <i class="fas fa-angle-right"></i> السابق
            </button>
            <button @click="filters.nextPage(totalPages)" :disabled="filters.page.value>=totalPages" class="pagination-btn">
              التالي <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Sale Details Modal -->
    <transition name="modal">
      <div v-if="showDetailsModal && selectedSale" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm" @click.self="showDetailsModal = false">
        <div class="bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden max-h-[90vh] animate-modalIn border border-white">
          <!-- Modal Header -->
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-100">
                <i class="fas fa-file-invoice text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">تفاصيل الفاتورة #{{ selectedSale.id }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">{{ formatDateTime(selectedSale.created_at) }}</p>
              </div>
            </div>
            <button @click="showDetailsModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <!-- Modal Content -->
          <div class="p-8 overflow-y-auto custom-scroll">
            <!-- Top Summary Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">العميل</p>
                <p class="text-sm font-bold text-slate-800">{{ selectedSale.customer_name || 'عميل نقدي' }}</p>
              </div>
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">طريقة الدفع</p>
                <p class="text-sm font-bold text-slate-800">{{ getPaymentMethodName(selectedSalePaymentMethodId) }}</p>
              </div>
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">حالة الفاتورة</p>
                <div>
                  <span :class="['status-badge inline-block', getDynamicStatusClass(selectedSale.dynamic_status)]">
                    {{ getStatusLabel(selectedSale.dynamic_status) }}
                  </span>
                  <p v-if="getStatusMessage(selectedSale.dynamic_status)" class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-2 font-medium leading-relaxed">
                    {{ getStatusMessage(selectedSale.dynamic_status) }}
                  </p>
                </div>
              </div>
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الفرع</p>
                <p class="text-sm font-bold text-slate-800">{{ selectedSale.branch_name || '-' }}</p>
              </div>
            </div>

            <!-- Items Table -->
            <h4 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-4 px-1">الأصناف المبيعة</h4>
            <div class="border border-slate-100 rounded-3xl overflow-hidden shadow-sm mb-8">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-100">
                    <th class="px-4 py-4 uppercase">المنتج</th>
                    <th class="px-4 py-4 text-center">الكمية</th>
                    <th class="px-4 py-4">سعر الوحدة</th>
                    <th class="px-4 py-4">الإجمالي الأصلي</th>
                    <th class="px-4 py-4">الخصم</th>
                    <th class="px-4 py-4">الصافي للوحدة</th>
                    <th class="px-4 py-4">الضريبة ({{ taxRateDisplay }}%)</th>
                    <th class="px-4 py-4">الإجمالي شامل الضريبة</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="item in selectedSale.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-4 py-4 font-bold text-slate-800">
                      {{ item.product_name }}
                      <span v-if="item.discount_value > 0" class="block text-[9px] text-rose-500 font-black uppercase mt-0.5">خصم: {{ formatPrice(item.discount_value) }}</span>
                    </td>
                    <td class="px-4 py-4 text-center font-black text-slate-500">{{ item.quantity }}</td>
                    <td class="px-4 py-4 font-bold">{{ formatPrice(item.sale_price) }}</td>
                    <td class="px-4 py-4 font-bold text-slate-700">{{ formatPrice(item.total) }}</td>
                    <td class="px-4 py-4 font-bold text-rose-500">
                      <span v-if="item.discount_value > 0">- {{ formatPrice(item.discount_value) }}</span>
                      <span v-else class="text-slate-300">-</span>
                    </td>
                    <td class="px-4 py-4 font-bold text-blue-600">{{ formatPrice(item.net_price) }}</td>
                    <td class="px-4 py-4 font-bold text-indigo-500">{{ formatPrice(item.net_price * (taxRate / 100)) }}</td>
                    <td class="px-4 py-4 font-black text-slate-900">{{ formatPrice(item.net_total * (1 + taxRate / 100)) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Totals Footer -->
            <div class="flex flex-col md:flex-row gap-8">
              <div class="flex-grow p-6 rounded-3xl bg-slate-50 border border-slate-100">
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">ملاحظات الفاتورة</h5>
                <p class="text-xs text-slate-500 leading-relaxed italic">{{ selectedSale.notes || 'لا توجد ملاحظات إضافية.' }}</p>
              </div>
              
              <div class="w-full md:w-80 p-6 rounded-[2rem] bg-slate-900 text-white shadow-xl space-y-3">
                <div class="flex justify-between text-xs font-bold text-slate-400">
                  <span>الإجمالي الفرعي</span>
                  <span>{{ formatPrice(subTotalNet) }}</span>
                </div>
                <div class="flex justify-between text-xs font-bold text-slate-400 border-b border-white/5 pb-2">
                  <span>الخصم</span>
                  <span class="text-rose-400">- {{ formatPrice(totalDiscount) }}</span>
                </div>
                <div class="flex justify-between text-xs font-bold text-slate-400 pt-1">
                  <span>الضريبة ({{ taxRateDisplay }}%)</span>
                  <span>{{ formatPrice(finalNetTotal * (taxRate / 100)) }}</span>
                </div>
                <div class="pt-3 border-t border-white/10 flex justify-between items-center">
                  <span class="text-xs font-black uppercase tracking-widest text-blue-400">الصافي النهائي</span>
                  <span class="text-2xl font-black">{{ formatPrice(finalNetTotal * (1 + taxRate / 100)) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer Actions -->
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">مستند معتمد من النظام</span>
            </div>
            <div class="flex items-center gap-2">
              <button @click="showDetailsModal = false" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-500 hover:bg-white hover:text-rose-500 transition-all">إغلاق</button>
              <button @click="printSaleDetails" class="px-8 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 flex items-center gap-2 active:scale-95 transition-all hover:bg-blue-700">
                <i class="fas fa-print"></i> طباعة الفاتورة
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useHistoryFilters } from '@/composables/useHistoryFilters';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useSalesStore } from '@/stores/sales/salesStore';
import { useSettingsStore } from '@/stores/settings/settingsStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useBootstrapStore } from '@/stores/bootstrap';
import AlertService from '@/services/AlertService';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { getLocalDateISO } from '@/utils/date';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { getBuilderByTemplate } from '@/utils/printTemplates';
import { printDocument } from '@/utils/PrintService';

// ─── Composables ──────────────────────────────────────────────────────────────
const { showToast } = useToast();
const { showLoader, hideLoader } = useLoader();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const salesStore = useSalesStore();
const settingsStore = useSettingsStore();
const customerStore = useCustomerStore();
const paymentStore = usePaymentStore();
const bootstrapStore = useBootstrapStore();
const route = useRoute();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);

// --- Tax Rate  ---
const taxRate = ref(0);
const taxRateDisplay = computed(() => taxRate.value.toFixed(0));

// --- Data ---
const sales = ref([]);
const paymentMethods = computed(() => paymentStore.paymentMethods);
const customers = computed(() => customerStore.customers);
const branches = computed(() => branchStore.branches);

const cashMethodId = computed(() => {
  const m = (paymentMethods.value || []).find(pm => String(pm.kind || '').toLowerCase() === 'cash');
  return m?.id || null;
});

// --- Filters ---
const statusOptions = [
  { value: '', label: 'كل الحالات' },
  { value: 'paid', label: 'مدفوعة' },
  { value: 'partial', label: 'جزئي' },
  { value: 'unpaid', label: 'مستحقة' }
];

// ─── Use History Filters Composable ────────────────────────────────────────
const filters = useHistoryFilters('sales_hist_filters');
filters.loadFromLocalStorage();

// ─── Customer Dropdown ────────────────────────────────────────────────────
const filteredCustomers = computed(() => {
  const q = (filters.customerSearch.value || '').toLowerCase();
  const list = customers.value || [];
  if (!q) return list.slice(0, 50);
  return list.filter(c => String(c.name || c.customer_name || '').toLowerCase().includes(q)).slice(0, 50);
});

// ─── Total Pages Computed ─────────────────────────────────────────────────
const totalPages = computed(() => Math.max(1, Math.ceil(filters.total.value / filters.perPage.value)));

// --- Loading ---
const isLoadingList = ref(false);
const isLoadingDetails = ref(false);
const isMarkingPaid = ref(false);
let listAbortCtrl = null;
let detailsAbortCtrl = null;
let searchTimer = null;

// --- Date Picker Refs ---
const dateFromRefLocal = ref(null);
const dateToRefLocal = ref(null);

// --- Column Visibility ---
const defaultVisibleCols = { id: true, created_at: true, customer: true, items: true, total: true, status: true, actions: true };
let persistedCols = {};
try { persistedCols = JSON.parse(localStorage.getItem('sales_hist_cols') || '{}') || {}; } catch {}
const visibleColumns = ref({ ...defaultVisibleCols, ...persistedCols });
watch(visibleColumns, (val) => { try { localStorage.setItem('sales_hist_cols', JSON.stringify(val)); } catch {} }, { deep: true });

// --- Selection Helpers ---
const allSelected = computed(() => sales.value.length > 0 && filters.selectedIds.value.length === sales.value.length);
const toggleSelectAll = (e) => { 
  filters.toggleSelectAll(e?.target?.checked ? sales.value : []);
};

// --- Detail Computeds ---
const subTotalNet = computed(() => {
  if (!selectedSale.value?.items) return 0;
  return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
});

const totalDiscount = computed(() => {
  if (!selectedSale.value?.items) return 0;
  return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.discount_value) || 0), 0);
});

const finalNetTotal = computed(() => {
  if (selectedSale.value?.sale?.net_total_amount !== undefined) return parseFloat(selectedSale.value.sale.net_total_amount) || 0;
  if (selectedSale.value?.items) return selectedSale.value.items.reduce((sum, item) => sum + (parseFloat(item.net_total) || 0), 0);
  return 0;
});

const selectedSalePaymentMethodId = computed(() => {
  const s = selectedSale.value || {};
  return s.payment_method_id || (s.payments && s.payments[0]?.payment_method_id) || null;
});

// --- Modal ---
const showDetailsModal = ref(false);
const selectedSale = ref(null);

// lock body scroll when modal open
watch(showDetailsModal, (val) => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = val ? 'hidden' : '';
  }
});

// --- Helpers ---
const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return '';
  return new Date(dateTimeString).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getStatusLabel = (status) => {
  const map = {
    paid: 'مدفوعة',
    settled: 'مسددة',
    unpaid: 'غير مدفوعة',
    partial: 'مدفوعة جزئياً',
    pending_payment: 'قيد الدفع',  // 🔧 Fixed: restored original
    returned: 'مرتجعة',
    settled_by_return: 'مسددة',
    closed_by_return: 'مرتجعة',
    settled_by_credit: 'مسوّاة بمرتجع',
    settled_mixed: 'مسوّاة نقدي/إشعار دائن',
  };
  return map[status] || status;
};

const getStatusMessage = (status) => {
  const messages = {
    settled_by_return: 'مسددة بإشعار دائن ناتج عن مرتجع.',
    settled_by_credit: 'مسددة بمرتجع.',
    settled_mixed: 'مسددة جزئياً نقدي وجزئياً بإشعار دائن.',
  };
  return messages[status] || null;
};

const getDynamicStatusClass = (status) => {
  const classMap = {
    paid: 'status-paid',
    settled: 'status-paid',
    unpaid: 'status-unpaid',
    partial: 'status-partial',
    pending_payment: 'status-partial',
    returned: 'status-unpaid',
    settled_by_return: 'status-paid',
    closed_by_return: 'status-unpaid',
    settled_by_credit: 'status-paid',
    settled_mixed: 'status-paid',
  };
  return classMap[status] || 'status-partial';
};

const getSaleGross = (s) => {
  if (!s) return 0;
  const net = parseFloat(s.net_total_amount ?? s.total_amount ?? 0) || 0;
  if (s.grand_total !== undefined && s.grand_total !== null) return parseFloat(s.grand_total) || net;
  const tax = (s.tax_amount !== undefined && s.tax_amount !== null) ? (parseFloat(s.tax_amount) || 0) : (net * (taxRate.value / 100));
  return net + tax;
};

const getPaymentMethodName = (id) => {
  if (!id) return 'غير محددة';
  const m = paymentMethods.value.find(p => Number(p.id) === Number(id));
  return m?.name || 'غير محددة';
};

const getAgeBadge = (sale) => {
  // Don't show age badge for fully settled or returned invoices
  if (!sale || ['paid', 'returned', 'settled_by_return', 'closed_by_return', 'settled_by_credit', 'settled_mixed'].includes(sale.dynamic_status)) return null;
  const base = sale.due_date || sale.created_at;
  if (!base) return null;
  const days = Math.max(0, Math.floor((Date.now() - new Date(base).getTime()) / (1000 * 60 * 60 * 24)));
  if (days <= 7) return { cls: 'bg-emerald-50 text-emerald-600 border-emerald-100', label: `${days} يوم` };
  if (days <= 30) return { cls: 'bg-amber-50 text-amber-600 border-amber-100', label: `${days} يوم` };
  return { cls: 'bg-rose-50 text-rose-600 border-rose-100', label: `${days} يوم` };
};

// --- API Calls ---
const fetchSalesHistory = async () => {
  if (listAbortCtrl) listAbortCtrl.abort();
  listAbortCtrl = new AbortController();
  isLoadingList.value = true;
  showLoader();
  try {
    const params = filters.getApiParams({ includeTotals: true });

    if (!isExempt.value) {
      const wid = authStore?.user?.branch_id;
      if (!wid) {
        sales.value = [];
        filters.total.value = 0;
        filters.kpiSum.value = 0;
        filters.kpiTax.value = null;
        filters.kpiDiscount.value = null;
        showToast('لم يتم تعيين مخزن لحسابك.', 'error');
        return;
      }
      params.branchId = String(wid);
    }

    // Use salesStore for better caching and deduplication with business logic
    const response = await salesStore.fetchSalesList(params);
    
    // Handle response - store returns { status, data: { items, total, ... }, message }
    const resData = response?.data || response;
    if (resData) {
      sales.value = resData.items || [];
      filters.total.value = resData.total || 0;
      filters.kpiSum.value = resData.kpiSum || 0;
      filters.kpiTax.value = resData.kpiTax ?? null;
      filters.kpiDiscount.value = resData.kpiDiscount ?? null;
    } else {
      sales.value = [];
      filters.total.value = 0;
      filters.kpiSum.value = 0;
      filters.kpiTax.value = null;
      filters.kpiDiscount.value = null;
    }
  } catch (e) {
    const isAborted = e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.message === 'canceled';
    if (!isAborted) showToast('Failed to load sales history', 'error');
    sales.value = [];
    filters.total.value = 0;
    filters.kpiSum.value = 0;
    filters.kpiTax.value = null;
    filters.kpiDiscount.value = null;
  } finally {
    isLoadingList.value = false;
    hideLoader();
  }
};

const viewSaleDetails = async (saleId) => {
  if (detailsAbortCtrl) detailsAbortCtrl.abort();
  detailsAbortCtrl = new AbortController();
  isLoadingDetails.value = true;
  showLoader();
  try {
    const payload = await salesStore.fetchSaleDetails(saleId);
    const saleData = payload?.data || payload;
    selectedSale.value = (saleData && saleData.sale) ? saleData.sale : saleData;
    showDetailsModal.value = true;
  } catch (e) {
    const isAborted = e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.message === 'canceled';
    if (!isAborted) showToast('فشل في تحميل تفاصيل الفاتورة', 'error');
  } finally {
    isLoadingDetails.value = false;
    hideLoader();
  }
};

// --- Actions ---
// previousPage و nextPage مُدارتان من filters.previousPage/nextPage من useHistoryFilters

const toggleSort = (key) => {
  if (filters.sortKey.value === key) filters.sortAsc.value = !filters.sortAsc.value;
  else { filters.sortKey.value = key; filters.sortAsc.value = true; }
  try { localStorage.setItem('sales_hist_sortKey', filters.sortKey.value); localStorage.setItem('sales_hist_sortAsc', filters.sortAsc.value ? '1' : '0'); } catch {}
  filters.page.value = 1;
  fetchSalesHistory();
};

const markSelectedAsPaid = async () => {
  if (!filters.selectedIds.value.length || isMarkingPaid.value) return;
  const set = new Set(filters.selectedIds.value);
  const rows = (sales.value || []).filter(s => set.has(s.id));
  const payable = rows.filter(s => {
    const net = parseFloat(s.net_total_amount ?? s.total_amount ?? 0);
    const paid = parseFloat(s.paid_amount ?? 0);
    return s.status !== 'paid' && (net - paid > 0.0001);
  });
  if (!payable.length) { showToast('لا توجد فواتير قابلة للتحصيل ضمن المحدد', 'info'); return; }
  const confirmed = await AlertService.confirm(`سيتم تعليم ${payable.length} فاتورة كمسددة. هل تريد المتابعة؟`, 'تسديد الفواتير');
  if (!confirmed) return;
  isMarkingPaid.value = true;
  showLoader();
  let ok = 0, fail = 0;
  for (const s of payable) {
    try {
      const remaining = Math.max(0, parseFloat(s.net_total_amount ?? s.total_amount ?? 0) - parseFloat(s.paid_amount ?? 0));
      if (remaining <= 0) { ok++; continue; }
      const res = await salesStore.payDebt({ sale_id: s.id, customer_id: s.customer_id, amount: remaining, payment_method_id: cashMethodId.value ?? (paymentMethods.value[0]?.id || 1) });
      if (res?.status === 'success') ok++; else fail++;
    } catch { fail++; }
  }
  hideLoader();
  isMarkingPaid.value = false;
  showToast(`تمت معالجة ${ok} بنجاح، وفشل ${fail}`, fail ? 'warning' : 'success');
  filters.clearSelection();
  fetchSalesHistory();
};

const exportCsv = () => {
  const rows = [['ID', 'Customer', 'Items', 'Total', 'Status', 'Date']];
  (sales.value || []).forEach(s => rows.push([s.invoice_number || s.id, s.customer_name || 'عميل نقدي', s.total_items, getSaleGross(s), getStatusLabel(s.dynamic_status), s.created_at]));
  const csv = rows.map(r => r.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = `sales_${getLocalDateISO()}.csv`; a.click();
  URL.revokeObjectURL(url);
};

const exportSelectedCsv = () => {
  const set = new Set(filters.selectedIds.value);
  const rows = [['ID', 'Customer', 'Items', 'Total', 'Status', 'Date']];
  (sales.value || []).filter(s => set.has(s.id)).forEach(s => rows.push([s.invoice_number || s.id, s.customer_name || 'عميل نقدي', s.total_items, getSaleGross(s), getStatusLabel(s.dynamic_status), s.created_at]));
  const csv = rows.map(r => r.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = `sales_selected_${getLocalDateISO()}.csv`; a.click();
  URL.revokeObjectURL(url);
};

const printSelected = () => {
  const set = new Set(filters.selectedIds.value);
  const rows = (sales.value || []).filter(s => set.has(s.id));
  if (!rows.length) return;
  const win = window.open('', '_blank');
  const totalSelected = rows.reduce((sum, s) => sum + parseFloat(getSaleGross(s) || 0), 0);
  const html = `<!DOCTYPE html><html lang="ar"><head><meta charset="utf-8"/><title>طباعة فواتير</title>
  <style>body{font-family:Tahoma,Arial,sans-serif;direction:rtl}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:center}th{background:#f5f5f5}</style>
  </head><body><h3>فواتير مختارة</h3>
  <table><thead><tr><th>#</th><th>التاريخ</th><th>العميل</th><th>عدد المنتجات</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
  <tbody>${rows.map(s => `<tr><td>${s.id}</td><td>${formatDateTime(s.created_at)}</td><td>${s.customer_name || 'عميل نقدي'}</td><td>${s.total_items}</td><td>${formatPrice(getSaleGross(s))}</td><td>${getStatusLabel(s.dynamic_status)}</td></tr>`).join('')}</tbody>
  <tfoot><tr><th colspan="3">الملخص</th><th>${rows.length} فاتورة</th><th>${formatPrice(totalSelected)}</th><th></th></tr></tfoot></table>
  </body></html>`;
  win.document.open(); win.document.write(html); win.document.close();
  win.onload = () => { win.print(); setTimeout(() => { try { win.close(); } catch {} }, 300); };
};

const getSelectedPrintTemplate = () => {
  let t = (localStorage.getItem('pos_print_template') || '').toLowerCase();
  if (t === 'thermal') t = 'thermal-compact';
  if (t === 'a4') t = 'a4-simple';
  return new Set(['thermal-compact', 'thermal-detailed', 'a4-simple', 'a4-professional']).has(t) ? t : 'thermal-compact';
};

const printSaleDetails = async () => {
  if (!selectedSale.value) return;
  const html = getBuilderByTemplate(getSelectedPrintTemplate())(selectedSale.value);
  await printDocument(html);
};



// --- Watchers ---

// حفظ الفلاتر في localStorage
watch([() => filters.dateFrom.value, () => filters.dateTo.value, () => filters.statusFilter.value, () => filters.customerFilter.value, () => filters.perPage.value], () => {
  try {
    localStorage.setItem('sales_hist_dateFrom', filters.dateFrom.value || '');
    localStorage.setItem('sales_hist_dateTo', filters.dateTo.value || '');
    localStorage.setItem('sales_hist_status', filters.statusFilter.value || '');
    localStorage.setItem('sales_hist_customer', filters.customerFilter.value || '');
    localStorage.setItem('sales_hist_perPage', filters.perPage.value.toString());
  } catch {}
  filters.page.value = 1;
  fetchSalesHistory();
});

// Page watch
watch(() => filters.page.value, () => {
  try { localStorage.setItem('sales_hist_page', filters.page.value.toString()); } catch {}
  fetchSalesHistory();
});

watch(() => filters.selectedBranch.value, () => { filters.page.value = 1; fetchSalesHistory(); });

// Debounced search
watch(() => filters.searchQuery.value, () => {
  if (searchTimer) clearTimeout(searchTimer);
  filters.page.value = 1;
  searchTimer = setTimeout(() => fetchSalesHistory(), 400);
});

// Sync customer search with customerFilter
watch(() => filters.customerFilter.value, (nv) => {
  const selected = (customers.value || []).find(c => String(c.id) === String(nv));
  filters.customerSearch.value = selected ? (selected.name || selected.customer_name || '') : '';
});

// route query deep link
watch(() => route.query.id, async (nv) => {
  const qid = Number(nv || 0);
  if (qid > 0) { try { await viewSaleDetails(qid); } catch {} }
});

// --- onMounted ---
onMounted(async () => {
  // Try Bootstrap API first for better performance
  try {
    const data = await bootstrapStore.fetchPaymentsData();
    
    // Apply bootstrap data to stores
    if (data.customers) {
      customerStore.customers = data.customers;
    }
    if (data.paymentMethods) {
      paymentStore.paymentMethods = data.paymentMethods;
    }
    
    // Still need to load these separately as they're not in bootstrap
    await Promise.all([
      fetchSettings(),
      settingsStore.fetchTaxSettings().catch(() => {}),
      ensureExemptionLoaded().catch(() => {}),
    ]);
    
    console.log('[SalesHistory] Bootstrap data loaded successfully');
  } catch (bootstrapError) {
    console.warn('[SalesHistory] Bootstrap API failed, using fallback', bootstrapError);
    
    // Fallback: Load data individually
    await Promise.all([
      fetchSettings(),
      settingsStore.fetchTaxSettings().catch(() => {}),
      ensureExemptionLoaded().catch(() => {}),
      customerStore.fetchCustomers().catch(() => {}),
      paymentStore.fetchPaymentMethods().catch(() => {}),
    ]);
  }

  // tax rate depends on fetchTaxSettings completing first
  try {
    const enabled = settingsStore.isTaxEnabled.value;
    taxRate.value = enabled ? settingsStore.getTaxRate.value : 0;
  } catch {}

  // المخازن للمدراء فقط — depends on ensureExemptionLoaded
  if (isExempt.value) {
    try { await branchStore.fetchBranches(); } catch {}
  }

  // sync customer name with saved filter
  const selected = customers.value.find(c => String(c.id) === String(filters.customerFilter.value));
  filters.customerSearch.value = selected ? (selected.name || selected.customer_name || '') : '';

  // enforce critical columns
  visibleColumns.value.id = true;
  visibleColumns.value.created_at = true;
  try { localStorage.setItem('sales_hist_cols', JSON.stringify(visibleColumns.value)); } catch {}

  filters.page.value = 1;
  fetchSalesHistory();

  // auto-open من URL query ?id=
  const qid = Number(route.query.id || 0);
  if (qid > 0) { try { await viewSaleDetails(qid); } catch {} }
});

// --- onUnmounted — cleanup ---
onUnmounted(() => {
  if (listAbortCtrl) listAbortCtrl.abort();
  if (detailsAbortCtrl) detailsAbortCtrl.abort();
  if (searchTimer) clearTimeout(searchTimer);
  if (typeof window !== 'undefined') document.body.style.overflow = '';
});
</script>

<style scoped>



.kpi-card { @apply bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

.form-input-modern, .form-select-modern {
  @apply w-full h-[46px] bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm;
}

.filter-chip {
  @apply inline-flex items-center gap-2 bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl text-[10px] font-black border border-blue-100 shadow-sm;
}

.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter; }
.status-paid { @apply bg-emerald-100 text-emerald-700; }
.status-unpaid { @apply bg-rose-100 text-rose-700; }
.status-partial { @apply bg-amber-100 text-amber-700; }

.pagination-btn {
  @apply h-10 px-4 rounded-xl bg-white border border-slate-200 flex items-center gap-1 text-xs font-black hover:bg-slate-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed;
}

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-enter-active, .slide-leave-active { transition: all 0.3s ease-out; max-height: 600px; overflow: hidden; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>