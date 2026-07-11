<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="closeModal">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-modalIn border border-white flex flex-col text-right" dir="rtl" @click.stop>
      
      <!-- Modal Header -->
      <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-200 text-white">
            <i class="fas fa-sliders text-xl"></i>
          </div>
          <div>
            <h3 class="text-xl font-black text-slate-800 leading-none">تسوية رصيد المخزون</h3>
            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">تعديل يدوي للكميات المتوفرة حالياً</p>
          </div>
        </div>
        <button @click="closeModal" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <form @submit.prevent="handleSubmit" class="p-8 space-y-6 overflow-y-auto custom-scroll max-h-[75vh]">
        
        <!-- Product Summary Profile -->
        <div v-if="product" class="bg-slate-900 p-6 rounded-[2rem] text-white shadow-xl relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-white/5 rounded-full translate-x-8 -translate-y-8 group-hover:scale-110 transition-transform"></div>
          <div class="relative z-10 flex items-center gap-4">
            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-blue-400 shadow-inner"><i class="fas fa-box"></i></div>
            <div>
              <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">المنتج المستهدف</p>
              <h4 class="text-base font-black leading-tight">{{ product.name || product.product_name }}</h4>
              <div class="flex gap-3 mt-2 text-[10px] font-bold text-white/40 font-mono uppercase tracking-tighter">
                <span v-if="product.barcode">BAR: {{ product.barcode }}</span>
                <span>CODE: {{ product.product_code || product.code }}</span>
              </div>
            </div>
          </div>
          <div class="mt-6 pt-4 border-t border-white/5 flex justify-between items-center">
             <span class="text-[10px] font-black text-white/30 uppercase tracking-widest">المخزون الحالي بالمستودع</span>
             <span class="px-3 py-1 bg-white/10 rounded-lg text-xs font-black text-blue-400 font-mono">{{ product.quantity || 0 }} {{ product.unit || 'قطعة' }}</span>
          </div>
        </div>

        <div v-else class="p-5 bg-rose-50 border border-rose-100 rounded-2xl text-rose-600 text-xs font-bold flex items-center gap-3">
          <i class="fas fa-exclamation-triangle"></i>
          عذراً، تعذر تحميل بيانات المنتج المطلوبة.
        </div>

        <!-- Adjustment Type Selector -->
        <div class="space-y-3">
          <label class="modal-label">نوع التسوية (الإجراء)</label>
          <div class="grid grid-cols-2 gap-3">
            <label class="type-selector-btn" :class="{'type-add-active': adjustmentType === 'add'}">
              <input type="radio" v-model="adjustmentType" value="add" class="sr-only">
              <i class="fas fa-plus-circle"></i>
              <span>إضافة وارد</span>
            </label>
            <label class="type-selector-btn" :class="{'type-remove-active': adjustmentType === 'remove'}">
              <input type="radio" v-model="adjustmentType" value="remove" class="sr-only">
              <i class="fas fa-minus-circle"></i>
              <span>خصم / سحب</span>
            </label>
          </div>
        </div>

        <!-- Quantity Field -->
        <div class="space-y-2">
          <label for="quantity" class="modal-label">الكمية المراد تعديلها</label>
          <div class="relative group">
            <input 
              type="number" 
              id="quantity" 
              v-model.number="form.quantity" 
              class="w-full h-14 rounded-2xl border-slate-100 bg-slate-50 text-3xl font-black text-center text-slate-800 focus:bg-white focus:ring-4 focus:ring-blue-50 transition-all outline-none" 
              min="0"
              step="0.01"
              required
              @input="calculateNewQuantity"
            >
            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase tracking-widest">{{ (product?.unit) || 'قطعة' }}</span>
          </div>
          <p v-if="errors.quantity" class="text-[10px] text-rose-500 font-bold px-1 animate-fadeIn">{{ errors.quantity }}</p>
        </div>

        <!-- New Quantity Preview (Result) -->
        <div class="p-5 rounded-2xl border-2 border-dashed border-slate-100 bg-slate-50/50 flex items-center justify-between transition-all">
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">الرصيد النهائي المتوقع</p>
            <p class="text-sm font-bold text-slate-500 mt-1 italic">بعد تنفيذ العملية</p>
          </div>
          <div class="text-left">
            <span :class="[adjustmentType === 'add' ? 'text-emerald-600' : 'text-rose-600']" class="text-3xl font-black font-mono tracking-tighter">
              {{ newQuantity }}
            </span>
          </div>
        </div>

        <!-- Reason Selection -->
        <div class="space-y-2">
          <label for="reason" class="modal-label">سبب التسوية <span class="text-rose-500">*</span></label>
          <select id="reason" v-model="form.reason" class="form-select-modern font-black text-sm cursor-pointer" required>
            <option value="">-- اختر السبب --</option>
            <option value="stock_take">جرد مخزون (مطابقة)</option>
            <option value="damaged">صنف تالف / غير صالح</option>
            <option value="expired">منتهي الصلاحية</option>
            <option value="theft">عجز / فقدان</option>
            <option value="other">أخرى (مذكور بالملاحظات)</option>
          </select>
        </div>

        <!-- Notes Field -->
        <div class="space-y-2 pt-2">
          <label for="notes" class="modal-label">ملاحظات توضيحية إضافية</label>
          <textarea 
            id="notes" 
            v-model="form.notes" 
            rows="2" 
            class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white transition-all outline-none"
            placeholder="اكتب تفاصيل إضافية للمرجعية الإدارية..."
          ></textarea>
        </div>
      </form>

      <!-- Modal Footer -->
      <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
        <button type="button" @click="$emit('close')" class="px-6 py-3 rounded-xl border-2 border-slate-100 font-black text-slate-400 hover:bg-white transition-all text-xs uppercase tracking-widest">
          إلغاء
        </button>
        <button @click="handleSubmit" class="px-10 py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3" :disabled="isSubmitting">
          <BaseSpinner v-if="isSubmitting" :size="16" color="#ffffff" />
          <span>تأكيد وتسوية المخزون</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { adjustStock } from '@/services/branchInventoryService';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

// --- Logic Initialization (STRICTLY PRESERVED) ---
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  branchId: { type: [Number, String], required: true },
  product: { type: Object, required: true }
});

const emit = defineEmits(['update:modelValue', 'close', 'adjusted']);

const closeModal = () => {
  emit('update:modelValue', false);
  emit('close');
};

const { showToast } = useToast();

const form = ref({
  product_id: null, quantity: 1, adjustment: 'add', reason: 'other', notes: '', reference: '', reference_type: 'adjustment', cost_price: null, sale_price: null
});

const adjustmentType = ref('add');
const isSubmitting = ref(false);
const errors = ref({});

// Logic: New Quantity Preview (Preserved)
const newQuantity = computed(() => {
  if (!props.product) return 0;
  const currentQty = parseFloat(props.product.quantity) || 0;
  const adjustmentQty = parseFloat(form.value.quantity) || 0;
  return adjustmentType.value === 'add' ? currentQty + adjustmentQty : Math.max(0, currentQty - adjustmentQty);
});

// Logic: Methods (Preserved)
const calculateNewQuantity = () => {
  const qty = parseFloat(form.value.quantity);
  form.value.quantity = isNaN(qty) ? 0 : Math.abs(qty);
};

const validate = () => {
  errors.value = {};
  let isValid = true;
  if (!form.value.quantity || form.value.quantity <= 0) { errors.quantity = 'يجب إدخال كمية صحيحة'; isValid = false; }
  else if (adjustmentType.value === 'remove' && form.value.quantity > props.product.quantity) { errors.quantity = 'الكمية المطلوبة أكبر من المتوفرة'; isValid = false; }
  if (!form.value.reason) { errors.reason = 'يجب تحديد السبب'; isValid = false; }
  return isValid;
};

const handleSubmit = async () => {
  if (!validate()) return;
  isSubmitting.value = true;
  try {
    if (!form.value.product_id && props.product) form.value.product_id = props.product.product_id || props.product.id || null;
    
    if (!form.value.product_id) { showToast('تعذر تحديد المنتج', 'error'); return; }

    const adjustmentData = {
      product_id: form.value.product_id,
      quantity: adjustmentType.value === 'remove' ? -Math.abs(Number(form.value.quantity)) : Math.abs(Number(form.value.quantity)),
      type: adjustmentType.value === 'add' ? 'in' : 'out',
      reason: form.value.reason,
      notes: form.value.notes || null,
      reference_type: form.value.reference_type,
      cost_price: form.value.cost_price ? Number(form.value.cost_price) : null,
      sale_price: form.value.sale_price ? Number(form.value.sale_price) : null
    };

    const success = await adjustStock(props.branchId, adjustmentData);
    if (success) {
      showToast('تمت التسوية بنجاح', 'success');
      emit('adjusted', adjustmentData);
      emit('close');
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'فشل في تسوية المخزون', 'error');
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  if (props.product) {
    form.value = { ...form.value, product_id: props.product.product_id || props.product.id, cost_price: props.product.cost_price || null, sale_price: props.product.sale_price || null };
  }
  if (!form.value.reason) form.value.reason = 'other';
});

watch(() => props.product, (p) => { if (p) form.value.product_id = p.product_id || p.id || null; });
</script>

<style scoped>

/* Modern UI Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

/* Custom Segmented Buttons */
.type-selector-btn { @apply flex items-center justify-center gap-2 p-4 rounded-2xl bg-slate-50 border-2 border-transparent text-xs font-black text-slate-400 cursor-pointer transition-all; }
.type-add-active { @apply bg-emerald-50 border-emerald-500 text-emerald-600 shadow-lg shadow-emerald-50; }
.type-remove-active { @apply bg-rose-50 border-rose-500 text-rose-600 shadow-lg shadow-rose-50; }

/* Transitions & Modals */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>