<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 lg:p-8 text-right animate-fadeIn" dir="rtl">
    
    <!-- Login Card Container -->
    <div class="w-full max-w-5xl bg-white rounded-[2.5rem] shadow-2xl shadow-blue-100/50 flex flex-col lg:flex-row overflow-hidden border border-white relative">
      
      <!-- Right Panel: Login Form -->
      <div class="w-full lg:w-1/2 p-8 md:p-16 flex flex-col justify-center relative z-10">
        <div class="mb-10">
          <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200 text-white mb-6">
            <i class="fas fa-shield-halved text-2xl"></i>
          </div>
          <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">مرحباً بعودتك!</h1>
          <p class="text-slate-400 mt-3 font-bold text-sm uppercase tracking-widest leading-relaxed">يرجى إدخال بيانات الاعتماد للوصول إلى لوحة التحكم</p>
        </div>

        <!-- [PRESERVED] novalidate on form -->
        <form @submit.prevent="handleLogin" @keydown.enter="handleKeyDown" class="space-y-6" novalidate>
          
          <!-- Username/Email Field -->
          <div class="space-y-2 group">
            <label for="username" class="modal-label">اسم المستخدم أو البريد <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                v-model="username"
                type="text"
                id="username"
                class="form-input-modern font-black"
                required
                autocomplete="username"
                placeholder="مثال: admin_101"
              />
              <i class="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <!-- Password Field -->
          <div class="space-y-2 group">
            <label for="password" class="modal-label">كلمة المرور <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                v-model="password"
                :type="passwordVisible ? 'text' : 'password'"
                id="password"
                class="form-input-modern pl-12 pr-11 font-black font-mono tracking-widest"
                required
                autocomplete="current-password"
                placeholder="••••••••"
              />
              <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
              
              <!-- Toggle Visibility -->
              <button
                type="button"
                @click="togglePasswordVisibility"
                class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 hover:text-blue-600 transition-colors p-1"
                title="إظهار/إخفاء"
              >
                <i :class="[passwordVisible ? 'fa fa-eye-slash' : 'fa fa-eye']"></i>
              </button>
            </div>
          </div>

          <!-- Error Alert -->
          <transition name="slide-fade">
            <div v-if="error" class="bg-rose-50 border border-rose-100 p-4 rounded-2xl flex items-center gap-3 animate-fadeIn">
              <div class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center shrink-0 shadow-sm"><i class="fas fa-exclamation-triangle text-xs"></i></div>
              <span class="text-xs font-black text-rose-700 leading-relaxed">{{ error }}</span>
            </div>
          </transition>

          <!-- Extra Options -->
          <div class="flex items-center justify-between py-2">
            <label class="flex items-center gap-3 cursor-pointer group select-none">
              <input type="checkbox" v-model="rememberMe" class="w-5 h-5 rounded-lg border-slate-200 text-blue-600 focus:ring-blue-50 transition-all cursor-pointer" />
              <span class="text-xs font-black text-slate-500 group-hover:text-slate-800 transition-colors">تذكر دخولي</span>
            </label>
            <router-link to="/forgot-password" class="text-xs font-black text-blue-600 hover:text-blue-800 hover:underline transition-colors decoration-2 underline-offset-4 uppercase tracking-tighter">نسيت كلمة المرور؟</router-link>
          </div>

          <!-- Login Button -->
          <div class="pt-4">
            <button
              type="submit"
              :disabled="loading"
              class="w-full h-14 bg-blue-600 text-white rounded-[1.2rem] font-black text-base shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-50"
            >
              <template v-if="!loading">
                <span>دخول آمن</span>
                <i class="fas fa-arrow-left-long text-xs"></i>
              </template>
              <BaseSpinner v-else :size="20" color="#fff" margin="0" />
            </button>
          </div>

          <!-- Registration Link -->
          <div class="text-center pt-8 border-t border-slate-50">
            <p class="text-xs font-bold text-slate-400">ليس لديك حساب في النظام؟</p>
            <router-link to="/register" class="inline-block mt-3 text-sm font-black text-slate-900 hover:text-blue-600 transition-colors decoration-blue-200 underline underline-offset-8">إنشاء حساب تجاري جديد</router-link>
          </div>
        </form>
      </div>

      <!-- Left Panel: Brand Experience -->
      <div class="hidden lg:block lg:w-1/2 relative bg-slate-900">
        <div class="absolute inset-0 z-10 bg-gradient-to-tr from-blue-900/90 to-blue-600/40"></div>
        <div 
          class="absolute inset-0 bg-cover bg-center transition-transform duration-[10s] hover:scale-110"
          style="background-image: url('https://images.unsplash.com/photo-1554774853-719586f82d77?ixlib=rb-4.0.3&auto=format&fit=crop&w=1032&q=80');"
        ></div>
        
        <div class="relative z-20 h-full flex flex-col justify-end p-16 text-white text-right">
          <div class="bg-white/10 backdrop-blur-xl border border-white/10 p-10 rounded-[3rem] shadow-2xl">
            <h2 class="text-4xl font-black leading-tight tracking-tight mb-4">نظام الإدارة المتكامل <br><span class="text-blue-400 underline decoration-blue-400/30 underline-offset-[12px]">للمؤسسات</span></h2>
            <p class="text-white/60 text-lg font-medium leading-relaxed max-w-md">الحل الأمثل لإدارة جميع جوانب عملك، الفروع، المبيعات والتقارير المالية بكفاءة وسهولة فائقة.</p>
            
            <div class="mt-8 flex gap-4">
              <div class="flex -space-x-3 space-x-reverse">
                <div v-for="i in 3" :key="i" class="w-10 h-10 rounded-full border-2 border-slate-900 bg-slate-800 flex items-center justify-center text-[10px] font-black">USER</div>
              </div>
              <div class="text-[10px] font-black uppercase tracking-[0.2em] flex items-center gap-2">
                 <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                 أكثر من +500 منشأة نشطة حالياً
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/composables/useToast';
import { useLoader } from '@/composables/useLoader';
import { useAuthStore } from '@/stores/auth';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// --- Logic ---
const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();
const { showLoader, hideLoader } = useLoader();

// Component state (Preserved)
const username = ref('');
const password = ref('');
const passwordVisible = ref(false);
const loading = ref(false);
const error = ref('');
const rememberMe = ref(false);

// [PRESERVED] Auto-focus username field on mount
onMounted(() => {
  const usernameField = document.getElementById('username');
  if (usernameField) {
    usernameField.focus();
  }
});

const togglePasswordVisibility = () => {
  passwordVisible.value = !passwordVisible.value;
};

// Logic: Handle Login (Preserved)
const handleLogin = async () => {
  error.value = '';
  
  // [PRESERVED] Validate inputs before attempting login
  if (!username.value || !password.value) {
    showToast('يرجى تعبئة جميع الحقول', 'warning');
    return;
  }

  loading.value = true;
  error.value = '';
  showLoader();

  try {
    const result = await authStore.login(username.value, password.value);
    
    if (result?.success) {
      showToast('تم تسجيل الدخول بنجاح', 'success');
      
      // [PRESERVED] Get the return URL or use default
      const redirectPath = authStore.returnUrl || '/cashier-dashboard';
      
      // [PRESERVED] Reset return URL before navigation
      authStore.returnUrl = null;
      
      // [PRESERVED] Navigate with fallback on error
      router.push(redirectPath).catch(err => {
        console.error('Navigation error:', err);
        router.push('/cashier-dashboard');
      });
    } else {
      // [PRESERVED] Handle login failure with server message fallback
      const errorMessage = result?.message || 'اسم المستخدم أو كلمة المرور غير صحيحة';
      error.value = errorMessage;
      showToast(errorMessage, 'error');
    }
  } catch (err) {
    // [PRESERVED] console.error for debugging + extract server error message
    console.error('Login error:', err);
    const errorMessage = err.response?.data?.message || 'حدث خطأ أثناء تسجيل الدخول';
    error.value = errorMessage;
    showToast(errorMessage, 'error');
  } finally {
    loading.value = false;
    hideLoader();
  }
};

// [PRESERVED] Handle Enter key press
const handleKeyDown = (e) => {
  if (e.key === 'Enter') {
    handleLogin();
  }
};
</script>

<style scoped>

/* Modern Form Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-12 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm text-sm; }

/* Transitions & Animations */
.animate-fadeIn { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }

/* Utility Styles */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>