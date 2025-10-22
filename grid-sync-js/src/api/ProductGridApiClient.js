import { GridDataAdapter } from '../core/GridDataAdapter.js';
import { ProductGridConstants } from '../constants/ProductGridConstants.js';

/**
 * ProductGridApiClient - Unified API client for product grid operations
 *
 * Handles API communications with support for both:
 * - Nested data structure (thediamondbox style)
 * - Flat data structure (marketplace-api style)
 *
 * @class ProductGridApiClient
 */
export class ProductGridApiClient {
    /**
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - API base URL
     * @param {string} [config.clientId] - Client ID for filtering (optional)
     * @param {string} [config.clientBaseUrl] - Client base URL for assets (optional)
     * @param {string} [config.dataMode] - Data mode: 'nested', 'flat', or 'auto'
     * @param {GridDataAdapter} [config.dataAdapter] - Custom data adapter instance
     */
    constructor(config) {
        this.baseUrl = config.baseUrl;
        this.clientId = config.clientId || null;
        this.clientBaseUrl = config.clientBaseUrl || '';
        this.csrfToken = this.getCsrfToken();

        // Initialize data adapter
        this.dataAdapter = config.dataAdapter || new GridDataAdapter(config.dataMode || 'auto');
    }

    /**
     * Get CSRF token from meta tag
     * @returns {string} CSRF token
     */
    getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    /**
     * Get default headers for API requests
     * @returns {Object} Headers object
     */
    getHeaders() {
        const headers = {
            ...ProductGridConstants.API_CONFIG.HEADERS,
            'X-CSRF-TOKEN': this.csrfToken
        };

        // Add client ID header if available
        if (this.clientId) {
            headers['client-id'] = this.clientId;
        }

        return headers;
    }

    /**
     * Load products with pagination and filters
     * @param {number} page - Page number
     * @param {number} perPage - Items per page
     * @returns {Promise<Object>} API response with products data
     */
    async loadProducts(page = 1, perPage = ProductGridConstants.GRID_CONFIG.PAGINATION_SIZE) {
        try {
            let url = `${this.baseUrl}?per_page=${perPage}&page=${page}`;

            // Add client ID filter if available
            if (this.clientId) {
                url += `&client_id=${this.clientId}`;
            }

            // Add includes for nested structure (thediamondbox style)
            if (this.dataAdapter.getCurrentMode() === 'nested' || this.dataAdapter.mode === 'auto') {
                url += '&include=category,brand,supplier,attributes';
            }

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Validate response structure
            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Invalid response format: missing data array');
            }

            // Ensure IDs are integers for consistent grid row identification
            data.data = data.data.map(item => {
                item.id = parseInt(item.id);
                return item;
            });

            // Auto-detect data mode if in auto mode
            if (this.dataAdapter.mode === 'auto' && data.data.length > 0) {
                this.dataAdapter.detectDataMode(data.data[0]);
            }

            return data;
        } catch (error) {
            throw new Error(`Failed to load products: ${error.message}`);
        }
    }

    /**
     * Update a single product field
     * @param {number} productId - Product ID
     * @param {string} fieldName - Field name to update
     * @param {*} value - New value
     * @returns {Promise<Object>} Update result
     */
    async updateProduct(productId, fieldName, value) {
        try {
            // Process value based on field type
            const processedValue = this.processFieldValue(fieldName, value);

            // Transform field name and value for API based on data mode
            const fieldData = this.dataAdapter.transformForApi(fieldName, processedValue);

            // Both modes use JSON:API format for updates
            // marketplace-api uses STRICT JSON:API format
            // thediamondbox also uses JSON:API format
            const updateData = {
                data: {
                    type: 'products',
                    id: String(productId),
                    attributes: fieldData
                }
            };

            const response = await fetch(`${this.baseUrl}/${productId}`, {
                method: ProductGridConstants.API_CONFIG.METHODS.PUT,
                headers: this.getHeaders(),
                body: JSON.stringify(updateData)
            });

            if (!response.ok) {
                // Try to get detailed error message
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
                    const cleanFieldName = this.dataAdapter.cleanFieldPath(fieldName);
                    if (errorData.errors && errorData.errors[cleanFieldName]) {
                        errorMessage = errorData.errors[cleanFieldName][0];
                    } else if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (parseError) {
                    // Use default error message if JSON parsing fails
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();

            // Validate response
            if (!result.data) {
                throw new Error('Invalid response format: missing data');
            }

            return result;
        } catch (error) {
            throw new Error(`Failed to update product: ${error.message}`);
        }
    }

    /**
     * Bulk update multiple products
     * @param {Array<Object>} updates - Array of update operations
     * @returns {Promise<Object>} Bulk update results
     */
    async bulkUpdateProducts(updates) {
        const results = [];
        const errors = [];

        for (const update of updates) {
            try {
                const result = await this.updateProduct(
                    update.productId,
                    update.fieldName,
                    update.value
                );
                results.push({
                    productId: update.productId,
                    success: true,
                    result
                });
            } catch (error) {
                errors.push({
                    productId: update.productId,
                    error: error.message
                });
                results.push({
                    productId: update.productId,
                    success: false,
                    error: error.message
                });
            }
        }

        return {
            results,
            errors,
            successCount: results.filter(r => r.success).length,
            errorCount: errors.length
        };
    }

    /**
     * Delete a product
     * @param {number} productId - Product ID
     * @returns {Promise<Object>} Delete result
     */
    async deleteProduct(productId) {
        try {
            const response = await fetch(`${this.baseUrl}/${productId}`, {
                method: ProductGridConstants.API_CONFIG.METHODS.DELETE,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // API now returns 200 OK with JSON body
            return await response.json();
        } catch (error) {
            throw new Error(`Failed to delete product: ${error.message}`);
        }
    }

    /**
     * Upload product image
     * @param {number} productId - Product ID
     * @param {FormData} formData - Form data with image file
     * @returns {Promise<Object>} Upload result
     */
    async uploadProductImage(productId, formData) {
        try {
            // Create headers without Content-Type (browser will set it with boundary for FormData)
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken
            };

            if (this.clientId) {
                headers['client-id'] = this.clientId;
            }

            const url = `${this.baseUrl}/${productId}/image`;

            // Build absolute URL to avoid routing issues
            const absoluteUrl = url.startsWith('http') ? url : `${window.location.origin}${url}`;

            const response = await fetch(absoluteUrl, {
                method: ProductGridConstants.API_CONFIG.METHODS.POST,
                headers: headers,
                body: formData
            });

            if (!response.ok) {
                // Try to get detailed error message
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
                    if (errorData.errors && errorData.errors.image) {
                        errorMessage = errorData.errors.image[0];
                    } else if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (parseError) {
                    // Use default error message if JSON parsing fails
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();

            // Validate response
            if (!result.data) {
                throw new Error('Invalid response format: missing data');
            }

            return result;
        } catch (error) {
            throw new Error(`Failed to upload image: ${error.message}`);
        }
    }

    /**
     * Search products
     * @param {string} query - Search query
     * @param {Object} filters - Additional filters
     * @returns {Promise<Object>} Search results
     */
    async searchProducts(query, filters = {}) {
        try {
            const searchFilters = {
                q: query,
                ...filters
            };

            // Add client ID filter if available
            if (this.clientId) {
                searchFilters.client_id = this.clientId;
            }

            // Add includes for nested structure
            if (this.dataAdapter.getCurrentMode() === 'nested' || this.dataAdapter.mode === 'auto') {
                searchFilters.include = 'category,brand,supplier,attributes';
            }

            const params = new URLSearchParams(searchFilters);

            const response = await fetch(`${this.baseUrl}/search?${params.toString()}`, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            throw new Error(`Failed to search products: ${error.message}`);
        }
    }

    /**
     * Fetch enabled attributes for dynamic columns
     * @returns {Promise<Array>} Array of enabled attributes
     */
    async fetchEnabledAttributes() {
        try {
            // For WTM mode (nested), fetch productAttributes to get enabled attributes
            const url = `${this.baseUrl}?per_page=1&include=productAttributes`;

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Extract unique attributes from included productAttributes
            if (data.included && data.included.length > 0) {
                const attributes = new Map();

                // Find all product_attributes in included
                const productAttributes = data.included.filter(inc => inc.type === 'product_attributes');

                // Extract unique attributes from productAttributes relationships
                productAttributes.forEach(prodAttr => {
                    if (prodAttr.relationships && prodAttr.relationships.attribute && prodAttr.relationships.attribute.data) {
                        const attrId = prodAttr.relationships.attribute.data.id;

                        // Find the actual attribute in included
                        const attr = data.included.find(inc =>
                            inc.type === 'attributes' && inc.id === attrId
                        );

                        if (attr && !attributes.has(attrId)) {
                            attributes.set(attrId, {
                                id: attr.id,
                                name: attr.attributes.name,
                                code: attr.attributes.code || `attr_${attr.id}`,
                                type: attr.attributes.type || 'text',
                                sort_order: attr.attributes.sort_order || 0
                            });
                        }
                    }
                });

                const result = Array.from(attributes.values()).sort((a, b) => a.sort_order - b.sort_order);
                return result;
            }

            return [];
        } catch (error) {
            console.error('[fetchEnabledAttributes] Error:', error);
            return [];
        }
    }

    /**
     * Export products
     * @param {string} format - Export format (csv, xlsx, etc.)
     * @param {Object} filters - Export filters
     * @returns {Promise<Response>} Export response for download
     */
    async exportProducts(format = 'csv', filters = {}) {
        try {
            const exportFilters = {
                format,
                ...filters
            };

            // Add client ID filter if available
            if (this.clientId) {
                exportFilters.client_id = this.clientId;
            }

            const params = new URLSearchParams(exportFilters);

            const response = await fetch(`${this.baseUrl}/export?${params.toString()}`, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response; // Return response for further processing (e.g., download)
        } catch (error) {
            throw new Error(`Failed to export products: ${error.message}`);
        }
    }

    /**
     * Import products from file
     * @param {FormData} formData - Form data with import file
     * @returns {Promise<Object>} Import result
     */
    async importProducts(formData) {
        try {
            const headers = {
                'X-CSRF-TOKEN': this.csrfToken
                // Don't set Content-Type for FormData, let browser handle it
            };

            if (this.clientId) {
                headers['client-id'] = this.clientId;
            }

            const response = await fetch(`${this.baseUrl}/import`, {
                method: ProductGridConstants.API_CONFIG.METHODS.POST,
                headers,
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            throw new Error(`Failed to import products: ${error.message}`);
        }
    }

    /**
     * Get product status information
     * @returns {Promise<Object>} Product status data
     */
    async getProductStatus() {
        try {
            const response = await fetch(`${this.baseUrl}/status`, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            throw new Error(`Failed to get product status: ${error.message}`);
        }
    }

    /**
     * Process field value based on field type for API
     * @param {string} fieldName - Field name
     * @param {*} value - Field value
     * @returns {*} Processed value
     */
    processFieldValue(fieldName, value) {
        // Clean field name (remove 'attributes.' prefix if present)
        const cleanFieldName = this.dataAdapter.cleanFieldPath(fieldName);

        switch (cleanFieldName) {
            case 'status':
                return typeof value === 'string'
                    ? ProductGridConstants.getProductStatusNumeric(value)
                    : value;

            case 'sell_status':
                return typeof value === 'string'
                    ? ProductGridConstants.getSellStatusNumeric(value)
                    : value;

            case 'vat_scheme':
                return typeof value === 'string'
                    ? ProductGridConstants.getVatSchemeNumeric(value)
                    : value;

            default:
                return value;
        }
    }

    /**
     * Transform API data for grid display
     * Delegates to GridDataAdapter for structure transformation
     * @param {Object} apiResponse - API response
     * @returns {Array} Transformed grid data
     */
    transformApiData(apiResponse) {
        return this.dataAdapter.transformForGrid(apiResponse);
    }

    /**
     * Validate response data structure
     * @param {Object} response - API response
     * @param {Array<string>} requiredFields - Required field names
     * @returns {boolean} Validation result
     */
    validateResponse(response, requiredFields = ['data']) {
        for (const field of requiredFields) {
            if (!(field in response)) {
                throw new Error(`Invalid response format: missing ${field}`);
            }
        }
        return true;
    }

    /**
     * Handle API errors consistently
     * @param {Error} error - Error object
     * @param {string} context - Error context
     * @returns {Error} Formatted error
     */
    handleApiError(error, context = 'API request') {
        if (error.name === 'NetworkError') {
            return new Error(`Network error during ${context}. Please check your connection.`);
        }

        if (error.name === 'TimeoutError') {
            return new Error(`Request timeout during ${context}. Please try again.`);
        }

        return new Error(`${context} failed: ${error.message}`);
    }
}

// Export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ProductGridApiClient };
}
