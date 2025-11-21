/**
 * CellEditManager - Handles all cell editing operations for ProductSyncGrid
 * Manages category, brand, supplier, attribute, and regular field updates
 *
 * @module CellEditManager
 */
export class CellEditManager {
    /**
     * @param {Object} config - Configuration object
     * @param {Object} config.gridApi - AG Grid API instance
     * @param {Object} config.columnApi - AG Grid Column API instance
     * @param {Object} config.apiClient - ProductGridApiClient instance
     * @param {Object} config.dataAdapter - GridDataAdapter instance
     * @param {Function} config.getCurrentData - Function to get current data with included relationships
     * @param {Function} config.showNotification - Callback to show notifications
     */
    constructor(config) {
        this.gridApi = config.gridApi;
        this.columnApi = config.columnApi;
        this.apiClient = config.apiClient;
        this.dataAdapter = config.dataAdapter;
        this.getCurrentData = config.getCurrentData;
        this.showNotification = config.showNotification;

        // Click tracking for enhanced editing UX
        this.lastClickedCell = null;
        this.lastClickTime = 0;
        this.CLICK_EDIT_MIN_DELAY = 300; // Minimum delay to avoid fast double-click conflicts (ms)
        this.CLICK_EDIT_MAX_DELAY = 3000; // Maximum delay for delayed edit trigger (ms)
    }

    /**
     * Handle cell edit operations
     * Supports: category updates, brand updates, supplier updates, attribute updates, regular field updates
     */
    async handleCellEdit(event) {
        const { data, colDef, newValue, oldValue } = event;

        const productId = data.id;
        const fieldName = colDef.field;

        try {
            // Check if this is a category update (before checking value equality)
            if (data._categoryUpdate) {
                const { categoryId } = data._categoryUpdate;

                const result = await this.apiClient.updateProduct(
                    productId,
                    'category_id',
                    categoryId,
                    'category' // Include category relationship in response
                );

                // Update local data with server response
                if (result.data) {
                    const updatedFields = result.data.attributes || result.data;
                    Object.keys(updatedFields).forEach(key => {
                        this.dataAdapter.setValue(data, key, updatedFields[key]);
                    });

                    // Update category relationship if included
                    if (result.data.relationships && result.data.relationships.category) {
                        data.relationships = data.relationships || {};
                        data.relationships.category = result.data.relationships.category;
                    }

                    // Update included categories if present
                    const currentData = this.getCurrentData();
                    if (result.included && currentData && currentData.included) {
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'categories') {
                                const existingIndex = currentData.included.findIndex(
                                    item => item.type === 'categories' && item.id === includedItem.id
                                );

                                if (existingIndex >= 0) {
                                    currentData.included[existingIndex] = includedItem;
                                } else {
                                    currentData.included.push(includedItem);
                                }
                            }
                        });
                    }
                }

                // Clear temp data
                delete data._categoryUpdate;

                // Show success notification
                this.showNotification('success', 'Category updated successfully');

                // Refresh the cell to show updated category name
                this.gridApi.refreshCells({
                    rowNodes: [event.node],
                    columns: ['categoryName'],
                    force: true
                });

                return;
            }

            // Check if this is a brand update
            if (data._brandUpdate) {
                const { brandId } = data._brandUpdate;

                const result = await this.apiClient.updateProduct(
                    productId,
                    'brand_id',
                    brandId,
                    'brand,supplier' // Include both brand and supplier to preserve supplier display
                );

                // Update local data with server response
                if (result.data) {
                    const updatedFields = result.data.attributes || result.data;
                    // Preserve existing values for display fields if new value is null/undefined
                    const preservedFields = ['supplier_name', 'category_name'];
                    Object.keys(updatedFields).forEach(key => {
                        // Only update if not a preserved field, or if the new value is not null/undefined
                        if (!preservedFields.includes(key) || updatedFields[key] != null) {
                            this.dataAdapter.setValue(data, key, updatedFields[key]);
                        }
                    });

                    // Update brand relationship if included
                    if (result.data.relationships && result.data.relationships.brand) {
                        data.relationships = data.relationships || {};
                        data.relationships.brand = result.data.relationships.brand;
                    }

                    // Update included brands if present
                    const currentData = this.getCurrentData();
                    if (result.included && currentData && currentData.included) {
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'brands') {
                                const existingIndex = currentData.included.findIndex(
                                    item => item.type === 'brands' && item.id === includedItem.id
                                );

                                if (existingIndex >= 0) {
                                    currentData.included[existingIndex] = includedItem;
                                } else {
                                    currentData.included.push(includedItem);
                                }
                            }
                        });
                    }
                }

                // Clear temp data
                delete data._brandUpdate;

                // Show success notification
                this.showNotification('success', 'Brand updated successfully');

                // Refresh the cell to show updated brand name
                this.gridApi.refreshCells({
                    rowNodes: [event.node],
                    columns: ['brandName'],
                    force: true
                });

                return;
            }

            // Check if this is a supplier update
            if (data._supplierUpdate) {
                const { supplierId } = data._supplierUpdate;

                const result = await this.apiClient.updateProduct(
                    productId,
                    'supplier_id',
                    supplierId,
                    'supplier,brand' // Include both supplier and brand to preserve brand display
                );

                // Update local data with server response
                if (result.data) {
                    const updatedFields = result.data.attributes || result.data;
                    // Preserve existing values for display fields if new value is null/undefined
                    const preservedFields = ['brand_name', 'category_name'];
                    Object.keys(updatedFields).forEach(key => {
                        // Only update if not a preserved field, or if the new value is not null/undefined
                        if (!preservedFields.includes(key) || updatedFields[key] != null) {
                            this.dataAdapter.setValue(data, key, updatedFields[key]);
                        }
                    });

                    // Update supplier relationship if included
                    if (result.data.relationships && result.data.relationships.supplier) {
                        data.relationships = data.relationships || {};
                        data.relationships.supplier = result.data.relationships.supplier;
                    }

                    // Update included suppliers if present
                    const currentData = this.getCurrentData();
                    if (result.included && currentData && currentData.included) {
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'suppliers') {
                                const existingIndex = currentData.included.findIndex(
                                    item => item.type === 'suppliers' && item.id === includedItem.id
                                );

                                if (existingIndex >= 0) {
                                    currentData.included[existingIndex] = includedItem;
                                } else {
                                    currentData.included.push(includedItem);
                                }
                            }
                        });
                    }
                }

                // Clear temp data
                delete data._supplierUpdate;

                // Show success notification
                this.showNotification('success', 'Supplier updated successfully');

                // Refresh the cell to show updated supplier name
                this.gridApi.refreshCells({
                    rowNodes: [event.node],
                    columns: ['supplierName'],
                    force: true
                });

                return;
            }

            // Check if this is an attribute update
            if (colDef.field.startsWith('attribute_') && data._attributeUpdate) {
                const { attributeId, newValue: actualNewValue } = data._attributeUpdate;

                // Use actualNewValue from _attributeUpdate (not event.newValue)
                // This ensures "- Select -" is converted to empty string
                const result = await this.apiClient.updateProductAttribute(
                    productId,
                    attributeId,
                    actualNewValue
                );

                // Update local data with response (including updated attribute values)
                if (result.data) {
                    // Initialize _attributeValues if not exists
                    if (!data._attributeValues) {
                        data._attributeValues = {};
                    }

                    // Update product relationships from response if available
                    if (result.data.relationships) {
                        data.relationships = result.data.relationships;
                    }

                    // CRITICAL: Update _attributeValues from API response, NOT user input
                    // Backend may transform/validate value, so we must use response value
                    let actualSavedValue = actualNewValue; // fallback to user input

                    // If response includes attribute data, extract actual saved value
                    const currentData = this.getCurrentData();
                    if (result.included && currentData && currentData.included) {
                        // CRITICAL FIX: DO NOT replace entire includedItem object!
                        // Only update _productValues for this specific product
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'attributes') {
                                const existingIndex = currentData.included.findIndex(
                                    item => item.type === 'attributes' && item.id === includedItem.id
                                );

                                // Extract pivot value from response
                                const pivotValue = (includedItem.attributes && includedItem.attributes.pivot)
                                    ? includedItem.attributes.pivot.value || ''
                                    : '';

                                if (existingIndex >= 0) {
                                    // DON'T replace object! Just update _productValues for THIS product
                                    if (!currentData.included[existingIndex]._productValues) {
                                        currentData.included[existingIndex]._productValues = {};
                                    }
                                    currentData.included[existingIndex]._productValues[String(productId)] = pivotValue;
                                    currentData.included[existingIndex]._productValues[Number(productId)] = pivotValue;
                                } else {
                                    // New attribute - create with _productValues
                                    const newAttr = {
                                        id: String(includedItem.id),
                                        type: 'attributes',
                                        attributes: includedItem.attributes,
                                        _productValues: {
                                            [String(productId)]: pivotValue,
                                            [Number(productId)]: pivotValue
                                        }
                                    };
                                    currentData.included.push(newAttr);
                                }

                                // Extract actual saved value for this specific attribute
                                if (String(includedItem.id) === String(attributeId)) {
                                    actualSavedValue = pivotValue;
                                }
                            }
                        });
                    }

                    // Update _attributeValues with actual saved value from backend
                    // CRITICAL: Use STRING key consistently across all operations
                    const attrKeyString = String(attributeId);
                    data._attributeValues[attrKeyString] = actualSavedValue;
                }

                // Clear temp data
                delete data._attributeUpdate;

                // Show success notification
                this.showNotification('success', 'Attribute updated successfully');
            } else {
                // Regular field update

                // Skip if no actual value change
                if (newValue === oldValue) {
                    return;
                }

                let fieldName = colDef.field;
                if (fieldName.startsWith('attributes.')) {
                    fieldName = fieldName.replace('attributes.', '');
                }

                const result = await this.apiClient.updateProduct(productId, fieldName, newValue);

                // Update local data with server response
                if (result.data) {
                    // Use dataAdapter to update the field
                    const updatedFields = result.data.attributes || result.data;
                    Object.keys(updatedFields).forEach(key => {
                        this.dataAdapter.setValue(data, key, updatedFields[key]);
                    });

                    // IMPORTANT: If response includes attributes, update them too
                    // This ensures data stays consistent when SSE broadcasts include attributes
                    const currentData = this.getCurrentData();
                    if (result.included && currentData && currentData.included) {
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'attributes') {
                                const existingIndex = currentData.included.findIndex(
                                    item => item.type === 'attributes' && item.id === includedItem.id
                                );

                                // Extract pivot value from API response for THIS product only
                                const pivotValue = (includedItem.attributes && includedItem.attributes.pivot)
                                    ? includedItem.attributes.pivot.value || ''
                                    : '';

                                if (existingIndex >= 0) {
                                    // CRITICAL FIX: DON'T replace entire object!
                                    // Just update _productValues for THIS product
                                    // Replacing the object would delete _productValues for OTHER products
                                    if (!currentData.included[existingIndex]._productValues) {
                                        currentData.included[existingIndex]._productValues = {};
                                    }
                                    currentData.included[existingIndex]._productValues[String(productId)] = pivotValue;
                                    currentData.included[existingIndex]._productValues[Number(productId)] = pivotValue;
                                } else {
                                    // New attribute - add with _productValues
                                    const newAttr = {
                                        id: String(includedItem.id),
                                        type: 'attributes',
                                        attributes: includedItem.attributes,
                                        _productValues: {
                                            [String(productId)]: pivotValue,
                                            [Number(productId)]: pivotValue
                                        }
                                    };
                                    currentData.included.push(newAttr);
                                }
                            }
                        });
                    }
                }

                // Show success notification for regular field updates too
                this.showNotification('success', 'Product updated successfully');
            }

            // Refresh the cell with force to ensure valueGetter is re-evaluated
            // This is critical for attributes to update correctly
            this.gridApi.refreshCells({
                rowNodes: [event.node],
                force: true  // Force refresh even if value hasn't changed
            });

        } catch (error) {
            // Revert the change
            if (data._attributeUpdate) {
                delete data._attributeUpdate;
            }

            let fieldName = colDef.field;
            if (fieldName.startsWith('attributes.')) {
                fieldName = fieldName.replace('attributes.', '');
            }
            this.dataAdapter.setValue(data, fieldName, oldValue);

            this.gridApi.refreshCells({ rowNodes: [event.node] });
            this.showNotification('error', error.message);
        }
    }

    /**
     * Handle enhanced cell click for improved editing UX
     * Allows editing through: 1) fast double-click, 2) click to select then click again
     */
    handleEnhancedCellClick(event) {
        // Only process clicks on editable cells
        if (!event.colDef.editable) {
            return;
        }

        const cellKey = `${event.rowIndex}-${event.column.colId}`;
        const currentTime = Date.now();
        const isDropdownField = typeof event.colDef.cellEditor === 'function' ||
                                event.colDef.cellEditor === 'agSelectCellEditor';

        // Check if this is a delayed second click on the same cell
        if (this.lastClickedCell === cellKey &&
            (currentTime - this.lastClickTime) > this.CLICK_EDIT_MIN_DELAY &&
            (currentTime - this.lastClickTime) < this.CLICK_EDIT_MAX_DELAY) {

            // For dropdown fields, add a small delay to ensure proper event handling
            if (isDropdownField) {
                setTimeout(() => {
                    this.gridApi.startEditingCell({
                        rowIndex: event.rowIndex,
                        colKey: event.column.colId
                    });
                }, 50);
            } else {
                // Start editing the cell programmatically
                this.gridApi.startEditingCell({
                    rowIndex: event.rowIndex,
                    colKey: event.column.colId
                });
            }

            // Reset tracking to prevent accidental re-triggers
            this.lastClickedCell = null;
            this.lastClickTime = 0;
        } else {
            // Update tracking for potential future edit trigger
            this.lastClickedCell = cellKey;
            this.lastClickTime = currentTime;
        }
    }
}

// CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CellEditManager };
}
