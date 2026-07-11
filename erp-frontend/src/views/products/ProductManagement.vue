<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-boxes text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة المنتجات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تنظيم كتالوج المنتجات، الأسعار، وتصنيف المنتجات</p>
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
            <option v-for="wh in branchStore.branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
          </select>
          <i class="fas fa-warehouse absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        </div>

        <div class="h-6 w-px bg-slate-100 mx-1 hidden sm:block"></div>

        <div class="flex items-center gap-2">
          <button @click="openAddModal" class="h-11 px-6 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
            <i class="fas fa-plus"></i> إضافة منتج جديد
          </button>
          <button @click="exportProducts" class="w-11 h-11 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-50 transition-all text-slate-400 hover:text-emerald-500" title="تصدير البيانات">
            <i class="fas fa-file-export"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Product Catalog KPIs — stock KPIs moved to إدارة المخزون -->
    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-box"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الأصناف</p>
            <p class="kpi-value text-slate-800">{{ (Array.isArray(products) ? products : []).length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-check-double"></i>
          </div>
          <div>
            <p class="kpi-label">منتجات نشطة</p>
            <p class="kpi-value text-emerald-600">{{ (Array.isArray(products) ? products : []).filter(p => p.active === 1).length }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content Grid -->
    <div class="flex flex-col gap-6">
      
      <!-- Filter & Search Bar -->
      <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="flex-grow">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block px-1">تصفية حسب التصنيف</label>
          <div class="flex flex-wrap gap-2">
            <button @click="selectedCategory = ''" :class="[selectedCategory === '' ? 'cat-chip-active' : 'cat-chip']">
              <span>الكل</span>
              <span class="text-[9px] opacity-60 font-black">{{ (Array.isArray(products) ? products : []).length }}</span>
            </button>
            <button v-for="cat in categories" :key="cat.id" @click="selectedCategory = cat.id" :class="[selectedCategory === cat.id ? 'cat-chip-active' : 'cat-chip']">
              <span>{{ cat.name }}</span>
              <span class="text-[9px] opacity-60 font-black">{{ (Array.isArray(products) ? products : []).filter(p => p.category_id == cat.id).length }}</span>
            </button>
          </div>
        </div>

        <div class="lg:w-80 relative group">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block px-1">بحث سريع</label>
          <input v-model="searchQuery" type="text" class="form-input-modern pr-11" placeholder="ابحث بـ: اسم المنتج أو الباركود..." />
          <i class="fas fa-search absolute right-4 top-[42px] text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <!-- Products Table Area -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden min-h-[400px] relative">
        
        <!-- Loading State (Skeleton) -->
        <div v-if="isLoading" class="p-8 space-y-6">
          <div class="flex justify-between items-center bg-slate-50 p-4 rounded-2xl">
            <div class="flex gap-4 items-center">
              <BaseSkeleton type="circle" size="lg" />
              <div class="space-y-2">
                <BaseSkeleton type="text" size="sm" width="12rem" />
                <BaseSkeleton type="text" size="sm" width="6rem" />
              </div>
            </div>
            <BaseSkeleton type="rect" size="sm" width="8rem" height="2rem" />
          </div>
          <div v-for="i in 5" :key="i" class="flex gap-4 items-center py-3">
            <BaseSkeleton type="circle" size="sm" animation="shimmer" />
            <BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" />
            <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
            <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
            <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="py-20 text-center px-6">
          <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-exclamation-triangle text-3xl"></i>
          </div>
          <h3 class="text-xl font-black text-slate-800">{{ error }}</h3>
          <button @click="fetchAllData" class="mt-4 px-8 py-3 bg-rose-600 text-white rounded-xl font-black text-xs shadow-lg shadow-rose-100 hover:bg-rose-700 transition-all">إعادة المحاولة</button>
        </div>

        <!-- Data Table -->
        <template v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
              <thead>
                <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                  <th class="px-6 py-5">بيانات المنتج</th>
                  <th class="px-4 py-5">SKU</th>
                  <th class="px-4 py-5">التصنيف</th>
                  <th class="px-4 py-5">سعر البيع</th>
                  <th class="px-4 py-5 text-center">الحالة</th>
                  <th class="px-6 py-5 text-center">الإجراءات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-if="filteredProducts.length === 0" class="text-center">
                  <td colspan="6" class="py-24">
                    <div class="flex flex-col items-center opacity-20 text-slate-400">
                      <i class="fas fa-box-open text-6xl mb-4"></i>
                      <p class="font-black text-sm uppercase">لا توجد منتجات تطابق معايير البحث</p>
                    </div>
                  </td>
                </tr>
                <tr v-else v-for="product in paginatedProducts" :key="product.id" class="hover:bg-blue-50/30 transition-all group">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-4">
                      <div class="w-11 h-11 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 border border-slate-100 group-hover:bg-white group-hover:border-blue-200 transition-all">
                        <i class="fas fa-box text-sm"></i>
                      </div>
                      <div class="flex flex-col">
                        <span class="font-black text-slate-800 leading-none">{{ product.name }}</span>
                        <span class="text-[10px] font-bold text-slate-400 font-mono mt-1.5 uppercase tracking-tighter">BARCODE: {{ product.barcode || '--' }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-4">
                    <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg font-mono">{{ product.product_code || '--' }}</span>
                  </td>
                  <td class="px-4 py-4 font-bold text-slate-500">
                    <span class="text-xs bg-slate-100 px-2 py-1 rounded-lg">{{ product.category_name || 'غير مصنف' }}</span>
                  </td>
                  <td class="px-4 py-4 font-black text-blue-600 text-base">{{ formatCurrencyLocale(product.sale_price, 2) }}</td>
                  <td class="px-4 py-4 text-center">
                    <div :class="[product.active === 1 ? 'text-emerald-500' : 'text-slate-300']" class="text-xs font-black flex items-center justify-center gap-1.5">
                      <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                      {{ product.active === 1 ? 'نشط' : 'معطل' }}
                    </div>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <button @click="openEditModal(product)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="تعديل">
                        <i class="fas fa-pen text-xs"></i>
                      </button>
                      <button @click="handleDelete(product.id)" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95" title="حذف">
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
              عرض <span class="text-slate-800">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> - <span class="text-slate-800">{{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }}</span> من إجمالي <span class="text-slate-800">{{ filteredProducts.length }}</span> صنف
            </div>
            
            <div v-if="totalPages > 1" class="flex items-center gap-1">
              <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="pagination-btn">
                <i class="fas fa-angle-right"></i>
              </button>
              <div class="flex items-center gap-1 mx-2">
                <template v-for="page in visiblePages" :key="page">
                  <span v-if="page === '...'" class="w-9 h-9 flex items-center justify-center text-slate-400 text-xs font-black">...</span>
                  <button v-else @click="goToPage(page)"
                    :class="[currentPage === page ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-white text-slate-400 hover:bg-slate-50']"
                    class="w-9 h-9 rounded-xl text-xs font-black transition-all">
                    {{ page }}
                  </button>
                </template>
              </div>
              <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="pagination-btn">
                <i class="fas fa-angle-left"></i>
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Form Modal (Logic Preserved) -->
    <transition name="modal-fade">
      <div v-if="showFormModal" class="fixed inset-0 z-[100] flex items-start sm:items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white w-full max-w-3xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-modalIn border border-white my-4 sm:my-auto max-h-[85vh] flex flex-col">
          <ProductForm
            :product-data="selectedProduct"
            :categories="categories"
            :branch-id="selectedBranch"
            :branches="branches"
            @close="showFormModal = false"
            @success="handleFormSuccess"
            @category-added="handleCategoryAdded"
          />
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import getLocalDateISO from '@/utils/date';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import ProductForm from '../../components/ProductForm.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import AlertService from '@/services/AlertService';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCatalogStore } from '@/stores/catalog/catalogStore';
import { useProductStore } from '@/stores/product/productStore';

// --- Services & Composables ---
const { showToast } = useToast();
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const branchStore = useBranchStore();
const catalogStore = useCatalogStore();
const productStore = useProductStore();

// --- State Management (ALL ORIGINAL REFS PRESERVED) ---
const products = ref([]);
const categories = computed(() => catalogStore.getCategoriesForBranch(branchStore.selectedBranchId));
const branches = computed(() => branchStore.branches);
const showFormModal = ref(false);
const selectedProduct = ref(null);
const selectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
});
const isLoading = ref(true);
const error = ref(null);
const searchQuery = ref('');
const selectedCategory = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// --- API Logic (PRESERVED) ---
const handleBranchChange = () => {
  fetchAllData(true);
};

const fetchAllData = async (force = false) => {
  isLoading.value = true;
  error.value = null;
  try {
    const branchId = selectedBranch.value ? String(selectedBranch.value) : null;

    const calls = [productStore.fetchProducts({ branchId, force })];
    if (branchId) calls.push(catalogStore.fetchCategories(branchId, { force }));

    const [productsResponse] = await Promise.all(calls);
    if (productsResponse && productsResponse.status === 'success') {
      products.value = productsResponse.data || [];
    }
  } catch (err) {
    error.value = err.message || 'فشل في تحميل البيانات';
    showToast(error.value, 'error');
  } finally { isLoading.value = false; }
};

const exportProducts = () => {
  const productList = Array.isArray(products.value) ? products.value : Object.values(products.value).flat();
  if (!productList.length) { showToast('لا توجد منتجات للتصدير', 'warning'); return; }
  const rows = [['ID', 'الاسم', 'الباركود', 'التصنيف', 'سعر البيع', 'الحالة']];
  productList.forEach(p => {
    const cat = categories.value.find(c => c.id === p.category_id)?.name || 'غير مصنف';
    rows.push([p.id, p.name, p.barcode || '', cat, p.sale_price, p.active === 1 ? 'نشط' : 'غير نشط']);
  });
  const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `products_${getLocalDateISO()}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  showToast('تم تصدير المنتجات بنجاح', 'success');
};

// --- Computed Logic (PRESERVED) ---
const filteredProducts = computed(() => {
  const productList = Array.isArray(products.value) ? products.value : Object.values(products.value).flat();
  let result = [...productList];
  if (selectedCategory.value) result = result.filter(p => p.category_id == selectedCategory.value);
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    result = result.filter(p => p.name.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q)));
  }
  return result;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / itemsPerPage.value)));

const visiblePages = computed(() => {
  const total = totalPages.value;
  const current = currentPage.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  
  const pages = new Set([1, total]);
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.add(i);
  }
  
  const sorted = [...pages].sort((a, b) => a - b);
  const result = [];
  for (let i = 0; i < sorted.length; i++) {
    result.push(sorted[i]);
    if (i < sorted.length - 1 && sorted[i + 1] - sorted[i] > 1) {
      result.push('...');
    }
  }
  return result;
});

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredProducts.value.slice(start, start + itemsPerPage.value);
});

// --- Watchers (PRESERVED) ---
watch(selectedBranch, async (newId, oldId) => {
  if (!newId || newId === oldId) return;
  isLoading.value = true;
  error.value = null;
  try {
    const [prodRes] = await Promise.all([
      productStore.fetchProducts({ branchId: newId }),
      catalogStore.fetchCategories(newId)
    ]);
    if (prodRes && prodRes.status === 'success') {
      products.value = prodRes.data || [];
    }
    selectedCategory.value = '';
    currentPage.value = 1;
  } catch { showToast('فشل تحميل البيانات', 'error'); }
  finally { isLoading.value = false; }
});

watch([searchQuery, selectedCategory], () => currentPage.value = 1);

// --- Handlers (PRESERVED) ---
const openAddModal = () => { selectedProduct.value = null; showFormModal.value = true; };
const openEditModal = async (p) => { 
  try {
    // جلب تفاصيل المنتج الكاملة
    const response = await productStore.getProductDetail(p.id);
    if (response.status === 'success') {
      const fullData = response.data;
      // تسطيح البيانات المتداخلة
      selectedProduct.value = {
        ...fullData,
        // التسعير
        sale_price: fullData.pricing?.sale_price ?? fullData.sale_price,
        purchase_price: fullData.pricing?.purchase_price ?? fullData.purchase_price,
        min_sale_price: fullData.pricing?.min_sale_price ?? fullData.min_sale_price ?? 0,
        fixed_discount_percentage: fullData.pricing?.fixed_discount_percentage ?? fullData.fixed_discount_percentage ?? 0,
        // المخزون
        current_quantity: fullData.inventory?.current_quantity ?? fullData.current_quantity ?? 0,
        unit_id: fullData.inventory?.unit_id ?? fullData.unit_id,
        unit_name: fullData.inventory?.unit_name ?? fullData.unit_name,
        min_quantity: fullData.inventory?.min_quantity ?? fullData.min_quantity ?? 0,
        max_quantity: fullData.inventory?.max_quantity ?? fullData.maximum_quantity ?? 0,
        // الإعدادات
        product_type: fullData.configuration?.product_type ?? fullData.product_type ?? 'stock',
        active: fullData.configuration?.active ?? fullData.active ?? 1,
        has_expiry_date: fullData.configuration?.has_expiry_date ?? fullData.has_expiry_date ?? false,
        has_batch_number: fullData.configuration?.has_batch_number ?? fullData.has_batch_number ?? false,
        has_serial_number: fullData.configuration?.has_serial_number ?? fullData.has_serial_number ?? false,
        expiry_date: fullData.configuration?.expiry_date ?? fullData.expiry_date ?? '',
        batch_number: fullData.configuration?.batch_number ?? fullData.batch_number ?? '',
        serial_number: fullData.configuration?.serial_number ?? fullData.serial_number ?? '',
      };
      showFormModal.value = true;
    } else {
      showToast('فشل في جلب بيانات المنتج', 'error');
    }
  } catch (error) {
    showToast(error.message || 'فشل في جلب بيانات المنتج', 'error');
  }
};
const handleFormSuccess = () => { 
  showFormModal.value = false; 
  fetchAllData(true); 
  // ✅ تحديث cache المنتجات في POS
  productStore.invalidateCache();
};
const handleCategoryAdded = async () => {
  await catalogStore.fetchCategories(selectedBranch.value, { force: true });
};

const goToPage = (p) => { if (p >= 1 && p <= totalPages.value) currentPage.value = p; };

const handleDelete = async (id) => {
  if (await AlertService.confirm('هل أنت متأكد من حذف هذا المنتج؟ سيتم إخفاؤه من نقاط البيع.', 'حذف المنتج')) {
    try {
      const response = await productStore.deleteProduct(id);
      if (response.status === 'success') {
        showToast('تم حذف المنتج بنجاح', 'success');
        
        // ✅ تحديث فوري للقائمة
        await fetchAllData(true);
        
        // ✅ تحديث cache المنتجات في POS
        productStore.invalidateCache();
      }
    } catch (e) { showToast(e.response?.data?.message || 'فشل الحذف', 'error'); }
  }
};

onMounted(async () => {
  await branchStore.initialize();
  await Promise.all([fetchSettings(), fetchAllData()]);
});
</script>

<style scoped>

/* KPI Cards */
.kpi-card { @apply bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm inline-block; }
.pagination-btn { @apply w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed; }

/* Category Chips */
.cat-chip { @apply px-4 py-2 rounded-xl bg-slate-50 text-slate-500 text-xs font-bold border border-transparent hover:bg-slate-100 hover:text-slate-700 transition-all flex items-center gap-2; }
.cat-chip-active { @apply px-5 py-2 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 flex items-center gap-2; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.modal-fade-enter-active { transition: opacity 0.3s ease; }
.modal-fade-enter-from { opacity: 0; }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>