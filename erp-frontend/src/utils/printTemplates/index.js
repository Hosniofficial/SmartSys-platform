import { buildThermalCompactHtml }   from './thermalCompact';
import { buildThermalDetailedHtml }  from './thermalDetailed';
import { buildA4SimpleHtml }         from './a4Simple';
import { buildA4ProfessionalHtml }   from './a4Professional';
import { buildReturnHtml }           from './returnTemplate';
import { buildPurchaseHtml }         from './purchaseTemplate';

export {
  buildThermalCompactHtml,
  buildThermalDetailedHtml,
  buildA4SimpleHtml,
  buildA4ProfessionalHtml,
  buildReturnHtml,
  buildPurchaseHtml,
};

export const getBuilderByTemplate = (template) => {
  switch (template) {
    case 'thermal-detailed':  return buildThermalDetailedHtml;
    case 'a4-simple':         return buildA4SimpleHtml;
    case 'a4-professional':   return buildA4ProfessionalHtml;
    case 'thermal-compact':
    default:                  return buildThermalCompactHtml;
  }
};