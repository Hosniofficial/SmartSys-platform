<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-share-nodes text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">التوزيع الجماعي للمخزون</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">توزيع الكميات على عدة مستودعات أو الاستيراد عبر ملفات CSV</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="refreshData" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-sync-alt"></i> تحديث البيانات
        </button>
        <router-link to="/branches" class="px-5 py-2.5 rounded-xl text-xs font-black text-blue-600 hover:bg-blue-50 transition-all flex items-center gap-2">
          <i class="fas fa-arrow-right"></i> العودة للمستودعات
        </router-link>
      </div>
    </div>

    <!-- Enhanced Tab Navigation -->
    <div class="flex items-center gap-2 p-1.5 bg-white rounded-2xl border border-slate-100 shadow-sm w-fit mx-auto mb-10 sticky top-4 z-40 backdrop-blur-md bg-white/90">
      <button v-for="tab in [
          { id: 'form', name: 'نموذج التوزيع', icon: 'sliders-h' },
          { id: 'csv', name: 'استيراد CSV', icon: 'file-csv' },
          { id: 'history', name: 'سجل العمليات', icon: 'history' }
        ]" 
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="[activeTab === tab.id ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-500 hover:bg-slate-50']"
        class="tab-pill"
      >
        <i :class="['fas fa-' + tab.icon, 'text-[10px]']"></i>
        <span>{{ tab.name }}</span>
      </button>
    </div>

    <!-- Tab Content: Form Distribution -->
    <div v-if="activeTab === 'form'" class="space-y-8">
      
      <!-- Product Selection Card -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-visible">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-xl font-black text-slate-900 leading-none">1. اختيار المنتج المستهدف</h3>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">ابحث عن الصنف المراد توزيعه حالياً</p>
          </div>
          <div v-if="selectedProduct" class="bg-blue-50 px-4 py-2 rounded-xl border border-blue-100 flex items-center gap-3">
             <span class="text-[10px] font-black text-blue-400 uppercase tracking-widest leading-none">إجمالي كمية التوزيع:</span>
             <span class="text-lg font-black text-blue-600 leading-none">{{ totalQuantity }}</span>
          </div>
        </div>

        <div class="relative group">
          <div class="flex gap-3">
            <div class="relative flex-grow">
              <input 
                type="text" 
                class="form-input-modern pr-12 text-lg font-black h-14" 
                v-model="productSearch" 
                @input="debouncedSearch" 
                @focus="showDropdown = true"
                @keydown="handleSearchKeydown"
                placeholder="ادخل اسم المنتج أو الباركود للبحث..." 
                aria-label="بحث عن المنتج"
                role="combobox"
                :aria-expanded="showDropdown"
                aria-autocomplete="list"
              />
              <i class="fas fa-search absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
            <button v-if="selectedProduct" @click="clearProduct" class="w-14 h-14 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-sm active:scale-90">
              <i class="fas fa-times text-lg"></i>
            </button>
          </div>

          <!-- Search Dropdown -->
          <transition name="fade">
            <div v-if="showDropdown && products.length" class="absolute z-50 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-2xl max-h-72 overflow-auto py-2">
              <div v-for="p in products" :key="p.id" @mousedown.prevent="selectProduct(p)" class="px-6 py-3 cursor-pointer hover:bg-blue-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0">
                <div class="flex flex-col">
                  <span class="font-black text-slate-800 text-sm leading-none">{{ p.name }}</span>
                  <span class="text-[10px] text-slate-400 mt-1.5 uppercase font-mono tracking-tighter">{{ p.barcode || 'بدون باركود' }}</span>
                </div>
                <div class="text-left">
                   <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-0.5 rounded-lg">المخزون: {{ p.stock || 0 }}</span>
                   <span v-if="selectedProduct && selectedProduct.id === p.id" class="text-[10px] font-black text-rose-500 bg-rose-50 px-2 py-0.5 rounded-lg mr-2">
                     المتبقي: {{ (selectedProduct.stock || 0) - totalQuantity }}
                   </span>
                </div>
              </div>
            </div>
          </transition>
        </div>

        <!-- Selected Product Badge -->
        <transition name="slide-fade">
          <div v-if="selectedProduct" class="mt-6 p-5 bg-slate-900 rounded-2xl text-white flex items-center justify-between shadow-xl shadow-slate-200 border border-slate-800">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center"><i class="fas fa-box"></i></div>
              <div>
                <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest leading-none mb-1">المنتج النشط</p>
                <h4 class="font-black text-base">{{ selectedProduct.name }}</h4>
              </div>
            </div>
            <div class="text-left">
              <p class="text-[9px] font-black text-white/30 uppercase tracking-widest mb-1">المخزون الحالي المتوفر</p>
              <p class="text-xl font-black text-blue-400 leading-none">{{ selectedProduct.stock || 0 }}</p>
              <p class="text-[9px] font-black text-rose-400 uppercase tracking-widest mt-1">المتبقي: {{ (selectedProduct.stock || 0) - totalQuantity }}</p>
            </div>
          </div>
        </transition>
      </div>

      <!-- Target branches Card -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
          <div>
            <h3 class="text-xl font-black text-slate-900 leading-none">2. الفروع والكميات</h3>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">حدد الكمية المراد تخصيصها لكل فرع</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="fillEqualQuantities" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase hover:bg-slate-200 transition-all">توزيع متساوي</button>
            <button @click="resetQuantities" class="px-4 py-2 rounded-xl bg-rose-50 text-rose-600 text-[10px] font-black uppercase hover:bg-rose-500 hover:text-white transition-all">تصفير الكل</button>
          </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-50">
          <table class="w-full text-right text-sm">
            <thead>
              <tr class="bg-slate-50/80 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                <th class="px-8 py-5">اسم الفرع</th>
                <th class="px-4 py-5 text-center">الكمية المستهدفة</th>
                <th class="px-4 py-5 text-center">النسبة (%)</th>
                <th class="px-6 py-5">البيان / ملاحظات التسوية</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-bold">
              <tr v-for="(row, index) in rows" :key="row.id" class="hover:bg-slate-50/50 transition-all">
                <td class="px-8 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400"><i class="fas fa-building text-xs"></i></div>
                    <span class="font-black text-slate-800 leading-none">{{ row.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-4 text-center">
                  <input 
                    type="number" 
                    v-model.number="row.quantity" 
                    class="w-32 h-11 text-center bg-white border-2 border-slate-100 rounded-xl font-black font-mono text-blue-600 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all outline-none" 
                    step="0.01" 
                    min="0"
                  />
                </td>
                <td class="px-4 py-4 text-center">
                  <div class="flex flex-col items-center gap-1">
                    <div class="w-12 h-1 bg-slate-100 rounded-full overflow-hidden">
                       <div class="h-full bg-blue-500" :style="{ width: getPercentage(row.quantity) + '%' }"></div>
                    </div>
                    <span class="text-[10px] font-black text-slate-400 font-mono">{{ getPercentage(row.quantity) }}%</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <input 
                    type="text" 
                    v-model="row.notes" 
                    placeholder="ملاحظات الحركة..." 
                    class="w-full h-11 bg-slate-50/50 border border-slate-100 rounded-xl px-4 text-xs font-bold outline-none focus:bg-white focus:border-blue-200 transition-all" 
                  />
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50/80 font-black">
              <tr>
                <td class="px-8 py-5 text-xs text-slate-400 uppercase tracking-widest">إجمالي الحركات</td>
                <td class="px-4 py-5 text-center text-xl text-blue-600 font-mono tracking-tighter">{{ totalQuantity }}</td>
                <td class="px-4 py-5 text-center text-slate-400">100%</td>
                <td class="px-6 py-5"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Action Button -->
        <div class="mt-10 flex flex-col md:flex-row items-center justify-between gap-6 p-8 bg-slate-50 rounded-[2rem] border border-slate-100">
           <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-blue-600 shadow-sm"><i class="fas fa-info-circle"></i></div>
              <p class="text-xs font-bold text-slate-500 leading-relaxed max-w-sm">سيتم تنفيذ عمليات تسوية مخزنية (Adjustment) لكافة الفروع المحددة دفعة واحدة في السجل المحاسبي.</p>
           </div>
           <button @click="submitBulk" :disabled="isSubmitting || !selectedProduct || totalQuantity === 0" class="h-14 px-12 bg-blue-600 text-white rounded-2xl font-black text-base shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-30 disabled:cursor-not-allowed flex items-center gap-3">
              <BaseSpinner v-if="isSubmitting" :size="20" color="#fff" />
              <i v-else class="fas fa-check-circle"></i>
              تنفيذ التوزيع الآن
           </button>
        </div>
      </div>
    </div>

    <!-- Tab Content: CSV Import -->
    <div v-else-if="activeTab === 'csv'" class="animate-fadeIn">
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
          <div>
            <h3 class="text-xl font-black text-slate-900 leading-none tracking-tight">استيراد جماعي عبر CSV</h3>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">تحديث المخزون عبر رفع ملفات البيانات المجدولة</p>
          </div>
          <a href="/templates/bulk_adjustments_template.csv" download class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-slate-200 hover:bg-black transition-all flex items-center gap-2">
            <i class="fas fa-download"></i> تحميل قالب البيانات
          </a>
        </div>
        
        <div class="space-y-8">
          <!-- File Drop Zone -->
          <div class="relative">
            <label class="flex flex-col items-center justify-center w-full h-56 border-4 border-slate-100 border-dashed rounded-[2.5rem] cursor-pointer bg-slate-50/50 hover:bg-white hover:border-blue-200 hover:shadow-2xl hover:shadow-blue-50 transition-all duration-500 group">
              <div class="flex flex-col items-center justify-center pt-5 pb-6">
                <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-slate-300 group-hover:text-blue-600 group-hover:scale-110 transition-all shadow-sm mb-4">
                  <i class="fas fa-cloud-upload-alt text-2xl"></i>
                </div>
                <p class="mb-2 text-sm font-black text-slate-700">اسحب الملف هنا أو انقر للاختيار</p>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">يدعم ملفات CSV بحد أقصى 10 ميجابايت</p>
              </div>
              <input type="file" accept=".csv" @change="onFileChange" class="hidden" />
            </label>

            <!-- Selected File Badge -->
            <transition name="slide-fade">
              <div v-if="csvFile" class="mt-4 p-5 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-center justify-between animate-fadeIn">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-emerald-500 shadow-sm"><i class="fas fa-file-csv text-xl"></i></div>
                  <div>
                    <p class="font-black text-slate-800 text-sm leading-none">{{ csvFile.name }}</p>
                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-tighter mt-1.5">{{ formatFileSize(csvFile.size) }}</p>
                  </div>
                </div>
                <button @click="clearFile" class="text-slate-300 hover:text-rose-500 transition-colors"><i class="fas fa-times-circle text-xl"></i></button>
              </div>
            </transition>
          </div>
          
          <!-- Default Product Option -->
          <div class="p-8 rounded-[2rem] bg-indigo-50/50 border border-indigo-100 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-100/50 rounded-full translate-x-8 -translate-y-8"></div>
            <div class="relative z-10">
                <label class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-4 block px-1 flex items-center gap-2">
                  <i class="fas fa-cube"></i> المنتج الافتراضي (في حال عدم وجود ID بالملف)
                </label>
                <div class="flex flex-col md:flex-row items-center gap-4">
                  <input 
                    type="number" 
                    v-model.number="defaultProductId" 
                    class="w-full md:w-40 h-11 rounded-xl border border-indigo-200 bg-white px-4 text-sm font-black font-mono outline-none focus:ring-4 focus:ring-indigo-100 transition-all" 
                    placeholder="ادخل ID المنتج"
                  />
                  <p class="text-[11px] font-bold text-indigo-700/60 leading-relaxed italic">ملاحظة: سيتم تطبيق هذا المعرف على كافة صفوف الملف التي تفتقر لمعرف منتج صريح.</p>
                </div>
            </div>
          </div>
          
          <div class="pt-6 flex justify-end">
            <button @click="submitCsv" :disabled="isSubmitting || !csvFile" class="h-14 px-12 bg-slate-900 text-white rounded-2xl font-black text-sm shadow-xl shadow-slate-200 hover:bg-black active:scale-95 transition-all disabled:opacity-30 flex items-center gap-3">
              <BaseSpinner v-if="isSubmitting" :size="18" color="#fff" />
              <i v-else class="fas fa-file-import"></i>
              بدء عملية الاستيراد الآن
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Content: History -->
    <div v-else-if="activeTab === 'history'" class="animate-fadeIn">
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10">
        <div class="flex items-center justify-between mb-10">
          <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight">سجل التوزيعات الجماعية</h3>
          <button @click="refreshHistory" class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-slate-100 transition-all"><i class="fas fa-sync-alt"></i></button>
        </div>
        
        <div class="py-24 text-center opacity-20 text-slate-400">
          <i class="fas fa-history text-6xl mb-4"></i>
          <p class="font-black text-sm uppercase tracking-widest leading-relaxed">سيتم تفعيل عرض السجل التاريخي<br>في التحديث القادم للنظام</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from '@/composables/useToast';
import { useBranchStore } from '@/stores/branch';
import { useProductStore } from '@/stores/product/productStore';
import { useBulkAdjustmentStore } from '@/stores/bulk/bulkAdjustmentStore';
import AlertService from '@/services/AlertService';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// --- Logic Initialization (STRICTLY PRESERVED) ---
const route = useRoute();
const { showToast } = useToast();
const branchStore = useBranchStore();
const bulkAdjustmentStore = useBulkAdjustmentStore();
const productStore = useProductStore();
const activeTab = ref('form');
const isSubmitting = ref(false);
const totalQuantity = computed(() => rows.value.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0));

// Product Search Logic
const productSearch = ref('');
const products = ref([]);
const selectedProduct = ref(null);
const showDropdown = ref(false);
let searchTimeout;

// Watch for search changes to close dropdown when cleared
watch(productSearch, (val) => {
  if (!val) showDropdown.value = false;
});

const selectProduct = (p) => {
  selectedProduct.value = p;
  productSearch.value = p.name;
  showDropdown.value = false;
};
const clearProduct = () => { selectedProduct.value = null; productSearch.value = ''; percentageCache.clear(); };
const handleSearchKeydown = (e) => {
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    const firstItem = document.querySelector('[data-dropdown-item="0"]');
    if (firstItem) firstItem.focus();
  } else if (e.key === 'Escape') {
    showDropdown.value = false;
  }
};
const handleDropdownKeydown = (e, product, index) => {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault();
    selectProduct(product);
  } else if (e.key === 'ArrowDown') {
    e.preventDefault();
    const nextItem = document.querySelector(`[data-dropdown-item="${index + 1}"]`);
    if (nextItem) nextItem.focus();
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    const prevItem = document.querySelector(`[data-dropdown-item="${index - 1}"]`);
    if (prevItem) prevItem.focus();
  } else if (e.key === 'Escape') {
    showDropdown.value = false;
    document.querySelector('input[role="combobox"]')?.focus();
  }
};
const debouncedSearch = () => {
  showDropdown.value = true;
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const result = await bulkAdjustmentStore.searchProducts(productSearch.value || '');
      products.value = result.status === 'success' ? result.data : [];
    } catch { products.value = []; }
  }, 300);
};

// Branches Logic
const rows = ref([]);
const loadBranches = async () => {
  try {
    await branchStore.fetchBranches();
    rows.value = branchStore.branches.map(w => ({ id: w.id, name: w.name, quantity: 0, notes: '' }));
  } catch { rows.value = []; }
};

// Bulk Actions Logic
const submitBulk = async () => {
  const items = rows.value
    .filter(r => r.quantity && r.quantity !== 0)
    .map(r => ({ branch_id: String(r.id), quantity: Number(r.quantity), notes: r.notes || undefined }));
  
  if (!selectedProduct.value) { await AlertService.warning('اختر منتجاً أولاً', 'بيانات ناقصة'); return; }
  if (items.length === 0) { await AlertService.warning('أدخل كميات لواحد على الأقل من الفروع', 'بيانات ناقصة'); return; }
  if (items.some(item => item.quantity < 0)) { await AlertService.warning('لا يمكن إدخال كميات سالبة', 'خطأ في البيانات'); return; }
  
  // Stock validation - prevent exceeding available stock
  if (totalQuantity.value > (selectedProduct.value.stock || 0)) {
    await AlertService.warning('الكمية الإجمالية تتجاوز المخزون المتاح', 'خطأ');
    return;
  }
  
  if (items.some(item => item.quantity > 999999)) {
    if (!await AlertService.confirm('هناك كميات كبيرة جداً. هل تريد المتابعة؟', 'تأكيد')) return;
  }
  
  isSubmitting.value = true;
  try {
    const res = await bulkAdjustmentStore.adjustProduct(selectedProduct.value.id, items);
    if (res.status === 'success') {
      await AlertService.success('تم تنفيذ التوزيع بنجاح', 'نجاح العمل');
      resetQuantities();
      // ✅ تحديث cache المنتجات في POS لأن التوزيع يؤثر على المخزون
      productStore.invalidateCache();
    } else throw new Error(res.message || 'فشل التوزيع');
  } catch (e) { await AlertService.error(e.message || 'حدث خطأ غير متوقع', 'خطأ تقني'); } 
  finally { isSubmitting.value = false; }
};

// CSV Logic
const csvFile = ref(null);
const defaultProductId = ref(route.query.product_id ? Number(route.query.product_id) : null);
const onFileChange = (e) => { csvFile.value = e.target.files?.[0] || null; };
const clearFile = () => { csvFile.value = null; };

const submitCsv = async () => {
  if (!csvFile.value) { await AlertService.warning('اختر ملف CSV أولاً', 'ملف مطلوب'); return; }
  if (!csvFile.value.name.toLowerCase().endsWith('.csv')) { await AlertService.warning('يجب أن يكون الملف من نوع CSV', 'خطأ'); return; }
  
// File size validation (max 10MB)
const maxSize = 10 * 1024 * 1024; // 10MB
  if (csvFile.value.size > maxSize) {
    await AlertService.warning('حجم الملف كبير جداً. الحد الأقصى 10 ميجابايت', 'حجم الملف كبير');
    return;
  }
  
  isSubmitting.value = true;
  try {
    const res = await bulkAdjustmentStore.adjustFromCsv(csvFile.value, defaultProductId.value || undefined);
    if (res.status === 'success') {
      await AlertService.success(`تم الاستيراد: ${res.summary?.imported || 0} صف، تم تجاوز ${res.summary?.skipped || 0} صف`, 'تم الاستيراد');
      csvFile.value = null;
      // ✅ تحديث cache المنتجات في POS لأن التوزيع يؤثر على المخزون
      productStore.invalidateCache();
    } else throw new Error(res.message);
  } catch (e) { await AlertService.error(e.message || 'فشل الاستيراد'); } 
  finally { isSubmitting.value = false; }
};

// UI & Formatting Helpers
const percentageCache = new Map();
const getPercentage = (qty) => {
  const key = `qty_${qty}_${totalQuantity.value}`;
  if (percentageCache.has(key)) {
    return percentageCache.get(key);
  }
  const result = totalQuantity.value === 0 ? 0 : ((Number(qty) || 0) / totalQuantity.value * 100).toFixed(1);
  percentageCache.set(key, result);
  return result;
};
const resetQuantities = () => {
  rows.value.forEach(r => { r.quantity = 0; r.notes = ''; });
  percentageCache.clear(); // Clear cache when resetting
};
const fillEqualQuantities = () => {
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
  modal.innerHTML = `
    <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
      <h3 class="text-lg font-black text-slate-900 mb-4">توزيع متساوي</h3>
      <p class="text-sm text-slate-600 mb-4">أدخل الكمية الإجمالية للتوزيع المتساوي على جميع الفروع:</p>
      <input type="number" id="qtyInput" class="w-full h-11 border border-slate-200 rounded-xl px-4 text-sm font-black focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none" placeholder="الكمية الإجمالية" min="0" step="0.01">
      <div class="flex gap-3 mt-6">
        <button id="cancelBtn" class="flex-1 h-11 bg-slate-100 text-slate-600 rounded-xl font-black hover:bg-slate-200 transition-all">إلغاء</button>
        <button id="confirmBtn" class="flex-1 h-11 bg-blue-600 text-white rounded-xl font-black hover:bg-blue-700 transition-all">تأكيد</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  
  const input = modal.querySelector('#qtyInput');
  const cancelBtn = modal.querySelector('#cancelBtn');
  const confirmBtn = modal.querySelector('#confirmBtn');
  
  const closeModal = () => {
    document.body.removeChild(modal);
  };
  
  cancelBtn.addEventListener('click', closeModal);
  confirmBtn.addEventListener('click', () => {
    const qty = parseFloat(input.value);
    if (!isNaN(qty) && qty >= 0) {
      const perWh = qty / rows.value.length;
      rows.value.forEach(r => r.quantity = perWh);
      percentageCache.clear(); // Clear cache after updating quantities
    }
    closeModal();
  });
  
  input.focus();
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') confirmBtn.click();
    if (e.key === 'Escape') closeModal();
  });
  
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });
};
const refreshData = async () => { await loadBranches(); showToast('تم تحديث بيانات الفروع', 'success'); };
const formatFileSize = (b) => { if (b === 0) return '0 B'; const k = 1024, i = Math.floor(Math.log(b) / Math.log(k)); return parseFloat((b / Math.pow(k, i)).toFixed(2)) + ' ' + ['Bytes', 'KB', 'MB', 'GB'][i]; };
const refreshHistory = () => AlertService.info('سجل التوزيعات سيتوفر قريباً');

onMounted(async () => {
  await loadBranches();
  const pid = route.query.product_id ? Number(route.query.product_id) : null;
  if (pid) {
    try {
      const res = await bulkAdjustmentStore.searchProducts(String(pid));
      const found = res.status === 'success' && res.data.find(p => p.id === pid);
      if (found) selectProduct(found);
    } catch {}
  }
});
</script>

<style scoped>



/* Tab Styling */
.tab-pill { @apply px-8 py-3 rounded-xl text-xs font-black transition-all flex items-center gap-3 active:scale-95; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.filter-chip { @apply inline-flex items-center gap-2 bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl text-[10px] font-black border border-blue-100 shadow-sm transition-all; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Enhanced Dropdown Animation */
.fade-enter-active {
  transition: all 0.2s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(-5px);
}
.fade-leave-active {
  transition: all 0.15s ease;
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }
</style>