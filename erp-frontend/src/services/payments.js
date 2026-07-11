import apiClient from '../config/axios';

export async function fetchPayments(params = {}) {
  const { data } = await apiClient.get('/payments', { params });
  // API returns { status, data: items, meta }
  return {
    items: data?.data || [],
    meta: data?.meta || { total: 0, page: 1, per_page: 50 },
  };
}

export function exportToCsv(filename, rows) {
  if (!rows || !rows.length) return;
  const headers = Object.keys(rows[0]);
  const csvContent = [headers.join(',')]
    .concat(rows.map(r => headers.map(h => formatCsvCell(r[h])).join(',')))
    .join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function formatCsvCell(value) {
  if (value === null || value === undefined) return '';
  const str = String(value).replaceAll('"', '""');
  if (str.search(/([",\n])/g) >= 0) {
    return '"' + str + '"';
  }
  return str;
}
