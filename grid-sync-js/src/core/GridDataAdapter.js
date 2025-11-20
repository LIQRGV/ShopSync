/**
 * GridDataAdapter - Data handling for JSON:API nested structure
 *
 * Handles JSON:API format where data is nested inside attributes object:
 * - { id: 1, type: "products", attributes: { name: "Product", price: 99.99 } }
 *
 * @class GridDataAdapter
 */
export class GridDataAdapter {
    /**
     * Constructor - No configuration needed as we always use JSON:API nested format
     */
    constructor() {
        // JSON:API always uses nested format
    }


    /**
     * Get field value from data object (universal accessor)
     * @param {Object} data - Row data object
     * @param {string} fieldPath - Field path (e.g., 'name' or 'attributes.name')
     * @returns {*} Field value
     */
    getValue(data, fieldPath) {
        if (!data) return null;

        // JSON:API nested structure - add attributes prefix if not present
        const fullPath = fieldPath.startsWith('attributes.') ? fieldPath : 'attributes.' + fieldPath;
        return this.getNestedValue(data, fullPath);
    }

    /**
     * Set field value in data object (universal setter)
     * @param {Object} data - Row data object
     * @param {string} fieldPath - Field path
     * @param {*} value - Value to set
     */
    setValue(data, fieldPath, value) {
        if (!data) return;

        // JSON:API nested structure - add attributes prefix if not present
        const fullPath = fieldPath.startsWith('attributes.') ? fieldPath : 'attributes.' + fieldPath;
        this.setNestedValue(data, fullPath, value);
    }

    /**
     * Get nested value using dot notation
     * @param {Object} obj - Object to traverse
     * @param {string} path - Dot notation path
     * @returns {*} Value at path
     */
    getNestedValue(obj, path) {
        if (!path) return obj;

        const parts = path.split('.');
        let current = obj;

        for (const part of parts) {
            if (current && typeof current === 'object' && part in current) {
                current = current[part];
            } else {
                return undefined;
            }
        }

        return current;
    }

    /**
     * Set nested value using dot notation
     * @param {Object} obj - Object to modify
     * @param {string} path - Dot notation path
     * @param {*} value - Value to set
     */
    setNestedValue(obj, path, value) {
        if (!path) return;

        const parts = path.split('.');
        const lastPart = parts.pop();
        let current = obj;

        // Traverse to the parent object
        for (const part of parts) {
            if (!(part in current) || typeof current[part] !== 'object') {
                current[part] = {};
            }
            current = current[part];
        }

        // Set the value
        current[lastPart] = value;
    }

    /**
     * Transform API response data for grid display
     * @param {Object} apiResponse - API response object
     * @returns {Array} Transformed data array
     */
    transformForGrid(apiResponse) {
        if (!apiResponse || !apiResponse.data) {
            return [];
        }

        const dataArray = Array.isArray(apiResponse.data) ? apiResponse.data : [apiResponse.data];

        // Keep JSON:API nested structure, ensure ID is at root level
        return dataArray.map(item => {
            if (item.id === undefined && item.attributes && item.attributes.id) {
                return { id: item.attributes.id, ...item };
            }
            return item;
        });
    }


    /**
     * Transform field update for API request
     * @param {string} fieldName - Field name from grid
     * @param {*} value - Field value
     * @returns {Object} Update payload for API
     */
    transformForApi(fieldName, value) {
        // For JSON:API nested mode, remove attributes prefix if present
        if (fieldName.startsWith('attributes.')) {
            const cleanField = fieldName.replace('attributes.', '');
            return { [cleanField]: value };
        }
        return { [fieldName]: value };
    }

    /**
     * Get field path for column definition
     * @param {string} fieldName - Base field name (e.g., 'name')
     * @returns {string} Full field path for JSON:API nested format
     */
    getFieldPath(fieldName) {
        // For JSON:API nested format, add attributes prefix if not present
        if (!fieldName.startsWith('attributes.')) {
            return `attributes.${fieldName}`;
        }
        return fieldName;
    }

    /**
     * Clean field path for API (remove attributes prefix if present)
     * @param {string} fieldPath - Field path from grid
     * @returns {string} Clean field name for API
     */
    cleanFieldPath(fieldPath) {
        return fieldPath.replace(/^attributes\./, '');
    }

}

// Export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GridDataAdapter };
}
