<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 lg:p-8 text-slate-700 animate-fadeIn" dir="rtl">
    
    <!-- 404 Card Container -->
    <div class="w-full max-w-lg bg-white rounded-[2.5rem] shadow-2xl shadow-blue-100/50 overflow-hidden border border-white relative text-center">
      
      <!-- Top Decorative Bar -->
      <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-blue-500 to-blue-600"></div>

      <div class="p-10 md:p-14">
        <!-- Error Illustration/Icon Section -->
        <div class="relative mb-10">
          <div class="text-8xl font-black text-slate-100 absolute inset-0 flex items-center justify-center select-none z-0">
            404
          </div>
          <div class="w-24 h-24 bg-blue-50 text-blue-600 rounded-[2rem] flex items-center justify-center mx-auto shadow-sm relative z-10 border border-blue-100/50">
            <i class="fas fa-map-signs text-4xl"></i>
          </div>
          <!-- Glow Effect -->
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-blue-50/50 rounded-full blur-2xl z-0"></div>
        </div>

        <!-- Error Text -->
        <div class="space-y-3 mb-10 relative z-10">
          <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">الصفحة غير موجودة</h1>
          <div class="h-1 w-12 bg-blue-100 mx-auto rounded-full"></div>
          <p class="text-slate-400 font-bold text-sm leading-relaxed max-w-xs mx-auto">
            عذراً، يبدو أن الرابط الذي تحاول الوصول إليه غير صحيح أو تم نقله لمكان آخر.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-3 relative z-10">
          <router-link 
            to="/cashier-dashboard" 
            class="h-12 w-full bg-blue-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-[0.98] transition-all flex items-center justify-center gap-2"
          >
            <i class="fas fa-home text-xs"></i>
            العودة للوحة التحكم
          </router-link>
          
          <button 
            @click="$router.back()" 
            class="h-12 w-full bg-slate-50 text-slate-500 rounded-2xl font-black text-xs uppercase tracking-widest border border-slate-100 hover:bg-white hover:border-slate-200 transition-all active:scale-[0.98] flex items-center justify-center gap-2"
          >
            <i class="fas fa-arrow-right text-xs"></i>
            الرجوع للصفحة السابقة
          </button>
        </div>

        <!-- Footer Note -->
        <div class="mt-10 pt-8 border-t border-slate-50 text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em]">
          System Status: Operational
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
</script>

<style scoped>



/* Animations */
.animate-fadeIn { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

/* Utility Styles */
.shadow-2xl {
  box-shadow: 0 25px 50px -12px rgba(30, 58, 138, 0.15);
}
</style>