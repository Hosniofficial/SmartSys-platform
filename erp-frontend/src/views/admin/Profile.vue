<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <div class="max-w-6xl mx-auto space-y-8">
      
      <!-- Profile Hero Header -->
      <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-hidden group">
        <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50/50 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-8">
          <div class="w-24 h-24 bg-blue-600 rounded-[2rem] flex items-center justify-center text-3xl font-black text-white shadow-xl shadow-blue-200 border-4 border-white transition-transform duration-500 hover:rotate-6">
            {{ userInitials }}
          </div>
          <div class="text-center md:text-right">
            <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">{{ profileForm.name || authUser?.name }}</h1>
            <p class="text-slate-400 text-sm mt-3 font-bold uppercase tracking-widest flex items-center justify-center md:justify-start gap-2">
              <i class="fas fa-id-badge text-blue-500"></i>
              إدارة إعدادات حسابك الشخصي والوصول
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Main Forms Area -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- Personal Information Card -->
          <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 transition-all hover:shadow-md">
            <div class="flex items-center gap-4 mb-10">
              <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-user-gear"></i></div>
              <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">المعلومات الشخصية</h2>
            </div>

            <form @submit.prevent="updateProfile" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2 group">
                  <label class="modal-label">الاسم الكامل <span class="text-rose-500">*</span></label>
                  <div class="relative">
                    <input v-model.trim="profileForm.name" type="text" class="form-input-modern pr-11 font-bold" placeholder="أدخل اسمك الكامل" autocomplete="name" />
                    <i class="fas fa-signature absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                  </div>
                </div>

                <div class="space-y-2 group">
                  <label class="modal-label">البريد الإلكتروني</label>
                  <div class="relative">
                    <input v-model.trim="profileForm.email" type="email" class="form-input-modern pr-11 font-bold" placeholder="example@domain.com" autocomplete="email" />
                    <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                  </div>
                </div>

                <div class="md:col-span-2 space-y-2 group">
                  <label class="modal-label">رقم الهاتف</label>
                  <div class="relative">
                    <input v-model.trim="profileForm.phone" type="text" class="form-input-modern pr-11 font-mono font-bold" placeholder="05XXXXXXXX" autocomplete="tel" />
                    <i class="fas fa-phone absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
                  </div>
                </div>
              </div>

              <div class="flex flex-col md:flex-row md:items-center gap-6 pt-4 border-t border-slate-50">
                <button type="submit" :disabled="profileLoading" class="h-12 px-10 bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-50 flex items-center gap-3">
                  <BaseSpinner v-if="profileLoading" :size="16" color="#fff" :margin="0" />
                  <i v-else class="fas fa-save"></i>
                  حفظ التعديلات
                </button>

                <transition name="slide-fade">
                  <p v-if="profileMessage" :class="[profileMessageType === 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100']" class="px-4 py-2 rounded-xl text-[11px] font-black uppercase border animate-fadeIn">
                    <i :class="[profileMessageType === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle', 'ml-2']"></i>
                    {{ profileMessage }}
                  </p>
                </transition>
              </div>
            </form>
          </section>

          <!-- Password Security Card -->
          <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 transition-all hover:shadow-md">
            <div class="flex items-center gap-4 mb-10">
              <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-shield-halved"></i></div>
              <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">أمان الحساب</h2>
            </div>

            <form @submit.prevent="changePassword" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2 space-y-2 group">
                  <label class="modal-label">كلمة المرور الحالية <span class="text-rose-500">*</span></label>
                  <div class="relative">
                    <input v-model="passwordForm.current_password" type="password" class="form-input-modern pr-11 font-mono font-black tracking-widest" autocomplete="current-password" />
                    <i class="fas fa-key absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-rose-500 transition-colors"></i>
                  </div>
                </div>

                <div class="space-y-2 group">
                  <label class="modal-label">كلمة المرور الجديدة</label>
                  <div class="relative">
                    <input v-model="passwordForm.new_password" type="password" class="form-input-modern pr-11 font-mono font-black tracking-widest" autocomplete="new-password" />
                    <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-rose-500 transition-colors"></i>
                  </div>
                </div>

                <div class="space-y-2 group">
                  <label class="modal-label">تأكيد كلمة المرور</label>
                  <div class="relative">
                    <input v-model="passwordForm.confirm_password" type="password" class="form-input-modern pr-11 font-mono font-black tracking-widest" autocomplete="new-password" />
                    <i class="fas fa-lock-open absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-rose-500 transition-colors"></i>
                  </div>
                </div>
              </div>

              <div class="flex flex-col md:flex-row md:items-center gap-6 pt-4 border-t border-slate-50">
                <button type="submit" :disabled="passwordLoading" class="h-12 px-10 bg-rose-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-rose-100 hover:bg-rose-700 active:scale-95 transition-all disabled:opacity-50 flex items-center gap-3">
                  <BaseSpinner v-if="passwordLoading" :size="16" color="#fff" :margin="0" />
                  <i v-else class="fas fa-shield-keyhole"></i>
                  تحديث كلمة المرور
                </button>

                <transition name="slide-fade">
                  <p v-if="passwordMessage" :class="[passwordMessageType === 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100']" class="px-4 py-2 rounded-xl text-[11px] font-black uppercase border animate-fadeIn">
                    <i :class="[passwordMessageType === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle', 'ml-2']"></i>
                    {{ passwordMessage }}
                  </p>
                </transition>
              </div>
            </form>
          </section>
        </div>

        <!-- Sidebar Info -->
        <div class="lg:col-span-4 space-y-6">
          
          <!-- Account Meta Card -->
          <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 relative overflow-hidden">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
              <i class="fas fa-circle-info text-blue-500"></i> بيانات الحساب النظامية
            </h3>
            
            <div class="space-y-4 font-bold text-sm">
              <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 transition-all hover:bg-white hover:border-blue-100">
                <span class="text-slate-400 text-xs">اسم المستخدم</span>
                <span class="text-slate-800 font-black font-mono">@{{ authUser?.username || '-' }}</span>
              </div>
              <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 transition-all hover:bg-white hover:border-blue-100">
                <span class="text-slate-400 text-xs">معرّف الحساب (ID)</span>
                <span class="text-slate-800 font-black font-mono">#{{ authUser?.id || '-' }}</span>
              </div>
            </div>
          </div>

          <!-- Security Note -->
          <div class="bg-slate-900 rounded-[2rem] p-8 text-white shadow-2xl relative overflow-hidden group">
            <div class="absolute top-0 left-0 w-24 h-24 bg-blue-500/10 rounded-full -translate-x-12 -translate-y-12 transition-transform group-hover:scale-125"></div>
            <h3 class="text-blue-400 text-[10px] font-black uppercase tracking-[0.2em] mb-3 leading-none">ملاحظة أمنية</h3>
            <p class="text-white/60 text-xs font-bold leading-relaxed italic">
              عند تغيير البريد الإلكتروني، قد يطلب منك النظام إعادة تسجيل الدخول لتحديث جلسة العمل الخاصة بك ولتأمين حسابك.
            </p>
            <div class="mt-6 flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span class="text-[9px] font-black text-white/30 uppercase tracking-widest leading-none">نظام حماية المستخدمين v2.4</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
import apiClient from '@/config/axios'
import { useAuthStore } from '@/stores/auth'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'

export default {
  name: 'Profile',
  components: { BaseSpinner },
  data() {
    return {
      authStore: useAuthStore(),
      profileLoading: false,
      profileMessage: '',
      profileMessageType: 'success',

      passwordLoading: false,
      passwordMessage: '',
      passwordMessageType: 'success',

      profileForm: { name: '', email: '', phone: '' },
      passwordForm: { current_password: '', new_password: '', confirm_password: '' }
    }
  },
  computed: {
    authUser() {
      const fromPinia = this.authStore?.user || null
      if (fromPinia) return fromPinia
      try {
        const raw = localStorage.getItem('user')
        return raw ? JSON.parse(raw) : null
      } catch (e) { return null }
    },
    userInitials() {
      const name = (this.profileForm.name || this.authUser?.name || this.authUser?.username || '').trim()
      if (!name) return 'U'
      const parts = name.split(' ').filter(Boolean)
      const a = parts[0]?.[0] || ''
      const b = parts[1]?.[0] || ''
      return (a + b).toUpperCase() || (name[0] ? name[0].toUpperCase() : 'U')
    }
  },
  mounted() {
    const u = this.authUser
    this.profileForm.name = u?.name || ''
    this.profileForm.email = u?.email || ''
    this.profileForm.phone = u?.phone || ''
    this.fetchMe()
  },
  methods: {
    async fetchMe() {
      try {
        const res = await apiClient.get('/users/me')
        const me = res?.data?.data
        if (!me) return
        this.profileForm.name = me?.name || this.profileForm.name
        this.profileForm.email = me?.email || ''
        this.profileForm.phone = me?.phone || ''
        const merged = { ...(this.authStore?.user || this.authUser || {}), ...me }
        localStorage.setItem('user', JSON.stringify(merged))
        if (this.authStore) this.authStore.user = merged
      } catch (e) { /* ignore */ }
    },

    async updateProfile() {
      this.profileMessage = ''; this.profileMessageType = 'success'
      if (!this.profileForm.name) { this.profileMessageType = 'error'; this.profileMessage = 'الاسم الكامل حقل مطلوب'; return }
      this.profileLoading = true
      try {
        const res = await apiClient.post('/users/update-profile', { name: this.profileForm.name, email: this.profileForm.email, phone: this.profileForm.phone })
        this.profileMessage = res?.data?.message || 'تم تحديث البيانات بنجاح'
        if (res?.data?.data) {
          const merged = { ...(this.authUser || {}), ...res.data.data }
          localStorage.setItem('user', JSON.stringify(merged))
          if (this.authStore) this.authStore.user = merged
        }
      } catch (e) {
        this.profileMessageType = 'error'; this.profileMessage = e?.response?.data?.message || 'فشل التحديث'
      } finally { this.profileLoading = false }
    },

    async changePassword() {
      this.passwordMessage = ''; this.passwordMessageType = 'success'
      if (!this.passwordForm.current_password || !this.passwordForm.new_password) { this.passwordMessageType = 'error'; this.passwordMessage = 'يرجى إدخال كلمات المرور'; return }
      if (this.passwordForm.new_password !== this.passwordForm.confirm_password) { this.passwordMessageType = 'error'; this.passwordMessage = 'كلمتا المرور غير متطابقتين'; return }
      this.passwordLoading = true
      try {
        const res = await apiClient.post('/users/change-password', { current_password: this.passwordForm.current_password, new_password: this.passwordForm.new_password })
        this.passwordMessage = res?.data?.message || 'تم تغيير كلمة المرور'
        this.passwordForm = { current_password: '', new_password: '', confirm_password: '' }
      } catch (e) {
        this.passwordMessageType = 'error'; this.passwordMessage = e?.response?.data?.message || 'فشل تغيير كلمة المرور'
      } finally { this.passwordLoading = false }
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* Modern UI Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

/* Dashboard Styling */
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm border border-transparent inline-flex items-center; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>