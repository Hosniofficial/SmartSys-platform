import apiClient from '@/config/axios';

// Shifts Service
export const shiftsService = {
  async open({ branch_id, terminal_id, opening_cash_amount = 0, cashier_id = undefined, notes = undefined }) {
    const payload = {
      branch_id,
      terminal_id,
      opening_cash_amount,
    };
    if (cashier_id !== undefined && cashier_id !== null) payload.cashier_id = cashier_id;
    if (notes !== undefined) payload.notes = notes;
    const res = await apiClient.post('/shifts/open', payload);
    return res?.data?.data || res?.data;
  },

  async close({ shift_id, closing_cash_amount, notes = undefined }) {
    const payload = {
      shift_id,
      closing_cash_amount,
    };
    if (notes !== undefined) payload.notes = notes;
    const res = await apiClient.post('/shifts/close', payload);
    return res?.data?.data || res?.data;
  },

  async getCurrent({ branch_id, terminal_id }) {
    const params = {
      branch_id,
      terminal_id,
    };
    const res = await apiClient.get('/shifts/current', { params });
    return res?.data?.data || res?.data || null;
  },
};

export default shiftsService;
