/**
 * Format a number as currency
 * @param {number} value - The number to format
 * @param {string} currency - The currency code (default: 'EGP')
 * @param {number} decimals - Number of decimal places (default: 2)
 * @returns {string} Formatted currency string
 */
import { getCurrencySymbol } from '../config/currencies.js';

export const formatCurrency = (value, currency = 'EGP', decimals = 2) => {
  if (value === null || value === undefined) return '0.00';
  
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;
  
  if (isNaN(numericValue)) return '0.00';
  
  // Format number with fixed decimals and add thousands separators
  const formattedValue = numericValue.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  
  // Get currency symbol from config (supports dynamic symbols from settings)
  const symbol = getCurrencySymbol(currency);
  
  // For RTL languages, put the symbol at the end
  return `${formattedValue} ${symbol}`;
};

/**
 * Format a number with thousands separators
 * @param {number|string} value - The number to format
 * @param {number} decimals - Number of decimal places (default: 2)
 * @returns {string} Formatted number string
 */
export const formatNumber = (value, decimals = 2) => {
  if (value === null || value === undefined) return '0';
  
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;
  
  if (isNaN(numericValue)) return '0';
  
  return numericValue.toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals
  });
};

/**
 * Format a percentage value
 * @param {number|string} value - The percentage value (0-100)
 * @param {number} decimals - Number of decimal places (default: 1)
 * @returns {string} Formatted percentage string
 */
export const formatPercentage = (value, decimals = 1) => {
  if (value === null || value === undefined) return '0%';
  
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;
  
  if (isNaN(numericValue)) return '0%';
  
  return `${numericValue.toFixed(decimals)}%`;
};

/**
 * ─────────────────────────────────────────────────────────────────────────
 * Statement Data Formatters (Unified for ContactDetails & AccountStatement)
 * ─────────────────────────────────────────────────────────────────────────
 */

/**
 * Format price using US number format (standard for statements)
 * Used across ContactDetails, AccountStatement, and other financial views
 * 
 * @param {number|string} value - The amount to format
 * @returns {string} Formatted price (e.g., "1,234.56")
 */
export const formatPrice = (value) => {
  if (value === null || value === undefined) return '0.00';
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;
  if (isNaN(numericValue)) return '0.00';
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(numericValue);
};

/**
 * Alias for formatPrice (used in some components)
 * Maintains backward compatibility
 */
export const formatPriceEn = formatPrice;

/**
 * Format transaction reference/ID
 * Removes leading zeros and extracts numeric ID
 * 
 * @param {string} reference - The reference string (e.g., "sale#123")
 * @returns {string} Formatted reference
 */
export const formatTransactionRef = (reference) => {
  if (!reference) return '-';
  return String(reference).split('#').pop();
};

/**
 * Get type label for transaction (Arabic)
 * Unified mapping used in both statement views
 * 
 * @param {string} type - The transaction type
 * @returns {string} Localized label
 */
export const getTransactionTypeLabel = (type) => {
  if (!type) return 'حركة';
  const t = String(type).toLowerCase().trim();

  const typeMap = {
    'sale': 'فاتورة بيع',
    'sales': 'فاتورة بيع',
    'purchase': 'فاتورة شراء',
    'purchases': 'فاتورة شراء',
    'receipt': 'قبض',
    'payment': 'صرف',
    'refund': 'صرف',
    'sales_return': 'مرتجع بيع',
    'sale_return': 'مرتجع بيع',
    'return_sale': 'مرتجع بيع',
    'purchase_return': 'مرتجع شراء',
    'return_purchase': 'مرتجع شراء',
    'return': 'مرتجع',
    'cash_voucher': 'سند نقدي',
    'journal': 'قيد يومية',
    'return_payment': 'سداد مرتجع',
    'sales_return_refund': 'استرداد عميل',
    'purchase_return_refund': 'استرجاع مورد'
  };

  return typeMap[t] || t || 'حركة';
};

/**
 * Get CSS badge class for transaction status
 * Used to color-code transactions in tables
 * 
 * @param {string} type - The transaction type
 * @returns {string} CSS class
 */
export const getTransactionBadgeClass = (type) => {
  const t = String(type || '').toLowerCase();

  const statusMap = {
    'closed_by_return': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    'settled_by_return': 'bg-teal-50 text-teal-700 border border-teal-200',
    'settled_by_credit': 'bg-cyan-50 text-cyan-700 border border-cyan-200',
    'settled_mixed': 'bg-purple-50 text-purple-700 border border-purple-200',
    'returned': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    'paid': 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    'partial': 'bg-amber-50 text-amber-700 border border-amber-200',
    'unpaid': 'bg-rose-50 text-rose-700 border border-rose-200'
  };

  if (statusMap[t]) return statusMap[t];

  if (['sale', 'sales', 'purchase', 'purchases'].includes(t)) {
    return 'bg-rose-50 text-rose-700 border border-rose-200';
  }
  if (['receipt', 'refund', 'payment'].includes(t)) {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
  }
  if (['sales_return', 'return_sale', 'purchase_return', 'return_purchase', 'return'].includes(t)) {
    return 'bg-blue-50 text-blue-700 border border-blue-200';
  }
  if (['journal', 'cash_voucher'].includes(t)) {
    return 'bg-amber-50 text-amber-700 border border-amber-200';
  }

  return 'bg-slate-50 text-slate-700 border border-slate-200';
};

/**
 * Get balance display with nature indicator (مدين/دائن)
 * Used in AccountStatement balance column
 * 
 * @param {number} balance - The balance amount
 * @param {string} nature - The balance nature ('debit', 'credit', 'zero')
 * @returns {string} Formatted balance with indicator
 */
export const getBalanceDisplay = (balance, nature) => {
  if (!balance && balance !== 0) return '-';
  const formatted = formatPrice(Math.abs(balance));

  if (nature === 'debit') return `${formatted} مدين`;
  if (nature === 'credit') return `${formatted} دائن`;
  return formatted;
};

/**
 * Get CSS color class for balance display
 * Indicates debit (blue), credit (amber), or neutral (gray)
 * 
 * @param {string} nature - The balance nature
 * @returns {string} CSS class
 */
export const getBalanceColorClass = (nature) => {
  const n = String(nature || 'zero').toLowerCase();
  if (n === 'debit') return 'text-blue-700 font-bold';
  if (n === 'credit') return 'text-amber-700 font-bold';
  return 'text-slate-600';
};

export default {
  formatCurrency,
  formatNumber,
  formatPercentage,
  formatPrice,
  formatPriceEn,
  formatTransactionRef,
  getTransactionTypeLabel,
  getTransactionBadgeClass,
  getBalanceDisplay,
  getBalanceColorClass
};
