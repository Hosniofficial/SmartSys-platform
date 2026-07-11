<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-gray-700 animate-fadeIn">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white">
          <i class="fas fa-cart-plus text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none">إضافة عملية بيع جديدة</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium">قم بإدارة المنتجات، العملاء، وإتمام الفواتير بسرعة وسهولة.</p>
        </div>
      </div>

      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="goToCashierDashboard" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
          <i class="fas fa-chart-line"></i>
          لوحة التحكم
        </button>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8 w-full items-start">
      <!-- Right Side: Products & Search -->
      <div class="w-full lg:w-2/3 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-6">
            <div class="md:col-span-3 relative group">
              <input
                v-model="searchQuery"
                ref="productSearchInputRef"
                @input="debouncedSearch"
                @focus="searchQuery && debouncedSearch()"
                @keydown.enter.prevent="addFirstResult"
                type="text"
                placeholder="ابحث عن منتج (اسم، كود، الباركود)..."
                class="form-input-modern pl-12 pr-11"
              />
              <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors"></i>
              <kbd class="absolute left-3 top-1/2 -translate-y-1/2 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded text-[10px] text-gray-400 font-sans">F1</kbd>
            </div>

            <div class="md:col-span-2 relative">
              <select v-model="selectedCategory" @change="debouncedSearch" class="form-select-modern">
                <option value="">كل التصنيفات</option>
                <option v-for="cat in categories || []" :key="cat?.id" :value="cat?.id">{{ cat?.name }}</option>
              </select>
              <i class="fas fa-tags absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>

            <div v-if="[1, 2, 3].includes(authStore.user?.role_id)" class="md:col-span-2">
              <div class="relative">
                <select v-model="branchStore.selectedBranchId" @change="() => { branchStore.setSelectedBranch(branchStore.selectedBranchId); debouncedSearch(); }" class="form-select-modern">
                  <option disabled :value="null">-- اختر الفرع --</option>
                  <option v-for="wh in branchStore.branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                </select>
                <i class="fas fa-building absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
              </div>
            </div>

            <div class="md:col-span-5 flex flex-wrap items-center gap-3">
              <button @click="toggleInactive" :class="['filter-badge', showInactive ? 'active-red' : '']">
                <i class="fas fa-eye-slash ml-2"></i> المنتجات غير النشطة
              </button>
              <button @click="toggleExpiring" :class="['filter-badge', showExpiring ? 'active-amber' : '']">
                <i class="fas fa-hourglass-half ml-2"></i> أوشكت على الانتهاء
              </button>
            </div>
          </div>

          <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
            <table class="min-w-full divide-y divide-gray-100 text-sm text-right">
              <thead class="bg-gray-50/50">
                <tr>
                  <th class="px-4 py-3 text-gray-600 font-bold">#</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">كود</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">الصنف</th>
                  <th class="px-4 py-3 text-gray-600 font-bold text-center">الكمية</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">الوحدة</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">سعر البيع</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">أقل سعر</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">الباركود</th>
                  <th class="px-4 py-3 text-gray-600 font-bold">التصنيف</th>
                  <th class="px-4 py-3 text-gray-600 font-bold text-center">إجراء</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <!-- Skeleton loading for table (GPU-accelerated shimmer) -->
                <template v-if="isSearchingProducts">
                  <tr v-for="row in 5" :key="row">
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="2rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                    <td class="px-4 py-3 text-center"><BaseSkeleton type="text" size="sm" width="3rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                    <td class="px-4 py-3"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                    <td class="px-4 py-3 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                  </tr>
                </template>
                <tr v-for="(product, i) in filteredSearchResults" :key="product.id" class="hover:bg-blue-50/30 transition-colors group">
                  <td class="px-4 py-3 text-gray-400">{{ i + 1 }}</td>
                  <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ product.product_code ?? '-' }}</td>
                  <td class="px-4 py-3 font-semibold text-slate-700">{{ product.name }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-xs font-medium">{{ product.quantity ?? 0 }}</span>
                  </td>
                  <td class="px-4 py-3 text-xs text-gray-500">{{ product.unit_name ?? '-' }}</td>
                  <td class="px-4 py-3 font-bold text-blue-600">{{ formatPrice(product.sale_price) }}</td>
                  <td class="px-4 py-3 text-yellow-600 text-xs">{{ formatPrice(product.min_sale_price) }}</td>
                  <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ product.barcode ?? '-' }}</td>
                  <td class="px-4 py-3 text-xs text-gray-500">{{ product.category_name ?? '-' }}</td>
                  <td class="px-4 py-3 text-center">
                    <button
                      @click="addToInvoice(product)"
                      :disabled="loadingProductId === product.id"
                      class="add-btn-table"
                    >
                      <BaseSpinner v-if="loadingProductId === product.id" size="14" inline />
                      <i v-else class="fas fa-plus"></i>
                    </button>
                  </td>
                </tr>
                <tr v-if="!filteredSearchResults.length && !isSearchingProducts">
                  <td colspan="10" class="py-12 text-center">
                    <div class="flex flex-col items-center text-gray-400">
                      <i class="fas fa-box-open text-4xl mb-3 opacity-20"></i>
                      <p>لا توجد نتائج للبحث حالياً.</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Left Side: Checkout & Session -->
      <div class="w-full lg:w-1/3 sticky top-6 space-y-6">
        <!-- Session Status Card -->
        <div v-if="sessionsEnabled" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-5 py-4 bg-slate-50 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
              <i class="fas fa-cash-register-alt text-blue-500"></i> حالة الوردية
            </h3>
            <div class="flex items-center gap-2">
              <BusyIndicator v-if="loadingSession || refreshingSession" type="dots" size="sm" :delay="300" />
              <span v-if="currentSession" class="status-badge-green">
                <i class="fas fa-check-circle ml-1"></i> مفتوحة
              </span>
              <span v-else class="status-badge-gray">
                <i class="fas fa-exclamation-triangle ml-1"></i> مغلقة
              </span>
            </div>
          </div>
          <div class="p-5">
            <div v-if="currentSession" class="space-y-3">
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">نوع الجلسة:</span>
                <span class="font-bold text-slate-700">{{ currentSession.session_type_label || sessionTypeToLabel(currentSession.session_type || 'manual') }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">وقت البدء:</span>
                <span class="font-bold text-slate-700">{{ formatSessionStart(currentSession.start_time) }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">الجهاز:</span>
                <span class="font-bold text-slate-700">{{ deviceLabel(currentSession) }}</span>
              </div>
              <div v-if="currentSession.terminal_name || currentSession.terminal_code || currentSession.terminal_id" class="flex justify-between text-xs">
                <span class="text-gray-500">الترمينال:</span>
                <span class="font-bold text-slate-700">{{ currentSession.terminal_name || currentSession.terminal_code || currentTerminalLabel || ('#' + currentSession.terminal_id) }}</span>
              </div>
              <div v-if="currentSession.shift_id" class="flex justify-between text-xs">
                <span class="text-gray-500">رقم الوردية:</span>
                <span class="font-bold text-indigo-600">#{{ currentSession.shift_id }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">المبلغ الافتتاحي:</span>
                <span class="font-bold text-blue-600">{{ formatPrice(currentSession.opening_cash_amount || 0) }}</span>
              </div>
              <div class="pt-3 flex gap-2">
                <button @click="openRenameDevice" class="flex-1 py-2 rounded-lg border border-gray-200 text-[11px] hover:bg-gray-50 transition">
                  <i class="fas fa-edit ml-1"></i> تسمية الجهاز
                </button>
              </div>
            </div>
            <div v-else class="space-y-3">
              <p class="text-xs text-gray-500">لا توجد جلسة نشطة لهذا المخزن.</p>
              <div class="text-xs text-gray-500 flex items-center gap-1">
                <i class="fas fa-info-circle"></i>
                نمط الجلسات: {{ sessionsModeLabel }}
              </div>
              <div v-if="sessionLimitReached" class="text-xs text-red-600 flex items-center gap-1">
                <i class="fas fa-exclamation-triangle"></i>
                تم الوصول إلى الحد الأقصى للجلسات اليوم.
              </div>
              <button @click="handleOpenSession" class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-blue-200 shadow-lg hover:bg-blue-700 transition" :disabled="sessionLimitReached || refreshingSession">
                <i class="fas fa-door-open ml-2"></i> فتح جلسة جديدة
              </button>
              <button @click="openRenameDevice" class="w-full py-2 rounded-lg border border-gray-200 text-[11px] hover:bg-gray-50 transition">
                <i class="fas fa-edit ml-1"></i> تسمية الجهاز
              </button>
            </div>
          </div>
        </div>

        <!-- Checkout Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 flex flex-col min-h-[500px]">

          <!-- Customer Selection -->
          <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between mb-4">
              <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <i class="fas fa-user-circle text-blue-500"></i> بيانات العميل
              </label>
              <button v-if="invoice.length > 0" @click="holdInvoice" class="text-xs text-amber-600 hover:text-amber-700 font-bold">
                <i class="fas fa-pause-circle ml-1"></i> تعليق الفاتورة
              </button>
            </div>
            <div class="relative">
              <input
                v-model="customerQuery"
                @input="customerActiveIndex = 0; debouncedCustomerSearch(); showCustomerDropdown = true"
                @focus="showCustomerDropdown = true"
                @blur="hideCustomerDropdown"
                @keydown.down.prevent="showCustomerDropdown = true; moveCustomerActive(1)"
                @keydown.up.prevent="showCustomerDropdown = true; moveCustomerActive(-1)"
                @keydown.enter.prevent="selectActiveCustomer()"
                @keydown.esc.prevent="showCustomerDropdown = false"
                type="text"
                placeholder="ابحث عن عميل..."
                class="form-input-modern text-sm"
              />
              <i class="fas fa-search absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <button
                v-if="customerQuery"
                @click="customerQuery=''; customerActiveIndex=0; debouncedCustomerSearch(); showCustomerDropdown = true"
                type="button"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition"
              >
                <i class="fas fa-times-circle"></i>
              </button>

              <!-- Dropdown -->
              <div v-if="showCustomerDropdown" class="absolute z-30 w-full mt-2 bg-white border border-gray-100 rounded-xl shadow-2xl max-h-48 overflow-auto animate-fadeIn">
                <ul class="text-sm divide-y divide-gray-50">
                  <li @mousedown.prevent="selectedCustomer=''; customerQuery=''; showCustomerDropdown=false" class="px-4 py-2.5 hover:bg-blue-50 cursor-pointer flex justify-between items-center">
                    <span class="font-medium">عميل نقدي</span>
                    <i class="fas fa-money-bill-wave text-green-500 text-xs"></i>
                  </li>
                  <li v-for="(c, idx) in filteredCustomers || []" :key="c?.id" @mousedown.prevent="selectCustomer(c)" :class="['px-4 py-2.5 cursor-pointer flex items-center justify-between', idx === customerActiveIndex ? 'bg-blue-50' : 'hover:bg-gray-50']">
                    <div>
                      <div class="font-bold text-slate-700">{{ c.name }}</div>
                      <div class="text-[10px] text-gray-400">{{ c.phone || '-' }}</div>
                    </div>
                    <span :class="['text-[10px] px-2 py-0.5 rounded-full font-bold', (c.balance || 0) > 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600']">
                      {{ formatPrice(c.balance || 0) }}
                    </span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Customer Summary Info -->
            <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-gray-100">
              <div class="flex items-center gap-3 mb-2">
                <div class="h-9 w-9 bg-white rounded-full flex items-center justify-center border border-gray-100 text-blue-500 shadow-sm flex-shrink-0">
                  <i class="fas fa-user-check text-sm"></i>
                </div>
                <div>
                  <h4 class="text-xs font-bold text-slate-700">{{ selectedCustomerData?.name || 'عميل نقدي' }}</h4>
                  <p class="text-[10px] text-gray-500">{{ selectedCustomerData?.phone || 'بدون رقم هاتف' }}</p>
                </div>
                <span v-if="selectedCustomerData" :class="['mr-auto text-[10px] px-2 py-0.5 rounded-full font-bold', (selectedCustomerData?.balance || 0) > 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600']">
                  {{ formatPrice(selectedCustomerData?.balance || 0) }}
                </span>
              </div>
              <div v-if="(selectedCustomerData?.balance || 0) > 0" class="text-[10px] px-2 py-1 rounded bg-yellow-100 text-yellow-800 flex items-center gap-1">
                <i class="fas fa-exclamation-triangle"></i>
                هذا العميل لديه رصيد مدين.
              </div>
            </div>
          </div>

          <!-- Held Invoices -->
          <div v-if="heldInvoices.length > 0" class="px-5 py-3 bg-amber-50 border-b border-amber-100">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] font-bold text-amber-800">فواتير معلقة: ({{ heldInvoices.length }})</span>
            </div>
            <ul class="space-y-1">
              <li v-for="(held, index) in heldInvoices" :key="index" class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-amber-100">
                <div>
                  <div class="text-xs font-medium text-gray-700">{{ held.customer_name }}</div>
                  <div class="text-[10px] text-gray-500">{{ held.items.length }} منتج · <span class="font-bold">{{ formatPrice(held.finalTotal) }}</span></div>
                </div>
                <button @click="resumeInvoice(index)" class="text-[11px] bg-amber-200 px-2 py-0.5 rounded hover:bg-amber-300 transition">
                  <i class="fas fa-play-circle ml-1"></i> استئناف
                </button>
              </li>
            </ul>
          </div>

          <!-- Items Table -->
          <div class="flex-grow overflow-auto p-3">
            <!-- Approval Notice -->
            <div v-if="requireApproval" class="mb-3 text-xs px-3 py-2 rounded-xl bg-amber-50 text-amber-700 flex items-center gap-2 border border-amber-100">
              <i class="fas fa-info-circle"></i>
              تم تفعيل نظام الموافقة. سيتم حفظ الفاتورة كطلب موافقة.
            </div>
            <table class="w-full text-xs text-right border-separate border-spacing-y-1.5">
              <thead>
                <tr class="text-gray-400">
                  <th class="px-2 py-1 text-right">الصنف</th>
                  <th class="px-2 py-1 text-center">الكمية</th>
                  <th class="px-2 py-1 text-center">السعر</th>
                  <th class="px-2 py-1 text-center">الدفعة</th>
                  <th class="px-2 py-1 text-center">الصلاحية</th>
                  <th class="px-2 py-1 text-center">السيريال</th>
                  <th class="px-2 py-1 text-center">الإجمالي</th>
                  <th class="px-2 py-1"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in invoice" :key="item.id" class="bg-white group transition-all hover:shadow-md">
                  <td class="pr-3 py-2 rounded-r-xl border-y border-r border-gray-100">
                    <div class="font-bold text-slate-700">{{ item.name }}</div>
                    <div v-if="item.sale_price < item.min_sale_price" class="text-[10px] text-red-500">أقل: {{ formatPrice(item.min_sale_price) }}</div>
                  </td>
                  <td class="px-1 py-2 border-y border-gray-100 text-center">
                    <input type="number" v-model.number="item.selectedQuantity" min="1" class="w-12 h-8 text-center bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none text-xs" @change="updateQty(item, item.selectedQuantity)" />
                  </td>
                  <td class="px-1 py-2 border-y border-gray-100 text-center">
                    <input type="number" v-model.number="item.sale_price" :min="item.min_sale_price" :class="['w-20 h-8 text-center bg-gray-50 border rounded-lg focus:ring-1 focus:ring-blue-500 outline-none text-xs', item.sale_price < item.min_sale_price ? 'border-red-400' : 'border-gray-200']" @change="validateSalePrice(item)" />
                  </td>
                  <td class="px-1 py-2 border-y border-gray-100 text-center">
                    <select v-if="item.has_batch_number" v-model="item.batch_number" class="w-24 h-8 text-xs bg-gray-50 border border-gray-200 rounded-lg outline-none" @change="onBatchChange(item)">
                      <option value="">-- اختر --</option>
                      <option v-for="batch in item.available_batches" :key="batch.batch_number" :value="batch.batch_number">{{ batch.batch_number }} ({{ batch.quantity }})</option>
                    </select>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-1 py-2 border-y border-gray-100 text-center">
                    <div v-if="item.has_expiry_date && item.expiry_date" class="flex items-center justify-center gap-1">
                      <span :class="{'text-red-600 font-bold': isExpired(item.expiry_date), 'text-orange-600': isExpiringSoon(item.expiry_date), 'text-gray-600': !isExpiringSoon(item.expiry_date) && !isExpired(item.expiry_date)}">
                        {{ formatDate(item.expiry_date) }}
                      </span>
                      <i v-if="isExpired(item.expiry_date)" class="fas fa-exclamation-triangle text-red-500 text-[10px]"></i>
                      <i v-else-if="isExpiringSoon(item.expiry_date)" class="fas fa-clock text-orange-500 text-[10px]"></i>
                    </div>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-1 py-2 border-y border-gray-100 text-center">
                    <select v-if="item.has_serial_number" v-model="item.serial" class="w-24 h-8 text-xs bg-gray-50 border border-gray-200 rounded-lg outline-none">
                      <option value="">-- اختر --</option>
                      <option v-for="serial in item.available_serials" :key="serial" :value="serial">{{ serial }}</option>
                    </select>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-2 py-2 border-y border-gray-100 font-bold text-blue-600 text-center">
                    {{ formatPrice(getNetItemTotal(item)) }}
                  </td>
                  <td class="pl-2 py-2 rounded-l-xl border-y border-l border-gray-100 text-center">
                    <button @click="removeFromInvoice(item)" class="text-gray-300 hover:text-red-500 transition-colors">
                      <i class="fas fa-times-circle text-base"></i>
                    </button>
                  </td>
                </tr>
                <tr v-if="invoice.length === 0">
                  <td colspan="8" class="py-16 text-center text-gray-400">
                    <i class="fas fa-shopping-basket text-4xl mb-4 opacity-10 block"></i>
                    <p class="text-sm">سلة البيع فارغة حالياً</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Totals Section -->
          <div class="p-5 bg-slate-50 border-t border-gray-100 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500">الإجمالي الفرعي:</span>
              <span class="font-bold">{{ formatPrice(subTotalNet) }}</span>
            </div>

            <div class="flex items-center gap-2">
              <select v-model="discountType" class="bg-transparent border-none text-[11px] font-bold text-gray-500 focus:ring-0 cursor-pointer">
                <option>مبلغ</option>
                <option>نسبة %</option>
              </select>
              <input v-model.number="discountValue" type="number" min="0" class="w-16 h-7 text-[11px] bg-white border border-gray-200 rounded px-2 text-left" />
              <span class="text-xs text-gray-400">{{ discountType === 'مبلغ' ? formatPrice(discountValue) : discountValue + '%' }}</span>
              <div class="flex-grow"></div>
              <span class="text-red-500 font-bold text-sm">- {{ formatPrice(discountAmount) }}</span>
            </div>

            <div class="flex justify-between text-sm">
              <span class="text-gray-500">الضريبة ({{ (taxValue || 0).toFixed(0) }}%):</span>
              <span class="font-bold">{{ formatPrice(taxAmount) }}</span>
            </div>

            <div class="pt-3 border-t border-gray-200">
              <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-2">
                  <span class="text-lg font-black text-slate-800">الإجمالي النهائي</span>
                  <span :class="['text-[10px] px-2 py-0.5 rounded-full font-bold', saleStatus === 'paid' ? 'bg-green-100 text-green-700' : saleStatus === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
                    {{ saleStatus === 'paid' ? 'مدفوعة' : saleStatus === 'partial' ? 'جزئي' : 'مستحقة' }}
                  </span>
                </div>
                <span class="text-2xl font-black text-blue-600 tracking-tight">{{ formatPrice(finalTotal) }}</span>
              </div>

              <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="relative">
                  <select v-model="selectedPaymentMethod" class="form-select-modern text-xs h-10 pr-8">
                    <option v-for="method in paymentMethods || []" :key="method?.id" :value="method?.id">{{ method?.name }}</option>
                  </select>
                  <i class="fas fa-wallet absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
                <input v-model.number="actualPaidAmount" type="number" min="0" :max="finalTotal" placeholder="المبلغ المدفوع" class="form-input-modern text-xs h-10 text-left font-bold" :class="{ 'border-red-400': actualPaidAmount > finalTotal }" :disabled="requireApproval" />
              </div>

              <div v-if="!requireApproval && actualPaidAmount > finalTotal" class="mb-2 text-[10px] px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 text-red-600 flex items-center gap-1">
                <i class="fas fa-exclamation-circle"></i>
                المبلغ المدفوع يتجاوز إجمالي الفاتورة
              </div>
              <div v-if="requireApproval" class="mb-3 text-[11px] px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-100 text-amber-700 flex items-center gap-1">
                <i class="fas fa-info-circle"></i>
                لا يمكن تعديل المبلغ قبل اعتماد الفاتورة.
              </div>

              <div v-if="remainingAmount > 0" class="flex justify-between items-center mb-3 px-3 py-2 bg-red-50 rounded-lg text-red-700 text-xs font-bold">
                <span>المبلغ المتبقي (آجل):</span>
                <span>{{ formatPrice(remainingAmount) }}</span>
              </div>

              <div class="space-y-3">
                <button @click="saveAndPrint" :disabled="isSaving || isPrinting" class="btn-success-main">
                  <BaseSpinner v-if="isPrinting" size="sm" color="#fff" />
                  <i v-else class="fas fa-print ml-2"></i>
                  {{ isPrinting ? 'جارٍ الحفظ والطباعة...' : 'حفظ وطباعة الفاتورة' }}
                  <span class="text-[10px] opacity-60 mr-2">(F2)</span>
                </button>
                <button @click="saveSale()" :disabled="isSaving || isPrinting" class="btn-primary-main">
                  <BaseSpinner v-if="isSaving" size="sm" color="#fff" />
                  <i v-else class="fas fa-save ml-2"></i>
                  {{ isSaving ? 'جارٍ الحفظ...' : (requireApproval ? 'حفظ (طلب موافقة)' : 'حفظ الفاتورة فقط') }}
                  <span class="text-[10px] opacity-60 mr-2">(F3)</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MODALS ===== -->

    <!-- Open Session Modal -->
    <div v-if="showOpenDialog" class="modal-overlay">
      <div class="modal-content-modern animate-zoomIn">
        <div class="modal-header">
          <h3 class="font-black text-slate-800">فتح جلسة كاشير جديدة</h3>
          <button @click="showOpenDialog = false" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-5">
          <div v-if="authStore.isAdmin && branches.length > 0" class="space-y-2">
            <label class="text-xs font-bold text-gray-600">المخزن المستهدف <span class="text-red-500">*</span></label>
            <select v-model="selectedBranch" class="form-select-modern">
              <option disabled value="">-- اختر الفرع --</option>
              <option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div v-if="terminals.length" class="space-y-2">
            <label class="text-xs font-bold text-gray-600">جهاز نقطة البيع (الترمينال) <span class="text-red-500">*</span></label>
            <div class="relative">
              <select v-model="selectedTerminalId" class="form-select-modern">
                <option disabled value="">-- اختر الجهاز --</option>
                <option v-for="t in terminals" :key="t.id" :value="t.id">{{ t.code ? (t.code + ' - ' + t.name) : t.name }}</option>
              </select>
              <i class="fas fa-desktop absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-bold text-gray-600">المبلغ الافتتاحي بالخزينة <span class="text-red-500">*</span></label>
            <input v-model.number="openingAmount" type="number" min="0" step="0.01" class="form-input-modern text-center text-xl font-bold py-3" placeholder="0.00" />
          </div>
          <div class="bg-blue-50 p-3 rounded-xl text-xs text-blue-700">
            <p><i class="fas fa-info-circle ml-1"></i> سيتم فتح الجلسة للمخزن المحدد وجهاز نقطة البيع الحالي.</p>
          </div>
          <div class="flex gap-3">
            <button @click="showOpenDialog = false" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition">إلغاء</button>
            <button @click="confirmOpenSession" :disabled="openSubmitting" class="flex-1 btn-primary-main py-3 rounded-xl">
              <BaseSpinner v-if="openSubmitting" size="sm" color="#fff" />
              <span v-else>تأكيد فتح الوردية</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Close Session Modal -->
    <div v-if="showCloseDialog" class="modal-overlay">
      <div class="modal-content-modern animate-zoomIn">
        <div class="modal-header">
          <h3 class="font-black text-slate-800">إنهاء الوردية</h3>
          <button @click="showCloseDialog = false" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-5">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-blue-50 p-3 rounded-xl">
              <div class="text-gray-500 text-xs mb-1">الرصيد الافتتاحي</div>
              <div class="text-lg font-bold text-blue-700">{{ formatPrice(currentSession?.opening_cash_amount || 0) }}</div>
            </div>
            <div class="bg-purple-50 p-3 rounded-xl">
              <div class="text-gray-500 text-xs mb-1">المتوقع بالخزينة</div>
              <div class="text-sm font-bold text-purple-700">انظر لوحة تحكم الكاشير</div>
            </div>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-bold text-gray-600">المبلغ الفعلي في الخزينة (الجرد اليدوي)</label>
            <input v-model="closeCountedCash" type="number" step="0.01" min="0" class="form-input-modern text-center text-xl font-bold py-3" placeholder="0.00" />
          </div>
          <div v-if="closeCountedCash !== ''" :class="['p-3 rounded-xl border-2 text-center text-sm', cashDifferencePos === 0 ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200']">
            <div class="font-bold">{{ cashDifferencePos === 0 ? 'مطابق للمتوقع' : 'يوجد فرق' }}</div>
            <div>الفرق: {{ formatPrice(cashDifferencePos) }}</div>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-bold text-gray-600">سبب الفرق (اختياري)</label>
            <select v-model="selectedVarianceReason" class="form-select-modern" @change="handleVarianceReasonChange">
              <option v-for="reason in varianceReasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
            </select>
            <textarea v-if="selectedVarianceReason === 'other'" v-model="varianceReason" rows="2" class="form-input-modern h-auto p-3 text-sm" placeholder="الرجاء تحديد سبب الفرق..."></textarea>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-bold text-gray-600">ملاحظات</label>
            <textarea v-model="closeNotes" rows="2" class="form-input-modern h-auto p-3 text-sm" placeholder="ملاحظات..."></textarea>
          </div>
          <div class="flex gap-3">
            <button @click="showCloseDialog = false" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition">إلغاء</button>
            <button @click="confirmCloseSession" :disabled="closeSubmitting" class="flex-1 py-3 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition flex items-center justify-center gap-2">
              <BaseSpinner v-if="closeSubmitting" size="sm" color="#fff" />
              <span v-else>تأكيد الإنهاء</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Session Summary Modal -->
    <div v-if="showSummaryDialog" class="modal-overlay">
      <div class="modal-content-modern animate-zoomIn max-w-lg">
        <div class="modal-header">
          <h3 class="font-black text-slate-800">ملخص الجلسة</h3>
          <button @click="showSummaryDialog = false" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4" v-if="closeSummary">
          <span :class="['inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold', closeSummary.closing?.variance === 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
            <i :class="closeSummary.closing?.variance === 0 ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'"></i>
            {{ closeSummary.closing?.variance === 0 ? 'تم الإغلاق بدون فروقات' : `يوجد فرق: ${formatPrice(closeSummary.closing?.variance)}` }}
          </span>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="bg-gray-50 rounded-xl p-3"><div class="text-gray-400 mb-1">وقت البداية</div><div class="font-mono">{{ formatSessionStart(closeSummary.session?.start_time) }}</div></div>
            <div class="bg-gray-50 rounded-xl p-3"><div class="text-gray-400 mb-1">وقت النهاية</div><div class="font-mono">{{ formatSessionStart(closeSummary.session?.end_time) }}</div></div>
            <div class="bg-blue-50 rounded-xl p-3"><div class="text-gray-500 mb-1">إجمالي المدفوعات</div><div class="font-bold text-blue-700">{{ formatPrice(closeSummary.totals?.payments || 0) }}</div></div>
            <div class="bg-green-50 rounded-xl p-3"><div class="text-gray-500 mb-1">نقد داخل</div><div class="font-bold text-green-700">{{ formatPrice(closeSummary.totals?.cash_in || 0) }}</div></div>
            <div class="bg-red-50 rounded-xl p-3"><div class="text-gray-500 mb-1">نقد خارج</div><div class="font-bold text-red-700">{{ formatPrice(closeSummary.totals?.cash_out || 0) }}</div></div>
            <div class="bg-purple-50 rounded-xl p-3"><div class="text-gray-500 mb-1">المتوقع بالدرج</div><div class="font-bold text-purple-700">{{ formatPrice(closeSummary.calculated?.expected_cash || 0) }}</div></div>
          </div>
          <div class="flex gap-3">
            <button @click="printSummary" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition">
              <i class="fas fa-print ml-1"></i> طباعة الملخص
            </button>
            <button @click="showSummaryDialog = false" class="flex-1 btn-primary-main py-3 rounded-xl">إغلاق</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Rename Device Modal -->
    <div v-if="showRenameDevice" class="modal-overlay">
      <div class="modal-content-modern animate-zoomIn">
        <div class="modal-header">
          <h3 class="font-black text-slate-800">تسمية جهاز نقطة البيع</h3>
          <button @click="showRenameDevice = false" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
          <div class="space-y-2">
            <label class="text-xs font-bold text-gray-600">اسم الجهاز</label>
            <input v-model.trim="deviceNameInput" type="text" maxlength="64" class="form-input-modern" placeholder="مثال: كاشير 1 - الفرع الرئيسي" />
            <p class="text-[11px] text-gray-400">اتركه فارغًا للعودة إلى الاسم الافتراضي.</p>
          </div>
          <div class="flex gap-3">
            <button @click="showRenameDevice = false" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition">إلغاء</button>
            <button @click="saveDeviceName" class="flex-1 btn-primary-main py-3 rounded-xl">حفظ</button>
          </div>
        </div>
      </div>
    </div>

  <!-- Branch Required Modal (shown when admin has 'all branches' and enters POS) -->
  <Transition name="fade">
    <div v-if="showBranchRequiredModal" class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-8 max-w-sm w-full shadow-2xl text-center">
        <div class="w-16 h-16 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
          <i class="fas fa-building text-orange-500 text-2xl"></i>
        </div>
        <!-- Dynamic header based on user role -->
        <h2 class="text-xl font-black text-slate-900 mb-2">
          <span v-if="[1, 2, 3].includes(authStore.user?.role_id)">حدد الفرع للعمل</span>
          <span v-else>اختر فرعاً للمتابعة</span>
        </h2>
        <p class="text-slate-500 text-sm mb-6">نقطة البيع تتطلب تحديد فرع</p>
        
        <!-- Branch Selector for Admin -->
        <div v-if="[1, 2, 3].includes(authStore.user?.role_id)">
          <select v-model="tempBranchId" class="w-full border border-slate-200 rounded-xl p-3 mb-5 text-sm font-bold text-slate-700 focus:outline-none focus:border-indigo-400">
            <option :value="null" disabled>-- اختر الفرع المطلوب --</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        
        <!-- Info Box for Regular Users -->
        <div v-else-if="authStore.user?.branch_id" class="p-4 bg-blue-50 border border-blue-200 rounded-xl mb-5">
          <p class="text-sm font-bold text-blue-700">
            <i class="fas fa-check-circle ml-1"></i>
            تم تحديد فرعك: <strong>{{ branches.find(b => b.id === authStore.user?.branch_id)?.name || 'الفرع الرئيسي' }}</strong>
          </p>
        </div>
        
        <div class="flex gap-3">
          <button @click="cancelBranchSelection" class="flex-1 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all">إلغاء</button>
          <button @click="confirmBranchSelection" :disabled="!tempBranchId && [1, 2, 3].includes(authStore.user?.role_id)" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">تأكيد</button>
        </div>
      </div>
    </div>
  </Transition>

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

.form-input-modern {
  @apply w-full h-12 bg-white border border-gray-200 rounded-xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50;
  font-family: 'Cairo', sans-serif;
}
.form-select-modern {
  @apply w-full h-12 bg-white border border-gray-200 rounded-xl px-4 pr-11 outline-none appearance-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50;
  font-family: 'Cairo', sans-serif;
}
.btn-secondary {
  @apply px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm;
  font-family: 'Cairo', sans-serif;
}
.btn-primary-main {
  @apply w-full py-3 bg-slate-800 text-white rounded-xl font-bold hover:bg-slate-900 transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-200;
}
.btn-success-main {
  @apply w-full py-4 bg-blue-600 text-white rounded-2xl font-black text-lg hover:bg-blue-700 transition-all flex items-center justify-center gap-2 shadow-xl shadow-blue-100;
}
.add-btn-table {
  @apply h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all duration-300;
}
.filter-badge {
  @apply px-4 py-1.5 rounded-full bg-white border border-gray-100 text-[11px] font-bold text-gray-400 hover:bg-gray-50 transition-all cursor-pointer shadow-sm;
}
.active-red { @apply border-red-200 bg-red-50 text-red-600; }
.active-amber { @apply border-amber-200 bg-amber-50 text-amber-600; }
.status-badge-green { @apply px-2 py-0.5 rounded-md bg-green-100 text-green-700 text-[10px] font-black flex items-center; }
.status-badge-gray { @apply px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 text-[10px] font-black flex items-center; }
.modal-overlay {
  @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4;
}
.modal-content-modern {
  @apply bg-white w-full max-w-md rounded-[2rem] shadow-2xl overflow-hidden;
}
.modal-header {
  @apply px-8 py-6 border-b border-gray-50 flex items-center justify-between;
}
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
.animate-zoomIn { animation: zoomIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes zoomIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>