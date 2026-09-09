<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4" dir="rtl">
    <div class="w-full max-w-md bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm">
      <h1 class="text-xl font-black text-slate-900 mb-2">إعادة تعيين كلمة المرور</h1>
      <p class="text-xs text-slate-500 font-bold mb-8">أدخل كلمة المرور الجديدة لإكمال العملية</p>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">كلمة المرور الجديدة</label>
          <input
            v-model="newPassword"
            type="password"
            autocomplete="new-password"
            class="w-full h-11 rounded-2xl border border-slate-200 px-4 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50"
          />
          <!-- Password Strength Indicator -->
          <div v-if="newPassword" class="mt-3">
            <div class="flex items-center gap-2 mb-1.5">
              <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div
                  class="h-full transition-all duration-300"
                  :class="strengthColor"
                  :style="{ width: strengthWidth }"
                ></div>
              </div>
              <span class="text-[10px] font-black uppercase" :class="strengthTextColor">
                {{ strengthLabel }}
              </span>
            </div>
            <ul class="text-[10px] text-slate-400 space-y-0.5">
              <li :class="hasMinLength ? 'text-emerald-500' : ''">
                <i :class="hasMinLength ? 'fas fa-check' : 'fas fa-circle text-[4px]'" class="ml-1"></i>
                8 أحرف على الأقل
              </li>
              <li :class="hasNumber ? 'text-emerald-500' : ''">
                <i :class="hasNumber ? 'fas fa-check' : 'fas fa-circle text-[4px]'" class="ml-1"></i>
                رقم واحد على الأقل
              </li>
              <li :class="hasSpecial ? 'text-emerald-500' : ''">
                <i :class="hasSpecial ? 'fas fa-check' : 'fas fa-circle text-[4px]'" class="ml-1"></i>
                رمز خاص (!@#$...)
              </li>
            </ul>
          </div>
        </div>

        <div>
          <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">تأكيد كلمة المرور</label>
          <input
            v-model="confirmPassword"
            type="password"
            autocomplete="new-password"
            class="w-full h-11 rounded-2xl border border-slate-200 px-4 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full h-12 bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-50"
        >
          {{ loading ? 'جاري التحديث...' : 'تحديث كلمة المرور' }}
        </button>

        <p v-if="message" class="text-[11px] font-black" :class="ok ? 'text-emerald-600' : 'text-rose-600'">
          {{ message }}
        </p>
      </form>

      <div class="mt-6 text-center">
        <router-link to="/" class="text-[11px] font-black text-slate-400 hover:text-blue-600">العودة لتسجيل الدخول</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/config/axios'

const route = useRoute()
const router = useRouter()

const token = computed(() => (route.query.token || '').toString())

const newPassword = ref('')
const confirmPassword = ref('')
const loading = ref(false)
const message = ref('')
const ok = ref(false)

// Password strength computed properties
const hasMinLength = computed(() => newPassword.value.length >= 8)
const hasNumber = computed(() => /\d/.test(newPassword.value))
const hasSpecial = computed(() => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(newPassword.value))
const hasUppercase = computed(() => /[A-Z]/.test(newPassword.value))

const strengthScore = computed(() => {
  let score = 0
  if (hasMinLength.value) score++
  if (hasNumber.value) score++
  if (hasSpecial.value) score++
  if (hasUppercase.value) score++
  return score
})

const strengthLabel = computed(() => {
  if (strengthScore.value <= 1) return 'ضعيفة'
  if (strengthScore.value === 2) return 'متوسطة'
  if (strengthScore.value === 3) return 'جيدة'
  return 'قوية'
})

const strengthColor = computed(() => {
  if (strengthScore.value <= 1) return 'bg-rose-500'
  if (strengthScore.value === 2) return 'bg-amber-500'
  if (strengthScore.value === 3) return 'bg-blue-500'
  return 'bg-emerald-500'
})

const strengthTextColor = computed(() => {
  if (strengthScore.value <= 1) return 'text-rose-500'
  if (strengthScore.value === 2) return 'text-amber-500'
  if (strengthScore.value === 3) return 'text-blue-500'
  return 'text-emerald-500'
})

const strengthWidth = computed(() => {
  return `${(strengthScore.value / 4) * 100}%`
})

const submit = async () => {
  message.value = ''
  ok.value = false

  if (!token.value) {
    message.value = 'الرابط غير صالح'
    return
  }

  if (!newPassword.value) {
    message.value = 'كلمة المرور الجديدة مطلوبة'
    return
  }

  if (newPassword.value.length < 8) {
    message.value = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'
    return
  }

  if (newPassword.value !== confirmPassword.value) {
    message.value = 'كلمتا المرور غير متطابقتين'
    return
  }

  loading.value = true
  try {
    const res = await apiClient.post('/auth/reset-password', {
      token: token.value,
      new_password: newPassword.value
    })

    ok.value = true
    message.value = res?.data?.message || 'تم تحديث كلمة المرور بنجاح'

    setTimeout(() => {
      router.push('/')
    }, 800)
  } catch (e) {
    ok.value = false
    message.value = e?.response?.data?.message || 'فشل تحديث كلمة المرور'
  } finally {
    loading.value = false
  }
}
</script>
