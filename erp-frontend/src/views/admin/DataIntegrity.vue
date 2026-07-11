<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-rose-600 rounded-2xl flex items-center justify-center shadow-xl shadow-rose-100 text-white shrink-0">
          <i class="fas fa-shield-heart text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">سلامة البيانات والربط المحاسبي</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">رصد ومعالجة الفجوات في ربط العملاء والموردين بدليل الحسابات</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="refreshAll" :disabled="loading" class="h-11 px-8 rounded-xl bg-slate-900 text-white text-xs font-black shadow-lg shadow-slate-200 hover:bg-black transition-all active:scale-95 flex items-center gap-2">
          <BaseSpinner v-if="loading" :size="16" color="#fff" :margin="0" />
          <i v-else class="fas fa-sync-alt"></i>
          تحديث كامل السجلات
        </button>
      </div>
    </div>

    <!-- Integrity Status KPIs -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
      <div class="kpi-card group border-l-4 border-l-rose-500 bg-white">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl"><i class="fas fa-user-slash"></i></div>
            <div>
              <p class="kpi-label">عملاء غير مرتبطين محاسبياً</p>
              <p class="kpi-value text-rose-600">{{ customers.length }}</p>
            </div>
          </div>
          <div v-if="customers.length > 0" class="status-badge bg-rose-100 text-rose-700 animate-pulse">يتطلب إجراء</div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500 bg-white">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl"><i class="fas fa-truck-pickup"></i></div>
            <div>
              <p class="kpi-label">موردون غير مرتبطين محاسبياً</p>
              <p class="kpi-value text-amber-600">{{ suppliers.length }}</p>
            </div>
          </div>
          <div v-if="suppliers.length > 0" class="status-badge bg-amber-100 text-amber-700 animate-pulse">يتطلب إجراء</div>
        </div>
      </div>
    </section>

    <!-- Main Content Panels -->
    <div class="grid grid-cols-1 gap-10 max-w-6xl mx-auto">
      
      <!-- Customers Panel -->
      <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
        <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
             <span class="w-1.5 h-6 bg-rose-500 rounded-full"></span>
             <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">العملاء المفقود ربطهم</h2>
          </div>
          <div class="flex items-center gap-2">
            <button @click="fetchCustomers" :disabled="loadingCustomers" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90 shadow-sm">
               <i class="fas fa-rotate" :class="{'animate-spin': loadingCustomers}"></i>
            </button>
            <button @click="fixAllCustomers" :disabled="loadingCustomers || customers.length === 0 || bulkCustomersInProgress" 
              class="h-10 px-6 rounded-xl bg-rose-600 text-white text-[10px] font-black uppercase shadow-lg shadow-rose-100 hover:bg-rose-700 transition-all active:scale-95 disabled:opacity-40">
              <i class="fas fa-magic ml-1.5"></i>
              {{ bulkCustomersInProgress ? `جاري المعالجة (${bulkCustomersDone}/${bulkCustomersTotal})` : 'إصلاح جميع العملاء' }}
            </button>
          </div>
        </div>

        <div v-if="errorCustomers" class="px-8 py-4 bg-rose-50 text-rose-700 text-xs font-bold border-b border-rose-100 animate-fadeIn">{{ errorCustomers }}</div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-right text-sm">
            <thead>
              <tr class="bg-slate-50/50 text-slate-400 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-8 py-4 w-16">#</th>
                <th class="px-4 py-4">اسم العميل</th>
                <th class="px-4 py-4">رقم الهاتف</th>
                <th class="px-4 py-4">الرصيد الدفتري</th>
                <th class="px-8 py-4 text-center w-32">إجراء فردي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
              <!-- Skeleton loading for customers table -->
              <template v-if="loadingCustomers">
                <tr v-for="row in 4" :key="row">
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                  <td class="px-6 py-4 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                </tr>
              </template>
              <tr v-else-if="customers.length === 0" class="text-center">
                <td colspan="5" class="py-16 opacity-30 text-slate-400">
                   <i class="fas fa-check-double text-4xl mb-3 text-emerald-500"></i>
                   <p class="font-black text-sm uppercase tracking-widest">جميع العملاء مرتبطون بشكل سليم</p>
                </td>
              </tr>
              <tr v-for="(c, idx) in customers" :key="c.id" class="hover:bg-rose-50/30 transition-all">
                <td class="px-8 py-4 text-slate-300 font-mono text-xs">{{ idx + 1 }}</td>
                <td class="px-4 py-4 font-black text-slate-800">{{ c.name }}</td>
                <td class="px-4 py-4 text-xs text-slate-400 font-mono">{{ c.phone || '-' }}</td>
                <td class="px-4 py-4 font-mono tracking-tighter text-slate-900">{{ formatCurrency(c.balance) }}</td>
                <td class="px-8 py-4 text-center">
                  <button @click="ensureCustomerAccount(c)" :disabled="processingCustomerIds.has(c.id)" 
                    class="px-4 py-1.5 rounded-lg bg-rose-50 text-rose-600 text-[10px] font-black uppercase hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-90 disabled:opacity-40">
                    {{ processingCustomerIds.has(c.id) ? 'جاري...' : 'إصلاح الربط' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Suppliers Panel -->
      <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
        <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
             <span class="w-1.5 h-6 bg-amber-500 rounded-full"></span>
             <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">الموردون المفقود ربطهم</h2>
          </div>
          <div class="flex items-center gap-2">
            <button @click="fetchSuppliers" :disabled="loadingSuppliers" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90 shadow-sm">
               <i class="fas fa-rotate" :class="{'animate-spin': loadingSuppliers}"></i>
            </button>
            <button @click="fixAllSuppliers" :disabled="loadingSuppliers || suppliers.length === 0 || bulkSuppliersInProgress" 
              class="h-10 px-6 rounded-xl bg-amber-600 text-white text-[10px] font-black uppercase shadow-lg shadow-amber-100 hover:bg-amber-700 transition-all active:scale-95 disabled:opacity-40">
              <i class="fas fa-wrench ml-1.5"></i>
              {{ bulkSuppliersInProgress ? `جاري المعالجة (${bulkSuppliersDone}/${bulkSuppliersTotal})` : 'إصلاح جميع الموردين' }}
            </button>
          </div>
        </div>

        <div v-if="errorSuppliers" class="px-8 py-4 bg-rose-50 text-rose-700 text-xs font-bold border-b border-rose-100 animate-fadeIn">{{ errorSuppliers }}</div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-right text-sm">
            <thead>
              <tr class="bg-slate-50/50 text-slate-400 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-8 py-4 w-16">#</th>
                <th class="px-4 py-4">اسم المورد</th>
                <th class="px-4 py-4">رقم الهاتف</th>
                <th class="px-4 py-4">الرصيد الدفتري</th>
                <th class="px-8 py-4 text-center w-32">إجراء فردي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
              <!-- Skeleton loading for suppliers table -->
              <template v-if="loadingSuppliers">
                <tr v-for="row in 4" :key="row">
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                  <td class="px-6 py-4"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                  <td class="px-6 py-4 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                </tr>
              </template>
              <tr v-else-if="suppliers.length === 0" class="text-center">
                <td colspan="5" class="py-16 opacity-30 text-slate-400">
                   <i class="fas fa-clipboard-check text-4xl mb-3 text-emerald-500"></i>
                   <p class="font-black text-sm uppercase tracking-widest">جميع الموردين مرتبطون بشكل سليم</p>
                </td>
              </tr>
              <tr v-for="(s, idx) in suppliers" :key="s.id" class="hover:bg-amber-50/30 transition-all">
                <td class="px-8 py-4 text-slate-300 font-mono text-xs">{{ idx + 1 }}</td>
                <td class="px-4 py-4 font-black text-slate-800">{{ s.name }}</td>
                <td class="px-4 py-4 text-xs text-slate-400 font-mono">{{ s.phone || '-' }}</td>
                <td class="px-4 py-4 font-mono tracking-tighter text-slate-900">{{ formatCurrency(s.balance) }}</td>
                <td class="px-8 py-4 text-center">
                  <button @click="ensureSupplierAccount(s)" :disabled="processingSupplierIds.has(s.id)" 
                    class="px-4 py-1.5 rounded-lg bg-amber-50 text-amber-600 text-[10px] font-black uppercase hover:bg-amber-600 hover:text-white transition-all shadow-sm active:scale-90 disabled:opacity-40">
                    {{ processingSupplierIds.has(s.id) ? 'جاري...' : 'إصلاح الربط' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAdminStore } from '@/stores/admin/adminStore';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';;

const adminStore = useAdminStore();
const customers = ref([]);
const suppliers = ref([]);
const loadingCustomers = ref(false);
const loadingSuppliers = ref(false);
const errorCustomers = ref('');
const errorSuppliers = ref('');
const processingCustomerIds = ref(new Set());
const processingSupplierIds = ref(new Set());

const bulkCustomersInProgress = ref(false);
const bulkSuppliersInProgress = ref(false);
const bulkCustomersDone = ref(0);
const bulkSuppliersDone = ref(0);
const bulkCustomersTotal = ref(0);
const bulkSuppliersTotal = ref(0);

function formatCurrency(val) {
  const n = Number(val || 0);
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'EGP' }).format(n);
}

async function fetchCustomers() {
  loadingCustomers.value = true;
  errorCustomers.value = '';
  const result = await adminStore.fetchCustomersMissingAccounts();
  if (result.status === 'success') {
    customers.value = result.data;
  } else {
    errorCustomers.value = result.message;
  }
  loadingCustomers.value = false;
}

async function fetchSuppliers() {
  loadingSuppliers.value = true;
  errorSuppliers.value = '';
  const result = await adminStore.fetchSuppliersMissingAccounts();
  if (result.status === 'success') {
    suppliers.value = result.data;
  } else {
    errorSuppliers.value = result.message;
  }
  loadingSuppliers.value = false;
}

async function refreshAll() {
  loading.value = true;
  try {
    await Promise.all([fetchCustomers(), fetchSuppliers()]);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  refreshAll();
});

async function ensureCustomerAccount(c) {
  if (!c?.id) return;
  processingCustomerIds.value.add(c.id);
  errorCustomers.value = '';
  const result = await adminStore.ensureCustomerAccount(c.id);
  if (result.status === 'success') {
    customers.value = customers.value.filter(item => item.id !== c.id);
  } else {
    errorCustomers.value = result.message;
  }
  processingCustomerIds.value.delete(c.id);
  processingCustomerIds.value = new Set(processingCustomerIds.value);
}

async function ensureSupplierAccount(s) {
  if (!s?.id) return;
  processingSupplierIds.value.add(s.id);
  errorSuppliers.value = '';
  const result = await adminStore.ensureSupplierAccount(s.id);
  if (result.status === 'success') {
    suppliers.value = suppliers.value.filter(item => item.id !== s.id);
  } else {
    errorSuppliers.value = result.message;
  }
  processingSupplierIds.value.delete(s.id);
  processingSupplierIds.value = new Set(processingSupplierIds.value);
}

async function fixAllCustomers() {
  if (bulkCustomersInProgress.value || customers.value.length === 0) return;
  bulkCustomersInProgress.value = true;
  bulkCustomersDone.value = 0;
  bulkCustomersTotal.value = customers.value.length;
  errorCustomers.value = '';
  const toFix = [...customers.value];
  for (const c of toFix) {
    const result = await adminStore.ensureCustomerAccount(c.id);
    if (result.status === 'success') {
      customers.value = customers.value.filter(item => item.id !== c.id);
    } else {
      const msg = result.message || 'فشل إصلاح عميل';
      errorCustomers.value = (errorCustomers.value ? errorCustomers.value + ' | ' : '') + `ID ${c.id}: ${msg}`;
    }
    bulkCustomersDone.value++;
    }
  bulkCustomersInProgress.value = false;
}

async function fixAllSuppliers() {
  if (bulkSuppliersInProgress.value || suppliers.value.length === 0) return;
  bulkSuppliersInProgress.value = true;
  bulkSuppliersDone.value = 0;
  bulkSuppliersTotal.value = suppliers.value.length;
  errorSuppliers.value = '';
  const toFix = [...suppliers.value];
  for (const s of toFix) {
    const result = await adminStore.ensureSupplierAccount(s.id);
    if (result.status === 'success') {
      suppliers.value = suppliers.value.filter(item => item.id !== s.id);
    } else {
      const msg = result.message || 'فشل إصلاح مورد';
      errorSuppliers.value = (errorSuppliers.value ? errorSuppliers.value + ' | ' : '') + `ID ${s.id}: ${msg}`;
    }
  bulkSuppliersDone.value++;  }
  bulkSuppliersInProgress.value = false;
}
</script>

<style scoped>



.kpi-card { @apply p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-3xl font-black leading-none tracking-tight font-mono; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>