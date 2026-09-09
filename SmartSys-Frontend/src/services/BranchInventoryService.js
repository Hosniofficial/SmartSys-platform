import apiClient from '../config/axios';

/**
 * Fetch inventory for a specific branch
 * @param {number} branchId - ID of the branch
 * @param {Object} filters - Optional filters for the inventory list
 * @returns {Promise<Object>} - Inventory data and stats
 */
export const getBranchInventory = async (branchId, filters = {}) => {
  try {
    const response = await apiClient.get(`/branches/${branchId}/stock`, { params: filters });
    return response.data;
  } catch (error) {
    console.error('Error fetching branch inventory:', error);
    throw error;
  }
};

/**
 * Add or update stock item in a branch
 * @param {number} branchId - ID of the branch
 * @param {Object} stockData - Stock item data
 * @returns {Promise<Object>} - Response data
 */
export const updateStockItem = async (branchId, stockData, options = {}) => {
  try {
    const isUpdate = options.isEdit === true; // explicit decision from caller
    // Use product_id for URL when updating, fallback to id
    const productId = stockData.product_id || stockData.id;
    const method = isUpdate ? 'put' : 'post';
    const url = isUpdate && productId
      ? `/branches/${branchId}/inventory/${productId}`
      : `/branches/${branchId}/inventory`;
    
    const response = await apiClient[method](url, stockData);
    return response.data;
  } catch (error) {
    console.error('Error updating stock item:', error);
    throw error;
  }
};

/**
 * Adjust stock quantity (add/remove)
 * @param {number} branchId - ID of the branch
 * @param {Object} adjustmentData - Adjustment data
 * @returns {Promise<Object>} - Response data
 */
export const adjustStock = async (branchId, adjustmentData) => {
  try {
    const response = await apiClient.post(
      `/branches/${branchId}/adjustments`,
      adjustmentData
    );
    return response.data;
  } catch (error) {
    console.error('Error adjusting stock:', error);
    throw error;
  }
};

/**
 * Search for products by name, barcode, or SKU
 * @param {string} query - Search query
 * @returns {Promise<Array>} - List of matching products
 */
export const searchProducts = async (query) => {
  try {
    const response = await apiClient.get('/products/search', {
      params: { q: query, limit: 10 }
    });
    return response.data.data || [];
  } catch (error) {
    console.error('Error searching products:', error);
    return [];
  }
};

/**
 * Get stock movement history for a product in a branch
 * @param {number} branchId - ID of the branch
 * @param {number} productId - ID of the product
 * @returns {Promise<Array>} - List of stock movements
 */
export const getStockMovements = async (branchId, productId) => {
  try {
    const response = await apiClient.get(
      `/branches/${branchId}/products/${productId}/movements`
    );
    return response.data.data || [];
  } catch (error) {
    console.error('Error fetching stock movements:', error);
    return [];
  }
};

/**
 * Get low stock items for a branch
 * @param {number} branchId - ID of the branch
 * @returns {Promise<Array>} - List of low stock items
 */
export const getLowStockItems = async (branchId) => {
  try {
    const response = await apiClient.get(
      `/branches/${branchId}/inventory/low-stock`
    );
    return response.data.data || [];
  } catch (error) {
    console.error('Error fetching low stock items:', error);
    return [];
  }
};
