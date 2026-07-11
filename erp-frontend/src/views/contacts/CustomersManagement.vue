<template>
  <div class="min-h-screen bg-[#f8fafc] text-slate-700 animate-fadeIn">
    <!-- Branch Indicator Breadcrumb -->
    <BranchIndicatorBreadcrumb 
      pageName="إدارة العملاء" 
      :currentBranchName="currentBranchName"
    />
    
    <div class="p-4 lg:p-8">
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-users text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة العملاء</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تتبع حسابات العملاء، الذمم المدينة، وسجل الدفعات</p>
        </div>
      </div>
      
      <div class="flex flex-wrap items-center gap-3 bg-white p-2 rounded-[1.5rem] border border-slate-100 shadow-sm">
        <!-- branch Selector -->
        <div class="relative min-w-[180px]">
          <select 
            v-model="selectedBranch" 
            @change="onBranchChange"
            class="w-full h-11 pr-10 pl-4 rounded-xl border-slate-200 bg-slate-50 focus:bg-white border outline-none text-xs font-black transition-all appearance-none cursor-pointer"
          >
            <option :value="null">كل الفروع</option>
            <option v-for="wh in branches" :key="wh.id" :value="String(wh.id)">{{ wh.name }}</option>
          </select>
          <i class="fas fa-building absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        </div>

        <div class="h-6 w-px bg-slate-100 mx-1 hidden sm:block"></div>

        <button @click="openAddModal" class="h-11 px-6 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-user-plus"></i> إضافة عميل جديد
        </button>
      </div>
    </div>

    <!-- KPIs Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
      <div v-for="kpi in kpis" :key="kpi.title" class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i :class="kpi.icon"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">{{ kpi.title }}</p>
            <p class="kpi-value text-slate-800">{{ kpi.value }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Toolbar: Search & View Toggle -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-4 mb-8 flex flex-col md:flex-row md:items-center gap-4">
      <div class="relative flex-grow group">
        <input type="text" v-model="search" class="form-input-modern pr-11" placeholder="ابحث بـ: اسم العميل أو رقم الهاتف..." />
        <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
      </div>
      
      <div class="flex items-center gap-3 shrink-0">
        <div class="flex items-center bg-slate-50 p-1 rounded-xl border border-slate-100">
          <button @click="viewMode = 'cards'" :class="[viewMode === 'cards' ? 'bg-white text-blue-600 shadow-sm font-black' : 'text-slate-400']" class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
            <i class="fas fa-th-large"></i> بطاقات
          </button>
          <button @click="viewMode = 'table'" :class="[viewMode === 'table' ? 'bg-white text-blue-600 shadow-sm font-black' : 'text-slate-400']" class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
            <i class="fas fa-list"></i> جدول
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content State: Skeleton Loading -->
    <template v-if="isLoading">
      <!-- Header skeleton -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <div class="flex items-center gap-4">
          <BaseSkeleton type="circle" size="xl" />
          <div class="space-y-2">
            <BaseSkeleton type="text" size="lg" width="16rem" />
            <BaseSkeleton type="text" size="sm" width="12rem" />
          </div>
        </div>
        <BaseSkeleton type="rect" size="md" width="10rem" height="2.75rem" />
      </div>
      <!-- KPIs skeleton -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div v-for="i in 3" :key="i" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <div class="flex items-center gap-4">
            <BaseSkeleton type="circle" size="lg" />
            <div class="space-y-2">
              <BaseSkeleton type="text" size="sm" width="6rem" />
              <BaseSkeleton type="text" size="lg" width="8rem" />
            </div>
          </div>
        </div>
      </div>
      <!-- Toolbar skeleton -->
      <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-4 mb-8">
        <div class="flex gap-4">
          <BaseSkeleton type="rect" size="sm" width="20rem" height="2.5rem" />
          <BaseSkeleton type="rect" size="sm" width="8rem" height="2.5rem" />
        </div>
      </div>
      <!-- Table skeleton -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden p-8">
        <div v-for="i in 6" :key="i" class="flex gap-4 items-center py-4 border-b border-slate-50">
          <BaseSkeleton type="circle" size="sm" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="12rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
          <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
          <BaseSkeleton type="circle" size="sm" animation="shimmer" />
        </div>
      </div>
    </template>

    <div v-else-if="!filteredContacts.length" class="py-24 text-center px-6">
      <div class="flex flex-col items-center opacity-20 text-slate-400">
        <i class="fas fa-users-slash text-6xl mb-4"></i>
        <p class="font-black text-lg uppercase tracking-widest">لا يوجد عملاء مضافين حالياً</p>
      </div>
      <button @click="fetchContacts" class="mt-6 text-blue-600 font-black text-xs hover:underline uppercase tracking-widest">إعادة تحميل البيانات</button>
    </div>

    <!-- View Mode: Cards -->
    <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="contact in filteredContacts" :key="contact.id" class="contact-card-modern group">
        <!-- Card Top: Info -->
        <div class="flex items-start justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
              <i class="fas fa-user text-lg"></i>
            </div>
            <div class="overflow-hidden">
              <h3 class="font-black text-slate-800 leading-none truncate mb-1.5 group-hover:text-blue-600 transition-colors">{{ contact.name }}</h3>
              <p class="text-[11px] font-bold text-slate-400 font-mono tracking-tighter">{{ contact.phone || 'بدون هاتف' }}</p>
            </div>
          </div>
          <button v-if="contact.balance !== 0" @click="openPaymentModal(contact)" class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="تسجيل دفعة">
            <i class="fas fa-hand-holding-usd text-xs"></i>
          </button>
        </div>

        <!-- Card Middle: Balance -->
        <div class="bg-slate-50 rounded-2xl p-4 mb-6 border border-slate-100 group-hover:bg-white group-hover:border-blue-100 transition-all">
          <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">الرصيد الحالي</p>
          <div class="flex items-baseline gap-1.5">
            <span :class="[contact.balance > 0 ? 'text-rose-600' : 'text-emerald-600', 'text-xl font-black tracking-tighter']">
              {{ formatPrice(Math.abs(contact.balance)) }}
            </span>
            <span class="text-[10px] font-bold text-slate-400 uppercase">{{ contact.balance > 0 ? 'مدين' : (contact.balance < 0 ? 'دائن' : 'مستقر') }}</span>
          </div>
        </div>

        <!-- Card Bottom: Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-50">
          <button @click="goToStatement(contact)" class="text-[10px] font-black text-indigo-500 hover:text-indigo-700 transition-colors uppercase tracking-tight">كشف الحساب</button>
          <div class="flex items-center gap-1.5">
            <button @click="viewContact(contact)" class="action-btn-mini hover:bg-blue-50 hover:text-blue-600" title="تفاصيل"><i class="fas fa-eye"></i></button>
            <button @click="editContact(contact)" class="action-btn-mini hover:bg-amber-50 hover:text-amber-600" title="تعديل"><i class="fas fa-pen"></i></button>
            <button @click="deleteContact(contact)" class="action-btn-mini hover:bg-rose-50 hover:text-rose-600" title="حذف"><i class="fas fa-trash-alt"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Mode: Table -->
    <div v-else-if="viewMode === 'table'" class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">العميل</th>
              <th class="px-4 py-5">رقم الهاتف</th>
              <th class="px-4 py-5 text-center">الرصيد</th>
              <th class="px-8 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="contact in filteredContacts" :key="contact.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-8 py-4">
                <div class="flex flex-col">
                  <span class="font-black text-slate-800 leading-none">{{ contact.name }}</span>
                  <span class="text-[10px] font-bold text-slate-400 mt-1.5 uppercase font-mono tracking-tighter">ID: {{ contact.code || '---' }}</span>
                </div>
              </td>
              <td class="px-4 py-4 font-bold text-slate-500">{{ contact.phone || '-' }}</td>
              <td class="px-4 py-4 text-center">
                <span :class="['px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter', contact.balance > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700']">
                  {{ formatPrice(Math.abs(contact.balance || 0)) }}
                  {{ contact.balance > 0 ? 'مدين' : (contact.balance < 0 ? 'دائن' : '') }}
                </span>
              </td>
              <td class="px-8 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="viewContact(contact)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="تفاصيل"><i class="fas fa-eye text-xs"></i></button>
                  <button @click="goToStatement(contact)" class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="كشف الحساب"><i class="fas fa-file-invoice-dollar text-xs"></i></button>
                  <button @click="editContact(contact)" class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-600 hover:text-white transition-all shadow-sm" title="تعديل"><i class="fas fa-pen text-xs"></i></button>
                  <button @click="deleteContact(contact)" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm" title="حذف"><i class="fas fa-trash-alt text-xs"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modals (Logic Preserved) -->
    
    <!-- Customer Form Modal -->
    <transition name="modal">
      <div v-if="showFormModal" class="modal-overlay">
        <div class="modal-content-modern animate-modalIn max-w-xl">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-blue-100">
                <i :class="selectedContact?.id ? 'fas fa-user-edit' : 'fas fa-user-plus'"></i>
              </div>
              <h3 class="text-lg font-black text-slate-800 leading-none">{{ selectedContact?.id ? 'تعديل بيانات العميل' : 'إضافة عميل جديد' }}</h3>
            </div>
            <button @click="showFormModal = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>
          <form @submit.prevent="handleFormSubmit(selectedContact)" class="p-8 space-y-6">
            <div class="space-y-2">
              <label class="modal-label">الاسم بالكامل <span class="text-rose-500">*</span></label>
              <input type="text" v-model="selectedContact.name" class="form-input-modern font-bold" placeholder="أدخل اسم العميل" required />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="modal-label">رقم الهاتف</label>
                <input type="text" v-model="selectedContact.phone" class="form-input-modern font-bold" />
              </div>
              <div class="space-y-2">
                <label class="modal-label">البريد الإلكتروني</label>
                <input type="email" v-model="selectedContact.email" class="form-input-modern font-bold" />
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="modal-label">الرقم الضريبي</label>
                <input type="text" v-model="selectedContact.tax_number" class="form-input-modern font-bold" />
              </div>
              <div class="space-y-2">
                <label class="modal-label">حد الائتمان</label>
                <input type="number" v-model.number="selectedContact.credit_limit" class="form-input-modern font-black text-blue-600" min="0" step="0.01" />
              </div>
            </div>
            <div class="space-y-2">
              <label class="modal-label">العنوان بالتفصيل</label>
              <textarea rows="2" v-model="selectedContact.address" class="w-full rounded-2xl border border-slate-200 p-4 text-sm font-bold bg-slate-50 focus:bg-white transition-all outline-none focus:ring-4 focus:ring-blue-50"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
              <button type="button" @click="showFormModal = false" class="px-6 py-2.5 rounded-xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all">إلغاء</button>
              <button type="submit" class="px-8 py-2.5 rounded-xl bg-blue-600 text-white font-black shadow-lg shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all">حفظ البيانات</button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- Payment Modal (Logic Preserved) -->
    <transition name="modal">
      <div v-if="showPaymentModal" class="modal-overlay">
        <div class="modal-content-modern animate-modalIn max-w-md">
          <div class="p-8 text-center border-b border-slate-50 bg-slate-50/50">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-sm">
              <i class="fas fa-hand-holding-usd text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 leading-none">تسجيل دفعة واردة</h3>
            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">العميل: {{ selectedContact.name }}</p>
          </div>
          <form @submit.prevent="handlePaymentSubmit" class="p-8 space-y-5">
            <div class="space-y-2">
              <label class="modal-label text-center block">المبلغ المراد تحصيله <span class="text-rose-500">*</span></label>
              <input type="number" v-model.number="paymentData.amount" class="w-full h-16 rounded-[1.5rem] border-slate-100 bg-slate-50 text-3xl font-black text-center text-emerald-600 focus:bg-white focus:ring-4 focus:ring-emerald-50 transition-all outline-none" required step="0.01" min="0" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="modal-label">تاريخ الدفع</label>
                <div class="relative">
                  <input ref="paymentDateRef" type="date" v-model="paymentData.payment_date" class="form-input-modern text-xs font-bold" required />
                  <i class="fas fa-calendar-days absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="paymentDateRef.showPicker()"></i>
                </div>
              </div>
              <div class="space-y-2">
                <label class="modal-label">طريقة الدفع</label>
                <select v-model.number="paymentData.payment_method_id" class="form-select-modern text-xs font-bold" required>
                  <option value="">اختر الطريقة</option>
                  <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </div>
            </div>
            <div class="space-y-2">
              <label class="modal-label">الفرع (في حال النقدي)</label>
              <select v-model="paymentData.branch_id" class="form-select-modern text-xs font-bold">
                <option value="">-- اختياري --</option>
                <option v-for="w in branches" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="modal-label">ملاحظات التحصيل</label>
              <textarea v-model="paymentData.notes" rows="2" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 outline-none focus:bg-white transition-all"></textarea>
            </div>
            <div class="flex gap-4 pt-2">
              <button type="button" @click="showPaymentModal = false" class="flex-1 py-3.5 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all">إلغاء</button>
              <button type="submit" class="flex-[2] py-3.5 rounded-2xl bg-emerald-600 text-white font-black shadow-xl shadow-emerald-100 hover:bg-emerald-700 transition-all active:scale-95">تأكيد التحصيل</button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import getLocalDateISO from '@/utils/date';
import { useRouter } from 'vue-router';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import AlertService from '@/services/AlertService';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import { useSessionExemption } from '@/composables/useCashierSessionGuard';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useCustomerStore } from '@/stores/customer/customerStore';
import { usePaymentStore } from '@/stores/payment/paymentStore';
import { useUserBranchPreference } from '@/composables/useUserBranchPreference';
import BranchIndicatorBreadcrumb from '@/components/BranchIndicatorBreadcrumb.vue';

const { showToast } = useToast(); 
const router = useRouter();

// --- Logic Initialization ---
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency();
const { ensureLoaded: ensureExemptionLoaded, isExempt } = useSessionExemption();
const authStore = useAuthStore();
const branchStore = useBranchStore();
const customerStore = useCustomerStore();
const paymentStore = usePaymentStore();
const { branches, initializePreferences, currentBranchName } = useUserBranchPreference('selectedCustomerBranch');

// Use unified branch store for operational data - mandatory branch_id
const selectedBranch = computed({
  get: () => branchStore.selectedBranchId,
  set: (val) => branchStore.setSelectedBranch(val)
});

const onBranchChange = () => {
  try {
    if (selectedBranch.value) {
      window.localStorage?.setItem('selectedCustomerBranch', selectedBranch.value);
    } else {
      window.localStorage?.removeItem('selectedCustomerBranch');
    }
  } catch {}
  fetchContacts();
};
const formatPrice = (amount) => formatCurrencyLocale(amount, 2);

const contacts = ref([]);
const search = ref('');
const showFormModal = ref(false);
const isLoading = ref(false);
const selectedContact = ref(null);
const viewMode = ref('cards'); 
const showPaymentModal = ref(false);
const paymentData = ref({ amount: null, payment_date: null, payment_method_id: null, branch_id: null, notes: '' });

// Date input refs
const paymentDateRef = ref(null);
const paymentMethods = ref([]);

// Logic: Filters
const filteredContacts = computed(() => {
  if (!search.value) return contacts.value;
  const q = search.value.toLowerCase();
  return contacts.value.filter(c => (c.name && c.name.toLowerCase().includes(q)) || (c.phone && c.phone.includes(q)));
});

// Logic: KPIs
const kpis = computed(() => {
  const debtors = contacts.value.filter(c => Number(c.balance) > 0);
  const totalReceivable = debtors.reduce((sum, c) => sum + Number(c.balance || 0), 0);
  const topDebtor = [...debtors].sort((a, b) => Number(b.balance) - Number(a.balance))[0];
  return [
    { title: 'إجمالي الذمم المدينة', value: formatPrice(totalReceivable), icon: 'fas fa-file-invoice-dollar' },
    { title: 'عملاء مدينين حالياً', value: debtors.length, icon: 'fas fa-user-clock' },
    { title: 'أكبر رصيد مدين', value: topDebtor ? `${topDebtor.name} (${formatPrice(Number(topDebtor.balance))})` : 'لا يوجد', icon: 'fas fa-arrow-trend-up' }
  ];
});

const fetchContacts = async () => {
  isLoading.value = true;
  try {
    let params = {};
    if (!isExempt.value) {
      const wid = authStore?.user?.branch_id;
      if (!wid) { contacts.value = []; showToast('لم يتم تعيين مخزن للمستخدم', 'warning'); return; }
      params.branch_id = String(wid);
    } else if (selectedBranch.value) {
      params.branch_id = String(selectedBranch.value);
    }
    // isExempt + no branch selected → no branch_id param → API returns all customers
    const res = await customerStore.fetchCustomers({ force: true, params });
    contacts.value = Array.isArray(res) ? res : (res.data || []);
  } catch (err) {
    showToast(err.response?.data?.message || 'حدث خطأ في التحميل', 'error');
  } finally { isLoading.value = false; }
};

const goToStatement = (c) => router.push({ name: 'AccountStatement', params: { type: 'customers', id: c.id } });
const openAddModal = () => {
  const branchId = !isExempt.value
    ? (authStore?.user?.branch_id || null)
    : (selectedBranch.value || null); // null when 'all' → admin must explicitly pick branch in form
  selectedContact.value = { name: '', phone: '', email: '', tax_number: '', address: '', credit_limit: 0, branch_id: branchId };
  showFormModal.value = true;
};
const editContact = (c) => { selectedContact.value = { ...c }; showFormModal.value = true; };
const viewContact = (c) => { try { sessionStorage.setItem('selectedContact', JSON.stringify(c)); } catch {} router.push({ name: 'ContactDetails', params: { type: 'customers', id: c.id } }); };

const deleteContact = async (c) => {
  if (await AlertService.confirm(`هل أنت متأكد من حذف ${c.name}?`, 'حذف العميل')) {
    try {
      await customerStore.deleteCustomer(c.id);
      showToast('تم الحذف بنجاح', 'success');
      fetchContacts();
    } catch (e) { showToast(e.response?.data?.message || 'فشل الحذف', 'error'); }
  }
};

const handleFormSubmit = async (formData) => {
  try {
    if (formData.id) await customerStore.updateCustomer(formData.id, formData);
    else await customerStore.createCustomer(formData);
    showToast('تم الحفظ بنجاح', 'success');
    showFormModal.value = false;
    fetchContacts();
  } catch (e) { showToast(e.response?.data?.message || 'خطأ في الحفظ', 'error'); }
};

const openPaymentModal = (c) => {
  selectedContact.value = c;
  paymentData.value = { amount: null, payment_date: getLocalDateISO(), payment_method_id: null, branch_id: null, notes: '' };
  showPaymentModal.value = true;
};

const handlePaymentSubmit = async () => {
  if (!paymentData.value.amount || paymentData.value.amount <= 0) return showToast('ادخل مبلغ صحيح', 'error');
  if (!paymentData.value.payment_method_id) return showToast('اختر الطريقة', 'error');
  if (!paymentData.value.payment_date) {
    showToast('الرجاء تحديد تاريخ الدفع', 'error');
    return;
  }
  try {
    const res = await customerStore.recordCustomerPayment(selectedContact.value?.id, paymentData.value);
    if (res.status === 'success') {
      showToast(`تم التسجيل بنجاح`, 'success');
      showPaymentModal.value = false;
      fetchContacts();
    } else {
      showToast(res.message || 'فشل التسجيل', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.message || e.message || 'فشل التسجيل', 'error');
  }
};

onMounted(async () => {
  await Promise.all([fetchSettings(), ensureExemptionLoaded(), paymentStore.fetchPaymentMethods().catch(() => {})]);
  paymentMethods.value = paymentStore.paymentMethods;
  await initializePreferences();
  fetchContacts();
});

</script>

<style scoped>



/* KPI Cards */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-xl font-black leading-none; }

/* Modern Components */
.form-input-modern, .form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

/* Contact Cards */
.contact-card-modern { @apply relative bg-white rounded-[2rem] shadow-sm border border-slate-100 p-7 transition-all duration-500 hover:shadow-xl hover:border-blue-200; }
.action-btn-mini { @apply w-8 h-8 rounded-lg flex items-center justify-center text-slate-300 transition-all active:scale-90; }

/* Modal Styles */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>