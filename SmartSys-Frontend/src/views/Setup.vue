<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-12 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <div class="max-w-4xl mx-auto space-y-10">
      
      <!-- Header Section -->
      <header class="text-center space-y-3 mb-12">
        <div class="w-20 h-20 bg-blue-600 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-blue-200 text-white mx-auto mb-6 transform hover:scale-105 transition-transform duration-500">
          <i class="fas fa-rocket text-3xl"></i>
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-slate-900 leading-tight tracking-tight">مرحباً بك في إعداد النظام</h1>
        <p class="text-slate-400 font-bold text-base leading-relaxed">دعنا نقم بتهيئة بيئة العمل الخاصة بك في دقائق معدودة</p>
      </header>

      <!-- Multi-step Progress Bar -->
      <nav class="relative flex justify-between items-center max-w-2xl mx-auto mb-16">
        <!-- Progress Line Background -->
        <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-100 -translate-y-1/2 z-0 rounded-full"></div>
        <!-- Active Progress Line -->
        <div 
          class="absolute top-1/2 right-0 h-1 bg-blue-600 -translate-y-1/2 z-0 transition-all duration-500 rounded-full"
          :style="{ width: (currentStep / (steps.length - 1)) * 100 + '%' }"
        ></div>

        <div
          v-for="(step, index) in steps"
          :key="index"
          class="relative z-10 flex flex-col items-center group"
        >
          <div
            :class="[
              currentStep === index ? 'bg-blue-600 text-white shadow-xl shadow-blue-200 scale-110' : 
              currentStep > index ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-100' : 'bg-white text-slate-300 border-2 border-slate-100'
            ]"
            class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-sm transition-all duration-500"
          >
            <i v-if="currentStep > index" class="fas fa-check"></i>
            <span v-else>{{ index + 1 }}</span>
          </div>
          <span :class="[currentStep === index ? 'text-blue-600 font-black' : 'text-slate-400 font-bold']" class="absolute -bottom-8 whitespace-nowrap text-[10px] uppercase tracking-widest transition-colors duration-500">
            {{ step.label }}
          </span>
        </div>
      </nav>

      <!-- Main Setup Content Card -->
      <div class="bg-white rounded-[3rem] shadow-2xl shadow-blue-100/50 border border-white relative overflow-hidden">
        
        <!-- Loading State Overlay -->
        <Transition name="fade">
          <div v-if="loading" class="absolute inset-0 bg-white/90 backdrop-blur-sm z-50 flex flex-col items-center justify-center p-12">
            <BaseSpinner :size="48" color="#2563eb" />
            <p class="mt-6 text-xs font-black text-slate-400 uppercase tracking-[0.2em] animate-pulse">جاري جلب بيانات التهيئة...</p>
          </div>
        </Transition>

        <div class="p-8 md:p-12 min-h-[450px]">
          
          <!-- Step 1: Company Information -->
          <div v-show="currentStep === 0" class="setup-step animate-fadeIn">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-building"></i></div>
              <h2 class="text-xl font-black text-slate-800">بيانات النشاط التجاري</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="modal-label">اسم المنشأة / الشركة <span class="text-rose-500">*</span></label>
                <input v-model="formData.company.name" type="text" class="form-input-modern font-black" placeholder="اسم المنشأة أو الشركة" required />
              </div>

              <div class="space-y-2">
                <label class="modal-label">البريد الإلكتروني</label>
                <input v-model="formData.company.email" type="email" class="form-input-modern font-bold" placeholder="example@company.com" />
              </div>

              <div class="space-y-2">
                <label class="modal-label">رقم هاتف التواصل</label>
                <input v-model="formData.company.phone" type="tel" dir="ltr" class="form-input-modern font-mono font-bold text-left" placeholder="أدخل رقم الهاتف" />
              </div>

              <div class="space-y-2">
                <label class="modal-label">العملة الافتراضية</label>
                <select v-model="formData.company.currency" @change="updateCurrencyData" class="form-select-modern font-black">
                  <option v-for="curr in getAvailableCurrencies('ar')" :key="curr.code" :value="curr.code">{{ curr.name }} ({{ curr.code }})</option>
                </select>
              </div>

              <div class="md:col-span-2 space-y-2">
                <label class="modal-label">العنوان والمقر الرئيسي</label>
                <input v-model="formData.company.address" type="text" class="form-input-modern font-bold" placeholder="عنوان المنشأة" />
              </div>
            </div>
          </div>

          <!-- Step 2: Tax Settings -->
          <div v-show="currentStep === 1" class="setup-step animate-fadeIn">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-percentage"></i></div>
              <h2 class="text-xl font-black text-slate-800">إعدادات الضرائب والتحصيل</h2>
            </div>

            <div class="space-y-8">
              <label class="flex items-center justify-between p-6 rounded-[1.5rem] bg-slate-50 border-2 border-transparent transition-all cursor-pointer hover:border-blue-200 group has-[:checked]:bg-white has-[:checked]:border-blue-600 has-[:checked]:shadow-xl has-[:checked]:shadow-blue-50">
                <div class="flex items-center gap-4">
                   <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-slate-300 group-hover:text-blue-600 transition-colors shadow-sm"><i class="fas fa-receipt"></i></div>
                   <div>
                     <p class="text-sm font-black text-slate-800 leading-none">تفعيل نظام الضريبة</p>
                     <p class="text-[10px] text-slate-400 font-bold mt-1.5 uppercase">هل المنشأة مسجلة ضريبيًا؟</p>
                   </div>
                </div>
                <input v-model="formData.tax.tax_enabled" type="checkbox" class="w-6 h-6 rounded-lg border-slate-300 text-blue-600 focus:ring-0 cursor-pointer" />
              </label>

              <transition name="slide-fade">
                <div v-if="formData.tax.tax_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 rounded-[2rem] bg-slate-50 border border-slate-100 shadow-inner">
                  <div class="space-y-2">
                    <label class="modal-label">مسمى الضريبة</label>
                    <input v-model="formData.tax.tax_name" type="text" class="form-input-modern font-black" placeholder="مثال: ضريبة القيمة المضافة" />
                  </div>
                  <div class="space-y-2">
                    <label class="modal-label">نسبة الضريبة (%)</label>
                    <input v-model.number="formData.tax.tax_rate" type="number" step="0.01" class="form-input-modern font-black text-lg" placeholder="مثال: 15" />
                  </div>
                  <div class="md:col-span-2 space-y-2">
                    <label class="modal-label">الرقم الضريبي (VAT Number)</label>
                    <input v-model="formData.tax.tax_number" type="text" class="form-input-modern font-black font-mono tracking-widest" placeholder="3000XXXXX" />
                  </div>
                </div>
              </transition>
            </div>
          </div>

          <!-- Step 3: Branch / Warehouse -->
          <div v-show="currentStep === 2" class="setup-step animate-fadeIn">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-warehouse"></i></div>
              <h2 class="text-xl font-black text-slate-800">إعداد الفرع والمستودع الأول</h2>
            </div>

            <div class="space-y-8">
              <div v-if="branches.length > 0" class="space-y-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">الفروع الحالية</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div v-for="branch in branches" :key="branch.id" class="flex items-center gap-4 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm">
                    <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400"><i class="fas fa-store"></i></div>
                    <div class="flex-grow">
                       <p class="text-xs font-black text-slate-800 leading-none">{{ branch.name }}</p>
                       <p class="text-[9px] text-slate-400 font-bold mt-1 tracking-tight">{{ branch.location || 'بدون موقع' }}</p>
                    </div>
                    <span :class="(branch.active || branch.is_active) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2.5 py-1 rounded-lg text-[8px] font-black uppercase tracking-tighter">
                      {{ (branch.active || branch.is_active) ? 'نشط' : 'معطل' }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="p-8 rounded-[2.5rem] bg-slate-50 border-2 border-dashed border-slate-200">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6"><i class="fas fa-plus-circle ml-2 text-blue-500"></i> إنشاء مستودع / فرع جديد</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <label class="modal-label">اسم الفرع</label>
                    <input v-model="formData.branch.name" type="text" class="form-input-modern font-black" placeholder="مثال: الفرع الرئيسي" />
                  </div>
                  <div class="space-y-2">
                    <label class="modal-label">الموقع الجغرافي</label>
                    <input v-model="formData.branch.location" type="text" class="form-input-modern font-bold" placeholder="عنوان الفرع" />
                  </div>
                  <div class="space-y-2">
                    <label class="modal-label">هاتف الفرع (اختياري)</label>
                    <input v-model="formData.branch.phone" type="tel" class="form-input-modern font-bold" placeholder="أدخل رقم الهاتف" />
                  </div>
                  <div class="space-y-2">
                    <label class="modal-label">بريد الفرع (اختياري)</label>
                    <input v-model="formData.branch.email" type="email" class="form-input-modern font-bold" placeholder="branch@company.com" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 4: Terminal Setup (Optional) -->
          <div v-show="currentStep === 3" class="setup-step animate-fadeIn">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 bg-violet-50 text-violet-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-cash-register"></i></div>
              <div>
                <h2 class="text-xl font-black text-slate-800">إعداد أجهزة نقطة البيع</h2>
                <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-widest">اختياري — يمكن تخطيه والإضافة لاحقاً من الإعدادات</p>
              </div>
            </div>

            <!-- Existing terminals -->
            <div v-if="existingTerminals.length > 0" class="space-y-3 mb-6">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">الأجهزة المسجّلة حالياً</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div v-for="t in existingTerminals" :key="t.id" class="flex items-center gap-4 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm">
                  <div class="w-10 h-10 bg-violet-50 rounded-xl flex items-center justify-center text-violet-500"><i class="fas fa-desktop"></i></div>
                  <div class="flex-grow">
                    <p class="text-xs font-black text-slate-800 leading-none">{{ t.name }}</p>
                    <p class="text-[9px] text-slate-400 font-bold mt-1 tracking-tight font-mono">{{ t.code }}</p>
                  </div>
                  <span :class="t.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2.5 py-1 rounded-lg text-[8px] font-black uppercase tracking-tighter">
                    {{ t.status === 'active' ? 'نشط' : 'معطل' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Add terminal form -->
            <div class="p-8 rounded-[2.5rem] bg-slate-50 border-2 border-dashed border-slate-200">
              <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6"><i class="fas fa-plus-circle ml-2 text-violet-500"></i> تسجيل جهاز جديد</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="modal-label">كود الجهاز <span class="text-rose-500">*</span></label>
                  <input v-model="formData.terminal.code" type="text" class="form-input-modern font-black font-mono tracking-widest" placeholder="POS-01" />
                </div>
                <div class="space-y-2">
                  <label class="modal-label">اسم الجهاز <span class="text-rose-500">*</span></label>
                  <input v-model="formData.terminal.name" type="text" class="form-input-modern font-black" placeholder="كاشير رئيسي" />
                </div>
                <div class="space-y-2">
                  <label class="modal-label">الفرع المرتبط</label>
                  <select v-model="formData.terminal.branch_id" class="form-select-modern font-black">
                    <option value="">-- اختر الفرع --</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label class="modal-label">الموقع / الوصف (اختياري)</label>
                  <input v-model="formData.terminal.location" type="text" class="form-input-modern font-bold" placeholder="مثال: كاونتر الاستقبال" />
                </div>
              </div>
            </div>
          </div>

          <!-- Step 5: Invoice Settings -->
          <div v-show="currentStep === 4" class="setup-step animate-fadeIn">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-sm"><i class="fas fa-file-invoice-dollar"></i></div>
              <h2 class="text-xl font-black text-slate-800">إعدادات الفواتير والطباعة</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-6">
                <div class="space-y-2">
                  <label class="modal-label">بادئة رقم الفاتورة (Prefix)</label>
                  <input v-model="formData.invoice.prefix" type="text" class="form-input-modern font-black font-mono tracking-widest" placeholder="مثال: INV- أو TRX" />
                </div>
                <div class="space-y-2">
                  <label class="modal-label">التسلسل التالي للعد</label>
                  <input v-model.number="formData.invoice.next_number" type="number" class="form-input-modern font-black text-lg" placeholder="1001" />
                </div>
              </div>

              <div class="space-y-6">
                <div class="space-y-2">
                  <label class="modal-label">تذييل الفاتورة الافتراضي</label>
                  <textarea v-model="formData.invoice.footer_text" class="w-full rounded-[1.5rem] border border-slate-200 p-5 text-sm font-bold bg-slate-50 focus:bg-white outline-none focus:ring-4 focus:ring-blue-50 transition-all" rows="3" placeholder="شكراً لتعاملكم معنا..."></textarea>
                </div>
                <label class="flex items-center gap-3 cursor-pointer group p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-all">
                  <input v-model="formData.invoice.show_tax_in_price" type="checkbox" class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-0 cursor-pointer" />
                  <span class="text-xs font-black text-slate-600 uppercase tracking-tight">تضمين الضريبة في سعر المنتج</span>
                </label>
              </div>
            </div>
          </div>

        </div>

        <!-- Sticky Navigation Footer -->
        <div class="px-8 md:px-12 py-8 bg-slate-50/80 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-6 shrink-0">
          <div class="flex items-center gap-3 w-full md:w-auto order-2 md:order-1">
             <button @click="skipSetup" :disabled="saving" class="px-6 py-3 rounded-2xl text-xs font-black text-slate-400 hover:text-slate-800 transition-all uppercase tracking-widest">تخطي الإعداد الآن</button>
          </div>

          <div class="flex items-center gap-3 w-full md:w-auto order-1 md:order-2">
            <button v-if="currentStep > 0" @click="previousStep" class="flex-1 md:flex-none px-8 py-3 rounded-2xl border-2 border-slate-200 font-black text-slate-400 hover:bg-white transition-all text-xs uppercase tracking-widest active:scale-95">
              <i class="fas fa-arrow-right ml-2"></i> السابق
            </button>

            <button v-if="currentStep < steps.length - 1" @click="nextStep" :disabled="saving" class="flex-1 md:flex-none px-10 py-3 bg-blue-600 text-white rounded-2xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all uppercase tracking-widest flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
              <BaseSpinner v-if="saving" :size="14" color="#ffffff" :margin="0" />
              <template v-else>التالي <i class="fas fa-arrow-left"></i></template>
            </button>

            <button v-if="currentStep === steps.length - 1" @click="saveSetup" :disabled="saving" class="flex-1 md:flex-none px-12 py-3 bg-emerald-600 text-white rounded-2xl font-black text-xs shadow-xl shadow-emerald-100 hover:bg-emerald-700 active:scale-95 transition-all uppercase tracking-widest flex items-center justify-center gap-3">
              <BaseSpinner v-if="saving" :size="16" color="#fff" />
              <i v-else class="fas fa-check-circle"></i>
              إنهاء وحفظ الإعدادات
            </button>
          </div>
        </div>
      </div>

      <footer class="text-center pt-8">
        <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em]">System Configuration Wizard</p>
      </footer>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useSettingsStore } from '@/stores/settings/settingsStore';
import { useSetupStore } from '@/stores/setup/setupStore';
import AlertService from '@/services/AlertService';
import { getAvailableCurrencies, getCurrencySymbol } from '@/config/currencies';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

export default {
  name: 'Setup',
  components: { BaseSpinner },
  setup() {
    const authStore = useAuthStore();
    const branchStore = useBranchStore();
    const settingsStore = useSettingsStore();
    const setupStore = useSetupStore();
    return { authStore, branchStore, settingsStore, setupStore };
  },
  data() {
    return {
      loading: true,
      saving: false,
      currentStep: 0,
      getAvailableCurrencies,
      steps: [
        { label: 'معلومات الشركة' },
        { label: 'إعدادات الضريبة' },
        { label: 'الفروع' },
        { label: 'أجهزة POS' },
        { label: 'الفواتير' }
      ],
      existingTerminals: [],
      formData: {
        company: {
          name: '',
          address: '',
          phone: '',
          email: '',
          currency: 'EGP',
          currency_code: 'EGP',
          currency_symbol: 'جنيه'
        },
        tax: {
          tax_enabled: false,
          tax_name: 'ضريبة القيمة المضافة',
          tax_rate: 14,
          tax_number: ''
        },
        branch: {
          name: '',
          location: '',
          phone: '',
          email: ''
        },
        terminal: {
          code: '',
          name: '',
          branch_id: '',
          location: ''
        },
        invoice: {
          prefix: 'INV-',
          next_number: 1001,
          footer_text: 'شكراً لتعاملكم معنا',
          show_tax_in_price: true
        },
        print: {
          header_text: '',
          footer_text: 'شكراً لتعاملكم معنا',
          terms_text: ''
        }
      },
      branches: []
    };
  },
  mounted() {
    this.loadSetupStatus();
  },
  methods: {
    updateCurrencyData() {
      const code = this.formData.company.currency;
      this.formData.company.currency_code = code;
      this.formData.company.currency_symbol = getCurrencySymbol(code);
    },

    async loadTerminals() {
      try {
        const { default: terminalsService } = await import('@/services/terminals');
        const data = await terminalsService.list({ status: undefined });
        this.existingTerminals = Array.isArray(data) ? data : [];
      } catch { this.existingTerminals = []; }
    },

    async loadSetupStatus() {
      try {
        // Load settings for form pre-fill — may return error for non-admin users
        const settingsRes = await this.settingsStore.fetchSettings();
        if (settingsRes?.status === 'success') {
          if (this.settingsStore.company) {
            this.formData.company = { ...this.formData.company, ...this.settingsStore.company };
          }
          if (this.settingsStore.tax) {
            this.formData.tax = { ...this.formData.tax, ...this.settingsStore.tax };
          }
          if (this.settingsStore.invoice) {
            this.formData.invoice = { ...this.formData.invoice, ...this.settingsStore.invoice };
          }
          if (this.settingsStore.print) {
            this.formData.print = { ...this.formData.print, ...this.settingsStore.print };
          }
        }
        // If 'error' (403 etc.) → continues with default values silently

        await this.loadTerminals();

        // جلب الفروع الموجودة — لو المستخدم أنشأ فرعاً وأعاد التحميل يظهر في الخطوة 4
        try {
          await this.branchStore.fetchBranches();
          if (this.branchStore.branches.length > 0) {
            this.branches = [...this.branchStore.branches];
            // اختر الفرع الأول تلقائياً في الـ terminal إذا لم يكن هناك اختيار
            if (!this.formData.terminal.branch_id) {
              this.formData.terminal.branch_id = this.branches[0].id;
            }
          }
        } catch { /* non-critical */ }

        // Load setup/tenant status separately
        const statusResponse = await this.setupStore.loadSetupStatus();
        const tenant = statusResponse?.data?.data?.tenant ?? statusResponse?.data?.tenant ?? null;
        if (tenant?.is_setup_complete && this.$route.query.force !== 'true') {
          this.$router.push('/cashier-dashboard');
        }
      } catch (error) {
        if (error?.response?.status === 403) {
          console.warn('Setup: permission denied, continuing with defaults');
        } else {
          const errorMessage = error.response?.data?.message || 'حدث خطأ أثناء تحميل بيانات الإعداد';
          this.$toast?.error(errorMessage, { position: 'top-center', duration: 5000 });
        }
      } finally {
        this.loading = false;
      }
    },

    async nextStep() {
      if (!this.validateCurrentStep()) return;

      // ─── خطوة الفرع (2) → احفظه فوراً لتظهر في قائمة الـ terminal
      if (this.currentStep === 2 && this.formData.branch.name?.trim()) {
        this.saving = true;
        try {
          const res = await this.branchStore.createBranch({
            name:     this.formData.branch.name,
            location: this.formData.branch.location || '',
            phone:    this.formData.branch.phone    || undefined,
            email:    this.formData.branch.email    || undefined,
          });

          if (res?.status === 'success' && res?.data?.id) {
            // sync مباشر من الـ store
            this.branches = [...this.branchStore.branches];
            this.formData.terminal.branch_id = res.data.id;
          } else if (res?.status === 'error') {
            AlertService.warning(res.message || 'فشل إنشاء الفرع', 'تنبيه');
          }
        } catch (e) {
          const msg = e?.response?.data?.message || '';
          if (e?.response?.status === 409 || msg.toLowerCase().includes('duplicate')) {
            await this.reloadBranches();
          } else if (msg) {
            AlertService.warning(msg, 'تنبيه إنشاء الفرع');
          }
        } finally {
          this.saving = false;
        }
      }

      // ─── خطوة الـ terminal (3) → سجّل الجهاز
      if (this.currentStep === 3 && this.formData.terminal.code && this.formData.terminal.name) {
        try {
          const { default: terminalsService } = await import('@/services/terminals');
          await terminalsService.create({
            code:      this.formData.terminal.code,
            name:      this.formData.terminal.name,
            branch_id: this.formData.terminal.branch_id || undefined,
            location:  this.formData.terminal.location  || undefined,
            status:    'active'
          });
          await this.loadTerminals();
        } catch (e) {
          const msg = e?.response?.data?.message || '';
          if (msg) AlertService.warning(msg, 'تنبيه جهاز POS');
        }
      }

      this.currentStep++;
    },

    async reloadBranches() {
      try {
        await this.branchStore.fetchBranches();
        this.branches = [...this.branchStore.branches];
        // اختر الفرع الأول تلقائياً إذا لم يكن هناك اختيار
        if (this.branches.length > 0 && !this.formData.terminal.branch_id) {
          this.formData.terminal.branch_id = this.branches[0].id;
        }
      } catch { /* ignore */ }
    },

    previousStep() { this.currentStep--; },

    validateCurrentStep() {
      if (this.currentStep === 0 && !this.formData.company.name) {
        AlertService.warning('يرجى إدخال اسم الشركة للمتابعة', 'بيانات ناقصة');
        return false;
      }
      return true;
    },

    async saveSetup() {
      if (!this.validateCurrentStep()) return;

      this.saving = true;
      try {
        // الفرع اتحفظ بالفعل في nextStep — لا ترسله مرة ثانية إذا كان موجوداً
        const payload = {
          ...this.formData,
          branch: this.branches.length > 0 ? null : this.formData.branch,
          current_step: 'complete'
        };

        const response = await this.setupStore.saveSetup(payload);

        if (response.status === 'success') {
          if (this.authStore.user) {
            this.authStore.user.is_setup_complete = 1;
            localStorage.setItem('user', JSON.stringify(this.authStore.user));
          }

          AlertService.success('تم إعداد النظام بنجاح!', 'تم بنجاح');
          this.$router.push('/cashier-dashboard').catch(err => {
            if (!err?.message?.includes('Navigation aborted')) {
              console.warn('Router navigation error:', err);
            }
          });
        } else {
          AlertService.error(response.data.message || 'فشل حفظ الإعدادات', 'خطأ');
        }
      } catch (error) {
        if (!error) {
          return;
        }

        if (error.message?.includes('Navigation aborted')) {
          return;
        }

        const errorMessage =
          error.response?.data?.message ||
          error.message ||
          'حدث خطأ أثناء حفظ الإعدادات';

        AlertService.error(errorMessage, 'خطأ');
      } finally {
        this.saving = false;
      }
    },

    async skipSetup() {
      const confirmed = await AlertService.confirm(
        'هل أنت متأكد من تخطي الإعداد؟ يمكنك إكماله لاحقاً من الإعدادات العامة.',
        'تنبيه'
      );

      if (!confirmed) return;

      this.saving = true;
      try {
        const response = await this.setupStore.skipSetup();

        if (response.status === 'success') {
          if (this.authStore.user) {
            this.authStore.user.is_setup_complete = 1;
            localStorage.setItem('user', JSON.stringify(this.authStore.user));
          }
          this.$router.push('/cashier-dashboard').catch(err => {
            if (!err?.message?.includes('Navigation aborted')) {
              console.warn('Router navigation error:', err);
            }
          });
        } else {
          AlertService.error(response.data.message || 'فشل تخطي الإعداد', 'خطأ');
        }
      } catch (error) {
        const errorMessage = error.response?.data?.message || 'حدث خطأ أثناء تخطي الإعداد';
        AlertService.error(errorMessage, 'خطأ');
      } finally {
        this.saving = false;
      }
    }
  }
};
</script>

<style scoped>



/* Modern Form Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }

/* Utility */
button:disabled { @apply opacity-50 cursor-not-allowed; }
</style>