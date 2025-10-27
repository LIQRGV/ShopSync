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
            enabledAttributes: this.enabledAttributes
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

        // Pre-load data to get attribute groups BEFORE initializing grid (nested mode only)
        let initialColumnDefs = this.gridRenderer.getColumnDefs();

        if (this.dataAdapter.mode === 'nested') {
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

        // Extract field name from colDef.field, handling both nested and flat paths
        let fieldName = colDef.field;
        if (fieldName.startsWith('attributes.')) {
            fieldName = fieldName.replace('attributes.', '');
        }

        try {
            const productId = data.id;

            const result = await this.apiClient.updateProduct(productId, fieldName, newValue);

            // Update local data with server response
            if (result.data) {
                // Use dataAdapter to update the field
                const updatedFields = result.data.attributes || result.data;
                Object.keys(updatedFields).forEach(key => {
                    this.dataAdapter.setValue(data, key, updatedFields[key]);
                });
            }

            // Refresh the cell
            this.gridApi.refreshCells({ rowNodes: [event.node] });

        } catch (error) {
            // Revert the change
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
     * Enforce column order (prevents accidental column reordering)
     */
    enforceColumnOrder() {
        if (!this.columnApi || !this.initialColumnState) {
            return;
        }

        const allColumns = this.columnApi.getAllGridColumns();
        const initialPositions = {};

        this.initialColumnState.forEach((col, index) => {
            initialPositions[col.colId] = index;
        });

        const sortedColumns = allColumns.sort((a, b) => {
            const aPos = initialPositions[a.getColId()] || 999;
            const bPos = initialPositions[b.getColId()] || 999;
            return aPos - bPos;
        });

        sortedColumns.forEach((col, index) => {
            const currentIndex = this.columnApi.getAllGridColumns().indexOf(col);
            if (currentIndex !== index) {
                this.columnApi.moveColumn(col, index);
            }
        });
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
            toast.remove();
            this.repositionNotifications();
        });

        document.body.appendChild(toast);

        const timestampElement = toast.querySelector('.toast-timestamp');
        toast.updateInterval = setInterval(() => {
            const elapsed = Date.now() - toast.createdAt;
            timestampElement.textContent = this.formatElapsedTime(elapsed);
        }, 10000);
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
            console.warn('[SSE] EventSource is not supported in this browser');
            return;
        }

        if (typeof ProductSSEClient === 'undefined') {
            console.warn('[SSE] ProductSSEClient not loaded, SSE features disabled');
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
        if (!this.gridApi) return;

        const productId = data.product_id || data.id;
        let rowNode = null;

        this.gridApi.forEachNode((node) => {
            if (node.data && node.data.id === productId) {
                rowNode = node;
            }
        });

        if (rowNode) {
            const productData = data.product || data.attributes || data;

            // Update fields using dataAdapter
            Object.keys(productData).forEach(key => {
                this.dataAdapter.setValue(rowNode.data, key, productData[key]);
            });

            rowNode.setData(rowNode.data);

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

        switch (state) {
            case 'connected':
                this.showNotification('success', 'Real-time sync connected');
                break;
            case 'disconnected':
                this.showNotification('warning', 'Real-time sync disconnected');
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
        // Validate file type
        if (!file.name.endsWith('.csv')) {
            this.showNotification('error', 'Please select a valid CSV file');
            return;
        }

        try {
            // Step 1: Parse CSV and get preview data (first 10 rows)
            const previewData = await this.parseCsvPreview(file, 10);

            if (!previewData || previewData.rows.length === 0) {
                this.showNotification('error', 'CSV file is empty or invalid');
                return;
            }

            // Step 2: Show confirmation modal with preview
            const userConfirmed = await this.showImportPreviewModal(file, previewData);

            if (!userConfirmed) {
                this.showNotification('info', 'Import cancelled');
                return;
            }

            // Step 3: Proceed with actual import
            this.showNotification('info', 'Importing CSV file...');

            // Create form data
            const formData = new FormData();
            formData.append('file', file);

            // Upload to API endpoint using apiClient
            const result = await this.apiClient.importProducts(formData);

            // Show success message
            this.showNotification('success', result.message || 'CSV imported successfully!');

            // Refresh the grid to show imported data
            this.refresh();

        } catch (error) {
            console.error('CSV import error:', error);
            this.showNotification('error', `Import failed: ${error.message}`);
        }
    }

    /**
     * Parse CSV file and extract first N rows for preview
     * @param {File} file - CSV file to parse
     * @param {number} maxRows - Maximum number of rows to preview (default: 10)
     * @returns {Promise<Object>} Preview data with headers and rows
     */
    async parseCsvPreview(file, maxRows = 10) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                try {
                    const csvText = e.target.result;
                    const lines = csvText.split('\n').filter(line => line.trim() !== '');

                    if (lines.length === 0) {
                        resolve(null);
                        return;
                    }

                    // Parse header (first line)
                    const rawHeader = this.parseCsvLine(lines[0]);

                    // Exclude image-related columns (case-insensitive)
                    const excludedColumns = ['image', 'images', 'image_url', 'image url', 'image_path', 'image path'];
                    const excludedIndices = [];
                    const header = rawHeader.filter((col, index) => {
                        const isExcluded = excludedColumns.some(exc =>
                            col.toLowerCase().trim() === exc.toLowerCase()
                        );
                        if (isExcluded) {
                            excludedIndices.push(index);
                        }
                        return !isExcluded;
                    });

                    // Calculate how many data rows exist (excluding header)
                    const totalDataRows = lines.length - 1;

                    // Determine how many rows to show in preview
                    const rowsToShow = Math.min(totalDataRows, maxRows);

                    // Parse preview rows
                    const previewRows = [];
                    for (let i = 1; i <= rowsToShow; i++) {
                        const rawRowData = this.parseCsvLine(lines[i]);

                        // Filter out excluded column values
                        const rowData = rawRowData.filter((val, index) => !excludedIndices.includes(index));

                        // Combine header with row data
                        const rowObject = {};
                        header.forEach((col, index) => {
                            rowObject[col] = rowData[index] || '';
                        });

                        previewRows.push(rowObject);
                    }

                    resolve({
                        header: header,
                        rows: previewRows,
                        totalRows: totalDataRows,
                        showingRows: rowsToShow,
                        hasMore: totalDataRows > maxRows,
                        moreRowsCount: totalDataRows > maxRows ? totalDataRows - maxRows : 0,
                        fileName: file.name,
                        fileSize: (file.size / 1024).toFixed(2) + ' KB',
                        hasExcludedColumns: excludedIndices.length > 0,
                        excludedColumnCount: excludedIndices.length
                    });

                } catch (error) {
                    reject(error);
                }
            };

            reader.onerror = (error) => reject(error);
            reader.readAsText(file);
        });
    }

    /**
     * Parse a single CSV line (handle quoted values and commas)
     * @param {string} line - CSV line to parse
     * @returns {Array<string>} Parsed values
     */
    parseCsvLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];

            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === ',' && !inQuotes) {
                result.push(current.trim());
                current = '';
            } else {
                current += char;
            }
        }

        result.push(current.trim());
        return result;
    }

    /**
     * Show import preview modal with data confirmation
     * @param {File} file - Original CSV file
     * @param {Object} previewData - Parsed preview data
     * @returns {Promise<boolean>} User confirmation (true = confirmed, false = cancelled)
     */
    async showImportPreviewModal(file, previewData) {
        return new Promise((resolve) => {
            // Create or get modal container
            let modalContainer = document.getElementById('csvPreviewModal');

            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'csvPreviewModal';
                modalContainer.className = 'modal fade';
                modalContainer.setAttribute('tabindex', '-1');
                modalContainer.setAttribute('role', 'dialog');
                modalContainer.setAttribute('aria-labelledby', 'csvPreviewModalLabel');
                modalContainer.setAttribute('aria-hidden', 'true');
                document.body.appendChild(modalContainer);
            }

            // Build preview message based on data
            let previewMessage = '';
            if (previewData.hasMore) {
                previewMessage = `Showing first ${previewData.showingRows} of ${previewData.totalRows} rows. ${previewData.moreRowsCount} more rows will be imported.`;
            } else {
                previewMessage = `Showing all ${previewData.totalRows} rows from the CSV file.`;
            }

            // Build modal HTML
            modalContainer.innerHTML = `
                <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <!-- Modal Body -->
                        <div class="modal-body">
                            <!-- File Info -->
                            <div class="alert alert-info mb-2">
                                <h6 class="mb-2"><i class="fa fa-info-circle"></i> File Information</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>File Name:</strong> ${this.escapeHtml(previewData.fileName)}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>File Size:</strong> ${previewData.fileSize}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Total Rows:</strong> ${previewData.totalRows}
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Message -->
                            <div class="alert alert-warning mb-2">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>Preview:</strong> ${previewMessage}
                            </div>

                            <!-- Preview Table -->
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-sm table-hover" style="font-size: 0.875rem;">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th style="width: 50px; font-size: 0.875rem;">#</th>
                                            ${previewData.header.map(col =>
                                                `<th style="min-width: 120px; white-space: nowrap; font-size: 0.875rem;">${this.escapeHtml(col)}</th>`
                                            ).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${previewData.rows.map((row, index) => `
                                            <tr>
                                                <td class="text-center text-muted" style="font-size: 0.875rem;"><strong>${index + 1}</strong></td>
                                                ${previewData.header.map(col => {
                                                    const value = row[col] || '';
                                                    const displayValue = value.length > 50
                                                        ? value.substring(0, 50) + '...'
                                                        : value;
                                                    return `<td title="${this.escapeHtml(value)}" style="font-size: 0.875rem;">${this.escapeHtml(displayValue)}</td>`;
                                                }).join('')}
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>

                            ${previewData.hasMore ? `
                                <div class="alert alert-secondary mt-2 mb-0">
                                    <i class="fa fa-arrow-down"></i>
                                    <strong>${previewData.moreRowsCount} more rows</strong> will be imported after confirmation.
                                </div>
                            ` : ''}
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelImportBtn">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-success" id="confirmImportBtn">
                                <i class="fa fa-check"></i> Confirm Import (${previewData.totalRows} ${previewData.totalRows === 1 ? 'row' : 'rows'})
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Inject modal styles if not already present
            this.injectModalStyles();

            // Show modal (Bootstrap or fallback)
            const useBootstrapModal = typeof $ !== 'undefined' && $.fn && $.fn.modal;

            if (useBootstrapModal) {
                // Use Bootstrap modal
                $(modalContainer).modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $(modalContainer).modal('show');

                // Handle confirm button
                $('#confirmImportBtn').off('click').on('click', () => {
                    $(modalContainer).modal('hide');
                    resolve(true);
                });

                // Handle cancel button
                $('#cancelImportBtn').off('click').on('click', () => {
                    $(modalContainer).modal('hide');
                    resolve(false);
                });

                // Handle modal hidden event
                $(modalContainer).off('hidden.bs.modal').on('hidden.bs.modal', function() {
                    // Default to false if modal closed without clicking button
                    resolve(false);
                });

            } else {
                // Fallback: Vanilla JS
                modalContainer.style.display = 'block';
                modalContainer.classList.add('show');
                document.body.classList.add('modal-open');

                const confirmBtn = document.getElementById('confirmImportBtn');
                const cancelBtn = document.getElementById('cancelImportBtn');
                const closeBtn = modalContainer.querySelector('.close');

                const closeModal = (confirmed) => {
                    modalContainer.style.display = 'none';
                    modalContainer.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    resolve(confirmed);
                };

                confirmBtn.addEventListener('click', () => closeModal(true));
                cancelBtn.addEventListener('click', () => closeModal(false));
                closeBtn.addEventListener('click', () => closeModal(false));
            }
        });
    }

    /**
     * Escape HTML to prevent XSS in preview table
     * @param {string} text - Text to escape
     * @returns {string} Escaped HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Inject modal styles dynamically if not already present
     */
    injectModalStyles() {
        // Check if styles already injected
        if (document.getElementById('csvPreviewModalStyles')) {
            return;
        }

        const styleElement = document.createElement('style');
        styleElement.id = 'csvPreviewModalStyles';
        styleElement.textContent = `
            #csvPreviewModal .modal-dialog {
                max-width: 90%;
            }

            #csvPreviewModal .table thead th {
                background-color: #f8f9fa;
                box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
            }

            #csvPreviewModal .table td {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #csvPreviewModal .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            #csvPreviewModal .close {
                opacity: 1;
            }

            /* Fallback modal styles */
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1050;
                width: 100%;
                height: 100%;
                overflow: hidden;
                outline: 0;
            }

            .modal.show {
                display: block !important;
                background-color: rgba(0, 0, 0, 0.5);
            }
        `;

        document.head.appendChild(styleElement);
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
