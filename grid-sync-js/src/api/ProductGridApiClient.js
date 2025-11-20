import { GridDataAdapter } from '../core/GridDataAdapter.js';
import { ProductGridConstants } from '../constants/ProductGridConstants.js';

/**
 * ProductGridApiClient - Unified API client for product grid operations
 *
 * Handles API communications using JSON:API format with nested structure.
 * All responses follow the JSON:API specification with data wrapped in attributes.
 *
 * @class ProductGridApiClient
 */
export class ProductGridApiClient {
    /**
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - API base URL
     * @param {string} [config.clientId] - Client ID for filtering (optional)
     * @param {string} [config.clientBaseUrl] - Client base URL for assets (optional)
     * @param {GridDataAdapter} [config.dataAdapter] - Custom data adapter instance
     */
    constructor(config) {
        this.baseUrl = config.baseUrl;
        this.clientId = config.clientId || null;
        this.clientBaseUrl = config.clientBaseUrl || '';
        this.csrfToken = this.getCsrfToken();

        // Initialize data adapter (always uses nested JSON:API format)
        this.dataAdapter = config.dataAdapter || new GridDataAdapter();
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

            // Add includes for JSON:API relationships
            // Only include category (for product-specific categories) and attributes (for dynamic columns)
            // Brands and suppliers are now fetched separately via dedicated endpoints
            url += '&include=category,attributes';

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
     * @param {string} includes - Optional includes parameter (e.g., 'category' or 'category,brand')
     * @returns {Promise<Object>} Update result
     */
    async updateProduct(productId, fieldName, value, includes = null) {
        try {
            // Process value based on field type
            const processedValue = this.processFieldValue(fieldName, value);

            // Both modes use JSON:API format for updates
            // marketplace-api uses STRICT JSON:API format
            // thediamondbox also uses JSON:API format
            const updateData = {
                data: {
                    type: 'products',
                    id: String(productId)
                }
            };

            // Handle category_id as a relationship for JSON:API compliance
            if (fieldName === 'category_id') {
                // Convert category IDs to JSON:API relationship format
                if (Array.isArray(processedValue) && processedValue.length > 0) {
                    // Multiple categories: to-many relationship
                    updateData.data.relationships = {
                        category: {
                            data: processedValue.map(id => ({
                                type: 'categories',
                                id: String(id)
                            }))
                        }
                    };
                } else if (processedValue !== null && processedValue !== undefined) {
                    // Single category: to-one relationship
                    updateData.data.relationships = {
                        category: {
                            data: {
                                type: 'categories',
                                id: String(processedValue)
                            }
                        }
                    };
                } else {
                    // Null category: clear relationship
                    updateData.data.relationships = {
                        category: {
                            data: null
                        }
                    };
                }
            } else if (fieldName === 'brand_id') {
                // Handle brand_id as a relationship for JSON:API compliance
                if (processedValue !== null && processedValue !== undefined) {
                    // Single brand: to-one relationship
                    updateData.data.relationships = {
                        brand: {
                            data: {
                                type: 'brands',
                                id: String(processedValue)
                            }
                        }
                    };
                } else {
                    // Null brand: clear relationship
                    updateData.data.relationships = {
                        brand: {
                            data: null
                        }
                    };
                }
            } else if (fieldName === 'supplier_id') {
                // Handle supplier_id as a relationship for JSON:API compliance
                if (processedValue !== null && processedValue !== undefined) {
                    // Single supplier: to-one relationship
                    updateData.data.relationships = {
                        supplier: {
                            data: {
                                type: 'suppliers',
                                id: String(processedValue)
                            }
                        }
                    };
                } else {
                    // Null supplier: clear relationship
                    updateData.data.relationships = {
                        supplier: {
                            data: null
                        }
                    };
                }
            } else {
                // Regular attributes
                const fieldData = this.dataAdapter.transformForApi(fieldName, processedValue);
                updateData.data.attributes = fieldData;
            }

            // Build URL with optional includes parameter
            let url = `${this.baseUrl}/${productId}`;
            if (includes) {
                url += `?include=${includes}`;
            }

            const response = await fetch(url, {
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
     * Update product attribute value
     * @param {number} productId - Product ID
     * @param {string|number} attributeId - Attribute ID
     * @param {*} value - New value
     * @returns {Promise<Object>} Update result
     */
    async updateProductAttribute(productId, attributeId, value) {
        try {
            // Transform for API using JSON:API format
            const updateData = {
                data: {
                    type: 'products',
                    id: String(productId),
                    attributes: {
                        attribute_id: String(attributeId),
                        value: String(value)
                    }
                }
            };

            // Add include parameter to get updated attribute data in response
            const url = `${this.baseUrl}/${productId}?include=attributes`;

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.PUT,
                headers: this.getHeaders(),
                body: JSON.stringify(updateData)
            });

            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (parseError) {
                    // Use default error message
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
            throw new Error(`Failed to update attribute: ${error.message}`);
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

            // Add includes for JSON:API relationships
            // Only include category and attributes - brands and suppliers loaded separately
            searchFilters.include = 'category,attributes';

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
     * Uses localStorage caching with TTL to minimize API calls
     * @returns {Promise<Array>} Array of enabled attributes
     */
    async fetchEnabledAttributes() {
        try {
            // Check localStorage cache first
            const cached = this.getAttributesFromCache();
            if (cached) {
                console.log('[fetchEnabledAttributes] Using cached attributes');
                return cached;
            }

            // Fetch from /attributes endpoint
            const baseApiUrl = this.baseUrl.replace('/products', '');
            const url = `${baseApiUrl}/attributes`;

            console.log('[fetchEnabledAttributes] Fetching from API:', url);

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const responseData = await response.json();
            const attributes = responseData.data || [];

            // Cache attributes with 1 hour TTL
            this.cacheAttributes(attributes, 3600000);

            console.log(`[fetchEnabledAttributes] Fetched ${attributes.length} attributes`);
            return attributes;

        } catch (error) {
            console.error('[fetchEnabledAttributes] Error:', error);
            return [];
        }
    }

    /**
     * Get attributes from localStorage cache
     * @returns {Array|null} Cached attributes or null if expired/missing
     */
    getAttributesFromCache() {
        try {
            const cacheKey = 'shopsync_attributes_cache';
            const cached = localStorage.getItem(cacheKey);

            if (!cached) {
                return null;
            }

            const { data, expiry } = JSON.parse(cached);

            // Check if expired
            if (Date.now() > expiry) {
                localStorage.removeItem(cacheKey);
                return null;
            }

            return data;
        } catch (error) {
            console.error('[getAttributesFromCache] Error:', error);
            return null;
        }
    }

    /**
     * Cache attributes in localStorage with TTL
     * @param {Array} attributes - Attributes to cache
     * @param {number} ttl - Time to live in milliseconds
     */
    cacheAttributes(attributes, ttl) {
        try {
            const cacheKey = 'shopsync_attributes_cache';
            const cacheData = {
                data: attributes,
                expiry: Date.now() + ttl
            };

            localStorage.setItem(cacheKey, JSON.stringify(cacheData));
        } catch (error) {
            console.error('[cacheAttributes] Error:', error);
        }
    }

    /**
     * Clear attributes cache
     * Useful for manual refresh or when attributes are updated
     */
    clearAttributesCache() {
        try {
            const cacheKey = 'shopsync_attributes_cache';
            localStorage.removeItem(cacheKey);
            console.log('[clearAttributesCache] Cache cleared');
        } catch (error) {
            console.error('[clearAttributesCache] Error:', error);
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
     * Load all categories for dropdown
     * @returns {Promise<Array>} Array of categories
     */
    async loadCategories() {
        try {
            // Extract base URL and replace /products with /categories
            const baseUrlWithoutProducts = this.baseUrl.replace('/products', '');
            const url = `${baseUrlWithoutProducts}/categories`;

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Return the data array (JSON:API format)
            return data.data || [];
        } catch (error) {
            console.error('[loadCategories] Error:', error);
            throw new Error(`Failed to load categories: ${error.message}`);
        }
    }

    /**
     * Load all brands for dropdown
     * @returns {Promise<Array>} Array of brands
     */
    async loadBrands() {
        try {
            // Extract base URL and replace /products with /brands
            const baseUrlWithoutProducts = this.baseUrl.replace('/products', '');
            const url = `${baseUrlWithoutProducts}/brands`;

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Return the data array (JSON:API format)
            return data.data || [];
        } catch (error) {
            console.error('[loadBrands] Error:', error);
            throw new Error(`Failed to load brands: ${error.message}`);
        }
    }

    /**
     * Load all suppliers for dropdown
     * @returns {Promise<Array>} Array of suppliers
     */
    async loadSuppliers() {
        try {
            // Extract base URL and replace /products with /suppliers
            const baseUrlWithoutProducts = this.baseUrl.replace('/products', '');
            const url = `${baseUrlWithoutProducts}/suppliers`;

            const response = await fetch(url, {
                method: ProductGridConstants.API_CONFIG.METHODS.GET,
                headers: this.getHeaders()
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Return the data array (JSON:API format)
            return data.data || [];
        } catch (error) {
            console.error('[loadSuppliers] Error:', error);
            throw new Error(`Failed to load suppliers: ${error.message}`);
        }
    }

    /**
     * Load all dropdown options at once
     * Caches the results globally for reuse
     * @returns {Promise<Object>} Object with categories, brands, and suppliers
     */
    async loadDropdownOptions() {
        try {
            // Check if already cached
            if (window._cachedDropdownOptions) {
                console.log('[loadDropdownOptions] Using cached dropdown options');
                return window._cachedDropdownOptions;
            }

            console.log('[loadDropdownOptions] Loading dropdown options from API');

            // Load all options in parallel
            const [categories, brands, suppliers] = await Promise.all([
                this.loadCategories(),
                this.loadBrands(),
                this.loadSuppliers()
            ]);

            // Cache globally
            window._cachedDropdownOptions = {
                categories,
                brands,
                suppliers
            };

            // Also cache individually for backwards compatibility
            window._cachedCategories = categories;
            window._cachedBrands = brands;
            window._cachedSuppliers = suppliers;

            console.log('[loadDropdownOptions] Loaded and cached:', {
                categoriesCount: categories.length,
                brandsCount: brands.length,
                suppliersCount: suppliers.length
            });

            return window._cachedDropdownOptions;
        } catch (error) {
            console.error('[loadDropdownOptions] Error:', error);
            throw new Error(`Failed to load dropdown options: ${error.message}`);
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
