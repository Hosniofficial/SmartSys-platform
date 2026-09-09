<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4" dir="rtl">
    <div class="w-full max-w-md bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm">
      <div class="mb-6">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200 text-white mb-4">
          <i class="fas fa-key text-xl"></i>
        </div>
        <h1 class="text-2xl font-black text-slate-900 mb-2">نسيت كلمة المرور؟</h1>
        <p class="text-xs text-slate-500 font-bold leading-relaxed">
          أدخل بريدك الإلكتروني المسجل في النظام، وسنرسل لك رابطاً لإعادة تعيين كلمة المرور (صلاحية الرابط 30 دقيقة).
        </p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">البريد الإلكتروني</label>
          <div class="relative">
            <input
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="example@domain.com"
              class="w-full h-11 rounded-2xl border border-slate-200 px-4 pr-11 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50"
            />
            <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
          </div>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full h-12 bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <template v-if="!loading">
            <span>إرسال رابط التعيين</span>
            <i class="fas fa-paper-plane text-xs"></i>
          </template>
          <template v-else>
            <span>جاري الإرسال...</span>
          </template>
        </button>

        <div
          v-if="message"
          class="p-4 rounded-2xl text-xs font-black"
          :class="ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'"
        >
          <div class="flex items-center gap-2">
            <i :class="ok ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
            <span>{{ message }}</span>
          </div>
        </div>
      </form>

      <div class="mt-6 text-center">
        <router-link to="/" class="text-[11px] font-black text-slate-400 hover:text-blue-600 transition-colors inline-flex items-center gap-2">
          <i class="fas fa-arrow-right"></i>
          <span>العودة لتسجيل الدخول</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import apiClient from '@/config/axios'

const email = ref('')
const loading = ref(false)
const message = ref('')
const ok = ref(false)

const submit = async () => {
  message.value = ''
  ok.value = false

  if (!email.value || !email.value.includes('@')) {
    message.value = 'يرجى إدخال بريد إلكتروني صالح'
    return
  }

  loading.value = true
  try {
    const res = await apiClient.post('/auth/forgot-password', { email: email.value.trim() }, { timeout: 30000 })
    ok.value = true
    message.value = res?.data?.message || 'إذا كان الإيميل مسجلاً، ستصلك رسالة خلال دقائق'
    email.value = ''
  } catch (e) {
    ok.value = true // عرض الرسالة العامة حتى لو حصل خطأ (أمان)
    message.value = e?.response?.data?.message || 'إذا كان الإيميل مسجلاً، ستصلك رسالة خلال دقائق'
  } finally {
    loading.value = false
  }
}
</script>
