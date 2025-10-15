/**
 * GridDataAdapter - Unified data handling for nested vs flat structures
 *
 * Handles differences between:
 * - Nested structure: { id: 1, attributes: { name: "Product" } }
 * - Flat structure: { id: 1, name: "Product" }
 *
 * @class GridDataAdapter
 */
export class GridDataAdapter {
    /**
     * @param {string} mode - 'nested', 'flat', or 'auto' (default)
     */
    constructor(mode = 'auto') {
        this.mode = mode;
        this.detectedMode = null;
    }

    /**
     * Auto-detect data structure mode from sample data
     * @param {Object} sampleData - Sample row data
     * @returns {string} 'nested' or 'flat'
     */
    detectDataMode(sampleData) {
        if (!sampleData) return 'flat';

        // Check if data has 'attributes' property with nested fields
        if (sampleData.attributes && typeof sampleData.attributes === 'object') {
            this.detectedMode = 'nested';
            return 'nested';
        }

        this.detectedMode = 'flat';
        return 'flat';
    }

    /**
     * Get current mode (detected or manual)
     * @returns {string}
     */
    getCurrentMode() {
        if (this.mode === 'auto') {
            return this.detectedMode || 'flat';
        }
        return this.mode;
    }

    /**
     * Get field value from data object (universal accessor)
     * @param {Object} data - Row data object
     * @param {string} fieldPath - Field path (e.g., 'name' or 'attributes.name')
     * @returns {*} Field value
     */
    getValue(data, fieldPath) {
        if (!data) return null;

        const mode = this.mode === 'auto' ? this.detectDataMode(data) : this.mode;

        if (mode === 'nested') {
            // Handle nested structure
            return this.getNestedValue(data, fieldPath);
        } else {
            // Handle flat structure - remove 'attributes.' prefix if present
            const cleanPath = fieldPath.replace(/^attributes\./, '');
            return this.getNestedValue(data, cleanPath);
        }
    }

    /**
     * Set field value in data object (universal setter)
     * @param {Object} data - Row data object
     * @param {string} fieldPath - Field path
     * @param {*} value - Value to set
     */
    setValue(data, fieldPath, value) {
        if (!data) return;

        const mode = this.mode === 'auto' ? this.detectDataMode(data) : this.mode;

        if (mode === 'nested') {
            this.setNestedValue(data, fieldPath, value);
        } else {
            // Handle flat structure - remove 'attributes.' prefix if present
            const cleanPath = fieldPath.replace(/^attributes\./, '');
            this.setNestedValue(data, cleanPath, value);
        }
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

        // Auto-detect mode from first item
        if (this.mode === 'auto' && dataArray.length > 0) {
            this.detectDataMode(dataArray[0]);
        }

        const mode = this.getCurrentMode();

        if (mode === 'flat') {
            // Flatten nested structures for grid
            return dataArray.map(item => this.flattenItem(item));
        } else {
            // Keep nested structure, ensure ID is at root level
            return dataArray.map(item => {
                if (item.id === undefined && item.attributes?.id) {
                    return { id: item.attributes.id, ...item };
                }
                return item;
            });
        }
    }

    /**
     * Flatten a single data item
     * @param {Object} item - Data item
     * @returns {Object} Flattened item
     */
    flattenItem(item) {
        if (!item) return item;

        if (item.attributes && typeof item.attributes === 'object') {
            // Flatten nested attributes
            return {
                id: item.id,
                type: item.type,
                ...item.attributes,
                // Keep relationships if present
                ...(item.relationships ? { relationships: item.relationships } : {})
            };
        }

        return item;
    }

    /**
     * Transform field update for API request
     * @param {string} fieldName - Field name from grid
     * @param {*} value - Field value
     * @returns {Object} Update payload for API
     */
    transformForApi(fieldName, value) {
        const mode = this.getCurrentMode();

        if (mode === 'nested') {
            // For nested mode, wrap in attributes if not already present
            if (fieldName.startsWith('attributes.')) {
                const cleanField = fieldName.replace('attributes.', '');
                return { [cleanField]: value };
            }
            return { [fieldName]: value };
        } else {
            // For flat mode, send as-is
            return { [fieldName]: value };
        }
    }

    /**
     * Get field path for column definition
     * @param {string} fieldName - Base field name (e.g., 'name')
     * @returns {string} Full field path for current mode
     */
    getFieldPath(fieldName) {
        const mode = this.getCurrentMode();

        if (mode === 'nested' && !fieldName.startsWith('attributes.')) {
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

    /**
     * Check if data structure is nested
     * @returns {boolean}
     */
    isNested() {
        return this.getCurrentMode() === 'nested';
    }

    /**
     * Check if data structure is flat
     * @returns {boolean}
     */
    isFlat() {
        return this.getCurrentMode() === 'flat';
    }
}

// Export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GridDataAdapter };
}
