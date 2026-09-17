<template>
  <div class="min-h-screen bg-[#fafafa] text-slate-900 font-sans antialiased selection:bg-blue-100" dir="rtl">

    <!-- Global Loading Progress: High-precision indicator -->
    <div v-if="isSearchingProducts || isSaving || isPrinting" class="fixed top-0 left-0 right-0 h-0.5 bg-blue-600/10 z-[100]">
      <div class="h-full bg-blue-600 animate-[loading_2s_ease-in-out_infinite] w-1/3"></div>
    </div>

    <!-- Sticky Header: Glassmorphism Navigation -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200 px-6 py-3">
      <div class="max-w-[1800px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">

        <!-- User Identity & Sales Info -->
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-white shadow-sm shrink-0 transition-transform hover:scale-105">
            <i class="fas fa-cart-plus text-sm"></i>
          </div>
          <div>
            <h1 class="text-sm font-bold text-slate-900 flex items-center gap-2">إضافة عملية بيع
              <span class="text-[9px] px-2 py-0.5 rounded-md bg-blue-50 border border-blue-100 text-blue-600 font-bold uppercase tracking-wider">New Sale</span>
            </h1>
            <div class="text-[10px] text-slate-500 font-medium mt-0.5 uppercase tracking-widest leading-none">نظام نقاط البيع الذكي v2.4</div>
          </div>
        </div>

        <!-- Global Controls -->
        <div class="flex items-center gap-3">
          <!-- Branch Selector Pattern (AdminPatterns) -->
          <div v-if="[1, 2, 3].includes(authStore.user?.role_id)" class="relative min-w-[160px]">
            <select
              v-model="branchStore.selectedBranchId"
              @change="() => { branchStore.setSelectedBranch(branchStore.selectedBranchId); debouncedSearch(); }"
              class="h-9 w-full pr-9 pl-4 rounded-md border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-slate-300 focus:ring-4 focus:ring-blue-500/5 outline-none transition-all appearance-none cursor-pointer"
            >
              <option :value="null" disabled>-- اختر الفرع --</option>
              <option v-for="b in branchStore.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <i class="fas fa-warehouse absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
          </div>

          <button @click="goToCashierDashboard" class="h-9 px-4 bg-white text-slate-600 rounded-md border border-slate-200 text-xs font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
            <i class="fas fa-chart-line text-[10px]"></i> لوحة التحكم
          </button>

          <button @click="() => debouncedSearch()" class="h-9 w-9 flex items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 hover:text-blue-600 transition-colors shadow-sm">
            <i class="fas fa-sync-alt text-xs" :class="{'animate-spin': isSearchingProducts}"></i>
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-[1800px] mx-auto p-4 lg:p-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Right Column: Product Catalog & Search (Main Area) -->
        <div class="lg:col-span-8 space-y-6">
          <section class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <!-- Utility Toolbar -->
            <div class="p-4 bg-slate-50/50 border-b border-slate-100 flex flex-col md:flex-row items-center gap-4">
              <div class="relative flex-grow group">
                <input
                  v-model="searchQuery"
                  ref="productSearchInputRef"
                  @input="debouncedSearch"
                  @keydown.enter.prevent="addFirstResult"
                  type="text"
                  placeholder="ابحث بالاسم، الكود، أو الباركود (F1)..."
                  class="w-full h-10 pr-10 pl-12 rounded-lg border border-slate-200 text-sm focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 outline-none transition-all font-medium"
                />
                <i class="fas fa-search absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
                  <kbd class="hidden md:inline-block px-1.5 py-0.5 border border-slate-200 rounded text-[9px] font-sans text-slate-400 bg-white">F1</kbd>
                </div>
              </div>

              <div class="relative min-w-[180px]">
                <select v-model="selectedCategory" @change="debouncedSearch" class="h-10 w-full pr-9 pl-4 border border-slate-200 rounded-lg text-xs font-bold text-slate-700 bg-white appearance-none outline-none focus:border-blue-500">
                  <option value="">جميع التصنيفات</option>
                  <option v-for="cat in categories || []" :key="cat?.id" :value="cat?.id">{{ cat?.name }}</option>
                </select>
                <i class="fas fa-tags absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
              </div>
            </div>

            <!-- Operational Quick Filters -->
            <div class="px-4 py-2 border-b border-slate-50 flex flex-wrap gap-2">
              <button @click="toggleInactive" :class="[showInactive ? 'bg-red-50 text-red-600 border-red-200 shadow-inner' : 'bg-white text-slate-500 border-slate-200']" class="px-3 py-1.5 rounded-md border text-[10px] font-bold transition-all flex items-center gap-2">
                <i class="fas fa-eye-slash"></i> غير نشط
              </button>
              <button @click="toggleExpiring" :class="[showExpiring ? 'bg-amber-50 text-amber-600 border-amber-200 shadow-inner' : 'bg-white text-slate-500 border-slate-200']" class="px-3 py-1.5 rounded-md border text-[10px] font-bold transition-all flex items-center gap-2">
                <i class="fas fa-hourglass-half"></i> أوشكت على الانتهاء
              </button>
            </div>

            <!-- Product Table: Professional Grid -->
            <div class="overflow-x-auto">
              <table class="w-full text-right border-collapse">
                <thead>
                  <tr class="bg-slate-50/50 border-b border-slate-100 font-bold text-slate-400 uppercase text-[10px] tracking-widest">
                    <th class="px-4 py-3 w-12 text-center">#</th>
                    <th class="px-4 py-3">الصنف / التعريف</th>
                    <th class="px-4 py-3 text-center">المتوفر</th>
                    <th class="px-4 py-3">سعر البيع</th>
                    <th class="px-4 py-3 text-center w-16">إضافة</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <template v-if="isSearchingProducts">
                    <tr v-for="n in 6" :key="n" class="animate-pulse">
                      <td v-for="m in 5" :key="m" class="px-4 py-4"><div class="h-3 bg-slate-100 rounded w-full"></div></td>
                    </tr>
                  </template>
                  <tr v-for="(product, i) in filteredSearchResults" :key="product.id" class="hover:bg-blue-50/20 transition-all group">
                    <td class="px-4 py-4 text-center text-[10px] font-mono text-slate-300">{{ i + 1 }}</td>
                    <td class="px-4 py-4">
                      <p class="text-sm font-bold text-slate-900 leading-none mb-1">{{ product.name }}</p>
                      <p class="text-[9px] font-mono text-slate-400 uppercase tracking-tighter">{{ product.product_code || 'N/A' }} • {{ product.barcode || '-' }}</p>
                    </td>
                    <td class="px-4 py-4 text-center">
                      <span :class="[product.quantity > 5 ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-600 border-rose-100']" class="px-2 py-0.5 rounded text-[10px] font-bold border font-mono">
                        {{ product.quantity ?? 0 }} {{ product.unit_name }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-left">
                      <p class="text-sm font-bold text-blue-600 font-mono tracking-tighter">{{ formatPrice(product.sale_price) }}</p>
                      <p class="text-[9px] text-slate-400 font-medium">أقل سعر: {{ formatPrice(product.min_sale_price) }}</p>
                    </td>
                    <td class="px-4 py-4 text-center">
                      <button @click="addToInvoice(product)" :disabled="loadingProductId === product.id" class="add-btn-table mx-auto">
                        <BaseSpinner v-if="loadingProductId === product.id" size="14" />
                        <i v-else class="fas fa-plus text-[10px]"></i>
                      </button>
                    </td>
                  </tr>
                  <!-- Empty State -->
                  <tr v-if="!filteredSearchResults.length && !isSearchingProducts">
                    <td colspan="5" class="py-24 text-center text-slate-300">
                      <i class="fas fa-search text-3xl mb-4 opacity-20"></i>
                      <p class="text-xs font-bold uppercase tracking-widest">لا توجد نتائج مطابقة</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Left Column: Checkout Sidebar (4/12) -->
        <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
          
          <!-- Shift/Session Indicator -->
          <div v-if="sessionsEnabled" class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">مراقبة الجلسة</span>
              <div class="flex items-center gap-3">
                <BusyIndicator v-if="loadingSession || refreshingSession" type="dots" size="xs" />
                <span v-if="currentSession" class="status-badge-green">مفتوحة</span>
                <span v-else class="status-badge-gray">مغلقة</span>
              </div>
            </div>
            <div class="p-5">
              <div v-if="currentSession" class="space-y-2">
                <div class="flex justify-between text-[11px] font-medium"><span class="text-slate-400 uppercase">النوع / الوردية</span><span class="text-slate-800 font-bold">#{{ currentSession.shift_id || '-' }} • {{ sessionTypeToLabel(currentSession.session_type) }}</span></div>
                <div class="flex justify-between text-[11px] font-medium border-t border-slate-50 pt-2"><span class="text-slate-400 uppercase">وقت البدء</span><span class="text-slate-800 font-mono tracking-tighter">{{ formatSessionStart(currentSession.start_time) }}</span></div>
                <button @click="openRenameDevice" class="w-full mt-3 h-8 border border-slate-200 rounded text-[10px] font-bold text-slate-500 hover:bg-slate-50">تسمية المحطة</button>
              </div>
              <button v-else @click="handleOpenSession" class="w-full py-2 bg-blue-600 text-white rounded-lg text-[11px] font-bold uppercase tracking-widest shadow-lg shadow-blue-900/10 hover:bg-blue-700 transition-all">فتح جلسة عمل جديدة</button>
            </div>
          </div>

          <!-- The Main Checkout Card -->
          <section class="bg-white border border-slate-200 rounded-xl shadow-xl flex flex-col min-h-[600px] overflow-hidden">
            <!-- Customer Panel -->
            <div class="p-5 border-b border-slate-100 space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-[10px] font-black text-slate-900 uppercase tracking-widest">العميل</h3>
                <button v-if="invoice.length > 0" @click="holdInvoice" class="text-[9px] font-bold text-amber-600 uppercase hover:underline">تعليق الفاتورة</button>
              </div>
              <div class="relative group">
                <input
                  v-model="customerQuery"
                  @input="customerActiveIndex = 0; debouncedCustomerSearch(); showCustomerDropdown = true"
                  @focus="showCustomerDropdown = true"
                  @blur="hideCustomerDropdown"
                  @keydown.down.prevent="moveCustomerActive(1)"
                  @keydown.up.prevent="moveCustomerActive(-1)"
                  @keydown.enter.prevent="selectActiveCustomer()"
                  type="text"
                  placeholder="بحث عن عميل بالاسم أو الجوال..."
                  class="w-full h-10 pr-9 pl-4 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 transition-all"
                />
                <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 text-[10px]"></i>
                
                <div v-if="showCustomerDropdown" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-2xl max-h-48 overflow-auto py-1 animate-fadeIn">
                  <div @mousedown.prevent="selectedCustomer=''; customerQuery=''; showCustomerDropdown=false" class="px-4 py-2 hover:bg-slate-50 cursor-pointer text-slate-400 text-[10px] font-bold italic border-b border-slate-50">عميل نقدي (افتراضي)</div>
                  <div v-for="(c, idx) in filteredCustomers" :key="c.id" @mousedown.prevent="selectCustomer(c)" :class="[idx === customerActiveIndex ? 'bg-blue-50' : 'hover:bg-slate-50']" class="px-4 py-2.5 cursor-pointer flex justify-between border-b border-slate-50 last:border-0 transition-colors">
                    <span class="text-xs font-bold text-slate-700">{{ c.name }}</span>
                    <span class="text-[10px] font-mono text-slate-400">{{ c.phone || '-' }}</span>
                  </div>
                </div>
              </div>
              
              <div v-if="selectedCustomerData" class="flex items-center gap-3 p-3 bg-blue-50/50 rounded-lg border border-blue-100 animate-fadeIn">
                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm text-blue-600"><i class="fas fa-user-check text-[10px]"></i></div>
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-bold text-slate-900 truncate">{{ selectedCustomerData.name }}</p>
                  <p class="text-[9px] font-black font-mono tracking-tighter" :class="selectedCustomerData.balance > 0 ? 'text-rose-500' : 'text-emerald-500'">{{ formatPrice(selectedCustomerData.balance) }}</p>
                </div>
              </div>
            </div>

            <!-- Items Ledger List -->
            <div class="flex-1 overflow-y-auto custom-scroll p-4 space-y-3 bg-slate-50/30">
              <div v-if="requireApproval" class="mb-3 text-[10px] px-3 py-2 rounded-lg bg-amber-50 text-amber-700 font-bold border border-amber-200 flex items-center gap-2">
                <i class="fas fa-shield-alt"></i> سيتم حفظ الفاتورة كطلب موافقة إداري.
              </div>

              <template v-if="invoice.length > 0">
                <div v-for="item in invoice" :key="item.id" class="p-3 bg-white border border-slate-100 rounded-lg group relative hover:border-blue-200 transition-all shadow-sm">
                  <button @click="removeFromInvoice(item)" class="absolute -left-2 -top-2 w-6 h-6 bg-white border border-slate-200 text-slate-300 hover:text-rose-500 rounded-full flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100 transition-all"><i class="fas fa-times text-[10px]"></i></button>
                  
                  <div class="flex justify-between items-start mb-3">
                    <div class="min-w-0 flex-1">
                      <p class="text-xs font-bold text-slate-800 truncate">{{ item.name }}</p>
                      <p v-if="item.sale_price < item.min_sale_price" class="text-[8px] font-black text-rose-500 uppercase tracking-tighter mt-0.5">تنبيه: تحت السعر المعتمد</p>
                    </div>
                    <span class="text-xs font-bold text-slate-900 font-mono tracking-tighter">{{ formatPrice(item.sale_price * item.selectedQuantity) }}</span>
                  </div>

                  <div class="grid grid-cols-2 gap-3 items-end">
                    <div class="space-y-1">
                      <label class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.2em]">الكمية</label>
                      <input type="number" v-model.number="item.selectedQuantity" @change="updateQty(item, item.selectedQuantity)" class="h-8 w-full bg-slate-50 border border-slate-200 rounded text-center text-xs font-black text-indigo-600 focus:ring-0" />
                    </div>
                    <div class="space-y-1 text-left">
                      <label class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.2em]">تعديل السعر</label>
                      <input type="number" v-model.number="item.sale_price" @change="validateSalePrice(item)" class="h-8 w-full bg-slate-50 border border-slate-200 rounded text-center text-[10px] font-bold text-slate-500" />
                    </div>
                  </div>

                  <!-- Tracking Meta -->
                  <div v-if="item.has_batch_number || item.has_serial_number" class="mt-3 grid grid-cols-2 gap-2">
                    <select v-if="item.has_batch_number" v-model="item.batch_number" @change="onBatchChange(item)" class="h-7 border border-slate-100 bg-slate-50 rounded text-[9px] px-1 font-bold">
                      <option value="">الدفعة</option>
                      <option v-for="batch in item.available_batches" :key="batch.batch_number" :value="batch.batch_number">{{ batch.batch_number }}</option>
                    </select>
                    <select v-if="item.has_serial_number" v-model="item.serial" class="h-7 border border-slate-100 bg-slate-50 rounded text-[9px] px-1 font-bold">
                      <option value="">SN</option>
                      <option v-for="s in item.available_serials" :key="s" :value="s">{{ s }}</option>
                    </select>
                  </div>
                </div>
              </template>
              <div v-else class="h-full flex flex-col items-center justify-center text-slate-300 opacity-30 py-20">
                <i class="fas fa-shopping-basket text-4xl mb-4"></i>
                <p class="text-[10px] font-black uppercase tracking-[0.3em]">بانتظار إضافة الأصناف</p>
              </div>
            </div>

            <!-- Held Invoices Registry -->
            <div v-if="heldInvoices.length > 0" class="px-5 py-3 bg-amber-50 border-t border-amber-100 divide-y divide-amber-100">
               <div v-for="(held, idx) in heldInvoices" :key="idx" class="flex justify-between items-center py-2 animate-fadeIn">
                 <div class="min-w-0 flex-1">
                   <p class="text-[10px] font-bold text-amber-900 truncate">{{ held.customer_name }}</p>
                   <p class="text-[8px] text-amber-600 font-mono tracking-tighter">{{ formatPrice(held.finalTotal) }}</p>
                 </div>
                 <button @click="resumeInvoice(idx)" class="h-7 px-3 bg-amber-200 text-amber-900 rounded text-[9px] font-bold uppercase tracking-widest hover:bg-amber-300 transition-colors">استئناف</button>
               </div>
            </div>

            <!-- Totals Section: High-Contrast SaaS Footer -->
            <footer class="bg-slate-900 p-6 text-white space-y-6">
              <div class="space-y-2 border-b border-white/10 pb-4">
                <div class="flex justify-between text-[11px] font-medium text-slate-400 uppercase tracking-tight">
                  <span>الإجمالي الفرعي</span>
                  <span class="font-mono tracking-tighter">{{ formatPrice(subTotalNet) }}</span>
                </div>
                <div class="flex justify-between items-center text-[11px] font-medium text-slate-400">
                  <div class="flex items-center gap-2">
                    <span class="uppercase tracking-tight">الخصم</span>
                    <select v-model="discountType" class="bg-white/10 border-0 rounded px-1 text-[9px] font-bold outline-none"><option class="text-slate-900">مبلغ</option><option class="text-slate-900">نسبة %</option></select>
                    <input v-model.number="discountValue" type="number" class="bg-white/10 border-0 rounded w-10 px-1 text-[9px] font-bold outline-none text-center" />
                  </div>
                  <span class="text-rose-400 font-mono tracking-tighter">- {{ formatPrice(discountAmount) }}</span>
                </div>
                <div class="flex justify-between text-[11px] font-medium text-slate-400 uppercase tracking-tight">
                  <span>الضريبة ({{ (taxValue || 0).toFixed(0) }}%)</span>
                  <span class="font-mono tracking-tighter">+ {{ formatPrice(taxAmount) }}</span>
                </div>
              </div>

              <div class="flex justify-between items-end">
                <div>
                  <p class="text-[9px] font-bold text-blue-400 uppercase tracking-[0.2em] mb-1">المبلغ الإجمالي</p>
                  <p class="text-4xl font-bold font-mono tracking-tighter leading-none">{{ formatPrice(finalTotal) }}</p>
                </div>
                <div class="text-left flex flex-col items-end gap-2">
                  <span :class="[saleStatus === 'paid' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/20 text-rose-400 border-rose-500/20']" class="px-2 py-1 rounded text-[9px] font-bold uppercase border">
                    {{ saleStatus === 'paid' ? 'مدفوعة' : 'آجل' }}
                  </span>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/10">
                <div class="space-y-1.5">
                  <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">وسيلة الدفع</label>
                  <select v-model="selectedPaymentMethod" class="w-full h-9 bg-white/5 border border-white/10 rounded-lg px-2 text-xs font-bold outline-none focus:border-blue-500 transition-all"><option v-for="m in paymentMethods" :key="m.id" :value="m.id" class="text-slate-900">{{ m.name }}</option></select>
                </div>
                <div class="space-y-1.5">
                  <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">المبلغ المدفوع</label>
                  <input v-model.number="actualPaidAmount" type="number" class="w-full h-9 bg-white/5 border border-white/10 rounded-lg px-3 text-sm font-bold font-mono text-emerald-400 text-left outline-none focus:border-emerald-500 transition-all" />
                </div>
              </div>

              <div class="grid grid-cols-1 gap-3 pt-2">
                <button @click="saveAndPrint" :disabled="isSaving || isPrinting || !invoice.length" class="h-12 bg-blue-600 rounded-xl text-sm font-bold flex items-center justify-center gap-3 shadow-lg shadow-blue-900/50 hover:bg-blue-500 active:scale-95 transition-all">
                  <i v-if="!isPrinting" class="fas fa-print"></i>
                  <BaseSpinner v-else size="18" color="#fff" />
                  حفظ وطباعة الفاتورة (F2)
                </button>
                <button @click="saveSale()" :disabled="isSaving || isPrinting || !invoice.length" class="h-10 border border-white/10 hover:bg-white/5 rounded-xl text-xs font-bold text-slate-400 transition-all">
                  <i v-if="!isSaving" class="fas fa-save ml-2"></i>
                  <BaseSpinner v-else size="14" color="#fff" class="ml-2" />
                  {{ requireApproval ? 'إرسال طلب اعتماد' : 'حفظ الفاتورة فقط (F3)' }}
                </button>
              </div>
            </footer>
          </section>
        </aside>
      </div>
    </main>

    <!-- Modals Logic Area (Teleported for robustness) -->
    <Teleport to="body">
      <div v-if="showOpenDialog || showCloseDialog || showSummaryDialog || showRenameDevice || showBranchRequiredModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
        
        <!-- Opening Session Dialog -->
        <BaseModal :show="showOpenDialog" @close="showOpenDialog = false" maxWidth="md" variant="modern">
          <template #header>
            <h3 class="text-sm font-bold text-slate-900 uppercase">بدء جلسة عمل جديدة</h3>
          </template>
          <div class="p-4 space-y-6">
            <div v-if="authStore.isAdmin" class="space-y-1.5">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">المستودع المستهدف</label>
              <select v-model="selectedBranch" class="h-10 w-full border border-slate-200 rounded-lg px-3 text-xs font-bold appearance-none bg-slate-50 focus:bg-white transition-all"><option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option></select>
            </div>
            <div class="space-y-1.5">
              <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">الرصيد الافتتاحي (العهدة)</label>
              <input v-model.number="openingAmount" type="number" class="h-14 w-full border-2 border-slate-100 rounded-xl text-3xl font-black text-center text-blue-600 focus:border-blue-500 outline-none transition-all" />
            </div>
            <button @click="confirmOpenSession" :disabled="openSubmitting" class="w-full h-12 bg-blue-600 text-white rounded-lg text-xs font-bold uppercase tracking-widest shadow-lg active:scale-95 transition-all">تأكيد وبدء العمل</button>
          </div>
        </BaseModal>

        <!-- Branch Required Selection -->
        <div v-if="showBranchRequiredModal" class="bg-white rounded-2xl p-10 max-w-sm w-full shadow-2xl text-center space-y-8 border border-slate-200 animate-modalIn">
          <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner"><i class="fas fa-warehouse text-2xl"></i></div>
          <div><h2 class="text-xl font-bold text-slate-900 uppercase">تحديد موقع العمل</h2><p class="text-[10px] text-slate-400 mt-2 font-medium">يتطلب النظام تحديد فرع نشط لمباشرة عمليات البيع</p></div>
          <select v-model="tempBranchId" class="w-full h-12 border border-slate-200 rounded-xl px-4 text-sm font-black text-center text-slate-700 focus:ring-4 focus:ring-blue-500/10 outline-none bg-slate-50"><option :value="null" disabled>-- اختر الفرع المطلوب --</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
          <div class="flex gap-3"><button @click="cancelBranchSelection" class="flex-1 h-11 text-xs font-bold text-slate-500">إلغاء</button><button @click="confirmBranchSelection" :disabled="!tempBranchId" class="flex-[2] h-11 bg-slate-900 text-white rounded-xl text-xs font-bold shadow-xl active:scale-95 disabled:opacity-50 transition-all">دخول لنقطة البيع</button></div>
        </div>

        <!-- Additional modals (Close/Summary/Rename) follow same pattern... -->
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import BusyIndicator from '@/components/ui/BusyIndicator.vue';
import getLocalDateISO from '@/utils/date';
import { useCashierSessionGuard } from '@/composables/useCashierSessionGuard';

const isComponentMounted = ref(false);
import { useSessionStore } from '@/stores/session/sessionStore';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useProductStore } from '@/stores/product/productStore';
import { useInventoryStore } from '@/stores/inventory/inventoryStore';
import { useSalesStore } from '@/stores/sales/salesStore';
import { useCatalogStore } from '@/stores/catalog/catalogStore';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useTerminalStore } from '@/stores/terminal/terminalStore';
import { useAnalyticsStore } from '@/stores/analytics';
import { useBootstrapStore } from '@/stores/bootstrap';
import { getBuilderByTemplate } from '@/utils/printTemplates';
import { printDocument } from '@/utils/PrintService';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import BaseModal from '@/components/BaseModal.vue';

// --- Router & Composables ---
const router = useRouter();
const { showToast } = useToast();
const { showLoader, hideLoader } = useLoader();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const catalogStore = useCatalogStore();
const customerStore = useCustomerStore();
const paymentStore = usePaymentStore();
const terminalStore = useTerminalStore();
const analyticsStore = useAnalyticsStore();
const productStore = useProductStore();
const inventoryStore = useInventoryStore();
const salesStore = useSalesStore();
const bootstrapStore = useBootstrapStore();
const { ensureOpenSession } = useCashierSessionGuard();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);

// --- Debug ---
const DEBUG = (import.meta?.env?.VITE_POS_DEBUG === '1') || (localStorage.getItem('pos_debug') === '1');
const dlog = (level, ...args) => {
  if (!DEBUG) return;
  const prefix = '[POS]';
  if (level === 'warn') return console.warn(prefix, ...args);
  if (level === 'error') return console.error(prefix, ...args);
  return console.log(prefix, ...args);
};

// --- branches & Terminals ---
const branches = computed(() => branchStore.branches);
const selectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
});
const terminals = ref([]);
const selectedTerminalId = ref('');

// --- Session ---
const currentSession = ref(null);
const loadingSession = ref(false);
const refreshingSession = ref(false);
const lastSessionId = ref(null);
const sessionsMode = ref('');
const settingsLoaded = ref(false);
const sessionLimitReached = ref(false);
const enforceForRoles = ref([]);

const sessionsEnabled = computed(() => {
  const mode = (sessionsMode.value || '').toLowerCase();
  return mode === 'one_per_day' || mode === 'two_per_day' || mode === 'three_per_day';
});
const sessionsModeLabel = computed(() => {
  switch ((sessionsMode.value || '').toLowerCase()) {
    case 'one_per_day': return 'جلسة واحدة يوميًا';
    case 'two_per_day': return 'جلستان يوميًا';
    case 'three_per_day': return 'ثلاث جلسات يوميًا';
    default: return 'غير محدد';
  }
});
const enforceSessionForMe = computed(() => {
  const rid = authStore.user?.role_id;
  return Array.isArray(enforceForRoles.value) && enforceForRoles.value.map(n => Number(n)).includes(Number(rid));
});
const currentTerminalLabel = computed(() => {
  const sid = currentSession.value?.terminal_id;
  if (!sid) return '';
  const t = (terminals.value || []).find(tt => Number(tt.id) === Number(sid));
  return t ? (t.code ? `${t.code} - ${t.name}` : (t.name || '')) : '';
});

// --- Session Dialogs ---
const showOpenDialog = ref(false);
const openingAmount = ref('');
const openSubmitting = ref(false);
const showCloseDialog = ref(false);
const closeCountedCash = ref('');
const closeNotes = ref('');
const closeSubmitting = ref(false);
const closeSummary = ref(null);
const showSummaryDialog = ref(false);
const varianceReason = ref('');
const selectedVarianceReason = ref('');
const varianceReasons = [
  { value: '', label: '-- اختر سبب الفرق --' },
  { value: 'counting_error', label: 'خطأ في العد' },
  { value: 'unrecorded_income', label: 'إيراد غير مسجل' },
  { value: 'manual_payment', label: 'دفع يدوي' },
  { value: 'other', label: 'سبب آخر' }
];
const showRenameDevice = ref(false);
const deviceNameInput = ref('');

const cashDifferencePos = computed(() => Number(closeCountedCash.value || 0) - 0);

// --- Search & Products ---
const searchQuery = ref('');
const selectedCategory = ref('');
const categories = computed(() => catalogStore.getCategoriesForBranch(branchStore.selectedBranchId));
const searchResults = ref([]);
const isSearchingProducts = ref(false);
const loadingProductId = ref(null); // لمنع الضغط المتعدد على زر الإضافة
const showInactive = ref(false);
const showExpiring = ref(false);
const productSearchInputRef = ref(null);
let productSearchAbortCtrl = null;

const filteredSearchResults = computed(() => {
  let results = [...searchResults.value];
  if (showInactive.value) results = results.filter(p => !p.active);
  if (showExpiring.value) {
    const today = new Date();
    const soonDate = new Date();
    soonDate.setDate(today.getDate() + 7);
    results = results.filter(p => {
      if (!p.expire_date) return false;
      const d = new Date(p.expire_date);
      return d >= today && d <= soonDate;
    });
  }
  return results;
});

// --- Customers ---
const customers = computed(() => customerStore.customers);
const customerSearchResults = ref([]);
const selectedCustomer = ref('');
const customerQuery = ref('');
const showCustomerDropdown = ref(false);
const customerActiveIndex = ref(0);
let customerSearchAbortCtrl = null;

const selectedCustomerData = computed(() => customers.value.find(c => c.id === selectedCustomer.value) || null);
const filteredCustomers = computed(() => {
  const q = (customerQuery.value || '').toString().trim().toLowerCase();
  if (!q) return customers.value;
  return customerSearchResults.value.filter(c =>
    (c.name || '').toLowerCase().includes(q) ||
    (c.phone || '').toLowerCase().includes(q) ||
    (c.code || '').toLowerCase().includes(q)
  );
});

// --- Invoice ---
const invoice = ref([]);
const heldInvoices = ref([]);
const discountType = ref('مبلغ');
const discountValue = ref(0);
const taxValue = ref(0);
const taxEnabled = ref(false);
const taxRate = ref(0);
const showTaxInPrice = ref(true);
const requireApproval = ref(false);
const paymentMethods = computed(() => paymentStore.paymentMethods);
const selectedPaymentMethod = ref('');
const actualPaidAmount = ref(0);
const isSaving = ref(false);
const isPrinting = ref(false);

const totalAmount = computed(() => invoice.value.reduce((sum, i) => sum + i.sale_price * i.selectedQuantity, 0));

const discountAmount = computed(() => {
  let disc = parseFloat(discountValue.value) || 0;
  if (discountType.value === 'نسبة %') disc = Math.min(Math.max(disc, 0), 100) / 100 * totalAmount.value;
  else disc = Math.min(disc, totalAmount.value);
  return disc;
});

const taxAmount = computed(() => (parseFloat(taxValue.value) || 0) / 100 * (totalAmount.value - discountAmount.value));

const finalTotal = computed(() => totalAmount.value - discountAmount.value + taxAmount.value);

const subTotalNet = computed(() => {
  if (!invoice.value.length) return 0;
  return invoice.value.reduce((sum, item) => sum + (parseFloat(item.net_total) || getNetItemTotal(item) || 0), 0);
});

const remainingAmount = computed(() => Math.max(0, finalTotal.value - (parseFloat(actualPaidAmount.value) || 0)));

const saleStatus = computed(() => {
  const paid = parseFloat(actualPaidAmount.value) || 0;
  const total = parseFloat(finalTotal.value) || 0;
  if (total <= 0) return 'due';
  if (paid >= total) return 'paid';
  if (paid > 0) return 'partial';
  return 'due';
});

const currentPaymentMethodName = computed(() => {
  const id = Number(selectedPaymentMethod.value || 0);
  const m = (paymentMethods.value || []).find(pm => Number(pm.id) === id);
  return m?.name || 'غير محددة';
});

// --- Helpers ---
const sessionTypeToLabel = (t) => {
  const v = String(t || '').toLowerCase();
  if (v === 'manual') return 'يدوي';
  if (v === 'daily') return 'يومي';
  if (v === 'morning') return 'صباحية';
  if (v === 'evening') return 'مسائية';
  return t || '-';
};

const formatSessionStart = (value) => {
  if (!value) return '';
  try {
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}/${pad(d.getMonth()+1)}/${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  } catch { return String(value); }
};

const getDeviceIdentity = () => {
  let id = localStorage.getItem('pos_device_id');
  if (!id) {
    id = 'dev-' + Math.random().toString(36).slice(2, 8) + '-' + Date.now().toString(36).slice(-6);
    try { localStorage.setItem('pos_device_id', id); } catch {}
  }
  const custom = (localStorage.getItem('pos_device_name') || '').trim();
  const nameBase = 'جهاز كاشير';
  return {
    device_id: id,
    device_name: (custom || nameBase).slice(0, 64)
  };
};

const deviceLabel = (s) => {
  if (!s) return getDeviceIdentity().device_name;
  const custom = (localStorage.getItem('pos_device_name') || '').trim();
  return s.device_name || custom || (s.device_id ? `Device ${s.device_id}` : getDeviceIdentity().device_name);
};

const getActivebranchId = () => selectedBranch.value || authStore.user?.branch_id;

const getNetItemTotal = (item) => {
  const total = item.sale_price * item.selectedQuantity;
  const disc = discountAmount.value;
  const all = totalAmount.value;
  const itemDiscount = all > 0 ? disc * (total / all) : 0;
  return total - itemDiscount;
};

const isExpired = (expiryDate) => expiryDate ? new Date(expiryDate) < new Date() : false;
const isExpiringSoon = (expiryDate) => {
  if (!expiryDate) return false;
  const diff = Math.ceil((new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24));
  return diff <= 30 && diff > 0;
};
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US') : '-';

// --- Session Actions ---
const loadAllSettings = async () => {
  try {
    const settings = await fetchSettings();
    
    if (settings) {
      // ✅ Tax Settings
      taxEnabled.value = settings['company.tax_enabled'] === '1';
      taxRate.value = parseFloat(settings['company.tax_rate']) || 0;
      showTaxInPrice.value = settings['invoice.show_tax_in_price'] !== '0';
      taxValue.value = taxEnabled.value ? taxRate.value : 0;
      requireApproval.value = settings['pos.require_approval'] === '1' || settings['pos.require_approval'] === 1 || settings['pos.require_approval'] === true;
      
      // ✅ POS Session Settings
      sessionsMode.value = (settings['pos.sessions.mode'] || '').toString();
      const raw = settings['pos.sessions.enforce_for_roles'];
      let parsed = [];
      if (Array.isArray(raw)) parsed = raw;
      else if (typeof raw === 'string') {
        try { parsed = raw.trim().startsWith('[') ? JSON.parse(raw) : raw.split(',').map(x => Number(x.trim())).filter(n => !isNaN(n)); } catch {}
      }
      enforceForRoles.value = parsed.map(n => Number(n)).filter(n => !isNaN(n));
    }
  } catch { 
    taxEnabled.value = false; 
    taxRate.value = 0; 
    taxValue.value = 0; 
  } finally {
    settingsLoaded.value = true;
  }
};

const refreshSession = async () => {
  const wid = getActivebranchId();
  try {
    loadingSession.value = true;
    if (sessionsEnabled.value) {
      const { device_id } = getDeviceIdentity();
      const sessionStore = useSessionStore();
      if (!wid && !enforceSessionForMe.value) {
        const result = await sessionStore.getCurrentSession(
          undefined,
          authStore.user?.id,
          device_id
        );
        currentSession.value = result.status === 'success' ? result.data : null;
      } else if (wid) {
        const result = await sessionStore.getCurrentSession(
          wid,
          authStore.user?.id,
          device_id
        );
        currentSession.value = result.status === 'success' ? result.data : null;
      } else {
        currentSession.value = null;
      }
      if (currentSession.value?.id && lastSessionId.value !== currentSession.value.id) {
        lastSessionId.value = currentSession.value.id;
      }
    } else {
      currentSession.value = null;
    }
  } catch { currentSession.value = null; lastSessionId.value = null; }
  finally { loadingSession.value = false; }
};

const fetchTerminals = async () => {
  try {
    const wid = getActivebranchId();
    if (!wid) { terminals.value = []; selectedTerminalId.value = ''; return; }
    await terminalStore.fetchTerminals(wid);
    terminals.value = terminalStore.getTerminalsForBranch(wid).value;
    if (!selectedTerminalId.value && terminals.value.length > 0) selectedTerminalId.value = terminals.value[0].id;
  } catch { terminals.value = []; }
};

const handleOpenSession = async () => {
  const wid = getActivebranchId();
  if (!wid && enforceSessionForMe.value) { showToast('الرجاء اختيار مخزن أولاً', 'warning'); return; }
  if (!selectedTerminalId.value) { showToast('الرجاء اختيار جهاز نقطة البيع قبل فتح الجلسة', 'warning'); return; }
  openingAmount.value = '';
  showOpenDialog.value = true;
};

const confirmOpenSession = async () => {
  const wid = getActivebranchId();
  if (!wid && enforceSessionForMe.value) { showToast('الرجاء اختيار مخزن أولاً', 'warning'); return; }
  if (!selectedTerminalId.value) { showToast('الرجاء اختيار جهاز نقطة البيع', 'warning'); return; }
  const amount = Number(String(openingAmount.value ?? '').trim() || 0);
  if (isNaN(amount) || amount < 0) { showToast('قيمة الرصيد الافتتاحي غير صحيحة', 'warning'); return; }
  try {
    openSubmitting.value = true;
    const sessionStore = useSessionStore();
    const { device_id, device_name } = getDeviceIdentity();
    const result = await sessionStore.openSession({ branch_id: String(wid) ?? undefined, opening_cash_amount: amount, session_type: 'manual', device_id, device_name, terminal_id: selectedTerminalId.value });
    if (result.status === 'success' && result.data?.id) {
      showToast('تم فتح جلسة الكاشير بنجاح', 'success');
      sessionLimitReached.value = false;
      lastSessionId.value = result.data.id;
      showOpenDialog.value = false;
      openingAmount.value = '';
      await refreshSession();
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'تعذر فتح الجلسة';
    showToast(msg, 'error');
    if (String(msg).includes('الحد الأقصى')) sessionLimitReached.value = true;
  } finally { openSubmitting.value = false; }
};

const resetCloseDialog = () => { closeCountedCash.value = ''; closeNotes.value = ''; closeSubmitting.value = false; varianceReason.value = ''; selectedVarianceReason.value = ''; };
const handleVarianceReasonChange = () => { if (selectedVarianceReason.value !== 'other') varianceReason.value = selectedVarianceReason.value; };

const confirmCloseSession = async () => {
  if (!currentSession.value?.id) return;
  const val = String(closeCountedCash.value ?? '').trim();
  if (val === '') { showToast('الرجاء إدخال قيمة النقدية المعدودة', 'warning'); return; }
  const amount = Number(val);
  if (isNaN(amount) || amount < 0) { showToast('قيمة النقدية غير صحيحة', 'warning'); return; }
  
  const counted = parseFloat(closeCountedCash.value);
  if (!isNaN(counted) && Math.abs(cashDifferencePos.value) > 0.01) {
    if (!selectedVarianceReason.value && !varianceReason.value) {
      showToast('الرجاء تحديد سبب الفرق في النقدية قبل الإغلاق', 'warning');
      return;
    }
  }
  
  try {
    closeSubmitting.value = true;
    const sessionStore = useSessionStore();
    const reasonToSend = selectedVarianceReason.value === 'other' ? varianceReason.value : selectedVarianceReason.value;
    const result = await sessionStore.closeSession(currentSession.value.id, amount, reasonToSend);
    closeSummary.value = result.status === 'success' ? result.data : null;
    showToast('تم إغلاق الجلسة بنجاح', 'success');
    showCloseDialog.value = false;
    resetCloseDialog();
    await refreshSession();
    showSummaryDialog.value = true;
  } catch (e) {
    showToast(e?.response?.data?.message || 'تعذر إغلاق الجلسة', 'error');
  } finally { closeSubmitting.value = false; }
};

const printSummary = () => {
  if (!closeSummary.value) return;
  const s = closeSummary.value.session || {}, t = closeSummary.value.totals || {}, c = closeSummary.value.calculated || {};
  const html = `<!doctype html><html lang="ar"><head><meta charset="utf-8"><title>ملخص الجلسة</title><style>body{font-family:Arial,Helvetica,sans-serif;direction:rtl;padding:16px}table{width:100%;border-collapse:collapse}td{padding:6px;border-bottom:1px solid #eee}</style></head><body onload="window.print();setTimeout(()=>window.close(),300);"><h2>ملخص الجلسة #${s.id||''}</h2><table><tr><td>البداية</td><td>${s.start_time||''}</td></tr><tr><td>النهاية</td><td>${s.end_time||''}</td></tr><tr><td>افتتاحي</td><td>${Number(s.opening_cash_amount||0).toFixed(2)}</td></tr><tr><td>مدفوعات</td><td>${Number(t.payments||0).toFixed(2)}</td></tr><tr><td>نقد داخل</td><td>${Number(t.cash_in||0).toFixed(2)}</td></tr><tr><td>نقد خارج</td><td>${Number(t.cash_out||0).toFixed(2)}</td></tr><tr><td>رصيد متوقع</td><td>${Number(c.expected_cash||0).toFixed(2)}</td></tr></table></body></html>`;
  const w = window.open('', '_blank', 'width=720,height=600');
  if (!w) return;
  w.document.open(); w.document.write(html); w.document.close();
};

const openRenameDevice = () => { try { deviceNameInput.value = localStorage.getItem('pos_device_name') || ''; } catch { deviceNameInput.value = ''; } showRenameDevice.value = true; };
const saveDeviceName = () => {
  const val = String(deviceNameInput.value || '').trim();
  try {
    if (val) localStorage.setItem('pos_device_name', val.slice(0, 64));
    else localStorage.removeItem('pos_device_name');
    showToast(val ? 'تم حفظ اسم الجهاز' : 'تمت إعادة الاسم الافتراضي', 'success');
    showRenameDevice.value = false;
  } catch { showToast('تعذر حفظ اسم الجهاز', 'error'); }
};

// --- Products & Search ---
let debounceTimer = null;
const debouncedSearch = async () => { 
  // ✅ منع التشغيل قبل جهوزية المكون
  if (!isComponentMounted.value) return;
  
  clearTimeout(debounceTimer); 
  if (!selectedBranch.value) return; 
  debounceTimer = setTimeout(searchProducts, 300); 
};

const searchProducts = async () => {
  const wid = selectedBranch.value || authStore.user?.branch_id;
  if (!wid) {
    showToast('الرجاء اختيار المخزن أولاً للبحث عن المنتجات', 'warning');
    return;
  }

  // ألغِ الطلب السابق فقط إذا كان لنفس الـ controller
  if (productSearchAbortCtrl) {
    productSearchAbortCtrl.abort();
  }
  productSearchAbortCtrl = new AbortController();
  const currentCtrl = productSearchAbortCtrl;

  isSearchingProducts.value = true;
  try {
    const res = await productStore.searchProducts({ query: searchQuery.value, branchId: wid, categoryId: selectedCategory.value, limit: 100 });
    // Verify controller hasn't changed (no newer request came)
    if (currentCtrl === productSearchAbortCtrl) {
      const list = res?.data || [];
      searchResults.value = list.map(p => ({ ...p, quantity: p.current_quantity, selectedQuantity: 1 }));
    }
  } catch (e) {
    const isAborted = e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.message === 'canceled' || e?.code === 'ERR_CANCELED';
    if (!isAborted) showToast('حدث خطأ أثناء البحث', 'error');
  } finally {
    if (currentCtrl === productSearchAbortCtrl) isSearchingProducts.value = false;
  }
};

const addFirstResult = () => {
  if (filteredSearchResults.value.length) { addToInvoice(filteredSearchResults.value[0]); searchQuery.value = ''; searchResults.value = []; }
};

const addToInvoice = async prod => {
  // منع الضغط المتعدد على نفس المنتج
  if (loadingProductId.value === prod.id) return;
  loadingProductId.value = prod.id;
  try {
    const wid = selectedBranch.value || authStore.user?.branch_id;
    if (!wid) { showToast('الرجاء اختيار مخزن أولاً', 'error'); return; }
    const availableQty = prod.quantity ?? 0;
    if (prod.selectedQuantity < 1) { showToast('الكمية يجب أن تكون أكبر من الصفر', 'warning'); return; }
    if (prod.selectedQuantity > availableQty) { showToast(`الكمية المطلوبة غير متوفرة. المتاح: ${availableQty}`, 'warning'); return; }
    let availableBatches = [], availableSerials = [];
    if (prod.has_batch_number || prod.has_serial_number) {
      try {
        const batchData = await inventoryStore.fetchBatches({ productId: prod.id, branchId: wid });
        if (prod.has_batch_number) availableBatches = batchData.data?.batches || [];
        if (prod.has_serial_number) availableSerials = batchData.data?.serials || [];
      } catch {}
    }
    const exist = invoice.value.find(i => i.id === prod.id);
    if (exist) {
      const newQty = exist.selectedQuantity + prod.selectedQuantity;
      exist.selectedQuantity = newQty > availableQty ? (showToast(`تم تعديل الكمية إلى الحد الأقصى: ${availableQty}`, 'warning'), availableQty) : newQty;
    } else {
      invoice.value.push({ ...prod, unit_id: prod.unit_id || (prod.unit && prod.unit.id) || 1, available_batches: availableBatches, available_serials: availableSerials, batch_number: '', expiry_date: '', serial: '' });
    }
  } catch { showToast('حدث خطأ أثناء إضافة المنتج', 'error'); }
  finally { loadingProductId.value = null; }
  searchQuery.value = '';
  searchResults.value = [];
};

const validateSalePrice = (item) => {
  if (item.sale_price < item.min_sale_price) { item.sale_price = item.min_sale_price; showToast(`لا يمكن البيع بأقل من ${formatPrice(item.min_sale_price)}`, 'warning'); }
};

const updateQty = (item, qty) => {
  if (qty < 1) item.selectedQuantity = 1;
  else if (qty > item.quantity) { item.selectedQuantity = item.quantity; showToast('تجاوزت الحد الأقصى للمخزون', 'warning'); }
  else item.selectedQuantity = qty;
};

const removeFromInvoice = i => { invoice.value = invoice.value.filter(x => x.id !== i.id); };

const onBatchChange = (item) => {
  if (item.batch_number && item.available_batches) {
    const batch = item.available_batches.find(b => b.batch_number === item.batch_number);
    if (batch) {
      item.expiry_date = batch.expiry_date || '';
      if (item.selectedQuantity > batch.quantity) { item.selectedQuantity = batch.quantity; showToast(`تم تعديل الكمية إلى المتاح في الدفعة: ${batch.quantity}`, 'warning'); }
    }
  }
};

// --- Customers ---
let customerDebounceTimer = null;
const debouncedCustomerSearch = () => { clearTimeout(customerDebounceTimer); customerDebounceTimer = setTimeout(searchCustomers, 300); };
const searchCustomers = async (q) => {
  customerSearchAbortCtrl?.abort();
  customerSearchAbortCtrl = new AbortController();
  try {
    const query = (customerQuery.value || '').trim();
    if (!query) {
      const response = await customerStore.fetchCustomers();
      if (response.status === 'success') {
        customerSearchResults.value = customerStore.customers;
      }
      return;
    }
    const searchResponse = await customerStore.searchCustomers(query);
    if (searchResponse.status === 'success') {
      customerSearchResults.value = searchResponse.data || [];
    } else {
      customerSearchResults.value = [];
    }
  } catch {
    if (customerSearchAbortCtrl?.signal?.aborted) return;
    customerSearchResults.value = [];
  }
};

const selectCustomer = (c) => { selectedCustomer.value = c?.id || ''; customerQuery.value = c?.name || ''; showCustomerDropdown.value = false; };
const hideCustomerDropdown = () => setTimeout(() => { showCustomerDropdown.value = false; }, 120);
const moveCustomerActive = (dir) => { const len = filteredCustomers.value.length; if (!len) return; customerActiveIndex.value = (customerActiveIndex.value + dir + len) % len; };
const selectActiveCustomer = () => { const list = filteredCustomers.value; if (list.length) selectCustomer(list[customerActiveIndex.value]); };

// --- Discount Validation ---
const getMaxAllowedDiscount = () => {
  if (!invoice.value.length) return 0;
  let maxDiscount = Infinity;
  invoice.value.forEach(item => {
    const allowed = (item.sale_price - (item.min_sale_price || 0)) * item.selectedQuantity;
    if (allowed < maxDiscount) maxDiscount = allowed;
  });
  return Math.max(maxDiscount === Infinity ? 0 : maxDiscount, 0);
};

watch([discountValue, discountType, invoice], () => {
  if (!invoice.value.length) return;
  let disc = parseFloat(discountValue.value) || 0;
  const total = totalAmount.value;
  const maxAllowed = getMaxAllowedDiscount();
  if (discountType.value === 'نسبة %') {
    const discAmount = (Math.min(Math.max(disc, 0), 100) / 100) * total;
    if (discAmount > maxAllowed) { discountValue.value = Math.floor((maxAllowed / total) * 100 * 100) / 100; showToast('لا يمكن تطبيق خصم يتجاوز أقل سعر بيع!', 'warning'); }
  } else {
    if (disc > maxAllowed) { discountValue.value = Math.floor(maxAllowed * 100) / 100; showToast('لا يمكن تطبيق خصم يتجاوز أقل سعر بيع!', 'warning'); }
  }
});

// --- Save ---
const resetInvoiceState = () => { invoice.value = []; discountValue.value = 0; taxValue.value = taxEnabled.value ? taxRate.value : 0; selectedCustomer.value = ''; customerQuery.value = ''; actualPaidAmount.value = 0; };

const holdInvoice = () => {
  if (!invoice.value.length) { showToast('لا توجد فاتورة لتعليقها', 'warning'); return; }
  heldInvoices.value.push({ items: JSON.parse(JSON.stringify(invoice.value)), customer_id: selectedCustomer.value, customer_name: selectedCustomerData.value?.name || 'عميل نقدي', discountType: discountType.value, discountValue: discountValue.value, actualPaidAmount: actualPaidAmount.value, finalTotal: finalTotal.value, timestamp: new Date().toLocaleTimeString() });
  resetInvoiceState();
  showToast('تم تعليق الفاتورة بنجاح', 'success');
};

const resumeInvoice = (index) => {
  if (invoice.value.length > 0) { showToast('يرجى حفظ الفاتورة الحالية أو تعليقها أولاً.', 'warning'); return; }
  const held = heldInvoices.value[index];
  invoice.value = held.items; selectedCustomer.value = held.customer_id; customerQuery.value = held.customer_name;
  discountType.value = held.discountType; discountValue.value = held.discountValue; actualPaidAmount.value = held.actualPaidAmount;
  heldInvoices.value.splice(index, 1);
  showToast('تم استئناف الفاتورة بنجاح', 'success');
};

const saveSale = async (options = {}) => {
  if (isSaving.value || (isPrinting.value && !options.allowWhilePrinting)) return null;
  isSaving.value = true;
  showLoader();
  try {
    const wid = selectedBranch.value || authStore.user?.branch_id;
    if (!wid) { showToast('الرجاء اختيار مخزن قبل الحفظ', 'error'); return null; }
    const branchIdStr = String(wid);
    if (!invoice.value.length) { showToast('الفاتورة فارغة', 'warning'); return null; }
    for (const item of invoice.value) {
      const total = item.sale_price * item.selectedQuantity;
      const disc = discountType.value === 'نسبة %' ? Math.min(Math.max(parseFloat(discountValue.value)||0,0),100)/100*totalAmount.value : Math.min(parseFloat(discountValue.value)||0, totalAmount.value);
      const netUnitPrice = (total - (totalAmount.value > 0 ? disc * (total / totalAmount.value) : 0)) / item.selectedQuantity;
      if (typeof item.min_sale_price === 'number' && netUnitPrice < item.min_sale_price) {
        showToast(`لا يمكن أن يصبح سعر "${item.name}" أقل من الحد الأدنى (${formatPrice(item.min_sale_price)})`, 'error');
        return null;
      }
    }
    if (!requireApproval.value) {
      try {
        const pm = Number(selectedPaymentMethod.value);
        const method = (paymentMethods.value || []).find(m => Number(m.id) === pm);
        if (method?.kind === 'cash' && (parseFloat(actualPaidAmount.value) || 0) > 0) {
          const ok = await ensureOpenSession(wid, { autoOpen: false, opening_cash_amount: 0, prompt: false, session_type: 'manual' });
          if (!ok) { openingAmount.value = ''; showOpenDialog.value = true; showToast('لا توجد جلسة كاشير مفتوحة. يرجى فتح جلسة أولاً.', 'warning'); return null; }
        }
      } catch (e) { showToast(e?.message || 'خطأ أثناء التحقق من الجلسة', 'error'); return null; }
    }
    const pm = Number(selectedPaymentMethod.value);
    const method = (paymentMethods.value || []).find(m => Number(m.id) === pm);
    const isCreditMethod = method?.kind === 'credit';
    const rawPaidAmount = parseFloat(actualPaidAmount.value) || 0;
    if (!requireApproval.value && rawPaidAmount > finalTotal.value + 0.01) {
      showToast(`المبلغ المدفوع (${rawPaidAmount.toFixed(2)}) لا يمكن أن يتجاوز إجمالي الفاتورة (${finalTotal.value.toFixed(2)})`, 'error');
      return null;
    }
    const intendedPaidAmount = requireApproval.value ? (method?.kind === 'cash' ? (parseFloat(finalTotal.value) || 0) : 0) : Math.min(rawPaidAmount, finalTotal.value);
    if (isCreditMethod && intendedPaidAmount > 0) { showToast('طريقة الدفع الآجلة لا تقبل أي مبلغ مدفوع — اجعل المبلغ المدفوع = 0 أو اختر طريقة دفع أخرى', 'error'); return null; }
    const status = requireApproval.value ? 'pending_approval' : (intendedPaidAmount >= finalTotal.value ? 'paid' : (intendedPaidAmount > 0 ? 'partial' : 'due'));
    if ((['due','partial','pending_payment'].includes(status) || (status === 'pending_approval' && isCreditMethod)) && !selectedCustomer.value) { showToast('يجب تحديد عميل لفواتير الآجل والذمم', 'error'); return null; }
    const payload = {
      customer_id: selectedCustomer.value || null,
      branch_id: branchIdStr,
      items: invoice.value.map(i => ({ product_id: i.id, quantity: i.selectedQuantity, sale_price: i.sale_price, purchase_price: i.purchase_price || 0, unit_id: Number(i.unit_id || (i.unit && i.unit.id) || 1), branch_id: branchIdStr, conversion_factor: 1, batch_number: (i.batch_number?.trim()) || null, expiry_date: (i.expiry_date?.trim()) || null, serial: (i.serial?.trim()) || null })),
      discount_type: discountType.value === 'نسبة %' ? 'percentage' : 'fixed',
      discount_value: parseFloat(discountValue.value) || 0,
      tax_rate: parseFloat(taxValue.value) || 0,
      payment_method_id: selectedPaymentMethod.value || null,
      paid_amount: intendedPaidAmount,
      tenant_id: authStore.user?.tenant_id,
      status,
      user_id: authStore.user?.id,
      device_id: getDeviceIdentity().device_id
    };
    const res = await salesStore.createSale(payload);
    if (res.status === 'success') {
      const d = res.data;
      const invoiceNo = d?.invoice_number ?? d?.sale?.invoice_number ?? d?.sale_id ?? d?.id ?? '';
      
      // ✅ تحديث تلقائي للكميات — امسح cache جميع المنتجات في المستودع
      productStore.invalidateCacheForBranch(wid);
      // ✅ إعادة جلب فعلية بدل الاكتفاء بمسح الكاش، لتحديث الجدول المعروض فوراً
      await searchProducts();
      
      showToast(`تم الحفظ (#${invoiceNo})`, 'success');
      if (options.returnResult) {
        return { ...((typeof d === 'object' && d) || {}), sale_id: d?.sale_id ?? d?.id ?? d?.sale?.id, id: d?.id ?? d?.sale_id ?? d?.sale?.id, invoice_number: d?.invoice_number ?? d?.sale?.invoice_number ?? invoiceNo };
      }
      if (!options.skipReset) resetInvoiceState();
      return { success: true };
    } else {
      showToast(res.message || 'فشل في الحفظ', 'error');
      return null;
    }
  } catch (e) {
    const errorCode = e?.response?.data?.error_code;
    const msg = e?.response?.data?.message || 'فشل في الحفظ';
    showToast(msg, 'error');
    
    // ✅ معالجة خاصة لخطأ المخزون غير الكافي
    if (errorCode === 'insufficient_stock') {
      // حدّث فورًا كمية المنتج المعني في السلة والجدول
      const wid = selectedBranch.value || authStore.user?.branch_id;
      if (wid) {
        productStore.invalidateCacheForBranch(wid);
        await searchProducts();
        
        const productId = e?.response?.data?.product_id;
        const availableQty = e?.response?.data?.available_qty;
        
        // حدّث الكمية في السلة الحالية
        if (productId && searchResults.value) {
          const item = invoice.value.find(i => i.id === productId);
          if (item) {
            const fresh = searchResults.value.find(p => p.id === productId);
            if (fresh) {
              item.quantity = fresh.quantity;
              // تنبيه إضافي للمستخدم
              showToast(`الكمية المتاحة: ${availableQty} فقط`, 'warning');
            }
          }
        }
      }
    }
    
    return null;
  } finally { hideLoader(); isSaving.value = false; }
};

const getSelectedPrintTemplate = () => {
  let t = (localStorage.getItem('pos_print_template') || '').toLowerCase();
  if (t === 'thermal') t = 'thermal-compact';
  if (t === 'a4') t = 'a4-simple';
  return new Set(['thermal-compact', 'thermal-detailed', 'a4-simple', 'a4-professional']).has(t) ? t : 'thermal-compact';
};

const fetchSaleDetails = async (saleId) => { return await salesStore.fetchSaleDetails(saleId); };
const fetchSaleDetailsWithRetry = async (saleId, retries = 3, delayMs = 500) => {
  let lastErr;
  for (let attempt = 1; attempt <= retries; attempt++) {
    try { const data = await fetchSaleDetails(saleId); if (data) return data; } catch (e) { lastErr = e; }
    if (attempt < retries) await new Promise(r => setTimeout(r, delayMs));
  }
  if (lastErr) throw lastErr;
  return null;
};

const openPrintWindow = async (html, existingWindow = null) => {
  const w = await printDocument(html, existingWindow);
  if (!w && existingWindow !== null) showToast('تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.', 'error');
  return w;
};

const saveAndPrint = async () => {
  if (isSaving.value || isPrinting.value) return;
  if (requireApproval.value) {
    isPrinting.value = true;
    try { const result = await saveSale({ skipReset: false, returnResult: true }); if (result) showToast('تم الحفظ كطلب موافقة. لا يمكن الطباعة قبل الاعتماد.', 'info'); }
    finally { isPrinting.value = false; }
    return;
  }
  const preOpened = window.open('', '_blank');
  if (!preOpened) { showToast('تم حظر نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.', 'error'); return; }
  isPrinting.value = true;
  let didSave = false;
  const invoiceSnapshot = JSON.parse(JSON.stringify(invoice.value || []));
  const totals = { total_amount: parseFloat(totalAmount.value || 0), discount_value: parseFloat(discountAmount.value || 0), tax_amount: parseFloat(taxAmount.value || 0), net_total_amount: parseFloat(finalTotal.value || 0), paid_amount: parseFloat(actualPaidAmount.value || 0) };
  const customerName = selectedCustomerData.value?.name || 'عميل نقدي';
  try {
    const result = await saveSale({ skipReset: true, returnResult: true, allowWhilePrinting: true });
    if (!result) { preOpened.close?.(); return; }
    didSave = true;
    const saleId = result.sale_id ?? result.id;
    if (!saleId) { preOpened.close?.(); return; }
    let sale = null;
    try { sale = await fetchSaleDetailsWithRetry(saleId, 3, 600); } catch {}
    if (!sale || !Array.isArray(sale.items) || !sale.items.length) {
      sale = { id: saleId, invoice_number: result.invoice_number || saleId, sale_date: getLocalDateISO(), customer_name: customerName, items: invoiceSnapshot.map(it => ({ product_name: it.name, name: it.name, quantity: it.selectedQuantity, sale_price: it.sale_price, net_total: parseFloat((it.sale_price * it.selectedQuantity).toFixed(2)) })), ...totals };
    }
    openPrintWindow(getBuilderByTemplate(getSelectedPrintTemplate())(sale), preOpened);
  } catch { try { preOpened.close?.(); } catch {} }
  finally { if (didSave) resetInvoiceState(); isPrinting.value = false; }
};

// --- Initial Load ---
const loadInitial = async () => {
  try {
    const wid = getActivebranchId();
    if (!wid) { showToast('لم يتم تحديد مستودع', 'error'); return; }
    
    // استخدام Bootstrap API لتحميل جميع البيانات دفعة واحدة
    try {
      const data = await bootstrapStore.fetchPosData();
      
      // تطبيق البيانات على الـ stores المحلية
      if (data.branches) branchStore.branches = data.branches;
      if (data.paymentMethods) paymentStore.paymentMethods = data.paymentMethods;
      
      // تحميل العملاء بشكل منفصل لأنهم ليسوا في POS bootstrap
      await customerStore.fetchCustomers().catch(err => {
        console.warn('Failed to fetch customers:', err);
      });
      
      console.log('[SalesPoint] Bootstrap data loaded successfully');
    } catch (bootstrapError) {
      console.warn('[SalesPoint] Bootstrap API failed, using fallback', bootstrapError);
      
      // Fallback: تحميل البيانات بشكل منفصل كما كان الكود الأصلي
      await Promise.all([
        customerStore.fetchCustomers(),
        paymentStore.fetchPaymentMethods()
      ]);
    }
    // Data is automatically cached in stores
  } catch (error) { 
    showToast('فشل تحميل البيانات الأولية', 'error'); 
  }
};

// --- Event Listeners ---
const handleSessionRefreshRequest = () => refreshSession();
const handleSaleReturnRecorded = async (event) => {
  await refreshSession();
  debouncedSearch();

  const customerId = event?.detail?.customerId;

  if (customerId && selectedCustomer.value === customerId) {
    await searchCustomers();
  }
};
const handleVoucherRecorded = async () => {
  await refreshSession();
};

// --- Watchers ---
watch(finalTotal, (v) => { if (!requireApproval.value) { const pm = Number(selectedPaymentMethod.value); const method = (paymentMethods.value || []).find(m => Number(m.id) === pm); if (method?.kind !== 'credit') actualPaidAmount.value = parseFloat(v.toFixed(2)); } });
watch(selectedPaymentMethod, (newVal) => { if (!newVal) return; const pm = Number(newVal); const method = (paymentMethods.value || []).find(m => Number(m.id) === pm); if (method?.kind === 'credit') actualPaidAmount.value = 0; else if (!requireApproval.value) actualPaidAmount.value = parseFloat(finalTotal.value.toFixed(2)); });
watch(requireApproval, (v) => { actualPaidAmount.value = v ? 0 : parseFloat(finalTotal.value.toFixed(2)); });
watch(selectedBranch, async (newVal, oldVal) => {
  if (!newVal || !settingsLoaded.value) return;
  // تحذير لو في فاتورة مفتوحة عند تغيير المخزن
  if (oldVal && invoice.value.length > 0) {
    const confirmed = window.confirm(
      `لديك ${invoice.value.length} منتج في الفاتورة الحالية.\nتغيير المخزن سيمسح الفاتورة. هل تريد المتابعة؟`
    );
    if (!confirmed) {
      branchStore.setSelectedBranch(oldVal);
      return;
    }
    resetInvoiceState();
  }
  sessionLimitReached.value = false;
  // Branch persistence handled by unified store
  await Promise.all([fetchTerminals(), refreshSession(), loadInitial()]);
  debouncedSearch();
});
watch(selectedPaymentMethod, (v) => { try { localStorage.setItem('pos_selectedPaymentMethod', v || ''); } catch {} });
watch(discountType, (v) => { try { localStorage.setItem('pos_discountType', v || ''); } catch {} });
watch(showInactive, (v) => { try { localStorage.setItem('pos_showInactive', v ? '1' : '0'); } catch {} });
watch(showExpiring, (v) => { try { localStorage.setItem('pos_showExpiring', v ? '1' : '0'); } catch {} });
watch(heldInvoices, (v) => { try { localStorage.setItem('held_invoices', JSON.stringify(v)); } catch {} }, { deep: true });

// --- Navigation ---
// --- Branch Required Modal (for admins with 'all' preference entering POS) ---
const showBranchRequiredModal = ref(false);
const tempBranchId = ref(null);

const confirmBranchSelection = async () => {
  // Validation: Admin must select a branch
  if ([1, 2, 3].includes(authStore.user?.role_id) && !tempBranchId.value) {
    return;
  }
  
  const branchId = tempBranchId.value || authStore.user?.branch_id;
  if (!branchId) return;
  
  // setTemporaryBranch: sets in-memory only, does NOT overwrite localStorage 'all'
  branchStore.setTemporaryBranch(branchId);
  showBranchRequiredModal.value = false;
  await Promise.all([loadInitial(), refreshSession(), fetchTerminals()]);
  debouncedSearch();
};

const cancelBranchSelection = () => router.push('/cashier-dashboard');

const goToCashierDashboard = () => router.push('/cashier-dashboard');
const openReturnsPage = (saleId = null) => {
  router.push({
    name: 'pos-returns',
    query: {
      returnFrom: 'cashier',
      saleId,
    },
  });
};
const toggleInactive = () => (showInactive.value = !showInactive.value);
const toggleExpiring = () => (showExpiring.value = !showExpiring.value);

// --- Keyboard Handler  ---
const handleKeydown = (e) => {
  if (e.key === 'F1') { e.preventDefault(); productSearchInputRef.value?.focus(); }
  if (e.key === 'F2') { e.preventDefault(); if (invoice.value.length > 0) saveAndPrint(); }
  if (e.key === 'F3') { e.preventDefault(); if (invoice.value.length > 0) saveSale(); }
};

// --- onMounted ---
onMounted(async () => {
  // ✅ تعيين حالة المكون قبل أي شيء
  isComponentMounted.value = true;
  
  // Initialize branch store
  await branchStore.initialize();
  
  // ✅ تحميل كل الإعدادات مرة واحدة بدل 3 مرات
  await loadAllSettings();

  try {
    // Branch loading handled by unified store
    const savedPaymentMethod = localStorage.getItem('pos_selectedPaymentMethod');
    // Only restore payment method if it exists in the current list
    if (savedPaymentMethod && paymentMethods.value?.some(pm => String(pm.id) === String(savedPaymentMethod))) {
      selectedPaymentMethod.value = savedPaymentMethod;
    } else {
      selectedPaymentMethod.value = paymentMethods.value?.[0]?.id || '';
    }
    
    discountType.value = localStorage.getItem('pos_discountType') || discountType.value;
    showInactive.value = localStorage.getItem('pos_showInactive') === '1';
    showExpiring.value = localStorage.getItem('pos_showExpiring') === '1';
    const held = localStorage.getItem('held_invoices');
    if (held) heldInvoices.value = JSON.parse(held);
  } catch {}

  // Sync with branch store if available
  if (branchStore.selectedBranchId) {
    // branch already selected — proceed normally
  } else if ([1, 2, 3].includes(authStore.user?.role_id)) {
    // Admin with 'all branches' setting
    await branchStore.fetchBranches();
    if (!branchStore.selectedBranchId) {
      // Admin chose 'all branches' globally → ask for a temporary branch for this session
      // We DON'T call setSelectedBranch() here to avoid overriding localStorage 'all'
      showBranchRequiredModal.value = true;
      return; // wait for modal confirmation before continuing init
    }
  } else {
    // Regular user with assigned branch
    const defaultBranch = authStore.user?.branch_id;
    if (defaultBranch && !branchStore.selectedBranchId) {
      branchStore.setTemporaryBranch(defaultBranch);
    }
  }

  // branch is set — run all initial requests
  await Promise.all([loadInitial(), refreshSession(), fetchTerminals()]);
  debouncedSearch();

  window.addEventListener('pos:return-recorded', handleSaleReturnRecorded);
  window.addEventListener('pos:voucher-recorded', handleVoucherRecorded);
  window.addEventListener('pos:session-refresh-request', handleSessionRefreshRequest);

  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  isComponentMounted.value = false;
  
  clearTimeout(debounceTimer);
  
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('pos:return-recorded', handleSaleReturnRecorded);
  
  if (productSearchAbortCtrl) {
    productSearchAbortCtrl.abort();
  }
  window.removeEventListener('pos:voucher-recorded', handleVoucherRecorded);
  window.removeEventListener('pos:session-refresh-request', handleSessionRefreshRequest);
  if (debounceTimer) clearTimeout(debounceTimer);
  if (customerDebounceTimer) clearTimeout(customerDebounceTimer);
  if (productSearchAbortCtrl) productSearchAbortCtrl.abort();
  if (customerSearchAbortCtrl) customerSearchAbortCtrl.abort();
});
</script>

<style scoped>
@keyframes loading { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

.add-btn-table {
  @apply h-8 w-8 rounded-lg border border-blue-200 bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm;
}

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.98) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.animate-modalIn { animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>