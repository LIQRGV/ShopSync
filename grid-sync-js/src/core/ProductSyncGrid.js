/**
 * ProductSyncGrid - Unified AG Grid orchestrator for product synchronization
 * Supports both nested and flat data structures via GridDataAdapter
 *
 * @module ProductSyncGrid
 */
import { GridDataAdapter } from './GridDataAdapter.js';
import { ProductGridApiClient } from '../api/ProductGridApiClient.js';
import { GridRenderer } from '../renderers/GridRenderer.js';
import { ClipboardManager } from '../managers/ClipboardManager.js';
import { SelectionHandler } from '../managers/SelectionHandler.js';
import { CsvPreviewHandler } from '../managers/CsvPreviewHandler.js';
import { ProductSSEClient } from '../realtime/SSEClient.js';
import { ProductGridConstants } from '../constants/ProductGridConstants.js';

export class ProductSyncGrid {
    /**
     * @param {Object} config - Configuration object
     * @param {string} config.apiEndpoint - API endpoint URL
     * @param {string} [config.dataMode='auto'] - Data structure mode: 'nested', 'flat', or 'auto'
     * @param {string} [config.gridElementId='#productGrid'] - Grid container selector
     * @param {string} [config.clientId] - Client ID for multi-tenant filtering
     * @param {string} [config.clientBaseUrl=''] - Base URL for client assets
     * @param {boolean} [config.enableSSE=true] - Enable Server-Sent Events
     * @param {string} [config.sseEndpoint='/api/v1/sse/events'] - SSE endpoint URL
     * @param {string} [config.csvExportPrefix='products'] - Prefix for CSV export filename
     * @param {Array} [config.masterAttributes=[]] - Master attributes data (for flat mode)
     */
    constructor(config) {
        // Configuration
        this.config = {
            dataMode: 'auto',
            gridElementId: '#productGrid',
            enableSSE: true,
            sseEndpoint: '/api/v1/sse/events',
            clientId: null,
            clientBaseUrl: '',
            csvExportPrefix: 'products',
            masterAttributes: [],
            ...config
        };

        // Grid APIs
        this.gridApi = null;
        this.columnApi = null;

        // State
        this.selectedRows = [];
        this.currentData = null;
        this.currentMeta = null;
        this.enabledAttributes = [];
        this.initialColumnState = null;
        this.isInitialized = false;
        this.columnsUpdated = false;

        // Click tracking for enhanced editing UX
        this.lastClickedCell = null;
        this.lastClickTime = 0;
        this.CLICK_EDIT_MIN_DELAY = 300; // Minimum delay to avoid fast double-click conflicts (ms)
        this.CLICK_EDIT_MAX_DELAY = 3000; // Maximum delay for delayed edit trigger (ms)

        // Components
        this.dataAdapter = null;
        this.apiClient = null;
        this.clipboardManager = null;
        this.selectionHandler = null;
        this.csvPreviewHandler = null;
        this.gridRenderer = null;
        this.sseClient = null;
        this.sseConnectionStatus = null;

        // Initialize
        this.initializeComponents();
    }

    /**
     * Initialize all modular components
     */
    initializeComponents() {
        // Initialize data adapter first
        this.dataAdapter = new GridDataAdapter(this.config.dataMode);

        // Initialize API client with data adapter
        this.apiClient = new ProductGridApiClient({
            baseUrl: this.config.apiEndpoint,
            clientId: this.config.clientId,
            clientBaseUrl: this.config.clientBaseUrl,
            dataAdapter: this.dataAdapter,
            dataMode: this.config.dataMode
        });

        // Initialize grid renderer with data adapter
        this.gridRenderer = new GridRenderer({
            baseUrl: this.config.clientBaseUrl || this.config.apiEndpoint,
            dataAdapter: this.dataAdapter,
            currentData: null,
            enabledAttributes: this.enabledAttributes,
            masterAttributes: this.config.masterAttributes
        });

        // Initialize SSE if enabled
        if (this.config.enableSSE) {
            this.initializeSSE();
        }
    }

    /**
     * Initialize AG Grid with configuration
     */
    async initializeGrid() {
        if (this.isInitialized) {
            // Grid already initialized, just refresh data
            this.loadProducts();
            return;
        }

        // Pre-load data to get attribute groups BEFORE initializing grid (both modes)
        let initialColumnDefs = this.gridRenderer.getColumnDefs();

        // Pre-load data for both nested and flat modes to get attribute columns
        if (this.dataAdapter.mode === 'nested' || this.dataAdapter.mode === 'flat') {
            try {
                const response = await this.apiClient.loadProducts(1, ProductGridConstants.GRID_CONFIG.PAGINATION_SIZE);
                this.gridRenderer.updateCurrentData(response);

                // Generate attribute columns from loaded data
                const attributeColumnGroups = this.gridRenderer.generateAttributeColumnGroups();

                if (attributeColumnGroups.length > 0) {
                    const urlSlugIndex = initialColumnDefs.findIndex(col =>
                        col.headerName === 'URL Slug' || col.field === 'slug' || col.field === this.dataAdapter.getFieldPath('slug')
                    );

                    const newColumnDefs = [];
                    if (urlSlugIndex > -1) {
                        newColumnDefs.push(...initialColumnDefs.slice(0, urlSlugIndex));
                        newColumnDefs.push(...attributeColumnGroups);
                        newColumnDefs.push(...initialColumnDefs.slice(urlSlugIndex));
                    } else {
                        newColumnDefs.push(...initialColumnDefs);
                        newColumnDefs.push(...attributeColumnGroups);
                    }

                    initialColumnDefs = newColumnDefs;
                }

                // Store the response to use in onGridReady
                this.initialData = response;
            } catch (error) {
                console.warn('ProductSync: Failed to pre-load attribute columns, will use default columns', error);
            }
        }

        const gridOptions = {
            columnDefs: initialColumnDefs,
            defaultColDef: this.gridRenderer.getDefaultColDef(),
            ...ProductGridConstants.AG_GRID_OPTIONS,
            paginationPageSize: ProductGridConstants.GRID_CONFIG.PAGINATION_SIZE,
            // Add context to make currentData accessible in valueGetter
            context: {
                gridInstance: this
            },

            onGridReady: (params) => {
                this.gridApi = params.api;
                this.columnApi = params.columnApi;

                // Initialize remaining components that need grid APIs
                this.clipboardManager = new ClipboardManager(this.gridApi, this.columnApi, {
                    csvExportPrefix: this.config.csvExportPrefix,
                    dataMode: this.config.dataMode,
                    gridInstance: this
                });
                this.clipboardManager.setNotificationCallback((type, message) =>
                    this.showNotification(type, message)
                );

                this.selectionHandler = new SelectionHandler(this.gridApi, this.columnApi, this.config.gridElementId);
                this.selectionHandler.setNotificationCallback((type, message) =>
                    this.showNotification(type, message)
                );

                // Initialize CSV Preview Handler (separate from grid-sync core)
                this.csvPreviewHandler = new CsvPreviewHandler({
                    apiClient: this.apiClient,
                    showNotification: (type, message) => this.showNotification(type, message),
                    onImportSuccess: () => this.refresh()
                });

                // Setup UI event listeners and keyboard shortcuts
                this.setupEventListeners();
                this.setupKeyboardShortcuts();

                // Load initial data (or use pre-loaded data if available)
                if (this.initialData) {
                    // Use pre-loaded data
                    this.currentData = this.initialData;
                    this.currentMeta = this.initialData.meta;
                    const gridData = this.apiClient.transformApiData(this.initialData);
                    this.gridApi.setRowData(gridData);
                    this.updateStats();
                    this.updatePaginationInfo(1);
                    this.columnsUpdated = true; // Mark as already updated
                    delete this.initialData; // Clear after use
                } else {
                    this.loadProducts();
                }

                // Setup post-initialization tasks
                setTimeout(() => {
                    this.initialColumnState = this.columnApi.getColumnState();
                    this.enforceColumnOrder();
                }, 500);

                this.isInitialized = true;
            },

            onSelectionChanged: () => {
                this.selectedRows = this.gridApi.getSelectedRows();
                this.updateSelectionInfo();
            },

            onFilterChanged: () => {
                this.updateStats();
            },

            onCellValueChanged: (event) => {
                this.handleCellEdit(event);
            },

            onCellClicked: (event) => {
                // Handle image cell clicks
                if (event.colDef.colId === 'image') {
                    const productId = event.data.id;
                    const imageUrl = this.dataAdapter.getValue(event.data, 'image');
                    this.openImagePicker(productId, imageUrl);
                    return;
                }

                if (this.selectionHandler) {
                    this.selectionHandler.handleCellClick(event);
                }

                // Enhanced editing UX: Handle delayed edit clicks
                this.handleEnhancedCellClick(event);
            },

            onCellMouseDown: (event) => {
                if (this.selectionHandler) {
                    this.selectionHandler.handleCellMouseDown(event);
                }
            },

            onCellMouseOver: (event) => {
                if (this.selectionHandler) {
                    this.selectionHandler.handleCellMouseOver(event);
                }
            },

            getRowId: (params) => params.data.id
        };

        // Create AG Grid instance
        const gridElement = document.querySelector(this.config.gridElementId);
        if (gridElement && typeof agGrid !== 'undefined') {
            new agGrid.Grid(gridElement, gridOptions);
        } else {
            console.error(`Grid element "${this.config.gridElementId}" not found or AG Grid not loaded`);
        }
    }

    /**
     * Load products from API
     */
    async loadProducts(page = 1) {
        try {
            const response = await this.apiClient.loadProducts(page, ProductGridConstants.GRID_CONFIG.PAGINATION_SIZE);

            this.currentData = response;
            this.currentMeta = response.meta;

            // Update renderer with new data (for relationships lookup in nested mode)
            this.gridRenderer.updateCurrentData(response);

            // Column definitions already set during grid initialization (pre-loaded)
            // No need to call setColumnDefs() here which would cause reordering issues

            // Transform data for grid display using data adapter
            const gridData = this.apiClient.transformApiData(response);

            // Set row data
            if (this.gridApi) {
                this.gridApi.setRowData(gridData);
                this.updateStats();
                this.updatePaginationInfo(page);
            }

        } catch (error) {
            this.showNotification('error', error.message);
        }
    }

    /**
     * Handle cell edit operations
     */
    async handleCellEdit(event) {
        const { data, colDef, newValue, oldValue } = event;

        if (newValue === oldValue) {
            return;
        }

        const productId = data.id;
        const fieldName = colDef.field;

        try {
            // Check if this is a relationship update (category, brand, supplier)
            if (data._relationshipUpdate) {
                const { field, value } = data._relationshipUpdate;

                // Update relationship via API
                const result = await this.apiClient.updateProductRelationship(
                    productId,
                    field,
                    value
                );

                // Update local data with response
                if (result.data) {
                    const updatedFields = result.data.attributes || result.data;
                    Object.keys(updatedFields).forEach(key => {
                        this.dataAdapter.setValue(data, key, updatedFields[key]);
                    });
                }

                // Clear temp data
                delete data._relationshipUpdate;

                // Refresh the cell
                this.gridApi.refreshCells({
                    rowNodes: [event.node],
                    force: true
                });

                this.showNotification('success', 'Relationship updated successfully');
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
                    if (result.included && this.currentData && this.currentData.included) {
                        // CRITICAL FIX: DO NOT replace entire includedItem object!
                        // Only update _productValues for this specific product
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'attributes') {
                                const existingIndex = this.currentData.included.findIndex(
                                    item => item.type === 'attributes' && item.id === includedItem.id
                                );

                                // Extract pivot value from response
                                const pivotValue = (includedItem.attributes && includedItem.attributes.pivot)
                                    ? includedItem.attributes.pivot.value || ''
                                    : '';

                                if (existingIndex >= 0) {
                                    // DON'T replace object! Just update _productValues for THIS product
                                    if (!this.currentData.included[existingIndex]._productValues) {
                                        this.currentData.included[existingIndex]._productValues = {};
                                    }
                                    this.currentData.included[existingIndex]._productValues[String(productId)] = pivotValue;
                                    this.currentData.included[existingIndex]._productValues[Number(productId)] = pivotValue;
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
                                    this.currentData.included.push(newAttr);
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
                    if (result.included && this.currentData && this.currentData.included) {
                        result.included.forEach(includedItem => {
                            if (includedItem.type === 'attributes') {
                                const existingIndex = this.currentData.included.findIndex(
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
                                    if (!this.currentData.included[existingIndex]._productValues) {
                                        this.currentData.included[existingIndex]._productValues = {};
                                    }
                                    this.currentData.included[existingIndex]._productValues[String(productId)] = pivotValue;
                                    this.currentData.included[existingIndex]._productValues[Number(productId)] = pivotValue;
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
                                    this.currentData.included.push(newAttr);
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

    /**
     * Update grid statistics
     */
    updateStats() {
        if (!this.currentMeta) return;

        const totalRecords = this.currentMeta.pagination.total;
        const displayedRows = this.gridApi ? this.gridApi.getDisplayedRowCount() : 0;

        const totalElement = document.getElementById('totalRecords') ||
                            document.getElementById('shop-total-records');
        const filteredElement = document.getElementById('filteredRecords') ||
                               document.getElementById('shop-filtered-records');

        if (totalElement) {
            totalElement.textContent = `${totalRecords} total products`;
        }

        if (filteredElement && displayedRows !== this.currentMeta.pagination.count) {
            filteredElement.textContent = `${displayedRows} filtered`;
        } else if (filteredElement) {
            filteredElement.textContent = '';
        }
    }

    /**
     * Update pagination information
     */
    updatePaginationInfo(currentPage) {
        if (!this.currentMeta) return;

        const pagination = this.currentMeta.pagination;
        const currentPageElement = document.getElementById('currentPage') ||
                                   document.getElementById('shop-current-page');

        if (currentPageElement) {
            currentPageElement.textContent = `Page ${currentPage} of ${pagination.total_pages}`;
        }

        this.createPaginationControls(currentPage, pagination.total_pages);
    }

    /**
     * Create pagination controls
     */
    createPaginationControls(currentPage, totalPages) {
        // Determine pagination ID and grid instance name based on grid element
        const paginationId = this.config.gridElementId.includes('shop') ?
                           'shopCustomPagination' : 'customPagination';
        const gridInstanceName = this.config.gridElementId.includes('shop') ?
                                'shopProductGrid' : 'productGrid';

        // Remove existing pagination
        const existingPagination = document.getElementById(paginationId);
        if (existingPagination) {
            existingPagination.remove();
        }

        if (totalPages <= 1) return;

        // Create new pagination
        const paginationDiv = document.createElement('div');
        paginationDiv.id = paginationId;
        paginationDiv.className = 'mt-3 d-flex justify-content-center';

        let paginationHTML = '<nav><ul class="pagination pagination-sm">';

        // Previous button
        if (currentPage > 1) {
            paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="window.${gridInstanceName}.loadProducts(${currentPage - 1}); return false;">Previous</a></li>`;
        }

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            paginationHTML += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="window.${gridInstanceName}.loadProducts(${i}); return false;">${i}</a></li>`;
        }

        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="window.${gridInstanceName}.loadProducts(${currentPage + 1}); return false;">Next</a></li>`;
        }

        paginationHTML += '</ul></nav>';
        paginationDiv.innerHTML = paginationHTML;

        // Append after grid container
        const gridContainer = document.querySelector('.ag-grid-container');
        if (gridContainer) {
            gridContainer.appendChild(paginationDiv);
        }
    }

    /**
     * Update selection information display
     */
    updateSelectionInfo() {
        const count = this.selectedRows.length;
        const selectedElement = document.getElementById('selectedRecords') ||
                               document.getElementById('shop-selected-records');

        if (selectedElement) {
            selectedElement.textContent = `${count} selected`;
        }
    }

    /**
     * Enforce column order using applyColumnState for atomic operation
     * This prevents AG Grid from reorganizing columns during visibility changes
     */
    enforceColumnOrder() {
        if (!this.columnApi || !this.initialColumnState) {
            console.warn('[ProductSyncGrid] Cannot enforce order: missing columnApi or initialColumnState');
            return;
        }

        // Get current column state to preserve visibility and other properties
        const currentState = this.columnApi.getColumnState();

        // Build a map of current column properties by colId
        const currentPropsMap = {};
        currentState.forEach(col => {
            currentPropsMap[col.colId] = col;
        });

        // Build new state based on initial order, preserving current visibility
        const newState = this.initialColumnState.map(initialCol => {
            const currentCol = currentPropsMap[initialCol.colId];
            if (currentCol) {
                // Preserve current properties but enforce initial order
                return {
                    ...currentCol,
                    // Ensure pinned property is preserved from initial state
                    pinned: initialCol.pinned || null
                };
            }
            return initialCol;
        });

        // Add any dynamic columns (like attributes) that weren't in initial state
        currentState.forEach(col => {
            const existsInNew = newState.find(s => s.colId === col.colId);
            if (!existsInNew) {
                newState.push(col);
            }
        });

        // Apply the complete state atomically
        this.columnApi.applyColumnState({
            state: newState,
            applyOrder: true
        });

        console.log('[ProductSyncGrid] Column order enforced via applyColumnState');
    }

    /**
     * Set visibility for a group of columns (Issue #183)
     * @param {string} groupName - Group name (essential, content, marketing, attributes)
     * @param {boolean} visible - Show or hide
     */
    setColumnGroupVisibility(groupName, visible) {
        if (!this.columnApi) {
            console.warn('[ProductSyncGrid] Column API not initialized yet');
            return;
        }

        // Columns that should always be visible regardless of group selection
        const ALWAYS_VISIBLE_COLUMNS = ['image', 'productName'];

        let columnIds = ProductGridConstants.COLUMN_GROUPS[groupName];

        // For attributes group, get all attribute_* columns dynamically
        if (groupName === 'attributes') {
            const allColumns = this.columnApi.getAllColumns();
            columnIds = allColumns
                .filter(col => col.getColId().startsWith('attribute_'))
                .map(col => col.getColId());
        }

        // Filter out columns that don't exist in current grid
        if (columnIds && columnIds.length > 0) {
            const existingColumnIds = columnIds.filter(colId => {
                const col = this.columnApi.getColumn(colId);
                return col !== null && col !== undefined;
            });

            // IMPORTANT: Essential group contains pinned columns (image, productName)
            // When hiding other groups (content/marketing/attributes), we should NOT hide essential
            // But when showing other groups, we should ALSO hide essential
            // So the logic is: only apply visibility to non-essential groups, keep essential always visible

            if (existingColumnIds.length > 0) {

                // Get current state to preserve width and sort
                const currentState = this.columnApi.getColumnState();
                const currentPropsMap = {};
                currentState.forEach(col => {
                    currentPropsMap[col.colId] = {
                        width: col.width,
                        hide: col.hide,
                        sort: col.sort,
                        sortIndex: col.sortIndex
                    };
                });

                // CRITICAL: Rebuild state strictly following initialColumnState order
                // Step 1: Create map of all columns (initial + dynamic)
                const allColumnsMap = {};

                // Add initial columns
                this.initialColumnState.forEach(col => {
                    allColumnsMap[col.colId] = col;
                });

                // Add dynamic columns (attributes) that aren't in initial state
                currentState.forEach(col => {
                    if (!allColumnsMap[col.colId]) {
                        allColumnsMap[col.colId] = col;
                    }
                });

                // Step 2: Build ordered state array following strict order:
                // Pinned left (in initial order) -> Center (in initial order + dynamic attributes) -> Pinned right
                const newState = [];

                // Add pinned left columns in their initial order
                this.initialColumnState.forEach(col => {
                    if (col.pinned === 'left') {
                        const currentProps = currentPropsMap[col.colId] || {};
                        const isInTargetGroup = existingColumnIds.includes(col.colId);
                        const isAlwaysVisible = ALWAYS_VISIBLE_COLUMNS.includes(col.colId);
                        const hide = isAlwaysVisible ? false : (isInTargetGroup ? !visible : (currentProps.hide !== undefined ? currentProps.hide : col.hide));

                        newState.push({
                            colId: col.colId,
                            hide: hide,
                            width: currentProps.width || col.width,
                            pinned: col.pinned,
                            sort: currentProps.sort || null,
                            sortIndex: currentProps.sortIndex || null
                        });
                    }
                });

                // Add center columns in their initial order, then dynamic attributes
                this.initialColumnState.forEach(col => {
                    if (!col.pinned) {
                        const currentProps = currentPropsMap[col.colId] || {};
                        const isInTargetGroup = existingColumnIds.includes(col.colId);
                        const isAlwaysVisible = ALWAYS_VISIBLE_COLUMNS.includes(col.colId);
                        const hide = isAlwaysVisible ? false : (isInTargetGroup ? !visible : (currentProps.hide !== undefined ? currentProps.hide : col.hide));

                        newState.push({
                            colId: col.colId,
                            hide: hide,
                            width: currentProps.width || col.width,
                            pinned: null,
                            sort: currentProps.sort || null,
                            sortIndex: currentProps.sortIndex || null
                        });
                    }
                });

                // Add dynamic attribute columns (not in initial state)
                currentState.forEach(col => {
                    const existsInNew = newState.find(s => s.colId === col.colId);
                    if (!existsInNew && !col.pinned) {
                        const isInTargetGroup = existingColumnIds.includes(col.colId);
                        const hide = isInTargetGroup ? !visible : col.hide;

                        newState.push({
                            colId: col.colId,
                            hide: hide,
                            width: col.width,
                            pinned: null,
                            sort: col.sort || null,
                            sortIndex: col.sortIndex || null
                        });
                    }
                });

                // Add pinned right columns in their initial order
                this.initialColumnState.forEach(col => {
                    if (col.pinned === 'right') {
                        const currentProps = currentPropsMap[col.colId] || {};
                        const isInTargetGroup = existingColumnIds.includes(col.colId);
                        const isAlwaysVisible = ALWAYS_VISIBLE_COLUMNS.includes(col.colId);
                        const hide = isAlwaysVisible ? false : (isInTargetGroup ? !visible : (currentProps.hide !== undefined ? currentProps.hide : col.hide));

                        newState.push({
                            colId: col.colId,
                            hide: hide,
                            width: currentProps.width || col.width,
                            pinned: col.pinned,
                            sort: currentProps.sort || null,
                            sortIndex: currentProps.sortIndex || null
                        });
                    }
                });

                const sortedState = newState;

                // Apply state atomically - both visibility AND order in one operation
                this.columnApi.applyColumnState({
                    state: sortedState,
                    applyOrder: true
                });

                // CRITICAL FIX: Force full refresh of header AND rows
                // refreshHeader() fixes header column order
                // redrawRows() fixes cell content to match header
                this.gridApi.refreshHeader();
                this.gridApi.redrawRows();
            }
        }
    }

    /**
     * Get current visibility state of column groups (Issue #183)
     * @returns {Object} Object with group names as keys and boolean visibility as values
     */
    getColumnGroupsVisibility() {
        if (!this.columnApi) {
            return {};
        }

        const visibility = {};
        const groups = ['essential', 'content', 'marketing', 'attributes'];

        groups.forEach(groupName => {
            let columnIds = ProductGridConstants.COLUMN_GROUPS[groupName];

            if (groupName === 'attributes') {
                const allColumns = this.columnApi.getAllColumns();
                columnIds = allColumns
                    .filter(col => col.getColId().startsWith('attribute_'))
                    .map(col => col.getColId());
            }

            if (columnIds && columnIds.length > 0) {
                // Check if at least one column in the group is visible
                const hasVisibleColumn = columnIds.some(colId => {
                    const col = this.columnApi.getColumn(colId);
                    return col && col.isVisible();
                });
                visibility[groupName] = hasVisibleColumn;
            } else {
                visibility[groupName] = false;
            }
        });

        return visibility;
    }

    /**
     * Setup event listeners for UI controls
     */
    setupEventListeners() {
        // Download Template button
        const downloadTemplateButton = document.getElementById('downloadTemplate');
        if (downloadTemplateButton) {
            downloadTemplateButton.addEventListener('click', () => {
                this.downloadCsvTemplate();
            });
        }

        // Import CSV button
        const importCsvButton = document.getElementById('importCsv');
        if (importCsvButton) {
            importCsvButton.addEventListener('click', () => {
                this.openCsvFilePicker();
            });
        }

        // Export button
        const exportButton = document.getElementById('exportGrid') ||
                           document.getElementById('exportShopGrid');
        if (exportButton) {
            exportButton.addEventListener('click', () => {
                if (this.clipboardManager) {
                    this.clipboardManager.exportToCsv();
                }
            });
        }

        // Copy range button
        const copyButton = document.getElementById('copyRange') ||
                          document.getElementById('copyShopRange');
        if (copyButton) {
            copyButton.addEventListener('click', () => {
                if (this.clipboardManager && this.selectionHandler) {
                    this.clipboardManager.copyRangeToClipboard(
                        this.selectionHandler.getSelectedCells()
                    );
                }
            });
        }

        // Clear range button
        const clearButton = document.getElementById('clearRange') ||
                           document.getElementById('clearShopRange');
        if (clearButton) {
            clearButton.addEventListener('click', () => {
                if (this.selectionHandler) {
                    this.selectionHandler.clearSelectedRanges();
                }
            });
        }
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            const gridContainer = document.querySelector(this.config.gridElementId);
            if (!gridContainer || !gridContainer.contains(document.activeElement)) {
                return;
            }

            // Ctrl+C: Copy selected range
            if (event.ctrlKey && event.key === ProductGridConstants.KEYBOARD.COPY &&
                this.selectionHandler && this.selectionHandler.hasSelection()) {
                event.preventDefault();
                if (this.clipboardManager) {
                    this.clipboardManager.copyRangeToClipboard(
                        this.selectionHandler.getSelectedCells()
                    );
                }
                return;
            }

            // Ctrl+V: Paste from clipboard
            if (event.ctrlKey && event.key === ProductGridConstants.KEYBOARD.PASTE) {
                event.preventDefault();
                this.handleClipboardPaste();
                return;
            }
        });
    }

    /**
     * Handle clipboard paste
     */
    async handleClipboardPaste() {
        if (!this.clipboardManager || !this.selectionHandler) {
            return;
        }

        try {
            const parsedData = await this.clipboardManager.handleClipboardPaste();
            if (!parsedData) return;

            const startPosition = this.clipboardManager.getPasteStartPosition(
                this.selectionHandler.getSelectedCells()
            );
            if (!startPosition) {
                this.showNotification('error', 'Please select a cell to start pasting from');
                return;
            }

            const validation = this.clipboardManager.validatePasteOperation(
                parsedData, startPosition
            );
            if (!validation.valid) {
                this.showNotification('error', validation.error);
                return;
            }

            const { updateOperations, affectedCells } =
                this.clipboardManager.preparePasteOperations(parsedData, startPosition);

            if (updateOperations.length === 0) {
                this.showNotification('error', 'No editable cells found in paste range');
                return;
            }

            // Execute updates using API client
            const result = await this.apiClient.bulkUpdateProducts(updateOperations);

            // Update row data locally before refreshing
            updateOperations.forEach(op => {
                // Extract field name and update using dataAdapter
                let fieldName = op.gridFieldName;
                if (fieldName.startsWith('attributes.')) {
                    fieldName = fieldName.replace('attributes.', '');
                }
                this.dataAdapter.setValue(op.rowNode.data, fieldName, op.newValue);
            });

            // Refresh affected cells
            this.gridApi.refreshCells({
                rowNodes: updateOperations.map(op => op.rowNode),
                force: true
            });

            // Show summary
            if (result.errorCount === 0) {
                this.showNotification('success',
                    `Successfully updated ${result.successCount} cells`);
            } else {
                this.showNotification('error',
                    `Updated ${result.successCount} cells, ${result.errorCount} failed`);
            }

        } catch (error) {
            this.showNotification('error', `Paste operation failed: ${error.message}`);
        }
    }

    /**
     * Show notification toast
     */
    showNotification(type, message) {
        const toast = document.createElement('div');
        const config = ProductGridConstants.TOAST_CONFIG;

        // Limit notifications to maximum of 3
        const existingToasts = document.querySelectorAll('.product-grid-toast');
        if (existingToasts.length >= 3) {
            const oldestToast = existingToasts[0];
            if (oldestToast.updateInterval) {
                clearInterval(oldestToast.updateInterval);
            }
            if (oldestToast.autoCloseTimeout) {
                clearTimeout(oldestToast.autoCloseTimeout);
            }
            oldestToast.remove();
            this.repositionNotifications();
        }

        const baseClasses = config.CLASSES[type.toUpperCase()] || config.CLASSES.INFO;
        toast.className = `${baseClasses} product-grid-toast`;

        const currentToasts = document.querySelectorAll('.product-grid-toast');
        let topPosition = 20;
        currentToasts.forEach(existingToast => {
            topPosition += existingToast.offsetHeight + 10;
        });

        toast.style.cssText = `top: ${topPosition}px; right: 20px; z-index: 9999; min-width: 300px; transition: top 0.3s ease;`;

        const typeText = type.charAt(0).toUpperCase() + type.slice(1);
        toast.innerHTML = `
            <strong>${typeText}:</strong> ${message}
            <br>
            <small class="text-muted toast-timestamp" style="font-size: 0.85em;">just now</small>
            <button type="button" class="close ml-2" data-dismiss-toast>
                <span>&times;</span>
            </button>
        `;

        toast.createdAt = Date.now();

        const closeButton = toast.querySelector('[data-dismiss-toast]');
        closeButton.addEventListener('click', () => {
            if (toast.updateInterval) {
                clearInterval(toast.updateInterval);
            }
            if (toast.autoCloseTimeout) {
                clearTimeout(toast.autoCloseTimeout);
            }
            toast.remove();
            this.repositionNotifications();
        });

        document.body.appendChild(toast);

        const timestampElement = toast.querySelector('.toast-timestamp');
        toast.updateInterval = setInterval(() => {
            const elapsed = Date.now() - toast.createdAt;
            timestampElement.textContent = this.formatElapsedTime(elapsed);
        }, 10000);

        toast.autoCloseTimeout = setTimeout(() => {
            if (toast.updateInterval) {
                clearInterval(toast.updateInterval);
            }
            toast.remove();
            this.repositionNotifications();
        }, 3000);
    }

    /**
     * Format elapsed time
     */
    formatElapsedTime(milliseconds) {
        const seconds = Math.floor(milliseconds / 1000);

        if (seconds < 10) {
            return 'just now';
        } else if (seconds < 60) {
            return `${seconds} seconds ago`;
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            return minutes === 1 ? '1 minute ago' : `${minutes} minutes ago`;
        } else {
            const hours = Math.floor(seconds / 3600);
            return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
        }
    }

    /**
     * Reposition notifications
     */
    repositionNotifications() {
        const activeToasts = document.querySelectorAll('.product-grid-toast');
        let currentTop = 20;

        activeToasts.forEach(toast => {
            toast.style.top = `${currentTop}px`;
            currentTop += toast.offsetHeight + 10;
        });
    }

    /**
     * Open image picker for product
     */
    openImagePicker(productId, currentImageUrl) {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';
        fileInput.style.display = 'none';

        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleImageUpload(productId, file, currentImageUrl);
            }
            fileInput.remove();
        };

        fileInput.oncancel = () => {
            fileInput.remove();
        };

        document.body.appendChild(fileInput);
        fileInput.click();
    }

    /**
     * Handle image upload for product
     */
    async handleImageUpload(productId, file, currentImageUrl) {
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            this.showNotification('error', 'Please select a valid image file (JPEG, PNG, GIF, or WebP)');
            return;
        }

        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            this.showNotification('error', 'File size must be less than 5MB');
            return;
        }

        try {
            this.showNotification('info', 'Uploading image...');

            const formData = new FormData();
            formData.append('image', file);

            const result = await this.apiClient.uploadProductImage(productId, formData);

            if (result.data) {
                let rowNode = null;
                this.gridApi.forEachNode((node) => {
                    if (node.data && node.data.id === productId) {
                        rowNode = node;
                    }
                });

                if (rowNode) {
                    const imageUrl = (result.data.attributes && result.data.attributes.image) || result.data.image;
                    this.dataAdapter.setValue(rowNode.data, 'image', imageUrl);
                    this.gridApi.refreshCells({ rowNodes: [rowNode] });
                }
            }

            this.showNotification('success', 'Image uploaded successfully!');

        } catch (error) {
            console.error('Image upload error:', error);
            this.showNotification('error', `Upload failed: ${error.message}`);
        }
    }

    /**
     * Initialize SSE client for real-time updates
     */
    initializeSSE() {
        if (!window.EventSource) {
            console.warn('[SSE] EventSource not supported in this browser');
            return;
        }

        if (typeof ProductSSEClient === 'undefined') {
            console.warn('[SSE] ProductSSEClient not loaded');
            return;
        }

        try {
            this.sseClient = new ProductSSEClient(
                this.config.sseEndpoint,
                this.config.clientId
            );

            this.setupSSEEventHandlers();
            this.sseClient.connect();
            this.createSSEStatusIndicator();

        } catch (error) {
            console.error('[SSE] Failed to initialize SSE client:', error);
        }
    }

    /**
     * Setup SSE event handlers
     */
    setupSSEEventHandlers() {
        if (!this.sseClient) return;

        this.sseClient.on('product.updated', (data) => this.handleSSEProductUpdate(data));
        this.sseClient.on('product.created', (data) => this.handleSSEProductCreated(data));
        this.sseClient.on('product.deleted', (data) => this.handleSSEProductDeleted(data));
        this.sseClient.on('product.restored', (data) => this.handleSSEProductRestored(data));
        this.sseClient.on('product.imported', (data) => this.handleSSEProductImported(data));
        this.sseClient.on('products.bulk.updated', (data) => this.handleSSEBulkUpdate(data));
        this.sseClient.on('server.error', (data) => this.handleSSEServerError(data));
        this.sseClient.onConnectionStateChange((state, data) =>
            this.handleSSEConnectionChange(state, data)
        );
    }

    /**
     * Handle SSE product update event
     */
    handleSSEProductUpdate(data) {
        if (!this.gridApi) {
            console.warn('[SSE] Grid API not available, ignoring update');
            return;
        }

        const productId = data.product_id || data.id;
        let rowNode = null;

        this.gridApi.forEachNode((node) => {
            if (node.data && node.data.id === productId) {
                rowNode = node;
            }
        });

        if (!rowNode) {
            console.warn('[SSE] Product not found in grid:', productId);
            return;
        }

        if (rowNode) {
            const productData = data.product || data.attributes || data;

            // Handle attribute updates specially
            // ProductObserver broadcasts attributes in Eloquent format: [{id, name, pivot: {value}}]
            // But grid expects JSON:API format in currentData.included
            if (productData.attributes && Array.isArray(productData.attributes)) {
                // Initialize _attributeValues if not exists
                if (!rowNode.data._attributeValues) {
                    rowNode.data._attributeValues = {};
                }

                // Build a set of attribute IDs that are in the broadcast
                const broadcastAttributeIds = new Set(productData.attributes.map(attr => attr.id));

                // Update relationships.attributes.data to match broadcast
                if (rowNode.data.relationships && rowNode.data.relationships.attributes && rowNode.data.relationships.attributes.data) {
                    // Filter to keep only attributes in broadcast
                    rowNode.data.relationships.attributes.data = rowNode.data.relationships.attributes.data.filter(attr =>
                        broadcastAttributeIds.has(Number(attr.id))
                    );
                }

                // CRITICAL: For attributes that existed but are NOT in broadcast anymore,
                // set to empty string so they display "- Select -"
                // Check _attributeValues for previously edited attributes
                if (rowNode.data._attributeValues) {
                    Object.keys(rowNode.data._attributeValues).forEach(attrKey => {
                        const attrIdNum = Number(attrKey);
                        if (!broadcastAttributeIds.has(attrIdNum)) {
                            // Not in broadcast = deleted, set to empty (use STRING key)
                            const attrKeyString = String(attrIdNum);
                            rowNode.data._attributeValues[attrKeyString] = '';
                        }
                    });
                }

                // Also check currentData.included for attributes that had values for this product
                if (this.currentData && this.currentData.included) {
                    this.currentData.included.forEach(item => {
                        if (item.type === 'attributes' && item._productValues) {
                            const attrIdNum = Number(item.id);
                            const hasValueForThisProduct = item._productValues[String(productId)] !== undefined ||
                                                          item._productValues[Number(productId)] !== undefined;

                            if (hasValueForThisProduct && !broadcastAttributeIds.has(attrIdNum)) {
                                // Attribute had value for this product but not in broadcast = deleted
                                // Set to empty in _attributeValues (use STRING key)
                                const attrKeyString = String(attrIdNum);
                                rowNode.data._attributeValues[attrKeyString] = '';
                            }
                        }
                    });
                }

                // CRITICAL: Update ALL attributes from broadcast to ensure consistency
                // Backend broadcasts complete attribute list, so we sync with it
                if (this.currentData && this.currentData.included) {
                    // First, update attributes that ARE in broadcast
                    productData.attributes.forEach(attr => {
                        // Get the attribute value from pivot (could be empty string)
                        const attrValue = (attr.pivot && attr.pivot.value !== undefined) ? attr.pivot.value : '';

                        // Find matching attribute in included array
                        const includedIndex = this.currentData.included.findIndex(
                            item => item.type === 'attributes' && item.id === String(attr.id)
                        );

                        if (includedIndex >= 0) {
                            // Update the pivot value for this product's attribute
                            // Store in format that valueGetter can find
                            // Use both string and number keys for compatibility
                            if (!this.currentData.included[includedIndex]._productValues) {
                                this.currentData.included[includedIndex]._productValues = {};
                            }
                            this.currentData.included[includedIndex]._productValues[String(productId)] = attrValue;
                            this.currentData.included[includedIndex]._productValues[Number(productId)] = attrValue;
                        }

                        // MOST IMPORTANT: Store directly on rowNode.data for valueGetter
                        // ValueGetter checks params.data._attributeValues FIRST
                        // This is the primary source of truth for attribute values
                        // CRITICAL: Use STRING key consistently
                        if (!rowNode.data._attributeValues) {
                            rowNode.data._attributeValues = {};
                        }
                        const attrKeyString = String(attr.id);
                        rowNode.data._attributeValues[attrKeyString] = attrValue;
                    });

                    // CRITICAL: Clean up _productValues for attributes that were DELETED (not in broadcast)
                    // This prevents valueGetter fallback from finding stale pivot values
                    this.currentData.included.forEach(includedItem => {
                        if (includedItem.type === 'attributes' && includedItem._productValues) {
                            const attrId = Number(includedItem.id);
                            // If this attribute is NOT in broadcast for this product, clean it up
                            if (!broadcastAttributeIds.has(attrId)) {
                                // Remove this product's value from _productValues
                                delete includedItem._productValues[String(productId)];
                                delete includedItem._productValues[Number(productId)];
                            }
                        }
                    });
                }

                // Don't set attributes on rowNode.data as it's in wrong format
                // Remove it from productData before updating
                delete productData.attributes;
            }

            // Update regular fields using dataAdapter
            Object.keys(productData).forEach(key => {
                this.dataAdapter.setValue(rowNode.data, key, productData[key]);
            });

            // Don't call setData, just refresh the cells
            // setData might reset the row and lose our _productValues updates

            // IMPORTANT: Force refresh cells to trigger valueGetter for attributes
            // This is critical for attribute deletions to show "- Select -"
            this.gridApi.refreshCells({
                rowNodes: [rowNode],
                force: true  // Force refresh even if value hasn't changed
            });

            // Flash the entire row to show it was updated
            this.gridApi.flashCells({
                rowNodes: [rowNode],
                flashDelay: 300,
                fadeDelay: 1000
            });

            const productName = this.dataAdapter.getValue(rowNode.data, 'name') || 'Product';
            this.showNotification('info', `${productName} updated via real-time sync`);
        }
    }

    /**
     * Handle SSE product created event
     */
    handleSSEProductCreated(data) {
        if (!this.gridApi) return;

        // Refresh grid to show new product
        this.loadProducts();

        const productName = (data.attributes && data.attributes.name) || data.name || 'Product';
        this.showNotification('success', `${productName} created via real-time sync`);
    }

    /**
     * Handle SSE product deleted event
     */
    handleSSEProductDeleted(data) {
        if (!this.gridApi) return;

        let rowToRemove = null;
        this.gridApi.forEachNode((node) => {
            if (node.data && node.data.id === data.id) {
                rowToRemove = node.data;
            }
        });

        if (rowToRemove) {
            this.gridApi.applyTransaction({ remove: [rowToRemove] });

            const productName = this.dataAdapter.getValue(rowToRemove, 'name') || 'Product';
            this.showNotification('info', `${productName} deleted via real-time sync`);
        }
    }

    /**
     * Handle SSE product restored event
     */
    handleSSEProductRestored(data) {
        if (!this.gridApi) return;

        // Reload grid to show restored product
        this.loadProducts();

        const productData = data.product || data.data || data;
        const productName = (productData.attributes && productData.attributes.name) || productData.name || 'Product';
        this.showNotification('success', `${productName} restored via real-time sync`);
    }

    /**
     * Handle SSE product imported event
     */
    handleSSEProductImported(data) {
        if (!this.gridApi) return;

        this.loadProducts();

        const message = data.message || 'Products imported successfully';
        this.showNotification('success', message);
    }

    /**
     * Handle SSE bulk update event
     */
    handleSSEBulkUpdate(data) {
        if (!this.gridApi) return;

        this.loadProducts();

        const count = (data.products && data.products.length) || 0;
        this.showNotification('info', `Bulk update: ${count} products updated via real-time sync`);
    }

    /**
     * Handle SSE server error
     */
    handleSSEServerError(data) {
        console.error('[SSE] Server error:', data);
        this.showNotification('error', `Server error: ${(data && data.message) || 'Unknown error'}`);
    }

    /**
     * Handle SSE connection state change
     */
    handleSSEConnectionChange(state, data) {
        this.updateSSEStatusIndicator(state);

        // Issue #250: Disabled toast notifications for normal connect/disconnect events
        // Visual status indicator is sufficient for connection state changes
        // Only show notifications for actual errors that require user attention
        switch (state) {
            case 'connected':
                // this.showNotification('success', 'Real-time sync connected');
                // Disabled per issue #250 - visual indicator is sufficient
                break;
            case 'disconnected':
                // this.showNotification('warning', 'Real-time sync disconnected');
                // Disabled per issue #250 - visual indicator is sufficient
                break;
            case 'failed':
                this.showNotification('error', 'Real-time sync connection failed');
                break;
            case 'error':
                this.showNotification('error', `Real-time sync error: ${(data && data.message) || 'Unknown error'}`);
                break;
        }
    }

    /**
     * Create SSE status indicator
     */
    createSSEStatusIndicator() {
        let statusContainer = document.querySelector('.sse-status-indicator');

        if (!statusContainer) {
            statusContainer = document.createElement('div');
            statusContainer.className = 'sse-status-indicator';
            statusContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 1000;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                display: flex;
                align-items: center;
                gap: 8px;
                background: rgba(255, 255, 255, 0.95);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            `;

            const statusIcon = document.createElement('span');
            statusIcon.className = 'sse-status-icon';
            statusIcon.style.cssText = `
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #6c757d;
            `;

            const statusText = document.createElement('span');
            statusText.className = 'sse-status-text';
            statusText.textContent = 'Initializing...';

            statusContainer.appendChild(statusIcon);
            statusContainer.appendChild(statusText);

            document.body.appendChild(statusContainer);
        }

        this.sseConnectionStatus = statusContainer;
    }

    /**
     * Update SSE status indicator
     */
    updateSSEStatusIndicator(state) {
        if (!this.sseConnectionStatus) return;

        const statusIcon = this.sseConnectionStatus.querySelector('.sse-status-icon');
        const statusText = this.sseConnectionStatus.querySelector('.sse-status-text');

        const stateConfig = {
            connected: { bg: '#28a745', text: 'Real-time sync active', containerBg: 'rgba(40, 167, 69, 0.1)' },
            disconnected: { bg: '#dc3545', text: 'Real-time sync disconnected', containerBg: 'rgba(220, 53, 69, 0.1)' },
            reconnecting: { bg: '#ffc107', text: 'Reconnecting...', containerBg: 'rgba(255, 193, 7, 0.1)' },
            failed: { bg: '#dc3545', text: 'Connection failed', containerBg: 'rgba(220, 53, 69, 0.1)' },
            error: { bg: '#dc3545', text: 'Connection error', containerBg: 'rgba(220, 53, 69, 0.1)' }
        };

        const config = stateConfig[state] || {
            bg: '#6c757d',
            text: 'Unknown status',
            containerBg: 'rgba(108, 117, 125, 0.1)'
        };

        statusIcon.style.background = config.bg;
        statusText.textContent = config.text;
        this.sseConnectionStatus.style.background = config.containerBg;
    }

    /**
     * Refresh grid data
     */
    refresh() {
        this.loadProducts();
    }

    /**
     * Download CSV template with predefined headers
     */
    downloadCsvTemplate() {
        const headers = [
            'Product Name',
            'SKU Prefix',
            'SKU Value',
            'SKU Custom Ref',
            'Product Status',
            'Sell Status',
            'Purchase Date',
            'Current Price',
            'Sale Price',
            'Trade Price',
            'VAT Scheme',
            'Description',
            'Category',
            'Brand',
            'Supplier',
            'SEO Title',
            'SEO Keywords',
            'SEO Description',
            'URL Slug'
        ];

        // Create CSV content
        const csvContent = headers.join(',') + '\n';

        // Create blob and download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'product_import_template.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        this.showNotification('success', 'CSV template downloaded successfully');
    }

    /**
     * Open CSV file picker for import
     */
    openCsvFilePicker() {
        // Create hidden file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.csv';
        fileInput.style.display = 'none';

        // Handle file selection
        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleCsvImport(file);
            }
            // Remove input after use
            fileInput.remove();
        };

        // Handle cancel (when user closes file picker without selecting)
        fileInput.oncancel = () => {
            fileInput.remove();
        };

        // Trigger file picker
        document.body.appendChild(fileInput);
        fileInput.click();
    }

    /**
     * Handle CSV import with preview confirmation
     * Shows first 10 rows before importing
     */
    async handleCsvImport(file) {
        // Delegate to CsvPreviewHandler (CSV preview is separate from grid-sync core)
        if (this.csvPreviewHandler) {
            await this.csvPreviewHandler.handleCsvImport(file);
        } else {
            console.error('[ProductSyncGrid] CSV Preview Handler not initialized');
            this.showNotification('error', 'CSV import feature not available');
        }
    }

    /**
     * Delete a product
     * @param {number} productId - Product ID to delete
     */
    async deleteProduct(productId) {
        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }

        try {
            // Call the delete API
            await this.apiClient.deleteProduct(productId);

            // Find and remove from grid
            let rowToRemove = null;
            this.gridApi.forEachNode((node) => {
                if (node.data && node.data.id === productId) {
                    rowToRemove = node.data;
                }
            });

            if (rowToRemove) {
                const transaction = {
                    remove: [rowToRemove]
                };
                this.gridApi.applyTransaction(transaction);

                // Get product name for notification
                const productName = this.dataAdapter.getValue(rowToRemove, 'name') || 'Product';
                this.showNotification('success', `${productName} deleted successfully`);

                // Update stats
                this.updateStats();
            }

        } catch (error) {
            console.error('Delete error:', error);
            this.showNotification('error', error.message);
        }
    }

    /**
     * Cleanup method
     */
    destroy() {
        if (this.sseClient) {
            this.sseClient.destroy();
            this.sseClient = null;
        }

        if (this.sseConnectionStatus) {
            this.sseConnectionStatus.remove();
            this.sseConnectionStatus = null;
        }

        if (this.selectionHandler) {
            this.selectionHandler.destroy();
        }

        if (this.gridApi) {
            this.gridApi.destroy();
        }
    }
}

// CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ProductSyncGrid };
}
