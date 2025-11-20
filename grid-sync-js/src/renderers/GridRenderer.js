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
     * @param {Array} config.enabledAttributes - Enabled attributes for dynamic columns
     * @param {Array} config.masterAttributes - Master attributes data (for flat mode)
     */
    constructor(config) {
        this.baseUrl = config.baseUrl || '';
        this.dataAdapter = config.dataAdapter;
        this.currentData = config.currentData || null;
        this.enabledAttributes = config.enabledAttributes || [];
        this.masterAttributes = config.masterAttributes || [];

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

        const attributeGroups = {};

        // Handle both nested and flat modes: Extract master attributes from included array
        // For nested mode (WTM): included contains product relationships + master attributes
        // For flat mode (WL): included contains only master attributes metadata
        if (this.currentData.included) {
            // Filter attributes that are enabled_on_dropship
            const enabledAttributes = this.currentData.included.filter(item =>
                item.type === 'attributes' &&
                item.attributes.enabled_on_dropship === true
            );

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
        }

        // Handle flat mode (master attributes passed from backend)
        // In flat mode, master attributes are passed via blade template to JavaScript
        if (this.dataAdapter.mode === 'flat' && this.masterAttributes && this.masterAttributes.length > 0) {

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
        }

        // Alternative: Extract from first product's attributes in flat mode

        if (this.dataAdapter.mode === 'flat' &&
            Object.keys(attributeGroups).length === 0 &&
            this.currentData.data &&
            Array.isArray(this.currentData.data) &&
            this.currentData.data.length > 0) {

            // Get unique attributes from all products
            const seenAttributes = new Set();

            this.currentData.data.forEach((product) => {
                if (product.attributes && Array.isArray(product.attributes)) {
                    product.attributes.forEach((attr) => {
                        const attrKey = String(attr.id);

                        if (!seenAttributes.has(attrKey) && attr.enabled_on_dropship === true) {
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
                    editable: (params) => {
                        return params.data ? true : false;
                    },
                    cellRenderer: (params) => {
                        // Display category name from relationship if available, otherwise from field
                        if (!params.data) return '';
                        const categoryName = this.getCategoryName(params);
                        return categoryName || params.value || '';
                    },
                    cellEditor: this.getCategoryEditor(),
                    cellEditorPopup: true,
                    cellEditorParams: {},
                    // Simple value getter/setter that reads/writes directly to category_name field
                    valueGetter: (params) => {
                        if (!params.data) return '';
                        // Try to get from relationship first, otherwise from field
                        const categoryFromRelationship = this.getCategoryName(params);
                        return categoryFromRelationship || params.data.category_name || '';
                    },
                    valueSetter: (params) => {
                        if (!params.data) return false;

                        // newValue is comma-separated string from CategoryEditor.getValue()
                        const newCategoryValue = params.newValue;
                        const oldCategoryId = params.data.category_id;

                        // Parse newValue to array for comparison
                        const newCategoryIds = newCategoryValue && newCategoryValue !== ''
                            ? newCategoryValue.split(',').map(id => parseInt(id.trim()))
                            : [];

                        // Parse oldValue to array for comparison
                        const oldCategoryIds = oldCategoryId
                            ? (typeof oldCategoryId === 'string'
                                ? oldCategoryId.split(',').map(id => parseInt(id.trim()))
                                : [parseInt(oldCategoryId)])
                            : [];

                        // Compare arrays - no change if same IDs
                        if (JSON.stringify(newCategoryIds.sort()) === JSON.stringify(oldCategoryIds.sort())) {
                            return false;
                        }

                        // Update category_id in row data (store as comma-separated for display)
                        params.data.category_id = newCategoryValue;

                        // Update category_name for display (hierarchical comma-separated)
                        params.data.category_name = this.buildCategoryNamesFromIds(newCategoryIds);

                        // Set flag for handleCellEdit - send as array for JSON:API
                        params.data._categoryUpdate = {
                            categoryId: newCategoryIds.length > 0 ? newCategoryIds : null
                        };

                        return true;
                    },
                    cellClass: (params) => params.data ? 'editable-cell' : 'read-only-cell',
                    cellStyle: (params) => this.getCellStyle(params)
                },
                {
                    colId: 'brandName',
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
                    colId: 'supplierName',
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
     * Get category name from included data (supports both nested and flat modes + multi-category)
     */
    getCategoryName(params) {
        if (!params.data || !this.currentData?.included) {
            return '';
        }

        // For WL mode (flat), prioritize direct category_id/sub_category_id fields over relationships
        // This is because WL mode uses flat data structure but still has empty relationships object
        const hasDirectCategoryFields = params.data?.category_id || params.data?.sub_category_id;

        if (hasDirectCategoryFields) {
            // Handle flat mode (WL) - supports comma-separated category_ids
            // Combine both category_id (parents) and sub_category_id (children)
            const allCategoryIds = [];

            if (params.data?.category_id) {
                const parentIds = String(params.data.category_id)
                    .split(',')
                    .map(id => id.trim())
                    .filter(Boolean)
                    .map(id => parseInt(id));
                allCategoryIds.push(...parentIds);
            }

            if (params.data?.sub_category_id) {
                const subIds = String(params.data.sub_category_id)
                    .split(',')
                    .map(id => id.trim())
                    .filter(Boolean)
                    .map(id => parseInt(id));
                allCategoryIds.push(...subIds);
            }

            if (allCategoryIds.length === 0) {
                return '';
            }

            // Use grouped display format
            const result = this.buildCategoryNamesFromIds(allCategoryIds);

            return result;
        }

        // Handle nested mode (WTM) - uses relationships (only if no direct fields)
        if (params.data?.relationships?.category) {
            const categoryData = this.findIncluded(params.data, 'categories', 'category');
            if (categoryData && categoryData.attributes) {
                return this.buildHierarchicalCategoryName(categoryData);
            }
            return '';
        }

        // No category data found
        return '';
    }

    /**
     * Build hierarchical category name (Parent > Child)
     */
    buildHierarchicalCategoryName(categoryData) {
        if (!categoryData || !categoryData.attributes) {
            return '';
        }

        const categoryName = categoryData.attributes.name || '';
        const parentId = categoryData.attributes.parent_id;

        // If has parent, find and prepend parent name
        if (parentId && this.currentData?.included) {
            const parentData = this.currentData.included.find(item =>
                item.type === 'categories' && String(item.id) === String(parentId)
            );

            if (parentData && parentData.attributes && parentData.attributes.name) {
                return `${parentData.attributes.name} > ${categoryName}`;
            }
        }

        // No parent or parent not found, return just the category name
        return categoryName;
    }

    /**
     * Build category names from array of IDs (for display after editor closes)
     * Groups subcategories by parent: "Jewellery > [Rings, Necklaces], Watches > Chronograph"
     */
    buildCategoryNamesFromIds(categoryIds) {
        if (!categoryIds || categoryIds.length === 0 || !this.currentData?.included) {
            return '';
        }

        // Group categories by parent
        const grouped = {};
        const standalone = []; // Categories without parent (root categories)

        categoryIds.forEach(categoryId => {
            const categoryData = this.currentData.included.find(item =>
                item.type === 'categories' && String(item.id) === String(categoryId)
            );

            if (categoryData && categoryData.attributes) {
                const parentId = categoryData.attributes.parent_id;
                const categoryName = categoryData.attributes.name || '';

                if (parentId && parentId !== 0) {
                    // Has parent - group by parent
                    const parentData = this.currentData.included.find(item =>
                        item.type === 'categories' && String(item.id) === String(parentId)
                    );

                    const parentName = parentData?.attributes?.name || 'Unknown';

                    if (!grouped[parentName]) {
                        grouped[parentName] = [];
                    }
                    grouped[parentName].push(categoryName);
                } else {
                    // Root category (no parent)
                    standalone.push(categoryName);
                }
            }
        });

        // Build display string
        const parts = [];

        // Add grouped categories (parent with children)
        Object.entries(grouped).forEach(([parentName, children]) => {
            if (children.length === 1) {
                // Single child: "Parent > Child"
                parts.push(`${parentName} > ${children[0]}`);
            } else {
                // Multiple children: "Parent > [Child1, Child2]"
                parts.push(`${parentName} > [${children.join(', ')}]`);
            }
        });

        // Add standalone root categories (but exclude parents that already have children shown)
        // This prevents showing "Jewellery, Watches" at the end when they're already shown with children
        const parentNamesWithChildren = Object.keys(grouped);
        const trulyStandalone = standalone.filter(name => !parentNamesWithChildren.includes(name));

        if (trulyStandalone.length > 0) {
            parts.push(...trulyStandalone);
        }

        return parts.join(', ');
    }

    /**
     * Set category value when editor closes
     * @param {Object} params - AG Grid value setter params
     * @returns {boolean} - True if value changed
     */
    setCategoryValue(params) {
        const newCategoryId = params.newValue;
        const oldCategoryId = params.data.category_id;

        // No change - use loose equality to handle string/number comparison
        if (newCategoryId == oldCategoryId) {
            return false;
        }

        // Update category_id in row data
        params.data.category_id = newCategoryId;

        // Update category_name for display
        if (newCategoryId && window._cachedCategories) {
            const category = window._cachedCategories.find(c => c.value == newCategoryId);
            params.data.category_name = category ? category.label : '';
        } else {
            params.data.category_name = '';
        }

        // Set flag for handleCellEdit to know this is a category update
        // Similar to _attributeUpdate pattern
        params.data._categoryUpdate = {
            categoryId: newCategoryId
        };

        // Return true to indicate value was set successfully
        // AG Grid will automatically fire onCellValueChanged event
        // which triggers handleCellEdit to update the backend
        return true;
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

    /**
     * Get category editor class for multi-select category dropdown
     * Supports comma-separated category IDs for multi-category assignment
     */
    getCategoryEditor() {
        class CategoryEditor {
            constructor() {
                this.eGui = null;
                this.eContainer = null;
                this.categories = [];
                this.selectedIds = new Set();
                this.checkboxes = [];
                this.isDestroyed = false;
            }

            async init(params) {
                this.params = params;

                // Load categories from cache or fetch from API
                await this.loadCategories();

                // Parse existing category IDs from BOTH category_id (parents) and sub_category_id (children)
                let currentCategoryIds = params.data.category_id;
                let currentSubCategoryIds = params.data.sub_category_id;

                // If not in category_id, try to read from relationships (nested mode)
                if (!currentCategoryIds && params.data.relationships && params.data.relationships.category) {
                    const catRel = params.data.relationships.category.data;
                    if (Array.isArray(catRel)) {
                        // To-many relationship
                        currentCategoryIds = catRel.map(c => c.id).join(',');
                    } else if (catRel && catRel.id) {
                        // To-one relationship
                        currentCategoryIds = catRel.id;
                    }
                }

                // Handle different formats: string with comma, single number, array, or empty
                let categoryIdsArray = [];

                // Parse parent categories from category_id
                if (currentCategoryIds) {
                    if (Array.isArray(currentCategoryIds)) {
                        categoryIdsArray = currentCategoryIds.map(id => String(id).trim()).filter(Boolean);
                    } else if (typeof currentCategoryIds === 'string' && currentCategoryIds.includes(',')) {
                        categoryIdsArray = currentCategoryIds.split(',').map(id => id.trim()).filter(Boolean);
                    } else if (currentCategoryIds) {
                        categoryIdsArray = [String(currentCategoryIds).trim()];
                    }
                }

                // Parse subcategories from sub_category_id and add to the array
                if (currentSubCategoryIds) {
                    let subCategoryIdsArray = [];
                    if (Array.isArray(currentSubCategoryIds)) {
                        subCategoryIdsArray = currentSubCategoryIds.map(id => String(id).trim()).filter(Boolean);
                    } else if (typeof currentSubCategoryIds === 'string' && currentSubCategoryIds.includes(',')) {
                        subCategoryIdsArray = currentSubCategoryIds.split(',').map(id => id.trim()).filter(Boolean);
                    } else if (currentSubCategoryIds) {
                        subCategoryIdsArray = [String(currentSubCategoryIds).trim()];
                    }
                    // Combine parent and subcategory IDs
                    categoryIdsArray = categoryIdsArray.concat(subCategoryIdsArray);
                }

                this.selectedIds = new Set(categoryIdsArray.map(id => parseInt(id)));

                // Auto-select parent categories for any selected child categories
                const selectedIdsArray = Array.from(this.selectedIds);
                selectedIdsArray.forEach(categoryId => {
                    const category = this.categories.find(c => c.value === categoryId);
                    if (category && category.parent_id && category.parent_id !== 0) {
                        this.selectedIds.add(category.parent_id);
                    }
                });

                // Create container
                this.eGui = document.createElement('div');
                this.eGui.style.position = 'relative';
                this.eGui.style.width = '320px';
                this.eGui.style.maxHeight = '400px';
                this.eGui.style.backgroundColor = 'white';
                this.eGui.style.border = '2px solid #4CAF50';
                this.eGui.style.borderRadius = '6px';
                this.eGui.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                this.eGui.style.display = 'flex';
                this.eGui.style.flexDirection = 'column';

                // Header with title and buttons
                const header = document.createElement('div');
                header.style.padding = '10px 12px';
                header.style.borderBottom = '1px solid #dee2e6';
                header.style.backgroundColor = '#f8f9fa';
                header.style.display = 'flex';
                header.style.justifyContent = 'space-between';
                header.style.alignItems = 'center';
                header.style.borderTopLeftRadius = '4px';
                header.style.borderTopRightRadius = '4px';

                const title = document.createElement('span');
                title.textContent = 'Select Categories (multi-select)';
                title.style.fontWeight = '600';
                title.style.fontSize = '13px';
                title.style.color = '#495057';
                header.appendChild(title);

                const buttonContainer = document.createElement('div');
                buttonContainer.style.display = 'flex';
                buttonContainer.style.gap = '6px';

                // Clear All button
                const clearBtn = document.createElement('button');
                clearBtn.textContent = 'Clear';
                clearBtn.style.padding = '4px 8px';
                clearBtn.style.fontSize = '11px';
                clearBtn.style.border = '1px solid #dc3545';
                clearBtn.style.backgroundColor = '#fff';
                clearBtn.style.color = '#dc3545';
                clearBtn.style.borderRadius = '4px';
                clearBtn.style.cursor = 'pointer';
                clearBtn.style.fontWeight = '500';
                clearBtn.addEventListener('click', () => this.clearAll());
                buttonContainer.appendChild(clearBtn);

                // Done button
                const doneBtn = document.createElement('button');
                doneBtn.textContent = 'Done';
                doneBtn.style.padding = '4px 12px';
                doneBtn.style.fontSize = '11px';
                doneBtn.style.border = 'none';
                doneBtn.style.backgroundColor = '#4CAF50';
                doneBtn.style.color = 'white';
                doneBtn.style.borderRadius = '4px';
                doneBtn.style.cursor = 'pointer';
                doneBtn.style.fontWeight = '500';
                doneBtn.addEventListener('click', () => {
                    params.stopEditing();
                });
                buttonContainer.appendChild(doneBtn);

                header.appendChild(buttonContainer);
                this.eGui.appendChild(header);

                // Scrollable container for checkboxes
                this.eContainer = document.createElement('div');
                this.eContainer.style.padding = '8px';
                this.eContainer.style.maxHeight = '320px';
                this.eContainer.style.overflowY = 'auto';
                this.eContainer.style.overflowX = 'hidden';

                // Populate checkboxes
                this.populateCheckboxes();

                this.eGui.appendChild(this.eContainer);

                // Status bar showing selection count
                const statusBar = document.createElement('div');
                statusBar.style.padding = '8px 12px';
                statusBar.style.borderTop = '1px solid #dee2e6';
                statusBar.style.backgroundColor = '#f8f9fa';
                statusBar.style.fontSize = '11px';
                statusBar.style.color = '#6c757d';
                statusBar.style.borderBottomLeftRadius = '4px';
                statusBar.style.borderBottomRightRadius = '4px';
                statusBar.id = 'category-status-bar';
                this.updateStatusBar(statusBar);
                this.eGui.appendChild(statusBar);
            }

            async loadCategories() {
                // Check if categories already cached globally
                if (window._cachedCategories && Array.isArray(window._cachedCategories)) {
                    this.categories = window._cachedCategories;
                    return;
                }

                // Try to read from currentData.included (same pattern as attributes)
                try {
                    const currentData = this.params.context?.gridInstance?.currentData;

                    if (currentData && currentData.included && Array.isArray(currentData.included)) {
                        // Extract unique categories from included data
                        const categoriesMap = new Map();
                        currentData.included
                            .filter(item => item.type === 'categories')
                            .forEach(cat => {
                                const catData = cat.attributes || cat;
                                categoriesMap.set(cat.id, {
                                    value: parseInt(cat.id),
                                    label: catData.name,
                                    parent_id: catData.parent_id || 0
                                });
                            });

                        this.categories = Array.from(categoriesMap.values())
                            .sort((a, b) => a.label.localeCompare(b.label));

                        // Cache globally for reuse
                        window._cachedCategories = this.categories;

                        return;
                    }

                    console.warn('[CategoryEditor] No included data available, categories list may be empty');
                    this.categories = [];
                } catch (error) {
                    console.error('[CategoryEditor] Failed to load categories:', error);
                    this.categories = [];
                }
            }

            populateCheckboxes() {
                if (this.categories.length === 0) {
                    const emptyMsg = document.createElement('div');
                    emptyMsg.textContent = 'No categories available';
                    emptyMsg.style.padding = '12px';
                    emptyMsg.style.color = '#6c757d';
                    emptyMsg.style.fontStyle = 'italic';
                    emptyMsg.style.fontSize = '12px';
                    this.eContainer.appendChild(emptyMsg);
                    return;
                }

                // Separate parents and children
                const parents = this.categories.filter(c => !c.parent_id || c.parent_id === 0);
                const children = this.categories.filter(c => c.parent_id && c.parent_id !== 0);

                // Add parent categories and their children
                parents.forEach(parent => {
                    // Add parent checkbox
                    this.addCheckbox(parent, false);

                    // Add children as indented checkboxes
                    const parentChildren = children.filter(c => c.parent_id === parent.value);
                    parentChildren.forEach(child => {
                        this.addCheckbox(child, true);
                    });
                });
            }

            addCheckbox(category, isChild) {
                const checkboxContainer = document.createElement('label');
                checkboxContainer.style.display = 'flex';
                checkboxContainer.style.alignItems = 'center';
                checkboxContainer.style.padding = '6px 8px';
                checkboxContainer.style.cursor = 'pointer';
                checkboxContainer.style.borderRadius = '4px';
                checkboxContainer.style.marginBottom = '2px';
                checkboxContainer.style.transition = 'background-color 0.15s';
                checkboxContainer.style.paddingLeft = isChild ? '28px' : '8px';

                checkboxContainer.addEventListener('mouseenter', () => {
                    checkboxContainer.style.backgroundColor = '#f8f9fa';
                });
                checkboxContainer.addEventListener('mouseleave', () => {
                    checkboxContainer.style.backgroundColor = 'transparent';
                });

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = category.value;
                checkbox.checked = this.selectedIds.has(category.value);
                checkbox.style.marginRight = '8px';
                checkbox.style.cursor = 'pointer';
                checkbox.addEventListener('change', () => this.handleCheckboxChange(category.value, checkbox.checked));

                const label = document.createElement('span');
                label.textContent = isChild ? '└─ ' + category.label : category.label;
                label.style.fontSize = '12px';
                label.style.fontWeight = isChild ? 'normal' : '600';
                label.style.color = isChild ? '#495057' : '#212529';
                label.style.userSelect = 'none';

                checkboxContainer.appendChild(checkbox);
                checkboxContainer.appendChild(label);
                this.eContainer.appendChild(checkboxContainer);

                this.checkboxes.push({ checkbox, categoryId: category.value });
            }

            handleCheckboxChange(categoryId, checked) {
                if (checked) {
                    // Add this category
                    this.selectedIds.add(categoryId);

                    // Auto-select parent if this is a child category
                    const category = this.categories.find(c => c.value === categoryId);
                    if (category && category.parent_id && category.parent_id !== 0) {
                        this.selectedIds.add(category.parent_id);

                        // Update parent checkbox to checked
                        const parentCheckbox = this.checkboxes.find(cb => cb.categoryId === category.parent_id);
                        if (parentCheckbox) {
                            parentCheckbox.checkbox.checked = true;
                        }
                    }
                } else {
                    // Remove this category
                    this.selectedIds.delete(categoryId);

                    // Auto-deselect children if this is a parent category
                    const children = this.categories.filter(c => c.parent_id === categoryId);
                    if (children.length > 0) {
                        children.forEach(child => {
                            this.selectedIds.delete(child.value);

                            // Update child checkbox to unchecked
                            const childCheckbox = this.checkboxes.find(cb => cb.categoryId === child.value);
                            if (childCheckbox) {
                                childCheckbox.checkbox.checked = false;
                            }
                        });
                    }
                }

                // Update status bar
                const statusBar = this.eGui.querySelector('#category-status-bar');
                if (statusBar) {
                    this.updateStatusBar(statusBar);
                }
            }

            updateStatusBar(statusBar) {
                const count = this.selectedIds.size;
                statusBar.textContent = count === 0
                    ? 'No categories selected'
                    : `${count} categor${count === 1 ? 'y' : 'ies'} selected`;
            }

            clearAll() {
                this.selectedIds.clear();
                this.checkboxes.forEach(({ checkbox }) => {
                    checkbox.checked = false;
                });

                // Update status bar
                const statusBar = this.eGui.querySelector('#category-status-bar');
                if (statusBar) {
                    this.updateStatusBar(statusBar);
                }
            }

            getGui() {
                return this.eGui;
            }

            getValue() {
                // Return comma-separated category IDs
                const value = Array.from(this.selectedIds).sort((a, b) => a - b).join(',');
                return value || null;
            }

            destroy() {
                this.isDestroyed = true;
            }

            isPopup() {
                return true;
            }

            isCancelBeforeStart() {
                return false;
            }

            isCancelAfterEnd() {
                return false;
            }

            focusIn() {
                // Focus on first checkbox
                if (this.checkboxes.length > 0 && !this.isDestroyed) {
                    this.checkboxes[0].checkbox.focus();
                }
            }

            focusOut() {
                // Nothing to do
            }
        }

        return CategoryEditor;
    }
}

// CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GridRenderer };
}
