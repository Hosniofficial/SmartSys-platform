import apiClient from '@/config/axios';

// Sessions Service
// axios baseURL is '/api' and Vite proxy rewrites '/api' -> '/api/v1'.
// Therefore, DO NOT prefix paths with '/v1' here.
export const SessionsService = {
  async getCurrent(branchId, cashierId, deviceId) {
    const params = {};
    if (branchId !== undefined && branchId !== null) params.branch_id = branchId;
    if (cashierId !== undefined && cashierId !== null) params.cashier_id = cashierId;
    if (deviceId !== undefined && deviceId !== null && `${deviceId}` !== '') params.device_id = deviceId;
    const res = await apiClient.get('/sessions/current', { params, meta: { skipLoader: true } });
    return res.data?.data || null;
  },

  async list({ status, branch_id, cashier_id, page = 1, limit = 20 } = {}) {
    const params = {};
    if (status) params.status = status;
    if (branch_id !== undefined && branch_id !== null && `${branch_id}` !== '') params.branch_id = branch_id;
    if (cashier_id !== undefined && cashier_id !== null && `${cashier_id}` !== '') params.cashier_id = cashier_id;
    params.page = page;
    params.limit = limit;
    const res = await apiClient.get('/sessions', { params });
    return res.data?.data || { items: [], total: 0, page, limit, total_pages: 0 };
  },

  async openSession({ branch_id, opening_cash_amount = 0, session_type = 'manual', notes = null, cashier_id = undefined, device_id = undefined, device_name = undefined, terminal_id = undefined }) {
    const body = { branch_id, opening_cash_amount, session_type };
    if (notes) body.notes = notes;
    // Do not send cashier_id normally; backend infers from JWT. Allow override for admin tools.
    if (cashier_id) body.cashier_id = cashier_id;
    if (device_id) body.device_id = device_id;
    if (device_name) body.device_name = device_name;
    if (terminal_id !== undefined && terminal_id !== null) body.terminal_id = terminal_id;
    const res = await apiClient.post('/sessions/open', body);
    return res.data?.data || null;
  },

  async closeSession(session_id, closing_cash_amount = null, variance_reason = '') {
    const data = { session_id };
    if (closing_cash_amount !== null) data.closing_cash_amount = closing_cash_amount;
    if (variance_reason) data.variance_reason = variance_reason;
    
    const res = await apiClient.post('/sessions/close', data);
    return res.data?.data || { success: true };
  },

  async getSummary(sessionId) {
    if (!sessionId) return null;
    const res = await apiClient.get(`/sessions/${sessionId}/summary`, { meta: { skipLoader: true } });
    return res.data?.data || null;
  },

  /**
   * Get daily sessions summary report
   * @param {Object} params - Query parameters
   * @param {string} params.from_date - Start date (YYYY-MM-DD)
   * @param {string} params.to_date - End date (YYYY-MM-DD)
   * @param {number} params.branch_id - Filter by branch ID
   * @param {number} params.cashier_id - Filter by cashier ID
   * @param {number} params.terminal_id - Filter by terminal ID
   * @param {boolean} params.has_variance - Filter by variance status
   * @param {number} params.page - Page number for pagination
   * @param {number} params.limit - Items per page
   * @returns {Promise<Object>} - Paginated sessions summary data
   */
  async getSessionsSummary(params = {}) {
    // Set default values
    const queryParams = {
      page: 1,
      limit: 25,
      ...params
    };

    // Remove undefined/null values
    Object.keys(queryParams).forEach(key => {
      if (queryParams[key] === undefined || queryParams[key] === null || queryParams[key] === '') {
        delete queryParams[key];
      }
    });

    const res = await apiClient.get('/sessions/summary/daily', { params: queryParams });
    return res.data?.data || { items: [], total: 0, page: 1, limit: 25, total_pages: 0 };
  },

  /**
   * Get session details including transactions and summary
   * @param {number} sessionId - Session ID
   * @returns {Promise<Object>} - Session details with transactions
   */
  async getSessionDetails(sessionId) {
    if (!sessionId) return null;
    
    const res = await apiClient.get(`/sessions/${sessionId}/summary`);
    return res.data?.data || null;
  }
};

export default SessionsService;
