import apiClient from '@/config/axios'

// POS Sales Approvals Service
// axios baseURL is '/api' and Vite proxy rewrites '/api' -> '/api/v1'.
// DO NOT prefix with '/v1' here.
export const ApprovalsService = {
  async listPending({ page = 1, limit = 20 } = {}) {
    const params = { page, limit }
    const res = await apiClient.get('/sales/pending-approvals', { params })
    // Support both array and paginated object
    const data = res?.data?.data
    if (Array.isArray(data)) {
      return { items: data, total: data.length, page, limit, total_pages: 1 }
    }
    return {
      items: data?.items || [],
      total: Number(data?.total || data?.count || 0),
      page: Number(data?.page || page),
      limit: Number(data?.limit || limit),
      total_pages: Number(data?.total_pages || Math.max(1, Math.ceil((Number(data?.total || 0)) / (Number(data?.limit || limit) || 1))))
    }
  },

  async approve(id, note = '') {
    const body = {}
    if (note && String(note).trim()) body.note = String(note).trim()
    const res = await apiClient.post(`/sales/${id}/approve`, body)
    return res?.data?.data ?? { success: true }
  },

  async reject(id, note = '') {
    const body = {}
    if (note && String(note).trim()) body.note = String(note).trim()
    const res = await apiClient.post(`/sales/${id}/reject`, body)
    return res?.data?.data ?? { success: true }
  }
}

export default ApprovalsService
