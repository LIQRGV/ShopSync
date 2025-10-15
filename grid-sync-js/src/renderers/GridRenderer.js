/**
 * GridRenderer - Unified cell rendering and formatting for Product Grid
 * Supports both nested and flat data structures via GridDataAdapter
 */
import { ProductGridConstants } from '../constants/ProductGridConstants.js';

export class GridRenderer {
    /**
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - Base URL for links
     * @param {GridDataAdapter} config.dataAdapter - Data adapter for field access
     * @param {Object} config.currentData - Current grid data with included relationships
     */
    constructor(config) {
        this.baseUrl = config.baseUrl || '';
        this.dataAdapter = config.dataAdapter;
        this.currentData = config.currentData || null;

        // Inject custom CSS for colored dropdowns and read-only cells
        this.injectStatusDropdownCSS();
    }

    /**
     * Update current data reference (for relationships lookup)
     */
    updateCurrentData(data) {
        this.currentData = data;
    }

    /**
     * Get column definitions for the grid
     */
    getColumnDefs() {
        return [
            // Pinned left columns
            {
                colId: 'image',
                headerName: 'Image',
                field: this.dataAdapter.getFieldPath('image'),
                width: ProductGridConstants.COLUMN_WIDTHS.image,
                pinned: 'left',
                lockPosition: true,
                sortable: false,
                filter: false,
                editable: false,
                suppressNavigable: true,
                cellClass: 'read-only-cell',
                cellRenderer: (params) => this.imageCellRenderer(params),
                cellStyle: (params) => this.getCellStyle(params)
            },
            {
                colId: 'productName',
                headerName: 'Product Name',
                field: this.dataAdapter.getFieldPath('name'),
                width: ProductGridConstants.COLUMN_WIDTHS.productName,
                pinned: 'left',
                lockPosition: true,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: true,
                cellEditor: 'agTextCellEditor',
                cellRenderer: (params) => this.nameCellRenderer(params)
            },

            // SKU Group
            {
                colId: 'skuPrefix',
                headerName: 'SKU Prefix',
                field: this.dataAdapter.getFieldPath('sku_prefix'),
                width: ProductGridConstants.COLUMN_WIDTHS.skuPrefix,
                lockPosition: true,
                sortable: true,
                filter: 'agSetColumnFilter',
                editable: false,
                cellClass: 'read-only-cell',
                cellStyle: (params) => this.getCellStyle(params)
            },
            {
                colId: 'skuValue',
                headerName: 'SKU Value',
                field: this.dataAdapter.getFieldPath('rol_number'),
                width: ProductGridConstants.COLUMN_WIDTHS.skuValue,
                lockPosition: true,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: false,
                cellClass: 'read-only-cell',
                cellStyle: (params) => this.getCellStyle(params)
            },
            {
                colId: 'skuCustomRef',
                headerName: 'SKU Custom Ref',
                field: this.dataAdapter.getFieldPath('sku_custom_ref'),
                width: ProductGridConstants.COLUMN_WIDTHS.skuCustomRef,
                lockPosition: true,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: true,
                cellEditor: 'agTextCellEditor'
            },
            {
                colId: 'fullSku',
                headerName: 'Full SKU',
                field: this.dataAdapter.getFieldPath('full_sku'),
                width: ProductGridConstants.COLUMN_WIDTHS.fullSku,
                lockPosition: true,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: false,
                cellClass: 'read-only-cell',
                cellStyle: (params) => this.getCellStyle(params)
            },

            // Status Group
            {
                colId: 'productStatus',
                headerName: 'Product Status',
                field: this.dataAdapter.getFieldPath('status'),
                width: ProductGridConstants.COLUMN_WIDTHS.productStatus,
                sortable: true,
                filter: 'agSetColumnFilter',
                editable: true,
                cellRenderer: (params) => this.statusCellRenderer(params),
                cellEditor: this.getAutoOpenSelectEditor(),
                cellEditorPopup: true,
                cellEditorParams: {
                    values: ProductGridConstants.PRODUCT_STATUS.OPTIONS
                },
                valueGetter: (params) => {
                    const status = this.dataAdapter.getValue(params.data, 'status');
                    return ProductGridConstants.getProductStatusText(status);
                },
                valueSetter: (params) => {
                    const numericValue = ProductGridConstants.getProductStatusNumeric(params.newValue);
                    this.dataAdapter.setValue(params.data, 'status', numericValue);
                    return true;
                }
            },
            {
                colId: 'sellStatus',
                headerName: 'Sell Status',
                field: this.dataAdapter.getFieldPath('sell_status'),
                width: ProductGridConstants.COLUMN_WIDTHS.sellStatus,
                sortable: true,
                filter: 'agSetColumnFilter',
                editable: true,
                cellRenderer: (params) => this.sellStatusCellRenderer(params),
                cellEditor: this.getAutoOpenSelectEditor(),
                cellEditorPopup: true,
                cellEditorParams: {
                    values: ProductGridConstants.SELL_STATUS.OPTIONS
                },
                valueGetter: (params) => {
                    const sellStatus = this.dataAdapter.getValue(params.data, 'sell_status');
                    return ProductGridConstants.getSellStatusText(sellStatus);
                },
                valueSetter: (params) => {
                    const numericValue = ProductGridConstants.getSellStatusNumeric(params.newValue);
                    this.dataAdapter.setValue(params.data, 'sell_status', numericValue);
                    return true;
                }
            },

            // Date & Price Group
            {
                colId: 'purchaseDate',
                headerName: 'Purchase Date',
                field: this.dataAdapter.getFieldPath('purchase_date'),
                width: ProductGridConstants.COLUMN_WIDTHS.purchaseDate,
                sortable: true,
                filter: 'agDateColumnFilter',
                editable: true,
                valueFormatter: (params) => this.dateFormatter(params)
            },
            {
                colId: 'currentPrice',
                headerName: 'Current Price',
                field: this.dataAdapter.getFieldPath('price'),
                width: ProductGridConstants.COLUMN_WIDTHS.currentPrice,
                sortable: true,
                filter: 'agNumberColumnFilter',
                editable: true,
                valueFormatter: (params) => this.currencyFormatter(params)
            },
            {
                colId: 'salePrice',
                headerName: 'Sale Price',
                field: this.dataAdapter.getFieldPath('sale_price'),
                width: ProductGridConstants.COLUMN_WIDTHS.salePrice,
                sortable: true,
                filter: 'agNumberColumnFilter',
                editable: true,
                valueFormatter: (params) => this.currencyFormatter(params)
            },
            {
                colId: 'tradePrice',
                headerName: 'Trade Price',
                field: this.dataAdapter.getFieldPath('trade_price'),
                width: ProductGridConstants.COLUMN_WIDTHS.tradePrice,
                sortable: true,
                filter: 'agNumberColumnFilter',
                editable: true,
                valueFormatter: (params) => this.currencyFormatter(params)
            },
            {
                colId: 'vatScheme',
                headerName: 'VAT Scheme',
                field: this.dataAdapter.getFieldPath('vat_scheme'),
                width: ProductGridConstants.COLUMN_WIDTHS.vatScheme,
                sortable: true,
                filter: 'agSetColumnFilter',
                editable: true,
                cellRenderer: (params) => this.vatSchemeCellRenderer(params),
                cellEditor: this.getAutoOpenSelectEditor(),
                cellEditorPopup: true,
                cellEditorParams: {
                    values: ProductGridConstants.VAT_SCHEME.OPTIONS
                },
                valueGetter: (params) => {
                    const vatScheme = this.dataAdapter.getValue(params.data, 'vat_scheme');
                    return ProductGridConstants.getVatSchemeText(vatScheme);
                },
                valueSetter: (params) => {
                    const numericValue = ProductGridConstants.getVatSchemeNumeric(params.newValue);
                    this.dataAdapter.setValue(params.data, 'vat_scheme', numericValue);
                    return true;
                }
            },

            // Content Group
            {
                headerName: 'Description',
                field: this.dataAdapter.getFieldPath('description'),
                width: ProductGridConstants.COLUMN_WIDTHS.description,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: true,
                cellRenderer: (params) => this.truncatedTextRenderer(params)
            },

            // Relations Group (only for nested mode with relationships)
            ...(this.dataAdapter.mode === 'nested' ? [
                {
                    headerName: 'Category',
                    field: 'category_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.category,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: false,
                    valueGetter: (params) => this.getCategoryName(params),
                    cellClass: 'read-only-cell',
                    cellStyle: (params) => this.getCellStyle(params)
                },
                {
                    headerName: 'Brand',
                    field: 'brand_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.brand,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: false,
                    valueGetter: (params) => this.getBrandName(params),
                    cellClass: 'read-only-cell',
                    cellStyle: (params) => this.getCellStyle(params)
                },
                {
                    headerName: 'Supplier',
                    field: 'supplier_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.supplier,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: false,
                    valueGetter: (params) => this.getSupplierName(params),
                    cellClass: 'read-only-cell',
                    cellStyle: (params) => this.getCellStyle(params)
                }
            ] : []),

            // SEO Group (only for nested mode - thediamondbox specific)
            ...(this.dataAdapter.mode === 'nested' ? [
                {
                    headerName: 'SEO Title',
                    field: this.dataAdapter.getFieldPath('seo_title'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoTitle,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true,
                    cellRenderer: (params) => this.truncatedTextRenderer(params)
                },
                {
                    headerName: 'SEO Keywords',
                    field: this.dataAdapter.getFieldPath('seo_keywords'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoKeywords,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true
                },
                {
                    headerName: 'SEO Description',
                    field: this.dataAdapter.getFieldPath('seo_description'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoDescription,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true,
                    cellRenderer: (params) => this.truncatedTextRenderer(params)
                }
            ] : []),

            // URL Slug (both modes)
            {
                headerName: 'URL Slug',
                field: this.dataAdapter.getFieldPath('slug'),
                width: ProductGridConstants.COLUMN_WIDTHS.urlSlug,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: true,
                cellRenderer: (params) => this.truncatedTextRenderer(params)
            },

            // Actions (pinned right)
            {
                headerName: 'Actions',
                width: ProductGridConstants.COLUMN_WIDTHS.actions,
                pinned: 'right',
                sortable: false,
                filter: false,
                editable: false,
                cellRenderer: (params) => this.actionCellRenderer(params),
                cellStyle: (params) => this.getCellStyle(params)
            }
        ];
    }

    /**
     * Render product image cell
     */
    imageCellRenderer(params) {
        const imageUrl = this.dataAdapter.getValue(params.data, 'image');
        const baseUrl = (window.ShopProductGridConfig && window.ShopProductGridConfig.clientBaseUrl) ||
                       (window.ProductGridConfig && window.ProductGridConfig.baseUrl) ||
                       this.baseUrl;

        if (imageUrl && imageUrl !== 'null') {
            // Check if imageUrl is already a full URL
            const isFullUrl = imageUrl.startsWith('http://') || imageUrl.startsWith('https://');
            const finalUrl = isFullUrl ? imageUrl : `${baseUrl}/${imageUrl}`;

            return `<img src="${finalUrl}"
                         alt="Product"
                         class="product-image"
                         style="width: 40px; height: 32px; object-fit: cover; border-radius: 4px; cursor: pointer; transition: opacity 0.2s;"
                         onmouseover="this.style.opacity='0.7'"
                         onmouseout="this.style.opacity='1'"
                         title="Click to change image"
                         onerror="this.parentElement.innerHTML='<div class=&quot;no-image&quot; style=&quot;cursor: pointer; width: 40px; height: 32px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 10px;&quot;>No Image</div>'" />`;
        }
        return `<div class="no-image"
                     style="width: 40px; height: 32px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 10px; cursor: pointer; transition: background-color 0.2s;"
                     onmouseover="this.style.backgroundColor='#e9ecef'"
                     onmouseout="this.style.backgroundColor='#f8f9fa'"
                     title="Click to add image">No Image</div>`;
    }

    /**
     * Render product name cell with link
     */
    nameCellRenderer(params) {
        const productId = params.data.id;
        const name = this.dataAdapter.getValue(params.data, 'name') || 'Unnamed Product';
        const baseUrl = (window.ShopProductGridConfig && window.ShopProductGridConfig.baseUrl) ||
                       (window.ProductGridConfig && window.ProductGridConfig.baseUrl) ||
                       this.baseUrl;
        return `<a href="${baseUrl}/admin/products/${productId}" target="_blank" class="text-decoration-none" title="${name}" style="color: #727cf5;">${name}</a>`;
    }

    /**
     * Render status cell with badge
     */
    statusCellRenderer(params) {
        const status = this.dataAdapter.getValue(params.data, 'status');
        const statusText = ProductGridConstants.getProductStatusText(status);

        // Use the same color mapping as dropdown options for consistency
        const colorMap = {
            'In Stock': { bg: '#d4edda', text: '#155724' },
            'Out Of Stock': { bg: '#f8d7da', text: '#721c24' },
            'Out Of Stock & Hide': { bg: '#fff3cd', text: '#856404' },
            'Repair': { bg: '#d1ecf1', text: '#0c5460' },
            'Coming Soon': { bg: '#e2e3e5', text: '#383d41' },
            'In Stock & Hide': { bg: '#fff3cd', text: '#856404' },
            'Unlisted': { bg: '#f8d7da', text: '#721c24' }
        };

        const colors = colorMap[statusText] || { bg: '#e2e3e5', text: '#383d41' };

        return `<span class="status-badge" style="padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: ${colors.bg}; color: ${colors.text};">${statusText}</span>`;
    }

    /**
     * Render sell status cell with badge
     */
    sellStatusCellRenderer(params) {
        const sellStatus = this.dataAdapter.getValue(params.data, 'sell_status');
        const statusText = ProductGridConstants.getSellStatusText(sellStatus);

        // Define color mappings for sell status using text-based mapping for consistency
        const colorMap = {
            'Sell as Standard': { bg: '#d4edda', text: '#155724' },
            'Oversell': { bg: '#fff3cd', text: '#856404' },
            'Unknown': { bg: '#f8d7da', text: '#721c24' }
        };

        const colors = colorMap[statusText] || { bg: '#e2e3e5', text: '#383d41' };

        return `<span class="sell-status-badge" style="padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: ${colors.bg}; color: ${colors.text};">${statusText}</span>`;
    }

    /**
     * Render VAT scheme cell with badge
     */
    vatSchemeCellRenderer(params) {
        const vatScheme = this.dataAdapter.getValue(params.data, 'vat_scheme');
        const schemeText = ProductGridConstants.getVatSchemeText(vatScheme);

        // Define color mappings for VAT scheme using text-based mapping for consistency
        const colorMap = {
            'Standard Rate': { bg: '#d1ecf1', text: '#0c5460' },
            'Reduced Rate': { bg: '#fff3cd', text: '#856404' },
            'Zero Rate': { bg: '#d4edda', text: '#155724' },
            'Exempt': { bg: '#f8d7da', text: '#721c24' },
            'None': { bg: '#e2e3e5', text: '#383d41' }
        };

        const colors = colorMap[schemeText] || { bg: '#e2e3e5', text: '#383d41' };

        return `<span class="vat-scheme-badge" style="padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background-color: ${colors.bg}; color: ${colors.text};">${schemeText}</span>`;
    }

    /**
     * Render truncated text with tooltip
     */
    truncatedTextRenderer(params) {
        if (!params.value) return '';
        const text = String(params.value);
        const truncated = text.length > ProductGridConstants.GRID_CONFIG.TEXT_TRUNCATE_LENGTH
            ? text.substring(0, ProductGridConstants.GRID_CONFIG.TEXT_TRUNCATE_LENGTH) + '...'
            : text;
        return `<span title="${text.replace(/"/g, '&quot;')}">${truncated}</span>`;
    }

    /**
     * Render action buttons
     */
    actionCellRenderer(params) {
        const productId = params.data.id;

        // Use different delete function name based on context
        const deleteFn = window.ShopProductGridConfig ? 'deleteShopProduct' : 'deleteProduct';
        const iconClass = window.ShopProductGridConfig ? 'mdi mdi-trash-can' : 'fa fa-trash';

        return `
            <div class="btn-group btn-group-sm">
                <button class="btn btn-sm btn-link text-danger p-1" onclick="${deleteFn}(${productId})" title="Delete">
                    <i class="${iconClass}"></i>
                </button>
            </div>
        `;
    }

    /**
     * Format date values
     */
    dateFormatter(params) {
        if (!params.value) return '';
        if (params.value === '1970-01-01') return '';
        return new Date(params.value).toLocaleDateString('en-GB');
    }

    /**
     * Format currency values
     */
    currencyFormatter(params) {
        if (!params.value) return '';
        return `£${parseFloat(params.value).toFixed(2)}`;
    }

    /**
     * Get category name from included data (nested mode only)
     */
    getCategoryName(params) {
        const categoryData = this.findIncluded(params.data, 'categories', 'category');
        return categoryData ? this.dataAdapter.getValue(categoryData, 'name') : '';
    }

    /**
     * Get brand name from included data (nested mode only)
     */
    getBrandName(params) {
        const brandData = this.findIncluded(params.data, 'brands', 'brand');
        return brandData ? this.dataAdapter.getValue(brandData, 'name') : '';
    }

    /**
     * Get supplier name from included data (nested mode only)
     */
    getSupplierName(params) {
        const supplierData = this.findIncluded(params.data, 'suppliers', 'supplier');
        return supplierData ? (this.dataAdapter.getValue(supplierData, 'company_name') || this.dataAdapter.getValue(supplierData, 'first_name')) : '';
    }

    /**
     * Find included data by type and relation (nested mode only)
     */
    findIncluded(rowData, type, relationField) {
        if (!this.currentData || !this.currentData.included || !rowData.relationships || !rowData.relationships[relationField]) {
            return null;
        }

        const relation = rowData.relationships[relationField].data;
        if (!relation || (Array.isArray(relation) && relation.length === 0)) {
            return null;
        }

        const relId = Array.isArray(relation) ? relation[0].id : relation.id;
        return this.currentData.included.find(item => item.type === type && item.id === relId);
    }

    /**
     * Get default column definitions configuration
     */
    getDefaultColDef() {
        return ProductGridConstants.AG_GRID_OPTIONS.defaultColDef;
    }

    /**
     * Get pinned columns configuration
     */
    getPinnedColumns() {
        return {
            left: ['image', 'productName'],
            right: ['actions']
        };
    }

    /**
     * Get editable columns list
     */
    getEditableColumns() {
        return [
            'productName', 'skuCustomRef', 'productStatus', 'sellStatus',
            'purchaseDate', 'currentPrice', 'salePrice', 'tradePrice', 'vatScheme',
            'description', 'seoTitle', 'seoKeywords', 'seoDescription', 'urlSlug'
        ];
    }

    /**
     * Check if column is editable
     */
    isColumnEditable(colId) {
        return this.getEditableColumns().includes(colId);
    }

    /**
     * Apply custom cell classes based on content
     */
    getCellClass(params) {
        const classes = [];

        // Add status-specific classes
        if (params.colDef.colId === 'productStatus') {
            const status = this.dataAdapter.getValue(params.data, 'status');
            classes.push(ProductGridConstants.getProductStatusClass(status));
        }

        // Add editable indicator
        if (this.isColumnEditable(params.colDef.colId)) {
            classes.push('editable-cell');
        }

        return classes.join(' ');
    }

    /**
     * Get cell style based on content
     */
    getCellStyle(params) {
        const style = {};

        // Grey out read-only cells
        if (params.colDef.editable === false || params.colDef.cellClass === 'read-only-cell') {
            style.backgroundColor = '#f5f5f5';
        }

        // Highlight empty required fields
        if (this.isRequiredField(params.colDef.colId) && !params.value) {
            style.backgroundColor = '#fff3cd';
            style.borderLeft = '3px solid #856404';
        }

        return style;
    }

    /**
     * Check if field is required
     */
    isRequiredField(colId) {
        const requiredFields = ['productName', 'skuValue', 'productStatus'];
        return requiredFields.includes(colId);
    }

    /**
     * Inject custom CSS for status dropdown colors and read-only cells
     */
    injectStatusDropdownCSS() {
        // Check if CSS already injected
        if (document.getElementById('status-dropdown-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'status-dropdown-styles';
        style.textContent = `
            /* Read-only cell styling */
            .ag-cell.read-only-cell {
                background-color: #f5f5f5 !important;
                color: #6c757d !important;
                cursor: not-allowed !important;
            }

            /* Base styles for AG Grid select dropdown options */
            .ag-picker-field-display {
                padding: 4px 8px !important;
                border-radius: 4px !important;
                font-weight: 500 !important;
            }

            .ag-list-item.ag-select-list-item {
                padding: 8px 12px !important;
                margin: 2px 4px !important;
                border-radius: 4px !important;
                font-weight: 500 !important;
            }

            /* Status-specific classes applied by JavaScript */
            .status-dropdown-in-stock {
                background-color: #d4edda !important;
                color: #155724 !important;
            }

            .status-dropdown-out-of-stock {
                background-color: #f8d7da !important;
                color: #721c24 !important;
            }

            .status-dropdown-out-of-stock-hide {
                background-color: #fff3cd !important;
                color: #856404 !important;
            }

            .status-dropdown-repair {
                background-color: #d1ecf1 !important;
                color: #0c5460 !important;
            }

            .status-dropdown-coming-soon {
                background-color: #e2e3e5 !important;
                color: #383d41 !important;
            }

            .status-dropdown-in-stock-hide {
                background-color: #fff3cd !important;
                color: #856404 !important;
            }

            .status-dropdown-unlisted {
                background-color: #f8d7da !important;
                color: #721c24 !important;
            }

            /* Hover effects */
            .ag-list-item.ag-select-list-item:hover {
                opacity: 0.8 !important;
            }
        `;

        document.head.appendChild(style);

        // Set up observer to colorize dropdown options when they appear
        this.setupDropdownObserver();
    }

    /**
     * Setup observer to apply colors to dropdown options when they appear
     */
    setupDropdownObserver() {
        // Create a mutation observer to watch for dropdown options
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        // Check if dropdown options were added
                        const dropdownItems = node.querySelectorAll ?
                            node.querySelectorAll('.ag-list-item.ag-select-list-item') : [];

                        if (dropdownItems.length > 0) {
                            this.colorizeDropdownOptions(dropdownItems);
                        }

                        // Also check if the node itself is a dropdown item
                        if (node.classList && node.classList.contains('ag-list-item') && node.classList.contains('ag-select-list-item')) {
                            this.colorizeDropdownOptions([node]);
                        }
                    }
                });
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Apply colors to dropdown options based on their text content
     */
    colorizeDropdownOptions(dropdownItems) {
        dropdownItems.forEach((item) => {
            const text = item.textContent.trim();

            // Remove any existing status classes
            item.classList.remove(
                'status-dropdown-in-stock',
                'status-dropdown-out-of-stock',
                'status-dropdown-out-of-stock-hide',
                'status-dropdown-repair',
                'status-dropdown-coming-soon',
                'status-dropdown-in-stock-hide',
                'status-dropdown-unlisted'
            );

            // Apply appropriate class based on text content
            switch (text) {
                case 'In Stock':
                    item.classList.add('status-dropdown-in-stock');
                    break;
                case 'Out Of Stock':
                    item.classList.add('status-dropdown-out-of-stock');
                    break;
                case 'Out Of Stock & Hide':
                    item.classList.add('status-dropdown-out-of-stock-hide');
                    break;
                case 'Repair':
                    item.classList.add('status-dropdown-repair');
                    break;
                case 'Coming Soon':
                    item.classList.add('status-dropdown-coming-soon');
                    break;
                case 'In Stock & Hide':
                    item.classList.add('status-dropdown-in-stock-hide');
                    break;
                case 'Unlisted':
                    item.classList.add('status-dropdown-unlisted');
                    break;
            }
        });
    }

    /**
     * Get auto-opening select editor class for dropdown cells
     */
    getAutoOpenSelectEditor() {
        class AutoOpenSelectCellEditor {
            constructor() {
                this.eGui = null;
                this.eSelect = null;
                this.isDestroyed = false;
            }

            init(params) {
                this.params = params;

                // Create container
                this.eGui = document.createElement('div');
                this.eGui.style.position = 'relative';
                this.eGui.style.width = '100%';
                this.eGui.style.height = '100%';
                this.eGui.style.display = 'flex';
                this.eGui.style.alignItems = 'center';

                // Create select element
                this.eSelect = document.createElement('select');
                this.eSelect.style.width = '100%';
                this.eSelect.style.height = '100%';
                this.eSelect.style.border = '2px solid #007bff';
                this.eSelect.style.outline = 'none';
                this.eSelect.style.padding = '4px 8px';
                this.eSelect.style.fontSize = '12px';
                this.eSelect.style.fontWeight = '500';
                this.eSelect.style.borderRadius = '4px';
                this.eSelect.style.backgroundColor = 'white';
                this.eSelect.style.cursor = 'pointer';

                // Populate options
                const values = params.values || [];
                values.forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.text = value;

                    // Apply colors to options based on status
                    this.applyOptionColors(option, value);

                    this.eSelect.appendChild(option);
                });

                // Set current value
                this.eSelect.value = params.value || '';

                // Apply colors to the select element itself
                this.applySelectColors(this.eSelect, this.eSelect.value);

                // Handle value changes to update colors
                this.eSelect.addEventListener('change', () => {
                    this.applySelectColors(this.eSelect, this.eSelect.value);
                });

                this.eGui.appendChild(this.eSelect);

                // Auto-focus and open dropdown immediately
                setTimeout(() => {
                    if (!this.isDestroyed) {
                        this.eSelect.focus();
                        this.eSelect.size = Math.min(values.length, 8); // Show up to 8 options

                        // For better browser compatibility, also trigger click
                        this.eSelect.click();

                        // Listen for selection and close
                        this.eSelect.addEventListener('click', (e) => {
                            setTimeout(() => {
                                if (!this.isDestroyed) {
                                    this.eSelect.size = 1; // Collapse back to single selection
                                }
                            }, 100);
                        });
                    }
                }, 10);
            }

            applyOptionColors(option, value) {
                // Apply colors based on the type of dropdown
                const productStatusColorMap = {
                    'In Stock': { bg: '#d4edda', text: '#155724' },
                    'Out Of Stock': { bg: '#f8d7da', text: '#721c24' },
                    'Out Of Stock & Hide': { bg: '#fff3cd', text: '#856404' },
                    'Repair': { bg: '#d1ecf1', text: '#0c5460' },
                    'Coming Soon': { bg: '#e2e3e5', text: '#383d41' },
                    'In Stock & Hide': { bg: '#fff3cd', text: '#856404' },
                    'Unlisted': { bg: '#f8d7da', text: '#721c24' }
                };

                const sellStatusColorMap = {
                    'Sell as Standard': { bg: '#d4edda', text: '#155724' },
                    'Oversell': { bg: '#fff3cd', text: '#856404' },
                    'Unknown': { bg: '#f8d7da', text: '#721c24' }
                };

                const vatSchemeColorMap = {
                    'Standard Rate': { bg: '#d1ecf1', text: '#0c5460' },
                    'Reduced Rate': { bg: '#fff3cd', text: '#856404' },
                    'Zero Rate': { bg: '#d4edda', text: '#155724' },
                    'Exempt': { bg: '#f8d7da', text: '#721c24' },
                    'None': { bg: '#e2e3e5', text: '#383d41' }
                };

                // Determine which color map to use based on the option values
                let colorMap = productStatusColorMap;
                if (sellStatusColorMap[value]) {
                    colorMap = sellStatusColorMap;
                } else if (vatSchemeColorMap[value]) {
                    colorMap = vatSchemeColorMap;
                }

                const colors = colorMap[value] || { bg: '#e2e3e5', text: '#383d41' };
                option.style.backgroundColor = colors.bg;
                option.style.color = colors.text;
                option.style.padding = '8px 12px';
                option.style.fontWeight = '500';
            }

            applySelectColors(select, value) {
                // Apply colors to the select element itself
                const productStatusColorMap = {
                    'In Stock': { bg: '#d4edda', text: '#155724' },
                    'Out Of Stock': { bg: '#f8d7da', text: '#721c24' },
                    'Out Of Stock & Hide': { bg: '#fff3cd', text: '#856404' },
                    'Repair': { bg: '#d1ecf1', text: '#0c5460' },
                    'Coming Soon': { bg: '#e2e3e5', text: '#383d41' },
                    'In Stock & Hide': { bg: '#fff3cd', text: '#856404' },
                    'Unlisted': { bg: '#f8d7da', text: '#721c24' }
                };

                const sellStatusColorMap = {
                    'Sell as Standard': { bg: '#d4edda', text: '#155724' },
                    'Oversell': { bg: '#fff3cd', text: '#856404' },
                    'Unknown': { bg: '#f8d7da', text: '#721c24' }
                };

                const vatSchemeColorMap = {
                    'Standard Rate': { bg: '#d1ecf1', text: '#0c5460' },
                    'Reduced Rate': { bg: '#fff3cd', text: '#856404' },
                    'Zero Rate': { bg: '#d4edda', text: '#155724' },
                    'Exempt': { bg: '#f8d7da', text: '#721c24' },
                    'None': { bg: '#e2e3e5', text: '#383d41' }
                };

                // Determine which color map to use based on the option values
                let colorMap = productStatusColorMap;
                if (sellStatusColorMap[value]) {
                    colorMap = sellStatusColorMap;
                } else if (vatSchemeColorMap[value]) {
                    colorMap = vatSchemeColorMap;
                }

                const colors = colorMap[value] || { bg: '#e2e3e5', text: '#383d41' };
                select.style.backgroundColor = colors.bg;
                select.style.color = colors.text;
            }

            getGui() {
                return this.eGui;
            }

            getValue() {
                return this.eSelect ? this.eSelect.value : '';
            }

            destroy() {
                this.isDestroyed = true;
                if (this.eSelect) {
                    this.eSelect.removeEventListener('change', this.handleChange);
                    this.eSelect.removeEventListener('click', this.handleClick);
                }
            }

            isCancelBeforeStart() {
                return false;
            }

            isCancelAfterEnd() {
                return false;
            }

            focusIn() {
                if (this.eSelect && !this.isDestroyed) {
                    this.eSelect.focus();
                }
            }

            focusOut() {
                if (this.eSelect && !this.isDestroyed) {
                    this.eSelect.size = 1; // Collapse dropdown on focus out
                }
            }
        }

        return AutoOpenSelectCellEditor;
    }
}

// CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GridRenderer };
}
