<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 lg:p-8 text-right animate-fadeIn" dir="rtl">
    
    <!-- Register Card Container -->
    <div class="w-full max-w-xl bg-white rounded-[2.5rem] shadow-2xl shadow-blue-100/50 overflow-hidden border border-white relative transition-all">
      
      <!-- Top Decorative Element -->
      <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-blue-600 to-indigo-600"></div>

      <!-- Content Area -->
      <div class="p-8 md:p-12">
        <!-- Header -->
        <div class="text-center mb-10">
          <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shadow-sm mx-auto mb-6">
            <i class="fas fa-user-plus text-2xl"></i>
          </div>
          <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">إنشاء حساب جديد</h1>
          <p class="text-slate-400 mt-3 font-bold text-sm uppercase tracking-widest leading-relaxed">ابدأ في إدارة مشروعك بسهولة — تجربة مجانية بالكامل</p>
        </div>

        <!-- Success State (After Registration) -->
        <div v-if="registered" class="text-center py-8 space-y-6">
          <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-3xl flex items-center justify-center mx-auto shadow-sm">
            <i class="fas fa-envelope-circle-check text-3xl"></i>
          </div>
          <div>
            <h2 class="text-xl font-black text-slate-900 mb-2">تم إنشاء حسابك!</h2>
            <p class="text-sm font-bold text-slate-500 leading-relaxed">
              تحقق من بريدك الإلكتروني لتأكيد الحساب<br/>
              <span class="text-blue-600">{{ form.email }}</span>
            </p>
          </div>
          <div class="bg-amber-50 border border-amber-100 p-4 rounded-2xl">
            <p class="text-xs font-bold text-amber-700 leading-relaxed">
              <i class="fas fa-info-circle ml-1"></i>
              لم يصلك الإيميل؟ تحقق من مجلد Spam أو اضغط أدناه لإعادة الإرسال
            </p>
          </div>
          <button
            @click="resendVerification"
            :disabled="resendLoading"
            class="w-full h-12 bg-slate-100 text-slate-700 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 active:scale-95 transition-all disabled:opacity-50"
          >
            {{ resendLoading ? 'جاري الإرسال...' : 'إعادة إرسال رابط التأكيد' }}
          </button>
          <router-link
            to="/"
            class="inline-flex items-center gap-2 text-sm font-black text-slate-400 hover:text-blue-600 transition-colors"
          >
            <i class="fas fa-arrow-right"></i>
            <span>تسجيل الدخول</span>
          </router-link>
        </div>

        <!-- Registration Form -->
        <!-- [PRESERVED] novalidate on form -->
        <form v-else @submit.prevent="submit" class="space-y-5" novalidate>
          
          <!-- Full Name Field -->
          <div class="space-y-2 group">
            <label for="full_name" class="modal-label">اسم صاحب النشاط</label>
            <div class="relative">
              <input
                id="full_name"
                v-model="form.full_name"
                type="text"
                class="form-input-modern pr-11 font-bold"
                placeholder="full name"
              />
              <i class="fas fa-id-card absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <!-- Username Field -->
          <div class="space-y-2 group">
            <label for="username" class="modal-label">اسم المستخدم <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                id="username"
                v-model="form.username"
                type="text"
                class="form-input-modern pr-11 font-black font-mono tracking-wider"
                required
                placeholder="username"
              />
              <i class="fas fa-at absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <!-- Email Field -->
          <div class="space-y-2 group">
            <label for="email" class="modal-label">البريد الإلكتروني <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="form-input-modern pr-11 font-bold"
                required
                placeholder="mail@example.com"
              />
              <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <!-- Password Field -->
          <div class="space-y-2 group">
            <label for="password" class="modal-label">كلمة المرور <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                type="password"
                class="form-input-modern pr-11 font-black font-mono tracking-widest"
                required
                placeholder="••••••••"
              />
              <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
            <p class="text-[9px] text-slate-400 font-bold px-1 uppercase tracking-tighter">استخدم 8 أحرف على الأقل تحتوي على أرقام ورموز</p>
          </div>

          <!-- Feedback Messages -->
          <transition name="slide-fade">
            <div v-if="error" class="bg-rose-50 border border-rose-100 p-4 rounded-2xl flex items-center gap-3 animate-fadeIn shadow-sm shadow-rose-50">
              <div class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center shrink-0 shadow-sm"><i class="fas fa-exclamation-triangle text-xs"></i></div>
              <span class="text-xs font-black text-rose-700 leading-relaxed">{{ error }}</span>
            </div>
          </transition>

          <transition name="slide-fade">
            <div v-if="success" class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl flex items-center gap-3 animate-fadeIn shadow-sm shadow-emerald-50">
              <div class="w-8 h-8 bg-emerald-500 text-white rounded-lg flex items-center justify-center shrink-0 shadow-sm"><i class="fas fa-check-circle text-xs"></i></div>
              <span class="text-xs font-black text-emerald-700 leading-relaxed">{{ success }}</span>
            </div>
          </transition>

          <!-- Submit Button -->
          <div class="pt-6">
            <button
              type="submit"
              :disabled="loading"
              class="w-full h-14 bg-blue-600 text-white rounded-2xl font-black text-base shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-50"
            >
              <template v-if="!loading">
                <span>تأكيد وإنشاء الحساب</span>
                <i class="fas fa-rocket text-xs"></i>
              </template>
              <BaseSpinner v-else :size="20" color="#fff" margin="0" />
            </button>
          </div>

          <!-- Login Link -->
          <div class="text-center pt-8 border-t border-slate-50">
            <p class="text-xs font-bold text-slate-400">لديك حساب مسجل بالفعل؟</p>
            <router-link to="/" class="inline-block mt-3 text-sm font-black text-slate-900 hover:text-blue-600 transition-colors decoration-blue-200 underline underline-offset-8 uppercase tracking-tight">تسجيل الدخول</router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import apiClient from '@/config/axios'

// --- Logic ---
const authStore = useAuthStore();
const router = useRouter()
const loading = ref(false)
const error = ref('')
const success = ref('')
const registered = ref(false)
const resendLoading = ref(false)

const form = ref({ full_name: '', username: '', email: '', password: '' })

// Logic: Validation (Preserved — original error message restored)
function validate() {
  if (!form.value.username || !form.value.email || !form.value.password) {
    // [PRESERVED] original validation message from old file
    error.value = 'يرجى تعبئة الحقول المطلوبة'
    return false
  }
  error.value = ''
  return true
}

// Logic: Handle Submit (Preserved — all original messages restored)
async function submit() {
  if (!validate()) return
  loading.value = true
  error.value = ''
  success.value = ''
  const result = await authStore.register({
    full_name: form.value.full_name,
    username: form.value.username,
    email: form.value.email,
    password: form.value.password,
  })
  if (result.status === 'success') {
    // ✅ Backend already sent verification email in createSecureTrial()
    // No need to send again — backend handles it
    registered.value = true
  } else {
    // [PRESERVED] original fallback message from old file
    error.value = result.message || 'تعذر إكمال التسجيل'
  }
  loading.value = false
}

// Resend verification email
async function resendVerification() {
  if (!form.value.email) return
  resendLoading.value = true
  try {
    await apiClient.post('/auth/verify-email/resend', {
      email: form.value.email,
      purpose: 'registration'
    }, {
      timeout: 30000
    })
    success.value = 'تم إعادة إرسال رابط التأكيد'
  } catch (e) {
    error.value = 'فشل إعادة إرسال الرابط'
  } finally {
    resendLoading.value = false
  }
}
</script>

<style scoped>



/* Modern UI Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-12 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm text-sm; }

/* Transitions & Animations */
.animate-fadeIn { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }

/* Utilities */
button:disabled { cursor: not-allowed; }
</style>