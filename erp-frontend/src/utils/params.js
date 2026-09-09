/**
 * API params utilities
 *
 * Rule: NEVER send null/undefined values to the backend as query params.
 * axios will serialize null as "?key=" (empty string) which confuses PHP backends.
 * Use undefined instead — axios omits undefined keys entirely.
 *
 * Usage:
 *   import { cleanParams } from '@/utils/params';
 *   const { data } = await apiClient.get('/customers', { params: cleanParams({ branch_id: selectedBranch, q: '' }) });
 *   // sends: /customers?branch_id=5  (empty string and null keys are dropped)
 */

/**
 * Removes null, undefined, and empty-string values from a params object.
 * Returns a new object with only meaningful values.
 *
 * @param {Record<string, any>} params
 * @returns {Record<string, any>}
 */
export function cleanParams(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(
      ([, v]) => v !== null && v !== undefined && v !== ''
    )
  );
}
