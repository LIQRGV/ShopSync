# NPM Package - Technical Versioning

Technical documentation for `@thediamondbox/grid-sync` NPM package.

---

## Current Version: v1.1.0

- **Version**: v1.1.0
- **Release Date**: 2025-10-28
- **Previous**: v1.0.0
- **Type**: JavaScript Module (ESM)
- **Repository**: https://github.com/The-Diamond-Box/module-shopsync

### What's New in v1.1.0

CSV operations enhancement and modal interference fixes. Main update: extracting CSV preview functionality into dedicated `CsvPreviewHandler` class, reducing ProductSyncGrid.js by 23% while fixing Bootstrap modal backdrop bugs.

**Key Updates**:
- ✨ NEW: CsvPreviewHandler class for CSV import with preview modal
- 🚀 ENHANCED: CSV export now supports ALL products (not limited to pagination)
- 🐛 FIX: Modal backdrop CSS scoping - no longer interferes with other modals
- ♻️ REFACTOR: Better code organization with 376 lines extracted from ProductSyncGrid

**Breaking Changes**: None (fully backward compatible)

### New Features

#### 1. CsvPreviewHandler Class
**File**: `grid-sync-js/src/managers/CsvPreviewHandler.js` (NEW)

Dedicated handler for CSV import with preview:
- Parse CSV files and show first 10 rows preview
- Modal confirmation before import
- Auto-exclude image columns
- XSS protection via `escapeHtml()`
- Smart modal cleanup (doesn't interfere with other modals)

**Configuration Constants**:
```javascript
this.PREVIEW_ROW_COUNT = 10;
this.MAX_CELL_DISPLAY_LENGTH = 50;
this.MODAL_CLEANUP_DELAY = 50;
```

#### 2. Enhanced CSV Export
**File**: `grid-sync-js/src/managers/ClipboardManager.js` (ENHANCED)

CSV export improvements:
- Export ALL products (not just current page)
- Warning popup for large exports (>1000 products)
- Auto-exclude image columns
- State restoration after export

### Bug Fixes

#### Critical: Modal Backdrop Interference
**Problem**: CSV modal CSS was global and affected ALL modals on page

**Solution**: Scope all CSS to `#csvPreviewModal` only
```css
/* Before: Global (affected all modals) */
.modal { display: flex; }

/* After: Scoped (only #csvPreviewModal) */
#csvPreviewModal.modal { display: flex; }
```

#### Orphaned Backdrop Elements
Smart cleanup that counts modal vs backdrop elements and removes only excess backdrops.

### Code Quality

**ProductSyncGrid.js Reduction**:
- Before: 1,629 lines
- After: 1,253 lines
- Reduction: 376 lines (23%)

**Improvements**:
- Replace magic numbers with constants
- Remove HTML comments from production
- Consolidate duplicate CSS rules
- Better JSDoc documentation

### Files Changed

```
grid-sync-js/src/managers/CsvPreviewHandler.js (NEW: +457 lines)
grid-sync-js/src/core/ProductSyncGrid.js (REFACTORED: -376 lines)
grid-sync-js/src/managers/ClipboardManager.js (ENHANCED: +113 lines)
grid-sync-js/src/index.js (UPDATED: +1 line)
grid-sync-js/package.json (VERSION: 1.0.0 → 1.1.0)
```

### Migration from v1.0.0

**No changes required!** This is a backward-compatible release.

**Optional**: If you want to use CsvPreviewHandler separately:
```javascript
import { CsvPreviewHandler } from '@thediamondbox/grid-sync';
```

---

## Version History

### v1.1.0 (2025-10-28) - Current

CSV Operations Enhancement
- New CsvPreviewHandler class
- Enhanced CSV export with full data support
- Fixed modal backdrop interference bugs
- 23% size reduction in ProductSyncGrid.js

See details above.

### v1.0.0 (2025-10-22)

First stable release of unified AG Grid implementation.

**Key Features**:
- Unified AG Grid implementation
- Dynamic attribute columns with grouping
- Real-time synchronization via SSE
- CSV import/export functionality
- Automatic Laravel integration
- Clipboard management

---

## Package Structure (v1.1.0)

```
grid-sync-js/
├── package.json
├── README.md
├── src/
│   ├── index.js                       # Main entry point
│   ├── core/
│   │   ├── ProductSyncGrid.js         # Grid initialization & management (REFACTORED)
│   │   └── GridDataAdapter.js         # Data transformation layer
│   ├── api/
│   │   └── ProductGridApiClient.js    # API communication
│   ├── realtime/
│   │   └── SSEClient.js               # Server-Sent Events client
│   ├── managers/
│   │   ├── CsvPreviewHandler.js       # CSV import with preview (NEW)
│   │   ├── SelectionHandler.js        # Row selection management
│   │   └── ClipboardManager.js        # Copy/paste functionality (ENHANCED)
│   ├── renderers/
│   │   └── GridRenderer.js            # Custom cell renderers
│   └── constants/
│       └── ProductGridConstants.js    # Configuration constants
└── postinstall.cjs                    # Auto-sync to Laravel public
```

---

## Package Structure

```
grid-sync-js/
├── package.json
├── README.md
├── src/
│   ├── index.js                       # Main entry point
│   ├── core/
│   │   ├── ProductSyncGrid.js         # Grid initialization & management
│   │   └── GridDataAdapter.js         # Data transformation layer
│   ├── api/
│   │   └── ProductGridApiClient.js    # API communication
│   ├── realtime/
│   │   └── SSEClient.js               # Server-Sent Events client
│   ├── managers/
│   │   ├── SelectionHandler.js        # Row selection management
│   │   └── ClipboardManager.js        # Copy/paste functionality
│   ├── renderers/
│   │   └── GridRenderer.js            # Custom cell renderers
│   └── constants/
│       └── ProductGridConstants.js    # Configuration constants
└── postinstall.cjs                    # Auto-sync to Laravel public
```

---

## Core Features

### 1. ProductSyncGrid

Main grid controller that manages AG Grid instance.

#### Implementation

```javascript
// src/core/ProductSyncGrid.js
export class ProductSyncGrid {
    constructor(config) {
        this.config = {
            apiUrl: config.apiUrl,
            mode: config.mode || 'wl',
            clientId: config.clientId,
            sseEnabled: config.sseEnabled !== false,
            ...config
        };

        this.gridApi = null;
        this.columnApi = null;
        this.apiClient = null;
        this.sseClient = null;
    }

    async init(containerElement) {
        // Initialize API client
        this.apiClient = new ProductGridApiClient(this.config);

        // Fetch and prepare column definitions
        const columnDefs = await this.prepareColumns();

        // Initialize AG Grid
        const gridOptions = {
            columnDefs: columnDefs,
            rowData: [],
            ...this.getDefaultGridOptions()
        };

        this.gridApi = agGrid.createGrid(containerElement, gridOptions);

        // Load initial data
        await this.loadData();

        // Initialize SSE if enabled
        if (this.config.sseEnabled) {
            this.initializeSSE();
        }
    }

    async prepareColumns() {
        // Fetch attribute definitions
        const attributes = await this.apiClient.getAttributes();

        // Generate base columns
        const baseColumns = this.getBaseColumns();

        // Generate dynamic attribute columns with grouping
        const attributeColumns = this.generateAttributeColumns(attributes);

        return [...baseColumns, ...attributeColumns];
    }

    generateAttributeColumns(attributes) {
        // Filter attributes enabled for dropship (WTM mode)
        const enabledAttributes = attributes.filter(attr => {
            if (this.config.mode === 'wtm') {
                return attr.enabled_on_dropship === true;
            }
            return true;
        });

        // Group attributes by group_name
        const grouped = this.groupBy(enabledAttributes, 'group_name');

        // Generate column groups
        const columnGroups = [];

        for (const [groupName, attrs] of Object.entries(grouped)) {
            if (groupName && groupName !== 'null') {
                // Create column group
                columnGroups.push({
                    headerName: groupName,
                    children: attrs.map(attr => ({
                        field: `attr_${attr.id}`,
                        headerName: attr.name,
                        editable: true,
                        cellRenderer: this.getAttributeCellRenderer(attr.type)
                    }))
                });
            } else {
                // Ungrouped attributes
                attrs.forEach(attr => {
                    columnGroups.push({
                        field: `attr_${attr.id}`,
                        headerName: attr.name,
                        editable: true,
                        cellRenderer: this.getAttributeCellRenderer(attr.type)
                    });
                });
            }
        }

        return columnGroups;
    }
}
```

#### Usage Example

```javascript
import { ProductSyncGrid } from '@thediamondbox/grid-sync';

// Initialize grid
const grid = new ProductSyncGrid({
    apiUrl: '/api/v1',
    mode: 'wtm',
    clientId: '123',
    sseEnabled: true
});

// Mount to DOM
await grid.init(document.getElementById('product-grid'));
```

---

### 2. Dynamic Attribute Columns

Automatic column generation based on attribute configuration.

#### Attribute Processing

```javascript
// src/core/ProductSyncGrid.js
generateAttributeColumns(attributes) {
    // Step 1: Filter by mode
    const filtered = this.config.mode === 'wtm'
        ? attributes.filter(a => a.enabled_on_dropship)
        : attributes;

    // Step 2: Sort by group and order
    const sorted = filtered.sort((a, b) => {
        if (a.group_name !== b.group_name) {
            return (a.group_name || '').localeCompare(b.group_name || '');
        }
        return (a.sort_order || 0) - (b.sort_order || 0);
    });

    // Step 3: Group by group_name
    const groups = this.groupBy(sorted, 'group_name');

    // Step 4: Generate column definitions
    return this.createColumnDefinitions(groups);
}
```

#### Column Group Structure

```javascript
// Example output
[
    {
        headerName: 'Specifications',
        children: [
            {
                field: 'attr_1',
                headerName: 'Size',
                editable: true,
                cellEditor: 'agTextCellEditor'
            },
            {
                field: 'attr_2',
                headerName: 'Weight',
                editable: true,
                cellEditor: 'agNumberCellEditor'
            }
        ]
    },
    {
        headerName: 'Features',
        children: [
            {
                field: 'attr_3',
                headerName: 'Color',
                editable: true,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: {
                    values: ['Red', 'Blue', 'Green']
                }
            }
        ]
    }
]
```

#### Pre-loading Strategy

Prevents grid column reordering by loading attribute columns upfront:

```javascript
async prepareColumns() {
    // Fetch attributes BEFORE grid initialization
    const attributes = await this.apiClient.getAttributes();

    // Generate all columns including attributes
    const allColumns = [
        ...this.getBaseColumns(),
        ...this.generateAttributeColumns(attributes)
    ];

    // Pass complete column definition to grid
    return allColumns;
}
```

**Why pre-loading?**
- Prevents columns jumping when attributes load dynamically
- Ensures consistent column order
- Improves user experience
- Eliminates grid repainting

---

### 3. Real-time Synchronization (SSE)

Server-Sent Events client for real-time product updates.

#### Implementation

```javascript
// src/realtime/SSEClient.js
export class SSEClient {
    constructor(config) {
        this.eventSource = null;
        this.config = config;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
    }

    connect() {
        const url = `${this.config.apiUrl}/sse/events`;

        // Add client-id for WTM mode
        const headers = {};
        if (this.config.clientId) {
            headers['client-id'] = this.config.clientId;
        }

        // Create EventSource connection
        this.eventSource = new EventSource(url);

        // Listen for product.created
        this.eventSource.addEventListener('product.created', (event) => {
            const product = JSON.parse(event.data);
            this.onProductCreated(product);
        });

        // Listen for product.updated
        this.eventSource.addEventListener('product.updated', (event) => {
            const product = JSON.parse(event.data);
            this.onProductUpdated(product);
        });

        // Listen for product.deleted
        this.eventSource.addEventListener('product.deleted', (event) => {
            const data = JSON.parse(event.data);
            this.onProductDeleted(data.id);
        });

        // Handle connection errors
        this.eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);
            this.reconnect();
        };
    }

    onProductCreated(product) {
        // Add new row to grid
        if (this.gridApi) {
            this.gridApi.applyTransaction({
                add: [product],
                addIndex: 0
            });
        }
    }

    onProductUpdated(product) {
        // Update existing row
        if (this.gridApi) {
            this.gridApi.applyTransaction({
                update: [product]
            });
        }
    }

    onProductDeleted(productId) {
        // Remove row from grid
        if (this.gridApi) {
            const rowNode = this.gridApi.getRowNode(productId);
            if (rowNode) {
                this.gridApi.applyTransaction({
                    remove: [rowNode.data]
                });
            }
        }
    }

    reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);

        setTimeout(() => {
            console.log(`Reconnecting... (attempt ${this.reconnectAttempts})`);
            this.connect();
        }, delay);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }
}
```

#### Event Handling

**product.created**:
```javascript
{
    event: 'product.created',
    data: {
        id: 123,
        name: 'New Product',
        price: '29.99',
        // ... full product data
    }
}
```

**product.updated**:
```javascript
{
    event: 'product.updated',
    data: {
        id: 123,
        name: 'Updated Product',
        price: '24.99',
        // ... updated product data
    }
}
```

**product.deleted**:
```javascript
{
    event: 'product.deleted',
    data: {
        id: 123
    }
}
```

---

### 4. API Client

HTTP client for product API communication.

#### Implementation

```javascript
// src/api/ProductGridApiClient.js
export class ProductGridApiClient {
    constructor(config) {
        this.baseUrl = config.apiUrl;
        this.mode = config.mode;
        this.clientId = config.clientId;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        // Add client-id for WTM mode
        if (this.mode === 'wtm' && this.clientId) {
            headers['client-id'] = this.clientId;
        }

        const response = await fetch(url, {
            ...options,
            headers
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }

    async getProducts(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`/products?${queryString}`);
    }

    async getProduct(id) {
        return this.request(`/products/${id}`);
    }

    async createProduct(data) {
        return this.request('/products', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async updateProduct(id, data) {
        return this.request(`/products/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    async deleteProduct(id) {
        return this.request(`/products/${id}`, {
            method: 'DELETE'
        });
    }

    async getAttributes() {
        // Fetch attribute definitions for column generation
        return this.request('/attributes');
    }

    async exportProducts(format = 'csv') {
        return this.request(`/products/export?format=${format}`);
    }

    async importProducts(file) {
        const formData = new FormData();
        formData.append('file', file);

        return this.request('/products/import', {
            method: 'POST',
            headers: {},  // Let browser set Content-Type for FormData
            body: formData
        });
    }
}
```

---

### 5. CSV Import/Export

Clipboard and CSV file handling.

#### ClipboardManager

```javascript
// src/managers/ClipboardManager.js
export class ClipboardManager {
    constructor(gridApi) {
        this.gridApi = gridApi;
    }

    copySelectedRows() {
        const selectedRows = this.gridApi.getSelectedRows();

        if (selectedRows.length === 0) {
            alert('No rows selected');
            return;
        }

        // Convert to CSV format
        const csv = this.convertToCSV(selectedRows);

        // Copy to clipboard
        navigator.clipboard.writeText(csv)
            .then(() => {
                console.log('Copied to clipboard');
            })
            .catch(err => {
                console.error('Failed to copy:', err);
            });
    }

    async pasteFromClipboard() {
        try {
            const text = await navigator.clipboard.readText();
            const rows = this.parseCSV(text);

            // Validate data
            const validRows = rows.filter(row => this.validateRow(row));

            // Add to grid
            this.gridApi.applyTransaction({
                add: validRows
            });

            console.log(`Pasted ${validRows.length} rows`);
        } catch (err) {
            console.error('Failed to paste:', err);
        }
    }

    convertToCSV(rows) {
        // Get column headers
        const columns = this.gridApi.getColumnDefs();
        const headers = columns.map(col => col.headerName).join(',');

        // Convert rows to CSV
        const csvRows = rows.map(row => {
            return columns.map(col => {
                const value = row[col.field] || '';
                // Escape commas and quotes
                return `"${String(value).replace(/"/g, '""')}"`;
            }).join(',');
        });

        return [headers, ...csvRows].join('\n');
    }

    parseCSV(text) {
        // Simple CSV parser
        const lines = text.split('\n');
        const headers = lines[0].split(',');

        return lines.slice(1).map(line => {
            const values = line.split(',');
            const row = {};

            headers.forEach((header, index) => {
                row[header.trim()] = values[index]?.trim() || '';
            });

            return row;
        });
    }

    validateRow(row) {
        // Validate required fields
        return row.name && row.name.trim() !== '';
    }
}
```

#### Usage

```javascript
// In ProductSyncGrid
setupClipboard() {
    this.clipboardManager = new ClipboardManager(this.gridApi);

    // Copy selected rows (Ctrl+C)
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'c') {
            this.clipboardManager.copySelectedRows();
        }
    });

    // Paste from clipboard (Ctrl+V)
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'v') {
            this.clipboardManager.pasteFromClipboard();
        }
    });
}
```

---

### 6. Grid Renderers

Custom cell renderers for different data types.

#### Implementation

```javascript
// src/renderers/GridRenderer.js
export class GridRenderer {
    static priceRenderer(params) {
        if (!params.value) return '';

        const price = parseFloat(params.value);
        return `£${price.toFixed(2)}`;
    }

    static imageRenderer(params) {
        if (!params.value) return '';

        return `<img src="${params.value}" style="width: 50px; height: 50px; object-fit: cover;" />`;
    }

    static statusRenderer(params) {
        const status = params.value;
        const colors = {
            'active': 'green',
            'inactive': 'red',
            'draft': 'orange'
        };

        const color = colors[status] || 'gray';

        return `<span style="color: ${color}; font-weight: bold;">${status}</span>`;
    }

    static booleanRenderer(params) {
        return params.value ? '✓' : '✗';
    }

    static dateRenderer(params) {
        if (!params.value) return '';

        const date = new Date(params.value);
        return date.toLocaleDateString('en-GB');
    }

    static attributeRenderer(attributeType) {
        return (params) => {
            switch (attributeType) {
                case 'boolean':
                    return GridRenderer.booleanRenderer(params);
                case 'date':
                    return GridRenderer.dateRenderer(params);
                case 'color':
                    return `<div style="width: 30px; height: 30px; background: ${params.value}; border: 1px solid #ccc;"></div>`;
                default:
                    return params.value || '';
            }
        };
    }
}
```

#### Usage in Column Definitions

```javascript
{
    field: 'price',
    headerName: 'Price',
    cellRenderer: GridRenderer.priceRenderer
},
{
    field: 'image',
    headerName: 'Image',
    cellRenderer: GridRenderer.imageRenderer
},
{
    field: 'status',
    headerName: 'Status',
    cellRenderer: GridRenderer.statusRenderer
}
```

---

## Automatic Laravel Integration

### Postinstall Script

```javascript
// postinstall.cjs
const fs = require('fs');
const path = require('path');

console.log('[@thediamondbox/grid-sync] Running postinstall...');

// Detect Laravel installation
const publicPath = path.join(process.cwd(), '..', '..', 'public');

if (fs.existsSync(publicPath)) {
    console.log('Laravel installation detected');

    // Create vendor directory
    const vendorPath = path.join(publicPath, 'vendor', 'grid-sync');
    fs.mkdirSync(vendorPath, { recursive: true });

    // Copy grid-sync-js files
    const srcPath = path.join(__dirname, 'grid-sync-js', 'src');
    const destPath = path.join(vendorPath, 'src');

    copyRecursive(srcPath, destPath);

    console.log('[@thediamondbox/grid-sync] Files synced to public/vendor/grid-sync');
} else {
    console.log('Not a Laravel project, skipping file sync');
}

function copyRecursive(src, dest) {
    fs.mkdirSync(dest, { recursive: true });

    const entries = fs.readdirSync(src, { withFileTypes: true });

    for (const entry of entries) {
        const srcPath = path.join(src, entry.name);
        const destPath = path.join(dest, entry.name);

        if (entry.isDirectory()) {
            copyRecursive(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }
}
```

**Result**:
```
public/
└── vendor/
    └── grid-sync/
        └── src/
            ├── index.js
            ├── core/
            ├── api/
            ├── realtime/
            ├── managers/
            ├── renderers/
            └── constants/
```

**Usage in Blade**:
```html
<script type="module">
    import { ProductSyncGrid } from '/vendor/grid-sync/src/index.js';

    const grid = new ProductSyncGrid({
        apiUrl: '/api/v1',
        mode: 'wtm',
        clientId: '{{ $clientId }}'
    });

    grid.init(document.getElementById('product-grid'));
</script>
```

---

## Configuration

### Package Configuration

```javascript
// Example configuration
const config = {
    // API endpoint base URL
    apiUrl: '/api/v1',

    // Operating mode: 'wl' or 'wtm'
    mode: 'wtm',

    // Client ID for WTM mode
    clientId: '123',

    // Enable real-time updates
    sseEnabled: true,

    // Grid options
    gridOptions: {
        pagination: true,
        paginationPageSize: 50,
        enableRangeSelection: true,
        enableFillHandle: true
    }
};
```

### AG Grid Options

```javascript
getDefaultGridOptions() {
    return {
        // Row selection
        rowSelection: 'multiple',
        suppressRowClickSelection: true,

        // Editing
        editType: 'fullRow',
        stopEditingWhenCellsLoseFocus: true,

        // Pagination
        pagination: true,
        paginationPageSize: 50,

        // Performance
        animateRows: true,
        enableCellChangeFlash: true,

        // Features
        enableRangeSelection: true,
        enableFillHandle: true,
        undoRedoCellEditing: true,

        // Callbacks
        onCellValueChanged: (event) => this.onCellChanged(event),
        onRowSelected: (event) => this.onRowSelected(event)
    };
}
```

---

## Dependencies

### Peer Dependencies

```json
{
    "peerDependencies": {
        "ag-grid-community": ">=28.0.0"
    }
}
```

**Note**: AG Grid must be installed separately by consuming application.

### Installation

```bash
npm install ag-grid-community @thediamondbox/grid-sync
```

---

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

**Required Features**:
- ES6 Modules
- EventSource (SSE)
- Fetch API
- Clipboard API

---

## Usage Examples

### Basic Initialization

```javascript
import { ProductSyncGrid } from '@thediamondbox/grid-sync';

const grid = new ProductSyncGrid({
    apiUrl: '/api/v1',
    mode: 'wl'
});

await grid.init(document.getElementById('grid-container'));
```

### WTM Mode with Client ID

```javascript
const grid = new ProductSyncGrid({
    apiUrl: '/api/v1',
    mode: 'wtm',
    clientId: '123',
    sseEnabled: true
});

await grid.init(document.getElementById('grid-container'));
```

### Custom Grid Options

```javascript
const grid = new ProductSyncGrid({
    apiUrl: '/api/v1',
    mode: 'wl',
    gridOptions: {
        pagination: false,
        paginationPageSize: 100,
        rowHeight: 60
    }
});

await grid.init(document.getElementById('grid-container'));
```

### Programmatic Control

```javascript
const grid = new ProductSyncGrid({ /* config */ });
await grid.init(document.getElementById('grid-container'));

// Get selected rows
const selected = grid.gridApi.getSelectedRows();

// Export to CSV
const csv = grid.exportToCSV();

// Refresh data
await grid.loadData();

// Add new row
grid.gridApi.applyTransaction({
    add: [{ name: 'New Product', price: 29.99 }]
});
```

---

## Migration Notes

### From Manual Implementation

If migrating from manual AG Grid setup:

1. **Remove manual column definitions**
   - Package auto-generates columns including attributes

2. **Remove SSE implementation**
   - Package handles SSE connection and updates

3. **Update API calls**
   - Use `ProductGridApiClient` instead of custom fetch

4. **Install package**
   ```bash
   npm install @thediamondbox/grid-sync
   ```

5. **Update initialization code**
   ```javascript
   // Before
   const gridOptions = { /* manual setup */ };
   new agGrid.Grid(element, gridOptions);

   // After
   import { ProductSyncGrid } from '@thediamondbox/grid-sync';
   const grid = new ProductSyncGrid(config);
   await grid.init(element);
   ```

---

## Performance Considerations

### Lazy Loading

Attributes are loaded once during initialization, not on every row render.

### Virtual Scrolling

AG Grid's virtual scrolling handles large datasets efficiently.

### SSE Optimization

- Connection reuse
- Event debouncing
- Automatic reconnection with exponential backoff

### Memory Management

- Detached event listeners on destroy
- Closed SSE connections
- Cleared grid data

---

## Troubleshooting

### Grid Not Initializing

**Check**:
- AG Grid installed: `npm list ag-grid-community`
- Container element exists in DOM
- API endpoint is correct

### Attributes Not Showing

**Check**:
- Attributes API endpoint returns data
- Attributes have `enabled_on_dropship: true` (WTM mode)
- `group_name` field is set correctly

### SSE Not Working

**Check**:
- SSE endpoint accessible: `/api/v1/sse/events`
- Server supports SSE (check headers)
- Browser supports EventSource API

### Postinstall Not Running

**Check**:
- Laravel project structure (public/ directory exists)
- File permissions
- Run manually: `node node_modules/@thediamondbox/grid-sync/postinstall.cjs`

---

## References

- AG Grid Documentation: https://www.ag-grid.com/documentation/
- Server-Sent Events: https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events
- ES6 Modules: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules
- Clipboard API: https://developer.mozilla.org/en-US/docs/Web/API/Clipboard_API
