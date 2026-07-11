<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-xl shadow-indigo-100 text-white shrink-0">
          <i class="fas fa-cart-arrow-down text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة المشتريات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">إدارة فواتير التوريد، تسوية أرصدة الموردين وتحديث المخزون</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="openAddModal" class="h-11 px-8 rounded-xl bg-indigo-600 text-white text-xs font-black shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus-circle"></i> إضافة فاتورة شراء
        </button>
      </div>
    </div>

    <!-- KPI Summary Overview -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-file-invoice"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">إجمالي المشتريات</p>
            <p class="kpi-value text-slate-800">{{ stats.totalPurchases || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">إجمالي المبالغ</p>
            <p class="kpi-value text-emerald-600 text-xl">{{ formatCurrency(stats.totalAmount || 0) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
            <i class="fas fa-clock"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">الفواتير المعلقة</p>
            <p class="kpi-value text-amber-600">{{ stats.pendingInvoices || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-indigo-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="fas fa-truck"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">الموردون النشطون</p>
            <p class="kpi-value text-indigo-600">{{ stats.activeSuppliers || 0 }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
          <!-- Search by Invoice Number with Dropdown -->
          <div class="flex-grow group relative">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
            <div class="relative">
              <input 
                ref="searchInputRef"
                v-model="search" 
                type="text" 
                class="form-input-modern pr-11 w-full" 
                placeholder="ابحث بـ: رقم الفاتورة أو المورد..."
                @focus="showSearchDropdown = true"
                @blur="handleSearchBlur"
              />
              <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-600 transition-colors"></i>
              
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
                    <BaseSpinner class="w-5 h-5 text-indigo-500 mx-auto" />
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
                      class="w-full px-4 py-3 text-right hover:bg-indigo-50 transition-all border-b border-slate-50 last:border-b-0 flex items-center gap-3 group"
                    >
                      <div class="flex-grow">
                        <div class="text-sm font-black text-slate-800">{{ result.invoice_number }}</div>
                        <div class="text-[10px] text-slate-400 mt-1">
                          <span>{{ formatDate(result.purchase_date) }}</span>
                          <span class="mx-2">•</span>
                          <span class="text-indigo-600 font-bold">{{ formatCurrency(result.total_amount || 0) }}</span>
                        </div>
                      </div>
                      <i class="fas fa-arrow-left text-slate-300 group-hover:text-indigo-600 transition-colors"></i>
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

          <!-- Supplier Filter -->
          <div class="lg:w-72">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">المورّد</label>
            <select v-model="selectedSupplier" @change="() => { currentPage = 1; loadPurchases(); }" class="form-select-modern font-black text-sm">
              <option value="">كل الموردين</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <!-- Branch Filter: اختياري للمدير، ثابت للمستخدم المعيّن له فرع -->
          <div v-if="isExempt" class="lg:w-56">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع</label>
            <select v-model="selectedBranch" @change="handleBranchChange" class="form-select-modern font-black text-sm">
              <option :value="null">كل الفروع</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>
          <div v-else-if="authStore.user?.branch_id" class="flex flex-col justify-end">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">الفرع</label>
            <div class="flex items-center gap-2 px-4 h-11 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black whitespace-nowrap">
              <i class="fas fa-building text-indigo-400"></i>
              {{ branches.find(b => String(b.id) === String(authStore.user?.branch_id))?.name || ('فرع #' + authStore.user?.branch_id) }}
            </div>
          </div>

          <!-- From Date -->
          <div class="lg:w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">من تاريخ</label>
            <div class="relative">
              <input ref="dateFromRef" type="date" v-model="dateFrom" class="form-input-modern font-bold text-sm" />
              <i 
                class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                @click="showDateFromPicker()"
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
                @click="showDateToPicker()"
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
          <button @click="loadPurchases" class="text-[10px] font-black text-indigo-600 hover:underline uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-sync-alt"></i> تحديث السجلات
          </button>
        </div>
      </div>
    </div>

    <!-- Purchases Data Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      
      <!-- Loading: Skeleton Table -->
      <div v-if="isLoading" class="p-8 space-y-4">
        <div v-for="i in 6" :key="i" class="flex gap-4 items-center py-4 border-b border-slate-50">
          <BaseSkeleton type="circle" size="sm" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" />
          <BaseSkeleton type="circle" size="sm" animation="shimmer" />
        </div>
      </div>

      <!-- Main Table -->
      <template v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-right text-sm">
            <thead>
              <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-6 py-5">رقم الفاتورة</th>
                <th class="px-4 py-5">المورّد</th>
                <th class="px-4 py-5">الفرع</th>
                <th class="px-4 py-5 text-center">التاريخ</th>
                <th class="px-4 py-5">المبلغ الإجمالي</th>
                <th class="px-4 py-5 text-center">حالة السداد</th>
                <th class="px-6 py-5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="purchases.length === 0" class="text-center">
                <td colspan="7" class="py-24">
                  <div class="flex flex-col items-center opacity-20 text-slate-400">
                    <i class="fas fa-file-invoice text-6xl mb-4"></i>
                    <p class="font-black text-sm uppercase tracking-widest">لا توجد سجلات مشتريات</p>
                  </div>
                </td>
              </tr>
              <tr v-for="p in purchases" :key="p.id" class="hover:bg-indigo-50/30 transition-all group font-bold">
                <td class="px-6 py-4 font-black text-slate-800 font-mono tracking-wider">{{ p.invoice_number }}</td>
                <td class="px-4 py-4 text-slate-700">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-xs group-hover:bg-white transition-all"><i class="fas fa-truck-loading"></i></div>
                    <span class="truncate max-w-[150px]">{{ suppliers.find(s=>s.id===p.supplier_id)?.name || '-' }}</span>
                  </div>
                </td>
                <td class="px-4 py-4">
                  <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 bg-slate-50 border border-slate-100 px-2.5 py-1 rounded-lg">
                    <i class="fas fa-building text-slate-400 text-[10px]"></i>
                    {{ p.branch_name || branches.find(b => String(b.id) === String(p.branch_id))?.name || '-' }}
                  </span>
                </td>
                <td class="px-4 py-4 text-center text-xs font-bold text-slate-400 font-mono tracking-tighter">
                  {{ p.purchase_date ? new Date(p.purchase_date).toLocaleDateString('en-US') : '-' }}
                </td>
                <td class="px-4 py-4">
                  <span class="font-black text-emerald-600 text-base">{{ formatCurrency(p.total_amount) }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="['status-badge', getPurchaseStatus(p.dynamic_status || p.status).class]">
                    {{ getPurchaseStatus(p.dynamic_status || p.status).text }}
                  </span>
                </td>
                <td class="px-6 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="openEditModal(p)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95">
                      <i class="fas fa-pen text-xs"></i>
                    </button>
                    <button @click="confirmDelete(p.id)" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95">
                      <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
            عرض <span class="text-slate-800">{{ purchases.length }}</span> سجل في هذه الصفحة
          </div>
          
          <div class="flex items-center gap-1">
            <button @click="previousPage()" :disabled="currentPage<=1" class="pagination-btn">
              <i class="fas fa-angle-right"></i>
            </button>
            <div class="px-6 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm">
              صفحة {{ currentPage }} من {{ totalPages }}
            </div>
            <button @click="nextPage(totalPages)" :disabled="currentPage>=totalPages" class="pagination-btn">
              <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Purchase Form Modal (Logic Strictly Preserved) -->
    <transition name="modal">
      <div v-if="showFormModal" class="modal-overlay">
        <div class="modal-content-modern max-w-6xl animate-modalIn border border-white">
          <!-- Modal Header -->
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200 text-white">
                <i :class="[isEditMode ? 'fas fa-edit' : 'fas fa-plus-circle', 'text-xl']"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">{{ isEditMode ? 'تعديل فاتورة المشتريات' : 'تسجيل فاتورة شراء جديدة' }}</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest font-mono">{{ form.invoice_number }}</p>
              </div>
            </div>
            <button @click="showFormModal = false" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-8 overflow-y-auto custom-scroll max-h-[70vh] space-y-8" dir="rtl">
            
            <!-- Section 1: Header Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50 p-6 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden">
               <div class="absolute top-0 right-0 w-24 h-24 bg-white/40 rounded-full translate-x-8 -translate-y-8"></div>
               
               <div class="space-y-2 relative z-10">
                 <label class="modal-label">المورّد المستهدف <span class="text-rose-500">*</span></label>
                 <div class="flex gap-2">
                    <select v-model="form.supplier_id" class="form-select-modern font-black flex-grow" required>
                      <option value="">اختر المورّد</option>
                      <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button @click="showSupplierModal = true" class="w-12 h-12 bg-white border-2 border-slate-100 rounded-2xl flex items-center justify-center text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all active:scale-90 shadow-sm"><i class="fas fa-user-plus"></i></button>
                 </div>
               </div>

               <div class="space-y-2 relative z-10">
                 <label class="modal-label">الفرع المستقبل</label>
                 <select v-model="form.branch_id" class="form-select-modern font-black" required>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                 </select>
               </div>

               <div class="space-y-2 relative z-10">
                 <label class="modal-label">تاريخ الشراء</label>
                 <div class="relative">
                   <input ref="purchaseDateRef" type="date" v-model="form.purchase_date" class="form-input-modern font-black" />
                   <i 
                     class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                     @click="purchaseDateRef.showPicker()"
                   ></i>
                 </div>
               </div>
            </div>

            <!-- Section 2: Items Table -->
            <div class="space-y-4">
              <div class="flex items-center justify-between px-2">
                <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                  <i class="fas fa-list-ul text-blue-500"></i> تفاصيل أصناف الفاتورة
                </h4>
                <button @click="addItemRow" class="px-5 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase shadow-lg hover:bg-black transition-all active:scale-95 flex items-center gap-2">
                   <i class="fas fa-plus"></i> إضافة صنف جديد
                </button>
              </div>

              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-100 uppercase tracking-tighter">
                      <th class="px-6 py-4 w-1/3">المنتج</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-4 py-4 text-center">سعر الشراء</th>
                      <th class="px-4 py-4 text-center">بيانات التتبع (Batch/SN)</th>
                      <th class="px-4 py-4 text-center">الإجمالي</th>
                      <th class="px-4 py-4 text-center"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50/50 transition-all">
                      <td class="px-6 py-4">
                        <div class="flex gap-2">
                          <select v-model="item.product_id" class="form-select-modern h-10 text-[11px] font-black flex-grow" @change="handleProductChange(item, index)" required>
                            <option value="">اختر المنتج...</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.barcode }})</option>
                          </select>
                          <button @click="showProductModal = true; productRowIndex = index" class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-blue-600 border border-slate-100 hover:bg-white transition-all"><i class="fas fa-plus text-[10px]"></i></button>
                        </div>
                      </td>
                      <td class="px-4 py-4 text-center">
                        <input type="number" min="1" v-model.number="item.quantity" class="w-20 h-10 bg-white border-2 border-slate-100 rounded-xl text-center font-black text-blue-600 outline-none focus:border-blue-500 transition-all" @input="updateItemTotal(item)" />
                      </td>
                      <td class="px-4 py-4 text-center">
                        <input type="number" step="0.01" v-model.number="item.purchase_price" class="w-24 h-10 bg-white border-2 border-slate-100 rounded-xl text-center font-black text-emerald-600 outline-none focus:border-blue-500 transition-all" @input="updateItemTotal(item)" />
                      </td>
                      <td class="px-4 py-4">
                        <div class="flex flex-col gap-1.5 min-w-[120px]">
                           <input v-if="item.has_batch_number" type="text" v-model="item.batch_number" placeholder="رقم الدفعة" class="h-8 rounded-lg bg-slate-50 border border-slate-100 text-[10px] px-2 outline-none" />
                           <div v-if="item.has_expiry_date" class="relative">
                              <input 
                                :ref="el => { expiryDateRefs[index] = el }" 
                                type="date" 
                                v-model="item.expiry_date" 
                                class="h-8 rounded-lg bg-slate-50 border border-slate-100 text-[10px] px-2 pr-7 outline-none" 
                              />
                              <i 
                                class="fas fa-calendar-days absolute right-1.5 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer text-[6px]"
                                @click="expiryDateRefs[index]?.showPicker()"
                              ></i>
                            </div>
                           <input v-if="item.has_serial_number" type="text" v-model="item.serial" placeholder="السيريال" class="h-8 rounded-lg bg-slate-50 border border-slate-100 text-[10px] px-2 outline-none" />
                           <span v-if="!item.has_batch_number && !item.has_expiry_date && !item.has_serial_number" class="text-slate-300 text-[9px] uppercase italic">لا يتطلب تتبع</span>
                        </div>
                      </td>
                      <td class="px-4 py-4 text-center font-black text-slate-800 text-sm">
                        {{ formatCurrency(item.quantity * item.purchase_price) }}
                      </td>
                      <td class="px-4 py-4 text-center">
                        <button @click="removeItemRow(index)" class="text-slate-300 hover:text-rose-500 transition-colors"><i class="fas fa-times-circle text-lg"></i></button>
                      </td>
                    </tr>
                    <tr v-if="!form.items.length">
                       <td colspan="6" class="py-12 text-center text-slate-300 font-bold uppercase tracking-widest">يرجى إضافة أصناف للفاتورة للبدء</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Section 3: Summary & Payment -->
            <div class="flex flex-col lg:flex-row gap-8 items-start">
               <!-- Notes Area -->
               <div class="flex-grow w-full space-y-4">
                  <label class="modal-label uppercase tracking-widest">ملاحظات الفاتورة (اختياري)</label>
                  <textarea v-model="form.notes" rows="3" class="w-full rounded-[1.5rem] border border-slate-200 p-5 text-sm font-bold bg-slate-50 focus:bg-white transition-all outline-none" placeholder="أدخل تفاصيل إضافية للمرجعية..."></textarea>
               </div>

               <!-- Calculations Box -->
               <div class="w-full lg:w-[450px] bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl relative overflow-hidden">
                  <div class="absolute top-0 left-0 w-32 h-32 bg-white/5 rounded-full -translate-x-12 -translate-y-12"></div>
                  <h4 class="text-sm font-black text-blue-400 uppercase tracking-widest mb-6 border-b border-white/5 pb-4">الملخص المالي النهائي</h4>
                  
                  <div class="space-y-4">
                    <div class="flex justify-between items-center text-xs font-bold text-white/50 uppercase tracking-widest">
                       <span>إجمالي المنتجات:</span>
                       <span class="text-white">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs font-bold text-white/50 uppercase tracking-widest">
                       <span>ضريبة القيمة المضافة ({{ taxValue }}%):</span>
                       <span class="text-blue-400">{{ formatCurrency(taxAmount) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-4 border-t border-white/10">
                       <span class="text-sm font-black uppercase tracking-widest">المبلغ الصافي:</span>
                       <span class="text-3xl font-black tracking-tighter text-blue-400">{{ formatCurrency(grandTotal) }}</span>
                    </div>
                  </div>

                  <div class="mt-8 grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                       <label class="text-[9px] font-black text-white/40 uppercase tracking-widest">طريقة الدفع</label>
                       <select v-model="form.payment_method_id" class="w-full h-11 bg-white/10 border border-white/10 rounded-xl px-4 text-xs font-black text-white outline-none focus:border-blue-400 transition-all">
                          <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                       </select>
                    </div>
                    <div class="space-y-2">
                       <label class="text-[9px] font-black text-white/40 uppercase tracking-widest">المبلغ المدفوع</label>
                       <input type="number" step="0.01" :max="grandTotal" v-model.number="form.paid_amount" class="w-full h-11 bg-white/10 border rounded-xl px-4 text-sm font-black text-emerald-400 text-left outline-none transition-all" :class="form.paid_amount > grandTotal ? 'border-red-500 focus:border-red-400' : 'border-white/10 focus:border-emerald-400'" />
                       <p v-if="form.paid_amount > grandTotal" class="text-[9px] text-red-400 font-bold">المبلغ المدفوع يتجاوز الإجمالي</p>
                    </div>
                  </div>

                  <div v-if="remainingAmount > 0" class="mt-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl flex justify-between items-center">
                    <span class="text-[10px] font-black uppercase text-rose-400 tracking-widest">رصيد آجل مستحق (للذمم):</span>
                    <span class="text-lg font-black text-rose-400 tracking-tighter">{{ formatCurrency(remainingAmount) }}</span>
                  </div>
               </div>
            </div>
          </div>

          <!-- Modal Footer Actions -->
          <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-4 shrink-0">
            <button @click="showFormModal = false" class="px-8 py-3 rounded-xl border-2 border-slate-100 font-black text-slate-500 hover:bg-white hover:text-rose-500 transition-all text-sm">إلغاء</button>
            <button @click="savePurchase" :disabled="isSaving" class="px-12 py-3 bg-indigo-600 text-white rounded-xl font-black text-sm shadow-xl shadow-indigo-100 hover:bg-indigo-700 active:scale-95 transition-all flex items-center gap-3">
              <BaseSpinner v-if="isSaving" :size="16" color="#fff" />
              <span>{{ isSaving ? 'جاري الحفظ...' : (isEditMode ? 'تحديث الفاتورة' : 'حفظ الفاتورة الآن') }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Simple Modals for Supplier and Product Quick Add (Logic Preserved) -->
    <transition name="modal-fade">
      <div v-if="showSupplierModal" class="modal-overlay z-[110]">
         <div class="modal-content-modern max-w-md animate-modalIn">
            <div class="p-8">
              <h3 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2"><i class="fas fa-truck text-indigo-500"></i> إضافة مورد جديد</h3>
              <div class="space-y-4">
                <div class="space-y-1.5"><label class="modal-label">اسم المورد</label><input v-model="newSupplier.name" type="text" class="form-input-modern font-bold" /></div>
                <div class="space-y-1.5"><label class="modal-label">رقم الهاتف</label><input v-model="newSupplier.phone" type="text" class="form-input-modern font-mono" /></div>
                <div class="space-y-1.5"><label class="modal-label">البريد</label><input v-model="newSupplier.email" type="email" class="form-input-modern font-bold" /></div>
              </div>
              <div class="mt-8 flex gap-3">
                <button @click="showSupplierModal = false" class="flex-1 py-3 rounded-xl border-2 border-slate-50 font-black text-slate-400 text-xs">إلغاء</button>
                <button @click="saveNewSupplier" :disabled="isAddingSupplier" class="flex-[2] py-3 rounded-xl bg-slate-900 text-white font-black text-xs shadow-xl active:scale-95">حفظ المورد</button>
              </div>
            </div>
         </div>
      </div>
    </transition>

    <!-- Product Modal Simplified -->
    <transition name="modal-fade">
      <div v-if="showProductModal" class="modal-overlay z-[110]">
         <div class="modal-content-modern max-w-lg animate-modalIn">
            <div class="p-8">
              <h3 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2"><i class="fas fa-cube text-blue-500"></i> إضافة منتج سريع للمخزن</h3>
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 space-y-1.5"><label class="modal-label">اسم المنتج</label><input v-model="newProduct.name" type="text" class="form-input-modern font-bold" /></div>
                <div class="space-y-1.5"><label class="modal-label">سعر الشراء</label><input v-model.number="newProduct.purchase_price" type="number" class="form-input-modern font-black text-emerald-600" /></div>
                <div class="space-y-1.5"><label class="modal-label">سعر البيع</label><input v-model.number="newProduct.sale_price" type="number" class="form-input-modern font-black text-blue-600" /></div>
              </div>
              <div class="mt-8 flex gap-3">
                <button @click="showProductModal = false" class="flex-1 py-3 rounded-xl border-2 border-slate-50 font-black text-slate-400 text-xs">إلغاء</button>
                <button @click="saveNewProduct" :disabled="isAddingProduct" class="flex-[2] py-3 rounded-xl bg-blue-600 text-white font-black text-xs shadow-xl active:scale-95">إضافة للمخزن</button>
              </div>
            </div>
         </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue'
import { useToast } from '@/composables/useToast'
import { useCompanyCurrency } from '@/composables/useCompanyCurrency'
import { useTableFilters } from '@/composables/useTableFilters'
import { useSearchDropdown } from '@/composables/useSearchDropdown'
import BaseSpinner from '../../components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { getLocalDateISO } from '@/utils/date'
import { useSessionExemption } from '@/composables/useCashierSessionGuard'
import { useAuthStore } from '@/stores/auth'
import { useBranchStore } from '@/stores/branch'
import { useCatalogStore } from '@/stores/catalog/catalogStore'
import { useProductStore } from '@/stores/product/productStore'
import { usePaymentStore } from '@/stores/payment/paymentStore'
import { useSessionStore } from '@/stores/session/sessionStore'
import { usePurchaseStore } from '@/stores/purchase/purchaseStore'
import { useSupplierStore } from '@/stores/supplier/supplierStore'
import { useSettingsStore } from '@/stores/settings/settingsStore'
import { useBootstrapStore } from '@/stores/bootstrap'
import AlertService from '@/services/AlertService'

// --- Services & Composables ---
const { showToast } = useToast()
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency()
const authStore = useAuthStore()
const branchStore = useBranchStore()
const catalogStore = useCatalogStore()
const productStore = useProductStore()
const paymentStore = usePaymentStore()
const sessionStore = useSessionStore()
const purchaseStore = usePurchaseStore()
const supplierStore = useSupplierStore()
const settingsStore = useSettingsStore()
const bootstrapStore = useBootstrapStore()
const { isExempt, ensureLoaded: ensureExemptionLoaded } = useSessionExemption()

// ✨ Initialize unified composables
const {
  page: currentPage,
  perPage: pageSize,
  totalCount,
  dateFrom,
  dateTo,
  dateFromRef,
  dateToRef,
  selectedBranch,
  branchId,
  handleBranchChange,
  showDateFromPicker,
  showDateToPicker,
  previousPage,
  nextPage,
} = useTableFilters('purchases_filters', {
  initialPageSize: 20,
  onFilterChange: () => loadPurchases(),
})

const {
  search,
  searchResults,
  isLoadingSearch,
  showSearchDropdown,
  searchInputRef,
  searchDropdownPosition,
  handleSearchBlur,
  selectSearchResult,
} = useSearchDropdown({
  onSearch: (query) => performSearch(query),
  onSelectResult: (result) => handleSearchSelect(result),
  onClear: () => handleSearchClear(),
})

// State Refs
const purchases = ref([]); const suppliers = ref([]); const products = ref([]); const branches = computed(() => branchStore.branches);
const stats = ref({ totalPurchases: 0, totalAmount: 0, pendingInvoices: 0, activeSuppliers: 0 });
const showSupplierModal = ref(false); const isAddingSupplier = ref(false);
const newSupplier = ref({ name: '', phone: '', email: '', address: '' });
const showProductModal = ref(false); const isAddingProduct = ref(false);
const newProduct = ref({ name: '', barcode: '', category_id: '', purchase_price: 0, sale_price: 0, min_quantity: 0, description: '' });
const categories = ref([]); const productRowIndex = ref(null);
const isLoading = ref(true); const error = ref(null);

// ✂️ DELETED: Advanced Filters State (now managed by composables)
// - search (from useSearchDropdown)
// - selectedSupplier (keep as local, not in composable - specific to this page)
// - selectedBranchFilter → selectedBranch (from useTableFilters)
// - dateFrom, dateTo, dateFromRef, dateToRef (from useTableFilters)
// - pageSize, totalCount, currentPage (from useTableFilters)
// - showSearchDropdown, searchResults, isLoadingSearch, searchInputRef, searchDropdownRef, searchDropdownPositionUpdate (from useSearchDropdown)

// Keep selectedSupplier (it's specific to this page, not in composables)
const selectedSupplier = ref('');
const isSaving = ref(false); const isEditMode = ref(false);
const showFormModal = ref(false);
const form = ref({ supplier_id: '', branch_id: branchStore.selectedBranchId || '', invoice_number: '', purchase_date: getLocalDateISO(), status: 'draft', tax: 0, discount: 0, items: [], paid_amount: 0 });
const purchaseDateRef = ref(null);
const expiryDateRefs = ref([]);

// Tax Settings
const taxEnabled = ref(false); const taxRate = ref(0); const taxValue = ref(0);

// --- Logic: Calculations (Preserved) ---
const subtotal = computed(() => form.value.items.reduce((sum, it) => sum + (it.quantity * it.purchase_price), 0))
const discountAmount = computed(() => Number(form.value.discount || 0))
const baseAfterDiscount = computed(() => Math.max(0, subtotal.value - discountAmount.value))
const taxAmount = computed(() => (parseFloat(taxValue.value) || 0) / 100 * baseAfterDiscount.value)
const grandTotal = computed(() => baseAfterDiscount.value + taxAmount.value)
const remainingAmount = computed(() => Math.max(0, grandTotal.value - parseFloat(form.value.paid_amount || 0)))

// --- Logic: Filtering & Pagination (handled by composables) ---
// totalPages و totalCount يتم تحديثهما من API

// Computed: totalPages (من API, يتم تحديثه في loadPurchases)
const totalPages = computed(() => Math.ceil((totalCount.value || 1) / pageSize.value))

// --- Logic: API & Handlers (Preserved) ---
const paymentMethods = ref([]);
const cashMethodId = computed(() => paymentMethods.value.find(pm => String(pm.kind || '').toLowerCase() === 'cash')?.id || null);
const isCashById = (id) => paymentMethods.value.find(pm => Number(pm.id) === Number(id))?.kind === 'cash';

const fetchAll = async () => {
  isLoading.value = true;
  try {
    const [purResult, suppResult, prodResult] = await Promise.allSettled([
      purchaseStore.fetchPurchases(),
      supplierStore.fetchSuppliers({ force: true }),
      productStore.fetchProducts({ force: true }),
    ]);

    if (purResult.status === 'fulfilled' && purResult.value?.status === 'success') {
      purchases.value = purResult.value.data?.items || purResult.value.data || [];
    }
    if (suppResult.status === 'fulfilled' && suppResult.value?.status === 'success') {
      suppliers.value = suppResult.value.data || [];
    }
    if (prodResult.status === 'fulfilled' && prodResult.value?.status === 'success') {
      products.value = prodResult.value.data || [];
    }

    await Promise.allSettled([
      branchStore.fetchBranches(),
      paymentStore.fetchPaymentMethods(),
    ]);
    paymentMethods.value = paymentStore.paymentMethods;

    stats.value = {
      totalPurchases: purchases.value.length,
      totalAmount: purchases.value.reduce((s, p) => s + parseFloat(p.total_amount || 0), 0),
      pendingInvoices: purchases.value.filter(p => p.status === 'pending').length,
      activeSuppliers: new Set(purchases.value.map(p => p.supplier_id)).size
    };
  } catch (err) { error.value = 'خطأ في التحميل'; } finally { isLoading.value = false; }
}

const loadTaxSettings = async () => {
  try {
    const response = await settingsStore.fetchSettings();
    if (response.status === 'success') {
      const s = response.data || {};
      taxEnabled.value = s['company.tax_enabled'] === '1';
      taxRate.value = parseFloat(s['company.tax_rate']) || 0;
      taxValue.value = taxEnabled.value ? taxRate.value : 0;
    }
  } catch (err) { error.value = 'Error loading tax settings'; }
};

// نظام التحقق الكامل
const validatePurchaseForm = () => {
  if (!form.value.items.length) {
    showToast('يجب إضافة منتج واحد على الأقل', 'error');
    return false;
  }
  
  if (!form.value.supplier_id) {
    showToast('يجب اختيار المورد', 'error');
    return false;
  }
  
  // التحقق من كل صنف
  for (let i = 0; i < form.value.items.length; i++) {
    const item = form.value.items[i];
    
    if (!item.product_id) {
      showToast(`المنتج رقم ${i + 1} غير محدد`, 'error');
      return false;
    }
    
    if (!item.quantity || item.quantity <= 0) {
      showToast(`كمية المنتج رقم ${i + 1} غير صالحة`, 'error');
      return false;
    }
    
    if (!item.purchase_price || item.purchase_price <= 0) {
      showToast(`سعر المنتج رقم ${i + 1} غير صالح`, 'error');
      return false;
    }
    
    // التحقق من الحقول الإضافية إذا كانت مطلوبة
    if (item.has_batch_number && !item.batch_number) {
      showToast(`المنتج رقم ${i + 1} يتطلب رقم الدفعة`, 'error');
      return false;
    }
    
    if (item.has_expiry_date && !item.expiry_date) {
      showToast(`المنتج رقم ${i + 1} يتطلب تاريخ الصلاحية`, 'error');
      return false;
    }
    
    if (item.has_serial_number && !item.serial) {
      showToast(`المنتج رقم ${i + 1} يتطلب الرقم التسلسلي`, 'error');
      return false;
    }
  }
  
  const paidAmt = parseFloat(form.value.paid_amount || 0);
  if (paidAmt > grandTotal.value) {
    showToast(`المبلغ المدفوع (${paidAmt}) لا يمكن أن يتجاوز إجمالي الفاتورة (${grandTotal.value.toFixed(2)})`, 'error');
    return false;
  }

  const selectedMethod = paymentMethods.value.find(m => Number(m.id) === Number(form.value.payment_method_id));
  if (selectedMethod?.kind === 'credit' && paidAmt > 0) {
    showToast('طريقة الدفع الآجلة لا تقبل مبلغاً مدفوعاً — اجعل المبلغ المدفوع = 0 أو اختر طريقة دفع أخرى', 'error');
    return false;
  }

  return true;
};

const savePurchase = async () => {
  if (!validatePurchaseForm()) return;
  
  // 🔴 فحص جلسة الكاشير للمدفوعات النقدية
  const isCash = paymentMethods.value.find(m => m.id === form.value.payment_method_id)?.kind === 'cash';
  const paidAmt = parseFloat(form.value.paid_amount || 0);
  const branchId = String(form.value.branch_id);
  const getDeviceIdentity = () => {
    let id = localStorage.getItem('pos_device_id');
    if (!id) {
      id = 'dev-' + Math.random().toString(36).slice(2, 8);
      try { localStorage.setItem('pos_device_id', id); } catch {}
    }
    return { device_id: id };
  };
  
  try { await ensureExemptionLoaded(); } catch (_) {}
  
  if (!isExempt.value && isCash && paidAmt > 0) {
    try {
      const result = await sessionStore.getCurrentSession(branchId, authStore?.user?.id || null, getDeviceIdentity?.().device_id);
      if (result?.status !== 'success' || !result.data?.id) {
        showToast('لا توجد جلسة كاشير مفتوحة. لا يمكن إتمام المدفوعات النقدية بدون جلسة.', 'error');
        return;
      }
    } catch (error) {
      showToast('فشل في التحقق من جلسة الكاشير', 'error');
      return;
    }
  }
  
  const payload = {
    supplier_id: Number(form.value.supplier_id), 
    branch_id: String(form.value.branch_id),
    invoice_number: form.value.invoice_number, 
    purchase_date: form.value.purchase_date,
    tax_rate: parseFloat(taxValue.value), 
    discount_value: parseFloat(form.value.discount || 0),
    discount_type: form.value.discount_type || 'fixed',
    paid_amount: parseFloat(form.value.paid_amount || 0), 
    payment_method_id: Number(form.value.payment_method_id || cashMethodId.value),
    remaining_amount: grandTotal.value - parseFloat(form.value.paid_amount || 0),
    status: paidAmt >= grandTotal.value ? 'paid' : (paidAmt > 0 ? 'partial' : 'due'),
    notes: form.value.notes || '',
    total_amount: grandTotal.value,
    total_items: form.value.items.length,
    items: form.value.items.map(it => ({
      id: it.id || undefined,
      product_id: Number(it.product_id), 
      quantity: parseFloat(it.quantity.toFixed(2)), 
      price: it.purchase_price,
      cost: it.purchase_price,
      total: it.purchase_price * it.quantity,
      unit_id: it.unit_id || 1,
      batch_number: it.batch_number || '',
      expiry_date: it.expiry_date || '',
      serial: it.serial || '',
      warehouse_id: form.value.branch_id
    }))
  };

  isSaving.value = true;
  try {
    let response;
    if (isEditMode.value) response = await purchaseStore.updatePurchase(form.value.id, payload);
    else response = await purchaseStore.createPurchase(payload);
    
    if (response.status === 'success') {
      showToast('تم الحفظ بنجاح'); 
      showFormModal.value = false; 
      await fetchAll();
      // ✅ تحديث cache المنتجات في POS لأن المشتريات تزيد المخزون
      productStore.invalidateCacheForBranch(form.value.branch_id);
    } else {
      showToast(response.message || 'فشل الحفظ', 'error');
    }
  } catch (e) { showToast(e.response?.data?.message || 'فشل الحفظ', 'error'); } 
  finally { isSaving.value = false; }
}

const addItemRow = () => form.value.items.push({ 
  product_id: '', 
  quantity: 1, 
  purchase_price: 0.01, 
  unit_id: 1,
  batch_number: '', 
  expiry_date: '', 
  serial: ''
});
const removeItemRow = idx => form.value.items.splice(idx, 1);
const updateItemTotal = it => {
  it.quantity = Math.max(1, parseFloat(parseFloat(it.quantity || 1).toFixed(2)));
  it.purchase_price = Math.max(0, parseFloat(parseFloat(it.purchase_price || 0).toFixed(2)));
};

const openAddModal = async () => {
  isEditMode.value = false;
  let newInvoiceNumber = ''; try { const res = await purchaseStore.getNextInvoiceNumber(); if (res.status === 'success') { newInvoiceNumber = res.data?.invoice_number || ''; } } catch {}
  form.value = { supplier_id: '', branch_id: branchStore.selectedBranchId || branches.value[0]?.id || '', invoice_number: newInvoiceNumber, purchase_date: getLocalDateISO(), status: 'received', items: [], paid_amount: 0, payment_method_id: cashMethodId.value };
  await loadTaxSettings(); showFormModal.value = true;
}

const openEditModal = (p) => {
  isEditMode.value = true;
  form.value = {
    ...p,
    items: Array.isArray(p.items) ? p.items : [],
    payment_method_id: p.payment_method_id ?? null
  };
  showFormModal.value = true;
};

const handleProductChange = (item, idx) => {
  const p = products.value.find(x => x.id === item.product_id);
  if (p) { 
    item.purchase_price = p.purchase_price || 0.01; 
    item.unit_id = p.unit_id || 1; 
    item.has_batch_number = p.has_batch_number; 
    item.has_expiry_date = p.has_expiry_date; 
    item.has_serial_number = p.has_serial_number;
    
    item.batch_number = '';
    item.expiry_date = '';
    item.serial = '';
  }
}

const confirmDelete = (id) => AlertService.confirm('حذف الفاتورة؟') && deletePurchase(id);
const deletePurchase = async (id) => { 
  try { 
    const response = await purchaseStore.deletePurchase(id);
    if (response.status === 'success') {
      showToast('تم الحذف'); 
      await fetchAll();
      // ✅ تحديث cache المنتجات في POS
      productStore.invalidateCache();
    } else {
      showToast(response.message || 'Failed to delete', 'error');
    }
  } catch { 
    showToast('خطأ في الحذف', 'error'); 
  } 
};

const saveNewSupplier = async () => {
  if (!newSupplier.value.name.trim()) {
    showToast('ادخل اسم المورّد', 'error'); return;
  }
  isAddingSupplier.value = true;
  try {
    const response = await supplierStore.createSupplier(newSupplier.value);
    if (response.status === 'success') {
      const data = response.data;
      suppliers.value.push(data);
      form.value.supplier_id = data.id;
      showSupplierModal.value = false;
      newSupplier.value = { name: '', phone: '', email: '', address: '' };
      showToast('تمت إضافة المورد بنجاح', 'success');
    } else {
      showToast(response.message || 'حدث خطأ أثناء إضافة المورد', 'error');
    }
  } catch (error) {
    showToast('حدث خطأ أثناء إضافة المورد', 'error');
  } finally {
    isAddingSupplier.value = false;
  }
};
const saveNewProduct = async () => {
  if (!newProduct.value.name.trim()) {
    showToast('ادخل اسم المنتج', 'error'); return;
  }
  isAddingProduct.value = true;
  try {
    const response = await productStore.createProduct(newProduct.value);
    if (response.status === 'success') {
      const data = response.data;
      if (!products.value.find(p => p.id === data.id)) {
        products.value.push(data);
      }
      if (productRowIndex.value !== null) {
        form.value.items[productRowIndex.value].product_id = data.id;
        form.value.items[productRowIndex.value].purchase_price = data.purchase_price || 0.01;
      }
      showProductModal.value = false;
      newProduct.value = { name: '', barcode: '', category_id: '', purchase_price: 0, sale_price: 0, min_quantity: 0, description: '' };
      productRowIndex.value = null;
      showToast('تمت إضافة المنتج بنجاح', 'success');
    } else {
      showToast(response.message || 'حدث خطأ أثناء إضافة المنتج', 'error');
    }
  } catch (error) {
    showToast('حدث خطأ أثناء إضافة المنتج', 'error');
  } finally {
    isAddingProduct.value = false;
  }
};

// Helpers
const getPurchaseStatus = (s) => {
  const statusMap = {
    draft: { text: 'مسودة', class: 'bg-slate-100 text-slate-700' },
    paid: { text: 'مدفوعة', class: 'bg-emerald-100 text-emerald-700' },
    settled: { text: 'مسددة', class: 'bg-emerald-100 text-emerald-700' },
    partial: { text: 'جزئي', class: 'bg-amber-100 text-amber-700' }, 
    due: { text: 'مستحق', class: 'bg-rose-100 text-rose-700' }
  };
  return statusMap[s] || { text: s, class: 'bg-slate-100' };
};

const formatCurrency = (v) => formatCurrencyLocale(v, 2);
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';

// --- API: Load Purchases List with Filters ---
const loadPurchases = async () => {
  isLoading.value = true;
  try {
    // تحميل البيانات الساكنة في المرة الأولى باستخدام Bootstrap API
    if (suppliers.value.length === 0 || products.value.length === 0) {
      try {
        // استخدام Bootstrap API لتحميل البيانات المطلوبة دفعة واحدة
        const data = await bootstrapStore.fetchManagementData('purchase');
        
        if (data.branches) branchStore.branches = data.branches;
        if (data.paymentMethods) paymentMethods.value = data.paymentMethods;
        if (data.suppliers) suppliers.value = data.suppliers;
        
        // تحميل المنتجات بشكل منفصل لأنها ليست في Bootstrap API
        if (products.value.length === 0) {
          await productStore.fetchProducts({ force: true }).then(res => {
            if (res?.status === 'success') products.value = res.data || [];
          });
        }
        
        console.log('[PurchaseManagement] Bootstrap data loaded successfully');
      } catch (bootstrapError) {
        console.warn('[PurchaseManagement] Bootstrap API failed, using fallback', bootstrapError);
        
        // Fallback: تحميل البيانات بشكل منفصل
        await Promise.allSettled([
          supplierStore.fetchSuppliers({ force: true }).then(res => {
            if (res?.status === 'success') suppliers.value = res.data || [];
          }),
          productStore.fetchProducts({ force: true }).then(res => {
            if (res?.status === 'success') products.value = res.data || [];
          }),
          branchStore.fetchBranches().then(res => {
            if (res?.status === 'success') branchStore.branches = res.data || [];
          }),
          paymentStore.fetchPaymentMethods().then(res => {
            if (res?.status === 'success') paymentMethods.value = res.data || [];
          })
        ]);
      }
    }

    // ✨ استخدم branchId من الـ composable (يُحسب بناءً على isExempt)
    const response = await purchaseStore.fetchPurchases({
      branchId: branchId.value || undefined,
      page: currentPage.value,
      perPage: pageSize.value,
      search: search.value || undefined,
      supplier_id: selectedSupplier.value || undefined,
      dateFrom: dateFrom.value || undefined,
      dateTo: dateTo.value || undefined
    });
    
    if (response?.status === 'success') {
      const resData = response.data;
      const list = Array.isArray(resData) ? resData : (resData?.items || []);
      purchases.value = list;
      totalCount.value = resData?.total || list.length;
      
      // تحديث الإحصائيات
      stats.value = {
        totalPurchases: totalCount.value,
        totalAmount: purchases.value.reduce((s, p) => s + parseFloat(p.total_amount || 0), 0),
        pendingInvoices: purchases.value.filter(p => p.status === 'pending').length,
        activeSuppliers: new Set(purchases.value.map(p => p.supplier_id)).size
      };
    } else {
      throw new Error(response?.message || 'Failed to load purchases');
    }
  } catch (error) {
    console.error('Error loading purchases:', error);
    showToast(error.message || 'فشل في تحميل المشتريات', 'error');
    purchases.value = [];
    totalCount.value = 0;
  } finally {
    isLoading.value = false;
  }
};

// --- Search with Dropdown Results ---
const performSearch = async (query) => {
  if (!query.trim()) {
    searchResults.value = [];
    return;
  }
  
  isLoadingSearch.value = true;
  try {
    // ✨ استخدم branchId من الـ composable
    const response = await purchaseStore.fetchPurchases({
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

// ✨ Handler for search result selection
const handleSearchSelect = (result) => {
  search.value = result.invoice_number || 'INV-' + result.id;
  showSearchDropdown.value = false;
  purchases.value = [result]; // Show only the selected result
  totalCount.value = 1;
};

/**
 * ✨ FIX #5: Handle search clearing
 * عندما يمسح المستخدم مربع البحث: أعد تحميل الجدول بالكامل
 */
const handleSearchClear = () => {
  search.value = '';
  searchResults.value = [];
  currentPage.value = 1;
  loadPurchases();
};

// ✨ Watch for supplier filter changes (page-specific, not in composable)
/**
 * ✨ FIX #6: Reset currentPage when supplier changes
 * في القديم كان بيعمل reset بس دلوقتي مش، وده بيسبب pagination bug
 */
watch([selectedSupplier], () => { 
  currentPage.value = 1;
  loadPurchases(); 
});

// Note: dateFrom, dateTo, pageSize changes are watched by useTableFilters composable
// which automatically calls onFilterChange → loadPurchases
watch(() => form.value.payment_method_id, (newId) => {
  const m = paymentMethods.value.find(pm => Number(pm.id) === Number(newId));
  if (m?.kind === 'credit') form.value.paid_amount = 0;
});

const fetchCategories = async () => {
  try {
    await catalogStore.fetchCategories(branchStore.selectedBranchId);
    categories.value = catalogStore.getCategoriesForBranch(branchStore.selectedBranchId);
  } catch (error) {
    console.error('Failed to fetch categories:', error);
  }
};

onMounted(async () => {
  await branchStore.initialize();
  try { await ensureExemptionLoaded(); } catch (_) {}

  // ✅ استعادة الفلاتر المحفوظة من localStorage (dateFrom/dateTo/perPage/page)
  // يجب قبل loadPurchases() حتى تُطبَّق الفلاتر من أول load
  try {
    const stored = localStorage.getItem('purchases_filters');
    if (stored) {
      const data = JSON.parse(stored);
      if (data.dateFrom) dateFrom.value = data.dateFrom;
      if (data.dateTo)   dateTo.value   = data.dateTo;
      if (data.perPage)  pageSize.value = data.perPage;
      // نبدأ دائمًا من صفحة 1 عند الـ refresh (تجنبًا لـ stale page)
    }
  } catch (_) {}
  
  await Promise.all([
    categories.value.length ? Promise.resolve() : fetchCategories(),
    loadTaxSettings(),
  ]);
  
  // تحميل المشتريات باستخدام API مع الفلاتر
  await loadPurchases();
  
  // ✨ Scroll and resize listeners are now managed by useSearchDropdown composable
  // No need to add them here - they're already set up in the composable's onMounted
});

// ✨ Cleanup is now managed by useSearchDropdown composable
// onBeforeUnmount hook removal - composable handles it
</script>

<style scoped>

/* KPI Cards */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Components */
.form-input-modern, .form-select-modern { @apply w-full h-12 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 shadow-sm font-bold text-sm; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

/* Modal & Layout */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>