import apiClient from '@/config/axios';

// Account Statement API service
// Backend expects either account_id or account_code (required), plus optional party context and filters
export default {
  async getStatement(params = {}) {
    // params may include: account_id, account_code, party_type, party_id, start_date, end_date,
    // include_types, exclude_types, status, per_page, page, fill_gaps, only_nonzero
    return apiClient.get('/account-statement', { params });
  }
};
