/**
 * Global Currencies Configuration
 * Supported currencies with their codes, symbols, and Arabic/English names
 */

export const SUPPORTED_CURRENCIES = {
  'EGP': {
    code: 'EGP',
    symbol: 'ج.م',
    nameAr: 'جنيه مصري',
    nameEn: 'Egyptian Pound',
    country: 'Egypt'
  },
  'USD': {
    code: 'USD',
    symbol: '$',
    nameAr: 'دولار أمريكي',
    nameEn: 'US Dollar',
    country: 'United States'
  },
  'EUR': {
    code: 'EUR',
    symbol: '€',
    nameAr: 'يورو',
    nameEn: 'Euro',
    country: 'Europe'
  },
  'SAR': {
    code: 'SAR',
    symbol: 'ر.س',
    nameAr: 'ريال سعودي',
    nameEn: 'Saudi Riyal',
    country: 'Saudi Arabia'
  },
  'AED': {
    code: 'AED',
    symbol: 'د.إ',
    nameAr: 'درهم إماراتي',
    nameEn: 'UAE Dirham',
    country: 'United Arab Emirates'
  },
  'KWD': {
    code: 'KWD',
    symbol: 'د.ك',
    nameAr: 'دينار كويتي',
    nameEn: 'Kuwaiti Dinar',
    country: 'Kuwait'
  },
  'QAR': {
    code: 'QAR',
    symbol: 'ر.ق',
    nameAr: 'ريال قطري',
    nameEn: 'Qatari Riyal',
    country: 'Qatar'
  },
  'BHD': {
    code: 'BHD',
    symbol: 'د.ب',
    nameAr: 'دينار بحريني',
    nameEn: 'Bahraini Dinar',
    country: 'Bahrain'
  },
  'OMR': {
    code: 'OMR',
    symbol: 'ر.ع',
    nameAr: 'ريال عماني',
    nameEn: 'Omani Rial',
    country: 'Oman'
  }
};

/**
 * Get currency info by code
 * @param {string} code - Currency code (e.g., 'EGP', 'USD')
 * @returns {object} Currency info object or null
 */
export const getCurrencyInfo = (code) => {
  return SUPPORTED_CURRENCIES[code] || null;
};

/**
 * Get currency symbol by code
 * @param {string} code - Currency code (e.g., 'EGP', 'USD')
 * @returns {string} Currency symbol
 */
export const getCurrencySymbol = (code) => {
  const currency = SUPPORTED_CURRENCIES[code];
  return currency ? currency.symbol : code;
};

/**
 * Get currency name in Arabic or English
 * @param {string} code - Currency code
 * @param {string} locale - 'ar' for Arabic, 'en' for English (default: 'ar')
 * @returns {string} Currency name
 */
export const getCurrencyName = (code, locale = 'ar') => {
  const currency = SUPPORTED_CURRENCIES[code];
  if (!currency) return code;
  return locale === 'ar' ? currency.nameAr : currency.nameEn;
};

/**
 * Get all supported currencies as array for dropdown/select
 * @param {string} locale - 'ar' for Arabic, 'en' for English (default: 'ar')
 * @returns {array} Array of currency objects with code and name
 */
export const getAvailableCurrencies = (locale = 'ar') => {
  return Object.values(SUPPORTED_CURRENCIES).map(currency => ({
    code: currency.code,
    name: locale === 'ar' ? currency.nameAr : currency.nameEn,
    symbol: currency.symbol
  }));
};

export default {
  SUPPORTED_CURRENCIES,
  getCurrencyInfo,
  getCurrencySymbol,
  getCurrencyName,
  getAvailableCurrencies
};
