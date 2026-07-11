<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-shield-alt text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة طلبات الضمان</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع خدمات ما بعد البيع، معالجة المطالبات، والدعم الفني</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="showCreate = true" class="h-11 px-8 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus-circle"></i> إنشاء طلب جديد
        </button>
      </div>
    </div>

    <!-- Status Navigation Filter -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-2 mb-8 flex flex-wrap items-center gap-1.5 w-fit">
      <button
        v-for="t in statusTabs"
        :key="t.value"
        @click="statusFilter = t.value"
        :class="[statusFilter === t.value ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50']"
        class="px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Search & Advanced Filters -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 flex flex-col lg:flex-row lg:items-end gap-6">
      <div class="flex-grow group">
        <label class="filter-label">بحث سريع</label>
        <div class="relative">
          <input v-model="search" type="text" class="form-input-modern" placeholder="ابحث بـ: رقم الطلب أو العميل أو الرقم التسلسلي..." />
          <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <div class="lg:w-48">
        <label class="filter-label">أولوية الطلب</label>
        <select v-model="priorityFilter" class="form-select-modern font-bold">
          <option v-for="p in priorityOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>
      </div>

      <div class="lg:w-44">
        <label class="filter-label">من تاريخ</label>
        <div class="relative">
          <input ref="dateFromRef" type="date" v-model="dateFrom" class="form-input-modern font-bold text-xs" />
          <i 
            class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
            @click="dateFromRef.showPicker()"
          ></i>
        </div>
      </div>

      <div class="lg:w-44">
        <label class="filter-label">إلى تاريخ</label>
        <div class="relative">
          <input ref="dateToRef" type="date" v-model="dateTo" class="form-input-modern font-bold text-xs" />
          <i 
            class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
            @click="dateToRef.showPicker()"
          ></i>
        </div>
      </div>

      <div class="lg:w-32">
        <label class="filter-label">لكل صفحة</label>
        <select v-model.number="perPage" class="form-select-modern font-bold text-xs">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
      </div>

      <button @click="fetchList" class="w-11 h-11 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-slate-100 hover:text-slate-600 transition-all active:scale-90 border border-slate-100" title="تحديث">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>

    <!-- Data List Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <!-- Error State -->
      <div v-if="error && !isLoading" class="p-20 text-center">
        <div class="w-16 h-16 bg-rose-50 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-exclamation-triangle text-2xl"></i>
        </div>
        <p class="font-black text-sm text-slate-600">{{ error }}</p>
        <button @click="fetchList" class="mt-4 px-6 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-black">إعادة المحاولة</button>
      </div>

      <template v-else>
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5 w-20"># رقم</th>
              <th class="px-4 py-5">العميل</th>
              <th class="px-4 py-5">الرقم التسلسلي</th>
              <th class="px-4 py-5 text-center">الأولوية</th>
              <th class="px-4 py-5 text-center">الحالة الحالية</th>
              <th class="px-8 py-5 text-center">إجراء</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <template v-else>
              <tr v-if="filteredItems.length === 0" class="text-center">
                <td colspan="6" class="py-24">
                  <div class="flex flex-col items-center opacity-20 text-slate-400">
                    <i class="fas fa-shield-alt text-6xl mb-4"></i>
                    <p class="font-black text-sm uppercase tracking-widest">لا توجد طلبات ضمان مسجلة</p>
                    <button class="mt-4 opacity-100 text-blue-500 text-[10px] font-black uppercase" @click="showCreate = true">+ إنشاء طلب جديد</button>
                  </div>
                </td>
              </tr>
              <tr v-for="r in pageItems" :key="r.id" class="hover:bg-blue-50/30 transition-all group">
                <td class="px-6 py-4 font-black text-slate-800 font-mono">#{{ r.id }}</td>
                <td class="px-4 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center text-[10px] group-hover:bg-white transition-all">
                      <i class="fas fa-user"></i>
                    </div>
                    <span class="truncate max-w-[150px] leading-none">{{ r.customer_name || r.customer_id }}</span>
                  </div>
                </td>
                <td class="px-4 py-4 text-xs font-bold text-slate-400 font-mono uppercase tracking-tighter">{{ r.product_serial || '—' }}</td>
                <td class="px-4 py-4 text-center">
                  <span :class="['status-badge', priorityClass(r.priority)]">{{ priorityLabel(r.priority) }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="['status-badge', statusClass(r.status)]">{{ statusLabel(r.status) }}</span>
                </td>
                <td class="px-8 py-4 text-center">
                  <button @click="openDetails(r.id)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="عرض التفاصيل">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                </td>
              </tr>
            </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
            عرض <span class="text-slate-800">{{ pageItems.length }}</span> من إجمالي <span class="text-slate-800">{{ filteredItems.length }}</span> طلب
          </div>
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="pagination-btn">
              <i class="fas fa-angle-right"></i>
            </button>
            <div class="px-4 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm mx-2">
              {{ currentPage }} / {{ totalPages }}
            </div>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="pagination-btn">
              <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Create Warranty Modal -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition name="modal">
      <div v-if="showCreate" class="modal-overlay">
        <div class="modal-content-modern max-w-4xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
                <i class="fas fa-plus-circle text-xl"></i>
              </div>
              <h3 class="text-xl font-black text-slate-800 leading-none uppercase tracking-tight">إنشاء طلب ضمان جديد</h3>
            </div>
            <button @click="showCreate = false; resetForm()" class="text-slate-300 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <div class="p-8 space-y-8 overflow-y-auto custom-scroll max-h-[75vh]" dir="rtl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Customer Search Section -->
              <div class="space-y-6">
                <div class="space-y-2 relative">
                  <label class="modal-label">العميل<span class="text-rose-500">*</span></label>
                  <div class="relative group">
                    <input
                      v-model="customerQuery"
                      type="text"
                      class="form-input-modern font-bold h-12"
                      placeholder="ابحث بـ: اسم العميل أو رقم الهاتف..."
                      @input="debouncedCustomerSearch(customerQuery)"
                      @focus="showCustomerDropdown = true; debouncedCustomerSearch(customerQuery)"
                      @blur="scheduleHideCustomerDropdown"
                    />
                    <i class="fas fa-user-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                    <input type="hidden" :value="form.customer_id" />

                    <div v-if="showCustomerDropdown" class="dropdown-list custom-scroll">
                      <div v-if="customerSearchResults.length === 0" class="px-6 py-3 text-[11px] text-slate-400 font-black">اكتب حرفين على الأقل للبحث...</div>
                      <li v-for="c in customerSearchResults" :key="c.id" @mousedown.prevent="selectCustomer(c)" class="dropdown-item">
                        <span class="font-black text-slate-800">{{ c.name }}</span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ c.phone || c.email || c.id }}</span>
                      </li>
                    </div>
                    <!-- BUG 1 FIX: removed duplicate error message that was outside this div -->
                    <p v-if="errors.customer_id" class="text-[10px] text-rose-500 font-black px-1 mt-1">{{ errors.customer_id }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="modal-label">الرقم التسلسلي</label>
                    <input v-model="form.product_serial" type="text" class="form-input-modern font-mono font-black" placeholder="رقم التسلسلي..." />
                  </div>
                  <div class="space-y-2">
                    <label class="modal-label">تاريخ الشراء</label>
                    <div class="relative">
                      <input ref="purchaseDateRef" v-model="form.purchase_date" type="date" class="form-input-modern font-bold" />
                      <i 
                        class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer"
                        @click="purchaseDateRef.showPicker()"
                      ></i>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Issue Details Section -->
              <div class="space-y-6">
                <div class="space-y-2">
                  <label class="modal-label">أولوية الطلب <span class="text-rose-500">*</span></label>
                  <select v-model="form.priority" class="form-select-modern font-black text-sm">
                    <option value="low">منخفضة</option>
                    <option value="medium">متوسطة</option>
                    <option value="high">مرتفعة</option>
                    <option value="urgent">عاجل جداً</option>
                  </select>
                  <p v-if="errors.priority" class="text-[10px] text-rose-500 font-black px-1">{{ errors.priority }}</p>
                </div>
                <div class="space-y-2">
                  <label class="modal-label">رقم الفاتورة المرتبطة</label>
                  <input v-model="form.invoice_id" type="text" class="form-input-modern font-mono font-black" placeholder="رقم الفاتورة..." />
                </div>
              </div>
            </div>

            <!-- Issue Description -->
            <div class="space-y-2">
              <label class="modal-label">وصف المشكلة الفنية <span class="text-rose-500">*</span></label>
              <textarea v-model="form.issue_description" rows="3" class="w-full rounded-[1.5rem] border border-slate-200 p-5 text-sm font-bold bg-slate-50 focus:bg-white transition-all outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50" placeholder="صف العطل أو العيب الفني بالتفصيل..."></textarea>
              <p v-if="errors.issue_description" class="text-[10px] text-rose-500 font-black px-1">{{ errors.issue_description }}</p>
            </div>

            <!-- Items Dynamic Table -->
            <div class="space-y-4 pt-4">
              <div class="flex items-center justify-between px-2">
                <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                  <i class="fas fa-boxes-stacked text-blue-500"></i> العناصر المشمولة بالضمان
                </h4>
                <button type="button" @click="addItem" class="px-5 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase hover:bg-black transition-all active:scale-95 flex items-center gap-2">
                  <i class="fas fa-plus"></i> إضافة عنصر
                </button>
              </div>

              <div class="space-y-4">
                <div v-for="(it, idx) in form.items" :key="idx" class="p-6 bg-slate-50 border border-slate-100 rounded-[2rem] relative group/item hover:bg-white hover:border-blue-100 transition-all">
                  <button v-if="form.items.length > 1" @click="removeItem(idx)" class="absolute -left-2 -top-2 w-8 h-8 bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg active:scale-90 transition-all">
                    <i class="fas fa-times text-[10px]"></i>
                  </button>
                  <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <!-- Product search -->
                    <div class="md:col-span-6 relative group/prod">
                      <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">بحث المنتج</label>
                      <input
                        v-model="it._productQuery"
                        type="text"
                        class="form-input-modern h-11 text-xs font-black"
                        placeholder="ابحث بـ: اسم المنتج أو الباركود..."
                        @input="debouncedProductSearch(idx, it._productQuery)"
                        @focus="it._showProductDropdown = true; debouncedProductSearch(idx, it._productQuery)"
                        @blur="scheduleHideProductDropdown(it)"
                      />
                      <i class="fas fa-search absolute right-3.5 top-[38px] text-slate-300 group-focus-within/prod:text-blue-500 text-[10px]"></i>
                      <div v-if="it._showProductDropdown" class="dropdown-list">
                        <div v-if="(it._productResults || []).length === 0" class="px-6 py-3 text-[11px] text-slate-400 font-black">اكتب حرفين على الأقل للبحث...</div>
                        <li v-for="p in it._productResults" :key="p.id" @mousedown.prevent="selectProduct(idx, p)" class="dropdown-item">
                          <span class="font-black text-slate-800">{{ p.name }}</span>
                          <span class="text-[10px] text-slate-400 font-mono">{{ p.barcode || p.product_code || p.id }}</span>
                        </li>
                      </div>
                      <p v-if="itemErrors[idx]?.product_id" class="text-[10px] text-rose-500 font-black mt-1 px-1">{{ itemErrors[idx].product_id }}</p>
                    </div>

                    <!-- Quantity -->
                    <div class="md:col-span-2">
                      <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block text-center">الكمية</label>
                      <input v-model.number="it.quantity" type="number" class="form-input-modern h-11 text-center font-black" min="1" @change="validateItem(idx)" />
                      <p v-if="itemErrors[idx]?.quantity" class="text-[10px] text-rose-500 font-black mt-1 px-1">{{ itemErrors[idx].quantity }}</p>
                    </div>

                    <!-- Issue notes -->
                    <div class="md:col-span-4">
                      <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">ملاحظات إضافية للصنف</label>
                      <input v-model="it.issue_notes" type="text" class="form-input-modern h-11 text-xs font-bold" placeholder="حالة القطعة..." />
                    </div>
                  </div>
                </div>
              </div>
              <p v-if="errors.items" class="text-[10px] text-rose-500 font-black px-1">{{ errors.items }}</p>
            </div>
          </div>

          <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
            <button @click="showCreate = false; resetForm()" class="px-8 py-3 rounded-xl border-2 border-slate-100 font-black text-slate-400 hover:bg-white transition-all text-xs uppercase tracking-widest">إلغاء</button>
            <button @click="createWarranty" :disabled="creating || !isFormValid" class="px-12 py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3 disabled:opacity-50">
              <BaseSpinner v-if="creating" :size="16" color="#fff" />
              <span>{{ creating ? 'جاري الإنشاء...' : 'تسجيل طلب الضمان' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Details Modal -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition name="modal">
      <div v-if="showDetails && details" class="modal-overlay">
        <div class="modal-content-modern max-w-5xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50 shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center text-white">
                <i class="fas fa-file-contract text-xl"></i>
              </div>
              <h3 class="text-xl font-black text-slate-800 leading-none">ملف طلب الضمان #{{ details.id }}</h3>
            </div>
            <button @click="closeDetails" class="text-slate-300 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-2xl"></i>
            </button>
          </div>

          <!-- Details loading state -->
          <div v-if="detailsLoading" class="p-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-for="i in 4" :key="i" class="p-5 rounded-[1.5rem] bg-slate-50 border border-slate-100 space-y-3">
                <BaseSkeleton type="text" size="xs" width="4rem" animation="shimmer" />
                <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
              </div>
            </div>
            <div class="p-6 rounded-[1.5rem] bg-slate-50 border border-slate-100 space-y-4">
              <BaseSkeleton type="text" size="sm" width="12rem" animation="shimmer" />
              <BaseSkeleton type="paragraph" size="sm" animation="shimmer" />
            </div>
          </div>

          <div v-else class="p-8 overflow-y-auto custom-scroll max-h-[80vh] space-y-10">
            <!-- Header Grid Info -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-5 rounded-[1.5rem] bg-slate-50 border border-slate-100 font-bold">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">العميل</p>
                <p class="text-xs text-slate-800 leading-none truncate">{{ customerName || details.customer_id }}</p>
              </div>
              <div class="p-5 rounded-[1.5rem] bg-slate-50 border border-slate-100 font-bold">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الأولوية</p>
                <span :class="['status-badge mt-1', priorityClass(details.priority)]">{{ priorityLabel(details.priority) }}</span>
              </div>
              <div class="p-5 rounded-[1.5rem] bg-slate-50 border border-slate-100 font-bold">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الحالة</p>
                <span :class="['status-badge mt-1', statusClass(details.status)]">{{ statusLabel(details.status) }}</span>
              </div>
              <div class="p-5 rounded-[1.5rem] bg-slate-900 border border-slate-800 text-white font-black">
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">الرقم التسلسلي</p>
                <p class="text-xs font-mono uppercase tracking-widest">{{ details.product_serial || '—' }}</p>
              </div>
            </div>

            <!-- Issue Description -->
            <div class="bg-blue-50/50 p-8 rounded-[2rem] border border-blue-100 relative overflow-hidden">
              <div class="absolute top-0 left-0 w-24 h-24 bg-blue-100 rounded-full -translate-x-12 -translate-y-12"></div>
              <h4 class="text-xs font-black text-blue-600 uppercase tracking-widest mb-4 flex items-center gap-2 relative z-10">
                <i class="fas fa-circle-exclamation"></i> وصف المشكلة المسجلة
              </h4>
              <p class="text-sm font-bold text-slate-700 leading-relaxed relative z-10">{{ details.issue_description }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
              <!-- Left Column: Timeline & Notes -->
              <div class="lg:col-span-7 space-y-8">
                <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2">
                  <i class="fas fa-timeline text-indigo-500"></i> الخط الزمني والتحديثات
                </h4>

                <div v-if="timelineEvents.length === 0" class="text-center opacity-30 py-8">
                  <i class="fas fa-clock text-3xl text-slate-300 mb-2"></i>
                  <p class="text-[10px] font-black text-slate-400 uppercase">لا توجد أحداث مسجلة</p>
                </div>

                <div class="space-y-6 mr-3">
                  <div v-for="e in timelineEvents" :key="e.id" class="relative pr-8 border-r-2 border-slate-100 pb-2">
                    <div class="absolute -right-[9px] top-0 w-4 h-4 rounded-full border-4 border-white shadow-sm"
                         :class="e.type === 'status' ? 'bg-blue-500' : (e.is_internal ? 'bg-amber-400' : 'bg-slate-300')"></div>
                    <div class="flex flex-col">
                      <span class="text-[9px] font-black text-slate-300 uppercase tracking-tighter">
                        {{ formatDateTime(e.at) }} • بواسطة {{ e.by || 'النظام' }}
                      </span>
                      <div v-if="e.type === 'status'" class="mt-2 p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs font-black uppercase tracking-tight">
                        {{ statusLabel(e.from) }} <i class="fas fa-arrow-left-long mx-2 text-slate-300"></i> {{ statusLabel(e.to) }}
                        <p v-if="e.note" class="mt-2 text-[10px] text-slate-400 italic font-bold normal-case">ملاحظة: {{ e.note }}</p>
                      </div>
                      <div v-else :class="[e.is_internal ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-white text-slate-600 border-slate-100 shadow-sm']"
                           class="mt-2 p-4 rounded-2xl border text-xs font-bold leading-relaxed relative">
                        <span v-if="e.is_internal" class="absolute -left-2 top-2 px-2 py-0.5 bg-amber-500 text-white text-[8px] font-black uppercase rounded-lg shadow-sm">داخلية</span>
                        {{ e.content }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Status Change & Add Note Panel -->
                <div class="p-8 bg-white rounded-[2rem] border border-slate-100 shadow-sm space-y-6">
                  <h4 class="text-xs font-black text-slate-800 uppercase tracking-widest px-1">تحديث الحالة وإضافة ملاحظات</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <select v-model="statusChange.value" class="form-select-modern text-xs font-black">
                      <option value="open">مفتوح</option>
                      <option value="in_progress">قيد المعالجة</option>
                      <option value="pending_customer">بانتظار العميل</option>
                      <option value="resolved">تم الحل</option>
                      <option value="closed">مغلق</option>
                    </select>
                    <input v-model="statusChange.note" class="form-input-modern text-xs font-bold" placeholder="ملاحظة على تغيير الحالة (اختياري)" />
                  </div>
                  <button @click="applyStatus" class="h-11 w-full bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase shadow-lg active:scale-95 transition-all">
                    تحديث الحالة
                  </button>

                  <div class="space-y-4 pt-4 border-t border-slate-50">
                    <textarea v-model="noteContent" rows="2" class="w-full rounded-2xl border border-slate-100 p-4 text-xs font-bold bg-slate-50 outline-none focus:bg-white focus:border-blue-300 transition-all" placeholder="أدخل ملاحظة فنية أو إدارية..."></textarea>
                    <div class="flex items-center justify-between">
                      <label class="flex items-center gap-2 cursor-pointer text-xs font-black text-slate-400 group">
                        <input type="checkbox" v-model="noteInternal" class="w-4 h-4 rounded text-amber-500 border-slate-200" />
                        <span class="group-hover:text-slate-600 transition-colors">ملاحظة داخلية (للموظفين فقط)</span>
                      </label>
                      <button @click="addNote" :disabled="!noteContent.trim()" class="px-8 py-2.5 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase shadow-sm active:scale-95 transition-all disabled:opacity-40">
                        إضافة الملاحظة
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column: Items & Attachments -->
              <div class="lg:col-span-5 space-y-8">
                <!-- Warranty Items -->
                <div class="space-y-4">
                  <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2">
                    <i class="fas fa-microchip text-blue-500"></i> القطع المشمولة
                  </h4>
                  <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                    <div v-if="!(details.items?.length)" class="p-8 text-center opacity-30">
                      <p class="text-[10px] font-black text-slate-400 uppercase">لا توجد عناصر</p>
                    </div>
                    <div v-for="it in (details.items || [])" :key="it.id"
                         class="p-5 flex items-center justify-between border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors group">
                      <div>
                        <p class="text-xs font-black text-slate-800 leading-none group-hover:text-blue-600 transition-colors">{{ productDisplayName(it.product_id) }}</p>
                        <p class="text-[10px] text-slate-400 mt-2 font-bold">{{ it.issue_notes || 'بدون ملاحظات إضافية' }}</p>
                      </div>
                      <span class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center font-black text-xs font-mono border border-slate-100">
                        x{{ formatQty(it.quantity) }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Attachments -->
                <div class="space-y-4">
                  <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2">
                    <i class="fas fa-paperclip text-emerald-500"></i> المرفقات والوثائق
                  </h4>
                  <div v-if="!details.attachments?.length" class="p-8 text-center bg-slate-50 rounded-[2rem] border border-slate-100 opacity-30 flex flex-col items-center">
                    <i class="fas fa-cloud-upload text-3xl mb-3"></i>
                    <p class="text-[10px] font-black uppercase">لا توجد مرفقات حالياً</p>
                  </div>
                  <div v-else class="grid grid-cols-2 gap-3">
                    <div v-for="a in details.attachments" :key="a.id"
                         class="group relative bg-white p-3 rounded-2xl border border-slate-100 shadow-sm hover:shadow-xl transition-all overflow-hidden">
                      <div v-if="isImage(a.file_name)"
                           @click="openLightbox(fileUrl(a.file_name))"
                           class="aspect-square rounded-xl overflow-hidden cursor-zoom-in bg-slate-50 mb-3 border border-slate-50">
                        <img :src="fileUrl(a.file_name)" :alt="a.original_name" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                      </div>
                      <div v-else class="aspect-square rounded-xl bg-slate-50 flex items-center justify-center text-slate-300 mb-3">
                        <i class="fas fa-file-invoice text-4xl"></i>
                      </div>
                      <p class="text-[9px] font-black text-slate-600 truncate uppercase mb-2 px-1">{{ a.original_name }}</p>
                      <div class="flex items-center justify-between border-t border-slate-50 pt-2">
                        <a :href="fileUrl(a.file_name)" target="_blank" class="text-[9px] font-black text-blue-600 uppercase hover:underline">تحميل</a>
                        <button @click="removeAttachment(a.id)" class="text-[9px] font-black text-rose-500 uppercase hover:underline">حذف</button>
                      </div>
                    </div>
                  </div>
                  <!-- Upload new attachment -->
                  <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-100 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-white hover:border-blue-200 transition-all group">
                    <div class="flex flex-col items-center justify-center">
                      <i class="fas fa-plus-circle text-slate-300 group-hover:text-blue-500 transition-colors text-xl"></i>
                      <p class="text-[9px] font-black text-slate-400 mt-2 uppercase tracking-tighter">رفع مرفق جديد</p>
                    </div>
                    <input type="file" @change="onFileChange" class="hidden" />
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Lightbox -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition name="fade">
      <div v-if="lightboxOpen" class="fixed inset-0 z-[200] bg-slate-950/95 backdrop-blur-md flex items-center justify-center p-8" @click.self="closeLightbox">
        <button @click="closeLightbox" class="absolute top-8 left-8 w-12 h-12 bg-white/10 text-white rounded-full flex items-center justify-center hover:bg-white/20 transition-all">
          <i class="fas fa-times text-xl"></i>
        </button>
        <img :src="lightboxSrc" class="max-w-full max-h-full object-contain shadow-2xl rounded-2xl animate-modalIn" />
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { useProductStore } from '@/stores/product/productStore';
import { useToast } from '@/composables/useToast';
import AlertService from '@/services/AlertService';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
// BUG 2 FIX: removed duplicate semicolon
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useWarrantyStore } from '@/stores/warranty/warrantyStore';
import { useDateValidation } from '@/composables/useDateValidation';

const router = useRouter();
const route = useRoute();
const warrantyStore = useWarrantyStore();

// ─── State ────────────────────────────────────────────────────────────────────

const items = computed(() => warrantyStore.warranties);
const isLoading = computed(() => warrantyStore.isLoading);
const error = computed(() => warrantyStore.error);
const { showToast } = useToast();
const { validateDateRange } = useDateValidation();

// Filters & pagination
const search = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const dateFrom = ref('');
const dateTo = ref('');
const dateFromRef = ref(null);
const dateToRef = ref(null);
const purchaseDateRef = ref(null);

// Status tabs
const statusTabs = [
  { value: '', label: 'الكل' },
  { value: 'open', label: 'مفتوح' },
  { value: 'in_progress', label: 'قيد المعالجة' },
  { value: 'pending_customer', label: 'بانتظار العميل' },
  { value: 'resolved', label: 'تم الحل' },
  { value: 'closed', label: 'مغلق' }
];

// Create modal state
const showCreate = ref(false);
const creating = ref(false);
const form = ref({
  customer_id: null,
  invoice_id: null,
  product_serial: '',
  purchase_date: '',
  priority: 'medium',
  issue_description: '',
  items: [{ product_id: null, quantity: 1, issue_notes: '', _productQuery: '', _productResults: [], _showProductDropdown: false, _productName: '' }]
});

// Customer autocomplete
const customerQuery = ref('');
const customerStore = useCustomerStore();
const customers = computed(() => customerStore.customers);
const customerSearchResults = ref([]);
const productStore = useProductStore();
const showCustomerDropdown = ref(false);
let customerSearchAbortCtrl = null;

// ─── BUG 5 FIX: tracked timeout IDs for safe cleanup on unmount ───────────────
const pendingTimeouts = new Set();

function safeTimeout(fn, delay) {
  const id = setTimeout(() => {
    pendingTimeouts.delete(id);
    fn();
  }, delay);
  pendingTimeouts.add(id);
  return id;
}

// Replaces inline setTimeout(() => showCustomerDropdown = false, 150) in template
function scheduleHideCustomerDropdown() {
  safeTimeout(() => { showCustomerDropdown.value = false; }, 150);
}

// Replaces inline setTimeout(() => it._showProductDropdown = false, 150) in template
function scheduleHideProductDropdown(item) {
  safeTimeout(() => { item._showProductDropdown = false; }, 150);
}

onUnmounted(() => {
  pendingTimeouts.forEach(id => clearTimeout(id));
  pendingTimeouts.clear();
});

// ─── Debounce helper ──────────────────────────────────────────────────────────

function debounce(fn, delay = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), delay);
  };
}

// ─── Details enrichment ───────────────────────────────────────────────────────

const customerName = ref('');
const productNameMap = ref({});

function formatQty(q) {
  const n = Number(q);
  if (!Number.isFinite(n)) return q;
  const r = Math.round(n);
  return Math.abs(n - r) < 1e-6 ? r : n;
}

function productDisplayName(id) {
  const name = productNameMap.value[id];
  return name ? name : `#${id}`;
}

function formatDateTime(val) {
  if (!val) return '—';
  return new Date(val).toLocaleString('en-US');
}

async function fetchCustomerNameById(id) {
  try {
    // BUG 4 FIX: use the return value of searchCustomers directly
    const list = await customerStore.searchCustomers(String(id));
    const exact = (list || []).find(c => String(c.id) === String(id));
    if (exact?.name) customerName.value = exact.name;
  } catch (_) { /* ignore */ }
}

const productNameCache = new Map();

async function fetchProductNameById(id) {
  if (!id) return;
  if (productNameCache.has(id)) {
    productNameMap.value[id] = productNameCache.get(id);
    return;
  }
  try {
    const list = await productStore.searchProducts({ query: String(id), limit: 1 });
    const m = list.find(p => String(p.id) === String(id)) || list[0];
    if (m?.name) {
      productNameCache.set(id, m.name);
      productNameMap.value = { ...productNameMap.value, [id]: m.name };
    }
  } catch (_) { /* ignore */ }
}

async function enrichDetailsNames() {
  const d = details.value;
  if (!d) return;
  customerName.value = d.customer_name || '';
  if (!customerName.value && d.customer_id) await fetchCustomerNameById(d.customer_id);
  const arr = Array.isArray(d.items) ? d.items : [];
  for (const it of arr) {
    if (it.product_name) {
      productNameMap.value = { ...productNameMap.value, [it.product_id]: it.product_name };
    } else if (it.product_id) {
      fetchProductNameById(it.product_id);
    }
  }
}

// ─── Customer search ──────────────────────────────────────────────────────────

const searchCustomers = async (q) => {
  if (!q || q.trim().length < 2) { 
    customerSearchResults.value = [];
    return; 
  }
  try {
    if (customerSearchAbortCtrl) customerSearchAbortCtrl.abort();
    customerSearchAbortCtrl = new AbortController();
    // BUG 4 FIX: use the returned list instead of relying on store.searchResults
    const list = await customerStore.searchCustomers(q.trim());
    customerSearchResults.value = list || [];
  } catch (_) { 
    customerSearchResults.value = [];
  }
};

const debouncedCustomerSearch = debounce((q) => {
  showCustomerDropdown.value = true;
  searchCustomers(q);
}, 300);

const selectCustomer = (c) => {
  form.value.customer_id = c.id;
  customerQuery.value = c.name || c.phone || String(c.id);
  showCustomerDropdown.value = false;
  validateCustomer();
};

// ─── Product search per-item ──────────────────────────────────────────────────

let productSearchAbortCtrlMap = new Map();

const searchProducts = async (idx, q) => {
  const it = form.value.items[idx];
  if (!it) return;
  if (!q || q.trim().length < 2) { it._productResults = []; return; }
  try {
    const prev = productSearchAbortCtrlMap.get(idx);
    if (prev) prev.abort();
    const ctrl = new AbortController();
    productSearchAbortCtrlMap.set(idx, ctrl);
    const list = await productStore.searchProducts({ query: q.trim(), limit: 20 });
    it._productResults = list;
  } catch (_) { /* ignore */ }
};

const debouncedProductSearch = debounce((idx, q) => {
  const it = form.value.items[idx];
  if (it) it._showProductDropdown = true;
  searchProducts(idx, q);
}, 300);

const selectProduct = (idx, p) => {
  const it = form.value.items[idx];
  if (!it) return;
  it.product_id = p.id;
  it._productName = p.name;
  it._productQuery = `${p.name}${p.barcode ? ' - ' + p.barcode : ''}`;
  it._showProductDropdown = false;
  validateItem(idx);
};

// ─── Details modal state ──────────────────────────────────────────────────────

const showDetails = ref(false);
const details = ref(null);
const detailsLoading = ref(false);
const statusChange = ref({ value: 'open', note: '' });
const noteContent = ref('');
const noteInternal = ref(false);

// ─── Timeline ─────────────────────────────────────────────────────────────────

const timelineEvents = computed(() => {
  const evts = [];
  const d = details.value || {};
  const statusHist = Array.isArray(d.status_history) ? d.status_history : [];
  for (const h of statusHist) {
    evts.push({
      type: 'status',
      id: `st_${h.id || h.created_at || Math.random()}`,
      at: h.created_at || h.date || h.at || null,
      by: h.created_by || h.user || null,
      from: h.from,
      to: h.to,
      note: h.note || '',
    });
  }
  const notes = Array.isArray(d.notes) ? d.notes : [];
  for (const n of notes) {
    evts.push({
      type: 'note',
      id: `nt_${n.id || n.created_at}`,
      at: n.created_at || null,
      by: n.created_by || null,
      content: n.content || '',
      is_internal: !!n.is_internal,
    });
  }
  evts.sort((a, b) => new Date(b.at || 0) - new Date(a.at || 0));
  return evts;
});

// ─── Lightbox ─────────────────────────────────────────────────────────────────

const lightboxOpen = ref(false);
const lightboxSrc = ref('');
function fileUrl(fileName) { return `/api/public/uploads/${fileName}`; }
function isImage(fileName) {
  const ext = String(fileName || '').toLowerCase().split('.').pop();
  return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(ext);
}
function openLightbox(src) { lightboxSrc.value = src; lightboxOpen.value = true; }
function closeLightbox() { lightboxOpen.value = false; lightboxSrc.value = ''; }

// ─── Options ──────────────────────────────────────────────────────────────────

const priorityOptions = [
  { value: '', label: 'كل الأولويات' },
  { value: 'low', label: 'منخفض' },
  { value: 'medium', label: 'متوسط' },
  { value: 'high', label: 'عالٍ' },
  { value: 'urgent', label: 'عاجل' }
];

// ─── Fetch list ───────────────────────────────────────────────────────────────

async function fetchList() {
  // Validate date range if both dates are provided
  if (dateFrom.value && dateTo.value) {
    if (!validateDateRange(dateFrom.value, dateTo.value)) {
      // Error message is shown automatically by useDateValidation
      return;
    }
  }

  const params = {
    search: search.value || undefined,
    status: statusFilter.value || undefined,
    priority: priorityFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    page: currentPage.value,
    per_page: perPage.value
  };
  const result = await warrantyStore.fetchWarranties(params);
  if (result.status !== 'success') {
    showToast(result.message || 'فشل تحميل طلبات الضمان', 'error');
  }
}

// ─── Form management ──────────────────────────────────────────────────────────

function resetForm() {
  form.value = {
    customer_id: null, invoice_id: null, product_serial: '', purchase_date: '',
    priority: 'medium', issue_description: '',
    items: [{ product_id: null, quantity: 1, issue_notes: '', _productQuery: '', _productResults: [], _showProductDropdown: false, _productName: '' }]
  };
  customerQuery.value = '';
  showCustomerDropdown.value = false;
  errors.value = { customer_id: '', priority: '', issue_description: '', items: '' };
  itemErrors.value = [];
}

function addItem() {
  form.value.items.push({ product_id: null, quantity: 1, issue_notes: '', _productQuery: '', _productResults: [], _showProductDropdown: false, _productName: '' });
}

function removeItem(idx) {
  if (form.value.items.length <= 1) return;
  form.value.items.splice(idx, 1);
}

// ─── Validation helpers (pure — no side effects) ──────────────────────────────

const errors = ref({ customer_id: '', priority: '', issue_description: '', items: '' });
const itemErrors = ref([]);

function ensureItemErrorsSize() {
  while (itemErrors.value.length < form.value.items.length) itemErrors.value.push({ product_id: '', quantity: '' });
  while (itemErrors.value.length > form.value.items.length) itemErrors.value.pop();
}
function validateCustomer() { errors.value.customer_id = form.value.customer_id ? '' : 'العميل مطلوب'; }
function validatePriority() { errors.value.priority = form.value.priority ? '' : 'الرجاء اختيار أولوية'; }
function validateIssueDescription() {
  const v = (form.value.issue_description || '').trim();
  errors.value.issue_description = v.length >= 5 ? '' : 'وصف المشكلة مطلوب (5 أحرف على الأقل)';
}
function validateItem(idx) {
  ensureItemErrorsSize();
  const it = form.value.items[idx];
  if (!it) return;
  const e = itemErrors.value[idx];
  e.product_id = it.product_id ? '' : 'اختر منتجاً';
  e.quantity = it.quantity && Number(it.quantity) >= 1 ? '' : 'الكمية يجب أن تكون 1 على الأقل';
}
function validateItemsSummary() {
  const hasAny = form.value.items.some(i => i.product_id);
  errors.value.items = hasAny ? '' : 'أضف عنصرًا واحدًا على الأقل';
}

// BUG 3 FIX: isFormValid is now a pure read-only getter — validation is
// triggered by a dedicated watcher instead of inside the computed itself.
// The computed only READS the already-updated errors refs.
const isFormValid = computed(() => {
  const noFieldErrors =
    !errors.value.customer_id &&
    !errors.value.priority &&
    !errors.value.issue_description &&
    !errors.value.items;
  const noItemErrors = itemErrors.value.every(e => !e.product_id && !e.quantity);
  return noFieldErrors && noItemErrors;
});

// Watcher drives validation whenever form data changes (no side effects in computed)
watch(
  () => ({
    customer_id: form.value.customer_id,
    priority: form.value.priority,
    issue_description: form.value.issue_description,
    items: form.value.items.map(i => ({ product_id: i.product_id, quantity: i.quantity }))
  }),
  () => {
    validateCustomer();
    validatePriority();
    validateIssueDescription();
    ensureItemErrorsSize();
    form.value.items.forEach((_, idx) => validateItem(idx));
    validateItemsSummary();
  },
  { deep: true }
);

watch(() => form.value.items.length, ensureItemErrorsSize);

// ─── Create warranty ──────────────────────────────────────────────────────────

async function createWarranty() {
  // Run a final validation pass before submit
  validateCustomer(); validatePriority(); validateIssueDescription();
  ensureItemErrorsSize();
  form.value.items.forEach((_, idx) => validateItem(idx));
  validateItemsSummary();

  if (!isFormValid.value) {
    showToast('تحقق من الحقول المطلوبة قبل الإرسال', 'error');
    return;
  }

  creating.value = true;
  try {
    const payload = {
      customer_id: form.value.customer_id,
      invoice_id: form.value.invoice_id,
      product_serial: form.value.product_serial,
      purchase_date: form.value.purchase_date,
      priority: form.value.priority,
      issue_description: form.value.issue_description,
      items: form.value.items
        .filter(i => i.product_id)
        .map(i => ({ product_id: i.product_id, quantity: i.quantity, issue_notes: i.issue_notes }))
    };
    const result = await warrantyStore.createWarranty(payload);
    if (result.status === 'success') {
      showCreate.value = false;
      resetForm();
      await fetchList();
      if (result.data?.id) openDetails(result.data.id);
    } else {
      showToast(result.message || 'فشل إنشاء الطلب', 'error');
    }
  } catch (e) {
    console.error(e);
    showToast('فشل إنشاء طلب الضمان', 'error');
  } finally {
    creating.value = false;
  }
}

// ─── Open / close details ─────────────────────────────────────────────────────

async function openDetails(id) {
  detailsLoading.value = true;
  showDetails.value = true;
  try {
    const result = await warrantyStore.fetchWarrantyDetails(id);
    if (result.status === 'success') {
      details.value = result.data;
      statusChange.value = { value: result.data?.status || 'open', note: '' };
      await enrichDetailsNames();
      const q = new URLSearchParams({ ...(route.query || {}), id: String(id) });
      router.replace({ path: '/warranty', query: Object.fromEntries(q.entries()) });
    } else {
      showToast(result.message || 'فشل تحميل التفاصيل', 'error');
    }
  } finally {
    detailsLoading.value = false;
  }
}

function closeDetails() {
  showDetails.value = false;
  details.value = null;
  customerName.value = '';
  const q = { ...(route.query || {}) };
  delete q.id;
  router.replace({ path: '/warranty', query: q });
}

// ─── Status / Notes / Attachments ────────────────────────────────────────────

async function applyStatus() {
  if (!details.value) return;
  const result = await warrantyStore.updateWarrantyStatus(details.value.id, statusChange.value.value, statusChange.value.note);
  if (result.status === 'success') {
    statusChange.value.note = '';
    details.value = result.data;
    await enrichDetailsNames();
    await fetchList();
  } else {
    showToast(result.message || 'تعذر تغيير الحالة', 'error');
  }
}

async function addNote() {
  if (!details.value || !noteContent.value.trim()) return;
  const result = await warrantyStore.addNote(details.value.id, noteContent.value, noteInternal.value);
  if (result.status === 'success') {
    noteContent.value = '';
    noteInternal.value = false;
    details.value = result.data;
    await enrichDetailsNames();
  } else {
    showToast(result.message || 'تعذر إضافة الملاحظة', 'error');
  }
}

async function onFileChange(e) {
  if (!details.value) return;
  const file = e.target.files?.[0];
  if (!file) return;
  const result = await warrantyStore.uploadAttachment(details.value.id, file);
  if (result.status === 'success') {
    details.value = result.data;
    await enrichDetailsNames();
  } else {
    showToast(result.message || 'تعذر رفع المرفق', 'error');
  }
  e.target.value = '';
}

async function removeAttachment(attId) {
  if (!details.value) return;
  const confirmed = await AlertService.confirm('هل تريد حذف هذا المرفق؟', 'حذف المرفق');
  if (!confirmed) return;
  const result = await warrantyStore.deleteAttachment(details.value.id, attId);
  if (result.status === 'success') {
    details.value = result.data;
    await enrichDetailsNames();
    showToast('تم حذف المرفق بنجاح', 'success');
  } else {
    showToast(result.message || 'تعذر حذف المرفق', 'error');
  }
}

// ─── Filtered / paginated items ───────────────────────────────────────────────

const filteredItems = computed(() => {
  let arr = [...items.value];
  if (statusFilter.value) arr = arr.filter(r => r.status === statusFilter.value);
  if (priorityFilter.value) arr = arr.filter(r => r.priority === priorityFilter.value);
  if (search.value) {
    const q = search.value.toLowerCase();
    arr = arr.filter(r =>
      String(r.id).includes(q) ||
      String(r.customer_id || '').includes(q) ||
      (r.product_serial || '').toLowerCase().includes(q) ||
      (r.issue_description || '').toLowerCase().includes(q)
    );
  }
  return arr;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredItems.value.length / perPage.value)));
const pageItems = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredItems.value.slice(start, start + perPage.value);
});

function goToPage(p) {
  if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
  await fetchList();
  const id = route.query?.id;
  if (id) openDetails(id);
});

// BUG 6 FIX: watcher now calls fetchList() so filters actually apply automatically
watch([search, statusFilter, priorityFilter], () => {
  currentPage.value = 1;
  fetchList();
});

// ─── Label / class helpers ────────────────────────────────────────────────────

function statusLabel(s) {
  return ({ open: 'مفتوح', in_progress: 'قيد المعالجة', pending_customer: 'بانتظار العميل', resolved: 'تم الحل', closed: 'مغلق' }[s] || s || '-');
}
function statusClass(s) {
  return ({ open: 'bg-blue-50 text-blue-700', in_progress: 'bg-yellow-50 text-yellow-800', pending_customer: 'bg-amber-50 text-amber-800', resolved: 'bg-green-50 text-green-800', closed: 'bg-gray-100 text-gray-800' }[s] || 'bg-gray-100 text-gray-800');
}
function priorityLabel(p) {
  return ({ low: 'منخفض', medium: 'متوسط', high: 'عالٍ', urgent: 'عاجل' }[p] || p || '-');
}
function priorityClass(p) {
  return ({ low: 'bg-gray-100 text-gray-700', medium: 'bg-blue-50 text-blue-700', high: 'bg-orange-50 text-orange-700', urgent: 'bg-red-50 text-red-700' }[p] || 'bg-gray-100 text-gray-700');
}
</script>

<style scoped>
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm h-11; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none appearance-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm border border-transparent; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

.dropdown-list { @apply absolute z-50 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-60 overflow-auto py-2; }
.dropdown-item { @apply px-6 py-3 cursor-pointer hover:bg-blue-50 flex items-center justify-between border-b border-slate-50 last:border-0 transition-colors text-xs font-bold list-none; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4 lg:p-8; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col; max-height: 90vh; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3,1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.modal-enter-active, .modal-leave-active { transition: all 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.custom-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }
</style>