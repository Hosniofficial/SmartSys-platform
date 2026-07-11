<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-rose-600 rounded-2xl flex items-center justify-center shadow-xl shadow-rose-100 text-white shrink-0">
          <i class="fas fa-undo-alt text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة المرتجعات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تسجيل وإدارة عمليات إرجاع المبيعات والمشتريات وتحديث الأرصدة</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="showCreateForm = !showCreateForm" :class="[showCreateForm ? 'bg-slate-800' : 'bg-rose-600 shadow-rose-100']" class="h-11 px-8 rounded-xl text-white text-xs font-black shadow-lg transition-all active:scale-95 flex items-center gap-2">
          <i :class="showCreateForm ? 'fas fa-times' : 'fas fa-plus-circle'"></i>
          {{ showCreateForm ? 'إغلاق النموذج' : 'تسجيل مرتجع جديد' }}
        </button>
      </div>
    </div>

    <!-- Main Navigation & Type Selector -->
    <div class="flex items-center gap-2 p-1.5 bg-white rounded-2xl border border-slate-100 shadow-sm w-fit mx-auto mb-10 sticky top-4 z-30 backdrop-blur-md bg-white/90">
      <button v-for="type in returnTypes" :key="type.id" @click="activeType = type.id" 
              :class="[activeType === type.id ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-500 hover:bg-slate-50']"
              class="tab-pill">
        <i :class="[type.icon, 'text-[10px]']"></i>
        <span>{{ type.name }}</span>
      </button>
    </div>

    <!-- KPI Summary Overview -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-list-ol"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي العمليات</p>
            <p class="kpi-value text-slate-800">{{ stats.count }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي القيمة</p>
            <p class="kpi-value text-rose-600">{{ formatPrice(stats.total) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div>
            <p class="kpi-label">نشاط اليوم (عدد)</p>
            <p class="kpi-value text-amber-600">{{ stats.today }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-chart-line"></i>
          </div>
          <div>
            <p class="kpi-label">قيمة مرتجع اليوم</p>
            <p class="kpi-value text-emerald-600">{{ formatPrice(stats.todayTotal) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Create Form Section (Collapsible) -->
    <transition name="slide-fade">
      <div v-if="showCreateForm" class="bg-white rounded-[2.5rem] shadow-sm border-2 border-rose-100 p-8 mb-10 relative z-0">
        <div class="absolute top-0 right-0 w-32 h-32 bg-rose-50 rounded-full translate-x-16 -translate-y-16 pointer-events-none"></div>
        <div class="relative z-20">
          <div class="flex items-center gap-3 mb-8">
            <h3 class="text-xl font-black text-slate-800">تسجيل مستند مرتجع جديد</h3>
            <span class="px-3 py-1 bg-rose-50 text-rose-600 text-[10px] font-black uppercase rounded-lg border border-rose-100">النوع: {{ activeType === 'sales' ? 'مبيعات' : 'مشتريات' }}</span>
          </div>
          <ReturnForm :type="activeType" @returnSuccess="handleReturnSuccess" />
        </div>
      </div>
    </transition>

    <!-- Main Content Area: History & Filters -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-visible">
      
      <!-- Filters Toolbar -->
      <div class="p-8 border-b border-slate-50 space-y-6 relative">
        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
          <div class="flex-grow group relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
            <div class="relative">
              <input 
                ref="searchInputRef"
                v-model="search" 
                type="text" 
                class="form-input-modern pr-11 w-full" 
                placeholder="ابحث بـ: رقم المرتجع أو الفاتورة..."
                @focus="showSearchDropdown = true"
                @blur="handleSearchBlur"
              />
              <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-rose-500 transition-colors text-xs"></i>
              
              <!-- Search Results Dropdown -->
              <Teleport to="body">
                <transition name="dropdown">
                  <div 
                    v-if="showSearchDropdown && search.length > 0" 
                    ref="searchDropdownRef"
                    class="fixed bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-80 overflow-y-auto border-t-0 rounded-t-none pointer-events-auto"
                    :style="[searchDropdownPosition, { zIndex: 99999 }]"
                  >
                  <!-- Loading State -->
                  <div v-if="isLoadingSearch" class="px-4 py-8 text-center">
                    <BaseSpinner class="w-5 h-5 text-blue-500 mx-auto" />
                    <p class="text-xs text-slate-400 mt-2">جاري البحث...</p>
                  </div>
                  
                  <!-- No Results -->
                  <div v-else-if="searchResults.length === 0" class="px-4 py-6 text-center">
                    <i class="fas fa-search text-2xl text-slate-200 mb-2 block"></i>
                    <p class="text-xs text-slate-400">لا توجد نتائج للبحث</p>
                  </div>
                  
                  <!-- Search Results List -->
                  <template v-else>
                    <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between sticky top-0">
                      <span class="text-[10px] font-black text-slate-400 uppercase">نتائج البحث</span>
                      <span class="text-[10px] font-black text-slate-500 bg-white px-2 py-1 rounded-lg">{{ searchResults.length }}</span>
                    </div>
                    <button 
                      v-for="result in searchResults.slice(0, 20)" 
                      :key="result.id"
                      @click="selectSearchResult(result)"
                      class="w-full px-4 py-3 text-right hover:bg-blue-50 transition-all border-b border-slate-50 last:border-b-0 flex items-center gap-3 group"
                    >
                      <div class="flex-grow">
                        <div class="text-sm font-black text-slate-800">{{ result.return_number || 'RET-' + result.id }}</div>
                        <div class="text-[10px] text-slate-400 mt-1">
                          <span>{{ formatDate(result.return_date || result.created_at) }}</span>
                          <span class="mx-2">•</span>
                          <span class="text-rose-600 font-bold">{{ formatPrice(result.grand_total || result.total_amount || 0) }}</span>
                        </div>
                      </div>
                      <i class="fas fa-arrow-left text-slate-300 group-hover:text-blue-600 transition-colors"></i>
                    </button>
                    <div v-if="searchResults.length > 20" class="px-4 py-3 text-center bg-slate-50 border-t border-slate-100">
                      <p class="text-[10px] text-slate-400">و {{ searchResults.length - 20 }} نتيجة أخرى...</p>
                    </div>
                  </template>
                  </div>
                </transition>
              </Teleport>
            </div>
          </div>

          <!-- Return Type Filter -->
          <div class="lg:w-56">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">نوع المرتجع</label>
            <select
              v-model="activeType"
              class="form-select-modern font-black"
            >
              <option value="sales">مرتجعات مبيعات</option>
              <option value="purchases">مرتجعات مشتريات</option>
            </select>
          </div>

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

          <div class="lg:w-40">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">النتائج / صفحة</label>
            <select v-model.number="pageSize" class="form-select-modern font-black">
              <option :value="10">10 سجلات</option>
              <option :value="20">20 سجل</option>
              <option :value="50">50 سجل</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-slate-50">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-400 uppercase">إجمالي النتائج المصفاة:</span>
            <span class="text-xs font-black text-slate-800 bg-slate-100 px-3 py-1 rounded-lg">{{ totalCount }}</span>
          </div>
          <button @click="loadReturns" class="text-[10px] font-black text-blue-600 hover:underline uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-sync-alt"></i> تحديث السجلات
          </button>
        </div>
      </div>

      <!-- Returns Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">رقم المرتجع</th>
              <th class="px-4 py-5">الفاتورة المرتبطة</th>
              <th class="px-4 py-5">تاريخ العملية</th>
              <th class="px-4 py-5">قيمة المرتجع</th>
              <th class="px-4 py-5 text-center">حالة المستند</th>
              <th class="px-8 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!returns.length" class="text-center py-20">
              <td colspan="6" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-box-open text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لم يتم العثور على مرتجعات</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="r in returns" :key="r.id" class="hover:bg-rose-50/20 transition-all group font-bold">
              <td class="px-8 py-4 font-black text-slate-800 font-mono tracking-wider">
                {{ r.return_number || 'RET-' + r.id.toString().padStart(4, '0') }}
              </td>
              <td class="px-4 py-4 text-xs font-bold text-slate-400 font-mono">
                <template v-if="r.invoice_number">{{ r.invoice_number }}</template>
                <template v-else>{{ r.return_type === 'sale' ? 'S-' : 'P-' }}{{ r.id.toString().padStart(6, '0') }}</template>
              </td>
              <td class="px-4 py-4 text-xs text-slate-500 tracking-tighter">{{ formatDate(r.return_date || r.created_at) }}</td>
              <td class="px-4 py-4">
                <span class="font-black text-rose-600 text-base leading-none">{{ formatPrice(r.grand_total || r.total_amount || 0) }}</span>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="status-badge bg-emerald-100 text-emerald-700">مكتمل ومرحّل</span>
              </td>
              <td class="px-8 py-4 text-center">
                <button @click="viewReturnDetails(r)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95 mx-auto" title="عرض التفاصيل">
                  <i class="fas fa-eye text-xs"></i>
                </button>
              </td>
            </tr>
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

    <!-- Details Modal -->
    <transition name="modal">
      <div v-if="showDetailsModal && selectedReturn" class="modal-overlay">
        <div class="modal-content-modern max-w-4xl animate-modalIn border border-white">
          <!-- Loading Overlay -->
          <div v-if="isLoadingDetails" class="absolute inset-0 bg-white/70 rounded-[3rem] flex items-center justify-center z-10">
            <BaseSpinner class="w-8 h-8 text-blue-500" />
          </div>

          <!-- Modal Header -->
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-rose-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-rose-100">
                <i class="fas fa-file-invoice text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">تفاصيل المرتجع {{ selectedReturn.return_number || 'RET-' + selectedReturn.id }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">{{ formatDateTime(selectedReturn.created_at) }}</p>
              </div>
            </div>
            <button @click="showDetailsModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-8 overflow-y-auto custom-scroll max-h-[75vh] space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Return Meta Info -->
              <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-100 space-y-4 font-bold">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">بيانات العملية</h4>
                <div class="flex justify-between text-xs"><span>رقم الفاتورة الأصلية:</span><span class="text-slate-900 font-mono">{{ selectedReturn.invoice_number || 'غير محدد' }}</span></div>
                <div class="flex justify-between text-xs"><span>تاريخ المرتجع:</span><span class="text-slate-900">{{ formatDate(selectedReturn.return_date || selectedReturn.created_at) }}</span></div>
                <div class="flex justify-between text-xs"><span>نوع العملية:</span><span class="text-blue-600">{{ selectedReturn.return_type === 'sale' ? 'مرتجع مبيعات' : 'مرتجع مشتريات' }}</span></div>
                <div class="flex justify-between text-xs"><span>طريقة الدفع:</span><span class="text-slate-900">{{ selectedReturn.is_cash ? 'نقدي' : 'آجل (ذمم)' }}</span></div>
                <div v-if="selectedReturn.party_name" class="flex justify-between text-xs">
                  <span>{{ selectedReturn.return_type === 'sale' ? 'العميل:' : 'المورد:' }}</span>
                  <span class="text-slate-900">{{ selectedReturn.party_name }}</span>
                </div>
              </div>
              
              <!-- Financial Summary -->
              <div class="bg-slate-900 p-8 rounded-[2.5rem] text-white shadow-2xl relative overflow-hidden flex flex-col justify-center">
                 <div class="absolute top-0 left-0 w-24 h-24 bg-white/5 rounded-full -translate-x-12 -translate-y-12"></div>
                 <p class="text-[9px] font-black text-rose-400 uppercase tracking-[0.2em] mb-3">قيمة المرتجع النهائية</p>
                 <p class="text-3xl font-black text-white leading-none tracking-tighter">{{ formatPrice(selectedReturn.grand_total || selectedReturn.total_amount || 0) }}</p>
                 <div class="mt-6 grid grid-cols-2 gap-4 text-[10px] font-bold text-white/40">
                   <div>ضريبة: {{ formatPrice(selectedReturn.tax_amount || 0) }}</div>
                   <div>خصم: {{ formatPrice(selectedReturn.discount_amount || 0) }}</div>
                   <div>مدفوع: {{ formatPrice(selectedReturn.paid_amount || 0) }}</div>
                 </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="space-y-4">
              <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2">
                <i class="fas fa-box-open text-blue-500"></i> تفاصيل الأصناف المرتجعة
              </h4>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/80 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-100">
                      <th class="px-6 py-4">اسم المنتج</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-4 py-4 text-center">سعر الوحدة</th>
                      <th class="px-6 py-4 text-left">الإجمالي</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-if="!selectedReturn.items || selectedReturn.items.length === 0">
                      <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-black text-xs">لا توجد أصناف مسجلة</td>
                    </tr>
                    <tr v-else v-for="(item, index) in selectedReturn.items" :key="index" class="hover:bg-slate-50/50 transition-all">
                      <td class="px-6 py-4">
                        <div class="text-slate-800">{{ item.product_name || 'منتج غير معرف' }}</div>
                        <div v-if="item.product_code" class="text-[9px] text-slate-400 mt-1 font-mono uppercase">{{ item.product_code }}</div>
                      </td>
                      <td class="px-4 py-4 text-center">
                        <span class="bg-slate-100 px-2 py-0.5 rounded-lg text-slate-700">{{ item.quantity }}</span>
                      </td>
                      <td class="px-4 py-4 text-center text-slate-500">{{ formatPrice(item.unit_price || 0) }}</td>
                      <td class="px-6 py-4 text-left font-black text-slate-900">{{ formatPrice((item.quantity || 0) * (item.unit_price || 0)) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="selectedReturn.notes" class="bg-amber-50 border border-amber-100 p-6 rounded-[1.5rem] flex gap-4">
              <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-amber-500 shadow-sm shrink-0"><i class="fas fa-sticky-note"></i></div>
              <div class="flex-grow">
                <h5 class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">سبب المرتجع / ملاحظات</h5>
                <p class="text-xs font-bold text-amber-900 leading-relaxed italic">{{ selectedReturn.notes }}</p>
              </div>
            </div>

            <!-- Creation Details -->
            <div class="flex flex-col md:flex-row items-center justify-between p-6 bg-slate-50 rounded-2xl border border-slate-100 gap-4">
              <div class="flex items-center gap-4">
                 <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-slate-400 shadow-sm"><i class="fas fa-user-edit text-xs"></i></div>
                 <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">بواسطة الموظف</p>
                    <p class="text-xs font-black text-slate-700 leading-none mt-1">{{ selectedReturn.created_by_name || 'النظام' }}</p>
                 </div>
              </div>
              <div class="text-[10px] font-black text-slate-300 uppercase tracking-tighter">
                آخر تحديث: {{ formatDateTime(selectedReturn.updated_at) }}
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3">
            <button @click="showDetailsModal = false" class="px-8 py-3 rounded-xl text-xs font-black text-slate-500 hover:bg-white transition-all">إغلاق</button>
            <button @click="printReturnDetails" class="px-10 py-3 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all flex items-center gap-2 active:scale-95">
              <i class="fas fa-print"></i> طباعة المستند
            </button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ReturnForm from '@/components/ReturnForm.vue';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useSupplierStore } from '@/stores/supplier/supplierStore';
import { useReturnStore } from '@/stores/return/returnStore';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { printDocument } from '@/utils/PrintService';
import { buildReturnHtml } from '@/utils/printTemplates';
import { useTableFilters } from '@/composables/useTableFilters';
import { useSearchDropdown } from '@/composables/useSearchDropdown';

// --- Services & Router ---
const router = useRouter();
const route = useRoute();
const { showLoader, hideLoader } = useLoader();
const { showToast } = useToast();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const customerStore = useCustomerStore();
const supplierStore = useSupplierStore();
const returnStore = useReturnStore();
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();

// Branches computed from store
const branches = computed(() => branchStore.branches);

// ✨ NEW: Initialize Composables
const {
  dateFrom,
  dateTo,
  perPage: pageSize,
  page: currentPage,
  selectedBranch,
  branchId,
  isExempt: exemptFromBranch,
  dateFromRef,
  dateToRef,
  handleBranchChange,
  nextPage,
  previousPage,
  goToPage,
  loadFromLocalStorage: loadFiltersFromLocalStorage,
  saveToLocalStorage: saveFiltersToLocalStorage,
  totalCount,
  totalPages,
} = useTableFilters('returns_mgmt_filters', {
  initialPageSize: 20,
  onFilterChange: () => loadReturns(),
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
  onSelectResult: (result) => handleSearchResultSelect(result),
  onClear: () => { currentPage.value = 1; loadReturns(); },
});

// --- Reactive State: General ---
const activeType = ref('sales');
const returns = ref([]);
const totalAmountFromAPI = ref(0);
const showCreateForm = ref(false);
const showDetailsModal = ref(false);
const selectedReturn = ref(null);
const isLoading = ref(false);
const isLoadingDetails = ref(false);

// --- Reactive State ---
const returnTypes = [
  { id: 'sales', name: 'مرتجعات المبيعات', icon: 'fas fa-shopping-cart' },
  { id: 'purchases', name: 'مرتجعات المشتريات', icon: 'fas fa-truck' }
];

// --- Computed Logic ---
const stats = computed(() => {
  const isToday = (someDate) => {
    const today = new Date();
    const d = new Date(someDate);
    return d.getDate() === today.getDate() &&
           d.getMonth() === today.getMonth() &&
           d.getFullYear() === today.getFullYear();
  };
  const todayReturns = returns.value.filter(r => isToday(r.created_at));
  const todayTotal = todayReturns.reduce((sum, r) => sum + parseFloat(r.grand_total || r.total_amount || r.total || r.amount || 0), 0);
  const totalVal = totalAmountFromAPI.value || returns.value.reduce((sum, r) => sum + parseFloat(r.grand_total || r.total_amount || r.total || r.amount || 0), 0);
  return {
    count: totalCount.value || returns.value.length,
    total: totalVal,
    today: todayReturns.length,
    todayTotal: todayTotal
  };
});

// --- Formatting Helpers ---
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
const formatDateTime = (date) => date ? new Date(date).toLocaleString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

// --- API: Load Returns List ---
const loadReturns = async () => {
  isLoading.value = true;
  showLoader();
  try {
    const response = await returnStore.fetchReturnsList({
      type: activeType.value,
      branchId: branchId.value || undefined,
      page: currentPage.value,
      perPage: pageSize.value,
      search: search.value || undefined,
      dateFrom: dateFrom.value || undefined,
      dateTo: dateTo.value || undefined
    });
    if (response?.status === 'success') {
      const resData = response.data;
      const list = Array.isArray(resData) ? resData : (resData?.items || []);
      returns.value = list;
      totalCount.value = resData?.total || list.length;
      totalAmountFromAPI.value = list.reduce((sum, r) => sum + (Number(r.total_amount) || 0), 0);
    } else {
      throw new Error(response?.message || 'Failed to load returns');
    }
  } catch (error) {
    console.error('Error loading returns:', error);
    showToast(error.message || 'فشل في تحميل المرتجعات', 'error');
    returns.value = [];
    totalCount.value = 0;
  } finally {
    isLoading.value = false;
    hideLoader();
  }
};

// --- Handle Return Success ---
const handleReturnSuccess = () => {
  showCreateForm.value = false;
  loadReturns();
  
  // ✅ تحديث cache المنتجات (المرتجعات تؤثر على المخزون)
  if (typeof productStore !== 'undefined' && productStore.invalidateCache) {
    productStore.invalidateCache();
  }

  window.dispatchEvent(new CustomEvent('pos:return-recorded', {
    detail: {
      returnId: Date.now(),
      saleId: route.query.saleId,
    }
  }));

  // Only navigate away if explicitly coming from another page
  if (!route.query.returnFrom) return;

  const destinations = {
    cashier: { name: 'SalesPoint' },
    'cashier-dashboard': { name: 'CashierDashboard' },
    'admin-sales': { name: 'SalesHistory' },
    'sale-details': { name: 'SalesHistory', query: { id: route.query.saleId } },
  };

  const dest = destinations[route.query.returnFrom] ?? { name: 'CashierDashboard' };
  router.push(dest).catch(() => {});
};

// --- Search with Dropdown Results ---
const performSearch = async (query) => {
  if (!query.trim()) {
    searchResults.value = [];
    return;
  }
  
  isLoadingSearch.value = true;
  try {
    const response = await returnStore.fetchReturnsList({
      type: activeType.value,
      branchId: branchId.value || undefined,
      search: query.trim(),
      perPage: 50 // Get more results for dropdown
    });
    if (response?.status === 'success') {
      const resData = response.data;
      searchResults.value = Array.isArray(resData) ? resData : (resData?.items || []);
    } else {
      searchResults.value = [];
    }
  } catch (error) {
    console.error('Search error:', error);
    searchResults.value = [];
  } finally {
    isLoadingSearch.value = false;
  }
};

const handleSearchResultSelect = (result) => {
  returns.value = [result]; // Show only the selected result
  totalCount.value = 1;
};

// ✅ NEW: Watchers & Lifecycle ---

watch(activeType, () => { currentPage.value = 1; search.value = ''; searchResults.value = []; loadReturns(); showCreateForm.value = false; });

onMounted(async () => {
  await Promise.all([ensureExemptionLoaded(), branchStore.initialize?.()]);
  await fetchSettings();
  loadFiltersFromLocalStorage(); // Load filters from localStorage

  if (!isExempt.value) {
    const userBranchId = authStore?.user?.branch_id;
    if (userBranchId) {
      branchStore.setSelectedBranch(userBranchId);
    }
  }

  loadReturns();
});

// --- View Return Details ---
const viewReturnDetails = async (r) => {
  selectedReturn.value = r;
  showDetailsModal.value = true;
  isLoadingDetails.value = true;
  try {
    const type = activeType.value === 'sales' ? 'sales' : 'purchase';
    const res = await returnStore.fetchReturnDetails(r.id, type);
    if (res?.status === 'success' && res.data) {
      selectedReturn.value = { ...r, ...res.data };
    }
  } catch (_) { /* keep list data */ }
  finally { isLoadingDetails.value = false; }
};

// --- Print Return Details ---
const printReturnDetails = async () => {
  if (!selectedReturn.value) return;
  await printDocument(buildReturnHtml(selectedReturn.value));
};
</script>

<style scoped>



/* KPI Cards */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Components */
.form-input-modern,
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-rose-500 focus:ring-4 focus:ring-rose-50 shadow-sm font-bold text-sm; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed; }
.tab-pill { @apply px-8 py-3 rounded-xl text-xs font-black transition-all flex items-center gap-3 active:scale-95; }

/* Modal Styles */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }

/* Dropdown Animation */
.dropdown-enter-active { animation: dropdownIn 0.2s ease-out; }
.dropdown-leave-active { animation: dropdownOut 0.15s ease-in; }
@keyframes dropdownIn { 
  from { 
    opacity: 0; 
    transform: translateY(-8px); 
  } 
  to { 
    opacity: 1; 
    transform: translateY(0); 
  } 
}
@keyframes dropdownOut { 
  from { 
    opacity: 1; 
    transform: translateY(0); 
  } 
  to { 
    opacity: 0; 
    transform: translateY(-8px); 
  } 
}
</style>