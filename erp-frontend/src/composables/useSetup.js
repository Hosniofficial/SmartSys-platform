// Composable للتعامل مع Setup API
import { ref } from 'vue'
import axios from 'axios'

export function useSetup() {
  const loading = ref(false)
  const saving = ref(false)
  const setupData = ref(null)
  const error = ref(null)

  /**
   * جلب حالة الإعداد
   */
  const fetchSetupStatus = async () => {
    loading.value = true
    error.value = null
    
    try {
      const response = await axios.get('/api/setup/status')
      
      if (response.data.success) {
        setupData.value = response.data.data
        return response.data.data
      } else {
        throw new Error(response.data.message || 'فشل في جلب بيانات الإعداد')
      }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'حدث خطأ أثناء جلب بيانات الإعداد'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * حفظ الإعدادات
   */
  const saveSetup = async (data) => {
    saving.value = true
    error.value = null
    
    try {
      const response = await axios.post('/api/setup/save', data)
      
      if (response.data.success) {
        return response.data
      } else {
        throw new Error(response.data.message || 'فشل في حفظ الإعدادات')
      }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'حدث خطأ أثناء حفظ الإعدادات'
      throw err
    } finally {
      saving.value = false
    }
  }

  /**
   * تخطي الإعداد
   */
  const skipSetup = async () => {
    saving.value = true
    error.value = null
    
    try {
      const response = await axios.post('/api/setup/skip')
      
      if (response.data.success) {
        return response.data
      } else {
        throw new Error(response.data.message || 'فشل في تخطي الإعداد')
      }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'حدث خطأ أثناء تخطي الإعداد'
      throw err
    } finally {
      saving.value = false
    }
  }

  /**
   * التحقق من أن المستخدم Owner
   */
  const isOwner = (user) => {
    if (!user) return false
    return user.is_owner === 1 || user.role === 'owner' || user.role_name === 'owner'
  }

  /**
   * التحقق من اكتمال الإعداد
   */
  const isSetupComplete = () => {
    return setupData.value?.tenant?.is_setup_complete === 1
  }

  return {
    loading,
    saving,
    setupData,
    error,
    fetchSetupStatus,
    saveSetup,
    skipSetup,
    isOwner,
    isSetupComplete
  }
}
