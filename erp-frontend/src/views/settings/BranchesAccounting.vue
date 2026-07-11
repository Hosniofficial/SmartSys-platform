<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-network-wired text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">ربط حسابات المخازن</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تخصيص الحسابات المالية لكل مستودع لضمان دقة القيود المحاسبية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <label class="toggle-option group bg-white px-5 py-2.5 rounded-xl border border-slate-100 shadow-sm transition-all hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" v-model="showMissingOnly" class="sr-only peer" />
          <div class="toggle-box peer-checked:bg-amber-500 peer-checked:border-amber-500"></div>
          <span class="text-xs font-black text-slate-500 group-hover:text-slate-800 transition-colors uppercase tracking-tight">إظهار غير المرتبط فقط</span>
        </label>
      </div>
    </div>

    <!-- Mapping Statistics Overview -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600">
            <i class="fas fa-warehouse"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الفروع</p>
            <p class="kpi-value text-slate-800">{{ branches.length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600">
            <i class="fas fa-link"></i>
          </div>
          <div>
            <p class="kpi-label">مستودعات مرتبطة</p>
            <p class="kpi-value text-emerald-600">{{ branches.filter(w => w.account_id).length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-amber-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-amber-50 text-amber-600">
            <i class="fas fa-link-slash text-xs"></i>
          </div>
          <div>
            <p class="kpi-label">بانتظار الربط</p>
            <p class="kpi-value text-amber-600">{{ branches.filter(w => !w.account_id).length }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Toolbar: Search & Refresh -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 flex flex-col md:flex-row md:items-end gap-6">
      <div class="flex-grow group">
        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">بحث شامل في الحسابات</label>
        <div class="relative">
          <input v-model="accountSearch" type="text" class="form-input-modern pr-11" placeholder="ابحث بالاسم أو كود الحساب..." />
          <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <button @click="reload" class="h-11 px-8 rounded-xl bg-slate-100 text-slate-600 font-black text-xs uppercase hover:bg-slate-200 transition-all flex items-center gap-2">
        <i class="fas fa-sync-alt"></i> تحديث القوائم
      </button>
    </div>

    <!-- Mapping Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5">المستودع / الفرع</th>
              <th class="px-4 py-5">الموقع</th>
              <th class="px-4 py-5">تعيين الحساب المحاسبي</th>
              <th class="px-8 py-5 text-center w-32">إجراء</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <tr v-if="!filteredbranchs.length" class="text-center py-20">
              <td colspan="4" class="py-24 opacity-20 text-slate-400 flex flex-col items-center">
                <i class="fas fa-link-slash text-6xl mb-4"></i>
                <p class="font-black text-sm uppercase">لا توجد مستودعات مطابقة للفلترة</p>
              </td>
            </tr>
            <tr v-for="w in filteredbranchs" :key="w.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-8 py-4 whitespace-nowrap">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-white transition-all"><i class="fas fa-store-alt text-sm"></i></div>
                  <div class="flex flex-col">
                    <span class="font-black text-slate-800 leading-none">{{ w.name }}</span>
                    <div class="mt-2 flex">
                      <span v-if="w.account_id" class="status-badge bg-emerald-100 text-emerald-700">
                        <i class="fas fa-check-circle ml-1 text-[8px]"></i> مرتبط
                      </span>
                      <span v-else class="status-badge bg-amber-100 text-amber-700">
                        <i class="fas fa-circle-exclamation ml-1 text-[8px]"></i> غير مرتبط
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-slate-400 text-xs font-bold">{{ w.location || '-' }}</td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-2 min-w-[320px]">
                  <div class="relative group/field">
                    <input type="text" v-model="w._search" class="h-9 w-full rounded-xl border border-slate-100 bg-slate-50/50 px-9 text-[11px] font-bold outline-none focus:bg-white focus:border-blue-200 transition-all" placeholder="بحث داخل الحسابات..." />
                    <i class="fas fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/field:text-blue-500 text-[10px]"></i>
                  </div>
                  <select v-model.number="w._account_id" class="form-select-modern h-10 text-xs font-black shadow-none border-slate-100">
                    <option :value="0">-- اختر الحساب المالي من القائمة --</option>
                    <optgroup label="حسابات المنشأة" v-if="tenantAccounts.length">
                      <option v-for="acc in filterAccounts(tenantAccounts, w._search)" :key="'t-'+acc.id" :value="acc.id">{{ accLabel(acc) }}</option>
                    </optgroup>
                    <optgroup label="الحسابات العامة (الافتراضية)" v-if="globalAccounts.length">
                      <option v-for="acc in filterAccounts(globalAccounts, w._search)" :key="'g-'+acc.id" :value="acc.id">{{ accLabel(acc) }}</option>
                    </optgroup>
                  </select>
                </div>
              </td>
              <td class="px-8 py-4 text-center">
                <button @click="saveOne(w)" :disabled="w._saving" class="w-14 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-all shadow-md shadow-blue-100 active:scale-90 disabled:opacity-30 mx-auto">
                  <BaseSpinner v-if="w._saving" :size="14" color="#fff" />
                  <i v-else class="fas fa-save text-xs"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bulk Actions Footer -->
      <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end items-center gap-6">
        <div class="flex items-center gap-3">
           <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-600 shadow-sm border border-slate-100"><i class="fas fa-info-circle"></i></div>
           <p class="text-[11px] font-bold text-slate-400 leading-relaxed max-w-sm">سيتم تحديث كافة الحسابات المحاسبية المرتبطة بالمستودعات دفعة واحدة. سيتم تجاهل الصفوف التي لم يطرأ عليها تغيير.</p>
        </div>
        <button @click="saveAll" :disabled="bulkSaving" class="h-14 px-12 bg-slate-900 text-white rounded-2xl font-black text-sm shadow-xl shadow-slate-200 hover:bg-black transition-all active:scale-95 flex items-center gap-3 disabled:opacity-40">
          <BaseSpinner v-if="bulkSaving" :size="20" color="#fff" />
          <i v-else class="fas fa-check-double"></i>
          حفظ كافة التغييرات
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useAccountStore } from '@/stores/account/accountStore';
import BaseSpinner from '@/components/ui/BaseSpinner.vue'

// --- Store Instances ---
const branchStore = useBranchStore();

// --- State ---
const branches = computed(() => branchStore.branches)
const accountStore = useAccountStore();
const tenantAccounts = computed(() => accountStore.tenantAccounts);
const globalAccounts = computed(() => accountStore.globalAccounts);
const accountSearch = ref('')
const showMissingOnly = ref(false)
const bulkSaving = ref(false)

const accLabel = (acc) => acc?.code ? `${acc.code} — ${acc.name}` : (acc?.name || '')

// [PRESERVED] filterAccounts يدعم البحث من حقل الصف (w._search) وكذلك البحث العام (accountSearch)
function filterAccounts(list, q) {
  const s = (q || accountSearch.value || '').toString().trim().toLowerCase()
  if (!s) return list
  return list.filter(a => (a.name || '').toLowerCase().includes(s) || (a.code || '').toLowerCase().includes(s))
}

// [PRESERVED] filteredbranchs يحترم showMissingOnly
const filteredbranchs = computed(() => {
  const arr = branches.value || []
  return arr.filter(w => !showMissingOnly.value || !w.account_id)
})

// Logic: Reload Data (Preserved from old — endpoint + mapping identical)
async function reload() {
  try {
    await Promise.all([
      accountStore.fetchGroupedAccounts(),
      branchStore.fetchBranches()
    ])

    // [PRESERVED] add local editing fields to branches
    branchStore.branches.forEach(w => {
      if (!('_account_id' in w)) {
        w._account_id = Number(w.account_id || 0)
        w._search = ''
        w._saving = false
      }
    })
  } catch (error) {
    console.error('Error loading data:', error)
    window.showToast && window.showToast('فشل تحميل البيانات', 'error')
  }
}

// Logic: Save Single Row (Preserved from old)
async function saveOne(w) {
  try {
    w._saving = true
    const account_id = w._account_id && w._account_id > 0 ? w._account_id : null
    const response = await branchStore.updateBranch(w.id, { account_id })
    if (response.status === 'success') {
      // [PRESERVED] update local account_id after successful save
      w.account_id = account_id
      window.showToast && window.showToast(`تم ربط ${w.name} بنجاح`, 'success')
    } else {
      window.showToast && window.showToast(response.message || 'فشل حفظ التغييرات', 'error')
    }
  } catch (e) {
    window.showToast && window.showToast('فشل حفظ التغييرات', 'error')
  } finally {
    w._saving = false
  }
}

// Logic: Bulk Save All (Preserved from old — only saves changed rows)
async function saveAll() {
  try {
    bulkSaving.value = true
    let successCount = 0
    let errorCount = 0
    
    for (const w of branches.value) {
      // [PRESERVED] skip rows that haven't changed
      if (w._account_id !== (Number(w.account_id || 0))) {
        const response = await branchStore.updateBranch(w.id, { account_id: w._account_id && w._account_id > 0 ? w._account_id : null })
        if (response.status === 'success') {
          w.account_id = w._account_id
          successCount++
        } else {
          errorCount++
        }
      }
    }
    
    const message = errorCount > 0 
      ? `Saved ${successCount} changes, ${errorCount} failed` 
      : 'Successfully saved all linking changes'
    window.showToast && window.showToast(message, errorCount > 0 ? 'warning' : 'success')
  } finally {
    bulkSaving.value = false
  }
}

onMounted(reload)
</script>

<style scoped>



/* Dashboard UI Components */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none tracking-tight; }

/* Modern UI Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

.status-badge { @apply px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-tighter shadow-sm inline-flex items-center; }

/* Toggle Styling */
.toggle-option { @apply flex items-center gap-3 cursor-pointer select-none; }
.toggle-box { @apply w-5 h-5 rounded-lg border-2 border-slate-200 bg-white transition-all relative; }
.toggle-box::after { content: '✓'; @apply absolute inset-0 flex items-center justify-center text-white text-[10px] font-black opacity-0 scale-50 transition-all; }
input:checked + .toggle-box::after { @apply opacity-100 scale-100; }

/* Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>