<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-desktop text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">أجهزة نقاط البيع (Terminals)</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تعريف وإدارة محطات الكاشير المرتبطة بالفروع والمستودعات</p>
        </div>
      </div>

      <div class="kpi-mini-card border-r-4 border-r-blue-500 bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-100">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">إجمالي الأجهزة المسجلة</p>
        <div class="flex items-center gap-2">
          <span class="text-2xl font-black text-slate-800 leading-none">{{ terminals.length }}</span>
          <i class="fas fa-microchip text-blue-500 text-xs"></i>
        </div>
      </div>
    </div>

    <!-- Registration Form Section -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 mb-10 relative overflow-hidden">
      <div class="absolute top-0 left-0 w-32 h-32 bg-blue-50/50 rounded-full -translate-x-16 -translate-y-16"></div>
      
      <div class="relative z-10 space-y-8">
        <div class="flex items-center gap-3 border-b border-slate-50 pb-4">
          <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">تسجيل محطة بيع جديدة</h2>
          <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-0.5 rounded-lg">New Registration</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="space-y-2">
            <label class="modal-label">الفرع المرتبط <span class="text-rose-500">*</span></label>
            <select v-model="form.branch_id" class="form-select-modern font-black text-sm cursor-pointer">
              <option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>

          <div class="space-y-2 group">
            <label class="modal-label">كود المحطة الفريد <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="form.code" type="text" class="form-input-modern font-mono font-black text-blue-600" placeholder="مثال: POS-01" />
              <i class="fas fa-barcode absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <div class="space-y-2 group">
            <label class="modal-label">اسم الجهاز التوضيحي</label>
            <div class="relative">
              <input v-model="form.name" type="text" class="form-input-modern font-bold" placeholder="كاشير الاستقبال الرئيسي" />
              <i class="fas fa-tag absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
            </div>
          </div>

          <div class="space-y-2">
            <label class="modal-label">حالة التشغيل</label>
            <select v-model="form.status" class="form-select-modern font-black text-sm">
              <option value="active">نشط (جاهز للعمل)</option>
              <option value="inactive">معطل مؤقتاً</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-50">
          <button 
            @click="saveTerminal" 
            :disabled="isSaving" 
            class="h-12 px-10 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-50 flex items-center gap-3"
          >
            <BaseSpinner v-if="isSaving" :size="16" color="#fff" :margin="0" />
            <i v-else class="fas fa-save"></i>
            حفظ وتسجيل الجهاز
          </button>
        </div>
      </div>
    </section>

    <!-- Terminals List Table -->
    <section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
        <div class="flex items-center gap-3">
          <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
          <h2 class="text-lg font-black text-slate-800 leading-none uppercase tracking-tight">قائمة الأجهزة المسجلة</h2>
        </div>
        <button @click="loadTerminals" :disabled="isLoading" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90">
          <i class="fas fa-sync-alt" :class="{'animate-spin': isLoading}"></i>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
          <thead>
            <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
              <th class="px-8 py-5 w-20 text-center">#</th>
              <th class="px-4 py-5">كود المحطة</th>
              <th class="px-4 py-5">اسم الجهاز</th>
              <th class="px-4 py-5">الفرع المرتبط</th>
              <th class="px-8 py-5 text-center">حالة الجهاز</th>
              <th class="px-8 py-5 text-center">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
            <!-- Skeleton loading for table (GPU-accelerated) -->
            <template v-if="isLoading">
              <tr v-for="row in 5" :key="row">
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" /></td>
                <td class="px-6 py-5"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
              </tr>
            </template>
            <tr v-else-if="!terminals.length" class="text-center py-20">
              <td colspan="6" class="py-24">
                <div class="flex flex-col items-center opacity-20 text-slate-400">
                  <i class="fas fa-desktop text-6xl mb-4"></i>
                  <p class="font-black text-sm uppercase">لا توجد أجهزة مسجلة حتى الآن</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="(t, idx) in terminals" :key="t.id" class="hover:bg-blue-50/30 transition-all group">
              <td class="px-8 py-4 text-center text-slate-300 font-mono text-xs">{{ idx + 1 }}</td>
              <td class="px-4 py-4">
                <span class="font-black text-blue-600 font-mono tracking-wider">{{ t.code }}</span>
              </td>
              <td class="px-4 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-white transition-all"><i class="fas fa-display text-[10px]"></i></div>
                  <span class="text-slate-800">{{ t.name || '—' }}</span>
                </div>
              </td>
              <td class="px-4 py-4 font-bold text-slate-500">
                {{ branches.find(w => w.id === t.branch_id)?.name || t.branch_id }}
              </td>
              <td class="px-8 py-4 text-center">
                <span :class="[t.status === 'active' ? 'bg-emerald-100 text-emerald-700 shadow-emerald-50' : 'bg-slate-100 text-slate-500 shadow-slate-50']" class="status-badge px-4 py-1.5 border border-white">
                   <i :class="[t.status === 'active' ? 'fas fa-check-circle' : 'fas fa-power-off', 'ml-1.5 text-[8px]']"></i>
                   {{ t.status === 'active' ? 'نشط' : 'غير نشط' }}
                </span>
              </td>
              <td class="px-8 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEdit(t)" title="تعديل" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-all flex items-center justify-center text-xs active:scale-90">
                    <i class="fas fa-pen"></i>
                  </button>
                  <button @click="toggleStatus(t)" :title="t.status === 'active' ? 'تعطيل' : 'تفعيل'"
                    :class="t.status === 'active' ? 'bg-rose-50 text-rose-500 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'"
                    class="w-8 h-8 rounded-lg transition-all flex items-center justify-center text-xs active:scale-90">
                    <i :class="t.status === 'active' ? 'fas fa-ban' : 'fas fa-check'"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

  <!-- Edit Terminal Modal -->
  <Teleport to="body">
    <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" @click.self="editModal.open = false">
      <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 space-y-6 animate-fadeIn" dir="rtl">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-black text-slate-900">تعديل بيانات الجهاز</h3>
          <button @click="editModal.open = false" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 hover:bg-slate-200 flex items-center justify-center transition-all"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-2">
          <label class="modal-label">اسم الجهاز</label>
          <input v-model="editModal.name" type="text" class="form-input-modern font-bold" placeholder="كاشير الاستقبال" />
        </div>
        <div class="space-y-2">
          <label class="modal-label">الفرع المرتبط <span class="text-rose-500">*</span></label>
          <select v-model="editModal.branch_id" class="form-select-modern font-black text-sm">
            <option v-for="wh in branches" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="modal-label">حالة التشغيل</label>
          <select v-model="editModal.status" class="form-select-modern font-black text-sm">
            <option value="active">نشط (جاهز للعمل)</option>
            <option value="inactive">معطل مؤقتاً</option>
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="editModal.open = false" class="flex-1 h-11 rounded-2xl border border-slate-200 text-slate-500 font-black text-sm hover:bg-slate-50 transition-all">إلغاء</button>
          <button @click="saveEdit" :disabled="isSaving" class="flex-1 h-11 rounded-2xl bg-blue-600 text-white font-black text-sm shadow-lg shadow-blue-100 hover:bg-blue-700 disabled:opacity-50 transition-all flex items-center justify-center gap-2">
            <BaseSpinner v-if="isSaving" :size="14" color="#fff" :margin="0" />
            <i v-else class="fas fa-save"></i>
            حفظ التعديلات
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import { useBranchStore } from '@/stores/branch'
import { useTerminalStore } from '@/stores/terminal/terminalStore'
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';

// --- State ---
const { showToast } = useToast()
const branchStore = useBranchStore()
const terminalStore = useTerminalStore()
const branches = computed(() => branchStore.branches)
const terminals = ref([])
const isLoading = ref(false)
const isSaving = ref(false)

const form = ref({
  branch_id: null,
  code: '',
  name: '',
  status: 'active',
})

const editModal = ref({ open: false, id: null, name: '', branch_id: null, status: 'active' })

const openEdit = (t) => {
  editModal.value = { open: true, id: t.id, name: t.name || '', branch_id: t.branch_id, status: t.status }
}

const saveEdit = async () => {
  try {
    isSaving.value = true
    const res = await terminalStore.updateTerminal(editModal.value.id, {
      name:      editModal.value.name.trim() || null,
      branch_id: editModal.value.branch_id,
      status:    editModal.value.status,
    })
    if (res.status === 'success') {
      showToast('تم تحديث بيانات الجهاز بنجاح', 'success')
      editModal.value.open = false
      await loadTerminals()
    } else {
      showToast(res.message || 'فشل التحديث', 'error')
    }
  } catch (e) {
    showToast(e?.response?.data?.message || e.message || 'تعذر تحديث الجهاز', 'error')
  } finally {
    isSaving.value = false
  }
}

const toggleStatus = async (t) => {
  const newStatus = t.status === 'active' ? 'inactive' : 'active'
  try {
    const res = await terminalStore.updateTerminal(t.id, { status: newStatus })
    if (res.status === 'success') {
      showToast(newStatus === 'active' ? 'تم تفعيل الجهاز' : 'تم تعطيل الجهاز', 'success')
      await loadTerminals()
    } else {
      showToast(res.message || 'فشل تغيير الحالة', 'error')
    }
  } catch (e) {
    showToast(e?.response?.data?.message || e.message || 'تعذر تغيير حالة الجهاز', 'error')
  }
}

// Logic: Load Branches (Preserved from old — endpoint + default selection identical)
const loadbranchs = async () => {
  try {
    await branchStore.fetchBranches()
    // [PRESERVED] auto-select first branch if none selected yet
    if (!form.value.branch_id && branches.value.length > 0) {
      form.value.branch_id = branches.value[0].id
    }
  } catch (e) {
    console.error('Failed to load branches', e)
    showToast('تعذر تحميل المخازن', 'error')
  }
}

// Logic: Load Terminals (Preserved from old — service + filter identical)
const loadTerminals = async () => {
  try {
    isLoading.value = true
    const result = await terminalStore.fetchTerminals(form.value.branch_id || undefined);
    terminals.value = Array.isArray(result) ? result : [];
  } catch (e) {
    console.error('Failed to load terminals', e);
    showToast('فشل تحميل أجهزة نقطة البيع', 'error');
  } finally {
    isLoading.value = false
  }
}

// Logic: Reset Form (Preserved from old — resets to first branch)
const resetForm = () => {
  form.value.code = ''
  form.value.name = ''
  form.value.status = 'active'
  if (branches.value.length > 0) {
    form.value.branch_id = branches.value[0].id
  } else {
    form.value.branch_id = null
  }
}

// Logic: Save Terminal (Preserved from old — validation + payload + reload identical)
const saveTerminal = async () => {
  // [PRESERVED] validation: branch_id and code are required
  if (!form.value.branch_id || !form.value.code.trim()) {
    showToast('الرجاء إدخال كود الجهاز واختيار المخزن', 'error')
    return
  }
  try {
    isSaving.value = true
    const payload = {
      branch_id: form.value.branch_id,
      code: form.value.code.trim(),
      // [PRESERVED] name fallback to null if empty
      name: form.value.name.trim() || null,
      status: form.value.status,
    }
    const response = await terminalStore.createTerminal(payload)
    if (response.status === 'success') {
      showToast('تم حفظ جهاز نقطة البيع بنجاح', 'success')
      // [PRESERVED] reset form then reload list after successful save
      resetForm()
      await loadTerminals()
    } else {
      showToast(response.message || 'Failed to save POS device', 'error')
    }
  } catch (e) {
    // [PRESERVED] extract server error message if available
    const msg = e?.response?.data?.message || e.message || 'تعذر حفظ جهاز نقطة البيع'
    console.error('Failed to save terminal', e)
    showToast(msg, 'error')
  } finally {
    isSaving.value = false
  }
}

// [PRESERVED] load branches first (sets default branch_id), then terminals
onMounted(async () => {
  await loadbranchs()
  await loadTerminals()
})
</script>

<style scoped>
/* Modern UI Components */
.modal-label { @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm appearance-none; }

.status-badge { @apply rounded-xl text-[10px] font-black uppercase tracking-tighter shadow-sm flex items-center justify-center; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Scrollbar */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
</style>