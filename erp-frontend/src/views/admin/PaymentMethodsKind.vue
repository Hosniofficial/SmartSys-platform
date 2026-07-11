<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-credit-card text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة طرق الدفع</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تعريف وتصنيف وسائل التحصيل المالي وربطها محاسبياً</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="load" :disabled="loading" class="w-11 h-11 bg-white border border-slate-100 rounded-xl flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90 shadow-sm" title="تحديث البيانات">
          <i class="fas fa-rotate" :class="{'animate-spin': loading}"></i>
        </button>
        <button @click="openCreate" class="h-11 px-8 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus-circle"></i> إضافة طريقة دفع
        </button>
      </div>
    </div>

    <!-- Payment Methods KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-list-ul"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الطرق</p>
            <p class="kpi-value text-slate-800">{{ methods.length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-money-bill-1"></i>
          </div>
          <div>
            <p class="kpi-label">طرق نقدية</p>
            <p class="kpi-value text-emerald-600">{{ methods.filter(m => m._kind === 'cash').length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-indigo-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="fas fa-university"></i>
          </div>
          <div>
            <p class="kpi-label">تحويلات بنكية</p>
            <p class="kpi-value text-indigo-600">{{ methods.filter(m => m._kind === 'bank' || m._kind === 'card').length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-violet-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-violet-50 text-violet-600 group-hover:bg-violet-600 group-hover:text-white transition-all">
            <i class="fas fa-link"></i>
          </div>
          <div>
            <p class="kpi-label">مربوطة بحساب</p>
            <p class="kpi-value text-violet-600">{{ methods.filter(m => m._account_id).length }}
              <span class="text-sm font-bold text-slate-300">/ {{ methods.length }}</span>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Error Banner -->
    <transition name="slide-fade">
      <div v-if="error" class="mb-6 bg-rose-50 border border-rose-100 p-5 rounded-2xl flex items-center gap-4 animate-fadeIn shadow-sm">
        <div class="w-10 h-10 bg-rose-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-rose-100"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="flex-grow">
          <p class="text-rose-900 font-black text-xs uppercase tracking-widest leading-none">خطأ في النظام</p>
          <p class="text-rose-600 text-sm font-bold mt-1.5">{{ error }}</p>
        </div>
        <button @click="error = ''" class="text-rose-300 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
      </div>
    </transition>

    <!-- Main Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">قائمة طرق الدفع النشطة</h3>
         <span v-if="lastRefreshed" class="text-[9px] font-black text-slate-300 uppercase tracking-widest">آخر مزامنة: {{ lastRefreshed }}</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5 w-16 text-center">#</th>
              <th class="px-4 py-5">طريقة الدفع</th>
              <th class="px-4 py-5">النوع الفني (Kind)</th>
              <th class="px-4 py-5">الحساب المحاسبي</th>
              <th class="px-8 py-5 text-center w-40">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="loading && !methods.length">
              <tr v-for="row in 5" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="methods.length === 0" class="text-center">
              <td colspan="5" class="py-24 opacity-20 text-slate-400">
                <i class="fas fa-credit-card text-6xl mb-4"></i>
                <p class="font-black text-sm uppercase">لم يتم تعريف أي طرق دفع حتى الآن</p>
              </td>
            </tr>
            <tr v-for="(m, idx) in methods" :key="m.id" class="hover:bg-blue-50/30 transition-all group font-bold">
              <td class="px-8 py-4 text-center text-slate-300 font-mono text-xs">{{ idx + 1 }}</td>
              <td class="px-4 py-4">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-white transition-all"><i :class="iconForKind(m._kind)"></i></div>
                  <div class="flex flex-col">
                    <span class="font-black text-slate-800 leading-none group-hover:text-blue-600 transition-colors">{{ m.name }}</span>
                    <span class="text-[10px] text-slate-400 mt-2 font-bold max-w-xs truncate italic" v-if="m.description">{{ m.description }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4">
                <select v-model="m._kind" class="form-select-modern h-10 text-xs font-black w-full max-w-[200px] border-slate-100 shadow-none">
                  <option v-for="k in kinds" :key="k" :value="k">{{ kindLabel(k) }}</option>
                </select>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-1.5">
                  <select v-model.number="m._account_id" class="form-select-modern h-10 text-[11px] font-black w-full max-w-[240px] border-slate-100 shadow-none">
                    <option :value="null">-- بدون ربط --</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                  </select>
                  <span v-if="m._account_id" class="inline-flex items-center gap-1 text-[9px] font-black text-violet-600 px-1">
                    <i class="fas fa-link"></i> مربوط
                  </span>
                  <span v-else class="inline-flex items-center gap-1 text-[9px] font-black text-amber-400 px-1">
                    <i class="fas fa-unlink"></i> غير مربوط
                  </span>
                </div>
              </td>
              <td class="px-8 py-4 text-center">
                <button @click="saveMethod(m)" :disabled="m._saving" class="h-10 px-6 rounded-xl bg-blue-600 text-white text-[10px] font-black uppercase shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-90 flex items-center justify-center gap-2 mx-auto disabled:opacity-50">
                  <BaseSpinner v-if="m._saving" :size="12" color="#fff" :margin="0" />
                  <i v-else class="fas fa-save"></i>
                  حفظ
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Payment Method Modal -->
    <transition name="modal">
      <div v-if="showCreate" class="modal-overlay">
        <div class="modal-content-modern max-w-2xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
                <i class="fas fa-plus-circle text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">إضافة طريقة دفع جديدة</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">تعريف مصدر مالي جديد للنظام</p>
              </div>
            </div>
            <button @click="closeCreate" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all"><i class="fas fa-times text-xl"></i></button>
          </div>

          <div class="p-8 space-y-8 overflow-y-auto custom-scroll max-h-[75vh]" dir="rtl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-6">
                 <div class="space-y-2 group">
                    <label class="modal-label">اسم الطريقة <span class="text-rose-500">*</span></label>
                    <div class="relative">
                      <input v-model="form.name" type="text" class="form-input-modern pr-11 font-black" placeholder="مثال: تحويل الراجحي" required />
                      <i class="fas fa-signature absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                 </div>

                 <div class="space-y-2 group">
                    <label class="modal-label">نوع الطريقة (Kind) <span class="text-rose-500">*</span></label>
                    <select v-model="form.kind" class="form-select-modern font-black text-sm">
                       <option v-for="k in kinds" :key="k" :value="k">{{ kindLabel(k) }}</option>
                    </select>
                 </div>

                 <div class="space-y-2 group">
                    <label class="modal-label">وصف توضيحي</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white outline-none focus:ring-4 focus:ring-blue-50 transition-all" placeholder="اكتب ملاحظات حول هذه الطريقة..."></textarea>
                 </div>
              </div>

              <div class="space-y-6">
                 <div class="space-y-2 group">
                    <label class="modal-label">شروط الدفع / السداد</label>
                    <div class="relative">
                       <input v-model="form.payment_terms" type="text" class="form-input-modern pr-11 font-bold" placeholder="مثال: استحقاق فوري" />
                       <i class="fas fa-clock absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                 </div>

                 <div class="p-6 bg-slate-50 rounded-[2rem] border border-slate-100 space-y-4 shadow-inner">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2"><i class="fas fa-link text-indigo-500"></i> الربط المحاسبي (اختياري)</h4>
                    <div class="space-y-3">
                       <div class="relative group/search">
                         <input v-model="accountSearch" type="text" class="h-9 w-full rounded-xl border border-slate-200 px-9 text-[10px] font-black outline-none focus:bg-white focus:border-indigo-300 transition-all" placeholder="بحث باسم أو كود الحساب..." />
                         <i class="fas fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/search:text-indigo-500 text-[10px]"></i>
                       </div>
                       <select v-model.number="form.account_id" class="form-select-modern h-10 text-[11px] font-black border-slate-200 shadow-none">
                          <option :value="null">-- بدون ربط مباشر --</option>
                          <option v-for="acc in filteredAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                       </select>
                       <p v-if="accountsLoading" class="text-[9px] font-bold text-blue-600 flex items-center gap-2 animate-pulse"><i class="fas fa-spinner fa-spin"></i> جاري مزامنة شجرة الحسابات...</p>
                    </div>
                 </div>
              </div>
            </div>
          </div>

          <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-4 shrink-0">
            <button @click="closeCreate" class="px-8 py-3 rounded-xl border-2 border-slate-100 font-black text-slate-400 hover:bg-white transition-all text-xs uppercase tracking-widest">إلغاء</button>
            <button @click="create" :disabled="creating" class="px-12 py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3">
              <BaseSpinner v-if="creating" :size="14" color="#fff" :margin="0" />
              <span>إضافة الطريقة الآن</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useAccountStore } from '@/stores/account/accountStore';
import AlertService from '@/services/AlertService';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';;

const paymentStore = usePaymentStore();
const accountStore = useAccountStore();
const loading = ref(false);
const error = ref('');
const methods = ref([]);
const accounts = ref([]);
const lastRefreshed = ref('');
const kinds = ['cash','bank','card','credit','wallet','other'];

const form = ref({ name: '', kind: 'cash', description: '', payment_terms: '', account_id: null });
const creating = ref(false);
const showCreate = ref(false);
const accountsLoading = ref(false);
const accountSearch = ref('');
const accountGroup = ref('10');

const filteredAccounts = computed(() => {
  const q = accountSearch.value.trim().toLowerCase();
  const g = accountGroup.value.trim();
  return accounts.value.filter(a => {
    const matchGroup = g ? String(a.account_group_code) === g : true;
    if (!q) return matchGroup;
    const text = `${a.code || ''} ${a.name || ''}`.toLowerCase();
    return matchGroup && text.includes(q);
  });
});

function kindLabel(k) {
  switch (k) {
    case 'cash': return 'نقدي';
    case 'bank': return 'تحويل بنكي';
    case 'card': return 'بطاقة';
    case 'credit': return 'آجل/دين';
    case 'wallet': return 'محفظة إلكترونية';
    default: return 'أخرى';
  }
}

function iconForKind(k) {
  const icons = { cash: 'fas fa-money-bill-wave text-emerald-500', bank: 'fas fa-university text-indigo-500', card: 'fas fa-credit-card text-blue-500', credit: 'fas fa-file-invoice-dollar text-amber-500', wallet: 'fas fa-wallet text-purple-500' };
  return icons[k] || 'fas fa-coins text-slate-300';
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const result = await paymentStore.fetchPaymentMethods({ force: true });
    if (result.status !== 'success') throw new Error(result.message || 'فشل تحميل طرق الدفع');
    const items = (Array.isArray(result.data) ? result.data : [])
      .filter(x => !x.is_global); // صفحة الإدارة تعرض طرق الـ tenant فقط — لا الـ global templates
    methods.value = items.map(x => ({
      ...x,
      _kind: (x.kind || 'other').toLowerCase(),
      _account_id: x.account_id ? Number(x.account_id) : null,
      _saving: false,
    }));
    lastRefreshed.value = new Date().toLocaleString();
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'فشل تحميل طرق الدفع';
  } finally {
    loading.value = false;
  }
}

async function saveMethod(m) {
  if (!m || !m.id) return;
  m._saving = true;
  try {
    const result = await paymentStore.updatePaymentMethodKind(m.id, m._kind, m._account_id ?? null);
    if (result.status !== 'success') {
      error.value = result.message;
    } else {
      await AlertService.success('تم حفظ التعديلات بنجاح', '');
    }
    await load();
  } catch (e) {
    await AlertService.error(e?.response?.data?.message || e?.message || 'فشل حفظ الطريقة', 'خطأ');
  } finally {
    m._saving = false;
  }
}

function resetForm() {
  form.value = { name: '', kind: 'cash', description: '', payment_terms: '', account_id: null };
}

async function create() {
  if (!form.value.name) {
    await AlertService.warning('يرجى إدخال الاسم', 'حقل مطلوب');
    return;
  }
  creating.value = true;
  try {
    const payload = { ...form.value };
    if (!payload.account_id) payload.account_id = null;
    payload.global = false;
    const result = await paymentStore.createPaymentMethod(payload);
    if (result.status !== 'success') {
      error.value = result.message;
    }
    resetForm();
    await load();
    closeCreate();
  } catch (e) {
    await AlertService.error(e?.response?.data?.message || e?.message || 'فشل إضافة طريقة الدفع', 'خطأ');
  } finally {
    creating.value = false;
  }
}

async function loadAccounts() {
  accountsLoading.value = true;
  try {
    const result = await accountStore.fetchAccounts({ force: true });
    accounts.value = result.status === 'success' ? (result.data || []) : [];
  } catch (e) {
    accounts.value = [];
  } finally {
    accountsLoading.value = false;
  }
}

function openCreate() {
  resetForm();
  showCreate.value = true;
}

function closeCreate() {
  showCreate.value = false;
}

onMounted(async () => {
  await Promise.all([load(), loadAccounts()]);
});
</script>

<style scoped>



.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight; }

.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>