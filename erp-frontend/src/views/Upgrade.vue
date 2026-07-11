<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <div class="max-w-5xl mx-auto space-y-10">
      
      <!-- Hero Header Section -->
      <header class="text-center space-y-4 max-w-2xl mx-auto mt-8">
        <div class="w-20 h-20 bg-blue-600 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-blue-200 text-white mx-auto mb-6 transform hover:rotate-12 transition-transform duration-500">
          <i class="fas fa-rocket text-3xl"></i>
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-slate-900 leading-tight tracking-tight">الترقية مطلوبة لمتابعة الاستخدام</h1>
        <p class="text-slate-400 font-bold text-base leading-relaxed">عذراً، لقد انتهت الفترة التجريبية أو الاشتراك الحالي لمنشأتك. يرجى اختيار الخطة المناسبة لاستكمال العمل بكافة الصلاحيات.</p>
      </header>

      <!-- Reason Alert (Conditional) -->
      <transition name="slide-fade">
        <div v-if="reason" class="bg-amber-50 border border-amber-100 p-5 rounded-[1.5rem] flex items-center gap-4 max-w-3xl mx-auto shadow-sm shadow-amber-50">
          <div class="w-10 h-10 bg-amber-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-md">
            <i class="fas fa-circle-info"></i>
          </div>
          <p class="text-sm font-black text-amber-900 leading-relaxed">{{ reason }}</p>
        </div>
      </transition>

      <!-- Pricing Plans Grid -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-8 py-4">
        <div v-for="p in plans" :key="p.code" class="pricing-card group">
          <div class="absolute top-0 left-0 w-24 h-24 bg-blue-500/5 rounded-full -translate-x-12 -translate-y-12 transition-transform group-hover:scale-150"></div>
          
          <div class="relative z-10 flex-grow">
            <div class="flex items-center justify-between mb-6">
              <span class="px-4 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-blue-100">باقة الأعمال</span>
              <div class="w-10 h-10 bg-slate-50 text-slate-300 rounded-xl flex items-center justify-center transition-colors group-hover:bg-blue-600 group-hover:text-white">
                <i class="fas fa-gem"></i>
              </div>
            </div>

            <h3 class="text-2xl font-black text-slate-800 mb-1 capitalize">{{ p.name }}</h3>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-8">دورة الفوترة: كل {{ p.billing_cycle_days }} يوم</p>

            <div class="flex items-baseline gap-2 mb-8">
              <span class="text-5xl font-black text-slate-900 tracking-tighter">{{ p.price }}</span>
              <span class="text-sm font-black text-slate-400 uppercase tracking-widest">{{ p.currency }}</span>
            </div>

            <ul class="space-y-4 mb-10">
               <li v-for="feature in ['كافة ميزات النظام الأساسية', 'دعم فني متكامل 24/7', 'تحديثات دورية مجانية']" :key="feature" class="flex items-center gap-3 text-sm font-bold text-slate-500">
                  <i class="fas fa-check-circle text-emerald-500 text-xs"></i>
                  {{ feature }}
               </li>
            </ul>
          </div>

          <button @click="selectPlan(p)" class="w-full py-4 bg-blue-600 text-white rounded-[1.2rem] font-black text-sm shadow-xl shadow-blue-500/20 hover:bg-blue-700 active:scale-95 transition-all flex items-center justify-center gap-3 group/btn relative z-10">
            <span>اشترك الآن</span>
            <i class="fas fa-arrow-left-long text-[10px] group-hover/btn:-translate-x-2 transition-transform"></i>
          </button>
        </div>
      </section>

      <!-- Footer Info -->
      <footer class="text-center py-6 border-t border-slate-100">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-[0.2em] leading-relaxed italic max-w-lg mx-auto">
          الأسعار الموضحة قد تخضع للتغيير. <br> جميع بوابات الدفع الإلكترونية مؤمنة بالكامل وسيتم تفعيل الدفع المباشر قريباً.
        </p>
      </footer>
    </div>

    <!-- Payment Selection Modal -->
    <transition name="modal">
      <div v-if="showPaymentModal" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn border border-white">
          <div class="px-8 py-7 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-xl font-black text-slate-800 tracking-tight leading-none">اختر وسيلة الدفع</h3>
            <button @click="closePaymentModal" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>

          <div class="p-8 space-y-6">
            <!-- Selected Plan Summary -->
            <div class="bg-slate-900 p-6 rounded-[1.5rem] text-white shadow-xl relative overflow-hidden">
               <div class="absolute right-0 bottom-0 w-20 h-20 bg-white/5 rounded-full translate-x-8 translate-y-8"></div>
               <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">الخطة المختارة:</p>
               <h4 class="text-lg font-black leading-none">{{ selectedPlan?.name }}</h4>
               <div class="mt-4 text-2xl font-black font-mono tracking-tighter text-blue-400">{{ selectedPlan?.price }} {{ selectedPlan?.currency }}</div>
            </div>

            <!-- Payment Options List -->
            <div class="space-y-3">
              <button @click="selectPaymentMethod('instapay')" class="payment-option group border-emerald-100 hover:border-emerald-500 hover:bg-emerald-50/30">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-mobile-screen-button"></i>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-black text-slate-800 leading-none">Instapay</p>
                    <p class="text-[10px] text-slate-400 font-bold mt-1.5 uppercase">دفع سريع عبر التطبيق</p>
                  </div>
                </div>
                <i class="fas fa-chevron-left text-slate-200 group-hover:text-emerald-500 transition-colors"></i>
              </button>

              <button @click="selectPaymentMethod('vodafonecash')" class="payment-option group border-rose-100 hover:border-rose-500 hover:bg-rose-50/30">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:bg-rose-600 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-wallet"></i>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-black text-slate-800 leading-none">Vodafone Cash</p>
                    <p class="text-[10px] text-slate-400 font-bold mt-1.5 uppercase">تحويل فوري عبر المحفظة</p>
                  </div>
                </div>
                <i class="fas fa-chevron-left text-slate-200 group-hover:text-rose-500 transition-colors"></i>
              </button>
            </div>

            <!-- Notification Badge -->
            <div class="bg-blue-50 border border-blue-100 p-4 rounded-2xl flex items-center gap-3">
               <i class="fab fa-whatsapp text-emerald-500 text-lg"></i>
               <p class="text-[10px] font-black text-blue-700 leading-relaxed italic">سيتم توجيهك إلى المحادثة المباشرة عبر WhatsApp لإتمام التحقق وتفعيل الحساب.</p>
            </div>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/config/axios'

// --- State ---
const route = useRoute()
const plans = ref([])
const loadingPlans = ref(false)
const reason = ref('')
const showPaymentModal = ref(false)
const selectedPlan = ref(null)

// Fallback plans if API fails
const fallbackPlans = [
  {
    id: 1,
    code: 'starter',
    name: 'Basic',
    price: '99',
    currency: 'EGP',
    billing_cycle_days: 30,
    is_active: true
  },
  {
    id: 2,
    code: 'professional',
    name: 'Professional',
    price: '299',
    currency: 'EGP',
    billing_cycle_days: 30,
    is_active: true
  },
  {
    id: 3,
    code: 'enterprise',
    name: 'Enterprise',
    price: '599',
    currency: 'EGP',
    billing_cycle_days: 30,
    is_active: true
  }
]

function getQuery(name) {
  try { return route.query[name] || '' } catch { return '' }
}

// Load plans from API with fallback to hardcoded
async function loadPlans() {
  loadingPlans.value = true
  reason.value = decodeURIComponent(getQuery('reason') || '')
  
  try {
    if (import.meta.env.DEV) {
      console.log('[Upgrade Page] Loading plans from API...')
    }
    
    // Try to fetch from /plans endpoint
    const response = await apiClient.get('/plans', {
      meta: { skipLoader: true, suppress402: true }
    })
    
    if (response.data?.status === 'success' && Array.isArray(response.data.data)) {
      plans.value = response.data.data
      if (import.meta.env.DEV) {
        console.log('[Upgrade Page] Loaded plans from API:', plans.value.length)
      }
      return
    }
  } catch (error) {
    // Silent catch: 402 expected when subscription expired
    // Also catches network errors gracefully
    if (import.meta.env.DEV) {
      console.log('[Upgrade Page] API call failed, using fallback plans:', error.message)
    }
  }
  
  // Fallback: use hardcoded plans
  plans.value = fallbackPlans
  if (import.meta.env.DEV) {
    console.log('[Upgrade Page] Using fallback plans')
  }
}

function selectPlan(p) {
  selectedPlan.value = p
  showPaymentModal.value = true
}

function closePaymentModal() {
  showPaymentModal.value = false
  selectedPlan.value = null
}

// Logic: Payment Redirect (STRICTLY PRESERVED)
function selectPaymentMethod(method) {
  const whatsappNumber = '+201062024249'
  const planName = selectedPlan.value?.name || ''
  const planPrice = selectedPlan.value?.price || ''
  const currency = selectedPlan.value?.currency || ''
  
  let message = `مرحباً، أرغب في الاشتراك في خطة ${planName} بسعر ${planPrice} ${currency} عبر ${method === 'instapay' ? 'Instapay' : 'Vodafone Cash'}`
  
  const whatsappUrl = `https://wa.me/${whatsappNumber.replace('+', '')}?text=${encodeURIComponent(message)}`
  window.open(whatsappUrl, '_blank')
  
  if (typeof window !== 'undefined' && typeof window.showToast === 'function') {
    window.showToast('جاري تحويلك إلى WhatsApp لإتمام عملية الدفع', 'info')
  }
  
  closePaymentModal()
}

onMounted(loadPlans)
</script>

<style scoped>



/* Pricing Card Styling */
.pricing-card { @apply relative bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 transition-all duration-500 hover:shadow-2xl hover:shadow-blue-100 hover:-translate-y-2 flex flex-col; }

/* Payment Option Styling */
.payment-option { @apply w-full p-5 bg-white border-2 rounded-[1.5rem] transition-all flex items-center justify-between active:scale-[0.98]; }

/* Modal & Transitions */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }
</style>