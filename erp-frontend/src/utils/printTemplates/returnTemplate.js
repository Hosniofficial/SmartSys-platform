import { getPrintConfig, clearPrintConfigCache } from './printConfig';
import { esc, fmt, getCompanyData } from './printHelpers';

const itemReturnTotal = (it) =>
  parseFloat(it.net_total || it.total || (it.quantity * (it.unit_price || it.sale_price || 0)) || 0);

/**
 * Thermal template for return documents (76mm roll)
 */
const buildReturnThermalHtml = (doc) => {
  const cfg     = getPrintConfig();
  const company = getCompanyData(doc);
  const docDate = doc.return_date || doc.created_at || new Date().toISOString();

  const itemsHtml = (doc.items || []).map((it, idx) => `
    <div style="margin-bottom:8px;font-size:12px;">
      <div style="display:flex;justify-content:space-between;font-weight:bold;">
        <span>${idx + 1}. ${esc(it.product_name || it.name)}</span>
        <span>${fmt(itemReturnTotal(it))}</span>
      </div>
      <div style="font-size:11px;color:#555;">
        ${fmt(it.quantity)} × ${fmt(it.unit_price || it.sale_price || 0)}
      </div>
    </div>`).join('');

  return `<!doctype html><html dir="rtl"><head><meta charset="utf-8"/>
  <title>مرتجع ${esc(doc.return_number || doc.id || '')}</title>
  <style>
    *{box-sizing:border-box;}
    body{font-family:Tahoma,Arial,sans-serif;margin:0;padding:0;background:#fff;}
    .receipt{width:76mm;margin:0 auto;padding:10px;}
    .text-center{text-align:center;}
    .header-title{font-size:18px;font-weight:bold;margin:5px 0;}
    .info-line{font-size:11px;color:#444;margin-bottom:2px;}
    .return-badge{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;padding:3px 8px;font-size:12px;font-weight:bold;display:inline-block;margin:6px 0;}
    .meta-grid{display:grid;grid-template-columns:1fr 1fr;border-top:1px dashed #000;border-bottom:1px dashed #000;padding:5px 0;margin:10px 0;font-size:11px;}
    .totals-area{border-top:1px solid #000;padding-top:5px;margin-top:10px;}
    .total-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;}
    .grand-total{font-size:15px;font-weight:bold;border-top:1px dashed #000;padding-top:5px;margin-top:5px;}
    @media print{@page{size:80mm auto;margin:0;}body{width:76mm;}.receipt{width:76mm;padding:4mm;}}
  </style></head>
  <body>
  <div class="receipt">
    <div class="text-center">
      <div class="header-title">${esc(company.name || 'وثيقة مرتجع')}</div>
      ${company.branch_name ? `<div class="info-line">${esc(company.branch_name)}</div>` : ''}
      <div class="info-line">
        ${company.phone ? `هاتف: ${esc(company.phone)} | ` : ''}
        ${company.tax ? `ر.ضريبي: ${esc(company.tax)}` : ''}
      </div>
      ${company.address ? `<div class="info-line">${esc(company.address)}</div>` : ''}
      <div class="return-badge">↩ وثيقة مرتجع</div>
    </div>

    <div class="meta-grid">
      <div><strong>رقم:</strong> ${esc(doc.return_number || doc.id)}</div>
      <div style="text-align:left"><strong>التاريخ:</strong> ${new Date(docDate).toLocaleDateString('ar-EG')}</div>
      ${doc.customer_name ? `<div><strong>العميل:</strong> ${esc(doc.customer_name)}</div>` : '<div></div>'}
      <div style="text-align:left"><strong>الوقت:</strong> ${new Date(docDate).toLocaleTimeString('ar-EG',{hour:'2-digit',minute:'2-digit'})}</div>
    </div>

    ${cfg.headerEnabled && cfg.headerText
      ? `<div class="text-center" style="font-size:12px;margin-bottom:10px;">${esc(cfg.headerText)}</div>`
      : ''}

    <div>${itemsHtml}</div>

    <div class="totals-area">
      <div class="total-row"><span>إجمالي المرتجع:</span><span>${fmt(doc.grand_total || doc.total_amount)}</span></div>
      ${doc.return_reason ? `<div class="total-row" style="color:#666;font-size:11px;"><span>السبب:</span><span>${esc(doc.return_reason)}</span></div>` : ''}
    </div>

    ${cfg.footerEnabled && cfg.footerText
      ? `<div class="text-center" style="font-size:12px;margin-top:15px;font-weight:bold;">${esc(cfg.footerText)}</div>`
      : ''}
    <div class="text-center" style="font-size:9px;color:#aaa;margin-top:15px;">Powered by POS System</div>
  </div>
  <script>window.onload=()=>{window.print();setTimeout(()=>{(${clearPrintConfigCache.toString()})();window.close();},500);};<\/script>
  </body></html>`;
};

/**
 * A4 template for return documents
 */
const buildReturnA4Html = (doc) => {
  const cfg     = getPrintConfig();
  const company = getCompanyData(doc);
  const docDate = doc.return_date || doc.created_at || new Date().toISOString();

  const itemsHtml = (doc.items || []).map((it, idx) => `
    <tr>
      <td>${idx + 1}</td>
      <td>${esc(it.product_name || it.name)}</td>
      <td style="text-align:center">${fmt(it.quantity)}</td>
      <td style="text-align:center">${fmt(it.unit_price || it.sale_price || 0)}</td>
      <td style="text-align:center">${fmt(itemReturnTotal(it))}</td>
    </tr>`).join('');

  return `<!doctype html><html dir="rtl"><head><meta charset="utf-8"/>
  <title>مرتجع ${esc(doc.return_number || doc.id || '')}</title>
  <style>
    *{box-sizing:border-box;}
    body{font-family:Tahoma,Arial,sans-serif;margin:0;padding:24px;color:#1e293b;direction:rtl;}
    .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #dc2626;}
    .company-name{font-size:22px;font-weight:900;color:#1e293b;}
    .company-sub{font-size:12px;color:#64748b;margin-top:4px;}
    .return-badge{background:#fee2e2;color:#dc2626;border:2px solid #fca5a5;border-radius:8px;padding:8px 20px;font-size:20px;font-weight:900;text-align:center;}
    .return-no{font-size:13px;color:#dc2626;font-weight:bold;margin-top:6px;text-align:center;}
    .info-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;}
    .info-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;font-size:12px;}
    .info-box label{display:block;font-size:10px;color:#94a3b8;margin-bottom:4px;}
    table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:20px;}
    th{background:#fee2e2;padding:8px;border:1px solid #fca5a5;text-align:right;color:#dc2626;}
    td{padding:8px;border:1px solid #e2e8f0;}
    .totals{max-width:300px;margin-right:auto;font-size:13px;}
    .totals-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;}
    .totals-final{display:flex;justify-content:space-between;padding:10px 0;font-size:16px;font-weight:900;color:#dc2626;}
    @media print{@page{size:A4;margin:15mm;}body{padding:0;}}
  </style></head>
  <body>
    <div class="header">
      <div>
        <div class="company-name">${esc(company.name || '')}</div>
        <div class="company-sub">
          ${company.branch_name ? esc(company.branch_name) + ' | ' : ''}
          ${company.phone ? 'هاتف: ' + esc(company.phone) : ''}
          ${company.address ? ' | ' + esc(company.address) : ''}
        </div>
      </div>
      <div>
        <div class="return-badge">↩ وثيقة مرتجع</div>
        <div class="return-no">${esc(doc.return_number || 'RET-' + doc.id)}</div>
      </div>
    </div>

    <div class="info-grid">
      <div class="info-box"><label>التاريخ</label>${new Date(docDate).toLocaleDateString('en-US')}</div>
      <div class="info-box"><label>العميل</label>${esc(doc.customer_name || 'عميل نقدي')}</div>
      <div class="info-box"><label>الفرع</label>${esc(doc.branch_name || '-')}</div>
    </div>

    <table>
      <thead><tr><th>#</th><th>المنتج</th><th style="text-align:center">الكمية</th><th style="text-align:center">سعر الوحدة</th><th style="text-align:center">الإجمالي</th></tr></thead>
      <tbody>${itemsHtml}</tbody>
    </table>

    <div class="totals">
      <div class="totals-final"><span>إجمالي المرتجع:</span><span>${fmt(doc.grand_total || doc.total_amount)}</span></div>
      ${doc.return_reason ? `<div class="totals-row" style="color:#666"><span>سبب المرتجع:</span><span>${esc(doc.return_reason)}</span></div>` : ''}
    </div>

    ${cfg.footerEnabled && cfg.footerText
      ? `<p style="text-align:center;margin-top:30px;font-size:13px;color:#64748b;">${esc(cfg.footerText)}</p>`
      : ''}
  <script>window.onload=()=>{window.print();setTimeout(()=>{(${clearPrintConfigCache.toString()})();window.close();},500);};<\/script>
  </body></html>`;
};

/**
 * Returns the appropriate builder based on template setting
 */
export const buildReturnHtml = (doc) => {
  const t = (localStorage.getItem('pos_print_template') || 'thermal-compact').toLowerCase();
  const isA4 = t === 'a4-simple' || t === 'a4-professional' || t === 'a4';
  return isA4 ? buildReturnA4Html(doc) : buildReturnThermalHtml(doc);
};
