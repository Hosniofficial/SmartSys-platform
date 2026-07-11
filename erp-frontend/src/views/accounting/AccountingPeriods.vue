<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-xl shadow-indigo-100 text-white shrink-0">
          <i class="fas fa-calendar-check text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none">الدورات المحاسبية</h1>
          <p class="text-slate-500 text-sm mt-1">إغلاق وفتح الفترات المحاسبية — القيود المرتبطة بدورة مغلقة يُرفض تسجيلها</p>
        </div>
      </div>
      <button @click="showCreate = true" class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 flex items-center gap-2 self-start">
        <i class="fas fa-plus"></i> دورة جديدة
      </button>
    </div>

    <!-- Status banner -->
    <div v-if="openPeriods.length === 0 && periods.length > 0" class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center gap-3">
      <i class="fas fa-exclamation-triangle text-amber-500 text-lg"></i>
      <span class="text-amber-800 font-semibold text-sm">لا توجد دورات مفتوحة — القيود الجديدة قد تُرفض إن كان تاريخها يقع ضمن دورة مغلقة.</span>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-20 text-slate-400">
        <i class="fas fa-spinner fa-spin text-3xl"></i>
      </div>
      <div v-else-if="periods.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
        <i class="fas fa-calendar-times text-5xl opacity-30"></i>
        <p class="font-medium">لا توجد دورات محاسبية — أنشئ أول دورة</p>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-100">
          <tr>
            <th class="px-6 py-4 text-right font-black text-slate-500 text-xs">اسم الدورة</th>
            <th class="px-4 py-4 text-center font-black text-slate-500 text-xs">من</th>
            <th class="px-4 py-4 text-center font-black text-slate-500 text-xs">إلى</th>
            <th class="px-4 py-4 text-center font-black text-slate-500 text-xs">الحالة</th>
            <th class="px-4 py-4 text-center font-black text-slate-500 text-xs">تاريخ الإغلاق</th>
            <th class="px-4 py-4 text-center font-black text-slate-500 text-xs">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in periods" :key="p.id" class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
            <td class="px-6 py-4 font-bold text-slate-800">{{ p.period_name }}</td>
            <td class="px-4 py-4 text-center text-slate-600 font-mono text-xs">{{ p.start_date }}</td>
            <td class="px-4 py-4 text-center text-slate-600 font-mono text-xs">{{ p.end_date }}</td>
            <td class="px-4 py-4 text-center">
              <span :class="p.status === 'open'
                ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                : 'bg-red-100 text-red-700 border border-red-200'"
                class="px-3 py-1 rounded-full text-xs font-black">
                {{ p.status === 'open' ? 'مفتوحة' : 'مغلقة' }}
              </span>
            </td>
            <td class="px-4 py-4 text-center text-slate-500 text-xs font-mono">{{ p.updated_at ? p.updated_at.slice(0,10) : '—' }}</td>
            <td class="px-4 py-4">
              <div class="flex items-center justify-center gap-2">
                <button v-if="p.status === 'open'"
                  @click="confirmClose(p)"
                  class="px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded-lg text-xs font-bold hover:bg-red-100 transition flex items-center gap-1">
                  <i class="fas fa-lock"></i> إغلاق
                </button>
                <button v-else
                  @click="doReopen(p.id)"
                  class="px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold hover:bg-emerald-100 transition flex items-center gap-1">
                  <i class="fas fa-lock-open"></i> إعادة فتح
                </button>
                <button v-if="p.status === 'open'"
                  @click="doDelete(p.id)"
                  class="px-3 py-1.5 bg-slate-50 text-slate-500 border border-slate-200 rounded-lg text-xs font-bold hover:bg-red-50 hover:text-red-500 transition">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 text-right" dir="rtl">
          <h2 class="text-xl font-black text-slate-900 mb-6 flex items-center gap-3">
            <i class="fas fa-calendar-plus text-indigo-500"></i> إنشاء دورة محاسبية
          </h2>
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-500 mb-1">اسم الدورة <span class="text-red-500">*</span></label>
              <input v-model="form.period_name" type="text" placeholder="مثال: 2025-Q1 أو يناير 2025"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">من تاريخ <span class="text-red-500">*</span></label>
                <input v-model="form.start_date" type="date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">إلى تاريخ <span class="text-red-500">*</span></label>
                <input v-model="form.end_date" type="date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              </div>
            </div>
          </div>
          <div class="flex gap-3 mt-6">
            <button @click="doCreate" :disabled="saving"
              class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition disabled:opacity-50">
              <i class="fas fa-plus mr-1"></i> {{ saving ? 'جارٍ الحفظ...' : 'إنشاء' }}
            </button>
            <button @click="showCreate = false" class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-200 transition">
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Confirm Close Modal -->
    <Teleport to="body">
      <div v-if="closeTarget" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeTarget = null">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 text-right" dir="rtl">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center text-red-500">
              <i class="fas fa-lock text-xl"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900">تأكيد إغلاق الدورة</h2>
          </div>
          <p class="text-slate-600 text-sm mb-6">
            سيتم إغلاق دورة <strong class="text-slate-900">{{ closeTarget?.period_name || 'هذه الدورة' }}</strong><br>
            <span class="text-red-500 font-semibold">لن يمكن تسجيل أي قيد بتاريخ يقع ضمن هذه الفترة بعد الإغلاق.</span>
          </p>
          <div class="flex gap-3">
            <button @click="doClose(closeTarget.id)" :disabled="saving"
              class="flex-1 py-2.5 bg-red-600 text-white rounded-xl font-bold text-sm hover:bg-red-700 transition disabled:opacity-50">
              {{ saving ? 'جارٍ...' : 'تأكيد الإغلاق' }}
            </button>
            <button @click="closeTarget = null" class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-200 transition">
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
      <div v-if="toast.show" :class="['fixed top-6 left-1/2 -translate-x-1/2 z-[9999] px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm flex items-center gap-2 transition-all',
        toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600']">
        <i :class="toast.type === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
        {{ toast.message }}
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const periods   = ref([])
const loading   = ref(false)
const saving    = ref(false)
const showCreate = ref(false)
const closeTarget = ref(null)

const form = ref({ period_name: '', start_date: '', end_date: '' })
const toast = ref({ show: false, message: '', type: 'success' })

const openPeriods = computed(() => periods.value.filter(p => p.status === 'open'))

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3500)
}

const api = () => axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: { Authorization: `Bearer ${authStore.token}`, 'X-Tenant-ID': authStore.tenantId }
})

const load = async () => {
  loading.value = true
  try {
    const res = await api().get('/accounting-periods')
    let resData = res.data
    if (typeof resData === 'string') {
      try { resData = JSON.parse(resData) } catch { resData = {} }
    }
    const raw = resData?.data ?? resData ?? []
    periods.value = Array.isArray(raw) ? raw : []
    if (!Array.isArray(raw)) console.error('Unexpected response format:', raw)
  } catch (e) {
    periods.value = []
    showToast(e?.response?.data?.message || 'تعذر تحميل الدورات', 'error')
  } finally { loading.value = false }
}

const doCreate = async () => {
  if (!form.value.period_name || !form.value.start_date || !form.value.end_date) {
    showToast('يرجى تعبئة جميع الحقول المطلوبة', 'error'); return
  }
  saving.value = true
  try {
    await api().post('/accounting-periods', form.value)
    showToast('تم إنشاء الدورة المحاسبية بنجاح')
    showCreate.value = false
    form.value = { period_name: '', start_date: '', end_date: '' }
    await load()
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل الإنشاء', 'error')
  } finally { saving.value = false }
}

const confirmClose = (p) => { closeTarget.value = p }

const doClose = async (id) => {
  saving.value = true
  try {
    await api().put(`/accounting-periods/${id}/close`)
    showToast('تم إغلاق الدورة المحاسبية')
    closeTarget.value = null
    await load()
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل الإغلاق', 'error')
  } finally { saving.value = false }
}

const doReopen = async (id) => {
  if (!confirm('هل أنت متأكد من إعادة فتح هذه الدورة؟')) return
  try {
    await api().put(`/accounting-periods/${id}/reopen`)
    showToast('تم إعادة فتح الدورة')
    await load()
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل إعادة الفتح', 'error')
  }
}

const doDelete = async (id) => {
  if (!confirm('حذف هذه الدورة؟')) return
  try {
    await api().delete(`/accounting-periods/${id}`)
    showToast('تم الحذف')
    await load()
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل الحذف', 'error')
  }
}

onMounted(load)
</script>
