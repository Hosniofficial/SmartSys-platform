<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 lg:p-8 text-slate-700 animate-fadeIn" dir="rtl">
    
    <!-- Error Card Container -->
    <div class="w-full max-w-lg bg-white rounded-[2.5rem] shadow-2xl shadow-blue-100/50 overflow-hidden border border-white relative text-center">
      
      <!-- Top Decorative Bar -->
      <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-rose-500 to-rose-600"></div>

      <div class="p-10 md:p-14">
        <!-- Error Icon Section -->
        <div class="relative mb-10">
          <div class="w-24 h-24 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto shadow-sm relative z-10 border border-rose-100/50">
            <i class="fas fa-shield-halved text-4xl"></i>
          </div>
          <!-- Decorative Background Circles -->
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-rose-50/50 rounded-full blur-2xl z-0"></div>
        </div>

        <!-- Error Text -->
        <div class="space-y-3 mb-10">
          <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">ممنوع الوصول</h1>
          <div class="h-1 w-12 bg-rose-100 mx-auto rounded-full"></div>
          <p class="text-slate-500 font-bold text-sm leading-relaxed max-w-xs mx-auto">
            {{ message }}
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-3">
          <router-link 
            to="/cashier-dashboard" 
            class="h-12 w-full bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-[0.98] transition-all flex items-center justify-center gap-2"
          >
            <i class="fas fa-home"></i>
            العودة للوحة التحكم
          </router-link>
          
          <button 
            @click="goBack" 
            class="h-12 w-full bg-slate-50 text-slate-500 rounded-2xl font-black text-xs uppercase tracking-widest border border-slate-100 hover:bg-white hover:border-slate-200 transition-all active:scale-[0.98] flex items-center justify-center gap-2"
          >
            <i class="fas fa-arrow-right"></i>
            رجوع للخلف
          </button>
        </div>

        <!-- Footer Note -->
        <div class="mt-10 pt-8 border-t border-slate-50 text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em]">
          Error Code: 403 Forbidden
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { useRouter, useRoute } from 'vue-router'
import { computed } from 'vue'

// --- Logic ---
const router = useRouter()
const route = useRoute()

// Logic: Reason Computation (STRICTLY PRESERVED)
const reason = computed(() => (route.query.reason || '').toString())

// Logic: Message Generator (STRICTLY PRESERVED)
const message = computed(() => {
  if (reason.value === 'super_admin') {
    return 'عذراً، هذه الصفحة محمية ومتاحة فقط للمشرف العام على النظام.'
  }
  if (reason.value === 'admin') {
    return 'تحتاج إلى صلاحيات مدير النظام للوصول إلى محتويات هذه الصفحة.'
  }
  return 'ليست لديك الصلاحيات الكافية للوصول إلى هذه الصفحة حالياً.'
})

// Logic: Navigation (STRICTLY PRESERVED)
const goBack = () => {
  if (window.history.length > 1) router.back()
  else router.push('/cashier-dashboard')
}
</script>

<style scoped>



/* Animations */
.animate-fadeIn { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>