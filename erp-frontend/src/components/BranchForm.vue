<template>
  <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-modalIn border border-white flex flex-col" @click.stop>
    
    <!-- Modal Header -->
    <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 text-white">
          <i :class="[isEditing ? 'fas fa-edit' : 'fas fa-plus-circle', 'text-xl']"></i>
        </div>
        <div>
          <h3 class="text-xl font-black text-slate-800 leading-none">{{ isEditing ? 'تعديل بيانات الفرع' : 'إنشاء فرع جديد' }}</h3>
          <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">إدارة الفروع والتوزيع</p>
        </div>
      </div>
      <button @click="closeModal" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <form @submit.prevent="handleSubmit" class="p-8 space-y-6 text-right" dir="rtl">
      
      <!-- branch Name -->
      <div class="space-y-2">
        <label for="branch-name" class="form-label-modern">اسم الفرع <span class="text-rose-500">*</span></label>
        <div class="relative group">
          <input 
            type="text" 
            id="branch-name" 
            v-model="form.name" 
            class="form-input-modern pr-11" 
            :class="{'border-rose-300 ring-rose-50': serverErrors.name}"
            placeholder="مثال: الفرع الرئيسي - جدة" 
            required
          >
          <i class="fas fa-store absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
        <p v-if="serverErrors.name" class="text-[10px] text-rose-500 font-bold px-1 animate-fadeIn">{{ serverErrors.name[0] }}</p>
      </div>

      <!-- branch Location -->
      <div class="space-y-2">
        <label for="branch-location" class="form-label-modern">الموقع الجغرافي (اختياري)</label>
        <div class="relative group">
          <input 
            type="text" 
            id="branch-location" 
            v-model="form.location" 
            class="form-input-modern pr-11"
            placeholder="مثال: المنطقة الصناعية، بلوك 4"
          >
          <i class="fas fa-map-marker-alt absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
        <p v-if="serverErrors.location" class="text-[10px] text-rose-500 font-bold px-1 animate-fadeIn">{{ serverErrors.location[0] }}</p>
      </div>

      <!-- Phone -->
      <div class="space-y-2">
        <label for="branch-phone" class="form-label-modern">هاتف الفرع (اختياري)</label>
        <div class="relative group">
          <input
            type="tel"
            id="branch-phone"
            v-model="form.phone"
            class="form-input-modern pr-11"
            placeholder="مثال: 0501234567"
          >
          <i class="fas fa-phone absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <!-- Email -->
      <div class="space-y-2">
        <label for="branch-email" class="form-label-modern">بريد الفرع (اختياري)</label>
        <div class="relative group">
          <input
            type="email"
            id="branch-email"
            v-model="form.email"
            class="form-input-modern pr-11"
            placeholder="branch@company.com"
          >
          <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
      </div>

      <!-- Active Status Toggle -->
      <div class="pt-2">
        <label class="relative flex items-center gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer hover:bg-white hover:border-blue-100 transition-all group">
          <div class="flex-grow">
            <h4 class="text-sm font-black text-slate-800 leading-none">حالة التشغيل</h4>
            <p class="text-[10px] text-slate-400 font-bold mt-1.5 uppercase">تفعيل أو تعطيل الفرع في النظام</p>
          </div>
          <div class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="branch-active" v-model="form.is_active" class="sr-only peer">
            <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
          </div>
        </label>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-end gap-3 pt-6 mt-4 border-t border-slate-50">
        <button type="button" @click="closeModal" class="px-6 py-3 rounded-2xl border-2 border-slate-100 font-black text-slate-400 hover:bg-white transition-all text-sm">
          إلغاء
        </button>
        <button 
          type="submit" 
          class="px-10 py-3 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-3" 
          :disabled="isLoading"
        >
          <BaseSpinner v-if="isLoading" :size="16" color="#ffffff" />
          <span>{{ isEditing ? 'حفظ التغييرات' : 'إنشاء مستودع' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import apiClient from '../config/axios';
import { useToast } from '@/composables/useToast';
import BaseSpinner from '@/components/ui/BaseSpinner.vue'

const props = defineProps({
  branch: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close', 'success']);
const { showToast } = useToast();

// --- State (Preserved) ---
const form = ref({
  id: null,
  name: '',
  location: '',
  phone: '',
  email: '',
  is_active: true
});
const isLoading = ref(false);
const serverErrors = ref({});

// --- Logic (Preserved) ---
watch(() => props.branch, (newVal) => {
  if (newVal) {
    form.value = { ...newVal, is_active: newVal.is_active == 1 };
  } else {
    // Reset for new branch
    form.value = {
      id: null,
      name: '',
      location: '',
      phone: '',
      email: '',
      is_active: true
    };
  }
  serverErrors.value = {}; // Clear errors when data changes
}, { immediate: true });

const isEditing = computed(() => !!form.value.id);

const handleSubmit = async () => {
  isLoading.value = true;
  serverErrors.value = {};
  
  try {
    const payload = {
        ...form.value,
        is_active: form.value.is_active ? 1 : 0
    };

    let response;
    if (isEditing.value) {
      // Update existing branch
      response = await apiClient.put(`/branches/${form.value.id}`, payload);
    } else {
      // Create new branch
      response = await apiClient.post('/branches', payload);
    }

    if (response.data.status === 'success') {
      showToast(`تم ${isEditing.value ? 'تحديث' : 'إنشاء'} الفرع بنجاح!`, 'success');
      emit('success');
    } else {
        throw new Error(response.data.message || 'An unknown error occurred.');
    }

  } catch (error) {
    const errorMessage = error.response?.data?.message || `فشل في العملية.`;
    showToast(errorMessage, 'error');
    if (error.response && error.response.data && error.response.data.errors) {
      serverErrors.value = error.response.data.errors;
    }
  } finally {
    isLoading.value = false;
  }
};

const closeModal = () => {
  emit('close');
};
</script>

<style scoped>

/* Modern Form Components */
.form-label-modern {
  @apply block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1;
}

.form-input-modern {
  @apply w-full h-12 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm;
}

/* Modal Animations */
.animate-modalIn {
  animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>