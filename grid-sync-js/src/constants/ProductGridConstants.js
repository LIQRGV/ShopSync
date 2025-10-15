/**
 * ProductGridConstants - Configuration and constants for Product Grid
 * Contains all hardcoded values, mappings, and configuration options
 *
 * Unified version compatible with both nested and flat data structures
 * @class ProductGridConstants
 */
export class ProductGridConstants {
    // Grid Configuration
    static GRID_CONFIG = {
        HEIGHT: '600px',
        PAGINATION_SIZE: 25,
        TEXT_TRUNCATE_LENGTH: 50,
        SCROLL_TIMEOUT: 150,
        VALIDATION_TIMEOUT: 200,
        SUCCESS_TOAST_DURATION: 3000,
        ERROR_TOAST_DURATION: 5000
    };

    // Product Status Mappings
    static PRODUCT_STATUS = {
        NUMERIC_TO_TEXT: {
            1: 'In Stock',
            2: 'Out Of Stock',
            3: 'Out Of Stock & Hide',
            4: 'Repair',
            5: 'Coming Soon',
            6: 'In Stock & Hide',
            7: 'Unlisted'
        },
        TEXT_TO_NUMERIC: {
            'In Stock': 1,
            'Out Of Stock': 2,
            'Out Of Stock & Hide': 3,
            'Repair': 4,
            'Coming Soon': 5,
            'In Stock & Hide': 6,
            'Unlisted': 7
        },
        OPTIONS: [
            'In Stock',
            'Out Of Stock',
            'Out Of Stock & Hide',
            'Repair',
            'Coming Soon',
            'In Stock & Hide',
            'Unlisted'
        ],
        CSS_CLASSES: {
            1: 'status-1',
            2: 'status-2',
            3: 'status-3',
            4: 'status-4',
            5: 'status-5',
            6: 'status-6',
            7: 'status-7'
        }
    };

    // Sell Status Mappings
    static SELL_STATUS = {
        NUMERIC_TO_TEXT: {
            1: 'Sell as Standard',
            2: 'Oversell',
            3: 'Unknown'
        },
        TEXT_TO_NUMERIC: {
            'Sell as Standard': 1,
            'Oversell': 2,
            'Unknown': 3
        },
        OPTIONS: [
            'Sell as Standard',
            'Oversell',
            'Unknown'
        ]
    };

    // VAT Scheme Mappings
    static VAT_SCHEME = {
        NUMERIC_TO_TEXT: {
            0: 'None',
            1: 'Standard Rate',
            2: 'Reduced Rate',
            3: 'Zero Rate',
            4: 'Exempt'
        },
        TEXT_TO_NUMERIC: {
            'None': 0,
            'Standard Rate': 1,
            'Reduced Rate': 2,
            'Zero Rate': 3,
            'Exempt': 4
        },
        OPTIONS: [
            'None',
            'Standard Rate',
            'Reduced Rate',
            'Zero Rate',
            'Exempt'
        ]
    };

    // SKU Prefix Options
    static SKU_PREFIXES = [
        'WATCH',
        'JEWEL',
        'RING',
        'NECK',
        'BRACE',
        'MISC',
        'ROL',
        'MKT'
    ];

    // Grid Column Definitions Configuration
    static COLUMN_WIDTHS = {
        image: 80,
        productName: 200,
        skuPrefix: 120,
        skuValue: 120,
        skuCustomRef: 140,
        fullSku: 120,
        productStatus: 130,
        sellStatus: 130,
        purchaseDate: 130,
        currentPrice: 130,
        salePrice: 120,
        tradePrice: 120,
        vatScheme: 120,
        description: 200,
        category: 150,
        brand: 150,
        supplier: 150,
        seoTitle: 200,
        seoKeywords: 200,
        seoDescription: 200,
        urlSlug: 200,
        slug: 200,
        actions: 100
    };

    // Default AG Grid Options
    static AG_GRID_OPTIONS = {
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            menuTabs: ['filterMenuTab', 'generalMenuTab'],
            lockPosition: true
        },
        rowSelection: {
            mode: 'multiRow'
        },
        suppressMenuHide: true,
        suppressRowClickSelection: true,
        rowMultiSelectWithClick: true,
        pagination: true,
        suppressPaginationPanel: true,
        cacheQuickFilter: true,
        animateRows: true,
        suppressCellFocus: false,
        enableCellTextSelection: true,
        suppressClipboardPaste: true,  // Using custom clipboard handler
        suppressColumnMoveAnimation: true,
        suppressMovableColumns: true,
        maintainColumnOrder: true,
        suppressColumnVirtualisation: true,
        singleClickEdit: false,  // Prevent single-click editing to enable custom click handling
        domLayout: 'autoHeight'  // Make grid auto-size to fit all rows without scrolling
    };

    // API Configuration
    static API_CONFIG = {
        HEADERS: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        METHODS: {
            GET: 'GET',
            POST: 'POST',
            PUT: 'PUT',
            PATCH: 'PATCH',
            DELETE: 'DELETE'
        }
    };

    // Toast/Alert Configuration
    static TOAST_CONFIG = {
        CLASSES: {
            SUCCESS: 'alert alert-success position-fixed',
            ERROR: 'alert alert-danger position-fixed',
            INFO: 'alert alert-info position-fixed',
            WARNING: 'alert alert-warning position-fixed',
            PROCESSING: 'alert alert-info position-fixed'
        },
        STYLES: 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
    };

    // Keyboard Shortcuts
    static KEYBOARD = {
        COPY: 'c',
        PASTE: 'v',
        SELECT_ALL: 'a',
        ESCAPE: 'Escape',
        DELETE: 'Delete'
    };

    // Clipboard Configuration
    static CLIPBOARD = {
        DELIMITERS: {
            TAB: '\t',
            COMMA: ',',
            NEWLINE: '\n'
        },
        QUOTE_REGEX: /^"|"$/g,
        DOUBLE_QUOTE_REGEX: /""/g
    };

    /**
     * Get product status text by numeric value
     * @param {number} numericValue - Numeric status value
     * @returns {string} Status text
     */
    static getProductStatusText(numericValue) {
        return this.PRODUCT_STATUS.NUMERIC_TO_TEXT[numericValue] || 'Unknown';
    }

    /**
     * Get product status numeric value by text
     * @param {string} textValue - Status text
     * @returns {number} Numeric status value
     */
    static getProductStatusNumeric(textValue) {
        return this.PRODUCT_STATUS.TEXT_TO_NUMERIC[textValue] || 1;
    }

    /**
     * Get product status CSS class by numeric value
     * @param {number} numericValue - Numeric status value
     * @returns {string} CSS class name
     */
    static getProductStatusClass(numericValue) {
        return this.PRODUCT_STATUS.CSS_CLASSES[numericValue] || 'status-default';
    }

    /**
     * Get sell status text by numeric value
     * @param {number} numericValue - Numeric sell status value
     * @returns {string} Sell status text
     */
    static getSellStatusText(numericValue) {
        return this.SELL_STATUS.NUMERIC_TO_TEXT[numericValue] || 'Unknown';
    }

    /**
     * Get sell status numeric value by text
     * @param {string} textValue - Sell status text
     * @returns {number} Numeric sell status value
     */
    static getSellStatusNumeric(textValue) {
        return this.SELL_STATUS.TEXT_TO_NUMERIC[textValue] || 1;
    }

    /**
     * Get VAT scheme text by numeric value
     * @param {number} numericValue - Numeric VAT scheme value
     * @returns {string} VAT scheme text
     */
    static getVatSchemeText(numericValue) {
        return this.VAT_SCHEME.NUMERIC_TO_TEXT[numericValue] || 'Unknown';
    }

    /**
     * Get VAT scheme numeric value by text
     * @param {string} textValue - VAT scheme text
     * @returns {number} Numeric VAT scheme value
     */
    static getVatSchemeNumeric(textValue) {
        return this.VAT_SCHEME.TEXT_TO_NUMERIC[textValue] || 0;
    }
}

// Export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ProductGridConstants };
}
