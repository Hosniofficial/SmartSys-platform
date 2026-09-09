<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4" dir="rtl">
    <div class="w-full max-w-md bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm">
      <div class="text-center mb-6">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200 text-white mb-4 mx-auto">
          <i class="fas fa-envelope-circle-check text-xl"></i>
        </div>
        <h1 class="text-2xl font-black text-slate-900 mb-2">تأكيد البريد الإلكتروني</h1>
        <p class="text-xs text-slate-500 font-bold leading-relaxed">
          {{ statusText }}
        </p>
      </div>

      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full mx-auto"></div>
        <p class="mt-4 text-xs font-black text-slate-400">جاري التحقق...</p>
      </div>

      <div
        v-else-if="message"
        class="p-4 rounded-2xl text-center"
        :class="ok ? 'bg-emerald-50 border border-emerald-100' : 'bg-rose-50 border border-rose-100'"
      >
        <div class="text-4xl mb-3">
          {{ ok ? '🎉' : '⚠️' }}
        </div>
        <p class="text-sm font-black" :class="ok ? 'text-emerald-700' : 'text-rose-700'">
          {{ message }}
        </p>
        <p v-if="ok && countdown > 0" class="text-xs text-emerald-600 font-bold mt-2">
          {{ autoLoginFailed ? 'سيتم توجيهك لتسجيل الدخول' : 'جاري تسجيل الدخول تلقائياً' }} خلال {{ countdown }} ثانية...
        </p>
      </div>

      <div class="mt-6 text-center space-y-3">
        <router-link
          v-if="ok && autoLoginFailed"
          to="/"
          class="inline-flex items-center gap-2 h-12 px-6 bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all"
        >
          <i class="fas fa-arrow-right"></i>
          <span>تسجيل الدخول</span>
        </router-link>

        <!-- Resend Form -->
        <div v-if="!ok" class="space-y-3">
          <div v-if="!emailFromQuery" class="relative">
            <input
              v-model="emailInput"
              type="email"
              placeholder="أدخل بريدك الإلكتروني"
              class="w-full h-11 rounded-2xl border border-slate-200 px-4 pr-11 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50 text-sm"
            />
            <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
          </div>

          <button
            @click="resend"
            :disabled="resendLoading || (!emailFromQuery && !emailInput)"
            class="w-full h-12 bg-slate-100 text-slate-700 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 active:scale-95 transition-all disabled:opacity-50"
          >
            {{ resendLoading ? 'جاري الإرسال...' : 'إعادة إرسال الرابط' }}
          </button>
        </div>

        <router-link
          v-if="!ok"
          to="/"
          class="block text-[11px] font-black text-slate-400 hover:text-blue-600 transition-colors"
        >
          العودة لتسجيل الدخول
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/config/axios'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const token = computed(() => (route.query.token || '').toString())
const emailFromQuery = computed(() => (route.query.email || '').toString())
const email = computed(() => emailFromQuery.value || emailInput.value.trim())

const loading = ref(true)
const resendLoading = ref(false)
const message = ref('')
const ok = ref(false)
const countdown = ref(3)
const emailInput = ref('')
const autoLoginFailed = ref(false)

const statusText = computed(() => {
  if (loading.value) return 'جاري التحقق من صلاحية الرابط...'
  if (ok.value) return 'تم تأكيد بريدك الإلكتروني بنجاح!'
  return 'الرابط غير صالح أو منتهي الصلاحية.'
})

const verify = async () => {
  if (!token.value) {
    loading.value = false
    message.value = 'الرابط غير صالح أو مفقود'
    return
  }

  try {
    const res = await apiClient.post('/auth/verify-email', { token: token.value })
    
    ok.value = true
    message.value = res?.data?.message || 'تم تأكيد بريدك الإلكتروني بنجاح!'

    const accessToken = res?.data?.access_token
    const userData    = res?.data?.data?.user
    const isSetupComplete = res?.data?.data?.is_setup_complete

    if (accessToken && userData) {
      // Server already sent refresh token cookie automatically in response
      // No flags, no workarounds needed — just store auth data and redirect
      authStore.setAuthData(userData, accessToken)
      
      const destination = isSetupComplete ? '/cashier-dashboard' : '/setup'
      message.value = 'تم تأكيد بريدك الإلكتروني — جاري تسجيل الدخول تلقائياً...'
      startCountdown(destination)
    } else {
      // Fallback: redirect to login page
      autoLoginFailed.value = true
      startCountdown('/')
    }
  } catch (e) {
    ok.value = false
    message.value = e?.response?.data?.message || 'الرابط غير صالح أو منتهي الصلاحية'
  } finally {
    loading.value = false
  }
}

const startCountdown = (destination = '/') => {
  const interval = setInterval(async () => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(interval)
      try {
        await router.push(destination)
      } catch (err) {
        window.location.href = destination === '/' ? window.location.origin + '/' : destination
      }
    }
  }, 1000)
}

const resend = async () => {
  const targetEmail = email.value
  if (!targetEmail) {
    message.value = 'البريد الإلكتروني مطلوب لإعادة الإرسال'
    return
  }

  resendLoading.value = true
  try {
    await apiClient.post('/auth/verify-email/resend', {
      email: targetEmail,
      purpose: 'registration'
    }, { timeout: 30000 })
    message.value = 'تم إعادة إرسال رابط التأكيد — تحقق من بريدك الإلكتروني'
    ok.value = true
    startCountdown('/')
  } catch (e) {
    message.value = e?.response?.data?.message || 'فشل إعادة إرسال الرابط'
    ok.value = false
  } finally {
    resendLoading.value = false
  }
}

onMounted(() => {
  verify()
})
</script>
