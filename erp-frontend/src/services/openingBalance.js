import apiClient from '../config/axios'

export const openingBalanceService = {
  async downloadTemplate() {
    // Returns blob response for CSV
    return await apiClient.get('/setup/opening-balance/template', { responseType: 'blob' })
  },

  async preview(items) {
    return await apiClient.post('/setup/opening-balance/preview', { items })
  },

  async commit(items, setPurchasePriceIfZero = false, purchasesOnly = false, postAccounting = true) {
    const params = new URLSearchParams()
    if (setPurchasePriceIfZero) params.set('set_purchase_price_if_zero', '1')
    if (purchasesOnly) params.set('purchases_only', '1')
    if (postAccounting) params.set('post_accounting', '1')
    
    const qs = params.toString()
    const url = qs ? `/setup/opening-balance/commit?${qs}` : '/setup/opening-balance/commit'
    
    // Ensure Authorization header is set and payload is properly formatted
    const config = {
      headers: {
        'Content-Type': 'application/json'
      }
    }
    
    return await apiClient.post(url, { items }, config)
  }
}


export default openingBalanceService
