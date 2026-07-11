import apiClient from '@/config/axios'

export const settingsService = {
  async getAll() {
    // Fetch all settings for current tenant
    const res = await apiClient.get('/settings')
    return res.data
  },

  async update(settings) {
    // Update multiple settings keys for current tenant
    // API expects { settings: { key: value, ... } } via PUT /settings
    const res = await apiClient.put('/settings', { settings })
    return res.data
  },
}
