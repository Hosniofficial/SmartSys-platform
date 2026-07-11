<template>
  <div class="space-y-4">
    <!-- الخطوة 1: البحث عن الفاتورة -->
    <div class="relative">
      <label class="form-label">1. البحث عن الفاتورة</label>
      <div class="relative">
        <input 
          ref="invoiceSearchInputRef"
          type="text" 
          v-model="invoiceNumber" 
          @input="debouncedSearchInvoice"
          @focus="showInvoiceDropdown = true"
          @blur="handleInvoiceBlur"
          @keydown.down="selectNextResult"
          @keydown.up="selectPrevResult"
          @keydown.enter.prevent="selectHighlightedResult"
          @keydown.escape="showInvoiceDropdown = false"
          class="form-input w-full" 
          placeholder="ابحث بـ: رقم الفاتورة أو اسم العميل..."
          autocomplete="off"
        >
        <div v-if="isSearchingInvoice" class="absolute left-4 top-1/2 -translate-y-1/2">
          <BaseSpinner :size="16" color="#3b82f6" />
        </div>
        
        <!-- Invoice Search Dropdown -->
        <Teleport to="body">
          <transition name="dropdown-invoice">
            <div 
              v-if="showInvoiceDropdown && invoiceSearchResults.length && !selectedInvoice" 
              ref="invoiceDropdownRef"
              class="fixed bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-80 overflow-y-auto border-t-0 rounded-t-none pointer-events-auto"
              :style="[invoiceDropdownPosition, { zIndex: 99999 }]"
            >
            <!-- Header -->
            <div class="px-4 py-2 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between sticky top-0">
              <span class="text-[10px] font-black text-slate-400 uppercase">نتائج البحث</span>
              <span class="text-[10px] font-black text-slate-500 bg-white px-2 py-1 rounded-lg">{{ invoiceSearchResults.length }}</span>
            </div>
            
            <!-- Results List -->
            <button 
              v-for="(inv, idx) in invoiceSearchResults.slice(0, 20)" 
              :key="inv.id"
              @click="selectInvoice(inv)"
              type="button"
              :class="['w-full px-4 py-3 text-right border-b border-slate-50 last:border-b-0 flex items-start gap-3 group transition-all', idx === highlightedIdx ? 'bg-blue-100 border-l-4 border-l-blue-500' : 'hover:bg-blue-50']"
            >
              <div class="flex-grow min-w-0">
                <div class="text-sm font-black text-slate-800 mb-1">#{{ inv.invoice_number || inv.id }}</div>
                <div class="text-[11px] text-slate-600 space-y-0.5">
                  <div v-if="inv.customer_name" class="flex items-center gap-2 truncate">
                    <i class="fas fa-user text-[8px] text-slate-400 flex-shrink-0"></i>
                    <span class="truncate">{{ inv.customer_name }}</span>
                  </div>
                  <div v-else-if="inv.supplier_name" class="flex items-center gap-2 truncate">
                    <i class="fas fa-building text-[8px] text-slate-400 flex-shrink-0"></i>
                    <span class="truncate">{{ inv.supplier_name }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <i class="fas fa-calendar text-[8px] text-slate-400"></i>
                    {{ formatDateShort(inv.invoice_date) }}
                    <span class="text-[10px] px-1.5 py-0.5 rounded" :class="getStatusBadgeClass(inv.payment_status)">{{ paymentStatusText(inv.payment_status) }}</span>
                  </div>
                </div>
              </div>
              <div class="text-right text-sm font-bold text-blue-600 flex-shrink-0">
                <div class="mb-1">{{ formatPrice(inv.total_amount || inv.net_total_amount || 0) }}</div>
                <div class="text-[9px] text-slate-500 space-y-0.5 font-normal">
                  <div v-if="inv.paid_amount && inv.paid_amount > 0">مدفوع: {{ formatPrice(inv.paid_amount) }}</div>
                  <div v-if="inv.outstanding_amount && inv.outstanding_amount > 0" class="text-amber-600">متبقي: {{ formatPrice(inv.outstanding_amount) }}</div>
                </div>
              </div>
            </button>
            
            <!-- Show More Message -->
            <div v-if="invoiceSearchResults.length > 20" class="px-4 py-3 text-center bg-slate-50 border-t border-slate-100">
              <p class="text-[10px] text-slate-500">و {{ invoiceSearchResults.length - 20 }} فاتورة أخرى (استخدم ↑↓ للتنقل)</p>
            </div>
            </div>
          </transition>
        </Teleport>
        
        <!-- No Results -->
        <div v-if="invoiceNumber && !isSearchingInvoice && !invoiceSearchResults.length && !selectedInvoice" class="text-xs text-red-500 mt-2">
          <i class="fas fa-exclamation-circle mr-1"></i>
          لا توجد فواتير مطابقة للبحث
        </div>
      </div>
    </div>

    <!-- الخطوة 2: اختيار المنتجات -->
    <div v-if="selectedInvoice" class="space-y-2">
      <div class="flex items-center gap-3 mb-2">
        <h4 class="form-label">2. حدد المنتجات وكمية الإرجاع</h4>
        <label class="flex items-center gap-1 cursor-pointer text-xs bg-blue-100 px-2 py-1 rounded">
          <input type="checkbox" v-model="isFullReturn" @change="setFullReturn" />
          إرجاع كامل الفاتورة
        </label>
      </div>
      <!-- تفاصيل الفاتورة المختارة -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 text-sm">
        <div class="bg-gray-50 p-3 rounded">
          <div><span class="font-semibold">رقم الفاتورة:</span> <span class="font-mono">{{ selectedInvoice.invoice_number || selectedInvoice.id }}</span></div>
          <div><span class="font-semibold">التاريخ:</span> {{ selectedInvoice.invoice_date }}</div>
          <div v-if="isSale"><span class="font-semibold">طريقة الدفع الأصلية:</span> <span class="px-2 py-0.5 rounded text-white" :class="statusBadgeClass(selectedInvoice.payment_status)">{{ selectedInvoice.payment_method_name || paymentStatusText(selectedInvoice.payment_status) }}</span></div>
        </div>
        <div class="bg-gray-50 p-3 rounded col-span-2">
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-0.5 text-gray-500">إجمالي الفاتورة</td>
                <td class="py-0.5 text-left font-mono">{{ formatPrice(selectedInvoice.total_amount || 0) }}</td>
              </tr>
              <tr v-if="selectedInvoice.has_discount || Number(selectedInvoice.discount_value) > 0">
                <td class="py-0.5 text-red-500">الخصم</td>
                <td class="py-0.5 text-left font-mono text-red-500">- {{ formatPrice(selectedInvoice.discount_value || 0) }}</td>
              </tr>
              <tr v-if="Number(selectedInvoice.tax_amount) > 0">
                <td class="py-0.5 text-gray-500">الضريبة</td>
                <td class="py-0.5 text-left font-mono">+ {{ formatPrice(selectedInvoice.tax_amount || 0) }}</td>
              </tr>
              <tr class="border-t border-gray-300 font-semibold">
                <td class="pt-1">صافي المدفوع</td>
                <td class="pt-1 text-left font-mono">{{ formatPrice(selectedInvoice.net_total_amount || 0) }}</td>
              </tr>
              <tr v-if="outstanding > 0">
                <td class="py-0.5 text-amber-600">المتبقي غير المسدد</td>
                <td class="py-0.5 text-left font-mono text-amber-600">{{ formatPrice(outstanding) }}</td>
              </tr>
              <tr class="border-t border-blue-300 font-bold text-blue-700">
                <td class="pt-1">قيمة المرتجع المستحق</td>
                <td class="pt-1 text-left font-mono">{{ formatPrice(totalReturnAmount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- تنبيه: منع النقدي مع وجود مديونية/ذمة سواء مبيعات أو مشتريات -->
      <div v-if="hasOutstanding" class="mb-3 p-3 rounded bg-amber-50 text-amber-800 border border-amber-200 text-sm">
        <template v-if="isSale">
          لا يمكن رد نقدي لفاتورة مبيعات آجلة أو غير مسددة بالكامل. سيتم خصم قيمة المرتجع من ذمة العميل فقط.
        </template>
        <template v-else>
          لا يمكن استلام نقدي لمرتجع على فاتورة مشتريات آجلة أو غير مسددة بالكامل. سيتم خصم قيمة المرتجع من ذمة المورد فقط.
        </template>
      </div>
      <table class="min-w-full divide-y divide-gray-200 mb-4">
        <thead class="bg-gray-50">
          <tr>
            <th class="table-header">المنتج</th>
            <th class="table-header">الكمية الأصلية</th>
            <th class="table-header">المرتجع سابقًا</th>
            <th class="table-header">المتبقي المسموح</th>
            <th class="table-header">كمية الإرجاع</th>
            <th class="table-header">السعر</th>
            <th class="table-header">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in selectedInvoice.items" :key="item.id">
            <td class="table-cell">{{ item.product_name }}</td>
            <td class="table-cell">{{ item[quantityKey] }}</td>
            <td class="table-cell">{{ item.prevReturned ?? 0 }}</td>
            <td class="table-cell">{{ item.remainingQty ?? item[quantityKey] }}</td>
            <td class="table-cell">
              <input type="number" v-model.number="item.returnQuantity" :disabled="isFullReturn" min="0" :max="item.remainingQty ?? item[quantityKey]" class="w-20 form-input" @change="() => { const max = item.remainingQty ?? item[quantityKey]; if(item.returnQuantity>max) item.returnQuantity=max; }" />
            </td>
            <td class="table-cell">{{ formatPrice(item[displayPriceKey]) }}</td>
            <td class="table-cell">
              <button v-if="!isFullReturn" class="text-blue-600" @click="addToReturnList(item)">إضافة</button>
              <span v-else>-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- الخطوة 3: سبب الإرجاع -->
    <div v-if="selectedInvoice">
      <label class="form-label">3. سبب الإرجاع</label>
      <textarea v-model="returnReason" rows="3" class="form-input"></textarea>
    </div>

    <!-- ملخص المرتجع -->
    <div v-if="returnItems.length" class="bg-blue-50 p-4 rounded-lg space-y-2">
      <div>
        <span class="font-bold">نوع المرتجع:</span>
        <span class="text-blue-700">{{ isFullReturn ? 'مرتجع كامل' : 'مرتجع جزئي' }}</span>
        <span class="mx-2">|</span>
        <span>عدد المنتجات: {{ returnItems.length }}</span>
        <span class="mx-2">|</span>
        <span>إجمالي المرتجع: {{ formatPrice(totalReturnAmount) }}</span>
      </div>
      <!-- اختيار سياسة الاسترجاع للمبيعات فقط -->
      <div class="flex items-center gap-3">
        <label class="form-label">سياسة الاسترجاع:</label>
        <select v-model="refundMode" class="form-input w-48" :disabled="hasOutstanding">
          <option value="auto">auto - خصم من الذمة ثم رد الفائض</option>
          <option value="cash">cash - رد نقدي كامل</option>
          <option value="credit_note">credit_note - خصم كامل من الذمة</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <label class="form-label">المبلغ المدفوع للعميل/المورد:</label>
        <input type="number" min="0" :max="totalReturnAmount" v-model.number="paidAmount" class="form-input w-32" :disabled="hasOutstanding" />
        <span class="text-xs text-gray-500">(يمكنك تعديله إذا لزم)</span>
      </div>
      <div class="flex items-center gap-2">
        <label class="form-label">طريقة الدفع:</label>
        <select v-model="paymentMethodId" class="form-input w-40" :disabled="hasOutstanding">
          <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
        </select>
      </div>

    </div>

    <!-- زر الإرسال -->
    <div v-if="selectedInvoice">
      <button @click="submitReturn" :disabled="isSubmittingReturn" class="btn-primary">تأكيد وتسجيل المرتجع</button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import apiClient from '@/config/axios';
import paymentService from '@/services/payment';
import { useToast } from '@/composables/useToast';
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { getLocalDateISO } from '@/utils/date';
import { useReturnStore } from '@/stores/return/returnStore';

const props = defineProps({
  type: { type: String, required: true }, // 'sales' or 'purchases'
});

const emit = defineEmits(['returnSuccess']);
const { showToast } = useToast();
const returnStore = useReturnStore();

// حالة
const invoiceNumber = ref('');
const selectedInvoice = ref(null);
const returnItems = ref([]);
const returnReason = ref('');
const isSubmittingReturn = ref(false);
const isFullReturn = ref(false);
const paidAmount = ref(0);
const paymentMethodId = ref(null);
const paymentMethods = ref([]);
const refundMode = ref('auto'); // auto | cash | credit_note

// جلب طرق الدفع عند التحميل
onMounted(async () => {
  try {
    const res = await paymentService.getPaymentMethods();
    if (res.data?.status === 'success') {
      paymentMethods.value = res.data.data;
      // اختر أول طريقة دفع بشكل افتراضي إذا متاح
      if (!paymentMethodId.value && paymentMethods.value.length) {
        paymentMethodId.value = paymentMethods.value[0].id;
      }
    }
  } catch (e) {
    paymentMethods.value = [];
  }
  
  // إضافة مستمعي أحداث التمرير وتغيير الحجم لتحديث موضع القائمة المنسدلة
  handleScroll = () => {
    if (scrollThrottleTimer) return;
    scrollThrottleTimer = setTimeout(() => {
      dropdownPositionUpdate.value++;
      scrollThrottleTimer = null;
    }, 16); // ~60fps
  };
  
  handleResize = () => {
    clearTimeout(resizeDebounceTimer);
    resizeDebounceTimer = setTimeout(() => {
      dropdownPositionUpdate.value++;
    }, 100);
  };
  
  window.addEventListener('scroll', handleScroll, true);
  window.addEventListener('resize', handleResize);
});

// تنظيف المستمعين عند فصل المكون
onBeforeUnmount(() => {
  if (handleScroll) window.removeEventListener('scroll', handleScroll, true);
  if (handleResize) window.removeEventListener('resize', handleResize);
  clearTimeout(scrollThrottleTimer);
  clearTimeout(resizeDebounceTimer);
});;
const discountType = ref('fixed'); // أو 'percent'
const discountValue = ref(0);
const partyId = ref(null);
const invoiceId = ref(null);
const invoiceSearchResults = ref([]);
const isSearchingInvoice = ref(false);
const showInvoiceDropdown = ref(false);
const highlightedIdx = ref(-1);
const invoiceSearchInputRef = ref(null);
const invoiceDropdownRef = ref(null);
const dropdownPositionUpdate = ref(0); // Trigger for position recalculation

// Event handler references for scroll/resize
let scrollThrottleTimer = null;
let resizeDebounceTimer = null;
let handleScroll = null;
let handleResize = null;

let debounceTimer = null;
let invoiceSearchAbortController = null;
const invoiceSearchCache = new Map();

// تطبيع النوع ليطابق ما يتوقعه الـ backend
const normalizedType = computed(() => props.type === 'sales' ? 'sale' : (props.type === 'purchases' ? 'purchase' : props.type));
const isSale = computed(() => normalizedType.value === 'sale');
const outstanding = computed(() => {
  if (!selectedInvoice.value) return 0;
  const net = Number(selectedInvoice.value.net_total_amount || 0);
  const paid = Number(selectedInvoice.value.paid_amount || 0);
  return Math.max(0, net - paid);
});
const hasOutstanding = computed(() => outstanding.value > 0);

// عند اختيار طريقة دفع آجلة → أجبر refundMode على credit_note (paidAmount سيتصفر تلقائياً)
watch(paymentMethodId, (newId) => {
  const m = paymentMethods.value.find(pm => Number(pm.id) === Number(newId));
  if (m?.kind === 'credit') refundMode.value = 'credit_note';
});

// اجعل paidAmount يتبع إجمالي المرتجع تلقائياً عند تغيير العناصر
watch([returnItems, isFullReturn, refundMode, selectedInvoice], () => {
  // اقتراح مبلغ مدفوع تلقائي حسب سياسة الاسترجاع (للمبيعات فقط)
  if (!selectedInvoice.value) { paidAmount.value = 0; return; }
  // منع أي حركة نقدية إن وُجد متبقي (مبيعات أو مشتريات)
  if (hasOutstanding.value) {
    refundMode.value = 'credit_note';
    paidAmount.value = 0;
    return;
  }
  const total = Number(totalReturnAmount.value || 0);
  const out = Number(outstanding.value || 0);
  if (refundMode.value === 'auto') {
    paidAmount.value = Math.max(0, total - out);
  } else if (refundMode.value === 'cash') {
    paidAmount.value = total;
  } else if (refundMode.value === 'credit_note') {
    paidAmount.value = 0;
  }
});

// مفاتيح حسب النوع
const quantityKey = computed(() => 'quantity');
const priceKey = computed(() => props.type === 'sales' ? 'net_price' : 'unit_price');
const displayPriceKey = priceKey;
const placeholderText = computed(() => props.type === 'sales' ? 'أدخل رقم فاتورة المبيعات أو ID...' : 'أدخل رقم فاتورة المشتريات أو ID...');

function formatPrice(amount) {
  if (!amount) return '0';
  return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function formatDateShort(date) {
  if (!date) return '';
  const d = new Date(date);
  return d.toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function paymentStatusText(status) {
  if (status === 'cash') return 'نقدي';
  if (status === 'credit') return 'آجل';
  if (status === 'partial') return 'مدفوع جزئيًا';
  return status || 'غير معروف';
}
function statusBadgeClass(status) {
  if (status === 'cash') return 'bg-green-600';
  if (status === 'credit') return 'bg-gray-600';
  if (status === 'partial') return 'bg-yellow-600';
  return 'bg-slate-500';
}

const totalReturnAmount = computed(() =>
  returnItems.value.reduce((total, item) => total + (item[displayPriceKey.value] * item.returnQuantity), 0)
);

function debouncedSearchInvoice() {
  clearTimeout(debounceTimer);
  highlightedIdx.value = -1;
  
  const query = invoiceNumber.value.trim();
  if (!query) {
    invoiceSearchResults.value = [];
    return;
  }
  
  // تحقق من Cache
  const cacheKey = `${normalizedType.value}:${query}`;
  if (invoiceSearchCache.has(cacheKey)) {
    invoiceSearchResults.value = invoiceSearchCache.get(cacheKey);
    return;
  }
  
  isSearchingInvoice.value = true;
  debounceTimer = setTimeout(async () => {
    try {
      if (invoiceSearchAbortController) invoiceSearchAbortController.abort();
      invoiceSearchAbortController = new AbortController();
      
      const res = await apiClient.get('/returns/invoices', {
        params: { type: normalizedType.value, q: query },
        signal: invoiceSearchAbortController.signal
      });
      
      const data = res.data?.data || [];
      invoiceSearchResults.value = data;
      invoiceSearchCache.set(cacheKey, data);
    } catch (err) {
      if (err.name !== 'CanceledError') {
        invoiceSearchResults.value = [];
      }
    } finally {
      isSearchingInvoice.value = false;
    }
  }, 400);
}

function handleInvoiceBlur() {
  setTimeout(() => {
    showInvoiceDropdown.value = false;
  }, 200);
}

function selectNextResult() {
  if (invoiceSearchResults.value.length === 0) return;
  highlightedIdx.value = (highlightedIdx.value + 1) % invoiceSearchResults.value.length;
}

function selectPrevResult() {
  if (invoiceSearchResults.value.length === 0) return;
  highlightedIdx.value = highlightedIdx.value <= 0 ? invoiceSearchResults.value.length - 1 : highlightedIdx.value - 1;
}

function selectHighlightedResult() {
  if (highlightedIdx.value >= 0 && highlightedIdx.value < invoiceSearchResults.value.length) {
    selectInvoice(invoiceSearchResults.value[highlightedIdx.value]);
  }
}

function getStatusBadgeClass(status) {
  if (status === 'cash') return 'bg-green-100 text-green-800';
  if (status === 'credit') return 'bg-gray-100 text-gray-800';
  if (status === 'partial') return 'bg-yellow-100 text-yellow-800';
  return 'bg-slate-100 text-slate-800';
}

const invoiceDropdownPosition = computed(() => {
  // Access dropdownPositionUpdate to create dependency for reactivity
  dropdownPositionUpdate.value;
  
  if (!invoiceSearchInputRef.value) {
    return { top: '0', left: '0', width: '0' };
  }
  
  const rect = invoiceSearchInputRef.value.getBoundingClientRect();
  const inputWidth = rect.width;
  
  return {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${inputWidth}px`
  };
});

async function selectInvoice(invoice) {
  // تعيين طريقة الدفع الافتراضية من الفاتورة أو أول خيار
  if (invoice.payment_method_id) {
    paymentMethodId.value = invoice.payment_method_id;
  } else {
    paymentMethodId.value = paymentMethods.value.length ? paymentMethods.value[0].id : null;
  }
  isSearchingInvoice.value = true;
  try {
    let items = invoice.items;
    if (!items) {
      if (props.type === 'sales') {
        const itemsRes = await apiClient.get(`/sales/${invoice.id}`);
        items = itemsRes.data?.data?.items || [];
      } else {
        const itemsRes = await apiClient.get('/returns/invoice-items', { params: { type: 'purchase', invoice_id: invoice.id } });
        items = itemsRes.data?.data?.items || [];
      }
    }
    // دمج بيانات المرتجعات السابقة والمتبقي
    let summaryMap = {};
    try {
      const sumRes = await apiClient.get('/returns/returned-qty', { params: { type: normalizedType.value, invoice_id: invoice.id } });
      const rows = sumRes.data?.data || [];
      summaryMap = rows.reduce((acc, r) => {
        acc[r.product_id] = {
          original_qty: Number(r.original_qty || 0),
          returned_qty: Number(r.returned_qty || 0),
          remaining_qty: Number(r.remaining_qty || 0)
        };
        return acc;
      }, {});
    } catch (e) {
      summaryMap = {};
    }
    selectedInvoice.value = {
      ...invoice,
      items: items.map(item => {
        const pid = item.product_id;
        const s = summaryMap[pid] || null;
        const remaining = s ? s.remaining_qty : Number(item[quantityKey.value] || 0);
        const prev = s ? s.returned_qty : 0;
        return {
          ...item,
          prevReturned: prev,
          remainingQty: remaining,
          returnQuantity: 0
        };
      })
    };
    returnItems.value = [];
    invoiceNumber.value = invoice.invoice_number;
    invoiceSearchResults.value = [];
    isFullReturn.value = false;
    paidAmount.value = 0;
    refundMode.value = 'auto';
    // تعيين رقم الفاتورة للطباعة
    invoiceId.value = invoice.id;
    // تعيين الطرف (عميل أو مورد) بناءً على نوع الفاتورة
    if (invoice.customer_id) partyId.value = invoice.customer_id;
    else if (invoice.supplier_id) partyId.value = invoice.supplier_id;
    else partyId.value = null;
  } catch (error) {
    selectedInvoice.value = null;
    showToast('تعذر جلب تفاصيل الفاتورة', 'error');
  } finally {
    isSearchingInvoice.value = false;
  }
}

function setFullReturn() {
  if (selectedInvoice.value && selectedInvoice.value.items) {
    selectedInvoice.value.items.forEach(item => {
      const maxQty = Number(item.remainingQty ?? item[quantityKey.value] ?? 0);
      item.returnQuantity = isFullReturn.value ? maxQty : 0;
    });
    returnItems.value = isFullReturn.value
      ? selectedInvoice.value.items
          .filter(item => !isNaN(Number(item[priceKey.value])) && Number(item[priceKey.value]) > 0)
          .map(item => ({ ...item, [priceKey.value]: Number(item[priceKey.value]) }))
      : [];
    if (isFullReturn.value && returnItems.value.length === 0) {
      showToast('لا يوجد منتجات لها سعر صالح في هذه الفاتورة!', 'error');
    }
  }
}

function addToReturnList(item) {
  if (!item.returnQuantity || item.returnQuantity < 1) return showToast('يرجى تحديد كمية صالحة', 'warning');
  const maxQty = Number(item.remainingQty ?? item[quantityKey.value] ?? 0);
  if (item.returnQuantity > maxQty) return showToast(`الكمية المتاحة هي ${maxQty} فقط`, 'warning');
  if (maxQty <= 0) return showToast('لا يوجد رصيد متبقٍ للإرجاع لهذا المنتج', 'warning');
  const existingItem = returnItems.value.find(i => i.id === item.id);
  if (existingItem) {
    existingItem.returnQuantity = item.returnQuantity;
    existingItem[priceKey.value] = Number(item[priceKey.value]);
  } else {
    returnItems.value.push({ ...item, [priceKey.value]: Number(item[priceKey.value]) });
  }
  showToast('تمت الإضافة إلى قائمة الإرجاع', 'success');
}

async function submitReturn() {
  if (returnItems.value.length === 0) return showToast('قائمة الإرجاع فارغة!', 'warning');
  if (!returnReason.value.trim()) return showToast('يرجى كتابة سبب الإرجاع', 'warning');

  if (!paymentMethodId.value) return showToast('يرجى اختيار طريقة الدفع', 'warning');
  const selectedPM = paymentMethods.value.find(pm => Number(pm.id) === Number(paymentMethodId.value));
  if (selectedPM?.kind === 'credit' && Number(paidAmount.value) > 0) {
    return showToast('طريقة الدفع الآجلة لا تقبل مبلغاً مسترداً — استخدم credit_note أو اختر طريقة دفع أخرى', 'error');
  }
  if (!invoiceId.value) return showToast('رقم الفاتورة غير محدد', 'warning');
  // Removed: if (!partyId.value) return showToast('العميل/المورد غير محدد', 'warning');

  // تحقق من الحقول داخل كل عنصر
  for (const [index, item] of returnItems.value.entries()) {
    const requiredFields = ['product_id', 'unit_id', 'returnQuantity', priceKey.value];
    for (const field of requiredFields) {
      if (!item[field] && item[field] !== 0) {
        return showToast(`العنصر رقم ${index + 1} ناقص فيه الحقل: ${field}`, 'error');
      }
    }
  }

  isSubmittingReturn.value = true;
  try {
    const user = JSON.parse(localStorage.getItem('user')) || {};
  const today = new Date();
  const formattedDate = getLocalDateISO(today); // YYYY-MM-DD (local)
    const returnData = {
      return_type: normalizedType.value,
      return_date: formattedDate,
      invoice_id: invoiceId.value,
      party_id: partyId.value,
      items: returnItems.value.map(item => ({
        product_id: item.product_id,
        unit_id: item.unit_id,
        quantity: item.returnQuantity,
        unit_price: item[priceKey.value],
        subtotal: item[priceKey.value] * item.returnQuantity
      })),
      paid_amount: paidAmount.value,
      payment_method_id: paymentMethodId.value,
      refund_mode: isSale.value ? refundMode.value : undefined,

      notes: returnReason.value,
      cashier_id: user.id,
      branch_id: user.branch_id
    };
    const res = await returnStore.createReturn(returnData);
    if (res.status !== 'success') throw new Error(res.message || 'فشل تسجيل المرتجع');
    const result = res.data || {};
    
    // أبلغ الواجهة عن تسجيل مرتجع بنجاح لتحديث درج النقدية (إن كان نقديًا)
    try {
      const detail = {
        returnId: result.id || Date.now(),
        return_type: returnData.return_type,
        saleId: invoiceId.value,
        customerId: partyId.value,
        totalRefunded: Number(returnData.paid_amount || 0),
        payment_method_id: Number(returnData.payment_method_id),
        paid_amount: Number(returnData.paid_amount || 0)
      };
      window.dispatchEvent(new CustomEvent('pos:return-recorded', { detail }));
    } catch (_) { /* ignore */ }
    showToast('تم تسجيل المرتجع بنجاح', 'success');
    
    // ✅ إرسال البيانات الحقيقية إلى الـ parent
    emit('returnSuccess', {
      returnId: result.id || Date.now(),
      saleId: invoiceId.value,
      customerId: partyId.value,
      totalRefunded: Number(returnData.paid_amount || 0),
      paymentMethodId: paymentMethodId.value
    });
    // إعادة تعيين النموذج
    selectedInvoice.value = null;
    returnItems.value = [];
    returnReason.value = '';
    paidAmount.value = 0;
    paymentMethodId.value = paymentMethods.value[0]?.id || null;
    discountType.value = 'fixed';
    discountValue.value = 0;
    partyId.value = null;
    invoiceId.value = null;
  } catch (error) {
    showToast(error?.response?.data?.message || 'حدث خطأ أثناء تسجيل المرتجع', 'error');
  } finally {
    isSubmittingReturn.value = false;
  }
}
</script>

<style scoped>
.form-label { @apply block mb-1 font-bold text-gray-700; }
.form-input { @apply block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-all; }
.btn-primary { @apply inline-flex items-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all; }
.table-header { @apply px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider; }
.table-cell { @apply px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-center; }

/* Invoice Dropdown Animations */
.dropdown-invoice-enter-active {
  animation: dropdownInvoiceIn 0.2s ease-out;
}

.dropdown-invoice-leave-active {
  animation: dropdownInvoiceOut 0.15s ease-in;
}

@keyframes dropdownInvoiceIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes dropdownInvoiceOut {
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateY(-8px);
  }
}
</style>
