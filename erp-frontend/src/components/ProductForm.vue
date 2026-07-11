<template>
  <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden animate-modalIn border border-white flex flex-col" @click.stop>
    
    <!-- Modal Header -->
    <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
          <i :class="[isEditMode ? 'fas fa-edit' : 'fas fa-plus-circle', 'text-xl']"></i>
        </div>
        <div>
          <h3 class="text-xl font-black text-slate-800 leading-none">{{ isEditMode ? 'تعديل بيانات المنتج' : 'إضافة منتج جديد للمخزن' }}</h3>
          <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">يرجى ملء الحقول المطلوبة بدقة</p>
        </div>
      </div>
      <button @click="$emit('close')" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <form @submit.prevent="handleSubmit" class="flex-grow overflow-y-auto custom-scroll p-8 space-y-8 text-right" dir="rtl">
      
      <!-- Section 1: Basic Information -->
      <div class="space-y-6">
        <h4 class="text-xs font-black text-blue-600 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          البيانات الأساسية
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="form-label-modern">اسم المنتج <span class="text-rose-500">*</span></label>
            <input type="text" v-model="form.name" class="form-input-modern px-4" :class="{'border-rose-300 ring-rose-50': errors.name}" placeholder="مثال: آيفون 15 برو ماكس" />
            <p v-if="errors.name" class="text-[10px] text-rose-500 font-bold px-1 animate-pulse">{{ errors.name }}</p>
          </div>

          <div v-if="isEditMode" class="space-y-2">
            <label class="form-label-modern">كود المنتج (SKU)</label>
            <div class="relative group">
              <input type="text" v-model="form.product_code" class="form-input-modern pr-10 font-mono" placeholder="PRD-000001" />
              <i class="fas fa-hashtag absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
            </div>
            <p v-if="errors.product_code" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.product_code }}</p>
            <p class="text-[9px] text-slate-400 font-bold px-1">ℹ️ يمكن تعديل الكود للمنتجات الموجودة</p>
          </div>

          <div class="space-y-2">
            <label class="form-label-modern">الباركود (Barcode)</label>
            <div class="relative group">
              <input type="text" v-model="form.barcode" class="form-input-modern pr-10 font-mono" placeholder="0000000000" />
              <i class="fas fa-barcode absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="form-label-modern">التصنيف</label>
            <div class="flex flex-col gap-2">
              <select v-model="form.category_id" class="form-select-modern font-bold">
                <option :value="null">بدون تصنيف (عام)</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
              <div class="flex gap-2 items-center mt-1">
                <input type="text" v-model="newCategoryName" placeholder="أو أضف تصنيف جديد..." class="form-input-modern px-4 h-9 text-xs flex-grow" />
                <button type="button" @click="addNewCategory" :disabled="isAddingCategory" class="h-9 px-4 bg-slate-800 text-white rounded-xl text-[10px] font-black hover:bg-black transition-all flex items-center gap-2 shrink-0">
                  <BaseSpinner v-if="isAddingCategory" :size="12" color="#fff" />
                  إضافة
                </button>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <label class="form-label-modern">الوحدة الأساسية <span class="text-rose-500">*</span></label>
            <select v-model="form.unit_id" class="form-select-modern font-bold" :class="{'border-rose-300': errors.unit_id}">
              <option :value="null">-- اختر الوحدة --</option>
              <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
            </select>
            <p v-if="errors.unit_id" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.unit_id }}</p>
          </div>
        </div>
      </div>

      <!-- Section 2: Pricing -->
      <div class="space-y-6 pt-4">
        <h4 class="text-xs font-black text-emerald-600 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
          بيانات التسعير ({{ currencySymbol }})
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="space-y-2">
            <label class="form-label-modern">سعر الشراء <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input type="number" step="0.01" v-model="form.purchase_price" class="form-input-modern pl-10 font-black text-slate-700" placeholder="0.00" />
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300">{{ currencySymbol }}</span>
            </div>
            <p v-if="errors.purchase_price" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.purchase_price }}</p>
          </div>

          <div class="space-y-2">
            <label class="form-label-modern">سعر البيع <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input type="number" step="0.01" v-model="form.sale_price" class="form-input-modern pl-10 font-black text-blue-600" placeholder="0.00" />
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-blue-300">{{ currencySymbol }}</span>
            </div>
            <p v-if="errors.sale_price" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.sale_price }}</p>
          </div>

          <div class="space-y-2">
            <label class="form-label-modern">أقل سعر بيع مسموح</label>
            <div class="relative">
              <input type="number" step="0.01" v-model="form.min_sale_price" class="form-input-modern pl-10 font-black text-amber-600" placeholder="0.00" />
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-amber-300">{{ currencySymbol }}</span>
            </div>
            <p v-if="errors.min_sale_price" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.min_sale_price }}</p>
          </div>
        </div>
      </div>

      <!-- Section 3: Inventory & Tracking -->
      <div class="space-y-6 pt-4">
        <h4 class="text-xs font-black text-indigo-600 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
          المخزون وتتبع الكميات
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="form-label-modern">الكمية الإجمالية الحالية</label>
            <div class="relative group">
              <input type="number" :value="form.quantity || 0" readonly class="form-input-modern pr-10 bg-slate-50 cursor-not-allowed font-black opacity-70" />
              <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
            </div>
            <div class="mt-2 p-3 bg-blue-50 border border-blue-100 rounded-2xl flex items-start gap-3 shadow-sm">
              <i class="fas fa-info-circle text-blue-500 mt-1"></i>
              <p class="text-[10px] text-blue-700 font-bold leading-relaxed">
                الكمية الإجمالية للقراءة فقط. يمكنك تعديل الأرصدة عبر 
                <router-link :to="isEditMode ? '/branches' : '/setup/opening-balance'" class="underline decoration-blue-300 hover:text-blue-900">
                  {{ isEditMode ? 'إدارة الفروع' : 'صفحة الرصيد الافتتاحي' }}
                </router-link>.
              </p>
            </div>
          </div>

          <div class="space-y-2">
            <label class="form-label-modern">حد التنبيه (الحد الأدنى)</label>
            <div class="relative group">
              <input type="number" v-model="form.min_quantity" class="form-input-modern pr-10 font-black text-slate-700" placeholder="مثال: 5" />
              <i class="fas fa-bell absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
            </div>
            <p v-if="errors.min_quantity" class="text-[10px] text-rose-500 font-bold px-1">{{ errors.min_quantity }}</p>
          </div>
        </div>

        <!-- Product Type Selection -->
        <div class="py-4">
          <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            نوع المنتج
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <label class="tracking-container group">
              <input type="radio" v-model="form.product_type" value="stock" class="hidden" />
              <div class="tracking-box group-hover:border-emerald-200 transition-all" :class="{'tracking-box-active tracking-box-selected': form.product_type === 'stock'}">
                <i class="fas fa-boxes text-emerald-500"></i>
                <span class="font-bold">منتج مخزون</span>
                <p class="text-[10px] text-slate-400">يحتاج لمخزون وكميات</p>
              </div>
            </label>

            <label class="tracking-container group">
              <input type="radio" v-model="form.product_type" value="service" class="hidden" />
              <div class="tracking-box group-hover:border-blue-200 transition-all" :class="{'tracking-box-active tracking-box-selected': form.product_type === 'service'}">
                <i class="fas fa-concierge-bell text-blue-500"></i>
                <span class="font-bold">خدمة / طلبية</span>
                <p class="text-[10px] text-slate-400">بدون مخزون (Service)</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Checkboxes Grid (Only for stock products) -->
        <div v-if="form.product_type === 'stock'" class="grid grid-cols-1 md:grid-cols-3 gap-4 py-2">
          <label class="tracking-container group">
            <input type="checkbox" v-model="form.has_expiry_date" class="hidden" />
            <div class="tracking-box group-hover:border-blue-200 transition-all" :class="{'tracking-box-active': form.has_expiry_date}">
              <i class="fas fa-calendar-alt"></i>
              <span>تاريخ صلاحية</span>
            </div>
          </label>

          <label class="tracking-container group">
            <input type="checkbox" v-model="form.has_batch_number" class="hidden" />
            <div class="tracking-box group-hover:border-blue-200 transition-all" :class="{'tracking-box-active': form.has_batch_number}">
              <i class="fas fa-layer-group"></i>
              <span>رقم دفعة (Batch)</span>
            </div>
          </label>

          <label class="tracking-container group">
            <input type="checkbox" v-model="form.has_serial_number" class="hidden" />
            <div class="tracking-box group-hover:border-blue-200 transition-all" :class="{'tracking-box-active': form.has_serial_number}">
              <i class="fas fa-hashtag"></i>
              <span>رقم تسلسلي (Serial)</span>
            </div>
          </label>
        </div>

        <!-- Conditional Tracking Inputs -->
        <transition-group name="slide-fade">
          <div v-if="form.has_expiry_date" key="expiry" class="space-y-2 p-5 bg-slate-50 rounded-2xl border border-slate-100 animate-fadeIn">
            <label class="form-label-modern text-blue-600">تاريخ الصلاحية الافتراضي</label>
            <div class="relative">
              <input ref="expiryDateRef" type="date" v-model="form.expiry_date" class="form-input-modern pr-10 font-bold" />
              <i class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer" @click="expiryDateRef.showPicker()"></i>
            </div>
            <p v-if="errors.expiry_date" class="text-[10px] text-rose-500 font-bold">{{ errors.expiry_date }}</p>
          </div>

          <div v-if="form.has_batch_number" key="batch" class="space-y-2 p-5 bg-slate-50 rounded-2xl border border-slate-100 animate-fadeIn">
            <label class="form-label-modern text-blue-600">رقم الدفعة (Batch Number)</label>
            <input type="text" v-model="form.batch_number" class="form-input-modern px-4 font-mono" placeholder="B-2024-X" />
          </div>

          <div v-if="form.has_serial_number" key="serial" class="space-y-2 p-5 bg-slate-50 rounded-2xl border border-slate-100 animate-fadeIn">
            <label class="form-label-modern text-blue-600">الرقم التسلسلي (Serial Number)</label>
            <input type="text" v-model="form.serial_number" class="form-input-modern px-4 font-mono" placeholder="SN-123..." />
          </div>
        </transition-group>
      </div>

      <!-- Description Area -->
      <div class="space-y-2 pt-4">
        <label class="form-label-modern uppercase tracking-widest">وصف المنتج (اختياري)</label>
        <textarea v-model="form.description" class="w-full rounded-3xl border border-slate-200 p-5 text-sm font-bold bg-white outline-none focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all" rows="3" placeholder="أدخل تفاصيل إضافية عن المنتج هنا..."></textarea>
      </div>

      <!-- Branch Assignment Section (New Products Only) -->
      <div v-if="!isEditMode && branches.length > 0" class="space-y-6 pt-4 border-t border-slate-100 mt-6">
        <h4 class="text-xs font-black text-amber-600 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
          توزيع المنتج على الفروع <span class="text-rose-500">*</span>
        </h4>

        <!-- Branch Assignment Options (ALWAYS VISIBLE - Mandatory) -->
        <div class="space-y-3">
          <p class="text-[11px] font-black text-slate-500 uppercase tracking-widest">اختر الفروع:</p>

          <label v-if="props.branchId" class="flex items-center gap-3 p-3 rounded-xl border-2 border-slate-100 hover:border-blue-200 cursor-pointer transition-all" :class="{ 'border-blue-500 bg-blue-50': branchAssignmentType === 'current' }">
            <input type="radio" v-model="branchAssignmentType" value="current" class="w-4 h-4 text-blue-600" />
            <span class="font-bold text-slate-700 text-sm">هذا الفرع فقط</span>
            <i class="fas fa-store text-blue-400 mr-auto"></i>
          </label>

          <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-slate-100 hover:border-emerald-200 cursor-pointer transition-all" :class="{ 'border-emerald-500 bg-emerald-50': branchAssignmentType === 'all' }">
            <input type="radio" v-model="branchAssignmentType" value="all" class="w-4 h-4 text-emerald-600" />
            <span class="font-bold text-slate-700 text-sm">كل الفروع</span>
            <i class="fas fa-globe text-emerald-400 mr-auto"></i>
          </label>

          <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-slate-100 hover:border-amber-200 cursor-pointer transition-all" :class="{ 'border-amber-500 bg-amber-50': branchAssignmentType === 'selected' }">
            <input type="radio" v-model="branchAssignmentType" value="selected" class="w-4 h-4 text-amber-600" />
            <span class="font-bold text-slate-700 text-sm">فروع محددة</span>
            <i class="fas fa-check-square text-amber-400 mr-auto"></i>
          </label>
        </div>

        <!-- Selected Branches List -->
        <div v-if="branchAssignmentType === 'selected'" class="space-y-3">
          <p class="text-[11px] font-black text-slate-500 uppercase tracking-widest">اختر الفروع:</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-48 overflow-y-auto custom-scroll p-1">
            <label v-for="branch in branches" :key="branch.id" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition-all">
              <input type="checkbox" v-model="selectedBranches" :value="branch.id" class="w-4 h-4 text-blue-600 rounded" />
              <span class="font-bold text-slate-700 text-sm">{{ branch.name }}</span>
            </label>
          </div>
          <p v-if="selectedBranches.length === 0" class="text-[10px] text-rose-500 font-bold">يرجى اختيار فرع واحد على الأقل</p>
        </div>

        <!-- Opening Balance Section (SEPARATE - Optional, only for stock products) -->
        <div v-if="form.product_type === 'stock'" class="pt-4 border-t border-slate-100">
          <label class="flex items-center gap-3 p-4 rounded-2xl border-2 border-slate-100 hover:border-amber-200 cursor-pointer transition-all" :class="{ 'border-amber-500 bg-amber-50': enableOpeningBalance }">
            <input type="checkbox" v-model="enableOpeningBalance" class="w-5 h-5 text-amber-600 rounded" />
            <div class="flex-1">
              <span class="font-black text-slate-700">إضافة رصيد افتتاحي الآن</span>
              <p class="text-[10px] text-slate-500 mt-1">اختياري - يمكنك إضافة الكمية والتكلفة لاحقًا من إدارة المخزون</p>
            </div>
            <i class="fas fa-boxes text-amber-500"></i>
          </label>

          <!-- Initial Stock Fields (shown only when opening balance enabled) -->
          <div v-if="enableOpeningBalance" class="grid grid-cols-2 gap-4 pt-4 animate-fadeIn">
            <div class="space-y-2">
              <label class="form-label-modern text-amber-600">الكمية الافتتاحية <span class="text-rose-500">*</span></label>
              <input type="number" v-model.number="initialQuantity" min="1" class="form-input-modern px-4 font-black" placeholder="مثال: 10" required />
              <p v-if="initialQuantity <= 0" class="text-[10px] text-rose-500 font-bold">الكمية مطلوبة</p>
            </div>
            <div class="space-y-2">
              <label class="form-label-modern text-amber-600">سعر التكلفة (الوحدة) <span class="text-rose-500">*</span></label>
              <input type="number" v-model.number="initialUnitCost" min="0.01" step="0.01" class="form-input-modern px-4 font-black" placeholder="مثال: 50.00" required />
              <p v-if="initialUnitCost <= 0" class="text-[10px] text-rose-500 font-bold">سعر التكلفة مطلوب</p>
            </div>
          </div>

          <!-- Total Cost Preview -->
          <div v-if="enableOpeningBalance && initialQuantity > 0 && initialUnitCost > 0" class="p-4 bg-amber-50 border border-amber-200 rounded-xl mt-4">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-amber-700 font-bold">الإجمالي:</span>
              <span class="text-lg font-black text-amber-800">
                {{ (initialQuantity * initialUnitCost).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }} {{ currencySymbol }}
              </span>
            </div>
            <p class="text-[10px] text-amber-600 mt-1">سيتم إنشاء قيد محاسبي تلقائي (مدين: المخزون / دائن: الأرصدة الافتتاحية)</p>
          </div>
        </div>
      </div>

    </form>

    <!-- Modal Footer Actions -->
    <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-4 shrink-0">
      <button type="button" @click="$emit('close')" class="px-6 py-3 rounded-2xl border-2 border-slate-200 font-black text-slate-500 hover:bg-white hover:text-rose-500 hover:border-rose-100 transition-all text-sm">
        إلغاء
      </button>
      <button @click="handleSubmit" :disabled="isSaving" class="px-10 py-3 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3">
        <BaseSpinner v-if="isSaving" :size="16" color="#fff" />
        <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ البيانات' }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import apiClient from '../config/axios';
import { useToast } from '@/composables/useToast';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

const props = defineProps({
  productData: { type: Object, default: null },
  categories: { type: Array, required: true },
  branchId: { type: Number, default: null },
  branches: { type: Array, default: () => [] }
});

// حدث open-opening-balance حُذف — الترصيد انتقل لصفحة إدارة المخزون
const emit = defineEmits(['close', 'success', 'category-added']);

const { showToast } = useToast();
const { currencySymbol, fetchSettings } = useCompanyCurrency();

const form = ref({});
const isSaving = ref(false);

// Date input refs
const expiryDateRef = ref(null);
const errors = ref({});
const units = ref([]);
const newCategoryName = ref('');
const isAddingCategory = ref(false);

// Branch assignment state (for new products only)
const enableOpeningBalance = ref(false);
const branchAssignmentType = ref(props.branchId ? 'current' : 'selected'); // when no specific branch, force explicit selection
const selectedBranches = ref([]);
const initialQuantity = ref(0);
const initialUnitCost = ref(0);

// Logic: Populate form
watch(() => props.productData, (newData) => {
  form.value = newData
    ? { 
        // Basic Info
        id: newData.id || null,
        name: newData.name || '',
        product_code: newData.product_code || '',
        barcode: newData.barcode || '',
        description: newData.description || '',
        category_id: newData.category_id || null,
        category_name: newData.category_name || '',
        // Pricing
        purchase_price: newData.purchase_price || 0,
        sale_price: newData.sale_price || 0,
        min_sale_price: newData.min_sale_price || 0,
        fixed_discount_percentage: newData.fixed_discount_percentage || 0,
        // Inventory
        quantity: newData.current_quantity || newData.quantity || 0,
        current_quantity: newData.current_quantity || 0,
        unit_id: newData.unit_id || null,
        unit_name: newData.unit_name || '',
        min_quantity: newData.min_quantity || 0,
        max_quantity: newData.max_quantity || 0,
        // Configuration
        product_type: newData.product_type || 'stock',
        active: newData.active ?? 1,
        // من configuration object أو root level
        has_expiry_date: !!(newData.has_expiry_date ?? newData.configuration?.has_expiry_date),
        has_batch_number: !!(newData.has_batch_number ?? newData.configuration?.has_batch_number),
        has_serial_number: !!(newData.has_serial_number ?? newData.configuration?.has_serial_number),
        // Optional fields - من root level (تمرر من openEditModal)
        expiry_date: newData.expiry_date || '',
        batch_number: newData.batch_number || '',
        serial_number: newData.serial_number || '',
      }
    : { 
        id: null,
        name: '', 
        product_code: '',
        barcode: '',
        description: '',
        category_id: null, 
        category_name: '',
        quantity: '', 
        current_quantity: 0,
        purchase_price: '', 
        sale_price: '', 
        min_sale_price: '', 
        min_quantity: '',
        has_expiry_date: false, 
        expiry_date: '', 
        has_batch_number: false, 
        batch_number: '',
        has_serial_number: false, 
        serial_number: '', 
        unit_id: null, 
        unit_name: '',
        product_type: 'stock',
        active: 1,
        fixed_discount_percentage: 0,
        max_quantity: 0,
      };
  errors.value = {};
}, { immediate: true });

const isEditMode = computed(() => !!props.productData);

const fetchUnits = async () => {
  try {
    const response = await apiClient.get('/units');
    units.value = response.data.data;
  } catch (error) {
    showToast(error.response?.data?.message || 'فشل في جلب الوحدات', 'error');
  }
};

onMounted(() => {
  fetchUnits();
  fetchSettings();
});

const addNewCategory = async () => {
  if (!newCategoryName.value.trim()) {
    showToast('يرجى إدخال اسم التصنيف.', 'error');
    return;
  }
  isAddingCategory.value = true;
  try {
    await apiClient.post('/categories', { 
      name: newCategoryName.value,
      branch_id: props.branchId
    });
    showToast(`تم إضافة التصنيف "${newCategoryName.value}" بنجاح!`, 'success');
    newCategoryName.value = '';
    emit('category-added');
  } catch (error) {
    showToast(error.response?.data?.message || 'فشل في إضافة التصنيف', 'error');
  } finally {
    isAddingCategory.value = false;
  }
};

const validateForm = () => {
  errors.value = {};
  if (!form.value.name) errors.value.name = 'اسم المنتج مطلوب.';
  if (!form.value.sale_price || isNaN(form.value.sale_price)) errors.value.sale_price = 'سعر البيع مطلوب.';
  if (!form.value.purchase_price || isNaN(form.value.purchase_price)) errors.value.purchase_price = 'سعر الشراء مطلوب.';
  if (form.value.min_sale_price && isNaN(form.value.min_sale_price)) errors.value.min_sale_price = 'يجب أن يكون رقماً.';
  if (form.value.min_quantity && isNaN(form.value.min_quantity)) errors.value.min_quantity = 'يجب أن يكون رقماً.';
  if (form.value.has_expiry_date && !form.value.expiry_date) errors.value.expiry_date = 'يرجى تحديد التاريخ.';
  if (!form.value.unit_id) errors.value.unit_id = 'الوحدة مطلوبة.';
  return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
  if (!validateForm()) return;

  const isStockProduct = form.value.product_type === 'stock';

  // Validate branch selection for all new products
  if (!isEditMode.value && branchAssignmentType.value === 'selected' && selectedBranches.value.length === 0) {
    showToast('يرجى اختيار فرع واحد على الأقل لتوزيع المنتج', 'error');
    return;
  }

  // Validate opening balance data only if enabled (only for stock products)
  if (!isEditMode.value && isStockProduct && enableOpeningBalance.value) {
    if (!initialQuantity.value || initialQuantity.value <= 0) {
      showToast('يرجى إدخال الكمية الافتتاحية', 'error');
      return;
    }
    if (!initialUnitCost.value || initialUnitCost.value <= 0) {
      showToast('يرجى إدخال سعر التكلفة', 'error');
      return;
    }
  }

  isSaving.value = true;
  try {
    const payload = { ...form.value };
    
    // Map field names from form to database column names
    if (payload.expiry_date !== undefined && payload.expiry_date !== null && payload.expiry_date !== '') {
      payload.default_expiry_date = payload.expiry_date;
      delete payload.expiry_date;
    }
    if (payload.batch_number !== undefined && payload.batch_number !== null && payload.batch_number !== '') {
      payload.default_batch_number = payload.batch_number;
      delete payload.batch_number;
    }
    if (payload.serial_number !== undefined && payload.serial_number !== null && payload.serial_number !== '') {
      payload.default_serial_number = payload.serial_number;
      delete payload.serial_number;
    }

    if (!isEditMode.value) {
      let targetBranchIds = [];
      let initialQty = 0;
      let initialCost = 0;

      if (isStockProduct) {
        if (enableOpeningBalance.value) {
          if (branchAssignmentType.value === 'current') {
            targetBranchIds = props.branchId ? [props.branchId] : props.branches.map(b => b.id);
          } else if (branchAssignmentType.value === 'all') {
            targetBranchIds = props.branches.map(b => b.id);
          } else if (branchAssignmentType.value === 'selected') {
            targetBranchIds = selectedBranches.value;
          }
          initialQty = initialQuantity.value || 0;
          initialCost = initialUnitCost.value || 0;
        } else {
          if (branchAssignmentType.value === 'all' || !props.branchId) {
            targetBranchIds = props.branches.map(b => b.id);
          } else if (branchAssignmentType.value === 'selected') {
            targetBranchIds = selectedBranches.value.length > 0 ? selectedBranches.value : [props.branchId];
          } else {
            targetBranchIds = props.branchId ? [props.branchId] : props.branches.map(b => b.id);
          }
          initialQty = 0;
          initialCost = 0;
        }
      } else {
        // Service product: attach to current branch or all branches if no branch selected
        targetBranchIds = props.branchId ? [props.branchId] : props.branches.map(b => b.id);
        initialQty = 0;
        initialCost = 0;
      }

      payload.branch_assignments = {
        branch_ids: targetBranchIds,
        initial_quantity: initialQty,
        initial_unit_cost: initialCost
      };
    }

    if (isEditMode.value) {
      await apiClient.put(`/products/${props.productData.id}`, payload);
      showToast('تم تحديث المنتج بنجاح!', 'success');
    } else {
      await apiClient.post('/products', payload);
      showToast('تم إضافة المنتج بنجاح!', 'success');

      // إذا لم يتم إدخال رصيد افتتاحي — توجيه المستخدم لإدارة المخزون
      if (!enableOpeningBalance.value && isStockProduct) {
        showToast('✅ تم إضافة المنتج! يمكنك الترصيد لاحقاً من صفحة إدارة المخزون', 'info', 4000);
      }
    }

    emit('success');
  } catch (error) {
    // Handle SKU duplicate error (409 Conflict)
    if (error.response?.status === 409) {
      errors.value.product_code = 'كود المنتج (SKU) مكرر بالفعل في النظام.';
      showToast(error.response?.data?.message || 'كود المنتج مكرر', 'error');
    } else {
      showToast(error.response?.data?.message || 'فشل في حفظ المنتج', 'error');
    }
  } finally {
    isSaving.value = false;
  }
};
</script>

<style scoped>

.form-label-modern { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl py-0 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none; }

/* Tracking Selection Boxes */
.tracking-container { @apply flex-1 cursor-pointer; }
.tracking-box { @apply flex flex-col items-center justify-center p-4 rounded-[1.5rem] bg-slate-50 border border-slate-100 text-slate-400 gap-2 transition-all; }
.tracking-box i { @apply text-lg; }
.tracking-box span { @apply text-[10px] font-black uppercase; }
.tracking-box-active { @apply bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-100; }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-enter-from { opacity: 0; transform: translateY(-10px); }
</style>