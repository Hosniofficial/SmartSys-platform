<template>
  <teleport to="body">
    <div v-if="isVisible" class="fixed inset-0 z-50 flex items-center justify-center">
      <!-- Backdrop -->
      <div 
        class="absolute inset-0 bg-black transition-opacity duration-300"
        :class="backdropOpacity"
        @click="handleBackdropClick"
      ></div>
      
      <!-- Alert Modal -->
      <div 
        class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300"
        :class="modalScale"
      >
        <!-- Icon and Title -->
        <div class="flex items-start p-6">
          <div class="flex-shrink-0">
            <!-- Success Icon -->
            <div v-if="type === 'success'" class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </div>
            
            <!-- Warning Icon -->
            <div v-else-if="type === 'warning'" class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            
            <!-- Error Icon -->
            <div v-else-if="type === 'error'" class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </div>
            
            <!-- Info Icon -->
            <div v-else-if="type === 'info'" class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            
            <!-- Question Icon (Confirm) -->
            <div v-else-if="type === 'confirm'" class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
          
          <!-- Content -->
          <div class="mr-4 flex-1">
            <h3 class="text-lg font-medium text-gray-900" v-if="title">{{ title }}</h3>
            <p class="mt-2 text-sm text-gray-600" v-if="message">{{ message }}</p>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
          <button
            v-if="type === 'confirm'"
            @click="handleConfirm"
            class="btn btn-primary"
            :class="confirmButtonClass"
          >
            {{ confirmText || 'تأكيد' }}
          </button>
          
          <button
            @click="handleCancel"
            class="btn"
            :class="cancelButtonClass"
          >
            {{ cancelText || (type === 'confirm' ? 'إلغاء' : 'موافق') }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'warning', 'error', 'info', 'confirm'].includes(value)
  },
  title: {
    type: String,
    default: ''
  },
  message: {
    type: String,
    default: ''
  },
  confirmText: {
    type: String,
    default: ''
  },
  cancelText: {
    type: String,
    default: ''
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true
  },
  persistent: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['confirm', 'cancel', 'close'])

// Animation states
const animationState = ref('enter')

// Computed classes for animations
const backdropOpacity = computed(() => {
  return animationState.value === 'enter' ? 'opacity-0' : 'opacity-50'
})

const modalScale = computed(() => {
  switch (animationState.value) {
    case 'enter':
      return 'scale-95 opacity-0'
    case 'active':
      return 'scale-100 opacity-100'
    case 'exit':
      return 'scale-95 opacity-0'
    default:
      return 'scale-100 opacity-100'
  }
})

const confirmButtonClass = computed(() => {
  switch (props.type) {
    case 'error':
      return 'btn-danger'
    case 'warning':
      return 'btn-warning'
    default:
      return 'btn-primary'
  }
})

const cancelButtonClass = computed(() => {
  switch (props.type) {
    case 'success':
      return 'btn-success'
    case 'error':
      return 'btn-outline'
    default:
      return 'btn-outline'
  }
})

// Handle animation states
watch(() => props.isVisible, (newValue) => {
  if (newValue) {
    animationState.value = 'enter'
    setTimeout(() => {
      animationState.value = 'active'
    }, 50)
  } else {
    animationState.value = 'exit'
  }
})

// Event handlers
const handleConfirm = () => {
  emit('confirm')
  closeAlert()
}

const handleCancel = () => {
  emit('cancel')
  closeAlert()
}

const handleBackdropClick = () => {
  if (props.closeOnBackdrop && !props.persistent) {
    handleCancel()
  }
}

const closeAlert = () => {
  animationState.value = 'exit'
  setTimeout(() => {
    emit('close')
  }, 300)
}

// Keyboard support
const handleKeydown = (event) => {
  if (!props.isVisible) return
  
  if (event.key === 'Escape') {
    handleCancel()
  } else if (event.key === 'Enter' && props.type === 'confirm') {
    handleConfirm()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
/* Custom animations */
.transform {
  transition-property: transform, opacity;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Ensure proper z-index stacking */
.fixed {
  z-index: 9999;
}

/* Button focus styles */
.btn:focus {
  outline: 2px solid transparent;
  outline-offset: 2px;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

/* RTL support */
@media (direction: rtl) {
  .flex-row-reverse {
    flex-direction: row-reverse;
  }
}
</style>
