<template>
  <div class="min-h-screen bg-slate-50/50 p-6 space-y-6" dir="rtl">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة الموظفين والصلاحيات</h1>
        <p class="text-xs text-slate-400 mt-1 font-medium">تعيين الأدوار وإدارة حسابات الفريق</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 h-11 px-6 bg-blue-600 text-white rounded-2xl font-black text-xs shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all active:scale-95">
        <i class="fas fa-user-plus"></i> إضافة موظف جديد
      </button>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">إجمالي الموظفين</p>
        <p class="text-3xl font-black text-slate-900">{{ users.length }}</p>
      </div>
      <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm">
        <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">نشط</p>
        <p class="text-3xl font-black text-emerald-600">{{ users.filter(u => u.status === 'active').length }}</p>
      </div>
      <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm">
        <p class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-1">موقوف</p>
        <p class="text-3xl font-black text-rose-500">{{ users.filter(u => u.status !== 'active').length }}</p>
      </div>
      <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm">
        <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1">الأدوار</p>
        <p class="text-3xl font-black text-blue-600">{{ roles.length }}</p>
      </div>
    </div>

    <!-- Search + Filter bar -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 flex flex-col md:flex-row gap-3">
      <div class="relative flex-1">
        <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 text-xs pointer-events-none"></i>
        <input v-model="search" type="text" placeholder="بحث بالاسم أو البريد الإلكتروني..." class="w-full h-11 bg-slate-50 border border-slate-100 rounded-2xl pr-10 pl-4 text-xs font-bold outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 transition-all" />
      </div>
      <select v-model="filterRole" class="h-11 bg-slate-50 border border-slate-100 rounded-2xl px-4 text-xs font-bold outline-none focus:border-blue-400 transition-all min-w-[160px]">
        <option value="">جميع الأدوار</option>
        <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleAr(r.name) }}</option>
      </select>
      <select v-model="filterStatus" class="h-11 bg-slate-50 border border-slate-100 rounded-2xl px-4 text-xs font-bold outline-none focus:border-blue-400 transition-all min-w-[140px]">
        <option value="">جميع الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">موقوف</option>
      </select>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-400 font-black uppercase tracking-tighter text-[10px]">
              <th class="px-6 py-5">الموظف</th>
              <th class="px-4 py-5">البريد الإلكتروني</th>
              <th class="px-4 py-5 text-center">الدور الوظيفي</th>
              <th class="px-4 py-5 text-center">الحالة</th>
              <th class="px-4 py-5 text-center">آخر دخول</th>
              <th class="px-6 py-5 text-center">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <template v-if="isLoading">
              <tr v-for="i in 5" :key="i">
                <td colspan="6" class="px-6 py-4"><div class="h-5 bg-slate-100 rounded-xl animate-pulse w-3/4"></div></td>
              </tr>
            </template>
            <tr v-else-if="!filtered.length">
              <td colspan="6" class="py-20 text-center">
                <div class="flex flex-col items-center gap-3 text-slate-300">
                  <i class="fas fa-users text-5xl"></i>
                  <p class="font-black text-xs uppercase tracking-widest">لا يوجد موظفون</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="u in filtered" :key="u.id" class="hover:bg-blue-50/20 transition-all group">
              <!-- Name + avatar -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div :class="['w-9 h-9 rounded-2xl flex items-center justify-center text-white font-black text-sm shrink-0', avatarColor(u.id)]">
                    {{ (u.name || u.username || '?')[0].toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-black text-slate-800 text-xs leading-none">{{ u.name || u.username }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">@{{ u.username }}</p>
                  </div>
                </div>
              </td>
              <!-- Email -->
              <td class="px-4 py-4 text-xs text-slate-500 font-medium">{{ u.email || '—' }}</td>
              <!-- Role dropdown -->
              <td class="px-4 py-4 text-center">
                <select
                  :value="primaryRoleId(u)"
                  @change="changeRole(u, $event.target.value)"
                  :disabled="savingId === u.id"
                  class="h-8 px-3 rounded-xl border border-slate-200 text-[11px] font-black text-slate-700 bg-white outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 transition-all cursor-pointer disabled:opacity-50"
                >
                  <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleAr(r.name) }}</option>
                </select>
              </td>
              <!-- Status -->
              <td class="px-4 py-4 text-center">
                <button @click="toggleStatus(u)" :disabled="savingId === u.id" class="disabled:opacity-50">
                  <span :class="['px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter cursor-pointer transition-all', u.status === 'active' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-rose-50 text-rose-500 hover:bg-rose-100']">
                    {{ u.status === 'active' ? 'نشط' : 'موقوف' }}
                  </span>
                </button>
              </td>
              <!-- Last login -->
              <td class="px-4 py-4 text-center text-[10px] text-slate-400 font-medium font-mono">
                {{ u.last_login ? formatDate(u.last_login) : 'لم يسجّل دخولاً' }}
              </td>
              <!-- Actions -->
              <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEdit(u)" class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-blue-50 hover:text-blue-600 transition-all" title="تعديل">
                    <i class="fas fa-pen text-[10px]"></i>
                  </button>
                  <button @click="confirmDelete(u)" class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-rose-50 hover:text-rose-500 transition-all" title="حذف">
                    <i class="fas fa-trash text-[10px]"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Create / Edit Modal ── -->
    <transition name="modal">
      <div v-if="modal.open" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-900">{{ modal.mode === 'create' ? 'إضافة موظف جديد' : 'تعديل بيانات الموظف' }}</h3>
            <button @click="modal.open = false" class="text-slate-300 hover:text-rose-400 transition-colors"><i class="fas fa-times"></i></button>
          </div>

          <div class="p-8 space-y-5 overflow-y-auto max-h-[70vh]">
            <div v-if="modal.mode === 'create'" class="space-y-4">
              <div>
                <label class="modal-label">الاسم الكامل</label>
                <input v-model="form.name" type="text" class="form-field" placeholder="أحمد محمد" />
              </div>
              <div>
                <label class="modal-label">اسم المستخدم</label>
                <input v-model="form.username" type="text" class="form-field" placeholder="ahmed.m" dir="ltr" />
              </div>
              <div>
                <label class="modal-label">البريد الإلكتروني</label>
                <input v-model="form.email" type="email" class="form-field" placeholder="ahmed@example.com" dir="ltr" />
              </div>
              <div>
                <label class="modal-label">كلمة المرور</label>
                <input v-model="form.password" type="password" class="form-field" placeholder="••••••••" dir="ltr" />
              </div>
            </div>

            <div>
              <label class="modal-label">الدور الوظيفي</label>
              <select v-model="form.role_id" class="form-field">
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleAr(r.name) }}</option>
              </select>
            </div>

            <div v-if="modal.mode === 'edit'">
              <label class="modal-label">كلمة المرور الجديدة (اتركها فارغة للإبقاء)</label>
              <input v-model="form.password" type="password" class="form-field" placeholder="••••••••" dir="ltr" />
            </div>

            <div>
              <label class="modal-label">الحالة</label>
              <select v-model="form.status" class="form-field">
                <option value="active">نشط</option>
                <option value="inactive">موقوف</option>
              </select>
            </div>

            <p v-if="modal.error" class="text-xs font-bold text-rose-500 bg-rose-50 rounded-xl px-4 py-3">{{ modal.error }}</p>
          </div>

          <div class="px-8 py-6 border-t border-slate-50 flex gap-3">
            <button @click="modal.open = false" class="flex-1 h-11 rounded-2xl border-2 border-slate-100 text-slate-400 font-black text-xs hover:bg-slate-50 transition-all">إلغاء</button>
            <button @click="saveUser" :disabled="modal.saving" class="flex-[2] h-11 rounded-2xl bg-blue-600 text-white font-black text-xs hover:bg-blue-700 transition-all active:scale-95 disabled:opacity-60 flex items-center justify-center gap-2">
              <i v-if="modal.saving" class="fas fa-spinner fa-spin"></i>
              {{ modal.saving ? 'جارٍ الحفظ...' : (modal.mode === 'create' ? 'إضافة الموظف' : 'حفظ التغييرات') }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Delete Confirm Modal ── -->
    <transition name="modal">
      <div v-if="deleteModal.open" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl overflow-hidden animate-modalIn border border-white p-8 text-center space-y-4">
          <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto">
            <i class="fas fa-trash text-2xl"></i>
          </div>
          <h3 class="font-black text-slate-800">حذف الموظف؟</h3>
          <p class="text-xs text-slate-400">سيتم حذف حساب <span class="font-black text-slate-700">{{ deleteModal.user?.name || deleteModal.user?.username }}</span> بشكل دائم.</p>
          <div class="flex gap-3 pt-2">
            <button @click="deleteModal.open = false" class="flex-1 h-11 rounded-2xl border-2 border-slate-100 text-slate-400 font-black text-xs">إلغاء</button>
            <button @click="deleteUser" :disabled="deleteModal.saving" class="flex-1 h-11 rounded-2xl bg-rose-600 text-white font-black text-xs hover:bg-rose-700 active:scale-95 disabled:opacity-60">
              <i v-if="deleteModal.saving" class="fas fa-spinner fa-spin ml-1"></i> حذف
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toast -->
    <transition name="toast">
      <div v-if="toast.show" :class="['fixed bottom-6 left-1/2 -translate-x-1/2 z-[200] px-6 py-3 rounded-2xl shadow-2xl font-black text-xs text-white flex items-center gap-2', toast.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600']">
        <i :class="toast.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
        {{ toast.message }}
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import apiClient from '@/config/axios'

// ── State ─────────────────────────────────────────────────────
const users    = ref([])
const roles    = ref([])
const isLoading = ref(false)
const savingId  = ref(null)
const search     = ref('')
const filterRole   = ref('')
const filterStatus = ref('')

const modal = reactive({ open: false, mode: 'create', saving: false, error: '', userId: null })
const form  = reactive({ name: '', username: '', email: '', password: '', role_id: null, status: 'active' })

const deleteModal = reactive({ open: false, saving: false, user: null })
const toast = reactive({ show: false, type: 'success', message: '' })

// ── Helpers ───────────────────────────────────────────────────
const roleLabels = {
  super_admin: 'مدير النظام الرئيسي',
  admin: 'مدير',
  manager: 'مشرف',
  cashier: 'كاشير',
  inventory_clerk: 'أمين مخزن',
  finance_officer: 'مسؤول مالي',
}
const roleAr = (name) => roleLabels[name] || name

const avatarColors = ['bg-blue-500','bg-violet-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-cyan-500','bg-indigo-500','bg-pink-500']
const avatarColor  = (id) => avatarColors[id % avatarColors.length]

const primaryRoleId = (u) => {
  if (u.role_ids) return parseInt(u.role_ids.split(',')[0])
  return u.role_id ?? ''
}

const formatDate = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })
}

const showToast = (message, type = 'success') => {
  toast.message = message; toast.type = type; toast.show = true
  setTimeout(() => { toast.show = false }, 3000)
}

// ── Computed ──────────────────────────────────────────────────
const filtered = computed(() => {
  return users.value.filter(u => {
    const q = search.value.toLowerCase()
    const matchQ = !q || (u.name || '').toLowerCase().includes(q) || (u.username || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
    const matchRole = !filterRole.value || String(primaryRoleId(u)) === String(filterRole.value)
    const matchStatus = !filterStatus.value || u.status === filterStatus.value
    return matchQ && matchRole && matchStatus
  })
})

// ── API calls ─────────────────────────────────────────────────
const fetchAll = async () => {
  isLoading.value = true
  try {
    const [uRes, rRes] = await Promise.all([
      apiClient.get('/rbac/users'),
      apiClient.get('/rbac/roles'),
    ])
    users.value = uRes.data?.data || uRes.data || []
    roles.value = (rRes.data?.data || rRes.data || []).filter(r => r.name !== 'super_admin')
  } catch (e) {
    showToast('فشل تحميل البيانات', 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Inline role change ────────────────────────────────────────
const changeRole = async (user, roleId) => {
  savingId.value = user.id
  try {
    await apiClient.put(`/rbac/users/${user.id}`, { roles: [parseInt(roleId)] })
    user.role_id  = parseInt(roleId)
    user.role_ids = String(roleId)
    user.roles    = roles.value.find(r => r.id == roleId)?.name || ''
    showToast('تم تغيير الدور بنجاح')
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل تغيير الدور', 'error')
  } finally {
    savingId.value = null
  }
}

// ── Toggle status ─────────────────────────────────────────────
const toggleStatus = async (user) => {
  savingId.value = user.id
  const newStatus = user.status === 'active' ? 'inactive' : 'active'
  try {
    await apiClient.put(`/rbac/users/${user.id}`, { status: newStatus })
    user.status = newStatus
    showToast(newStatus === 'active' ? 'تم تفعيل الحساب' : 'تم إيقاف الحساب')
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل تغيير الحالة', 'error')
  } finally {
    savingId.value = null
  }
}

// ── Create / Edit modal ───────────────────────────────────────
const openCreate = () => {
  Object.assign(form, { name: '', username: '', email: '', password: '', role_id: roles.value[0]?.id ?? null, status: 'active' })
  Object.assign(modal, { open: true, mode: 'create', saving: false, error: '', userId: null })
}

const openEdit = (user) => {
  Object.assign(form, {
    name: user.name || '', username: user.username || '',
    email: user.email || '', password: '',
    role_id: primaryRoleId(user), status: user.status || 'active'
  })
  Object.assign(modal, { open: true, mode: 'edit', saving: false, error: '', userId: user.id })
}

const saveUser = async () => {
  modal.error = ''
  if (modal.mode === 'create') {
    if (!form.username || !form.email || !form.password)
      return (modal.error = 'اسم المستخدم والبريد وكلمة المرور مطلوبة')
  }
  modal.saving = true
  try {
    if (modal.mode === 'create') {
      await apiClient.post('/rbac/users', {
        name: form.name, username: form.username, email: form.email,
        password: form.password, roles: [form.role_id], status: form.status
      })
    } else {
      const payload = { roles: [form.role_id], status: form.status }
      if (form.password) payload.password = form.password
      await apiClient.put(`/rbac/users/${modal.userId}`, payload)
    }
    modal.open = false
    await fetchAll()
    showToast(modal.mode === 'create' ? 'تم إضافة الموظف بنجاح' : 'تم تحديث البيانات')
  } catch (e) {
    modal.error = e?.response?.data?.message || 'فشل الحفظ'
  } finally {
    modal.saving = false
  }
}

// ── Delete ────────────────────────────────────────────────────
const confirmDelete = (user) => {
  deleteModal.user = user; deleteModal.open = true; deleteModal.saving = false
}

const deleteUser = async () => {
  deleteModal.saving = true
  try {
    await apiClient.delete(`/rbac/users/${deleteModal.user.id}`)
    deleteModal.open = false
    users.value = users.value.filter(u => u.id !== deleteModal.user.id)
    showToast('تم حذف الموظف')
  } catch (e) {
    showToast(e?.response?.data?.message || 'فشل الحذف', 'error')
    deleteModal.open = false
  } finally {
    deleteModal.saving = false
  }
}

onMounted(fetchAll)
</script>

<style scoped>
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-field  { @apply w-full h-11 bg-slate-50 border border-slate-200 rounded-2xl px-4 outline-none text-xs font-bold transition-all focus:border-blue-400 focus:ring-2 focus:ring-blue-50; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to       { opacity: 0; }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to       { opacity: 0; transform: translateX(-50%) translateY(20px); }

.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>
