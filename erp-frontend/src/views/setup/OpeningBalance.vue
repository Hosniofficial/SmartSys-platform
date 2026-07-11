<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-boxes-packing text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">الرصيد الافتتاحي للمخزون</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تصفير أو إعداد الأرصدة الأولية للمنتجات في كافة الفروع</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="preview" :disabled="loading" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-magnifying-glass"></i> تحقق / Preview
        </button>
        <button @click="commit" :disabled="loading || items.length === 0" class="px-8 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-black shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-check-double"></i> ترحيل السجل نهائياً
        </button>
      </div>
    </div>

    <!-- Quick Options & Toggles Card -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 flex flex-wrap items-center gap-6">
      <div class="flex flex-wrap items-center gap-6">
        <label class="toggle-option group" title="عند التفعيل سيتم إنشاء قيد محاسبي للرصيد الافتتاحي تلقائياً">
          <input type="checkbox" v-model="postAccounting" class="sr-only peer" />
          <div class="toggle-box peer-checked:bg-blue-600 peer-checked:border-blue-600"></div>
          <span class="text-xs font-black text-slate-500 group-hover:text-slate-800 transition-colors uppercase tracking-tight">إنشاء قيد محاسبي تلقائي</span>
        </label>

        <label class="toggle-option group">
          <input type="checkbox" v-model="setPurchasePriceIfZero" class="sr-only peer" />
          <div class="toggle-box peer-checked:bg-indigo-600 peer-checked:border-indigo-600"></div>
          <span class="text-xs font-black text-slate-500 group-hover:text-slate-800 transition-colors uppercase tracking-tight">تعيين سعر شراء المنتج إذا كان 0</span>
        </label>

        <label class="toggle-option group" title="لن يتم تعديل المخزون، فقط إنشاء مشتريات افتتاحية لاستخدامها في WAC/COGS">
          <input type="checkbox" v-model="purchasesOnly" class="sr-only peer" />
          <div class="toggle-box peer-checked:bg-amber-600 peer-checked:border-amber-600"></div>
          <span class="text-xs font-black text-slate-500 group-hover:text-slate-800 transition-colors uppercase tracking-tight">إنشاء مشتريات فقط (بدون تحريك المخزون)</span>
        </label>
      </div>

      <div class="h-8 w-px bg-slate-100 mx-2 hidden lg:block"></div>

      <div class="flex items-center gap-2 mr-auto">
        <button @click="addRow" class="btn-action bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white" title="إضافة صف">
          <i class="fas fa-plus"></i>
        </button>
        <button @click="clearRows" class="btn-action bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white" title="تفريغ الجدول">
          <i class="fas fa-trash-can"></i>
        </button>
        <button @click="downloadTemplate('basic')" class="btn-action bg-slate-50 text-slate-500 hover:bg-slate-600 hover:text-white" title="تحميل قالب CSV">
          <i class="fas fa-download"></i>
        </button>
        <label class="btn-action bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white cursor-pointer" title="استيراد CSV سريع">
          <i class="fas fa-file-csv"></i>
          <input type="file" class="hidden" accept=".csv" @change="onCsvSelected" />
        </label>
      </div>
    </div>

    <!-- Inventory Totals KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
      <div class="bg-slate-900 rounded-[2rem] p-6 text-white shadow-xl shadow-slate-200 border border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-xl text-blue-400">
            <i class="fas fa-cubes"></i>
          </div>
          <div>
            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">الكمية الإجمالية للسجل</p>
            <p class="text-2xl font-black font-mono tracking-tighter leading-none">{{ formatNumber(totalQuantity) }}</p>
          </div>
        </div>
        <div class="text-left">
          <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">التكلفة الإجمالية</p>
          <p class="text-2xl font-black text-emerald-400 font-mono tracking-tighter leading-none">{{ formatMoney(totalCost) }}</p>
        </div>
      </div>
      <div v-if="lastSaveTime" class="bg-white rounded-[2rem] p-6 border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500">
          <i class="fas fa-cloud-arrow-up"></i>
        </div>
        <div>
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">آخر حفظ تلقائي</p>
          <p class="text-sm font-black text-slate-700">{{ lastSaveTime.toLocaleTimeString('ar-EG') }}</p>
        </div>
      </div>
    </div>

    <!-- Main Dynamic Data Entry Table -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden mb-10">
      <div class="overflow-x-auto custom-scroll" style="max-height: 55vh;">
        <table class="w-full text-right text-sm">
          <thead class="sticky top-0 z-20">
            <tr class="bg-slate-50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter shadow-sm">
              <th class="px-6 py-5">المستودع / الفرع</th>
              <th class="px-4 py-5 w-72">المنتج / الباركود</th>
              <th class="px-4 py-5">الوحدة</th>
              <th class="px-4 py-5 text-center">الكمية</th>
              <th class="px-4 py-5 text-center">التكلفة (الوحدة)</th>
              <th class="px-4 py-5 text-center">إجمالي التكلفة</th>
              <th class="px-4 py-5">ملاحظات الصف</th>
              <th class="px-6 py-5 w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold">
            <tr v-if="items.length === 0">
              <td colspan="8" class="py-16 text-center">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-boxes-stacked text-5xl mb-3"></i>
                  <p class="font-black text-sm uppercase">لا توجد صفوف — اضغط + لإضافة صف</p>
                </div>
              </td>
            </tr>
            <tr v-for="(row, idx) in items" :key="idx" class="hover:bg-blue-50/30 transition-all group">
              <!-- Branch -->
              <td class="px-6 py-4">
                <div class="relative dropdown-container group/field">
                  <input type="text" v-model="row.branch_search"
                         @input="filterbranchs(idx)"
                         @focus="showbranchDropdown(idx)"
                         class="grid-input h-10 w-44 pr-9" placeholder="بحث المخزن..." />
                  <i class="fas fa-warehouse absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/field:text-blue-500 transition-colors text-[10px]"></i>
                  <div v-if="row.showbranchList" class="dropdown-list-modern">
                    <div v-if="!filteredbranchs(idx).length" class="px-5 py-3 text-[10px] text-slate-400 font-black uppercase">لا نتائج</div>
                    <div v-for="branch in filteredbranchs(idx)" :key="branch.id"
                         @click="selectbranch(idx, branch)"
                         class="dropdown-item-modern">
                      <span class="font-black text-slate-700 leading-none">{{ branch.name }}</span>
                      <span class="text-[10px] text-slate-400 font-mono">{{ branch.code || 'ID:' + branch.id }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <!-- Product -->
              <td class="px-4 py-4">
                <div class="relative dropdown-container group/field">
                  <input type="text" v-model="row.product_search"
                         @input="filterProducts(idx)"
                         @focus="showProductDropdown(idx)"
                         class="grid-input h-10 w-full pr-9" placeholder="اسم المنتج أو الكود..." />
                  <i class="fas fa-box absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/field:text-blue-500 transition-colors text-[10px]"></i>
                  <div v-if="row.showProductList" class="dropdown-list-modern">
                    <div v-if="!filteredProducts(idx).length" class="px-5 py-3 text-[10px] text-slate-400 font-black uppercase">لا نتائج</div>
                    <div v-for="product in filteredProducts(idx)" :key="product.id"
                         @click="selectProduct(idx, product)"
                         class="dropdown-item-modern">
                      <span class="font-black text-slate-700 leading-none truncate max-w-[180px]">{{ product.name }}</span>
                      <span class="text-[10px] text-slate-400 font-mono">{{ product.code || product.barcode }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <!-- Unit -->
              <td class="px-4 py-4">
                <div class="relative dropdown-container group/field">
                  <input type="text" v-model="row.unit_search"
                         @input="filterUnits(idx)"
                         @focus="showUnitDropdown(idx)"
                         :disabled="!row.product_id"
                         class="grid-input h-10 w-28 pr-8 disabled:opacity-30" placeholder="الوحدة" />
                  <i class="fas fa-tag absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/field:text-blue-500 transition-colors text-[9px]"></i>
                  <div v-if="row.showUnitList" class="dropdown-list-modern">
                    <div v-if="!filteredUnits(idx).length" class="px-5 py-3 text-[10px] text-slate-400 font-black uppercase">لا نتائج</div>
                    <div v-for="unit in filteredUnits(idx)" :key="unit.id"
                         @click="selectUnit(idx, unit)"
                         class="dropdown-item-modern">
                      <span class="font-black text-slate-700 leading-none">{{ unit.name }}</span>
                      <span class="text-[10px] text-slate-400 font-mono">{{ unit.code }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <!-- Quantity -->
              <td class="px-4 py-4 text-center">
                <input type="number" step="0.0001" min="0" v-model.number="row.quantity"
                       @change="autoSave"
                       class="grid-input h-10 w-28 text-center font-black text-blue-600" />
              </td>
              <!-- Cost -->
              <td class="px-4 py-4 text-center">
                <input type="number" step="0.01" min="0" v-model.number="row.cost"
                       @change="autoSave"
                       class="grid-input h-10 w-28 text-center font-black text-emerald-600" />
              </td>
              <!-- Subtotal -->
              <td class="px-4 py-4 text-center font-black text-slate-800 font-mono tracking-tighter text-base">
                {{ formatMoney(row.quantity * row.cost) }}
              </td>
              <!-- Notes -->
              <td class="px-4 py-4">
                <input type="text" v-model="row.notes"
                       class="grid-input h-10 w-full text-xs font-bold text-slate-400" placeholder="ملاحظات..." />
              </td>
              <!-- Delete -->
              <td class="px-6 py-4 text-center">
                <button @click="removeRow(idx)"
                        class="w-8 h-8 rounded-xl bg-rose-50 text-rose-400 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all active:scale-90">
                  <i class="fas fa-times text-[10px]"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Verification Results View (Preview) -->
    <transition name="slide-fade">
      <div v-if="previewResult" class="mb-10 animate-fadeIn">
        <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-xl overflow-hidden">
          <div :class="[previewResult.status === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700']"
               class="px-8 py-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div :class="[previewResult.status === 'success' ? 'bg-emerald-500' : 'bg-rose-500']"
                   class="w-10 h-10 rounded-xl flex items-center justify-center text-white">
                <i :class="[previewResult.status === 'success' ? 'fas fa-check-circle' : 'fas fa-triangle-exclamation']"></i>
              </div>
              <div>
                <h4 class="font-black text-lg leading-none">تقرير التحقق المسبق</h4>
                <p v-if="previewResult.status === 'success'" class="text-[10px] font-black uppercase mt-1">
                  الإجمالي: كمية {{ formatNumber(previewResult.data.summary.total_quantity) }} | تكلفة {{ formatMoney(previewResult.data.summary.total_cost) }}
                </p>
              </div>
            </div>
            <button @click="previewResult = null; previewItems = []; previewWarnings = []"
                    class="opacity-40 hover:opacity-100 transition-opacity">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <div class="p-8 space-y-6">
            <!-- Errors -->
            <div v-if="previewResult.errors?.length" class="bg-rose-50/50 p-6 rounded-2xl border border-rose-100">
              <p class="text-xs font-black text-rose-700 uppercase tracking-widest mb-3 px-1">الأخطاء المكتشفة:</p>
              <ul class="space-y-1.5">
                <li v-for="(err, i) in previewResult.errors" :key="i"
                    class="text-[11px] font-bold text-rose-500 flex items-center gap-2">
                  <i class="fas fa-circle text-[4px]"></i> {{ err }}
                </li>
              </ul>
            </div>
            <!-- Warnings -->
            <div v-if="previewWarnings.length" class="bg-amber-50/50 p-6 rounded-2xl border border-amber-100">
              <p class="text-xs font-black text-amber-700 uppercase tracking-widest mb-3 px-1">تنبيهات هامة:</p>
              <ul class="space-y-1.5">
                <li v-for="(w, i) in previewWarnings" :key="i"
                    class="text-[11px] font-bold text-amber-500 flex items-center gap-2">
                  <i class="fas fa-circle text-[4px]"></i> {{ w }}
                </li>
              </ul>
            </div>
            <!-- Preview Items Table -->
            <div v-if="previewItems.length" class="overflow-x-auto rounded-2xl border border-slate-100">
              <table class="w-full text-right text-xs font-black">
                <thead>
                  <tr class="bg-slate-50/50 text-slate-400">
                    <th class="px-4 py-3">المخزن</th>
                    <th class="px-4 py-3">المنتج</th>
                    <th class="px-4 py-3">الوحدة</th>
                    <th class="px-4 py-3 text-center">الكمية</th>
                    <th class="px-4 py-3 text-center">التكلفة</th>
                    <th class="px-4 py-3 text-center">الإجمالي</th>
                    <th class="px-4 py-3">ملاحظات</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="(r, i) in previewItems" :key="i" class="text-slate-600">
                    <td class="px-4 py-3">{{ r.branch_code || r.branch_id }}</td>
                    <td class="px-4 py-3">{{ r.product_code || r.barcode || r.product_id }}</td>
                    <td class="px-4 py-3">{{ r.unit_code || r.unit_id }}</td>
                    <td class="px-4 py-3 text-center font-mono">{{ formatNumber(r.quantity) }}</td>
                    <td class="px-4 py-3 text-center font-mono">{{ formatMoney(r.cost) }}</td>
                    <td class="px-4 py-3 text-center font-mono text-emerald-600">{{ formatMoney(r.subtotal) }}</td>
                    <td class="px-4 py-3">{{ r.notes }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Import Methods Section (Tabs) -->
    <div class="max-w-5xl mx-auto">
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-10 overflow-hidden">
        <h3 class="text-xl font-black text-slate-900 leading-none mb-8">استيراد البيانات الخارجية</h3>

        <!-- Import Tabs Navigation -->
        <div class="flex items-center gap-2 p-1.5 bg-slate-100 rounded-2xl w-fit mx-auto mb-10">
          <button v-for="tab in [
              { id: 'paste', name: 'لصق جماعي (سريع)', icon: 'paste' },
              { id: 'file', name: 'استيراد ملف Excel', icon: 'file-excel' },
              { id: 'templates', name: 'قوالب جاهزة', icon: 'table' }
            ]"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[activeTab === tab.id ? 'bg-white text-blue-600 shadow-sm font-black' : 'text-slate-400 hover:text-slate-600']"
            class="px-8 py-3 rounded-xl text-xs transition-all uppercase tracking-widest flex items-center gap-2"
          >
            <i :class="['fas fa-' + tab.icon, 'text-[10px]']"></i>
            <span>{{ tab.name }}</span>
          </button>
        </div>

        <!-- Tab: Paste Area -->
        <div v-if="activeTab === 'paste'" class="animate-fadeIn space-y-6">
          <div class="bg-blue-50 border border-blue-100 p-6 rounded-[1.5rem] space-y-3">
            <p class="text-sm font-black text-blue-900 leading-none">كيفية استخدام اللصق السريع</p>
            <div class="space-y-2">
              <div class="flex items-start gap-3">
                <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-[10px] font-black shrink-0">1</span>
                <p class="text-xs font-bold text-blue-700 leading-relaxed">انسخ البيانات من Excel — الترتيب: المستودع، المنتج، الوحدة، الكمية، التكلفة، ملاحظات</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-[10px] font-black shrink-0">2</span>
                <p class="text-xs font-bold text-blue-700 leading-relaxed">الصق البيانات في الحقل أدناه (يدعم عدة صفوف دفعة واحدة)</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-[10px] font-black shrink-0">3</span>
                <p class="text-xs font-bold text-blue-700 leading-relaxed">اضغط "تحويل إلى صفوف"</p>
              </div>
            </div>
            <div class="bg-white rounded-xl p-3 border border-blue-100">
              <p class="text-[10px] font-black text-slate-500 mb-1 uppercase">مثال:</p>
              <code class="text-[10px] text-slate-600 leading-relaxed block">MAIN,SKU-001,PCS,10,25.50,رصيد أولي<br>MAIN,SKU-002,PCS,5,40.00,منتج جديد</code>
            </div>
          </div>

          <textarea v-model="bulkText" @input="validateBulkText" rows="5"
                    class="w-full rounded-[1.5rem] border border-slate-200 p-6 text-sm font-bold font-mono bg-slate-50 focus:bg-white outline-none focus:ring-4 focus:ring-blue-50 transition-all"
                    placeholder="MAIN,SKU-001,PCS,10,25.50,Initial..."></textarea>

          <div v-if="bulkValidation.message"
               :class="[bulkValidation.valid ? 'text-emerald-600 bg-emerald-50 border-emerald-100' : 'text-rose-500 bg-rose-50 border-rose-100']"
               class="text-[10px] font-black uppercase px-4 py-2 rounded-xl border flex items-center gap-2 w-fit">
            <i :class="bulkValidation.valid ? 'fas fa-check-circle' : 'fas fa-triangle-exclamation'"></i>
            {{ bulkValidation.message }}
          </div>

          <div class="flex gap-3 justify-end">
            <button @click="clearBulk" class="px-6 py-3 font-black text-slate-400 text-xs hover:text-slate-800">مسح الحقل</button>
            <button @click="convertBulkToRows" :disabled="!bulkValidation.valid || !bulkText.trim()"
                    class="px-10 py-3 bg-blue-600 text-white rounded-2xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-40">
              تحويل إلى {{ bulkValidation.validRows }} صفوف
            </button>
          </div>
        </div>

        <!-- Tab: File Upload -->
        <div v-if="activeTab === 'file'" class="animate-fadeIn space-y-6">
          <label class="flex flex-col items-center justify-center w-full h-56 border-4 border-slate-100 border-dashed rounded-[2.5rem] cursor-pointer bg-slate-50 hover:bg-white hover:border-blue-300 hover:shadow-2xl hover:shadow-blue-50 transition-all duration-500 group relative">
            <div class="flex flex-col items-center justify-center">
              <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-slate-300 group-hover:text-blue-600 group-hover:scale-110 transition-all shadow-sm mb-4">
                <i class="fas fa-file-arrow-up text-2xl"></i>
              </div>
              <p class="mb-2 text-sm font-black text-slate-700">
                {{ selectedFile ? selectedFile.name : 'اسحب ملف Excel أو CSV هنا' }}
              </p>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                يدعم ملفات .xlsx و .xls و .csv
              </p>
            </div>
            <input type="file" @change="onFileSelected" accept=".xlsx,.xls,.csv" class="hidden" id="file-input-main" />
          </label>

          <!-- File Preview -->
          <div v-if="filePreview" class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-24 h-24 bg-white/5 rounded-full -translate-x-12 -translate-y-12"></div>
            <div class="flex justify-between items-center relative z-10">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-emerald-400">
                  <i class="fas fa-table-list"></i>
                </div>
                <div>
                  <p class="text-xs font-black">{{ filePreview.name }}</p>
                  <p class="text-[10px] text-white/40 mt-1 uppercase font-bold">{{ filePreview.rows }} صف مكتشف • {{ formatFileSize(filePreview.size) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <button @click="clearFile" class="px-4 py-2 rounded-xl text-[10px] font-black text-white/50 hover:text-white transition-colors">إلغاء</button>
                <button @click="importFile" :disabled="loading"
                        class="px-8 py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase shadow-lg shadow-blue-500/20 active:scale-95 transition-all">
                  استيراد السجل الآن ({{ filePreview.rows }} صف)
                </button>
              </div>
            </div>

            <!-- Sample preview inside card -->
            <div v-if="filePreview.sample" class="mt-6 overflow-auto max-h-32 border border-white/10 rounded-xl text-[10px] font-mono relative z-10">
              <table class="min-w-full">
                <thead class="bg-white/10">
                  <tr>
                    <th v-for="h in filePreview.sample.headers" :key="h" class="px-3 py-1.5 text-right text-white/60">{{ h }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, ri) in filePreview.sample.data" :key="ri" class="border-t border-white/5">
                    <td v-for="(cell, ci) in row" :key="ci" class="px-3 py-1.5 text-white/50">{{ cell }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Tab: Templates -->
        <div v-if="activeTab === 'templates'" class="animate-fadeIn space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="t in [
                { id: 'basic', name: 'القالب الأساسي', desc: 'للبيانات البسيطة بالرموز فقط', icon: 'file-lines' },
                { id: 'advanced', name: 'القالب المتقدم', desc: 'مع معرفات ورموز معاً', icon: 'file-shield' },
                { id: 'samples', name: 'قالب مع أمثلة', desc: 'يحتوي على بيانات نموذجية للشرح', icon: 'file-signature' }
              ]"
              :key="t.id"
              @click="downloadTemplate(t.id)"
              class="bg-slate-50 border border-slate-100 p-8 rounded-[2rem] text-center hover:bg-white hover:border-blue-200 hover:shadow-xl transition-all group cursor-pointer">
              <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-slate-300 group-hover:text-blue-600 shadow-sm mx-auto mb-6 transition-all group-hover:scale-110">
                <i :class="['fas fa-' + t.icon, 'text-2xl']"></i>
              </div>
              <h4 class="font-black text-slate-800 text-sm mb-2 uppercase">{{ t.name }}</h4>
              <p class="text-[10px] font-bold text-slate-400 leading-relaxed">{{ t.desc }}</p>
              <div class="mt-6 flex items-center justify-center gap-2 text-blue-600 text-[10px] font-black uppercase opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-download"></i> تحميل الآن
              </div>
            </div>
          </div>
          <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100">
            <p class="text-xs font-bold text-blue-800">
              <strong>نصيحة:</strong> استخدم القالب الأساسي للبيانات الصغيرة، والقالب المتقدم للبيانات الكبيرة والمعقدة.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Global Loading Overlay -->
    <div v-if="loading" class="fixed bottom-10 right-10 z-[100] animate-fadeIn">
      <div class="bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4">
        <svg class="animate-spin w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <span class="text-xs font-black uppercase tracking-widest">جاري معالجة البيانات...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useSetupStore } from '@/stores/setup/setupStore'
import { useProductStore } from '@/stores/product/productStore'
import apiClient from '@/config/axios'
import ExcelJS from 'exceljs'
import AlertService from '@/services/AlertService'
import { useBranchStore } from '@/stores/branch'

// ─── State ───────────────────────────────────────────────────────────────────

// Stores
const productStore = useProductStore();
const branchStore = useBranchStore();
const setupStore = useSetupStore();

const items = ref([])
const bulkText = ref('')
const bulkValidation = ref({ valid: false, message: '', validRows: 0 })
const previewResult = ref(null)
const previewItems = ref([])
const previewWarnings = ref([])
const loading = ref(false)
const setPurchasePriceIfZero = ref(true)
const purchasesOnly = ref(false)
const postAccounting = ref(true)

const activeTab = ref('file')
const filePreview = ref(null)
const selectedFile = ref(null)

const branches = computed(() => branchStore.branches)
const products = ref([])
const units = ref([])

// Auto-save
const autoSaveKey = 'opening_balance_draft'
const lastSaveTime = ref(null)

// Change log (kept from original)
const changeLog = ref([])

// ─── Computed ─────────────────────────────────────────────────────────────────

const totalQuantity = computed(() => items.value.reduce((s, r) => s + (Number(r.quantity) || 0), 0))
const totalCost = computed(() => items.value.reduce((s, r) => s + ((Number(r.quantity) || 0) * (Number(r.cost) || 0)), 0))

// ─── Row Factory ──────────────────────────────────────────────────────────────

function makeRow(overrides = {}) {
  return {
    branch_code: '',
    product_code_or_barcode: '',
    unit_code: '',
    quantity: 0,
    cost: 0,
    notes: '',
    branch_search: '',
    product_search: '',
    unit_search: '',
    showbranchList: false,
    showProductList: false,
    showUnitList: false,
    filteredbranchs: [],
    filteredProducts: [],
    filteredUnits: [],
    branch_id: null,
    product_id: null,
    unit_id: null,
    ...overrides
  }
}

// ─── Row Management ───────────────────────────────────────────────────────────

function addRow() {
  items.value.push(makeRow())
  initializeRowFilters(items.value.length - 1)
}

function removeRow(idx) {
  logChange('remove_row', { index: idx })
  items.value.splice(idx, 1)
  autoSave()
}

function clearRows() {
  logChange('clear_all', { previousCount: items.value.length })
  items.value = []
  autoSave()
}

// ─── Dropdown / Filter Logic (STRICTLY PRESERVED from original) ───────────────

function initializeRowFilters(idx) {
  const row = items.value[idx]
  if (!row) return
  row.filteredbranchs = [...branches.value]
  row.filteredProducts = [...products.value]
  row.filteredUnits = [...units.value]
}

function closeAllDropdowns() {
  items.value.forEach(row => {
    row.showbranchList = false
    row.showProductList = false
    row.showUnitList = false
  })
}

function filterbranchs(idx) {
  closeAllDropdowns()
  const row = items.value[idx]
  const q = (row.branch_search || '').toLowerCase()
  row.filteredbranchs = branches.value.filter(w =>
    w.name.toLowerCase().includes(q) || (w.code && w.code.toLowerCase().includes(q))
  )
  row.showbranchList = true
}

function filterProducts(idx) {
  closeAllDropdowns()
  const row = items.value[idx]
  const q = (row.product_search || '').toLowerCase()
  row.filteredProducts = products.value.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.code && p.code.toLowerCase().includes(q)) ||
    (p.barcode && p.barcode.includes(q))
  )
  row.showProductList = true
}

function filterUnits(idx) {
  closeAllDropdowns()
  const row = items.value[idx]
  const q = (row.unit_search || '').toLowerCase()
  row.filteredUnits = units.value.filter(u =>
    u.name.toLowerCase().includes(q) || (u.code && u.code.toLowerCase().includes(q))
  )
  row.showUnitList = true
}

function showbranchDropdown(idx) {
  closeAllDropdowns()
  items.value[idx].showbranchList = true
  filterbranchs(idx)
}

function showProductDropdown(idx) {
  closeAllDropdowns()
  items.value[idx].showProductList = true
  filterProducts(idx)
}

function showUnitDropdown(idx) {
  closeAllDropdowns()
  if (items.value[idx].product_id) {
    items.value[idx].showUnitList = true
    filterUnits(idx)
  }
}

function selectbranch(idx, branch) {
  const row = items.value[idx]
  row.branch_code = branch.code || branch.name
  row.branch_id = branch.id
  row.branch_search = branch.name
  row.showbranchList = false
  logChange('select_branch', { index: idx, branch: branch.name })
  autoSave()
}

function selectProduct(idx, product) {
  const row = items.value[idx]
  row.product_code_or_barcode = product.code || product.barcode
  row.product_id = product.id
  row.product_search = product.name
  row.showProductList = false

  // Auto-fill cost if available (preserved from original)
  if (product.purchase_price && !row.cost) {
    row.cost = product.purchase_price
  }

  // Auto-fill default unit (preserved from original)
  if (product.unit_id) {
    const defaultUnit = units.value.find(u => u.id === product.unit_id)
    if (defaultUnit) {
      row.unit_code = defaultUnit.code
      row.unit_id = defaultUnit.id
      row.unit_search = defaultUnit.name
    }
  }

  logChange('select_product', { index: idx, product: product.name })
  autoSave()
}

function selectUnit(idx, unit) {
  const row = items.value[idx]
  row.unit_code = unit.code
  row.unit_id = unit.id
  row.unit_search = unit.name
  row.showUnitList = false
  logChange('select_unit', { index: idx, unit: unit.name })
  autoSave()
}

function filteredbranchs(idx) { return items.value[idx]?.filteredbranchs || [] }
function filteredProducts(idx) { return items.value[idx]?.filteredProducts || [] }
function filteredUnits(idx) { return items.value[idx]?.filteredUnits || [] }

// ─── Auto-save (STRICTLY PRESERVED from original) ─────────────────────────────

function autoSave() {
  const data = {
    items: items.value,
    timestamp: new Date().toISOString(),
    expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
  }
  localStorage.setItem(autoSaveKey, JSON.stringify(data))
  lastSaveTime.value = new Date()
}

function loadAutoSave() {
  try {
    const saved = localStorage.getItem(autoSaveKey)
    if (!saved) return false
    const data = JSON.parse(saved)
    if (data.expiresAt && new Date() > new Date(data.expiresAt)) {
      clearAutoSave(); return false
    }
    if (data.items && data.items.length > 0) {
      items.value = data.items
      items.value.forEach((_, idx) => initializeRowFilters(idx))
      lastSaveTime.value = new Date(data.timestamp)
      return true
    }
  } catch (e) {
    console.error('Error loading auto-save:', e)
  }
  return false
}

function clearAutoSave() {
  localStorage.removeItem(autoSaveKey)
  lastSaveTime.value = null
}

// ─── Change Tracking (preserved from original) ────────────────────────────────

function logChange(action, details) {
  changeLog.value.unshift({ action, details, timestamp: new Date().toISOString() })
  if (changeLog.value.length > 50) changeLog.value = changeLog.value.slice(0, 50)
}

// ─── CSV Paste Parser (STRICTLY PRESERVED from original) ──────────────────────

function parseCsvText(text) {
  const lines = text.split(/\r?\n/).filter(l => l.trim() !== '')
  const out = []
  for (const line of lines) {
    const parts = line.split(',').map(s => s.trim())
    if (parts.length < 5) continue
    const [a, b, c, q, cost, ...rest] = parts
    const quantity = Number(q)
    const costNum = Number(cost)
    const aNum = Number(a); const bNum = Number(b); const cNum = Number(c)
    const isIds = !isNaN(aNum) && !isNaN(bNum) && !isNaN(cNum) && a !== '' && b !== '' && c !== ''
    if (isIds) {
      out.push({
        branch_id: aNum || null,
        product_id: bNum || null,
        unit_id: cNum || 1,
        quantity: isNaN(quantity) ? 0 : quantity,
        cost: isNaN(costNum) ? 0 : costNum,
        notes: rest.join(',') || ''
      })
    } else {
      out.push({
        branch_code: a || '',
        product_code_or_barcode: b || '',
        unit_code: c || '',
        quantity: isNaN(quantity) ? 0 : quantity,
        cost: isNaN(costNum) ? 0 : costNum,
        notes: rest.join(',') || ''
      })
    }
  }
  return out
}

// ─── Bulk Paste Validation (STRICTLY PRESERVED from original) ─────────────────

function validateBulkText() {
  const text = bulkText.value.trim()
  if (!text) { bulkValidation.value = { valid: false, message: '', validRows: 0 }; return }

  const lines = text.split('\n').filter(l => l.trim())
  let validRows = 0
  const errors = []

  for (let i = 0; i < lines.length; i++) {
    const parts = lines[i].trim().split(',').map(p => p.trim())
    if (parts.length < 5) { errors.push(`صف ${i + 1}: يجب أن يحتوي على 5-6 حقول`); continue }
    const qty = parseFloat(parts[3])
    const cst = parseFloat(parts[4])
    if (isNaN(qty) || qty < 0) { errors.push(`صف ${i + 1}: الكمية يجب أن تكون رقماً موجباً`); continue }
    if (isNaN(cst) || cst < 0) { errors.push(`صف ${i + 1}: التكلفة يجب أن تكون رقماً موجباً`); continue }
    validRows++
  }

  if (errors.length > 0) {
    bulkValidation.value = {
      valid: false,
      message: `أخطاء في ${errors.length} صفوف: ${errors.slice(0, 3).join(' | ')}${errors.length > 3 ? '...' : ''}`,
      validRows
    }
  } else {
    bulkValidation.value = { valid: true, message: `جميع البيانات صالحة (${validRows} صفوف)`, validRows }
  }
}

// ─── Convert Bulk to Rows (STRICTLY PRESERVED from original) ──────────────────

async function convertBulkToRows() {
  if (!bulkValidation.value.valid) {
    await AlertService.warning('يرجى تصحيح الأخطاء أولاً', 'بيانات غير صالحة')
    return
  }
  const lines = bulkText.value.trim().split('\n').filter(l => l.trim())
  const newItems = lines.map(line => {
    const p = line.split(',').map(s => s.trim())
    return makeRow({
      branch_code: p[0],
      product_code_or_barcode: p[1],
      unit_code: p[2],
      quantity: parseFloat(p[3]) || 0,
      cost: parseFloat(p[4]) || 0,
      notes: p[5] || ''
    })
  })
  items.value = [...items.value, ...newItems]
  newItems.forEach((_, i) => initializeRowFilters(items.value.length - newItems.length + i))
  logChange('bulk_import', { rowsCount: newItems.length })
  autoSave()
  clearBulk()
  await AlertService.success(`تم تحويل ${newItems.length} صفوف بنجاح`, 'نجاح')
}

function clearBulk() {
  bulkText.value = ''
  bulkValidation.value = { valid: false, message: '', validRows: 0 }
}

// ─── CSV Quick Import (STRICTLY PRESERVED from original) ──────────────────────

async function onCsvSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  try {
    const text = await file.text()
    const rows = parseCsvText(text)
    if (rows.length === 0) {
      await AlertService.warning('ملف CSV لا يحتوي بيانات صالحة', 'ملف CSV فارغ')
      return
    }
    const newItems = rows.map(r => makeRow(r))
    items.value.push(...newItems)
    newItems.forEach((_, i) => initializeRowFilters(items.value.length - newItems.length + i))
    logChange('csv_import', { rowsCount: newItems.length })
    autoSave()
    await AlertService.success(`تم استيراد ${newItems.length} صفوف من CSV بنجاح`, 'نجاح')
  } catch (err) {
    await AlertService.error('فشل قراءة ملف CSV: ' + err.message, 'خطأ')
  } finally {
    e.target.value = ''
  }
}

// ─── File Selection & Preview (STRICTLY PRESERVED from original) ───────────────

async function onFileSelected(event) {
  const file = event.target.files[0]
  if (!file) return
  selectedFile.value = file

  const ext = '.' + file.name.split('.').pop().toLowerCase()
  const validTypes = ['.xlsx', '.xls', '.csv']
  if (!validTypes.includes(ext)) {
    await AlertService.warning('نوع الملف غير مدعوم. الرجاء اختيار Excel أو CSV', 'خطأ في نوع الملف')
    clearFile(); return
  }

  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      if (ext === '.csv') {
        const lines = (e.target.result).split('\n').filter(l => l.trim())
        const headers = lines[0].split(',').map(h => h.trim())
        const rows = lines.slice(1, 6).map(line => line.split(',').map(c => c.trim()))
        filePreview.value = {
          name: file.name, size: file.size,
          rows: lines.length - 1, columns: headers.length,
          sample: { headers, data: rows }
        }
      } else {
        const workbook = new ExcelJS.Workbook()
        await workbook.xlsx.load(e.target.result)
        const worksheet = workbook.getWorksheet(1)
        if (!worksheet) {
          await AlertService.warning('الملف لا يحتوي على أوراق عمل', 'خطأ')
          clearFile(); return
        }
        const rows = worksheet.getRows(1, 6)
        if (!rows || rows.length === 0) {
          await AlertService.warning('الملف فارغ', 'ملف فارغ')
          clearFile(); return
        }
        const headers = rows[0].values.slice(1)
        const sampleRows = rows.slice(1).map(r => r.values.slice(1))
        filePreview.value = {
          name: file.name, size: file.size,
          rows: worksheet.rowCount - 1, columns: headers.length,
          sample: { headers, data: sampleRows }
        }
      }
    } catch (err) {
      await AlertService.error('فشل قراءة الملف: ' + err.message, 'خطأ')
      clearFile()
    }
  }
  reader.readAsArrayBuffer(file)
}

// ─── File Import (STRICTLY PRESERVED from original) ───────────────────────────

async function importFile() {
  if (!selectedFile.value || !filePreview.value) return
  loading.value = true
  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      const ext = '.' + selectedFile.value.name.split('.').pop().toLowerCase()
      let jsonData = []

      if (ext === '.csv') {
        const lines = (new TextDecoder().decode(e.target.result)).split('\n').filter(l => l.trim())
        const headers = lines[0].split(',').map(h => h.trim())
        for (let i = 1; i < lines.length; i++) {
          const values = lines[i].split(',').map(v => v.trim())
          const row = {}
          headers.forEach((h, idx) => { row[h] = values[idx] || '' })
          jsonData.push(row)
        }
      } else {
        const workbook = new ExcelJS.Workbook()
        await workbook.xlsx.load(e.target.result)
        const worksheet = workbook.getWorksheet(1)
        if (!worksheet) { await AlertService.warning('الملف لا يحتوي على أوراق عمل', 'خطأ'); return }
        const headerRow = worksheet.getRow(1)
        const headers = headerRow.values.slice(1)
        for (let i = 2; i <= worksheet.rowCount; i++) {
          const row = worksheet.getRow(i)
          const values = row.values.slice(1)
          const rowData = {}
          headers.forEach((h, idx) => { rowData[h] = values[idx] || '' })
          jsonData.push(rowData)
        }
      }

      // Convert to our format (preserved logic)
      const newItems = jsonData.map(row => {
        const hasCodes = row.branch_code || row.product_code_or_barcode || row.unit_code
        const hasIds = row.branch_id || row.product_id || row.unit_id
        if (hasCodes) {
          return makeRow({
            branch_code: row.branch_code || '',
            product_code_or_barcode: row.product_code_or_barcode || row.barcode || '',
            unit_code: row.unit_code || '',
            quantity: parseFloat(row.quantity) || 0,
            cost: parseFloat(row.cost) || 0,
            notes: row.notes || ''
          })
        } else if (hasIds) {
          return makeRow({
            branch_id: parseInt(row.branch_id) || null,
            product_id: parseInt(row.product_id) || null,
            unit_id: parseInt(row.unit_id) || 1,
            quantity: parseFloat(row.quantity) || 0,
            cost: parseFloat(row.cost) || 0,
            notes: row.notes || ''
          })
        } else {
          const keys = Object.keys(row)
          return makeRow({
            branch_code: row[keys[0]] || '',
            product_code_or_barcode: row[keys[1]] || '',
            unit_code: row[keys[2]] || '',
            quantity: parseFloat(row[keys[3]]) || 0,
            cost: parseFloat(row[keys[4]]) || 0,
            notes: row[keys[5]] || ''
          })
        }
      }).filter(r => r.branch_code || r.branch_id || r.product_code_or_barcode || r.product_id)

      items.value = [...items.value, ...newItems]
      newItems.forEach((_, i) => initializeRowFilters(items.value.length - newItems.length + i))
      logChange('file_import', { rowsCount: newItems.length, fileName: selectedFile.value.name })
      autoSave()
      await AlertService.success(`تم استيراد ${newItems.length} صفوف من ${selectedFile.value.name}`, 'نجاح')
      clearFile()
    } catch (err) {
      await AlertService.error('فشل استيراد الملف: ' + err.message, 'خطأ')
    } finally {
      loading.value = false
    }
  }
  reader.readAsArrayBuffer(selectedFile.value)
}

function clearFile() {
  selectedFile.value = null
  filePreview.value = null
  const fi = document.getElementById('file-input-main')
  if (fi) fi.value = ''
}

// ─── Download Template (STRICTLY PRESERVED from original, all 3 types) ─────────

async function downloadTemplate(type = 'basic') {
  try {
    let templateData = ''
    let filename = ''
    switch (type) {
      case 'basic':
        templateData = 'branch_code,product_code_or_barcode,unit_code,quantity,cost,notes\nMAIN,SKU-001,PCS,10,25.5,initial stock\nMAIN,SKU-002,PCS,5,40.0,new item'
        filename = 'opening_balance_basic_template.csv'; break
      case 'advanced':
        templateData = 'branch_code,product_code_or_barcode,unit_code,quantity,cost,notes,branch_id,product_id,unit_id\nMAIN,SKU-001,PCS,10,25.5,initial stock,1,101,1\nMAIN,SKU-002,PCS,5,40.0,new item,1,102,1'
        filename = 'opening_balance_advanced_template.csv'; break
      case 'samples':
        templateData = `branch_code,product_code_or_barcode,unit_code,quantity,cost,notes
MAIN,SKU-001,PCS,10,25.5,initial stock
MAIN,SKU-002,PCS,5,40.0,new item
MAIN,SKU-003,PCS,20,15.75,bulk purchase
MAIN,SKU-004,PCS,50,8.99,discounted item
MAIN,SKU-005,PCS,100,2.50,clearance stock
SECOND,SKU-006,PCS,15,30.00,branch transfer
SECOND,SKU-007,PCS,8,45.50,premium item
THIRD,SKU-008,PCS,12,22.75,seasonal stock`
        filename = 'opening_balance_samples_template.csv'; break
      default:
        templateData = 'branch_code,product_code_or_barcode,unit_code,quantity,cost,notes\nMAIN,SKU-001,PCS,10,25.5,initial stock'
        filename = 'opening_balance_template.csv'
    }
    const blob = new Blob([templateData], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = filename; a.click()
    URL.revokeObjectURL(url)
    await AlertService.success(`تم تحميل ${filename} بنجاح`, 'نجاح')
  } catch (e) {
    await AlertService.error('فشل تحميل القالب: ' + e.message, 'خطأ')
  }
}

// ─── Preview (STRICTLY PRESERVED from original — full payload mapping) ─────────

async function preview() {
  previewResult.value = null; previewItems.value = []; previewWarnings.value = []
  loading.value = true
  try {
    const payload = items.value.map(r => ({
      branch_id: r.branch_id ?? null,
      product_id: r.product_id ?? null,
      unit_id: r.unit_id ?? null,
      branch_code: r.branch_code || undefined,
      product_code: (r.product_code || r.product_code_or_barcode)?.match(/\D/)
        ? (r.product_code || r.product_code_or_barcode) : undefined,
      barcode: (r.barcode || r.product_code_or_barcode)?.match(/^\d+$/)
        ? (r.barcode || r.product_code_or_barcode) : undefined,
      unit_code: r.unit_code || undefined,
      quantity: r.quantity,
      cost: r.cost,
      notes: r.notes || ''
    }))
    const res = await setupStore.preview(payload)
    if (res.status === 'success') {
      previewResult.value = res.data
      previewItems.value = res.data?.items || []
      previewWarnings.value = res.data?.warnings || []
    } else {
      previewResult.value = { status: 'error', message: res.message }
    }
  } catch (e) {
    previewResult.value = e?.response?.data || { status: 'error', message: e.message }
  } finally {
    loading.value = false
  }
}

// ─── Commit (STRICTLY PRESERVED from original — double confirm + full payload) ─

async function commit() {
  // Double confirmation as in original
  const firstConfirm = await AlertService.confirm(
    'هل أنت متأكد من ترحيل الرصيد الافتتاحي؟ هذه العملية لا يمكن التراجع عنها.',
    'تأكيد العملية'
  )
  if (!firstConfirm) return

  // Delay prevents residual click event from first dialog bleeding into the second
  await new Promise(resolve => setTimeout(resolve, 350))

  const secondConfirm = await AlertService.confirm(
    'تأكيد نهائي: هل تريد ترحيل الرصيد الآن؟',
    'تأكيد نهائي'
  )
  if (!secondConfirm) return

  loading.value = true
  try {
    const payload = items.value.map(r => ({
      branch_id: r.branch_id ?? null,
      product_id: r.product_id ?? null,
      unit_id: r.unit_id ?? null,
      branch_code: r.branch_code || undefined,
      product_code: (r.product_code || r.product_code_or_barcode)?.match(/\D/)
        ? (r.product_code || r.product_code_or_barcode) : undefined,
      barcode: (r.barcode || r.product_code_or_barcode)?.match(/^\d+$/)
        ? (r.barcode || r.product_code_or_barcode) : undefined,
      unit_code: r.unit_code || undefined,
      quantity: r.quantity,
      cost: r.cost,
      notes: r.notes,
      setPurchasePrice: setPurchasePriceIfZero.value,
      purchasesOnly: purchasesOnly.value,
      postAccounting: postAccounting.value
    }))
    const res = await setupStore.commit(payload)
    if (res.status === 'success') {
      await AlertService.success(res.message || 'تم الترحيل بنجاح', 'نجاح')
      clearAutoSave()
      previewResult.value = null; previewItems.value = []; previewWarnings.value = []
      logChange('commit', { itemCount: items.value.length })
      
      // ─── Emit event to notify InventoryManagement.vue to refresh ────────────
      // Wait a moment for backend to finish processing
      await new Promise(resolve => setTimeout(resolve, 500))
      
      const event = new CustomEvent('openingBalancePosted', {
        detail: {
          timestamp: new Date().toISOString(),
          itemCount: items.value.length,
          message: 'تم ترصيد الرصيد الافتتاحي بنجاح'
        }
      })
      window.dispatchEvent(event)
      console.log('📡 openingBalancePosted event emitted', { itemCount: items.value.length })
      
      // ✅ تحديث cache المنتجات في POS (يؤثر على الكميات)
      if (typeof productStore !== 'undefined' && productStore.invalidateCache) {
        productStore.invalidateCache()
      }
      
      // Also store in session storage as fallback (in case InventoryManagement isn't open)
      sessionStorage.setItem('lastOpeningBalancePosted', JSON.stringify({
        timestamp: new Date().toISOString(),
        itemCount: items.value.length
      }))
      
      // Clear items after successful commit
      items.value = []
      addRow()
    } else {
      throw new Error(res.message)
    }
  } catch (e) {
    await AlertService.error('فشل الترحيل: ' + (e?.response?.data?.message || e.message), 'خطأ')
  } finally {
    loading.value = false
  }
}

// ─── Click Outside Handler ─────────────────────────────────────────────────────

function handleClickOutside(event) {
  if (!event.target.closest('.dropdown-container')) closeAllDropdowns()
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatNumber(n) {
  return (Number(n) || 0).toLocaleString('en-US', { maximumFractionDigits: 4 })
}
function formatMoney(n) {
  return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function formatFileSize(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
  // Initialize stores
  await branchStore.initialize()
  
  try {
    // Load data using stores
    const [productsRes, unitsRes] = await Promise.all([
      productStore.fetchProducts({ force: true }),
      apiClient.get('/units')
    ])
    products.value = productsRes.status === 'success' ? productsRes.data : []
    const rawUnits = unitsRes?.data?.data ?? unitsRes?.data ?? []
    units.value = Array.isArray(rawUnits) ? rawUnits : []
  } catch (e) {
    console.error('Error loading master data', e)
  }

  // Auto-save restore prompt (STRICTLY PRESERVED from original)
  const hasAutoSave = loadAutoSave()
  if (hasAutoSave) {
    const restore = await AlertService.confirm(
      'تم العثور على بيانات محفوظة. هل تريد استعادتها؟',
      'استعادة البيانات المحفوظة'
    )
    if (restore) {
      await AlertService.success('تم استعادة البيانات المحفوظة بنجاح', 'نجاح')
    } else {
      clearAutoSave()
      items.value = []
    }
  }

  if (items.value.length === 0) addRow()
  items.value.forEach((_, idx) => initializeRowFilters(idx))

  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>



/* Grid Entry Components */
.grid-input { @apply block bg-white border border-slate-100 rounded-xl px-3 pr-9 outline-none transition-all duration-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 text-sm; }

/* Dropdown Styling */
.dropdown-list-modern { @apply absolute top-full left-0 right-0 bg-white border border-slate-100 rounded-2xl shadow-2xl z-50 max-h-48 overflow-auto py-2 animate-fadeIn; min-width: 180px; }
.dropdown-item-modern { @apply px-5 py-2.5 hover:bg-blue-50 cursor-pointer flex justify-between items-center transition-colors border-b border-slate-50 last:border-0; }

/* Toggle Styling */
.toggle-option { @apply flex items-center gap-3 cursor-pointer select-none; }
.toggle-box {
  @apply w-5 h-5 rounded-lg border-2 border-slate-200 bg-white transition-all relative flex items-center justify-center;
}
.toggle-box::after {
  content: '✓';
  @apply text-white text-[10px] font-black opacity-0 scale-50 transition-all;
}
input:checked + .toggle-box { @apply text-white; }
input:checked + .toggle-box::after { @apply opacity-100 scale-100; }

/* Action Buttons */
.btn-action { @apply w-10 h-10 rounded-xl flex items-center justify-center transition-all active:scale-90 shadow-sm border border-white text-sm; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-leave-active { transition: all 0.2s ease-in; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateY(-10px); }

/* Custom Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>