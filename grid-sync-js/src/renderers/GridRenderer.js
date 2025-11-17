/**
 * GridRenderer - Unified cell rendering and formatting for Product Grid
 * Supports both nested and flat data structures via GridDataAdapter
 */
import { ProductGridConstants } from '../constants/ProductGridConstants.js';
import { SearchableSelectEditor } from '../editors/SearchableSelectEditor.js';

export class GridRenderer {
    /**
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - Base URL for links
     * @param {GridDataAdapter} config.dataAdapter - Data adapter for field access
     * @param {Object} config.currentData - Current grid data with included relationships
     * @param {Array} config.enabledAttributes - Enabled attributes for dynamic columns
     * @param {Array} config.masterAttributes - Master attributes data (for flat mode)
     */
    constructor(config) {
        this.baseUrl = config.baseUrl || '';
        this.dataAdapter = config.dataAdapter;
        this.currentData = config.currentData || null;
        this.enabledAttributes = config.enabledAttributes || [];
        this.masterAttributes = config.masterAttributes || [];

        console.log('[GridRenderer] Constructor: masterAttributes count =', this.masterAttributes.length);

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
     * Extract attribute groups from current data
     * Returns grouped attributes with group_name as key
     */
    extractAttributeGroups() {
        if (!this.currentData) {
            console.warn('[GridRenderer] extractAttributeGroups: No currentData');
            return {};
        }

        console.log('[GridRenderer] extractAttributeGroups: dataAdapter.mode =', this.dataAdapter.mode);
        console.log('[GridRenderer] extractAttributeGroups: currentData keys =', Object.keys(this.currentData));
        console.log('[GridRenderer] extractAttributeGroups: has included?', !!this.currentData.included);
        console.log('[GridRenderer] extractAttributeGroups: has attributes?', !!this.currentData.attributes);

        // Log first product structure to see where attributes are
        if (this.currentData.data && this.currentData.data.length > 0) {
            const firstProduct = this.currentData.data[0];
            console.log('[GridRenderer] First product keys:', Object.keys(firstProduct));
            console.log('[GridRenderer] First product full:', firstProduct);
        }

        const attributeGroups = {};

        // Handle both nested and flat modes: Extract master attributes from included array
        // For nested mode (WTM): included contains product relationships + master attributes
        // For flat mode (WL): included contains only master attributes metadata
        if (this.currentData.included) {
            console.log('[GridRenderer] Found included array, extracting attributes...');

            // Filter attributes that are enabled_on_dropship
            const enabledAttributes = this.currentData.included.filter(item =>
                item.type === 'attributes' &&
                item.attributes.enabled_on_dropship === true
            );

            console.log('[GridRenderer] Enabled attributes found:', enabledAttributes.length);

            // Group by group_name
            enabledAttributes.forEach(attr => {
                const groupName = attr.attributes.group_name || 'Other';

                if (!attributeGroups[groupName]) {
                    attributeGroups[groupName] = [];
                }

                attributeGroups[groupName].push({
                    id: attr.id,
                    name: attr.attributes.name,
                    code: attr.attributes.code,
                    type: attr.attributes.type,
                    input_type: attr.attributes.input_type || 1,
                    options: attr.attributes.options || []
                });
            });

            console.log('[GridRenderer] Attribute groups extracted:', Object.keys(attributeGroups));
        }

        // Handle flat mode (master attributes passed from backend)
        // In flat mode, master attributes are passed via blade template to JavaScript
        if (this.dataAdapter.mode === 'flat' && this.masterAttributes && this.masterAttributes.length > 0) {
            console.log('[GridRenderer] Using masterAttributes from blade template, count:', this.masterAttributes.length);

            this.masterAttributes.forEach(attr => {
                const groupName = attr.group_name || 'Other';

                if (!attributeGroups[groupName]) {
                    attributeGroups[groupName] = [];
                }

                attributeGroups[groupName].push({
                    id: attr.id,
                    name: attr.name,
                    code: attr.code || null,
                    type: attr.input_type || 1
                });
            });

            console.log('[GridRenderer] Extracted attribute groups:', Object.keys(attributeGroups));
        }

        // Alternative: Extract from first product's attributes in flat mode
        console.log('[GridRenderer] Checking product attributes extraction...');
        console.log('[GridRenderer] - attributeGroups.length:', Object.keys(attributeGroups).length);
        console.log('[GridRenderer] - has data?:', !!this.currentData.data);
        console.log('[GridRenderer] - is array?:', Array.isArray(this.currentData.data));
        console.log('[GridRenderer] - data.length:', this.currentData.data?.length);

        if (this.dataAdapter.mode === 'flat' &&
            Object.keys(attributeGroups).length === 0 &&
            this.currentData.data &&
            Array.isArray(this.currentData.data) &&
            this.currentData.data.length > 0) {

            console.log('[GridRenderer] Extracting attributes from products...');
            console.log('[GridRenderer] First product:', this.currentData.data[0]);

            // Get unique attributes from all products
            const seenAttributes = new Set();

            this.currentData.data.forEach((product, productIndex) => {
                // Skip logging after first 3 products to avoid console spam
                if (productIndex < 3) {
                    console.log(`[GridRenderer] Product ${productIndex} keys:`, Object.keys(product));
                    console.log(`[GridRenderer] Product ${productIndex} attributes type:`, typeof product.attributes);
                    console.log(`[GridRenderer] Product ${productIndex} attributes isArray:`, Array.isArray(product.attributes));

                    // Check for other potential attribute fields
                    if (product.product_attributes) console.log(`[GridRenderer] Product ${productIndex} has product_attributes:`, product.product_attributes);
                    if (product.attribute_values) console.log(`[GridRenderer] Product ${productIndex} has attribute_values:`, product.attribute_values);
                    if (product.pivot_attributes) console.log(`[GridRenderer] Product ${productIndex} has pivot_attributes:`, product.pivot_attributes);
                }

                if (product.attributes && Array.isArray(product.attributes)) {
                    if (productIndex < 3) {
                        console.log(`[GridRenderer] Product ${productIndex} has ${product.attributes.length} attributes`);
                    }

                    product.attributes.forEach((attr, attrIndex) => {
                        console.log(`[GridRenderer] - Attribute ${attrIndex}:`, attr);
                        const attrKey = String(attr.id);

                        if (!seenAttributes.has(attrKey) && attr.enabled_on_dropship === true) {
                            console.log(`[GridRenderer] - Adding attribute ${attr.name} (${attr.id})`);
                            seenAttributes.add(attrKey);

                            const groupName = attr.group_name || 'Other';

                            if (!attributeGroups[groupName]) {
                                attributeGroups[groupName] = [];
                            }

                            attributeGroups[groupName].push({
                                id: attr.id,
                                name: attr.name,
                                code: attr.code,
                                type: attr.type
                            });
                        }
                    });
                }
            });
        }

        console.log('[GridRenderer] Final attributeGroups:', attributeGroups);
        console.log('[GridRenderer] Total attribute groups found:', Object.keys(attributeGroups).length);

        return attributeGroups;
    }

    /**
     * Generate dynamic attribute column groups
     */
    generateAttributeColumnGroups() {
        const attributeGroups = this.extractAttributeGroups();
        const columnGroups = [];

        // Generate column group for each attribute group
        Object.entries(attributeGroups).forEach(([groupName, attributes]) => {
            const children = attributes.map((attr, index) => {
                // Capture attr properties in closure
                const attrId = String(attr.id);
                const attrName = attr.name;
                const inputType = attr.input_type || 1;
                const options = attr.options || [];

                // Check if this is an option type without options configured
                const isOptionWithoutValues = inputType !== 1 && options.length === 0;

                // Placeholder text for different states
                const emptyOptionText = '- Select -'; // For clearing attribute when options exist
                const noOptionsText = 'No options available'; // For disabled dropdown without options

                // Add clear option as first option to allow clearing attribute value
                // Use special marker "- Select -" that will be converted to empty string on save
                const editorOptions = inputType === 1 ? options : [emptyOptionText, ...options];

                return {
                    colId: `attribute_${attrId}`,
                    headerName: attrName,
                    field: `attribute_${attrId}`,
                    width: 150,
                    sortable: false,
                    filter: 'agTextColumnFilter',
                    editable: !isOptionWithoutValues, // Non-editable if option type has no options
                    cellEditor: inputType === 1 ? 'agTextCellEditor' : this.getAutoOpenSelectEditor(),
                    cellEditorPopup: inputType !== 1,
                    cellEditorParams: inputType === 1 ? {} : { values: editorOptions },
                    cellStyle: (params) => {
                        const baseStyle = this.getCellStyle(params);
                        // Add gray italic style for placeholder text
                        // Show for: option without values OR option with values but empty
                        if (!params.value && inputType !== 1) {
                            return {
                                ...baseStyle,
                                color: '#999',
                                fontStyle: 'italic'
                            };
                        }
                        return baseStyle;
                    },
                    valueGetter: (params) => {
                        if (!params.data) return '';

                        // Get currentData and dataAdapter from context
                        const currentData = params.context?.gridInstance?.currentData || this.currentData;
                        const dataAdapter = params.context?.gridInstance?.dataAdapter || this.dataAdapter;

                        // First check if there's a locally updated value (from recent edit)
                        if (params.data._attributeValues && params.data._attributeValues[attrId] !== undefined) {
                            const value = params.data._attributeValues[attrId];
                            // For option types, show appropriate placeholder for empty values
                            return (inputType !== 1 && !value) ? (isOptionWithoutValues ? noOptionsText : emptyOptionText) : value;
                        }

                        // Handle nested mode (JSON:API with relationships + included)
                        if (dataAdapter.mode === 'nested' && params.data.relationships && params.data.relationships.attributes) {
                            const attributeIds = params.data.relationships.attributes.data.map(a => String(a.id));

                            // Check if this attribute is in the product
                            if (!attributeIds.includes(attrId)) {
                                // For option types, show appropriate placeholder for empty values
                                return inputType !== 1 ? (isOptionWithoutValues ? noOptionsText : emptyOptionText) : '';
                            }

                            if (currentData && currentData.included) {
                                // Find the attribute in included data for this specific product
                                const includedAttr = currentData.included.find(inc =>
                                    inc.type === 'attributes' &&
                                    String(inc.id) === attrId &&
                                    attributeIds.includes(String(inc.id))
                                );

                                if (includedAttr) {
                                    // Check for SSE-updated value first (from real-time sync)
                                    // Try both as string and number since JavaScript object keys are strings
                                    if (includedAttr._productValues) {
                                        const productIdStr = String(params.data.id);
                                        const productIdNum = Number(params.data.id);

                                        if (includedAttr._productValues[productIdStr] !== undefined) {
                                            const value = includedAttr._productValues[productIdStr];
                                            // For option types, show appropriate placeholder for empty values
                                            return (inputType !== 1 && !value) ? (isOptionWithoutValues ? noOptionsText : emptyOptionText) : value;
                                        }
                                        if (includedAttr._productValues[productIdNum] !== undefined) {
                                            const value = includedAttr._productValues[productIdNum];
                                            // For option types, show appropriate placeholder for empty values
                                            return (inputType !== 1 && !value) ? (isOptionWithoutValues ? noOptionsText : emptyOptionText) : value;
                                        }
                                    }

                                    // REMOVED FALLBACK TO attributes.pivot.value
                                    // This was causing all products to show same value!
                                    // If _productValues doesn't have value for this product, it means empty
                                }
                            }
                        }

                        // Handle flat mode (direct attributes array or object)
                        if (dataAdapter.mode === 'flat') {
                            // Get product ID for filtering
                            const productId = String(params.data.id);

                            // Check if data has attributes as array (with pivot)
                            if (params.data.attributes && Array.isArray(params.data.attributes)) {
                                const attr = params.data.attributes.find(a => String(a.id) === attrId);
                                if (attr && attr.pivot) {
                                    return attr.pivot.value || '';
                                }
                            }

                            // Check if data has attribute values in included
                            // Pivot is now an array of objects, find the one for this product
                            if (currentData && currentData.included) {
                                const includedAttr = currentData.included.find(inc =>
                                    inc.type === 'attributes' &&
                                    String(inc.id) === attrId
                                );

                                if (includedAttr && includedAttr.attributes && includedAttr.attributes.pivot) {
                                    // Pivot is now an array - find the pivot for this product
                                    const pivots = Array.isArray(includedAttr.attributes.pivot)
                                        ? includedAttr.attributes.pivot
                                        : [includedAttr.attributes.pivot];

                                    const productPivot = pivots.find(p => String(p.product_id) === productId);

                                    if (productPivot) {
                                        return productPivot.value || '';
                                    }
                                }
                            }

                            // Fallback: check direct field attribute_X
                            if (params.data[`attribute_${attrId}`]) {
                                return params.data[`attribute_${attrId}`];
                            }
                        }

                        // For option types, show appropriate placeholder for empty values
                        return inputType !== 1 ? (isOptionWithoutValues ? noOptionsText : emptyOptionText) : '';
                    },
                    valueSetter: (params) => {
                        // Convert "- Select -" to empty string for clearing attribute
                        const actualValue = params.newValue === '- Select -' ? '' : params.newValue;

                        // Store attribute_id for update handler
                        params.data._attributeUpdate = {
                            attributeId: attrId,
                            oldValue: params.oldValue === '- Select -' ? '' : params.oldValue,
                            newValue: actualValue
                        };

                        // Actually update the value in the data to trigger onCellValueChanged
                        // This is necessary for AG Grid to properly detect the change
                        // The field name matches what valueGetter uses
                        const fieldName = `attribute_${attrId}`;

                        // Update the data directly - this triggers onCellValueChanged event
                        if (!params.data._attributeValues) {
                            params.data._attributeValues = {};
                        }
                        // Store the actual value (empty string for "- Select -")
                        params.data._attributeValues[attrId] = actualValue;

                        return true; // Return true to indicate value was set successfully
                    },
                    valueFormatter: (params) => {
                        // Don't format "- Select -" - let it pass through as is
                        if (params.value === '- Select -') {
                            return '- Select -';
                        }

                        if (!params.value) {
                            // Show different placeholder based on whether options are configured
                            if (isOptionWithoutValues) {
                                return 'No options configured';
                            } else if (inputType !== 1) {
                                // Option type with values but not selected yet
                                return '- Select -';
                            }
                        }
                        return params.value || '';
                    }
                };
            });

            columnGroups.push({
                headerName: groupName,
                headerGroupClass: 'attribute-group-header',
                children: children
            });
        });

        return columnGroups;
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
                colId: 'description',
                headerName: 'Description',
                field: this.dataAdapter.getFieldPath('description'),
                width: ProductGridConstants.COLUMN_WIDTHS.description,
                sortable: true,
                filter: 'agTextColumnFilter',
                editable: true,
                cellRenderer: (params) => this.truncatedTextRenderer(params)
            },

            // Relations Group (only for flat/WL mode with relationships)
            ...(this.dataAdapter.mode === 'flat' ? [
                {
                    colId: 'categoryName',
                    headerName: 'Category',
                    field: 'category_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.category,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: true,
                    cellEditor: SearchableSelectEditor,
                    cellEditorPopup: false,
                    cellEditorParams: {
                        fetchMethod: 'fetchCategories',
                        valueField: 'id',
                        displayField: 'name',
                        relationshipIdField: 'category_id',
                        placeholder: 'Search categories...'
                    },
                    valueGetter: (params) => this.getCategoryName(params),
                    valueSetter: (params) => {
                        // Store the relationship ID for update
                        params.data._relationshipUpdate = {
                            field: 'category_id',
                            value: params.newValue
                        };
                        return true;
                    }
                },
                {
                    colId: 'brandName',
                    headerName: 'Brand',
                    field: 'brand_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.brand,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: true,
                    cellEditor: SearchableSelectEditor,
                    cellEditorPopup: false,
                    cellEditorParams: {
                        fetchMethod: 'fetchBrands',
                        valueField: 'id',
                        displayField: 'name',
                        relationshipIdField: 'brand_id',
                        placeholder: 'Search brands...'
                    },
                    valueGetter: (params) => this.getBrandName(params),
                    valueSetter: (params) => {
                        // Store the relationship ID for update
                        params.data._relationshipUpdate = {
                            field: 'brand_id',
                            value: params.newValue
                        };
                        return true;
                    }
                },
                {
                    colId: 'supplierName',
                    headerName: 'Supplier',
                    field: 'supplier_name',
                    width: ProductGridConstants.COLUMN_WIDTHS.supplier,
                    sortable: true,
                    filter: 'agSetColumnFilter',
                    editable: true,
                    cellEditor: SearchableSelectEditor,
                    cellEditorPopup: false,
                    cellEditorParams: {
                        fetchMethod: 'fetchSuppliers',
                        valueField: 'id',
                        displayField: 'name',
                        relationshipIdField: 'supplier_id',
                        placeholder: 'Search suppliers...'
                    },
                    valueGetter: (params) => this.getSupplierName(params),
                    valueSetter: (params) => {
                        // Store the relationship ID for update
                        params.data._relationshipUpdate = {
                            field: 'supplier_id',
                            value: params.newValue
                        };
                        return true;
                    }
                }
            ] : []),

            // SEO Group (only for flat/WL mode - thediamondbox specific)
            ...(this.dataAdapter.mode === 'flat' ? [
                {
                    colId: 'seoTitle',
                    headerName: 'SEO Title',
                    field: this.dataAdapter.getFieldPath('seo_title'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoTitle,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true,
                    cellRenderer: (params) => this.truncatedTextRenderer(params)
                },
                {
                    colId: 'seoKeywords',
                    headerName: 'SEO Keywords',
                    field: this.dataAdapter.getFieldPath('seo_keywords'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoKeywords,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true
                },
                {
                    colId: 'seoDescription',
                    headerName: 'SEO Description',
                    field: this.dataAdapter.getFieldPath('seo_description'),
                    width: ProductGridConstants.COLUMN_WIDTHS.seoDescription,
                    sortable: true,
                    filter: 'agTextColumnFilter',
                    editable: true,
                    cellRenderer: (params) => this.truncatedTextRenderer(params)
                }
            ] : []),

            // Dynamic Attribute Column Groups will be added after data loads (see ProductSyncGrid.loadProducts)
            // Removed from initial column defs to prevent empty columns

            // URL Slug (both modes)
            {
                colId: 'urlSlug',
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
                this.cancelBeforeStart = false;
                this.cancelAfterEnd = false;

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
                this.handleChange = () => {
                    this.applySelectColors(this.eSelect, this.eSelect.value);
                };
                this.eSelect.addEventListener('change', this.handleChange);

                // ESC key handler to cancel editing
                this.handleKeyDown = (e) => {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.cancelAfterEnd = true;
                        // Stop editing via AG Grid API
                        if (this.params.api && this.params.stopEditing) {
                            this.params.stopEditing(true); // true = cancel
                        }
                    }
                };
                this.eSelect.addEventListener('keydown', this.handleKeyDown);

                this.eGui.appendChild(this.eSelect);

                // Auto-focus and open dropdown immediately
                setTimeout(() => {
                    if (!this.isDestroyed) {
                        this.eSelect.focus();
                        this.eSelect.size = Math.min(values.length, 8); // Show up to 8 options

                        // For better browser compatibility, also trigger click
                        this.eSelect.click();

                        // Listen for selection and close
                        this.handleClick = (e) => {
                            setTimeout(() => {
                                if (!this.isDestroyed) {
                                    this.eSelect.size = 1; // Collapse back to single selection
                                }
                            }, 100);
                        };
                        this.eSelect.addEventListener('click', this.handleClick);
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
                    if (this.handleChange) {
                        this.eSelect.removeEventListener('change', this.handleChange);
                    }
                    if (this.handleClick) {
                        this.eSelect.removeEventListener('click', this.handleClick);
                    }
                    if (this.handleKeyDown) {
                        this.eSelect.removeEventListener('keydown', this.handleKeyDown);
                    }
                }
            }

            isCancelBeforeStart() {
                return this.cancelBeforeStart;
            }

            isCancelAfterEnd() {
                return this.cancelAfterEnd;
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
