/**
 * printConfig.js
 * ─────────────────────────────────────────────
 * Shared print configuration accessor for templates.
 * Cached per call-stack to avoid redundant localStorage reads
 * during a single render. Cache is cleared after print.
 */

let _cfg = null;

export const getPrintConfig = () => {
  if (_cfg) return _cfg;

  _cfg = {
    printMode:     localStorage.getItem('print.mode') || 'browser',
    headerEnabled: (localStorage.getItem('pos_print_header_enabled') || '1') === '1',
    footerEnabled: (localStorage.getItem('pos_print_footer_enabled') || '1') === '1',
    headerText:    localStorage.getItem('pos_print_header_text')  || '',
    footerText:    localStorage.getItem('pos_print_footer_text')  || 'شكراً لتعاملكم معنا',
    termsText:     localStorage.getItem('pos_print_terms_text')   || '',
  };

  return _cfg;
};

/** Call this after window.print() to allow fresh config on next print */
export const clearPrintConfigCache = () => { _cfg = null; };