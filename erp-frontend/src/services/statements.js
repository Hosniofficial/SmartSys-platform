import apiClient from '@/config/axios';

export function getCustomerStatement(id, params = {}) {
  return apiClient.get(`/customers/${id}/statement`, { params });
}

export function getSupplierStatement(id, params = {}) {
  return apiClient.get(`/suppliers/${id}/statement`, { params });
}

export function getStatementByType(type, id, params = {}) {
  if (type === 'customers') return getCustomerStatement(id, params);
  if (type === 'suppliers') return getSupplierStatement(id, params);
  throw new Error('Invalid type. Expected "customers" or "suppliers"');
}
