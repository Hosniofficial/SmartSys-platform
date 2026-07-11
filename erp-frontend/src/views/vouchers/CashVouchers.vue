<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-file-invoice-dollar text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">سندات القبض والصرف</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">إدارة الحركات النقدية، السندات المالية، وتسوية حسابات الأطراف</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="onAddVoucher" class="h-11 px-8 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus-circle"></i> إضافة سند جديد
        </button>
      </div>
    </div>

    <!-- Financial KPI Summary -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-arrow-down-long"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">إجمالي المقبوضات (سندات قبض)</p>
            <p class="kpi-value text-emerald-600">{{ formatPrice(vouchers.filter(v => v.type === 'receipt').reduce((s, v) => s + parseFloat(v.amount), 0)) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-arrow-up-long"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">إجمالي المدفوعات (سندات صرف)</p>
            <p class="kpi-value text-rose-600">{{ formatPrice(vouchers.filter(v => v.type === 'payment').reduce((s, v) => s + parseFloat(v.amount), 0)) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-scale-balanced"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">صافي الحركة النقدية</p>
            <p class="kpi-value text-slate-800">{{ formatPrice(vouchers.filter(v => v.type === 'receipt').reduce((s, v) => s + parseFloat(v.amount), 0) - vouchers.filter(v => v.type === 'payment').reduce((s, v) => s + parseFloat(v.amount), 0)) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters Toolbar - Enhanced with Branch Selection -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-visible">
      
      <!-- Filters Panel -->
      <div class="p-8 border-b border-slate-50 space-y-6 relative">
        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
          <!-- Search by Reference Number or Invoice -->
          <div class="flex-grow group relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
            <div class="relative">
              <input 
                ref="searchInputRef"
                v-model="search" 
                type="text" 
                class="form-input-modern pr-11 w-full" 
                placeholder="ابحث بـ: رقم السند أو الفاتورة..." 
                @focus="showSearchDropdown = true"
                @blur="handleSearchBlur"
              />
              <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors text-xs"></i>
            </div>
            
            <!-- ✅ NEW: Teleport Search Dropdown -->
            <Teleport to="body">
              <transition name="dropdown">
                <div 
                  v-if="showSearchDropdown && search.length > 0" 
                  class="fixed bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-80 overflow-y-auto"
                  :style="searchDropdownPosition"
                  style="z-index: 99999"
                >
                  <!-- Loading State -->
                  <div v-if="isLoadingSearch" class="px-4 py-8 text-center">
                    <BaseSpinner class="w-5 h-5 text-blue-500 mx-auto" />
                    <p class="text-xs text-slate-400 mt-2">جاري البحث...</p>
                  </div>
                  
                  <!-- No Results -->
                  <div v-else-if="searchResults.length === 0" class="px-4 py-6 text-center">
                    <i class="fas fa-search text-2xl text-slate-200 mb-2 block"></i>
                    <p class="text-xs text-slate-400">لا توجد نتائج</p>
                  </div>
                  
                  <!-- Results -->
                  <template v-else>
                    <div class="px-3 py-2 border-b border-slate-100 bg-slate-50 sticky top-0">
                      <span class="text-[10px] font-black text-slate-400">النتائج ({{ searchResults.length }})</span>
                    </div>
                    <button 
                      v-for="(result, idx) in searchResults.slice(0, 20)" 
                      :key="result.id || idx"
                      @click="selectSearchResult(result)"
                      class="w-full px-4 py-3 text-right hover:bg-blue-50 transition-all border-b border-slate-50 last:border-b-0 flex items-center gap-3 group"
                    >
                      <div class="flex-grow">
                        <div class="text-sm font-black text-slate-800">
                          {{ result.reference_number || result.id }}
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">
                          {{ formatDate(result.date) }} • {{ formatPrice(result.amount) }}
                        </div>
                      </div>
                      <i class="fas fa-arrow-left text-slate-300 group-hover:text-blue-600"></i>
                    </button>
                  </template>
                </div>
              </transition>
            </Teleport>
          </div>

          <!-- Voucher Type Filter -->
          <div class="lg:w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">نوع السند</label>
            <select
              v-model="filterType"
              class="form-select-modern font-black"
            >
              <option value="all">كل السندات</option>
              <option value="receipt">سندات قبض</option>
              <option value="payment">سندات صرف</option>
            </select>
          </div>

          <!-- Branch Selection (visible only for exempt users) -->
          <div v-if="isExempt" class="lg:w-56">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع / المستودع</label>
            <select
              v-model="selectedBranch"
              @change="handleBranchChange"
              class="form-select-modern font-black"
            >
              <option :value="null">كل الفروع</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">
                {{ b.name || b.branch_name }}
              </option>
            </select>
          </div>

          <!-- From Date -->
          <div class="lg:w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="dateFromRef" type="date" v-model="dateFrom" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateFromRef?.showPicker?.()"
              ></i>
            </div>
          </div>

          <!-- To Date -->
          <div class="lg:w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">إلى تاريخ</label>
            <div class="relative">
              <input ref="dateToRef" type="date" v-model="dateTo" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="dateToRef?.showPicker?.()"
              ></i>
            </div>
          </div>

          <!-- Results Per Page -->
          <div class="lg:w-40">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">النتائج / صفحة</label>
            <select v-model.number="pageSize" class="form-select-modern font-black">
              <option :value="10">10 سجلات</option>
              <option :value="20">20 سجل</option>
              <option :value="50">50 سجل</option>
            </select>
          </div>
        </div>

        <!-- Filter Actions Row -->
        <div class="flex items-center justify-between pt-6 border-t border-slate-50">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400 uppercase">إجمالي النتائج المصفاة:</span>
            <span class="text-xs font-black text-slate-800 bg-slate-100 px-3 py-1 rounded-lg">{{ totalCount }}</span>
          </div>
          <button @click="fetchVouchers" class="text-[10px] font-black text-blue-600 hover:underline uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-sync-alt"></i> تحديث السجلات
          </button>
        </div>
      </div>
    </div>
    <!-- ✅ closing Filters Toolbar -->

    <!-- Main Vouchers Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
      <!-- Vouchers Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
              <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-6 py-5 w-16 text-center">#</th>
                <th class="px-4 py-5">تاريخ السند</th>
                <th class="px-4 py-5 text-center">نوع العملية</th>
                <th class="px-4 py-5">قيمة السند</th>
                <th class="px-4 py-5">الحساب المتأثر</th>
                <th class="px-6 py-5">البيان / الوصف</th>
                <th class="px-8 py-5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <!-- Skeleton loading for table (GPU-accelerated) -->
              <template v-if="isLoading">
                <tr v-for="row in 6" :key="row">
                  <td class="px-6 py-4 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                  <td class="px-4 py-4"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                  <td class="px-4 py-4 text-center"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                  <td class="px-4 py-4"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                  <td class="px-4 py-4"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="12rem" animation="shimmer" /></td>
                  <td class="px-8 py-4 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                </tr>
              </template>
              <template v-else>
                <tr v-if="vouchers.length === 0" class="text-center">
                  <td colspan="7" class="py-24 opacity-20 text-slate-400">
                    <i class="fas fa-folder-open text-6xl mb-4"></i>
                    <p class="font-black text-sm uppercase">لا توجد سندات مسجلة</p>
                  </td>
                </tr>
                <tr v-for="(voucher, idx) in vouchers" :key="voucher.id" class="hover:bg-blue-50/30 transition-all group font-bold">
                <td class="px-6 py-4 text-center text-slate-300 font-mono text-xs">{{ (currentPage - 1) * pageSize + idx + 1 }}</td>
                <td class="px-4 py-4 text-xs text-slate-500 font-mono tracking-tighter">{{ voucher.date }}</td>
                <td class="px-4 py-4 text-center">
                  <span :class="[voucher.type === 'receipt' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700']" class="status-badge">
                    {{ voucher.type === 'receipt' ? 'سند قبض' : 'سند صرف' }}
                  </span>
                </td>
                <td class="px-4 py-4">
                  <span :class="[voucher.type === 'receipt' ? 'text-emerald-600' : 'text-rose-600']" class="font-black text-base font-mono tracking-tighter">
                    {{ formatPrice(voucher.amount) }} <span class="text-[10px] opacity-40 mr-1">{{ currencySymbol }}</span>
                  </span>
                </td>
                <td class="px-4 py-4 text-slate-700">{{ voucher.account_name }}</td>
                <td class="px-6 py-4 text-slate-400 text-xs italic truncate max-w-[250px]" :title="voucher.description">{{ voucher.description }}</td>
                <td class="px-8 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="onEditVoucher(voucher)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="تعديل">
                      <i class="fas fa-pen text-xs"></i>
                    </button>
                    <button @click="onDeleteVoucher(voucher)" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95" title="حذف">
                      <i class="fas fa-trash-alt text-xs"></i>
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
          صفحة <span class="text-slate-800">{{ currentPage }}</span> من <span class="text-slate-800">{{ totalPages }}</span>
        </div>
        
        <div class="flex items-center gap-1">
          <button @click="previousPage()" :disabled="currentPage <= 1" class="pagination-btn">
            <i class="fas fa-angle-right"></i>
          </button>
          <div class="px-6 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm">
            {{ currentPage }} / {{ totalPages }}
          </div>
          <button @click="nextPage(totalPages)" :disabled="currentPage >= totalPages" class="pagination-btn">
            <i class="fas fa-angle-left"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Modals Section -->

    <!-- Add Voucher Modal (Logic Preserved) -->
    <transition name="modal">
      <div v-if="showAddModal" class="modal-overlay">
        <div class="modal-content-modern max-w-2xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
                <i class="fas fa-plus-circle text-xl"></i>
              </div>
              <h3 class="text-xl font-black text-slate-800 leading-none">إصدار سند مالي جديد</h3>
            </div>
            <button @click="closeAddModal" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>

          <form @submit.prevent="submitAddVoucher" class="p-8 space-y-6 text-right" dir="rtl">
            <!-- Lists Loading Indicator -->
            <div v-if="listsLoading" class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 text-sm font-black text-center flex items-center justify-center gap-2">
              <BaseSpinner :size="16" color="#3b82f6" />
              <span>جاري تحميل القوائم...</span>
            </div>
            
            <!-- Lists Error Display -->
            <div v-if="listsError" class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-black text-center">
              <i class="fas fa-exclamation-triangle ml-2"></i>{{ listsError }}
            </div>
            
            <!-- Add Error Display -->
            <div v-if="addError" class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-black text-center">{{ addError }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="modal-label">نوع العملية المالية <span class="text-rose-500">*</span></label>
                <select v-model="addForm.type" class="form-select-modern font-black" required>
                  <option value="receipt">قبض (وارد للخزينة)</option>
                  <option value="payment">صرف (صادر من الخزينة)</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="modal-label">تاريخ المستند</label>
                <div class="relative">
                  <input ref="addFormDateRef" v-model="addForm.date" type="date" class="form-input-modern font-bold" required />
                  <i 
                    class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                    @click="addFormDateRef.showPicker()"
                  ></i>
                </div>
              </div>
              <div class="col-span-2 space-y-2">
                <label class="modal-label">الفرع / الفرع المرتبط <span class="text-rose-500">*</span></label>
                <select v-model="addForm.branch_id" class="form-select-modern font-black" :required="!isExempt">
                  <option value="">-- اختر الفرع --</option>
                  <option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="modal-label text-blue-600">المبلغ المدفوع <span class="text-rose-500">*</span></label>
                <input v-model.number="addForm.amount" type="number" min="0.01" step="0.01" placeholder="0" class="w-full h-14 rounded-2xl border-slate-100 bg-slate-50 text-3xl font-black text-center text-blue-600 focus:bg-white outline-none focus:ring-4 focus:ring-blue-50 transition-all" required />
              </div>
              <div class="space-y-2">
                <label class="modal-label">طريقة الدفع <span class="text-rose-500">*</span></label>
                <select v-model="addForm.payment_method_id" class="form-select-modern font-bold h-14" @change="updateAccountFromPaymentMethod">
                  <option value="">-- اختر طريقة الدفع --</option>
                  <option v-for="pm in validPaymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                </select>
                <!-- Display linked account (info only) -->
                <div v-if="addForm.account_id" class="text-xs text-slate-500 font-bold mt-1 px-2 py-1 bg-blue-50 rounded-lg">
                  <i class="fas fa-link text-blue-500 ml-1"></i>
                  الحساب المرتبط: <strong>{{ getAccountName(addForm.account_id) }}</strong>
                </div>
              </div>

              <!-- Counterparty Logic (Preserved) - Customer & Invoice aligned with other fields -->
              <div v-if="addForm.type === 'receipt'" class="space-y-2">
                <label class="modal-label">العميل المستهدف</label>
                <select v-model="addForm.customer_id" class="form-select-modern font-bold h-14">
                  <option :value="null">-- بدون عميل (إيراد عام) --</option>
                  <option v-for="c in customersList" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div v-if="addForm.type === 'receipt' && addForm.customer_id" class="space-y-2">
                <label class="modal-label">ربط بفاتورة مبيعات <span class="text-slate-400 font-normal normal-case">(اختياري)</span></label>
                <div v-if="customerSalesLoading" class="text-xs text-slate-400 font-bold py-2 flex items-center justify-center h-14">جاري تحميل الفواتير...</div>
                <select v-else v-model="addForm.sale_id" class="form-select-modern font-bold h-14">
                  <option :value="null">— بدون ربط —</option>
                  <option v-if="customerPendingSales.length === 0" disabled>لا توجد فواتير</option>
                  <option v-for="s in customerPendingSales" :key="s.id" :value="s.id">
                    {{ s.invoice_number }} — {{ formatPrice(s.net_total_amount) }} (متبقي: {{ formatPrice(s.remaining_balance ?? (s.net_total_amount - (s.actual_paid_amount || s.paid_amount || 0))) }})
                  </option>
                </select>
              </div>
              <div v-else-if="addForm.type === 'payment'" class="space-y-2">
                <label class="modal-label">جهة الصرف المستهدفة</label>
                <div class="flex gap-4 p-2 bg-slate-50 rounded-xl mb-2">
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-black"><input type="radio" v-model="addForm.payment_to_type" value="supplier" class="w-4 h-4 text-blue-600"> مورد</label>
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-black"><input type="radio" v-model="addForm.payment_to_type" value="expense" class="w-4 h-4 text-blue-600"> مصروف</label>
                </div>
                <select v-if="addForm.payment_to_type === 'supplier'" v-model="addForm.supplier_id"
                  class="form-select-modern font-bold"
                  @change="addForm.purchase_id = null; loadSupplierPurchases($event.target.value)">
                  <option value="">اختر المورد</option>
                  <option v-for="s in suppliersList" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <div v-if="addForm.payment_to_type === 'supplier' && addForm.supplier_id" class="space-y-1 mt-2">
                  <label class="modal-label">ربط بفاتورة شراء <span class="text-slate-400 font-normal normal-case">(اختياري)</span></label>
                  <div v-if="supplierPurchasesLoading" class="text-xs text-slate-400 font-bold py-2">جاري تحميل الفواتير...</div>
                  <select v-else v-model="addForm.purchase_id" class="form-select-modern font-bold">
                    <option :value="null">— بدون ربط بفاتورة —</option>
                    <option v-if="supplierPendingPurchases.length === 0" disabled>لا توجد فواتير مستحقة لهذا المورد</option>
                    <option v-for="p in supplierPendingPurchases" :key="p.id" :value="p.id">
                      {{ p.invoice_number }} — {{ formatPrice(p.total_amount) }} (متبقي: {{ formatPrice(p.remaining_balance ?? (p.total_amount - (p.actual_paid_amount || p.paid_amount || 0))) }})
                    </option>
                  </select>
                </div>
                <select v-else v-model="addForm.expense_account_id" class="form-select-modern font-bold">
                  <option value="">اختر المصروف</option>
                  <option v-for="e in expensesList" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
              </div>
              
              <div class="col-span-2 space-y-2">
                <label class="modal-label">البيان / ملاحظات المستند</label>
                <textarea v-model="addForm.description" class="w-full rounded-2xl border-slate-200 p-4 text-sm font-bold bg-slate-50 focus:bg-white transition-all outline-none" rows="2" placeholder="اكتب وصفاً مفصلاً للعملية..."></textarea>
              </div>
            </div>

            <!-- Pre-validation Notice (Preserved) -->
            <transition name="fade">
              <div v-if="addForm.type === 'receipt' && addForm.customer_id" class="p-4 bg-amber-50 border border-amber-100 rounded-[1.5rem] flex items-start gap-4">
                <i class="fas fa-circle-exclamation text-amber-500 mt-1"></i>
                <p class="text-[11px] font-bold text-amber-800 leading-relaxed italic">تنبيه محاسبي: لن يتم قبول السند إذا كان رصيد العميل دائن (له مبلغ). القبض مخصص فقط لتسديد المديونيات المستحقة. لرد المبالغ للعميل يرجى استخدام سند صرف.</p>
              </div>
            </transition>

            <div class="flex justify-end gap-4 pt-4 border-t border-slate-50">
              <button type="button" @click="closeAddModal" class="px-8 py-3 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all text-xs">إلغاء</button>
              <button type="submit" :disabled="addLoading" class="px-12 py-3 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3">
                <BaseSpinner v-if="addLoading" :size="16" color="#fff" />
                <span>حفظ السند المالي</span>
              </button>
            </div>
            <p v-if="addError" class="text-[11px] text-rose-500 font-black text-center animate-shake">{{ addError }}</p>
          </form>
        </div>
      </div>
    </transition>

    <!-- Edit Voucher Modal (Similar Modern Style) -->
    <transition name="modal">
      <div v-if="showEditModal" class="modal-overlay">
        <div class="modal-content-modern max-w-2xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-xl font-black text-slate-800 leading-none"><i class="fas fa-edit text-blue-500 ml-2"></i> تعديل السند المالي</h3>
            <button @click="closeEditModal" class="text-slate-400 hover:text-rose-500"><i class="fas fa-times text-xl"></i></button>
          </div>
          <form @submit.prevent="submitEditVoucher" class="p-8 space-y-6 text-right" dir="rtl">
            <!-- Lists Loading Indicator -->
            <div v-if="listsLoading" class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 text-sm font-black text-center flex items-center justify-center gap-2">
              <BaseSpinner :size="16" color="#3b82f6" />
              <span>جاري تحميل القوائم...</span>
            </div>
            
            <!-- Lists Error Display -->
            <div v-if="listsError" class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-black text-center">
              <i class="fas fa-exclamation-triangle ml-2"></i>{{ listsError }}
            </div>
            
            <!-- Error Display -->
            <div v-if="editError" class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-black text-center">{{ editError }}</div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
               <div class="space-y-2"><label class="modal-label">نوع العملية</label><select v-model="editForm.type" class="form-select-modern font-black"><option value="receipt">قبض</option><option value="payment">صرف</option></select></div>
               <div class="space-y-2"><label class="modal-label">التاريخ</label><div class="relative"><input ref="editFormDateRef" v-model="editForm.date" type="date" class="form-input-modern font-bold" /><i 
                class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="editFormDateRef.showPicker()"
              ></i></div></div>
               <div class="col-span-2 space-y-2"><label class="modal-label">المخزن</label><select v-model="editForm.branch_id" class="form-select-modern font-black h-14" :required="!isExempt"><option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option></select></div>
               <div class="space-y-2"><label class="modal-label">المبلغ المدفوع</label><input v-model.number="editForm.amount" type="number" step="0.01" placeholder="0" class="form-input-modern font-black text-blue-600 h-14" /></div>
               <div class="space-y-2"><label class="modal-label">طريقة الدفع</label><select v-model="editForm.payment_method_id" class="form-select-modern font-bold h-14" @change="updateAccountFromPaymentMethod"><option v-for="pm in validPaymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option></select><div v-if="editForm.account_id" class="text-xs text-slate-500 font-bold mt-1 px-2 py-1 bg-blue-50 rounded-lg"><i class="fas fa-link text-blue-500 ml-1"></i>الحساب المرتبط: <strong>{{ getAccountName(editForm.account_id) }}</strong></div></div>
            </div>
            
            <div class="space-y-4">
              <div v-if="editForm.type === 'receipt'" class="grid lg:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="modal-label">العميل</label>
                  <select v-model="editForm.customer_id" class="form-select-modern font-bold h-14">
                    <option value="">اختر العميل</option>
                    <option v-for="c in customersList" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div v-if="editForm.customer_id" class="space-y-2">
                  <label class="modal-label">ربط بفاتورة مبيعات <span class="text-slate-400 font-normal normal-case">(اختياري)</span></label>
                  <div v-if="editFormCustomerSalesLoading" class="text-xs text-slate-400 font-bold py-2 flex items-center justify-center h-14">جاري تحميل الفواتير...</div>
                  <select v-else v-model="editForm.sale_id" class="form-select-modern font-bold h-14">
                    <option :value="null">— بدون ربط —</option>
                    <option v-if="editFormCustomerPendingSales.length === 0" disabled>لا توجد فواتير</option>
                    <option v-for="s in editFormCustomerPendingSales" :key="s.id" :value="s.id">
                      {{ s.invoice_number }} — {{ formatPrice(s.net_total_amount) }} (متبقي: {{ formatPrice(s.remaining_balance ?? (s.net_total_amount - (s.actual_paid_amount || s.paid_amount || 0))) }})
                    </option>
                  </select>
                </div>
              </div>
              
              <div v-if="editForm.customer_id" class="p-4 bg-amber-50 border border-amber-100 rounded-[1.5rem] flex items-start gap-4">
                <i class="fas fa-circle-exclamation text-amber-500 mt-1"></i>
                <p class="text-[11px] font-bold text-amber-800 leading-relaxed italic">تنبيه محاسبي: لن يتم قبول السند إذا كان رصيد العميل دائن (له مبلغ). القبض مخصص فقط لتسديد المديونيات المستحقة. لرد المبالغ للعميل يرجى استخدام سند صرف.</p>
              </div>
              
              <div v-if="editForm.type === 'payment'" class="space-y-2">
                <label class="modal-label">الطرف المقابل</label>
                <div class="flex gap-4">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="editForm.payment_to_type" value="supplier" class="text-blue-600">
                    <span class="font-black">مورد</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="editForm.payment_to_type" value="expense" class="text-blue-600">
                    <span class="font-black">مصروف</span>
                  </label>
                </div>
                
                <div v-if="editForm.payment_to_type === 'supplier'" class="space-y-2">
                  <label class="modal-label">المورد</label>
                  <select v-model="editForm.supplier_id" class="form-select-modern font-bold">
                    <option value="">اختر المورد</option>
                    <option v-for="s in suppliersList" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                
                <div v-if="editForm.payment_to_type === 'expense'" class="space-y-2">
                  <label class="modal-label">حساب المصروف</label>
                  <select v-model="editForm.expense_account_id" class="form-select-modern font-bold">
                    <option value="">اختر حساب المصروف</option>
                    <option v-for="e in expensesList" :key="e.id" :value="e.id">{{ e.name }}</option>
                  </select>
                </div>
              </div>
            </div>
            
            <div class="col-span-2 space-y-2"><label class="modal-label">البيان</label><textarea v-model="editForm.description" class="w-full rounded-2xl border-slate-200 p-4 text-sm font-bold bg-slate-50 outline-none" rows="2"></textarea></div>
            
            <div class="flex justify-end gap-4 pt-4 border-t">
              <button type="button" @click="closeEditModal" class="px-8 py-3 rounded-2xl border-2 border-slate-100 font-black text-slate-400 text-xs">إلغاء</button>
              <button type="submit" :disabled="editLoading" class="px-12 py-3 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl active:scale-95 transition-all flex items-center gap-3">
                <BaseSpinner v-if="editLoading" :size="16" color="#fff" />
                <span>حفظ التعديلات</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- Delete Confirmation Modal (Modern & Clean) -->
    <transition name="modal">
      <div v-if="showDeleteModal" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-8 text-center">
            <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6 shadow-sm border border-rose-100">
              <i class="fas fa-trash-alt text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-900 leading-none">تأكيد حذف السند</h3>
            <p class="text-slate-400 text-xs mt-3 font-bold leading-relaxed">أنت على وشك حذف مستند مالي نهائياً من سجلات النظام. هل ترغب في المتابعة؟</p>
            
            <div v-if="deleteError" class="mt-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-black text-center">
              <i class="fas fa-exclamation-triangle ml-2"></i>{{ deleteError }}
            </div>
            
            <div class="mt-8 p-4 bg-slate-50 rounded-2xl border border-slate-100 text-right space-y-2 font-bold text-xs">
              <div class="flex justify-between"><span>الحساب المالي:</span><span class="text-slate-800">{{ deleteTarget?.account_name }}</span></div>
              <div class="flex justify-between"><span>المبلغ المحذوف:</span><span class="text-rose-600">{{ deleteTarget?.amount }} {{ currencyCode }}</span></div>
              <div class="flex justify-between"><span>تاريخ السند:</span><span class="text-slate-800">{{ deleteTarget?.date }}</span></div>
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-4">
             <button @click="closeDeleteModal" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 text-sm hover:bg-slate-50 transition-all">تراجع</button>
             <button @click="submitDeleteVoucher" :disabled="deleteLoading" class="flex-1 py-4 rounded-2xl bg-rose-600 text-white font-black text-sm shadow-xl shadow-rose-100 hover:bg-rose-700 active:scale-95 transition-all flex items-center justify-center gap-3">
                <BaseSpinner v-if="deleteLoading" :size="16" color="#fff" />
                <span>حذف السند</span>
             </button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import getLocalDateISO from '@/utils/date';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useSessionStore } from '@/stores/session/sessionStore';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useCostCenterStore } from '@/stores/costCenter';
import { useProductStore } from '@/stores/product/productStore';
import { useBranchStore } from '@/stores/branch';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { usePurchaseStore } from '@/stores/purchase/purchaseStore';
import { useSalesStore } from '@/stores/sales/salesStore';
import { useAccountStore } from '@/stores/account/accountStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useVoucherStore } from '@/stores/voucher/voucherStore';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useToast } from '@/composables/useToast';
import { useDateValidation } from '@/composables/useDateValidation';
import { useTableFilters } from '@/composables/useTableFilters';
import { useSearchDropdown } from '@/composables/useSearchDropdown';

const vouchers = ref([]);
const branches = computed(() => branchStore.branches);
const isLoading = ref(false);
const filterType = ref('all');

// ✨ NEW: Initialize Composables
const {
  dateFrom,
  dateTo,
  perPage: pageSize,
  page: currentPage,
  selectedBranch,
  branchId,
  hasActiveFilters,
  dateFromRef,
  dateToRef,
  handleBranchChange,
  nextPage,
  previousPage,
  goToPage,
  loadFromLocalStorage: loadFiltersFromLocalStorage,
  saveToLocalStorage: saveFiltersToLocalStorage,
} = useTableFilters('cash_vouchers_filters', {
  initialPageSize: 20,
  onFilterChange: () => fetchVouchers(),
});

const {
  search,
  searchInputRef,
  showSearchDropdown,
  searchResults,
  isLoadingSearch,
  searchDropdownPosition,
  handleSearchBlur,
  selectSearchResult,
} = useSearchDropdown({
  onSearch: (query) => performSearch(query),
  onSelectResult: (result) => handleSelectSearchResult(result),
  onClear: () => { currentPage.value = 1; fetchVouchers(); },
});

// Pagination - derived from API
const totalCount = ref(0);
const totalPages = computed(() => Math.ceil(totalCount.value / pageSize.value));

// Modal state for Add/Edit
const addFormDateRef = ref(null);
const editFormDateRef = ref(null);

const showAddModal = ref(false);
const addForm = ref({ type: 'receipt', date: '', amount: '', account_id: '', payment_method_id: '', customer_id: null, supplier_id: null, expense_account_id: null, payment_to_type: 'supplier', description: '', cost_center_id: null, branch_id: null, purchase_id: null, sale_id: null });
const addLoading = ref(false);
const addError = ref('');

// Edit form refs (moved before watch to avoid "Cannot access editForm before initialization")
const showEditModal = ref(false);
const editForm = ref({});
const editLoading = ref(false);
const editError = ref('');

const customersList = ref([]);
const supplierStore = useSupplierStore();
const accountStore = useAccountStore();
const suppliersList = computed(() => supplierStore.suppliers);
const purchaseStore = usePurchaseStore();
const salesStore = useSalesStore();
const customerSales = ref([]);
const customerSalesLoading = ref(false);
const customerPendingSales = computed(() =>
  customerSales.value.filter(s => {
    // Show invoices that have remaining balance (not fully paid)
    const remaining = s.remaining_balance ?? (s.net_total_amount - (s.actual_paid_amount || s.paid_amount || 0));
    const status = s.dynamic_status || s.status;
    // Exclude: paid, settled, closed_by_return, returned, settled_by_return
    // (settled and settled_by_return mean it's fully paid, not cash-due)
    return remaining > 0 && !['paid', 'settled', 'closed_by_return', 'returned', 'settled_by_return'].includes(status);
  })
);

const loadCustomerSales = async (customerId) => {
  customerSales.value = [];
  if (!customerId) return;
  customerSalesLoading.value = true;
  try {
    const res = await salesStore.fetchSalesList({ customerId, perPage: 100, force: true });
    customerSales.value = res?.data?.items || [];
    
    // 🎯 الحل الهجين الذكي
    const pending = customerPendingSales.value;
    
    if (pending.length === 1) {
      // حالة واضحة: فاتورة واحدة فقط → ربط تلقائي
      addForm.value.sale_id = pending[0].id;
      showToast(`تم ربط الفاتورة "${pending[0].invoice_number}" تلقائياً`, 'info');
    } else if (pending.length > 1) {
      // حالة التباس: عدة فواتير → اعرض تنبيه
      showToast(`يوجد ${pending.length} فاتورة مستحقة - اختر الفاتورة المراد ربطها`, 'warning');
    }
    // إذا pending.length === 0 → سند عام (بدون ربط) - لا تنبيه
  } catch { customerSales.value = []; }
  finally { customerSalesLoading.value = false; }
};

watch(
  [() => addForm.value.customer_id, () => addForm.value.type],
  ([custId, type]) => {
    if (custId && type === 'receipt') {
      loadCustomerSales(custId); // watch يستدعي loadCustomerSales مرة واحدة
    } else {
      customerSales.value = [];
      addForm.value.sale_id = null; // reset invoice when customer changes
    }
  }
);

// Smart hybrid linking for edit form
const editFormCustomerSalesLoading = ref(false);
const editFormCustomerSales = ref([]);
const editFormCustomerPendingSales = computed(() =>
  editFormCustomerSales.value.filter(s => {
    const remaining = s.remaining_balance ?? (s.net_total_amount - (s.actual_paid_amount || s.paid_amount || 0));
    const status = s.dynamic_status || s.status;
    // Exclude fully settled invoices (paid or settled)
    return remaining > 0 && !['paid', 'settled', 'closed_by_return', 'returned', 'settled_by_return'].includes(status);
  })
);

const loadEditFormCustomerSales = async (customerId) => {
  editFormCustomerSales.value = [];
  if (!customerId) return;
  editFormCustomerSalesLoading.value = true;
  try {
    const res = await salesStore.fetchSalesList({ customerId, perPage: 100, force: true });
    editFormCustomerSales.value = res?.data?.items || [];
    
    // 🎯 نفس الحل الهجين الذكي للتعديل
    const pending = editFormCustomerPendingSales.value;
    
    if (pending.length === 1 && !editForm.value.sale_id) {
      editForm.value.sale_id = pending[0].id;
      showToast(`تم ربط الفاتورة "${pending[0].invoice_number}" تلقائياً`, 'info');
    } else if (pending.length > 1 && !editForm.value.sale_id) {
      showToast(`يوجد ${pending.length} فاتورة مستحقة - اختر الفاتورة المراد ربطها`, 'warning');
    }
  } catch { editFormCustomerSales.value = []; }
  finally { editFormCustomerSalesLoading.value = false; }
};

watch(
  [() => editForm.value?.customer_id, () => editForm.value?.type],
  ([custId, type]) => {
    if (custId && type === 'receipt') loadEditFormCustomerSales(custId);
    else editFormCustomerSales.value = [];
  }
);
const supplierPurchases = ref([]);
const supplierPurchasesLoading = ref(false);
const supplierPendingPurchases = computed(() =>
  supplierPurchases.value.filter(p => {
    // Show purchases that have remaining balance (not fully paid)
    const remaining = p.remaining_balance ?? (p.total_amount - (p.actual_paid_amount || p.paid_amount || 0));
    const status = p.dynamic_status || p.status;
    // Exclude fully settled invoices
    return remaining > 0 && !['paid', 'settled', 'closed_by_return', 'returned', 'settled_by_return'].includes(status);
  })
);

const loadSupplierPurchases = async (supplierId) => {
  supplierPurchases.value = [];
  if (!supplierId) return;
  supplierPurchasesLoading.value = true;
  try {
    const res = await purchaseStore.fetchPurchasesList({ supplierId, perPage: 100, force: true });
    supplierPurchases.value = res?.data?.items || [];
  } catch { supplierPurchases.value = []; }
  finally { supplierPurchasesLoading.value = false; }
};

watch(
  [() => addForm.value.supplier_id, () => addForm.value.payment_to_type],
  ([suppId, payToType]) => {
    addForm.value.purchase_id = null;
    if (suppId && payToType === 'supplier') loadSupplierPurchases(suppId);
    else supplierPurchases.value = [];
  }
);

// Auto-update account when payment method changes in edit form
watch(
  () => editForm.value?.payment_method_id,
  (pmId) => {
    if (!pmId || !editForm.value) return;
    const pm = validPaymentMethods.value.find(p => p.id === pmId);
    if (pm?.account_id) {
      editForm.value.account_id = pm.account_id;
    }
  }
);
const expensesList = ref([]);
const allAccounts = computed(() => accountStore.allAccounts);
const cashBankAccounts = ref([]);
const paymentMethods = ref([]);
// 🎯 Computed: Filter payment methods - only those with account_id (valid payment methods)
const validPaymentMethods = computed(() => 
  paymentMethods.value.filter(pm => pm.account_id && pm.account_id > 0)
);
const listsLoading = ref(false);
const listsError = ref('');

const authStore = useAuthStore();
const productStore = useProductStore();
const costCenterStore = useCostCenterStore();
const branchStore = useBranchStore();
const customerStore = useCustomerStore();
const paymentStore = usePaymentStore();
const voucherStore = useVoucherStore();
const { isExempt, ensureLoaded: ensureExemptLoaded } = useSessionExemption();
const { formatCurrencyLocale, fetchSettings, currencySymbol, currencyCode } = useCompanyCurrency();
const { showToast } = useToast();
const { validateDateRange } = useDateValidation();
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);

const getDeviceIdentity = () => {
  let id = localStorage.getItem('pos_device_id');
  if (!id) { 
    id = 'dev-' + Math.random().toString(36).slice(2, 8) + '-' + Date.now().toString().slice(-6); 
    try { localStorage.setItem('pos_device_id', id); } catch {} 
  }
  
  let name = localStorage.getItem('pos_device_name');
  if (!name) {
    name = navigator.platform || navigator.userAgent || 'POS Device';
    try { localStorage.setItem('pos_device_name', name); } catch {} 
  }
  
  return { device_id: id, device_name: name.trim() };
};

// Auto-link account from payment method
const updateAccountFromPaymentMethod = () => {
  if (!addForm.value.payment_method_id) {
    addForm.value.account_id = '';
    return;
  }
  
  // ابحث في طرق الدفع الصحيحة (التي لها account_id)
  const pm = validPaymentMethods.value.find(p => p.id === addForm.value.payment_method_id);
  if (pm?.account_id) {
    addForm.value.account_id = pm.account_id;
  } else {
    addForm.value.account_id = '';
  }
};

// Get account name for display
const getAccountName = (accountId) => {
  if (!accountId) return '';
  const account = allAccounts.value.find(a => a.id === accountId);
  return account?.name || '(حساب غير موجود)';
};

const fetchLists = async () => {
  listsLoading.value = true;
  listsError.value = '';
  try {
    const [branchRes, customerRes, paymentRes, supplierRes, accountRes] = await Promise.all([
      branchStore.fetchBranches(),
      customerStore.fetchCustomers(),
      paymentStore.fetchPaymentMethods(),
      supplierStore.fetchSuppliers(),
      accountStore.fetchGroupedAccounts()
    ]);
    
    // Handle responses if needed
    if (customerRes.status !== 'success') {
      console.warn('Failed to fetch customers:', customerRes.message);
    }
    customersList.value = customerStore.customers;
    paymentMethods.value = paymentStore.paymentMethods;
    cashBankAccounts.value = allAccounts.value.filter(a => a.code && a.code.startsWith('10'));
    expensesList.value = allAccounts.value.filter(a => a.code && a.code.startsWith('51'));
  } catch { listsError.value = 'خطأ في القوائم'; } finally { listsLoading.value = false; }
};

const onAddVoucher = async () => {
  await ensureExemptLoaded(); await fetchLists();
  
  // Use branch_id from store; cost_center_id will be derived by backend
  const branchId = branchStore.selectedBranchId || authStore?.user?.branch_id || (branches.value.length > 0 ? branches.value[0]?.id : '');
  
  addForm.value = { 
    type: 'receipt', 
    date: getLocalDateISO(), 
    amount: '', 
    account_id: '', 
    payment_method_id: '', 
    branch_id: branchId,
    customer_id: null, 
    supplier_id: null, 
    expense_account_id: null, 
    payment_to_type: 'supplier', 
    description: '',
    purchase_id: null,
    sale_id: null
  };
  supplierPurchases.value = [];
  customerSales.value = [];
  addError.value = ''; showAddModal.value = true;
};
const closeAddModal = () => showAddModal.value = false;

const submitAddVoucher = async () => {
  addLoading.value = true; addError.value = '';
  try {
    await ensureExemptLoaded();
    if (!isExempt.value && !addForm.value.branch_id) { addError.value = 'يجب اختيار المخزن.'; addLoading.value = false; return; }
    
    // 🔴 التحقق من الطرف المقابل
    if (addForm.value.type === 'receipt' && !addForm.value.customer_id) {
      addError.value = 'يجب اختيار العميل لسند القبض.'; 
      addLoading.value = false; 
      return; 
    }
    
    if (addForm.value.type === 'payment' && addForm.value.payment_to_type === 'supplier' && !addForm.value.supplier_id) {
      addError.value = 'يجب اختيار المورد لسند الصرف.'; 
      addLoading.value = false; 
      return; 
    }
    
    if (addForm.value.type === 'payment' && addForm.value.payment_to_type === 'expense' && !addForm.value.expense_account_id) {
      addError.value = 'يجب اختيار حساب المصروف لسند الصرف.'; 
      addLoading.value = false; 
      return; 
    }

    if (!addForm.value.payment_method_id) {
      addError.value = 'يجب تحديد طريقة الدفع.';
      addLoading.value = false;
      return;
    }
    
    if (!addForm.value.account_id) {
      addError.value = 'طريقة الدفع المختارة ليس لها حساب مرتبط. يرجى اختيار طريقة دفع أخرى.';
      addLoading.value = false;
      return;
    }
    
    const isCashPayment = (pmId) => {
      if (!pmId) return true;
      const pm = validPaymentMethods.value.find(p => String(p.id) === String(pmId));
      if (!pm) return false;
      if (pm.is_cash === true) return true;
      if (pm.kind && String(pm.kind).toLowerCase() === 'cash') return true;
      if (pm.type && String(pm.type).toLowerCase() === 'cash') return true;
      if (pm.code && String(pm.code).toLowerCase() === 'cash') return true;
      if (pm.name && /cash|نقد/i.test(String(pm.name))) return true;
      return false;
    };

    if (!isExempt.value && Number(addForm.value.amount) > 0 && isCashPayment(addForm.value.payment_method_id)) {
      const sessionStore = useSessionStore();
      const { device_id } = getDeviceIdentity();
      const result = await sessionStore.getCurrentSession(addForm.value.branch_id || undefined, undefined, device_id);
      if (!result.data?.id) { addError.value = 'لا توجد جلسة كاشير مفتوحة.'; addLoading.value = false; return; }
    }

    // 🔴 Payload آمن ومحدد بدلاً من spread
    const payload = {
      type: addForm.value.type,
      date: addForm.value.date,
      amount: addForm.value.amount,
      currency: currencyCode.value,
      account_id: addForm.value.account_id,
      payment_method_id: addForm.value.payment_method_id || null,
      branch_id: addForm.value.branch_id,
      description: addForm.value.description,
      ...(addForm.value.type === 'receipt' && {
        customer_id: addForm.value.customer_id,
        ...(addForm.value.sale_id ? { sale_id: addForm.value.sale_id } : {})
      }),
      ...(addForm.value.type === 'payment' && {
        payment_to_type: addForm.value.payment_to_type,
        ...(addForm.value.payment_to_type === 'supplier' && {
          supplier_id: addForm.value.supplier_id,
          ...(addForm.value.purchase_id ? { purchase_id: addForm.value.purchase_id } : {})
        }),
        ...(addForm.value.payment_to_type === 'expense' && { expense_account_id: addForm.value.expense_account_id })
      })
    };

    // Admin-only cost center override with fallback derivation
    if (authStore.isAdmin) {
      // Use admin-selected cost center if available
      if (costCenterStore.selectedCostCenterId) {
        payload.cost_center_id = costCenterStore.selectedCostCenterId;
      }
      // Fallback: derive from selected branch (safety net)
      else if (branchStore.selectedBranch?.cost_center_id) {
        payload.cost_center_id = branchStore.selectedBranch.cost_center_id;
      }
    }

    await voucherStore.createVoucher(payload);
    window.dispatchEvent(new CustomEvent('pos:voucher-recorded', { 
      detail: { 
        voucher_type: addForm.value.type, 
        amount: Number(addForm.value.amount),
        payment_method_id: addForm.value.payment_method_id
      } 
    }));
    closeAddModal(); 
    await fetchVouchers();
    // ✅ تحديث cache المنتجات في POS إذا كان السند يؤثر على المخزون
    productStore.invalidateCache();
  } catch (e) { addError.value = e?.response?.data?.message || 'خطأ في الإضافة'; } finally { addLoading.value = false; }
};

const fetchVouchers = async () => {
  // Validate date range if both dates are provided
  if (dateFrom.value && dateTo.value) {
    if (!validateDateRange(dateFrom.value, dateTo.value)) {
      // Error message is shown automatically by useDateValidation
      isLoading.value = false;
      return;
    }
  }

  isLoading.value = true;
  try {
    await ensureExemptLoaded();

    const response = await voucherStore.fetchVouchersList({ 
      branchId: branchId.value || undefined,
      type: filterType.value !== 'all' ? filterType.value : undefined,
      dateFrom: dateFrom.value || undefined,
      dateTo: dateTo.value || undefined,
      search: search.value || undefined,
      page: currentPage.value,
      perPage: pageSize.value,
      force: true
    });
    
    // Handle response structure (could be array or object with items)
    if (Array.isArray(response)) {
      vouchers.value = response;
      totalCount.value = response.length;
    } else if (response?.data?.items) {
      vouchers.value = response.data.items;
      totalCount.value = response.data.total || 0;
    } else if (response?.items) {
      vouchers.value = response.items;
      totalCount.value = response.total || 0;
    } else {
      vouchers.value = response || [];
      totalCount.value = vouchers.value.length;
    }
  } catch (err) {
    console.error('[CashVouchers] fetch error:', err);
    showToast(err?.message || 'فشل تحميل السندات', 'error');
    vouchers.value = [];
    totalCount.value = 0;
  } finally { isLoading.value = false; }
};

// ✅ NEW: Search with Dropdown Results
const performSearch = async (query) => {
  if (!query.trim()) {
    searchResults.value = [];
    return;
  }
  
  isLoadingSearch.value = true;
  try {
    const response = await voucherStore.fetchVouchersList({
      search: query.trim(),
      type: filterType.value !== 'all' ? filterType.value : undefined,
      branchId: branchId.value || undefined,
      perPage: 50 // Get more results for dropdown
    });
    
    if (Array.isArray(response)) {
      searchResults.value = response;
    } else if (response?.data?.items) {
      searchResults.value = response.data.items;
    } else if (response?.items) {
      searchResults.value = response.items;
    } else {
      searchResults.value = response || [];
    }
  } catch (error) {
    console.error('Search error:', error);
    searchResults.value = [];
  } finally {
    isLoadingSearch.value = false;
  }
};

// ✅ NEW: Select Search Result
const handleSelectSearchResult = (result) => {
  vouchers.value = [result];
  totalCount.value = 1;
};

const openEditModal = async (v) => {
  await ensureExemptLoaded(); await fetchLists();
  
  // إضافة حقول احتياطية للسندات القديمة
  let editData = { ...v };
  
  // إضافة payment_method_id احتياطياً
  if (!('payment_method_id' in editData)) {
    editData.payment_method_id = '';
  }
  
  // إضافة branch_id احتياطياً
  if (!editData.branch_id) {
    const userBranchId = authStore?.user?.branch_id || '';
    if (userBranchId && branches.value.length > 0) {
      const validBranch = branches.value.find(b => String(b.id) === String(userBranchId));
      editData.branch_id = validBranch ? userBranchId : branches.value[0]?.id || '';
    }
  }
  
  // منطق payment_to_type الصحيح
  editData.payment_to_type = v.supplier_id ? 'supplier' : (v.expense_account_id ? 'expense' : 'supplier');
  
  editForm.value = editData;
  
  // Auto-link account from payment method if not set
  if (editForm.value.payment_method_id && !editForm.value.account_id) {
    const pm = validPaymentMethods.value.find(p => p.id === editForm.value.payment_method_id);
    if (pm?.account_id) {
      editForm.value.account_id = pm.account_id;
    }
  }
  
  // تحميل الفواتير إذا كان هناك عميل (للتعديل)
  if (editForm.value.customer_id && editForm.value.type === 'receipt') {
    await loadEditFormCustomerSales(editForm.value.customer_id);
  }
  
  showEditModal.value = true;
};
const closeEditModal = () => showEditModal.value = false;

const submitEditVoucher = async () => {
  editLoading.value = true; editError.value = '';
  try {
    await ensureExemptLoaded();
    
    // 🔴 فحص المخزن
    if (!isExempt.value && !editForm.value.branch_id) { 
      editError.value = 'يجب اختيار المخزن.'; 
      editLoading.value = false; 
      return; 
    }
    
    // 🔴 فحص جلسة الكاشير للمدفوعات النقدية
    const isCashPayment = (pmId) => {
      if (!pmId) return true;
      const pm = validPaymentMethods.value.find(p => String(p.id) === String(pmId));
      if (!pm) return false;
      if (pm.is_cash === true) return true;
      if (pm.kind && String(pm.kind).toLowerCase() === 'cash') return true;
      if (pm.type && String(pm.type).toLowerCase() === 'cash') return true;
      if (pm.code && String(pm.code).toLowerCase() === 'cash') return true;
      if (pm.name && /cash|نقد/i.test(String(pm.name))) return true;
      return false;
    };

    if (!isExempt.value && Number(editForm.value.amount) > 0 && isCashPayment(editForm.value.payment_method_id)) {
      const sessionStore = useSessionStore();
      const { device_id } = getDeviceIdentity();
      const result = await sessionStore.getCurrentSession(editForm.value.branch_id || undefined, undefined, device_id);
      if (!result.data?.id) { 
        editError.value = 'لا توجد جلسة كاشير مفتوحة.'; 
        editLoading.value = false; 
        return; 
      }
    }

    // Validate account_id is linked
    if (editForm.value.payment_method_id && !editForm.value.account_id) {
      editError.value = 'طريقة الدفع المختارة ليس لها حساب مرتبط. يرجى اختيار طريقة دفع أخرى.';
      editLoading.value = false;
      return;
    }

    // 🔴 Payload مخصص للتعديل مع تنظيف الحقول
    const payload = {
      type: editForm.value.type,
      date: editForm.value.date,
      amount: editForm.value.amount,
      currency: currencyCode.value,
      account_id: editForm.value.account_id,
      payment_method_id: editForm.value.payment_method_id,
      branch_id: editForm.value.branch_id,
      description: editForm.value.description,
      // تنظيف الحقول حسب النوع
      ...(editForm.value.type === 'receipt' && { customer_id: editForm.value.customer_id }),
      ...(editForm.value.type === 'payment' && {
        payment_to_type: editForm.value.payment_to_type,
        ...(editForm.value.payment_to_type === 'supplier' && { 
          supplier_id: editForm.value.supplier_id,
          expense_account_id: null // تنظيف expense عند اختيار supplier
        }),
        ...(editForm.value.payment_to_type === 'expense' && { 
          expense_account_id: editForm.value.expense_account_id,
          supplier_id: null // تنظيف supplier عند اختيار expense
        })
      })
    };

    // Admin-only cost center override with fallback derivation
    if (authStore.isAdmin) {
      // Use admin-selected cost center if available
      if (costCenterStore.selectedCostCenterId) {
        payload.cost_center_id = costCenterStore.selectedCostCenterId;
      }
      // Fallback: derive from selected branch (safety net)
      else if (branchStore.selectedBranch?.cost_center_id) {
        payload.cost_center_id = branchStore.selectedBranch.cost_center_id;
      }
    }

    await voucherStore.updateVoucher(editForm.value.id, payload);
    window.dispatchEvent(new Event('pos:session-refresh-request'));
    closeEditModal(); 
    await fetchVouchers();
    // ✅ تحديث cache المنتجات في POS
    productStore.invalidateCache();
  } catch (e) { editError.value = e?.response?.data?.message || 'خطأ في التعديل'; } finally { editLoading.value = false; }
};

const onEditVoucher = (v) => openEditModal(v);

const showDeleteModal = ref(false);
const deleteTarget = ref(null);
const deleteLoading = ref(false);
const deleteError = ref('');

const onDeleteVoucher = (v) => { 
  deleteTarget.value = v; 
  deleteError.value = ''; 
  showDeleteModal.value = true; 
};
const closeDeleteModal = () => showDeleteModal.value = false;
const submitDeleteVoucher = async () => {
  if (!deleteTarget.value?.id) return;
  deleteLoading.value = true;
  try {
    await voucherStore.deleteVoucher(deleteTarget.value.id);
    window.dispatchEvent(new Event('pos:session-refresh-request'));
    closeDeleteModal(); 
    await fetchVouchers();
    // ✅ تحديث cache المنتجات في POS
    productStore.invalidateCache();
  } catch (e) { deleteError.value = e?.response?.data?.message || 'حدث خطأ أثناء حذف السند'; } finally { deleteLoading.value = false; }
};

// ✅ NEW: Watch filterType (not in useTableFilters, so needs manual watch)
watch(filterType, () => {
  currentPage.value = 1;
  fetchVouchers();
});

onMounted(async () => {
  await branchStore.initialize?.();
  await ensureExemptLoaded();
  loadFiltersFromLocalStorage(); // Load filters from localStorage
  
  // Mandatory branch enforcement for non-exempt users
  if (!isExempt.value && authStore.user?.branch_id) {
    branchStore.setSelectedBranch(authStore.user.branch_id);
  }
  
  await Promise.all([fetchSettings(), fetchVouchers()]);
});
</script>

<style scoped>



/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Components */
.form-input-modern, .form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm inline-block; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

/* Modal Styling */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>