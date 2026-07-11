<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center shadow-xl shadow-slate-200 text-white shrink-0">
          <i class="fas fa-id-card-clip text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة الاشتراكات</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">متابعة تراخيص المستأجرين، صلاحية الباقات، والتدقيق الأمني</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="load" :disabled="loading" class="px-5 py-2.5 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 border-l border-slate-50">
          <i class="fas fa-sync-alt" :class="{'animate-spin': loading}"></i> {{ loading ? 'جاري التحميل...' : 'تحديث البيانات' }}
        </button>
        <button @click="exportCsv" :disabled="rows.length===0" class="px-5 py-2.5 rounded-xl text-xs font-black text-emerald-600 hover:bg-emerald-50 transition-all flex items-center gap-2 disabled:opacity-30">
          <i class="fas fa-file-csv"></i> تصدير سجل CSV
        </button>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-8 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="space-y-2">
          <label class="filter-label">باقة الاشتراك</label>
          <select v-model="filters.plan" class="form-select-modern font-black text-xs">
            <option value="">كل الخطط</option>
            <option value="trial">التجريبية (Trial)</option>
            <option value="monthly">الشهرية (Monthly)</option>
            <option value="yearly">السنوية (Yearly)</option>
          </select>
        </div>

        <div class="space-y-2 text-right">
          <label class="filter-label">حالة الاشتراك</label>
          <select v-model="filters.status" class="form-select-modern font-black text-xs">
            <option value="">كل الحالات</option>
            <option value="trial">تجريبي</option>
            <option value="active">نشط</option>
            <option value="expired">منتهي</option>
            <option value="cancelled">ملغى</option>
            <option value="pending">قيد الانتظار</option>
          </select>
        </div>

        <div class="space-y-2 text-right">
          <label class="filter-label">مستوى المخاطر</label>
          <select v-model="filters.risk_level" class="form-select-modern font-black text-xs">
            <option value="">كل المستويات</option>
            <option value="low">منخفض (Low Risk)</option>
            <option value="medium">متوسط (Medium)</option>
            <option value="high">مرتفع (High Risk)</option>
          </select>
        </div>

        <div class="space-y-2 group">
          <label class="filter-label">رقم المستأجر (Tenant ID)</label>
          <div class="relative">
            <input v-model.number="filters.tenant_id" type="number" min="1" class="form-input-modern h-[46px] pr-10 font-mono" placeholder="مثال: 101" />
            <i class="fas fa-hashtag absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500"></i>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-6 border-t border-slate-50">
        <button @click="resetFilters" :disabled="loading" class="px-6 py-2.5 rounded-xl text-xs font-black text-slate-400 hover:bg-slate-50 transition-all uppercase tracking-widest">إعادة تعيين</button>
        <button @click="applyFilters" :disabled="loading" class="px-10 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-black shadow-xl shadow-slate-200 hover:bg-black transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-filter"></i> تطبيق التصفية
        </button>
      </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600"><i class="fas fa-users-rectangle"></i></div>
          <div><p class="kpi-label uppercase">إجمالي الاشتراكات</p><p class="kpi-value text-slate-800">{{ rows.length }}</p></div>
        </div>
      </div>
      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check-double"></i></div>
          <div><p class="kpi-label uppercase">تراخيص نشطة</p><p class="kpi-value text-emerald-600">{{ summary.active }}</p></div>
        </div>
      </div>
      <div class="kpi-card group border-l-4 border-l-rose-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-rose-50 text-rose-600"><i class="fas fa-hourglass-end"></i></div>
          <div><p class="kpi-label uppercase">تراخيص منتهية</p><p class="kpi-value text-rose-600">{{ summary.expired }}</p></div>
        </div>
      </div>
    </div>

    <!-- Subscriptions Data Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5 w-16 text-center">#</th>
              <th class="px-4 py-5 w-24">Tenant</th>
              <th class="px-4 py-5">الخطة / الباقة</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-4 py-5">تاريخ البداية</th>
              <th class="px-4 py-5">تاريخ الانتهاء</th>
              <th class="px-4 py-5 text-center">السداد</th>
              <th class="px-4 py-5 text-center">المخاطر</th>
              <th class="px-8 py-5 text-center">الإجراءات والتحكم</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="loading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="pagedRows.length === 0" class="text-center py-20">
              <td colspan="9" class="py-24 opacity-20 text-slate-400">
                <i class="fas fa-id-card text-6xl mb-4"></i>
                <p class="font-black text-sm uppercase">لا توجد اشتراكات مسجلة</p>
              </td>
            </tr>
            <tr v-for="s in pagedRows" :key="s.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-6 py-4 text-center text-slate-300 font-mono text-xs">{{ s.id }}</td>
              <td class="px-4 py-4"><span class="font-black text-slate-900 bg-slate-50 px-2 py-1 rounded-lg">#{{ s.tenant_id }}</span></td>
              <td class="px-4 py-4">
                <div class="flex flex-col">
                  <span class="font-black text-slate-800 leading-none">{{ s.plan_name }}</span>
                  <span class="text-[9px] text-slate-400 mt-1.5 uppercase font-black tracking-widest">{{ s.plan_code }}</span>
                </div>
              </td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', badgeClass(s.status)]">{{ s.status }}</span>
              </td>
              <td class="px-4 py-4 text-xs font-bold text-slate-500 font-mono tracking-tighter">{{ formatDate(s.start_date) }}</td>
              <td class="px-4 py-4 text-xs font-bold text-slate-500 font-mono tracking-tighter">{{ formatDate(s.end_date) }}</td>
              <td class="px-4 py-4 text-center">
                <span class="text-[10px] font-black uppercase text-slate-400">{{ s.payment_status || '-' }}</span>
              </td>
              <td class="px-4 py-4 text-center">
                <span v-if="s.risk_score" :class="['px-2 py-1 rounded-lg text-[10px] font-black font-mono tracking-tight', riskBadgeClass(s.risk_score)]">
                   {{ s.risk_score }}/10
                </span>
                <span v-else class="text-slate-200">—</span>
              </td>
              <td class="px-8 py-4 text-center">
                <div class="flex items-center justify-center gap-1.5 flex-wrap max-w-[280px] mx-auto">
                  <button @click="openActivate(s)" class="action-btn-xs bg-emerald-50 text-emerald-600 hover:bg-emerald-600" title="تفعيل"><i class="fas fa-play"></i></button>
                  <button @click="openExpire(s)" class="action-btn-xs bg-rose-50 text-rose-600 hover:bg-rose-600" title="إيقاف"><i class="fas fa-stop"></i></button>
                  <button @click="openExtend(s)" class="action-btn-xs bg-amber-50 text-amber-600 hover:bg-amber-600" title="تمديد"><i class="fas fa-calendar-plus"></i></button>
                  <button @click="viewSecurityDetails(s)" class="action-btn-xs bg-blue-50 text-blue-600 hover:bg-blue-600" title="فحص أمني"><i class="fas fa-shield-halved"></i></button>
                  <button @click="openChangePlan(s)" class="action-btn-xs bg-purple-50 text-purple-600 hover:bg-purple-600" title="تغيير الخطة"><i class="fas fa-shuffle"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          عرض الصفحة <span class="text-slate-900">{{ page }}</span> من <span class="text-slate-900">{{ totalPages }}</span>
        </div>
        <div class="flex items-center gap-1">
          <button @click="page--" :disabled="page===1" class="pagination-btn"><i class="fas fa-angle-right"></i></button>
          <div class="px-6 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm">
            {{ page }} / {{ totalPages }}
          </div>
          <button @click="page++" :disabled="page===totalPages" class="pagination-btn"><i class="fas fa-angle-left"></i></button>
        </div>
      </div>
    </div>

    <!-- Activate Modal -->
    <transition name="modal">
      <div v-if="dialogs.activate" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-8 text-center border-b border-slate-50 bg-slate-50/50">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-4"><i class="fas fa-bolt-lightning text-2xl"></i></div>
            <h3 class="text-xl font-black text-slate-800 leading-none">تفعيل الاشتراك #{{ current?.id }}</h3>
          </div>
          <div class="p-8 space-y-6">
            <div class="space-y-2">
               <label class="modal-label">تعيين خطة مخصصة (اختياري)</label>
               <select v-model="form.plan" class="form-select-modern font-black">
                 <option value="">بدون تغيير (الافتراضية)</option>
                 <option value="monthly">monthly</option>
                 <option value="yearly">yearly</option>
               </select>
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-4">
            <button @click="closeDialogs" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 text-xs">إلغاء</button>
            <button @click="submitActivate" :disabled="actionLoading" class="flex-[2] py-4 rounded-2xl bg-emerald-600 text-white font-black shadow-xl shadow-emerald-100 hover:bg-emerald-700 transition-all active:scale-95 flex items-center justify-center gap-3">
               <BaseSpinner v-if="actionLoading" :size="16" color="#fff" />
               <span>تفعيل الاشتراك</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Expire Modal -->
    <transition name="modal">
      <div v-if="dialogs.expire" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-10 text-center">
            <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-[2.5rem] flex items-center justify-center mx-auto mb-6 shadow-sm"><i class="fas fa-hand-holding-hand text-3xl"></i></div>
            <h3 class="text-xl font-black text-slate-900 leading-none">إيقاف الاشتراك #{{ current?.id }}</h3>
            <p class="text-slate-400 text-sm mt-4 leading-relaxed font-bold">تحذير: سيتم تعطيل وصول المستأجر للنظام فوراً. هل تريد المتابعة؟</p>
            <div class="grid grid-cols-2 gap-4 mt-10">
              <button @click="closeDialogs" class="h-12 rounded-2xl border-2 border-slate-50 font-black text-slate-400 text-xs hover:bg-slate-50 transition-all">تراجع</button>
              <button @click="submitExpire" :disabled="actionLoading" class="h-12 rounded-2xl bg-rose-600 text-white font-black text-xs shadow-xl shadow-rose-100 active:scale-95 transition-all flex items-center justify-center gap-2">
                 <BaseSpinner v-if="actionLoading" :size="14" color="#fff" />
                 <span>إيقاف الآن</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Extend Modal -->
    <transition name="modal">
      <div v-if="dialogs.extend" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-8 border-b border-slate-50 bg-slate-50/50">
            <h3 class="text-xl font-black text-slate-800">تمديد اشتراك #{{ current?.id }}</h3>
          </div>
          <div class="p-8 space-y-6">
            <div class="space-y-2 text-right">
               <label class="modal-label">عدد أيام التمديد الإضافية</label>
               <input v-model.number="form.days" type="number" min="1" class="form-input-modern text-center font-black text-3xl h-16" />
               <p class="text-[10px] text-slate-400 font-bold uppercase mt-2 text-center">سيتم إضافة هذه الأيام لتاريخ الانتهاء الحالي</p>
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-4">
            <button @click="closeDialogs" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 text-xs">إلغاء</button>
            <button @click="submitExtend" :disabled="actionLoading || !form.days" class="flex-[2] py-4 rounded-2xl bg-amber-500 text-white font-black shadow-xl shadow-amber-100 hover:bg-amber-600 active:scale-95 transition-all">تأكيد التمديد</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Security Modal -->
    <transition name="modal">
      <div v-if="dialogs.security" class="modal-overlay">
        <div class="modal-content-modern max-w-2xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-xl font-black text-slate-800">التدقيق الأمني للاشتراك #{{ current?.id }}</h3>
            <button @click="closeDialogs" class="text-slate-400 hover:text-rose-500"><i class="fas fa-times text-xl"></i></button>
          </div>
          <div class="p-8 space-y-8 overflow-y-auto custom-scroll max-h-[65vh]">
            <div class="grid grid-cols-2 gap-4">
               <div class="p-6 rounded-[1.5rem] bg-slate-50 border border-slate-100 flex flex-col justify-center items-center">
                  <p class="text-[10px] font-black text-slate-400 uppercase mb-2">مؤشر المخاطر</p>
                  <p :class="[riskBadgeClass(current?.risk_score), 'text-3xl font-black font-mono rounded-2xl px-6 py-2']">{{ current?.risk_score || 0 }}/10</p>
               </div>
               <div class="p-6 rounded-[1.5rem] bg-slate-50 border border-slate-100 flex flex-col justify-center items-center">
                  <p class="text-[10px] font-black text-slate-400 uppercase mb-2">آخر فحص تلقائي</p>
                  <p class="text-xs font-black text-slate-800">{{ formatDate(current?.last_security_check) }}</p>
               </div>
            </div>

            <div v-if="current?.security_flags" class="bg-amber-50 border-2 border-amber-100 p-6 rounded-[2rem] space-y-3 shadow-inner">
               <h4 class="text-xs font-black text-amber-800 uppercase tracking-widest flex items-center gap-2"><i class="fas fa-triangle-exclamation"></i> تنبيهات رصدها النظام:</h4>
               <p class="text-xs font-bold text-amber-700 leading-relaxed italic">{{ current.security_flags }}</p>
            </div>

            <div class="flex flex-col gap-3">
               <button @click="refreshSecurityData" :disabled="securityLoading" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-black transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-3">
                  <BaseSpinner v-if="securityLoading" :size="16" color="#fff" />
                  <i v-else class="fas fa-shield-virus"></i> تشغيل فحص أمني فوري
               </button>
               <button @click="blockSubscription" :disabled="securityLoading" class="w-full py-4 bg-rose-50 text-rose-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-[0.98] flex items-center justify-center gap-3">
                  <i class="fas fa-ban"></i> حظر هذا المستأجر نهائياً
               </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Change Plan Modal -->
    <transition name="modal">
      <div v-if="dialogs.changePlan" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="p-8 border-b border-slate-50 bg-slate-50/50">
            <h3 class="text-xl font-black text-slate-800">تعديل باقة المستأجر #{{ current?.id }}</h3>
          </div>
          <div class="p-8 space-y-6">
            <div class="p-5 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-between">
               <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest">الباقة الحالية:</p>
               <span class="text-xs font-black text-blue-900 uppercase tracking-tight">{{ current?.plan_code }} — {{ current?.plan_name }}</span>
            </div>
            
            <div class="space-y-2">
              <label class="modal-label">اختر الباقة الجديدة</label>
              <select v-model="form.newPlan" class="form-select-modern font-black" required>
                <option value="">-- اختر خطة --</option>
                <option value="trial">Trial (تجريبي)</option>
                <option value="monthly">Monthly (شهري)</option>
                <option value="yearly">Yearly (سنوي)</option>
              </select>
            </div>

            <div class="grid grid-cols-1 gap-3 pt-2">
               <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-50 hover:bg-slate-50 transition-all cursor-pointer">
                  <input type="checkbox" v-model="form.prorate" class="w-5 h-5 rounded text-purple-600 border-slate-300 focus:ring-0" />
                  <span class="text-xs font-black text-slate-600 uppercase">احتساب تناسبي (Prorate)</span>
               </label>
               <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-50 hover:bg-slate-50 transition-all cursor-pointer">
                  <input type="checkbox" v-model="form.extendPeriod" class="w-5 h-5 rounded text-purple-600 border-slate-300 focus:ring-0" />
                  <span class="text-xs font-black text-slate-600 uppercase">تمديد فترة الصلاحية آلياً</span>
               </label>
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-4">
            <button @click="closeDialogs" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 text-xs">إلغاء</button>
            <button @click="submitChangePlan" :disabled="actionLoading || !form.newPlan" class="flex-[2] py-4 rounded-2xl bg-purple-600 text-white font-black shadow-xl shadow-purple-100 hover:bg-purple-700 active:scale-95 transition-all flex items-center justify-center gap-3 text-xs">
               <BaseSpinner v-if="actionLoading" :size="16" color="#fff" />
               <span>تحديث الباقة</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAdminStore } from '@/stores/admin/adminStore';
import { useAuthStore } from '@/stores/auth';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';;

const adminStore = useAdminStore();
const rows = ref([])
const loading = computed(() => adminStore.loading)
const actionLoading = computed(() => adminStore.actionLoading)
const securityLoading = computed(() => adminStore.securityLoading)
const page = ref(1)
const pageSize = ref(10)

const filters = ref({ plan: '', status: '', tenant_id: '', risk_level: '' })
const dialogs = ref({ activate: false, expire: false, extend: false, security: false, changePlan: false })
const current = ref(null)
const form = ref({ plan: '', days: 30, newPlan: '', prorate: false, extendPeriod: false })

const pagedRows = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return rows.value.slice(start, start + pageSize.value)
})

const totalPages = computed(() => Math.max(1, Math.ceil(rows.value.length / pageSize.value)))

const summary = computed(() => ({
  active: rows.value.filter(r => r.status === 'active').length,
  expired: rows.value.filter(r => r.status === 'expired').length,
}))

function formatDate(d) {
  if (!d) return '-'
  try { return new Date(d).toLocaleString('en-US') } catch { return d }
}

function badgeClass(status) {
  if (status === 'active') return 'bg-emerald-100 text-emerald-700 border-emerald-50'
  if (status === 'trial') return 'bg-blue-100 text-blue-700 border-blue-50'
  if (status === 'expired') return 'bg-rose-100 text-rose-700 border-rose-50'
  return 'bg-slate-100 text-slate-500'
}

function riskBadgeClass(score) {
  if (score >= 7) return 'bg-rose-100 text-rose-700'
  if (score >= 4) return 'bg-amber-100 text-amber-700'
  return 'bg-emerald-100 text-emerald-700'
}

async function load() {
  const params = { ...filters.value }
  if (!params.tenant_id) delete params.tenant_id
  const result = await adminStore.fetchSubscriptions(params)
  if (result.status === 'success') {
    rows.value = result.data
    page.value = 1
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

function applyFilters() { load() }
function resetFilters() { filters.value = { plan: '', status: '', tenant_id: '', risk_level: '' }; load() }

function openActivate(s) { current.value = s; form.value = { plan: '', days: 30, newPlan: '', prorate: false, extendPeriod: false }; dialogs.value.activate = true }
function openExpire(s) { current.value = s; dialogs.value.expire = true }
function openExtend(s) { current.value = s; form.value = { ...form.value, days: 30, newPlan: '', prorate: false, extendPeriod: false }; dialogs.value.extend = true }
function viewSecurityDetails(s) { current.value = s; dialogs.value.security = true }
function openChangePlan(s) { current.value = s; form.value = { ...form.value, newPlan: '', prorate: false, extendPeriod: false }; dialogs.value.changePlan = true }
function closeDialogs() { dialogs.value = { activate: false, expire: false, extend: false, security: false, changePlan: false }; current.value = null }

async function refreshSecurityData() {
  if (!current.value) return
  securityLoading.value = true
  try {
    const res = await apiClient.post(`/admin/subscriptions/${current.value.id}/security-check`)
    if (res.data?.data) {
      Object.assign(current.value, res.data.data)
      if (typeof window?.showToast === 'function') window.showToast('تم تحديث البيانات الأمنية', 'success')
    }
  } catch (e) {
    // ✅ مستعادة من النسخة القديمة: showToast في catch
    if (typeof window?.showToast === 'function') window.showToast(e?.message || 'فشل التحديث', 'error')
  } finally { securityLoading.value = false }
}

async function blockSubscription() {
  if (!current.value) return
  if (!confirm(`هل أنت متأكد من حظر اشتراك #${current.value.id}؟`)) return
  const result = await adminStore.blockSubscription(current.value.id)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    closeDialogs()
    await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function submitActivate() {
  if (!current.value) return
  const body = form.value.plan ? { plan: form.value.plan } : {}
  const result = await adminStore.activateSubscription(current.value.id, form.value.plan)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    closeDialogs(); await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function submitExpire() {
  if (!current.value) return
  const result = await adminStore.expireSubscription(current.value.id)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    closeDialogs(); await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function submitChangePlan() {
  if (!current.value || !form.value.newPlan) return
  const result = await adminStore.changeSubscriptionPlan(
    current.value.id,
    form.value.newPlan,
    form.value.prorate,
    form.value.extendPeriod
  )
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    closeDialogs(); await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

async function submitExtend() {
  if (!current.value || !form.value.days || form.value.days <= 0) return
  const result = await adminStore.extendSubscription(current.value.id, form.value.days)
  if (result.status === 'success') {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'success')
    closeDialogs(); await load()
  } else {
    if (typeof window?.showToast === 'function') window.showToast(result.message, 'error')
  }
}

function exportCsv() {
  if (rows.value.length === 0) return
  const headers = ['id', 'tenant_id', 'plan_code', 'plan_name', 'status', 'start_date', 'end_date', 'payment_status']
  const csv = [headers.join(',')]
  rows.value.forEach(r => {
    csv.push([
      r.id, r.tenant_id, r.plan_code, r.plan_name, r.status, r.start_date, r.end_date, r.payment_status || ''
    ].map(v => `"${(v ?? '').toString().replaceAll('"', '""')}"`).join(','))
  })
  const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'subscriptions.csv'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => load());
</script>

<style scoped>

.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300 hover:shadow-xl hover:-translate-y-1; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 leading-none; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight; }

.form-input-modern, .form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }

.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm border border-transparent; }
.action-btn-xs { @apply w-8 h-8 rounded-xl flex items-center justify-center text-[10px] transition-all hover:text-white active:scale-90 shadow-sm border border-white; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>