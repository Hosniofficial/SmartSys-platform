import apiClient from '../config/axios'

export const analyticsService = {
  // تحليلات المبيعات (مع branch_id إجباري)
  async getSalesAnalytics(startDate, endDate, branchId, sessionId = null) {
    const params = { 
      start_date: startDate, 
      end_date: endDate 
    }
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    if (sessionId) {
      params.session_id = sessionId
    }
    return await apiClient.get(`/analytics/sales`, { params })
  },

  // تحليلات المخزون (مع branch_id إجباري)
  async getInventoryAnalytics(branchId) {
    const params = {}
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    return await apiClient.get(`/analytics/inventory`, { params })
  },

  // سجلات التدقيق (مع branch_id اختياري للتفصيل أكثر)
  async getAuditLogs(startDate, endDate, branchId = null) {
    const params = { 
      start_date: startDate, 
      end_date: endDate 
    }
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    return await apiClient.get(`/audit/logs`, { params })
  },

  // تقرير الأرباح والخسائر (مع branch_id إجباري)
  async getProfitLossReport(startDate, endDate, branchId) {
    const params = { 
      start_date: startDate, 
      end_date: endDate 
    }
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    return await apiClient.get(`/reports/profit-loss`, { params })
  },

  // حركات المخزون (مع branch_id إجباري)
  async getInventoryMovements(startDate, endDate, productId, branchId) {
    const params = {
      start_date: startDate,
      end_date: endDate,
      product_id: productId
    }
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    return await apiClient.get(`/reports/inventory/movements`, { params })
  },

  // قيمة المخزون (مع branch_id إجباري)
  async getInventoryValue(branchId) {
    const params = {}
    if (branchId !== undefined && branchId !== null && branchId !== '') {
      params.branch_id = branchId
    }
    return await apiClient.get(`/reports/inventory/value`, { params })
  },
 
}

export default analyticsService
