<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-xl shadow-indigo-100 text-white">
          <i class="fas fa-shopping-bag text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none">سجل المشتريات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع وإدارة فواتير المشتريات والمدفوعات</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="filters.showFilters.value = !filters.showFilters.value" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-2', filters.showFilters.value ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-transparent text-slate-600 hover:bg-slate-50']">
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
              <input v-model="filters.searchQuery.value" type="text" class="form-input-modern pr-10" placeholder="ابحث بـ: رقم الفاتورة أو المورد..." />
              <i class="fas fa-search absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors pointer-events-none"></i>
              <button v-if="filters.searchQuery.value" @click="filters.searchQuery.value = ''" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح البحث">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="dateFromRef" type="date" v-model="filters.dateFrom.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateFromRef.showPicker()"
              ></i>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">إلى تاريخ</label>
            <div class="relative">
              <input ref="dateToRef" type="date" v-model="filters.dateTo.value" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateToRef.showPicker()"
              ></i>
            </div>
          </div>

          <div class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">حالة الفاتورة</label>
            <select v-model="filters.statusFilter.value" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option value="">كل الحالات</option>
              <option value="paid">مدفوعة</option>
              <option value="settled">مسددة</option>
              <option value="partial">جزئي</option>
              <option value="due">غير مدفوعة</option>
            </select>
            <i class="fas fa-filter absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
          <div class="relative md:col-span-2">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">المورد المستهدف</label>
            <div class="relative">
              <input
                type="text"
                v-model="filters.customerSearch.value"
                class="form-input-modern pr-10 font-bold text-sm"
                placeholder="ابحث عن مورد..."
                @focus="filters.showCustomerDropdown.value = true"
                @blur="hideSupplierDropdown"
                @input="filters.showCustomerDropdown.value = true"
              />
              <i class="fas fa-truck absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
              <button v-if="filters.customerFilter.value" @click="filters.clearCustomerFilter();" class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 hover:scale-110 transition-all duration-200 cursor-pointer active:scale-95 p-0.5" title="مسح التصفية">
                <i class="fas fa-circle-xmark text-sm"></i>
              </button>

              <div v-if="filters.showCustomerDropdown.value" class="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-60 overflow-auto py-2">
                <button @mousedown.prevent="filters.clearCustomerFilter();" class="w-full text-right px-5 py-3 text-xs font-black text-indigo-600 hover:bg-slate-50 border-b border-slate-50 transition-colors">
                  كل الموردين
                </button>
                <button v-for="s in filteredSuppliers" :key="s.id" @mousedown.prevent="selectSupplier(s)" class="w-full text-right px-5 py-3 text-xs font-bold text-slate-700 hover:bg-indigo-50 transition-colors">
                  {{ s.name || s.supplier_name }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="isExempt" class="relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع / المستودع</label>
            <select v-model="filters.selectedBranch.value" class="form-select-modern pr-10 font-bold text-sm cursor-pointer">
              <option value="">كل المخازن</option>
              <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name || w.branch_name }}</option>
            </select>
            <i class="fas fa-warehouse absolute right-4 top-[38px] text-slate-300 pointer-events-none"></i>
          </div>

          <button @click="resetFilters" class="h-[46px] w-full rounded-2xl bg-slate-100 text-slate-600 font-black text-xs hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-sync-alt"></i> إعادة تعيين
          </button>
        </div>

        <!-- Filter Chips -->
        <div class="flex flex-wrap gap-2 mt-6 pt-6 border-t border-slate-50" v-if="filters.hasActiveFilters.value || filters.customerFilter.value || (isExempt && filters.selectedBranch.value)">
          <div class="flex items-center gap-2 w-full">
            <div class="flex items-center gap-2 text-indigo-600 bg-indigo-50 px-3 py-2 rounded-lg border border-indigo-100">
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
                <i class="fas fa-filter ml-1 text-[9px]"></i>{{ getStatusLabel(filters.statusFilter.value) }}
                <i @click="filters.statusFilter.value=''" class="fas fa-times cursor-pointer opacity-60 group-hover:opacity-100 ml-1 transition-opacity"></i>
              </span>
              <span v-if="filters.customerFilter.value" class="filter-chip group">
                <i class="fas fa-truck ml-1 text-[9px]"></i>{{ filters.customerSearch.value }}
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
      <div class="kpi-card group border-l-4 border-l-indigo-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="fas fa-file-invoice"></i>
          </div>
          <div>
            <p class="kpi-label">عدد الفواتير</p>
            <p class="kpi-value text-slate-800">{{ kpiCount }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-truck-loading"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي المشتريات</p>
            <p class="kpi-value text-emerald-600">{{ formatPrice(kpiSum) }}</p>
          </div>
        </div>
      </div>

      <div v-if="kpiTax != null" class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-receipt"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الضريبة</p>
            <p class="kpi-value text-blue-600">{{ formatPrice(kpiTax) }}</p>
          </div>
        </div>
      </div>

      <div v-if="kpiDiscount != null" class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-tag"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الخصومات</p>
            <p class="kpi-value text-rose-600">{{ formatPrice(kpiDiscount) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Purchases Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">رقم الفاتورة</th>
              <th class="px-4 py-5">التاريخ</th>
              <th class="px-4 py-5">المورد</th>
              <th class="px-4 py-5 text-center">الأصناف</th>
              <th class="px-4 py-5">الإجمالي</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-6 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
           <tbody class="divide-y divide-slate-50">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoadingPurchases">
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

            <tr v-else-if="rows.length === 0" class="text-center">
              <td colspan="7" class="py-20">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-box-open text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد فواتير شراء مطابقة</p>
                </div>
              </td>
            </tr>

            <template v-else v-for="purchase in rows" :key="purchase?.id || Math.random()">
            <tr v-if="purchase && purchase.id" class="hover:bg-indigo-50/30 transition-all group">
              <td class="px-6 py-4 font-black text-slate-800">{{ purchase.invoice_number || ('#' + purchase.id) }}</td>
              <td class="px-4 py-4 text-xs font-bold text-slate-500 font-mono tracking-tighter">{{ formatDateTime(purchase.invoice_date) }}</td>
              <td class="px-4 py-4 font-bold text-slate-700 truncate max-w-[180px]">{{ purchase.supplier_name }}</td>
              <td class="px-4 py-4 text-center">
                <span class="bg-slate-100 text-slate-500 px-2.5 py-1 rounded-lg text-[10px] font-black">
                  {{ purchase.total_items ?? purchase.items_count ?? (Array.isArray(purchase.items) ? purchase.items.length : '-') }}
                </span>              
			 </td>
              <td class="px-4 py-4 font-black text-emerald-600 text-base">{{ formatPrice(purchase.total_amount) }}</td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', getDynamicStatusClass(purchase.dynamic_status || purchase.status)]">
                  {{ getStatusLabel(purchase.dynamic_status || purchase.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="viewPurchaseDetails(purchase.id)" class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-600 hover:text-white transition-all shadow-sm active:scale-95" title="عرض التفاصيل">
                    <i class="fas fa-eye text-sm"></i>
                  </button>
                  <button v-if="!['paid', 'settled'].includes(purchase.dynamic_status || purchase.status)" @click="openPaymentModal(purchase)" class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm active:scale-95" title="إضافة دفعة">
                    <i class="fas fa-dollar-sign text-sm"></i>
                  </button>
                </div>
              </td>
            </tr>
            </template>
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

    <!-- Purchase Details Modal -->
    <transition name="modal">
      <div v-if="showDetailsModal && selectedPurchase" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
        <div class="bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden max-h-[90vh] animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-100">
                <i class="fas fa-file-invoice text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">فاتورة شراء #{{ selectedPurchase.invoice_number || selectedPurchase.id }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">{{ formatDateTime(selectedPurchase.invoice_date || selectedPurchase.created_at) }}</p>
              </div>
            </div>
            <button @click="showDetailsModal = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>

          <div class="p-8 overflow-y-auto custom-scroll">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">المورد</p>
                <p class="text-sm font-bold text-slate-800">{{ selectedPurchase?.supplier_name || selectedPurchase?.supplier?.name || '-' }}</p>
              </div>
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الحالة</p>
                <span :class="['status-badge inline-block', getDynamicStatusClass(selectedPurchase.dynamic_status || selectedPurchase.status)]">
                  {{ getStatusLabel(selectedPurchase.dynamic_status || selectedPurchase.status) }}
                </span>
              </div>
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الفرع</p>
                <p class="text-sm font-bold text-slate-800">{{ selectedPurchase.branch_name || '-' }}</p>
              </div>
            </div>

            <h4 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-4 px-1">الأصناف الموردة</h4>
            <div class="border border-slate-100 rounded-3xl overflow-hidden shadow-sm mb-8">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-100">
                    <th class="px-4 py-4">المنتج</th>
                    <th class="px-4 py-4 text-center">الكمية</th>
                    <th class="px-4 py-4 text-center">سعر الوحدة</th>
                    <th class="px-4 py-4 text-center">الإجمالي</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="(it, idx) in (selectedPurchase?.items || selectedPurchase?.purchase?.items || [])" :key="idx" class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-4 py-4 font-bold text-slate-800">{{ it.product_name || it.name || '-' }}</td>
                    <td class="px-4 py-4 text-center font-black text-slate-500">{{ it.quantity || it.qty || 0 }}</td>
                    <td class="px-4 py-4 text-center font-bold">{{ formatPrice(it.price || it.unit_price || 0) }}</td>
                    <td class="px-4 py-4 text-center font-black text-slate-900">{{ formatPrice((it.price || it.unit_price || 0) * (it.quantity || it.qty || 0)) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex flex-col md:flex-row gap-8">
              <div class="flex-grow p-6 rounded-3xl bg-slate-50 border border-slate-100">
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">تفاصيل مالية إضافية</h5>
                <div class="space-y-2">
                  <div class="flex justify-between text-xs font-bold text-slate-500"><span>إجمالي المبلغ:</span><span class="text-slate-900">{{ formatPrice(selectedPurchase?.total_amount || 0) }}</span></div>
                  <div class="flex justify-between text-xs font-bold text-slate-500"><span>المبلغ المدفوع:</span><span class="text-emerald-600">{{ formatPrice(selectedPurchase?.paid_amount || 0) }}</span></div>
                  <div class="flex justify-between text-xs font-black pt-2 border-t border-slate-200"><span>المبلغ المتبقي:</span><span class="text-rose-600">{{ formatPrice((selectedPurchase?.total_amount || 0) - (selectedPurchase?.paid_amount || 0)) }}</span></div>
                </div>
              </div>
              <div class="w-full md:w-80 p-6 rounded-[2rem] bg-slate-900 text-white shadow-xl flex items-center justify-center">
                <div class="text-center">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">إجمالي الفاتورة النهائي</p>
                  <p class="text-3xl font-black text-blue-400 leading-none">{{ formatPrice(selectedPurchase?.total_amount || 0) }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3">
            <button @click="showDetailsModal = false" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-500 hover:bg-white transition-all">إغلاق</button>
            <button @click="printPurchaseDetails" class="px-8 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-black shadow-lg shadow-indigo-100 flex items-center gap-2 active:scale-95 transition-all">
              <i class="fas fa-print"></i> طباعة الفاتورة
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Payment Modal -->
    <transition name="modal">
      <div v-if="showPaymentModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden animate-modalIn border border-white">
          <div class="px-8 py-7 text-center border-b border-slate-50">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-hand-holding-usd text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">تسجيل دفعة للمورد</h3>
          </div>
          <div class="p-8 space-y-5">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">المبلغ</label>
              <input type="number" v-model.number="paymentData.amount" class="w-full h-16 rounded-2xl border-slate-100 bg-slate-50 text-3xl font-black text-center text-emerald-600 outline-none focus:bg-white focus:ring-4 focus:ring-emerald-50 transition-all" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Payment Date</label>
                <div class="relative">
                  <input ref="paymentDateRef" type="date" v-model="paymentData.payment_date" class="form-input-modern text-xs font-bold" />
                  <i 
                    class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                    @click="paymentDateRef.showPicker()"
                  ></i>
                </div>
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">طريقة الدفع</label>
                <select v-model.number="paymentData.payment_method_id" class="form-select-modern text-xs font-bold">
                  <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </div>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">رقم المرجع (اختياري)</label>
              <input type="text" v-model="paymentData.reference_number" class="form-input-modern text-xs font-bold" placeholder="شيك، تحويل..." />
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-4">
            <button @click="showPaymentModal = false" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-colors">إلغاء</button>
            <button @click="submitPayment" class="flex-[2] py-4 rounded-2xl bg-emerald-600 text-white font-black shadow-xl shadow-emerald-100 hover:bg-emerald-700 active:scale-95 transition-all">تأكيد الدفع</button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import BaseSpinner from '../../components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useHistoryFilters } from '@/composables/useHistoryFilters';
import { useLoader } from '@/composables/useLoader';
import { useToast } from '@/composables/useToast';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useSessionStore } from '@/stores/session/sessionStore';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { usePurchaseStore } from '@/stores/purchase/purchaseStore';
import { useBootstrapStore } from '@/stores/bootstrap';
import { getLocalDateISO } from '@/utils/date';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { printDocument } from '@/utils/PrintService';
import { buildPurchaseHtml } from '@/utils/printTemplates';

// ─── Composables ──────────────────────────────────────────────────────────────
const { showLoader, hideLoader } = useLoader();
const { showToast } = useToast();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const purchaseStore = usePurchaseStore();
const paymentStore = usePaymentStore();
const supplierStore = useSupplierStore();
const bootstrapStore = useBootstrapStore();

const route = useRoute();
const detailsAbortCtrl = ref(null);
const showDetailsModal = ref(false);
const selectedPurchase = ref(null);

// ─── Use History Filters Composable (موحد مع SalesHistory و ReturnsHistory) ──────
const filters = useHistoryFilters('purchases_hist_filters');
filters.loadFromLocalStorage();

// ─── State المحلية ───────────────────────────────────────────────────────────────
const rows = ref([]);
const isLoadingPurchases = ref(false);
const isLoadingDetails = ref(false);

// ─── Branch Filter من useHistoryFilters ──────────────────────────────────────────
const branches = computed(() => branchStore.branches);

// ─── Supplier Filter (Party Filter) ───────────────────────────────────────────────
const suppliers = computed(() => supplierStore.suppliers);

const filteredSuppliers = computed(() => {
  const q = (filters.customerSearch.value || '').toLowerCase();
  const list = suppliers.value || [];
  if (!q) return list.slice(0, 50);
  return list.filter(s => String(s.name || s.supplier_name || '').toLowerCase().includes(q)).slice(0, 50);
});

const selectSupplier = (s) => {
  filters.setCustomerFilter(s.id, s.name || s.supplier_name || '');
};

// ✅ blur مع delay للسماح بالـ mousedown
const hideSupplierDropdown = () => filters.hideCustomerDropdown();

// ─── KPI ──────────────────────────────────────────────────────────────────────
const kpiCount = computed(() => filters.total.value || rows.value.length);
const kpiSum = computed(() => rows.value.reduce((s, p) => s + parseFloat(p.total_amount || p.total || 0), 0));
const kpiTax = computed(() => {
  const any = rows.value.some(p => p.tax_amount != null);
  return any ? rows.value.reduce((s, p) => s + parseFloat(p.tax_amount || 0), 0) : null;
});
const kpiDiscount = computed(() => {
  const any = rows.value.some(p => p.discount_amount != null || p.discount_value != null);
  return any ? rows.value.reduce((s, p) => s + parseFloat((p.discount_amount ?? p.discount_value) || 0), 0) : null;
});

const totalPages = computed(() => Math.max(1, Math.ceil((filters.total.value || 0) / filters.perPage.value)));

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);
const formatDateTime = (date) => date ? new Date(date).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

// ─── Payment Methods & Status ──────────────────────────────────────────────────
const paymentMethods = computed(() => paymentStore.paymentMethods);
const cashMethodId = computed(() => paymentMethods.value.find(pm => pm.kind === 'cash')?.id || null);
const isCashById = (id) => paymentMethods.value.find(pm => Number(pm.id) === Number(id))?.kind === 'cash';

const getDynamicStatusClass = (status) => {
  const statusMap = {
    'paid': 'bg-emerald-100 text-emerald-700',
    'settled': 'bg-emerald-100 text-emerald-700',
    'partial': 'bg-amber-100 text-amber-700',
    'due': 'bg-rose-100 text-rose-700',
    'pending': 'bg-slate-100 text-slate-700',
  };
  return statusMap[status] || 'bg-slate-100 text-slate-700';
};

const getStatusLabel = (status) => {
  const labels = {
    'paid': 'مدفوعة',
    'settled': 'مسددة',
    'partial': 'جزئي',
    'due': 'غير مدفوعة',
    'pending': 'قيد الانتظار',
  };
  return labels[status] || status;
};

// ─── API ──────────────────────────────────────────────────────────────────────
let purchasesAbortCtrl = null;

const fetchPurchases = async () => {
  // Cancel previous request
  if (purchasesAbortCtrl) purchasesAbortCtrl.abort();
  purchasesAbortCtrl = new AbortController();
  const currentCtrl = purchasesAbortCtrl;

  isLoadingPurchases.value = true;
  try {
    // ✅ Set branch filter based on user type
    if (!isExempt.value) {
      // Regular user - must have branch assigned
      const wid = authStore?.user?.branch_id;
      if (!wid) {
        rows.value = [];
        filters.total.value = 0;
        showToast('لم يتم تعيين مخزن لحسابك.', 'error');
        return;
      }
      filters.selectedBranch.value = String(wid);
    }
    // If admin (isExempt) and no branch selected → show all branches (branchId will be null)

    // استخدم getApiParams من useHistoryFilters
    const params = filters.getApiParams({
      supplierId: filters.customerFilter.value || undefined
    });

    const response = await purchaseStore.fetchPurchasesList(params);

    // Verify controller hasn't changed
    if (currentCtrl !== purchasesAbortCtrl) return;

    if (response?.status === 'success') {
      const resData = response.data;
      const list = Array.isArray(resData) ? resData : (resData?.items || []);
      rows.value = list;
      filters.total.value = resData?.total || list.length;
    } else {
      rows.value = [];
      filters.total.value = 0;
    }
  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError' || e?.code === 'ERR_CANCELED';
    if (!isAborted) {
      showToast('فشل في تحميل سجل المشتريات', 'error');
      rows.value = [];
      filters.total.value = 0;
    }
  } finally {
    if (currentCtrl === purchasesAbortCtrl) isLoadingPurchases.value = false;
    hideLoader();
  }
};

const resetFilters = () => {
  filters.resetFilters();
  fetchPurchases();
};

// previousPage و nextPage مُدارتان من filters.previousPage/nextPage من useHistoryFilters

const viewPurchaseDetails = async (purchaseRow) => {
  if (!purchaseRow || !purchaseRow.id) return;
  if (detailsAbortCtrl.value) detailsAbortCtrl.value.abort();
  detailsAbortCtrl.value = new AbortController();
  const currentCtrl = detailsAbortCtrl.value;

  isLoadingDetails.value = true;
  showLoader();
  try {
    const response = await purchaseStore.getPurchaseById(purchaseRow.id, { signal: currentCtrl.signal });
    if (currentCtrl !== detailsAbortCtrl.value) return;

    if (response) {
      selectedPurchase.value = response;
      showDetailsModal.value = true;
    } else {
      showToast('فشل في تحميل تفاصيل المشترية', 'error');
    }
  } catch (e) {
    const isAborted = e?.name === 'AbortError' || e?.name === 'CanceledError' || e?.code === 'ERR_CANCELED';
    if (!isAborted) {
      showToast('فشل في تحميل تفاصيل المشترية', 'error');
    }
  } finally {
    if (currentCtrl === detailsAbortCtrl.value) isLoadingDetails.value = false;
    hideLoader();
  }
};

// ─── Payment Modal ─────────────────────────────────────────────────────────────
const showPaymentModal = ref(false);
const paymentData = ref({
  purchase_id: null,
  supplier_id: null,
  amount: 0,
  payment_method_id: null,
  payment_date: getLocalDateISO(),
  reference_number: ''
});
const currentPayingPurchase = ref(null);
const paymentDateRef = ref(null);

const openPaymentModal = (p) => {
  paymentData.value = {
    purchase_id: p.id,
    supplier_id: p.supplier_id,
    amount: (p.total_amount || 0) - (p.paid_amount || 0),
    payment_method_id: cashMethodId.value ?? paymentMethods.value[0]?.id ?? null,
    payment_date: getLocalDateISO(),
    reference_number: ''
  };
  showPaymentModal.value = true;
  currentPayingPurchase.value = p;
};

const submitPayment = async () => {
  const amount = Number(paymentData.value.amount || 0);
  if (amount <= 0) return showToast('الرجاء إدخال مبلغ صحيح', 'error');
  
  try { await ensureExemptionLoaded(); } catch {}
  
  if (!isExempt.value && isCashById(paymentData.value.payment_method_id) && amount > 0) {
    const wid = authStore?.user?.branch_id ?? currentPayingPurchase.value?.branch_id;
    if (!wid) return showToast('يجب تحديد الفرع', 'error');
    try {
      const sessionStore = useSessionStore();
      const result = await sessionStore.getCurrentSession(wid, authStore?.user?.id || null);
      if (!result.data?.id) return showToast('لا توجد جلسة كاشير مفتوحة', 'error');
    } catch { return showToast('خطأ في التحقق من الجلسة', 'error'); }
  }
  
  showLoader();
  try {
    const res = await purchaseStore.addPayment(paymentData.value.purchase_id, {
      amount,
      payment_date: paymentData.value.payment_date,
      payment_method_id: paymentData.value.payment_method_id,
      reference_number: paymentData.value.reference_number || undefined,
      branch_id: String(authStore?.user?.branch_id ?? currentPayingPurchase.value?.branch_id)
    });
    if (res?.status === 'success') {
      showToast('تمت العملية', 'success');
      showPaymentModal.value = false;
      fetchPurchases();
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'فشل التسجيل', 'error');
  } finally {
    hideLoader();
  }
};

const printPurchaseDetails = async () => {
  if (!selectedPurchase.value) return;
  const p = { ...selectedPurchase.value, items: selectedPurchase.value.items || selectedPurchase.value.purchase?.items || [] };
  await printDocument(buildPurchaseHtml(p));
};

// ─── Watchers ─────────────────────────────────────────────────────────────────

// ✅ debounce على searchQuery بدل fetch فوري
let searchDebounceTimer = null;
watch(filters.searchQuery, () => {
  clearTimeout(searchDebounceTimer);
  filters.page.value = 1;
  searchDebounceTimer = setTimeout(fetchPurchases, 400);
});

// الفلاتر الأخرى فورية (من useHistoryFilters)
watch([filters.customerFilter, filters.dateFrom, filters.dateTo, filters.selectedBranch], () => {
  filters.page.value = 1;
  fetchPurchases();
});

// الصفحة منفصلة
watch(filters.page, fetchPurchases);

// ✅ sync نص الـ supplier لما الـ ID يتغير
watch([filters.customerFilter, suppliers], () => {
  const list = suppliers.value || [];
  const found = list.find(x => String(x.id) === String(filters.customerFilter.value));
  filters.customerSearch.value = found ? (found.name || found.supplier_name || '') : '';
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Try Bootstrap API first for better performance
  try {
    const data = await bootstrapStore.fetchManagementData('purchase');
    
    // Apply bootstrap data to stores
    if (data.branches && isExempt.value) {
      branchStore.setBranches(data.branches);
    }
    if (data.paymentMethods) {
      paymentStore.paymentMethods = data.paymentMethods;
    }
    if (data.suppliers) {
      supplierStore.suppliers = data.suppliers;
    }
    
    // Still need these separately
    await Promise.all([
      fetchSettings(),
      ensureExemptionLoaded()
    ]);
    
    console.log('[PurchaseHistory] Bootstrap data loaded successfully');
  } catch (bootstrapError) {
    console.warn('[PurchaseHistory] Bootstrap API failed, using fallback', bootstrapError);
    
    // Fallback: Load data individually
    await Promise.all([fetchSettings(), ensureExemptionLoaded()]);
    
    // جلب الفروع إذا كان المستخدم معفى
    if (isExempt.value) {
      try { 
        await branchStore.fetchBranches();
      } catch (e) { 
        console.error('خطأ في جلب الفروع:', e); 
      }
    }

    // Load payment methods
    try {
      await paymentStore.fetchPaymentMethods();
    } catch { /* ignore */ }

    // Load suppliers
    try {
      await supplierStore.fetchSuppliers();
    } catch { /* ignore */ }
  }

  // تأخير صغير للتأكد من تحميل البيانات
  await new Promise(resolve => setTimeout(resolve, 100));

  fetchPurchases();

  const qid = Number(route.query.id || 0);
  if (qid > 0) {
    try {
      await viewPurchaseDetails({ id: qid });
    } catch (err) {
      console.error('Failed to load purchase details from query.id', err);
    }
  }
});

// ✅ cleanup عند الخروج من الصفحة
onUnmounted(() => {
  if (purchasesAbortCtrl) purchasesAbortCtrl.abort();
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});
</script>

<style scoped>
.kpi-card { @apply bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

.form-input-modern, .form-select-modern {
  @apply w-full h-[46px] bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 shadow-sm;
}

.filter-chip { @apply inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-xl text-[10px] font-black border border-indigo-100 shadow-sm transition-all; }
.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply h-10 px-4 rounded-xl bg-white border border-slate-200 flex items-center gap-1 text-xs font-black hover:bg-slate-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-enter-active, .slide-leave-active { transition: all 0.3s ease-out; max-height: 500px; overflow: hidden; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0; }

.modal-enter-active, .modal-leave-active { transition: all 0.3s ease-out; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95); }

.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
