<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center shadow-xl shadow-amber-100 text-white shrink-0">
          <i class="fas fa-clipboard-check text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">فواتير بانتظار الموافقة</h1>
          <div class="flex items-center gap-3 mt-2">
            <span class="flex items-center gap-1.5 text-[11px] font-bold" :class="isPolling ? 'text-emerald-600' : 'text-slate-400'">
              <span class="relative flex h-2 w-2">
                <span v-if="isPolling" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2" :class="isPolling ? 'bg-emerald-500' : 'bg-slate-300'"></span>
              </span>
              {{ isPolling ? 'مزامنة' : 'متوقف' }}
            </span>
            <span v-if="lastUpdatedLabel" class="text-[10px] text-slate-400 font-medium">آخر تحديث: {{ lastUpdatedLabel }}</span>
          </div>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <!-- New invoice alert badge -->
        <transition name="fade-scale">
          <div v-if="newInvoicesAlert" class="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-amber-500 text-white text-xs font-black shadow-lg shadow-amber-200 animate-bounce">
            <i class="fas fa-bell"></i>
            فاتورة جديدة!
          </div>
        </transition>
        <div class="kpi-mini-card border-r-4 border-r-amber-500 bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-100">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">إجمالي الطلبات المعلقة</p>
          <div class="flex items-center gap-2">
            <span class="text-2xl font-black text-slate-800 leading-none">{{ total }}</span>
            <i class="fas fa-hourglass-half text-amber-500 text-xs animate-pulse"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      
      <!-- Toolbar -->
      <div class="p-6 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
        <div class="flex items-center gap-3">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">عرض النتائج:</label>
          <select v-model.number="limit" class="h-10 text-xs font-black border-slate-200 rounded-xl bg-white px-4 outline-none focus:ring-4 focus:ring-blue-50 transition-all border">
            <option :value="10">10 طلبات</option>
            <option :value="20">20 طلب</option>
            <option :value="50">50 طلب</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[10px] text-slate-400 font-bold hidden md:block">تحديث تلقائي كل {{ POLL_INTERVAL / 1000 }}ث</span>
          <button @click="manualRefresh" :disabled="isLoading" class="h-10 px-6 rounded-xl bg-white border border-slate-200 text-slate-500 font-black text-[10px] uppercase hover:text-blue-600 hover:border-blue-100 transition-all active:scale-95 flex items-center gap-2">
            <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i>
            تحديث يدوي
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-6 py-5 w-20">#</th>
              <th class="px-4 py-5">التاريخ والوقت</th>
              <th class="px-4 py-5">العميل</th>
              <th class="px-4 py-5 text-center">الأصناف</th>
              <th class="px-4 py-5">المبلغ الإجمالي</th>
              <th class="px-4 py-5 text-center">نوع الدفع</th>
              <th class="px-8 py-5 text-center">الإجراءات والقرارات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 6" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!rows.length" class="text-center py-20">
              <td colspan="7" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-check-double text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase tracking-widest">لا توجد فواتير بانتظار الموافقة حالياً</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="s in rows" :key="s.id" class="hover:bg-blue-50/30 transition-all group font-bold">
              <td class="px-6 py-4 font-black text-slate-800">#{{ s.id }}</td>
              <td class="px-4 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ formatDateTime(s.created_at || s.sale?.created_at) }}</td>
              <td class="px-4 py-4 text-slate-700 leading-none">{{ s.customer_name || s.sale?.customer_name || 'عميل نقدي' }}</td>
              <td class="px-4 py-4 text-center">
                <span class="bg-slate-100 text-slate-500 px-2.5 py-1 rounded-lg text-[10px] font-black">{{ s.total_items ?? s.items_count ?? s.sale?.items?.length ?? '-' }}</span>
              </td>
              <td class="px-4 py-4 font-black text-blue-600 text-base leading-none">{{ formatPrice(s.net_total_amount ?? s.total_amount ?? s.sale?.net_total_amount) }}</td>
              <td class="px-4 py-4 text-center">
                <span :class="['status-badge', kindClass(s.payment_method_kind ?? s.sale?.payment_method_kind ?? s.payment_method?.kind)]">
                  {{ kindLabel(s.payment_method_kind ?? s.sale?.payment_method_kind ?? s.payment_method?.kind) }}
                </span>
              </td>
              <td class="px-8 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="viewDetails(s.id)" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="عرض التفاصيل">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <button @click="openAction('approve', s.id)" class="h-9 px-4 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm active:scale-95 text-[10px] font-black uppercase">
                    <i class="fas fa-check ml-2 text-[8px]"></i> اعتماد
                  </button>
                  <button @click="openAction('reject', s.id)" class="h-9 px-4 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95 text-[10px] font-black uppercase">
                    <i class="fas fa-times ml-2 text-[8px]"></i> رفض
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">
          عرض الصفحة <span class="text-slate-800">{{ page }}</span> من إجمالي <span class="text-slate-800">{{ totalPages }}</span>
        </div>
        
        <div class="flex items-center gap-1">
          <button @click="page = Math.max(1, page - 1)" :disabled="page <= 1" class="pagination-btn">
            <i class="fas fa-angle-right"></i>
          </button>
          <div class="px-6 h-10 bg-white border border-slate-200 rounded-xl flex items-center text-xs font-black shadow-sm">
            {{ page }} / {{ totalPages }}
          </div>
          <button @click="page = Math.min(totalPages, page + 1)" :disabled="page >= totalPages" class="pagination-btn">
            <i class="fas fa-angle-left"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Approve/Reject Action Modal -->
    <transition name="modal">
      <div v-if="actionModal.open" class="modal-overlay">
        <div class="modal-content-modern animate-modalIn max-w-lg border border-white">
          <div class="p-8 text-center border-b border-slate-50">
            <div :class="[actionModal.mode === 'approve' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500']" class="w-16 h-16 rounded-3xl flex items-center justify-center mx-auto mb-4">
              <i :class="[actionModal.mode === 'approve' ? 'fas fa-check-double' : 'fas fa-ban', 'text-2xl']"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 leading-none">
              {{ actionModal.mode === 'approve' ? 'اعتماد وتحصيل الفاتورة' : 'رفض طلب الفاتورة' }}
            </h3>
            <p class="text-slate-400 text-[10px] mt-2 font-bold uppercase tracking-widest">فاتورة رقم: #{{ actionModal.id }} — الإجمالي: {{ formatPrice(actionModal.invoiceTotal) }}</p>
          </div>

          <!-- Approve-only: payment collection section -->
          <div v-if="actionModal.mode === 'approve'" class="px-8 pt-6 pb-2 space-y-4">
            <div v-if="actionModal.isCredit" class="p-4 rounded-2xl bg-sky-50 border border-sky-100 text-sky-700 text-[11px] font-bold flex items-center gap-2">
              <i class="fas fa-clock"></i>
              هذه فاتورة آجل — لن يتم تحصيل دفعة الآن. يمكنك تعديل المبلغ المستلم إذا دفع العميل جزءاً
            </div>
            <div v-else class="p-4 rounded-2xl bg-amber-50 border border-amber-100 text-amber-700 text-[11px] font-bold flex items-center gap-2">
              <i class="fas fa-cash-register"></i>
              قم باختيار طريقة الدفع وإدخال المبلغ المستلم فعلياً من العميل
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1.5">
                <label class="modal-label">طريقة الدفع <span v-if="actionModal.paidAmount > 0" class="text-red-400">*</span></label>
                <div class="relative">
                  <select v-model="actionModal.paymentMethodId" class="w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-10 outline-none focus:ring-4 focus:ring-blue-50 text-xs font-bold transition-all">
                    <option value="">-- اختر --</option>
                    <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                  </select>
                  <i class="fas fa-wallet absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="modal-label">المبلغ المستلم <span class="text-red-400">*</span></label>
                <input v-model.number="actionModal.paidAmount" type="number" min="0" step="0.01" class="w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-50 text-sm font-black text-left transition-all" />
              </div>
            </div>
            <div v-if="actionModal.paidAmount > 0 || !actionModal.isCredit" class="grid grid-cols-3 gap-3 text-center text-xs">
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="text-[9px] font-black text-slate-400 uppercase mb-1">إجمالي الفاتورة</div>
                <div class="font-black text-slate-800">{{ formatPrice(actionModal.invoiceTotal) }}</div>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="text-[9px] font-black text-slate-400 uppercase mb-1">المستلم</div>
                <div class="font-black text-blue-600">{{ formatPrice(actionModal.paidAmount || 0) }}</div>
              </div>
              <div :class="[changeAmount >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100']" class="p-3 rounded-xl border">
                <div class="text-[9px] font-black text-slate-400 uppercase mb-1">{{ changeAmount > 0 ? 'الباقي للعميل' : changeAmount < 0 ? 'مبلغ ناقص' : 'مسدَّد كاملاً' }}</div>
                <div :class="[changeAmount >= 0 ? 'text-emerald-700' : 'text-rose-600']" class="font-black">{{ changeAmount === 0 ? '—' : formatPrice(Math.abs(changeAmount)) }}</div>
              </div>
            </div>
          </div>

          <div class="px-8 py-4 space-y-4">
            <div class="space-y-1.5">
              <label class="modal-label">ملاحظات (اختياري)</label>
              <textarea v-model="actionModal.note" rows="2" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white transition-all outline-none focus:ring-4 focus:ring-blue-50" :placeholder="actionModal.mode === 'approve' ? 'ملاحظة للاعتماد...' : 'سبب الرفض...'"></textarea>
            </div>
          </div>

          <div class="px-8 pb-8 flex gap-4">
            <button @click="closeAction" class="flex-1 py-4 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all text-xs">تراجع</button>
            <button @click="submitAction" :disabled="isSubmitting || (actionModal.mode === 'approve' && actionModal.paidAmount > 0 && !actionModal.paymentMethodId)" :class="[actionModal.mode === 'approve' ? 'bg-emerald-600 shadow-emerald-100 hover:bg-emerald-700' : 'bg-rose-600 shadow-rose-100 hover:bg-rose-700']" class="flex-[2] py-4 rounded-2xl text-white font-black shadow-xl transition-all active:scale-95 flex items-center justify-center gap-3 text-xs disabled:opacity-50 disabled:cursor-not-allowed">
              <BaseSpinner v-if="isSubmitting" :size="16" color="#fff" />
              <i v-else :class="actionModal.mode === 'approve' ? 'fas fa-check ml-2' : 'fas fa-ban ml-2'"></i>
              <span>{{ actionModal.mode === 'approve' ? 'تأكيد الاعتماد والتحصيل' : 'تأكيد الرفض' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Post-Approval Print Modal -->
    <transition name="modal">
      <div v-if="printModal.open" class="modal-overlay">
        <div class="modal-content-modern animate-modalIn max-w-sm border border-white">
          <div class="p-8 text-center">
            <div class="w-16 h-16 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-check-circle text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">تم الاعتماد بنجاح!</h3>
            <p class="text-slate-400 text-xs mt-2">فاتورة #{{ printModal.saleId }} — {{ formatPrice(printModal.invoiceTotal) }}</p>
            <div v-if="printModal.change > 0" class="mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 font-black text-sm">
              <i class="fas fa-coins ml-1"></i> الباقي للعميل: {{ formatPrice(printModal.change) }}
            </div>
          </div>
          <div class="px-8 pb-8 flex gap-3">
            <button @click="printModal.open = false" class="flex-1 py-3 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-slate-50 transition-all text-xs">إغلاق</button>
            <button @click="printApprovedSale" class="flex-[2] py-3 rounded-2xl bg-blue-600 text-white font-black text-xs hover:bg-blue-700 transition-all active:scale-95 flex items-center justify-center gap-2">
              <i class="fas fa-print"></i> طباعة الفاتورة
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Details Modal (Professional Report Style) -->
    <transition name="modal">
      <div v-if="showDetails && details" class="modal-overlay">
        <div class="modal-content-modern max-w-4xl animate-modalIn border border-white">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center shadow-lg"><i class="fas fa-file-invoice-dollar text-xl"></i></div>
              <h3 class="text-xl font-black text-slate-800 leading-none">مراجعة تفاصيل الفاتورة #{{ details?.sale?.id ?? details?.id }}</h3>
            </div>
            <button @click="showDetails=false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>
          
          <div class="p-8 overflow-y-auto custom-scroll max-h-[75vh] space-y-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-1">العميل</p><p class="text-xs font-black text-slate-800 leading-none truncate">{{ details?.sale?.customer_name ?? details?.customer_name ?? 'عميل نقدي' }}</p></div>
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-1">تاريخ الطلب</p><p class="text-xs font-black text-slate-800 leading-none font-mono uppercase tracking-tighter">{{ formatDateTime(details?.sale?.created_at ?? details?.created_at) }}</p></div>
               <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100"><p class="text-[9px] font-black text-slate-400 uppercase mb-1">طريقة الدفع</p><p class="text-xs font-black text-slate-800 leading-none">{{ details?.sale?.payment_method_name ?? details?.payment_method_name ?? '-' }}</p></div>
               <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 text-white"><p class="text-[9px] font-black text-blue-400 uppercase mb-1">صافي الفاتورة</p><p class="text-lg font-black leading-none tracking-tighter">{{ formatPrice(details?.sale?.net_total_amount ?? details?.net_total_amount) }}</p></div>
            </div>

            <div class="space-y-4">
              <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest px-2 flex items-center gap-2"><i class="fas fa-list-ul text-blue-500"></i> قائمة الأصناف المختارة</h4>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-100">
                      <th class="px-6 py-4">اسم المنتج</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-4 py-4 text-center">سعر الوحدة</th>
                      <th class="px-6 py-4 text-left">الإجمالي</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-for="it in (details?.items || details?.sale?.items || [])" :key="it.id" class="hover:bg-slate-50/50 transition-all">
                      <td class="px-6 py-4 text-slate-800">{{ it.name || it.product_name }}</td>
                      <td class="px-4 py-4 text-center"><span class="bg-slate-100 px-2 py-0.5 rounded-lg text-slate-600">{{ it.quantity ?? it.qty }}</span></td>
                      <td class="px-4 py-4 text-center text-slate-500">{{ formatPrice(itemPrice(it)) }}</td>
                      <td class="px-6 py-4 text-left font-black text-slate-900">{{ formatPrice(itemTotal(it)) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Totals & Discount Summary -->
              <div class="flex justify-end">
                <div class="w-full max-w-sm space-y-2 text-xs font-bold">
                  <div class="flex justify-between px-4 py-2 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-400">المجموع قبل الخصم</span>
                    <span class="text-slate-700">{{ formatPrice(details?.total_amount ?? details?.sale?.total_amount) }}</span>
                  </div>
                  <div v-if="Number(details?.discount_value ?? details?.sale?.discount_value)" class="flex justify-between px-4 py-2 rounded-xl bg-rose-50 border border-rose-100 text-rose-700">
                    <span>
                      الخصم
                      <span v-if="(details?.discount_type ?? details?.sale?.discount_type) === 'percentage'" class="text-[10px] font-black ml-1">({{ details?.discount_value ?? details?.sale?.discount_value }}%)</span>
                    </span>
                    <span>— {{ formatPrice(details?.discount_value ?? details?.sale?.discount_value) }}</span>
                  </div>
                  <div v-if="Number(details?.tax_amount ?? details?.sale?.tax_amount)" class="flex justify-between px-4 py-2 rounded-xl bg-amber-50 border border-amber-100 text-amber-700">
                    <span>الضريبة</span>
                    <span>+ {{ formatPrice(details?.tax_amount ?? details?.sale?.tax_amount) }}</span>
                  </div>
                  <div class="flex justify-between px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-white">
                    <span class="font-black">صافي الفاتورة</span>
                    <span class="font-black text-blue-300">{{ formatPrice(details?.net_total_amount ?? details?.sale?.net_total_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0">
             <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">بانتظار القرار الإداري</span></div>
             <button @click="showDetails=false" class="px-8 py-3 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95">إتمام المراجعة</button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useApprovalsStore } from '@/stores/approvals/approvalsStore'
import { useToast } from '@/composables/useToast'
import { useLoader } from '@/composables/useLoader'
import { useCompanyCurrency } from '@/composables/useCompanyCurrency'
import { useSalesStore } from '@/stores/sales/salesStore'
import { usePaymentStore } from '@/stores/payment/paymentStore'
import { useProductStore } from '@/stores/product/productStore'
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';

// --- Logic Initialization (STRICTLY PRESERVED) ---
const { showToast } = useToast()
const { showLoader, hideLoader } = useLoader()
const { formatCurrencyLocale, fetchSettings } = useCompanyCurrency()
const salesStore = useSalesStore()
const paymentStore = usePaymentStore()
const productStore = useProductStore()
const paymentMethods = computed(() => paymentStore.paymentMethods || [])

const rows = ref([])
const page = ref(1)
const limit = ref(20)
const total = ref(0)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / limit.value)))
const isLoading = ref(false)
let listAbortCtrl = null

// ─── Real-time polling ─────────────────────────────────────────────
const POLL_INTERVAL = 15000
let pollTimer = null
let pollingBusy = false
const isPolling = ref(false)
const lastUpdatedAt = ref(null)
const newInvoicesAlert = ref(false)
let newInvoicesAlertTimer = null
let knownIds = new Set()
let lastSoundAt = 0

const lastUpdatedLabel = computed(() => {
  if (!lastUpdatedAt.value) return ''
  const now = new Date()
  const diff = Math.floor((now - lastUpdatedAt.value) / 1000)
  if (diff < 5) return 'الآن'
  if (diff < 60) return `منذ ${diff} ث`
  return lastUpdatedAt.value.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
})

const showDetails = ref(false)
const details = ref(null)
const isLoadingDetails = ref(false)

const actionModal = ref({ open: false, mode: 'approve', id: null, note: '', paymentMethodId: '', paidAmount: 0, invoiceTotal: 0 })
const isSubmitting = ref(false)
const printModal = ref({ open: false, saleId: null, invoiceTotal: 0, change: 0 })

const changeAmount = computed(() => (actionModal.value.paidAmount || 0) - (actionModal.value.invoiceTotal || 0))

const formatPrice = (amount) => formatCurrencyLocale(amount, 2)
const formatDateTime = (val) => {
  if (!val) return ''
  try {
    return new Date(val).toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
  } catch { return String(val) }
}

const itemPrice = (it) => { const v = it?.price ?? it?.sale_price ?? it?.unit_price; return Number(v ?? 0); }
const itemQty = (it) => Number((it?.quantity ?? it?.qty ?? 1))
const itemTotal = (it) => {
  const v = it?.total ?? it?.net_total
  if (v != null) return Number(v)
  return itemPrice(it) * itemQty(it)
}

const kindLabel = (k) => {
  const v = String(k || '').toLowerCase()
  switch (v) {
    case 'cash': return 'نقدي';
    case 'bank': return 'بنكي';
    case 'card': return 'بطاقة';
    case 'credit': return 'آجل';
    default: return 'أخرى';
  }
}
const kindClass = (k) => {
  const v = String(k || '').toLowerCase()
  if (v === 'cash') return 'bg-emerald-100 text-emerald-700';
  if (v === 'credit') return 'bg-amber-100 text-amber-700';
  if (v === 'bank' || v === 'card') return 'bg-sky-100 text-sky-700';
  return 'bg-slate-100 text-slate-500';
}

const fetchPending = async ({ silent = false } = {}) => {
  if (listAbortCtrl) listAbortCtrl.abort()
  listAbortCtrl = new AbortController()
  if (!silent) { isLoading.value = true; showLoader(); }
  try {
    const approvalsStore = useApprovalsStore();
    approvalsStore.clearCache()
    const res = await approvalsStore.listPending({ page: page.value, limit: limit.value })
    const newRows = res.data || []
    const newTotal = Number(res.pagination?.total || newRows.length)

    // Detect new invoices by ID comparison (more accurate than count)
    const newIds = newRows.map(r => r.id)
    const hasNew = silent && knownIds.size > 0 && newIds.some(id => !knownIds.has(id))
    knownIds = new Set(newIds)

    if (hasNew) {
      newInvoicesAlert.value = true
      playAlertSound()
      clearTimeout(newInvoicesAlertTimer)
      newInvoicesAlertTimer = setTimeout(() => { newInvoicesAlert.value = false }, 5000)
    }
    rows.value = newRows
    total.value = newTotal
    lastUpdatedAt.value = new Date()
  } catch (e) {
    if (e.name !== 'CanceledError' && !silent)
      showToast(e?.response?.data?.message || 'فشل تحميل القائمة', 'error')
  } finally {
    if (!silent) { isLoading.value = false; hideLoader(); }
  }
}

const manualRefresh = () => {
  newInvoicesAlert.value = false
  fetchPending({ silent: false })
}

const playAlertSound = () => {
  const now = Date.now()
  if (now - lastSoundAt < 10000) return
  lastSoundAt = now
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain); gain.connect(ctx.destination)
    osc.frequency.value = 880
    osc.type = 'sine'
    gain.gain.setValueAtTime(0.3, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4)
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4)
  } catch {}
}

const pollOnce = async () => {
  if (pollingBusy) return
  pollingBusy = true
  try {
    await fetchPending({ silent: true })
  } finally {
    pollingBusy = false
  }
}

const startPolling = () => {
  if (pollTimer) return
  isPolling.value = true
  pollTimer = setInterval(() => {
    if (document.hidden || actionModal.value.open || printModal.value.open) return
    pollOnce()
  }, POLL_INTERVAL)
}

const stopPolling = () => {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
  isPolling.value = false
}

const handleVisibilityChange = () => {
  if (document.hidden) {
    stopPolling()
  } else {
    fetchPending({ silent: true })
    startPolling()
  }
}

const openAction = (mode, id) => {
  const row = rows.value.find(r => r.id === id)
  const invoiceTotal = Number(row?.net_total_amount ?? row?.total_amount ?? 0)
  const kind = String(row?.payment_method_kind ?? row?.sale?.payment_method_kind ?? row?.payment_method?.kind ?? '').toLowerCase()
  const isCredit = kind === 'credit'
  const defaultMethodId = isCredit ? '' : (row?.payment_method_id || (paymentMethods.value[0]?.id ?? ''))
  const defaultPaid    = isCredit ? 0 : invoiceTotal
  actionModal.value = { open: true, mode, id, note: '', paymentMethodId: defaultMethodId, paidAmount: defaultPaid, invoiceTotal, isCredit }
}
const closeAction = () => { actionModal.value.open = false; }

const submitAction = async () => {
  if (!actionModal.value.open || !actionModal.value.id || isSubmitting.value) return
  isSubmitting.value = true; showLoader();
  try {
    const approvalsStore = useApprovalsStore();
    if (actionModal.value.mode === 'approve') {
      const override = {
        payment_method_id: actionModal.value.paymentMethodId || undefined,
        paid_amount: actionModal.value.paidAmount ?? undefined
      }
      const res = await approvalsStore.approve(actionModal.value.id, actionModal.value.note, override)
      if (res.status === 'error') { showToast(res.message || 'فشل التنفيذ', 'error'); return }
      const saleId = actionModal.value.id
      const invoiceTotal = actionModal.value.invoiceTotal
      const change = (actionModal.value.paidAmount || 0) - invoiceTotal
      closeAction()
      await fetchPending()
      // ✅ تحديث cache المنتجات في POS لأن الاعتماد يخصم من المخزون
      productStore.invalidateCache()
      printModal.value = { open: true, saleId, invoiceTotal, change: Math.max(0, change) }
    } else {
      const res = await approvalsStore.reject(actionModal.value.id, actionModal.value.note)
      if (res.status === 'error') { showToast(res.message || 'فشل التنفيذ', 'error'); return }
      showToast('تم رفض الفاتورة', 'success')
      closeAction(); await fetchPending();
    }
  } catch (e) { showToast(e?.response?.data?.message || 'فشل التنفيذ', 'error') } 
  finally { hideLoader(); isSubmitting.value = false; }
}

const printApprovedSale = async () => {
  printModal.value.open = false
  try {
    const res = await salesStore.fetchSaleDetails(printModal.value.saleId)
    const saleData = res?.data ?? res
    if (saleData) { details.value = saleData; showDetails.value = true; }
  } catch { showToast('فشل تحميل تفاصيل الفاتورة للطباعة', 'error') }
}

const viewDetails = async (id) => {
  isLoadingDetails.value = true; showLoader();
  try {
    const res = await salesStore.fetchSaleDetails(id, { force: true });
    const saleData = res?.data ?? res
    if (saleData) { details.value = saleData; showDetails.value = true; }
    else showToast('لم يتم استرجاع بيانات الفاتورة', 'error')
  } catch { showToast('فشل تحميل التفاصيل', 'error') } 
  finally { isLoadingDetails.value = false; hideLoader(); }
}

onMounted(() => {
  fetchSettings()
  fetchPending()
  paymentStore.fetchPaymentMethods()
  startPolling()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})
onUnmounted(() => {
  stopPolling()
  clearTimeout(newInvoicesAlertTimer)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  if (listAbortCtrl) listAbortCtrl.abort()
})
watch(page, () => fetchPending())
watch(limit, () => { page.value = 1; fetchPending() })
</script>

<style scoped>



.status-badge { @apply px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm; }
.pagination-btn { @apply w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold; }

.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]; }

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.fade-scale-enter-active, .fade-scale-leave-active { transition: all 0.3s ease; }
.fade-scale-enter-from, .fade-scale-leave-to { opacity: 0; transform: scale(0.8); }
</style>