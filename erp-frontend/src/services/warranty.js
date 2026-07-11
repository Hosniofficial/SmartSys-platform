import apiClient from '@/config/axios';

// Warranty Service
// axios baseURL is '/api' and Vite proxy rewrites '/api' -> '/api/v1'.
// Therefore, DO NOT prefix paths with '/v1' here.
export const WarrantyService = {
  async list({ status, priority, customer_id, assigned_to, page = 1, limit = 20 } = {}) {
    const params = {};
    if (status) params.status = status;
    if (priority) params.priority = priority;
    if (customer_id) params.customer_id = customer_id;
    if (assigned_to) params.assigned_to = assigned_to;
    params.page = page;
    params.limit = limit;
    const res = await apiClient.get('/warranty/requests', { params });
    return res.data?.data || { items: [], total: 0, page, limit, total_pages: 0 };
  },

  async create(payload) {
    // payload: { customer_id, invoice_id, product_serial, issue_description, purchase_date, priority, items: [{product_id, quantity, issue_notes}] }
    const res = await apiClient.post('/warranty/requests', payload);
    return res.data?.data || null;
  },

  async get(id) {
    const res = await apiClient.get(`/warranty/requests/${id}`);
    return res.data?.data || null;
  },

  async update(id, updates) {
    const res = await api.patch(`/warranty/requests/${id}`, updates);
    return res.data?.data || null;
  },

  async changeStatus(id, status, note = null) {
    const body = { status };
    if (note) body.note = note;
    const res = await apiClient.post(`/warranty/requests/${id}/status`, body);
    return res.data?.data || null;
  },

  async addNote(id, { content, is_internal = false }) {
    const res = await apiClient.post(`/warranty/requests/${id}/notes`, { content, is_internal });
    return res.data?.data || null;
  },

  async uploadAttachment(id, file) {
    const form = new FormData();
    form.append('file', file);
    const res = await apiClient.post(`/warranty/requests/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    return res.data?.data || null;
  },

  async deleteAttachment(attachmentId) {
    const res = await api.delete(`/warranty/attachments/${attachmentId}`);
    return res.data?.data || { success: true };
  }
};

export default WarrantyService;
