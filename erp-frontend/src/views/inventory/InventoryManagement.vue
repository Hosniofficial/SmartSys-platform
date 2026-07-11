<template>
  <div class="min-h-screen bg-[#f8fafc] text-slate-700 animate-fadeIn">
    <!-- Branch Indicator Breadcrumb -->
    <BranchIndicatorBreadcrumb 
      pageName="إدارة المخزون" 
      :currentBranchName="currentBranchName"
    />
    
    <div class="p-4 lg:p-8">
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-building text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة المخزون</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">مراقبة الكميات، القيمة المالية، الترصيد، وتسوية الفروقات</p>
        </div>
      </div>
      
      <div class="flex flex-wrap items-center gap-3 bg-white p-2 rounded-[1.5rem] border border-slate-100 shadow-sm">
        <!-- branch Selector -->
        <div class="relative min-w-[180px]">
          <select 
            v-model="selectedBranch" 
            @change="handleBranchChange"
            class="w-full h-11 pr-10 pl-4 rounded-xl border-slate-200 bg-slate-50 focus:bg-white border outline-none text-xs font-black transition-all appearance-none cursor-pointer"
          >
            <option :value="null">كل الفروع</option>
            <option v-for="wh in branches" :key="wh.id" :value="String(wh.id)">{{ wh.name }}</option>
          </select>
          <i class="fas fa-building absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        </div>

        <div class="h-6 w-px bg-slate-100 mx-1 hidden sm:block"></div>

        <div class="relative">
          <button
            @click="showPickerPanel = !showPickerPanel"
            class="h-11 px-6 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
            <i class="fas fa-sliders"></i> تسوية المخزون
          </button>

          <!-- Product Picker Panel -->
          <div
            v-if="showPickerPanel"
            class="absolute left-0 top-13 mt-1 w-80 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden"
            @click.stop
          >
            <div class="p-3 border-b border-slate-50">
              <input
                v-model="pickerSearch"
                autofocus
                placeholder="ابحث عن منتج..."
                class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold outline-none focus:ring-2 focus:ring-blue-50 focus:border-blue-400"
              />
            </div>
            <ul class="max-h-64 overflow-y-auto custom-scroll divide-y divide-slate-50">
              <li
                v-for="item in pickerItems"
                :key="item.product_id ?? item.id"
                @click="openAdjustFromPicker(item)"
                class="flex items-center justify-between px-4 py-3 hover:bg-blue-50 cursor-pointer transition-all"
              >
                <div>
                  <p class="text-xs font-black text-slate-800 leading-none">{{ item.name }}</p>
                  <p class="text-[10px] text-slate-400 font-bold mt-1 font-mono">{{ item.barcode || item.product_code || '—' }}</p>
                </div>
                <span class="text-[10px] font-black px-2 py-1 rounded-lg" :class="item.quantity <= 0 ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-600'">
                  {{ item.quantity ?? 0 }}
                </span>
              </li>
              <li v-if="pickerItems.length === 0" class="px-4 py-6 text-center text-xs text-slate-400 font-bold">لا توجد نتائج</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- KPIs Overview -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-boxes"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الأصناف</p>
            <p class="kpi-value text-slate-800">{{ stats.totalProducts || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-coins"></i>
          </div>
          <div>
            <p class="kpi-label">قيمة المخزون</p>
            <p class="kpi-value text-emerald-600 text-xl">{{ formatCurrency(stats.totalValue || 0) }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div>
            <p class="kpi-label">أوشكت على النفاذ</p>
            <p class="kpi-value text-amber-600">{{ stats.aboutToFinish || 0 }} <span class="text-[10px] font-bold opacity-60">صنف</span></p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all">
            <i class="fas fa-hourglass-end"></i>
          </div>
          <div>
            <p class="kpi-label">قريبة الانتهاء</p>
            <p class="kpi-value text-rose-600">{{ stats.expiringSoon || 0 }} <span class="text-[10px] font-bold opacity-60">صنف</span></p>
          </div>
        </div>
      </div>
    </section>

    <!-- Toolbar: Search & Filters -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 flex flex-col lg:flex-row lg:items-end gap-6">
      <!-- Search -->
      <div class="flex-grow group">
        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث سريع</label>
        <div class="relative">
          <input
            type="text"
            v-model="search"
            class="form-input-modern pr-11"
            placeholder="ابحث بـ: اسم المنتج أو الباركود..."
          />
          <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <!-- Filter -->
      <div class="lg:w-48">
        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">تصفية الحالة</label>
        <select v-model="filter" class="form-select-modern font-bold">
          <option value="all">كل المنتجات</option>
          <option value="low">منخفضة المخزون</option>
          <option value="about-to-finish">على وشك الانتهاء</option>
          <option value="out">المنتهية (نافذة)</option>
        </select>
      </div>

      <!-- Sort -->
      <div class="lg:w-48">
        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">ترتيب حسب</label>
        <select v-model="sort" class="form-select-modern font-bold">
          <option value="name">الاسم أبجدياً</option>
          <option value="quantity">الكمية (الأكثر)</option>
          <option value="value">القيمة المالية</option>
        </select>
      </div>
    </div>

    <!-- Main Inventory Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5">المنتج والباركود</th>
              <th class="px-4 py-5">التصنيف</th>
              <th class="px-4 py-5 text-center">الكمية الحالية</th>
              <th class="px-4 py-5 text-center">الحد الأدنى</th>
              <th class="px-4 py-5 text-center">التكلفة / الوحدة</th>
              <th class="px-4 py-5 text-center">إجمالي القيمة</th>
              <th class="px-4 py-5 text-center">حالة التوفر</th>
              <th class="px-4 py-5 text-center">سعر البيع</th>
              <th class="px-4 py-5 text-center">هامش الربح</th>
              <template v-if="selectedBranch">
                <th class="px-4 py-5 text-center">حالة GL</th>
                <th class="px-4 py-5 text-center">التفعيل / الترصيد</th>
              </template>
              <th v-else class="px-4 py-5 text-center text-slate-300 opacity-50" title="اختر فرعاً لرؤية حالة GL والتفعيل">حالة GL</th>
              <th class="px-6 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="!filteredInventory.length" class="text-center">
              <td :colspan="selectedBranch ? 12 : 11" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-box-open text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد بيانات مخزنية مطابقة</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="item in filteredInventory" :key="item.id" class="hover:bg-blue-50/30 transition-all group">
              <!-- Product Name & Barcode -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-4">
                  <div class="w-11 h-11 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 border border-slate-100 group-hover:bg-white group-hover:border-blue-200 transition-all">
                    <i class="fas fa-box text-sm"></i>
                  </div>
                  <div class="flex flex-col">
                    <span class="font-black text-slate-800 leading-none truncate max-w-[200px]">{{ item.name }}</span>
                    <span class="text-[10px] font-bold text-slate-400 font-mono mt-1.5 uppercase tracking-tighter">{{ item.barcode || '--' }}</span>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td class="px-4 py-4">
                <span class="text-xs bg-slate-100 px-2.5 py-1 rounded-lg font-bold text-slate-500">{{ item.category_name || 'عام' }}</span>
              </td>

              <!-- Quantity -->
              <td class="px-4 py-4 text-center">
                <span :class="['px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm', getQuantityClass(item)]">
                  {{ item.quantity }} {{ item.unit_name || 'قطعة' }}
                </span>
              </td>

              <!-- Min Quantity -->
              <td class="px-4 py-4 text-center text-sm text-slate-500 font-bold">
                {{ item.min_quantity > 0 ? item.min_quantity : '--' }} {{ item.unit_name || 'قطعة' }}
              </td>

              <!-- Price -->
              <td class="px-4 py-4 text-center font-black text-slate-700">{{ formatCurrency(getPrice(item)) }}</td>

              <!-- Total Value -->
              <td class="px-4 py-4 text-center font-black text-blue-600 text-base">{{ formatCurrency(getPrice(item) * (item.quantity || 0)) }}</td>

              <!-- Status -->
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', getStatusClass(item)]">
                  {{ getStatusText(item) }}
                </span>
              </td>

              <!-- Sale Price -->
              <td class="px-4 py-4 text-center text-slate-600 font-bold text-xs">
                {{ formatCurrency(parseFloat(item.sale_price) || 0) }}
              </td>

              <!-- Profit Margin (from API) -->
              <td class="px-4 py-4 text-center">
                <template v-if="item.profit_margin_percent !== undefined && item.sale_price > 0">
                  <span :class="item.profit_margin_percent >= 0
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                    : 'bg-red-50 text-red-600 border border-red-200'"
                    class="px-2 py-0.5 rounded-lg text-xs font-black">
                    {{ item.profit_margin_percent }}%
                  </span>
                </template>
                <span v-else class="text-slate-300 text-xs">—</span>
              </td>

              <!-- GL Status (from API) - Only show if branch selected -->
              <template v-if="selectedBranch">
                <td class="px-4 py-4 text-center text-xs font-bold">
                  <template v-if="item.gl_status === 'draft'">
                    <span class="bg-orange-100 text-orange-700 px-2 py-1 rounded-lg">غير مُفعَّل</span>
                  </template>
                  <template v-else-if="item.gl_status === 'active'">
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg">مفعَّل</span>
                  </template>
                  <template v-else-if="item.gl_status === 'posted'">
                    <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg">✓ مُرصّد</span>
                  </template>
                  <template v-else>
                    <span class="text-slate-400 text-xs">—</span>
                  </template>
                </td>

                <!-- GL Activate / Opening Balance Button -->
                <td class="px-4 py-4 text-center">
                  <button v-if="item.gl_status === 'draft'"
                          @click="openActivateModal(item)"
                          class="px-3 py-1.5 text-xs font-black bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all active:scale-95">
                    تفعيل
                  </button>
                  <button v-else-if="item.gl_status === 'active'"
                          @click="openOpeningBalanceModal(item)"
                          class="px-3 py-1.5 text-xs font-black bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-600 hover:text-white transition-all active:scale-95">
                    ترصيد
                  </button>
                  <span v-else-if="item.gl_status === 'posted'" class="text-xs text-emerald-600 font-black bg-emerald-50 px-2 py-1 rounded-lg">✓ مُرصّد</span>
                  <span v-else class="text-xs text-slate-400 font-black">-</span>
                </td>
              </template>
              <template v-else>
                <td class="px-4 py-4 text-center">
                  <span class="text-slate-300 text-xs italic">— اختر فرعاً —</span>
                </td>
              </template>

              <!-- Actions -->
              <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="adjustStock(item)"
                    class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-600 hover:text-white transition-all shadow-sm active:scale-95"
                    title="تسوية الكمية"
                  >
                    <i class="fas fa-sliders text-xs"></i>
                  </button>
                  <button
                    @click="openTransferModal(item)"
                    class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm active:scale-95"
                    title="نقل لمستودع آخر"
                  >
                    <i class="fas fa-exchange-alt text-xs"></i>
                  </button>
                  <button
                    @click="viewDetails(item)"
                    class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95"
                    title="تفاصيل وحركات"
                  >
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===== Stock Adjustment Modal ===== -->
    <StockAdjustmentModal
      v-if="showStockModal && selectedItem"
      v-model="showStockModal"
      :branch-id="selectedBranch"
      :product="selectedItem"
      @adjusted="handleStockAdjusted"
      @close="closeStockModal"
    />

    <!-- ===== Stock Transfer Modal (moved from BranchInventory) ===== -->
    <transition name="modal">
      <div v-if="showTransferModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-content-modern animate-modalIn max-w-md" @click.stop>
          <div class="p-8 border-b border-slate-50 bg-slate-50/50">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-exchange-alt text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 text-center leading-none">نقل المخزون بين الفروع</h3>
            <p class="text-slate-400 text-[10px] mt-2 font-bold uppercase tracking-widest text-center">{{ transferItem?.name }}</p>
          </div>

          <form @submit.prevent="submitTransfer" class="p-8 space-y-5">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex justify-between text-[11px] font-black text-slate-500 uppercase">
              <span>المتوفر: {{ transferItem?.quantity || 0 }}</span>
              <span>من: {{ currentBranchName }}</span>
            </div>

            <div class="space-y-2">
              <label class="modal-label">الفرع المستلم</label>
              <select v-model="transferData.toBranchId" class="form-select-modern font-bold" required>
                <option value="">اختر الفرع المستلم...</option>
                <option v-for="b in transferableBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>

            <div class="space-y-2">
              <label class="modal-label">الكمية المنقولة</label>
              <input type="number" min="1" :max="transferItem?.quantity || 0" step="1"
                v-model.number="transferData.quantity"
                class="w-full h-14 rounded-2xl border border-slate-200 bg-slate-50 text-3xl font-black text-center text-emerald-600 focus:bg-white focus:ring-4 focus:ring-emerald-50 focus:border-emerald-400 transition-all outline-none"
                placeholder="0" required />
            </div>

            <div class="space-y-2">
              <label class="modal-label">ملاحظات النقل</label>
              <textarea rows="2" v-model="transferData.notes"
                class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white focus:ring-4 focus:ring-emerald-50 focus:border-emerald-400 transition-all outline-none resize-none"
                placeholder="سبب النقل (اختياري)..."></textarea>
            </div>

            <div class="flex gap-4 pt-2">
              <button type="button" @click="showTransferModal = false" :disabled="isTransferring"
                class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all disabled:opacity-50">إلغاء</button>
              <button type="submit" :disabled="isTransferring"
                class="flex-[2] py-4 rounded-2xl bg-emerald-600 text-white font-black shadow-xl shadow-emerald-100 hover:bg-emerald-700 transition-all active:scale-95 flex items-center justify-center gap-3 disabled:opacity-70">
                <BaseSpinner v-if="isTransferring" :size="16" color="#fff" margin="0" />
                <i v-else class="fas fa-exchange-alt"></i>
                <span>{{ isTransferring ? 'جاري النقل...' : 'تأكيد النقل' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- ===== GL Activate Modal (moved from ProductManagement) ===== -->
    <transition name="modal">
      <div v-if="showActivateModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-content-modern animate-modalIn max-w-md" @click.stop>
          <div class="p-8">
            <h2 class="text-2xl font-black mb-4">تفعيل المنتج في الفرع</h2>
            <p class="text-slate-500 mb-6">
              هل تريد تفعيل <strong>{{ selectedProductForActivation?.name }}</strong>
              في الفرع <strong>{{ branches.find(b => String(b.id) === String(selectedBranch))?.name }}</strong>؟
            </p>
            <div class="flex gap-4">
              <button @click="showActivateModal = false" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-all">إلغاء</button>
              <button @click="activateProduct(selectedProductForActivation.id)" class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl font-black hover:bg-blue-700 transition-all active:scale-95">تفعيل</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ===== Opening Balance Modal (moved from ProductManagement) ===== -->
    <transition name="modal">
      <div v-if="showOpeningBalanceModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-content-modern animate-modalIn max-w-md" @click.stop>
          <div class="p-8">
            <h2 class="text-2xl font-black mb-6">إدخال الرصيد الافتتاحي</h2>
            <form @submit.prevent="handleOpeningBalanceSubmit">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-black text-slate-600 mb-2">الكمية</label>
                  <input v-model.number="obQuantity" type="number" min="0" step="1"
                    class="w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm" required />
                </div>
                <div>
                  <label class="block text-sm font-black text-slate-600 mb-2">سعر التكلفة (الوحدة)</label>
                  <input v-model.number="obUnitCost" type="number" min="0" step="0.01"
                    class="w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm" required />
                </div>
                <p class="text-sm text-slate-500 bg-slate-50 p-3 rounded-xl">
                  الإجمالي: <strong class="text-slate-800">{{ (obQuantity * obUnitCost).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</strong>
                </p>
              </div>
              <div class="flex gap-4 mt-8">
                <button type="button" @click="showOpeningBalanceModal = false" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-all">إلغاء</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-black hover:bg-emerald-700 transition-all active:scale-95">ترصيد</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </transition>

    <!-- ===== Product Details Modal ===== -->
    <transition name="modal">
      <div v-if="showDetailsModal" class="modal-overlay" aria-labelledby="details-modal-title" role="dialog" aria-modal="true">
        <div class="modal-content-modern max-w-4xl animate-modalIn" @click.stop>
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-xl font-black text-slate-800 leading-none" id="details-modal-title">بطاقة تفاصيل الصنف</h3>
            <button @click="closeDetailsModal" class="text-slate-400 hover:text-rose-500 transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <div class="p-8 overflow-y-auto custom-scroll max-h-[80vh]">
            <template v-if="isLoadingDetails">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
              </tr>
            </template>

            <template v-else-if="productDetails">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div class="bg-slate-900 p-8 rounded-[2rem] text-white shadow-2xl relative overflow-hidden group">
                  <div class="absolute top-0 left-0 w-32 h-32 bg-white/5 rounded-full -translate-x-16 -translate-y-16 group-hover:scale-110 transition-transform"></div>
                  <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-4">بيانات المنتج الأساسية</p>
                  <h4 class="text-2xl font-black mb-1">{{ productDetails.name || 'غير متوفر' }}</h4>
                  <p class="text-xs font-bold text-white/40 font-mono mb-6">{{ productDetails.barcode || '—' }}</p>
                  <div class="grid grid-cols-2 gap-6 pt-6 border-t border-white/5">
                    <div>
                      <p class="text-[9px] font-black text-white/30 uppercase mb-1">الكمية المتوفرة</p>
                      <p class="text-xl font-black">{{ productDetails.quantity ?? 0 }} {{ productDetails.unit_name || 'قطعة' }}</p>
                    </div>
                    <div>
                      <p class="text-[9px] font-black text-white/30 uppercase mb-1">القيمة المالية</p>
                      <p class="text-xl font-black text-emerald-400">{{ formatCurrency(getPrice(productDetails) * (productDetails.quantity || 0)) }}</p>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">الفئة</p>
                    <p class="font-black text-slate-700">{{ productDetails.category_name || 'غير مصنف' }}</p>
                  </div>
                  <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">سعر البيع</p>
                    <p class="font-black text-blue-600">{{ formatCurrency(productDetails.sale_price || 0) }}</p>
                  </div>
                  <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">حالة المخزون</p>
                    <span :class="['status-badge mt-1 w-fit', getStatusClass(productDetails)]">{{ getStatusText(productDetails) }}</span>
                  </div>
                  <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">حد التنبيه</p>
                    <p class="font-black text-slate-700">{{ productDetails.min_quantity > 0 ? productDetails.min_quantity : '--' }} {{ productDetails.unit_name || 'قطعة' }}</p>
                  </div>
                  <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">كود المنتج (SKU)</p>
                    <p class="font-black text-indigo-600 font-mono">{{ productDetails.product_code || '--' }}</p>
                  </div>
                  <div v-if="productDetails.description" class="col-span-2 p-5 rounded-2xl bg-slate-50 border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">الوصف</p>
                    <p class="font-bold text-slate-700 text-sm">{{ productDetails.description }}</p>
                  </div>
                </div>
              </div>

              <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-4 px-2 flex items-center gap-2">
                <i class="fas fa-history text-indigo-500"></i> سجل آخر الحركات المخزنية
              </h4>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                      <th class="px-6 py-4">التاريخ</th>
                      <th class="px-4 py-4">نوع الحركة</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-6 py-4">البيان / الملاحظات</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50">
                    <tr v-if="!productDetails.recentHistory?.length">
                      <td colspan="4" class="py-12 text-center text-slate-300 font-bold uppercase tracking-widest">لا توجد حركات سابقة مسجلة</td>
                    </tr>
                    <tr v-for="(history, index) in productDetails.recentHistory" :key="index" class="hover:bg-slate-50/50 transition-all font-bold">
                      <td class="px-6 py-4 text-slate-400 font-mono">{{ formatDate(history.movement_date || history.created_at) }}</td>
                      <td class="px-4 py-4">
                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[9px] uppercase tracking-widest">{{ getMovementTypeText(history.movement_type || history.type) }}</span>
                      </td>
                      <td class="px-4 py-4 text-center font-black text-slate-800">{{ history.quantity }}</td>
                      <td class="px-6 py-4 text-slate-500 italic">{{ translateMovementNote(history.notes) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>

          <div class="px-8 py-6 bg-slate-50/80 border-t border-slate-100 flex justify-end gap-3">
            <button type="button" @click="closeDetailsModal"
              class="px-8 py-3 rounded-xl text-xs font-black text-slate-500 hover:bg-white transition-all border border-slate-200">إغلاق</button>
            <button type="button" @click="adjustStock(productDetails)"
              class="px-8 py-3 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
              <i class="fas fa-edit"></i> تعديل الكمية
            </button>
          </div>
        </div>
      </div>
    </transition>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useLoader } from '../../composables/useLoader';
import { useCompanyCurrency } from '../../composables/useCompanyCurrency';
import { useToast } from '@/composables/useToast';
import getLocalDateISO from '@/utils/date';
import BaseSpinner from '../../components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import AlertService from '@/services/AlertService';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useProductStore } from '@/stores/product/productStore';
import { useInventoryStore } from '@/stores/inventory/inventoryStore';
import { useUserBranchPreference } from '@/composables/useUserBranchPreference';
import BranchIndicatorBreadcrumb from '@/components/BranchIndicatorBreadcrumb.vue';
import StockAdjustmentModal from '@/components/branch/StockAdjustmentModal.vue';

// ─── State ───────────────────────────────────────────────────────────────────
const branchStore = useBranchStore();
const productStore = useProductStore();
const inventoryStore = useInventoryStore();

const route = useRoute();
const { showLoader, hideLoader } = useLoader();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { showToast } = useToast();
const { ensureLoaded: ensureExemptionLoaded, isExempt } = useSessionExemption();
const authStore = useAuthStore();
const { branches, initializePreferences } = useUserBranchPreference('selectedInventoryBranch');

const selectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
});

const currentBranchName = computed(() => {
  const branch = branches.value.find(b => b.id === selectedBranch.value);
  return branch?.name || 'All Branches';
});

const inventory = ref([]);
const stats = ref({
  totalProducts: 0,
  totalValue: 0,
  lowStock: 0,
  aboutToFinish: 0,
});
const products = ref([]);

const search = ref('');
const filter = ref('all');
const sort = ref('name');

// ─── Stock Adjustment State ────────────────────────────────────────────────────────────────
const showStockModal = ref(false);
const showDetailsModal = ref(false);
const selectedItem = ref(null);
const productDetails = ref(null);
const isLoadingDetails = ref(false);
const showPickerPanel = ref(false);
const pickerSearch = ref('');

// ─── GL Integration State (moved from ProductManagement) ─────────────────────
const glProductStatuses = ref({});
const showActivateModal = ref(false);
const showOpeningBalanceModal = ref(false);
const selectedProductForActivation = ref(null);
const obQuantity = ref(0);
const obUnitCost = ref(0);

// ─── Transfer State (moved from BranchInventory) ─────────────────────────────
const showTransferModal = ref(false);
const transferItem = ref(null);
const isTransferring = ref(false);
const transferData = ref({ toBranchId: '', quantity: 0, notes: '' });

const transferableBranches = computed(() =>
  (branches.value || []).filter(b => String(b.id) !== String(selectedBranch.value))
);

// ─── Picker Computed ────────────────────────────────────────────────────────────
const pickerItems = computed(() => {
  const q = pickerSearch.value.trim().toLowerCase();
  const src = inventory.value.length ? inventory.value : [];
  if (!q) return src.slice(0, 50);
  return src.filter(i =>
    i.name?.toLowerCase().includes(q) ||
    i.barcode?.toLowerCase().includes(q) ||
    i.product_code?.toLowerCase().includes(q)
  ).slice(0, 50);
});

const openAdjustFromPicker = (item) => {
  showPickerPanel.value = false;
  pickerSearch.value = '';
  adjustStock(item);
};

// ─── Computed ─────────────────────────────────────────────────────────────────
const filteredInventory = computed(() => {
  let filtered = inventory.value;
  if (search.value) {
    const term = search.value.toLowerCase();
    filtered = filtered.filter(
      item =>
        item.name.toLowerCase().includes(term) ||
        (item.barcode && item.barcode.toLowerCase().includes(term))
    );
  }
  if (filter.value === 'low') {
    // Only include items where min_quantity is defined and quantity <= min_quantity
    filtered = filtered.filter(item => 
      item.min_quantity > 0 && item.quantity <= item.min_quantity
    );
  } else if (filter.value === 'about-to-finish') {
    // Only include items where min_quantity is defined
    filtered = filtered.filter(item => 
      item.min_quantity > 0 && 
      item.quantity > 0 && 
      item.quantity <= item.min_quantity * 1.5
    );
  } else if (filter.value === 'out') {
    filtered = filtered.filter(item => item.quantity === 0);
  }
  filtered = filtered.slice().sort((a, b) => {
    if (sort.value === 'name') return a.name.localeCompare(b.name);
    if (sort.value === 'quantity') return b.quantity - a.quantity;
    if (sort.value === 'value') return getPrice(b) * b.quantity - getPrice(a) * a.quantity;
    return 0;
  });
  return filtered;
});

const totalInventoryValue = computed(() =>
  inventory.value.reduce((sum, item) => sum + getPrice(item) * (parseInt(item.quantity) || 0), 0)
);

// ─── Summary Loader ───────────────────────────────────────────────────
const loadSummary = async (force = false) => {
  const res = await inventoryStore.fetchInventorySummary({
    branchId: selectedBranch.value || null,
    force
  });
  if (res.status === 'success' && res.data) {
    stats.value = {
      totalProducts: res.data.total_products  ?? 0,
      totalValue:    res.data.total_value     ?? 0,
      lowStock:      res.data.low_stock       ?? 0,
      aboutToFinish: res.data.about_to_finish ?? 0,
    };
  }
};

// ─── Branch Change Handler ───────────────────────────────────────────────────
const handleBranchChange = () => {
  if (selectedBranch.value) {
    window.localStorage.setItem('selectedInventoryBranch', selectedBranch.value);
  } else {
    window.localStorage.removeItem('selectedInventoryBranch');
  }
  Promise.all([
    loadInventory(true),
    loadSummary(true),
  ]);
};

// ─── Data Loading ─────────────────────────────────────────────────────────────
const loadInventory = async (force = false) => {
  try {
    const params = { force };
    if (!isExempt.value) {
      const wid = authStore?.user?.branch_id;
      if (!wid) { inventory.value = []; products.value = []; showToast('لم يتم تعيين مخزن', 'warning'); return; }
      params.branchId = String(wid);
    } else if (selectedBranch.value) {
      params.branchId = String(selectedBranch.value);
    }

    const response = await productStore.fetchProducts(params);
    if (response.status === 'success') {
      const data = response.data?.items || response.data || [];
      
      // Map new API response fields to component properties
      // New API returns: current_quantity, inventory_status, gl_status, profit_margin_percent, profit_markup_percent
      products.value  = data.map(p => ({
        ...p,
        id: p.id,
        name: p.name,
        barcode: p.barcode,
        category_name: p.category_name,
        category_id: p.category_id,
        purchase_price: parseFloat(p.purchase_price) || 0,
        sale_price: parseFloat(p.sale_price) || 0,
        quantity: parseInt(p.current_quantity) || 0,
        min_quantity: parseFloat(p.min_quantity) || 0,  // ← قيمة محددة: null → 0
        unit_name: p.unit_name || 'قطعة',
        unit_id: p.unit_id,
        inventory_status: p.inventory_status, // from API: in_stock, low_stock, out_of_stock, N/A
        gl_status: p.gl_status, // from API: posted, draft
        active: p.active,  // ← توحيد: is_active → active
        profit_margin_percent: parseFloat(p.profit_margin_percent) || 0,
        profit_markup_percent: parseFloat(p.profit_markup_percent) || 0,
        total_inventory_value: parseFloat(p.total_inventory_value) || 0,
        product_type: p.product_type || 'stock'
      }));
      
      inventory.value = [...products.value];
    }
  } catch (error) {
    console.error('Error loading inventory:', error);
    await AlertService.error('حدث خطأ أثناء تحميل بيانات المخزون: ' + (error.response?.data?.message || error.message), 'خطأ في التحميل');
  }
};

const loadProducts = () => loadInventory();

// ─── Price Logic (UPDATED to use API purchase_price) ──────────────────────────────────────────────────
const getPrice = item => {
  const pid = item.product_id || item.id;
  
  // Primary source: API purchase_price (most reliable)
  if (item.purchase_price > 0) return parseFloat(item.purchase_price);
  
  // Fallback: find in products array cache
  const product = products.value.find(p => p.id === pid);
  if (product?.purchase_price > 0) return parseFloat(product.purchase_price);
  
  // Last resort: use sale_price if no purchase price available
  if (product?.sale_price > 0) return parseFloat(product.sale_price);
  if (item.sale_price > 0) return parseFloat(item.sale_price);
  
  return 0;
};

// ─── Stock Adjustment Handlers ──────────────────────────────────────────────────────────────────
const adjustStock = item => {
  selectedItem.value = item;
  showStockModal.value = true;
};

const closeStockModal = () => {
  showStockModal.value = false;
  selectedItem.value = null;
};

const handleStockAdjusted = async () => {
  await loadInventory(true);  // ← force refresh to show updated quantities
  updateStats();
  // ✅ تحديث cache المنتجات في POS
  productStore.invalidateCache();
};

// ─── Transfer Handlers (moved from BranchInventory) ──────────────────────────
const openTransferModal = (item) => {
  transferItem.value = item;
  transferData.value = { toBranchId: '', quantity: 0, notes: '' };
  showTransferModal.value = true;
};

const submitTransfer = async () => {
  if (!selectedBranch.value) {
    showToast('يرجى اختيار فرع المصدر أولاً', 'warning');
    return;
  }
  if (!transferData.value.toBranchId || !transferData.value.quantity) {
    showToast('يرجى اختيار الفرع وإدخال الكمية', 'warning');
    return;
  }
  if (transferData.value.quantity > (transferItem.value?.quantity || 0)) {
    showToast('الكمية المطلوبة تتجاوز المتوفر في المخزون', 'warning');
    return;
  }
  isTransferring.value = true;
  try {
    const result = await inventoryStore.transferStock({
      product_id: transferItem.value.product_id || transferItem.value.id,
      from_branch_id: selectedBranch.value,
      to_branch_id: transferData.value.toBranchId,
      quantity: transferData.value.quantity,
      notes: transferData.value.notes,
    });
    if (result.status !== 'success') throw new Error(result.message || 'فشل نقل المخزون');
    showToast('تم نقل المخزون بنجاح', 'success');
    showTransferModal.value = false;
    transferItem.value = null;
    transferData.value = { toBranchId: '', quantity: 0, notes: '' };
    await loadInventory(true);  // ← force refresh to show updated quantities
    updateStats();
    // ✅ تحديث cache المنتجات في POS
    productStore.invalidateCache();
  } catch (error) {
    showToast(error.response?.data?.message || 'فشل نقل المخزون', 'error');
  } finally {
    isTransferring.value = false;
  }
};

// ─── GL Integration (DEPRECATED - kept for future reference) ─────────────────
// NOTE: glProductStatuses, getGLStatusKey, getProductCostBasis, and loadProductGLStatuses
// are no longer actively used. GL status is now provided by the API directly via item.gl_status.
// These functions are kept for reference only in case GL-specific logic is needed in the future.

const getGLStatusKey = (productId) => {
  // DEPRECATED: Use item.gl_status from API instead
  return 'draft'; // placeholder
};

const getProductCostBasis = (productId) => {
  // DEPRECATED: Use item.purchase_price from API instead
  return 0; // placeholder
};

const loadProductGLStatuses = async (branchId, force = false) => {
  // DEPRECATED: GL status now comes from API in loadInventory()
  // This function is kept for reference but is no longer called
  if (!branchId) return;
  console.debug('loadProductGLStatuses called but deprecated - GL status comes from API now');
};

const openActivateModal = (item) => {
  // Ensure a branch is selected
  if (!selectedBranch.value) {
    showToast('يرجى اختيار فرع أولاً', 'warning');
    return;
  }
  selectedProductForActivation.value = item;
  showActivateModal.value = true;
};

const openOpeningBalanceModal = (item) => {
  // Ensure a branch is selected
  if (!selectedBranch.value) {
    showToast('يرجى اختيار فرع أولاً', 'warning');
    return;
  }
  selectedProductForActivation.value = item;
  obQuantity.value = 0;
  obUnitCost.value = 0;
  showOpeningBalanceModal.value = true;
};

const activateProduct = async (productId) => {
  try {
    // Ensure a branch is selected
    if (!selectedBranch.value) {
      showToast('يرجى اختيار فرع أولاً', 'warning');
      return;
    }
    
    const response = await productStore.activateProductInBranch(productId, Number(selectedBranch.value));
    if (response.status === 'success') {
      showToast('تم تفعيل المنتج في الفرع بنجاح', 'success');
      showActivateModal.value = false;
      // Reload inventory to get updated gl_status from API
      await loadInventory(true);
      updateStats();
      // ✅ تحديث cache المنتجات في POS
      productStore.invalidateCache();
    } else {
      showToast(response.message || 'فشل التفعيل', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.message || e.message || 'فشل التفعيل', 'error');
  }
};

const handleOpeningBalanceSubmit = async () => {
  if (!obQuantity.value || !obUnitCost.value) {
    showToast('يرجى إدخال الكمية والسعر', 'warning');
    return;
  }
  if (!selectedBranch.value) {
    showToast('يرجى اختيار فرع أولاً', 'warning');
    return;
  }

  try {
    // ✅ مباشرة: أرسل product_id + branch_id، الـ backend سيبحث عن mapping
    const response = await productStore.postOpeningBalance({
      product_id: selectedProductForActivation.value.id,
      branch_id: selectedBranch.value,
      quantity: obQuantity.value,
      unit_cost: obUnitCost.value,
      entry_date: getLocalDateISO(),
    });

    if (response.status === 'success') {
      showToast('تم ترصيد الرصيد الافتتاحي وإنشاء قيود محاسبية بنجاح', 'success');
      showOpeningBalanceModal.value = false;
      obQuantity.value = 0;
      obUnitCost.value = 0;
      await loadInventory(true);
      updateStats();
      // ✅ تحديث cache المنتجات في POS
      productStore.invalidateCache();
    } else {
      showToast(response.message || 'فشل الترصيد', 'error');
    }
  } catch (e) {
    console.error('Opening balance error:', e);
    showToast(e.response?.data?.message || e.message || 'فشل الترصيد', 'error');
  }
};

// ─── Product Details (PRESERVED) ─────────────────────────────────────────────
const viewDetails = async item => {
  try {
    isLoadingDetails.value = true;
    showDetailsModal.value = true;
    productDetails.value = { ...item };
    const data = await productStore.getProductById(item.id);
    if (data) {
      // Map nested response structure to flat template properties
      productDetails.value = {
        ...productDetails.value,
        ...data,
        // Flatten nested objects for template compatibility
        sale_price: data.pricing?.sale_price ?? data.sale_price,
        purchase_price: data.pricing?.purchase_price ?? data.purchase_price,
        min_sale_price: data.pricing?.min_sale_price ?? data.min_sale_price,
        quantity: data.inventory?.current_quantity ?? data.quantity,
        unit_name: data.inventory?.unit_name ?? data.unit_name,
        unit_id: data.inventory?.unit_id ?? data.unit_id,
        min_quantity: data.inventory?.min_quantity ?? data.min_quantity,
        max_quantity: data.inventory?.max_quantity ?? data.max_quantity,
        inventory_status: data.inventory?.inventory_status ?? data.inventory_status,
        category_name: data.category?.name ?? data.category_name,
        category_id: data.category?.id ?? data.category_id,
      };
    }
    const historyRes = await inventoryStore.getProductHistory(item.id, 5);
    productDetails.value.recentHistory = historyRes.data || [];
  } catch (error) {
    console.error('Error loading product details:', error);
    await AlertService.error('حدث خطأ أثناء تحميل تفاصيل المنتج', 'خطأ');
  } finally {
    isLoadingDetails.value = false;
  }
};

const closeDetailsModal = () => { showDetailsModal.value = false; productDetails.value = null; };

// ─── Stats Update (PRESERVED) ─────────────────────────────────────────────────
const updateStats = () => {
  loadSummary(true);
};

// ─── UI Helpers (UPDATED for new API status) ──────────────────────────────────────────────────
// Map inventory_status from API to UI classes
const getStatusClass = item => {
  const status = item.inventory_status || 'out_of_stock';
  return {
    'in_stock': 'bg-emerald-100 text-emerald-700 border-emerald-200',      // 🟢 Green
    'low_stock': 'bg-amber-100 text-amber-700 border-amber-200',            // 🟠 Orange
    'out_of_stock': 'bg-rose-100 text-rose-700 border-rose-200',            // 🔴 Red
    'N/A': 'bg-slate-100 text-slate-700 border-slate-200'                   // ⚪ Gray (service)
  }[status] || 'bg-slate-100 text-slate-700 border-slate-200';
};

const getStatusText = item => {
  const status = item.inventory_status || 'out_of_stock';
  return {
    'in_stock': 'متوفر بالمخزن',
    'low_stock': 'مخزون منخفض',
    'out_of_stock': 'نافذ من المخزن',
    'N/A': 'خدمة (بدون مخزون)'
  }[status] || 'غير معروف';
};

const getQuantityClass = item => {
  const status = item.inventory_status || 'out_of_stock';
  return {
    'in_stock': 'bg-blue-50 text-blue-600',       // 🟢 Green
    'low_stock': 'bg-amber-50 text-amber-600',     // 🟠 Orange
    'out_of_stock': 'bg-rose-50 text-rose-600',    // 🔴 Red
    'N/A': 'bg-slate-50 text-slate-600'            // ⚪ Gray
  }[status] || 'bg-slate-50 text-slate-600';
};

const getAdjustmentTypeText = type =>
  ({ add: 'إضافة وارد', subtract: 'خصم / سحب', set: 'تعيين يدوي' }[type] || type);

const getMovementTypeText = (type) => ({
  out:             'بيع',
  in:              'وارد',
  initial_stock:   'إدخال مخزون أولي',
  sale:            'بيع',
  sales:           'بيع',
  purchase:        'مشتريات',
  opening_balance:        'رصيد افتتاحي',
  opening_balance_manual: 'رصيد افتتاحي يدوي',
  opening_balance_bulk:   'رصيد افتتاحي دفعي',
  adjustment:      'تسوية مخزون',
  adjustment_in:   'تسوية وارد',
  adjustment_out:  'تسوية صادر',
  add:             'إضافة وارد',
  subtract:        'خصم / سحب',
  set:             'تعيين يدوي',
  transfer_in:     'تحويل وارد',
  transfer_out:    'تحويل صادر',
  transfer:        'تحويل بين فروع',
  return:          'مرتجع',
  return_in:       'مرتجع وارد',
  return_out:      'مرتجع صادر',
  write_off:       'إتلاف / شطب',
  correction:      'تصحيح',
}[type] || type || '—');

const NOTES_AR = {
  'Initial stock on product creation': 'رصيد افتتاحي عند إنشاء المنتج',
  'Initial stock':                     'رصيد افتتاحي',
  'Opening balance':                   'رصيد افتتاحي',
  'Stock adjustment':                  'تسوية مخزون',
  'Transfer in':                       'تحويل وارد',
  'Transfer out':                      'تحويل صادر',
  'Sale':                              'بيع',
  'Purchase':                          'مشتريات',
  'Return':                            'مرتجع',
};
const NOTES_PREFIX_AR = [
  { prefix: 'Opening balance commit @', ar: 'رصيد افتتاحي' },
  { prefix: 'Opening balance',          ar: 'رصيد افتتاحي' },
  { prefix: 'Initial stock',            ar: 'رصيد افتتاحي عند إنشاء المنتج' },
  { prefix: 'Stock adjustment',         ar: 'تسوية مخزون' },
  { prefix: 'Transfer in',              ar: 'تحويل وارد' },
  { prefix: 'Transfer out',             ar: 'تحويل صادر' },
];
const translateMovementNote = (note) => {
  if (!note) return '—';
  const t = note.trim();
  if (NOTES_AR[t]) return NOTES_AR[t];
  const prefix = NOTES_PREFIX_AR.find(p => t.startsWith(p.prefix));
  return prefix ? prefix.ar : note;
};

const formatCurrency = v => formatCurrencyLocale(v, 2);

const formatDate = date => { if (!date) return '—'; return new Date(date).toLocaleDateString('ar-SA'); };

// ─── Lifecycle ────────────────────────────────────────────────────────────────
const handleOpeningBalancePosted = async (event) => {
  console.log('📡 Received openingBalancePosted event:', event.detail);
  try {
    showToast('🔄 جاري تحديث بيانات المخزون...', 'info');
    
    // Add a small delay to ensure backend has processed the data
    await new Promise(resolve => setTimeout(resolve, 300));
    
    await loadInventory(true);
    await loadSummary(true);
    
    showToast(
      `✅ ${event.detail?.message || 'تم تحديث بيانات المخزون بنجاح'} (${event.detail?.itemCount || ''} منتج)`,
      'success'
    );
    
    console.log('✅ Inventory refreshed successfully');
  } catch (error) {
    console.error('❌ Error refreshing inventory:', error);
    showToast('خطأ في تحديث البيانات: ' + error.message, 'error');
  }
};

// Check if there's a pending update from sessionStorage (for fallback)
const checkPendingOpeningBalance = async () => {
  try {
    const pending = sessionStorage.getItem('lastOpeningBalancePosted');
    if (pending) {
      const data = JSON.parse(pending);
      const timeDiff = Date.now() - new Date(data.timestamp).getTime();
      
      // If the update was recent (last 10 seconds), refresh the data
      if (timeDiff < 10000) {
        console.log('📡 Found pending opening balance update from sessionStorage');
        await handleOpeningBalancePosted({
          detail: {
            ...data,
            message: 'تم ترصيد الرصيد الافتتاحي (تحديث من الجلسة السابقة)'
          }
        });
        sessionStorage.removeItem('lastOpeningBalancePosted');
      }
    }
  } catch (error) {
    console.error('Error checking pending update:', error);
  }
};

onMounted(async () => {
  showLoader(true);
  try {
    await fetchSettings();
    await ensureExemptionLoaded();
    await initializePreferences();
    await Promise.all([
      loadInventory(),
      loadSummary(),
    ]);
    
    // Check for pending opening balance updates from previous session
    await checkPendingOpeningBalance();

    // ─── Listen for opening balance posted events from OpeningBalance.vue ─────
    window.addEventListener('openingBalancePosted', handleOpeningBalancePosted);
    console.log('✅ Listener registered for openingBalancePosted events');

    // ─── Auto-open modal from query params (من BranchInventory router-links) ───
    const action    = route.query.action;
    const productId = route.query.product_id ? Number(route.query.product_id) : null;
    const branchId  = route.query.branch_id;
    if (action && productId) {
      if (branchId && String(branchId) !== String(selectedBranch.value)) {
        branchStore.setSelectedBranch(branchId);
        await loadInventory();
      }
      const item = inventory.value.find(
        i => Number(i.product_id ?? i.id) === productId
      );
      if (item) {
        if (action === 'adjust')   adjustStock(item);
        else if (action === 'transfer') openTransferModal(item);
      }
    }
  } finally {
    hideLoader();
  }
});

onUnmounted(() => {
  // Clean up the event listener
  window.removeEventListener('openingBalancePosted', handleOpeningBalancePosted);
});
</script>

<style scoped>
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }
.form-input-modern,
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm border; }
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>