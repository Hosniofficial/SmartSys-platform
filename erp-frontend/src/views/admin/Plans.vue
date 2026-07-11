<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-layer-group text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة خطط الاشتراك</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تخصيص الباقات، الأسعار، ودورات الفوترة للنظام</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="load" :disabled="loading" class="w-11 h-11 bg-white border border-slate-100 rounded-xl flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90 shadow-sm" title="تحديث البيانات">
          <i class="fas fa-rotate" :class="{'animate-spin': loading}"></i>
        </button>
        <button @click="showAddModal = true" class="h-11 px-8 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus-circle"></i> إضافة خطة جديدة
        </button>
      </div>
    </div>

    <!-- Plans Overview KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600">
            <i class="fas fa-tags"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">إجمالي الخطط</p>
            <p class="kpi-value text-slate-800">{{ plans.length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600">
            <i class="fas fa-check-circle"></i>
          </div>
          <div>
            <p class="kpi-label uppercase tracking-widest">خطط نشطة</p>
            <p class="kpi-value text-emerald-600">{{ plans.filter(p => p.is_active).length }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Plans Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
         <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">مصفوفة خطط الاشتراك الحالية</h3>
         <div v-if="plans.some(p => p.code === 'trial')" class="flex items-center gap-2 px-4 py-1.5 bg-amber-50 rounded-xl border border-amber-100">
            <i class="fas fa-shield-halved text-amber-500 text-[10px]"></i>
            <span class="text-[9px] font-black text-amber-700 uppercase tracking-tighter">الخطة التجريبية محمية من التعديل</span>
         </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">كود الخطة</th>
              <th class="px-4 py-5">اسم الباقة</th>
              <th class="px-4 py-5">السعر (الوحدة)</th>
              <th class="px-4 py-5">العملة</th>
              <th class="px-4 py-5">الدورة (أيام)</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-8 py-5 text-center w-48">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="loading && !plans.length">
              <tr v-for="row in 5" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="12rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="plans.length === 0" class="text-center py-20">
              <td colspan="7" class="py-24 opacity-20 text-slate-400">
                <i class="fas fa-layer-group text-6xl mb-4"></i>
                <p class="font-black text-sm uppercase">لا توجد خطط معرفة في النظام</p>
              </td>
            </tr>
            <tr v-for="p in plans" :key="p.code" class="hover:bg-blue-50/30 transition-all group font-bold">
              <td class="px-8 py-4">
                <span class="font-black text-blue-600 font-mono text-xs uppercase tracking-wider bg-blue-50 px-3 py-1 rounded-lg border border-blue-100 group-hover:bg-white transition-all">{{ p.code }}</span>
              </td>
              <td class="px-4 py-4 text-slate-800 font-black leading-none">{{ p.name }}</td>
              <td class="px-4 py-4">
                <input 
                  v-model.number="p.price" 
                  type="number" 
                  step="0.01" 
                  class="w-28 h-10 bg-white border-2 border-slate-100 rounded-xl text-center font-black text-slate-900 focus:border-blue-500 outline-none transition-all disabled:opacity-30 disabled:cursor-not-allowed shadow-sm"
                  :disabled="p.code === 'trial'" 
                />
              </td>
              <td class="px-4 py-4">
                <select v-model="p.currency" class="h-10 px-4 bg-white border-2 border-slate-100 rounded-xl font-black text-xs outline-none focus:border-blue-500 transition-all disabled:opacity-30 shadow-sm" :disabled="p.code === 'trial'">
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="SAR">SAR</option>
                  <option value="EGP">EGP</option>
                </select>
              </td>
              <td class="px-4 py-4">
                <input 
                  v-model.number="p.billing_cycle_days" 
                  type="number" 
                  class="w-24 h-10 bg-white border-2 border-slate-100 rounded-xl text-center font-black text-slate-500 outline-none focus:border-blue-500 transition-all disabled:opacity-30 shadow-sm"
                  :disabled="p.code === 'trial'" 
                />
              </td>
              <td class="px-4 py-4 text-center">
                <label class="relative inline-flex items-center cursor-pointer" :class="{'opacity-30 cursor-not-allowed': p.code === 'trial'}">
                  <input type="checkbox" v-model="p.is_active" :true-value="1" :false-value="0" class="sr-only peer" :disabled="p.code === 'trial'">
                  <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
              </td>
              <td class="px-8 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="save(p)" :disabled="p.code === 'trial' || saveLoading[p.code]" class="h-9 px-5 rounded-xl bg-blue-600 text-white text-[10px] font-black uppercase shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-90 disabled:opacity-30 flex items-center gap-2">
                    <BaseSpinner v-if="saveLoading[p.code]" :size="10" color="#fff" :margin="0" />
                    <i v-else class="fas fa-save"></i>
                    حفظ
                  </button>
                  <button v-if="p.code !== 'trial'" @click="deletePlan(p)" :disabled="deleteLoading[p.code]" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all active:scale-90 shadow-sm disabled:opacity-30">
                    <BaseSpinner v-if="deleteLoading[p.code]" :size="12" color="#fff" :margin="0" />
                    <i v-else class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Plan Modal -->
    <transition name="modal">
      <div v-if="showAddModal" class="modal-overlay">
        <div class="modal-content-modern max-w-lg animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
                <i class="fas fa-plus-circle text-xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800 leading-none">إضافة خطة اشتراك</h3>
                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest leading-none">تحديد تفاصيل الباقة الجديدة</p>
              </div>
            </div>
            <button @click="showAddModal = false" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <form @submit.prevent="addPlan" class="p-8 space-y-6" dir="rtl">
            <div class="grid grid-cols-2 gap-6">
               <div class="space-y-2 group">
                  <label class="modal-label">كود الخطة <span class="text-rose-500">*</span></label>
                  <input v-model="newPlan.code" type="text" required class="form-input-modern font-black font-mono uppercase tracking-widest" placeholder="PREMIUM_X" pattern="[a-z0-9_]+" />
               </div>
               <div class="space-y-2 group">
                  <label class="modal-label">اسم الباقة <span class="text-rose-500">*</span></label>
                  <input v-model="newPlan.name" type="text" required class="form-input-modern font-bold" placeholder="باقة الشركات" />
               </div>
               <div class="space-y-2 group">
                  <label class="modal-label">السعر المستهدف <span class="text-rose-500">*</span></label>
                  <div class="relative">
                    <input v-model.number="newPlan.price" type="number" step="0.01" required class="form-input-modern font-black text-lg pl-12 pr-11" placeholder="0.00" />
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase">{{ newPlan.currency }}</span>
                  </div>
               </div>
               <div class="space-y-2 group">
                  <label class="modal-label">دورة الفوترة (أيام) <span class="text-rose-500">*</span></label>
                  <input v-model.number="newPlan.billing_cycle_days" type="number" required class="form-input-modern font-black text-lg" placeholder="30" />
               </div>
            </div>

            <div class="flex flex-col gap-4">
               <div class="space-y-2">
                  <label class="modal-label">عملة الاشتراك</label>
                  <select v-model="newPlan.currency" class="form-select-modern font-black">
                    <option value="USD">USD - دولار أمريكي</option>
                    <option value="EUR">EUR - يورو</option>
                    <option value="SAR">SAR - ريال سعودي</option>
                    <option value="EGP">EGP - جنيه مصري</option>
                  </select>
               </div>
               
               <label class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer transition-all hover:bg-white hover:border-blue-100 group">
                  <div class="flex-grow">
                    <h4 class="text-sm font-black text-slate-800 leading-none">تنشيط الخطة</h4>
                    <p class="text-[10px] text-slate-400 font-bold mt-1.5 uppercase leading-none">إتاحة الباقة للاستخدام فور الحفظ</p>
                  </div>
                  <input type="checkbox" v-model="newPlan.is_active" :true-value="1" :false-value="0" class="w-6 h-6 rounded-lg border-slate-300 text-blue-600 focus:ring-0 cursor-pointer" />
               </label>
            </div>

            <div class="flex justify-end gap-3 pt-6 border-t border-slate-50">
              <button type="button" @click="showAddModal = false" class="px-8 py-3 rounded-xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all text-xs uppercase tracking-widest">إلغاء</button>
              <button type="submit" :disabled="addLoading" class="px-12 py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3">
                <BaseSpinner v-if="addLoading" :size="16" color="#fff" :margin="0" />
                <span>إضافة الخطة الآن</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAdminStore } from '@/stores/admin/adminStore';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';;

const adminStore = useAdminStore();
const plans = ref([])
const loading = computed(() => adminStore.loading)
const showAddModal = ref(false)
const addLoading = computed(() => adminStore.actionLoading)
const saveLoading = reactive({})
const deleteLoading = reactive({})

const newPlan = reactive({
  code: '',
  name: '',
  price: 0,
  currency: 'USD',
  billing_cycle_days: 30,
  is_active: 1
})

async function load() {
  const result = await adminStore.fetchPlans()
  if (result.status === 'success') {
    plans.value = result.data
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function addPlan() {
  const result = await adminStore.createPlan(newPlan)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    Object.assign(newPlan, { code: '', name: '', price: 0, currency: 'USD', billing_cycle_days: 30, is_active: 1 })
    showAddModal.value = false
    await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function save(p) {
  // ✅ مستعادة من النسخة القديمة: الشرط الدقيق — يسمح فقط بـ monthly و yearly
  // النسخة الجديدة كانت تتحقق من p.code === 'trial' فقط، وهذا يسمح بحفظ أي كود آخر غير معروف
  if (!['monthly', 'yearly'].includes(p.code)) return

  saveLoading[p.code] = true
  const planData = {
    price: p.price,
    currency: p.currency,
    billing_cycle_days: p.billing_cycle_days,
    is_active: p.is_active
  }
  const result = await adminStore.updatePlan(p.code, planData)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
  saveLoading[p.code] = false
}

async function deletePlan(p) {
  if (p.code === 'trial') return
  if (!confirm(`هل أنت متأكد من حذف خطة "${p.name}"؟`)) return
  deleteLoading[p.code] = true
  const result = await adminStore.deletePlan(p.code)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
  deleteLoading[p.code] = false
}

onMounted(() => load());
</script>

<style scoped>



.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight font-mono; }

.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>