<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-building text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إدارة الفروع</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">إدارة الفروع، مواقع التخزين، وحالات التشغيل</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button @click="openAddModal" class="h-11 px-6 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-plus"></i> إضافة فرع جديد
        </button>
      </div>
    </div>

    <!-- Overview KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <div class="kpi-card group border-l-4 border-l-blue-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
            <i class="fas fa-list-check"></i>
          </div>
          <div>
            <p class="kpi-label">إجمالي الفروع</p>
            <p class="kpi-value text-slate-800">{{ branches.length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-emerald-500">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="fas fa-check-circle"></i>
          </div>
          <div>
            <p class="kpi-label">الفروع النشطة</p>
            <p class="kpi-value text-emerald-600">{{ branches.filter(w => w.active === 1 || w.active === true).length }}</p>
          </div>
        </div>
      </div>

      <div class="kpi-card group border-l-4 border-l-slate-400">
        <div class="flex items-center gap-4">
          <div class="kpi-icon bg-slate-50 text-slate-400 group-hover:bg-slate-600 group-hover:text-white transition-all">
            <i class="fas fa-eye-slash"></i>
          </div>
          <div>
            <p class="kpi-label">الفروع المعطلة</p>
            <p class="kpi-value text-slate-400">{{ branches.filter(w => w.active !== 1 && w.active !== true).length }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Toolbar: Search & Actions -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div class="relative flex-grow group max-w-xl">
        <input 
          type="text" 
          v-model="searchQuery" 
          class="form-input-modern pr-11" 
          placeholder="ابحث باسم الفرع أو الموقع الجغرافي..."
        />
        <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
      </div>
      
      <!-- إصلاح: كان exportbranchs (خطأ إملائي) -->
      <button @click="exportBranches" class="h-11 px-6 rounded-xl border-2 border-slate-50 text-slate-400 font-black text-xs hover:bg-slate-50 hover:text-emerald-600 transition-all flex items-center justify-center gap-2">
        <i class="fas fa-file-export"></i>
        <span>تصدير البيانات</span>
      </button>
    </div>
      
    <!-- Main Content Area -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative min-h-[400px]">
      
      <!-- 1. Loading State -->
      <div v-if="isLoading" class="p-8 space-y-6">
        <div class="h-14 bg-slate-50 rounded-2xl animate-pulse w-full"></div>
        <div v-for="i in 4" :key="i" class="h-16 bg-slate-50/50 rounded-2xl animate-pulse"></div>
      </div>

      <!-- 2. Error State -->
      <div v-else-if="error" class="py-24 text-center px-6">
        <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-exclamation-triangle text-3xl"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800">{{ error }}</h3>
        <!-- إصلاح: كان fetchbranchs (خطأ إملائي) -->
        <button @click="fetchBranches" class="mt-4 px-8 py-3 bg-rose-600 text-white rounded-xl font-black text-xs shadow-lg shadow-rose-100 hover:bg-rose-700 transition-all flex items-center justify-center gap-2 mx-auto">
          <i class="fas fa-redo"></i> إعادة المحاولة
        </button>
      </div>

      <!-- 3. Data Loaded State -->
      <template v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-right text-sm">
            <thead>
              <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-8 py-5">الفرع</th>
                <th class="px-4 py-5">الموقع الجغرافي</th>
                <th class="px-4 py-5 text-center">حالة التشغيل</th>
                <th class="px-8 py-5 text-center">إجراءات التحكم</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <template v-if="isLoading">
                <tr v-for="row in 5" :key="row">
                  <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" /></td>
                  <td class="px-6 py-5"><BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" /></td>
                  <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                  <td class="px-6 py-5 text-center"><BaseSkeleton type="circle" size="sm" animation="shimmer" /></td>
                </tr>
              </template>
              <tr v-if="filteredBranches.length === 0" class="text-center">
                <td colspan="4" class="py-24">
                  <div class="flex flex-col items-center opacity-20 text-slate-400">
                    <i class="fas fa-building text-6xl mb-4"></i>
                    <p class="font-black text-sm uppercase">لم يتم العثور على فروع</p>
                  </div>
                </td>
              </tr>
              <tr v-for="branch in paginatedBranches" :key="branch.id" class="hover:bg-blue-50/30 transition-all group font-bold">
                <td class="px-8 py-4">
                  <div class="flex items-center gap-4">
                    <div class="w-11 h-11 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 border border-slate-100 group-hover:bg-white group-hover:border-blue-200 transition-all">
                      <i class="fas fa-store-alt text-sm"></i>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-black text-slate-800 leading-none group-hover:text-blue-600 transition-colors">{{ branch.name }}</span>
                      <span class="text-[10px] text-slate-400 mt-1.5 uppercase font-black tracking-widest font-mono">CODE: BR-{{ branch.id }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4 text-slate-500">
                  <div class="flex items-center gap-2">
                    <i class="fas fa-map-pin text-[10px] text-blue-400"></i>
                    <span>{{ branch.location || 'غير محدد' }}</span>
                  </div>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="['status-badge', (branch.active === 1 || branch.active === true) ? 'status-active' : 'status-inactive']">
                    {{ (branch.active === 1 || branch.active === true) ? 'نشط الآن' : 'غير نشط' }}
                  </span>
                </td>
                <td class="px-8 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <router-link :to="`/branches/${branch.id}`" class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95" title="عرض التفاصيل">
                      <i class="fas fa-eye text-sm"></i>
                    </router-link>
                    <button @click="openEditModal(branch)" class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm active:scale-95" title="تعديل">
                      <i class="fas fa-edit text-sm"></i>
                    </button>
                    <button @click="handleDelete(branch.id)" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm active:scale-95" title="حذف">
                      <i class="fas fa-trash text-sm"></i>
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
            عرض <span class="text-slate-800">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> - <span class="text-slate-800">{{ Math.min(currentPage * itemsPerPage, filteredBranches.length) }}</span> من إجمالي <span class="text-slate-800">{{ filteredBranches.length }}</span> مستودع
          </div>
          
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="pagination-btn">
              <i class="fas fa-angle-right"></i>
            </button>
            <div class="flex items-center gap-1 mx-2">
              <button v-for="page in totalPages" :key="page" @click="goToPage(page)" 
                :class="[currentPage === page ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-white text-slate-400 hover:bg-slate-50']"
                class="w-9 h-9 rounded-xl text-xs font-black transition-all">
                {{ page }}
              </button>
            </div>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="pagination-btn">
              <i class="fas fa-angle-left"></i>
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Branch Form Modal -->
    <transition name="modal">
      <div v-if="showFormModal" class="modal-overlay">
        <div class="modal-content-modern animate-modalIn max-w-lg">
          <BranchForm v-if="showFormModal" :branch="selectedBranch" @success="handleFormSuccess" @cancel="showFormModal = false" />
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useToast } from '@/composables/useToast';
// BaseSpinner حُذف — لم يكن مستخدماً في template هذه الصفحة
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import BranchForm from '../../components/BranchForm.vue';
import { BranchesService } from '@/services/branches';
import { useBranchStore } from '@/stores/branch';
import AlertService from '@/services/AlertService';

// --- Core Composables ---
const { showToast } = useToast();
const branchStore = useBranchStore();

// --- State Management (ALL ORIGINAL REFS PRESERVED) ---
const branches = computed(() => branchStore.branches);
const showFormModal = ref(false);
const selectedBranch = ref(null);
const isLoading = ref(false);
const error = ref(null);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// --- API Logic (PRESERVED) ---
const fetchBranches = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    await branchStore.fetchBranches();
  } catch (err) {
    error.value = 'حدث خطأ أثناء تحميل البيانات. يرجى المحاولة مرة أخرى.';
    showToast(err.response?.data?.message || 'فشل في تحميل البيانات', 'error');
  } finally {
    isLoading.value = false;
  }
};

// إصلاح: كان اسمها exportbranchs في الأصل (خطأ إملائي سبّب ReferenceError)
const exportBranches = () => {
  showToast('سيتم تنفيذ عملية التصدير قريباً', 'info');
};

// --- Computed Logic (STRICTLY PRESERVED) ---
const filteredBranches = computed(() => {
  let result = branches.value;
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(w => 
      w.name.toLowerCase().includes(query) ||
      (w.location && w.location.toLowerCase().includes(query))
    );
  }
  return result;
});

const totalPages = computed(() => Math.ceil(filteredBranches.value.length / itemsPerPage.value));

const paginatedBranches = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredBranches.value.slice(start, end);
});

// --- Watchers & Lifecycle ---
watch(searchQuery, () => currentPage.value = 1);

onMounted(() => fetchBranches());

// --- Handlers (PRESERVED) ---
const openAddModal = () => { selectedBranch.value = null; showFormModal.value = true; };
const openEditModal = (branch) => { selectedBranch.value = { ...branch }; showFormModal.value = true; };
const handleFormSuccess = () => { showFormModal.value = false; fetchBranches(); };
const goToPage = (page) => { if (page >= 1 && page <= totalPages.value) currentPage.value = page; };

const handleDelete = async (branchId) => {
  if (await AlertService.confirm('Are you sure you want to delete this branch? It will be disabled in the system.', 'Delete Branch')) {
    try {
      const response = await branchStore.deleteBranch(branchId);
      if (response.status === 'success') {
        showToast('Branch deleted successfully', 'success');
        // Store already handles state update, no need to manually splice
      } else {
        showToast(response.message || 'Failed to delete branch', 'error');
      }
    } catch (error) {
      showToast(error.response?.data?.message || 'Failed to delete branch', 'error');
    }
  }
};
</script>

<style scoped>
/* KPI Styling */
.kpi-card { @apply bg-white p-7 rounded-[2rem] shadow-sm border border-slate-100 transition-all duration-300; }
.kpi-icon { @apply w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm; }
.kpi-label { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1; }
.kpi-value { @apply text-2xl font-black leading-none; }

/* Modern Components */
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.status-badge { @apply px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-tighter shadow-sm inline-block; }
.status-active { @apply bg-emerald-100 text-emerald-700; }
.status-inactive { @apply bg-slate-100 text-slate-400; }
.pagination-btn { @apply w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all disabled:opacity-30; }

/* Modal Styles */
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>