{{-- Products Management Interface --}}
<div id="product-table" class="products-package-container">
    {{-- Filters and Search --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-filter"></i> Product Filters
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="search-input" class="form-label">Search Products</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="search-input" class="form-control"
                               placeholder="Search by name, SKU, or description...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="category-filter" class="form-label">Category</label>
                    <select id="category-filter" class="form-select">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status-filter" class="form-label">Status</label>
                    <select id="status-filter" class="form-select">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort-filter" class="form-label">Sort By</label>
                    <select id="sort-filter" class="form-select">
                        <option value="created_at">Created Date</option>
                        <option value="name">Name</option>
                        <option value="price">Price</option>
                        <option value="stock">Stock</option>
                        <option value="category">Category</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort-order" class="form-label">Order</label>
                    <select id="sort-order" class="form-select">
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <button id="add-product-btn" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>
                <div class="col-md-3">
                    <button id="export-csv-btn" class="btn btn-success w-100">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                <div class="col-md-3">
                    <label for="import-csv-file" class="btn btn-info w-100 mb-0">
                        <i class="fas fa-upload"></i> Import CSV
                    </label>
                    <input type="file" id="import-csv-file" accept=".csv,.txt" style="display: none;">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-2">
                        <input type="checkbox" id="show-deleted" class="form-check-input">
                        <label for="show-deleted" class="form-check-label">
                            <i class="fas fa-trash"></i> Show Deleted
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Loading and Error Messages --}}
    <div id="loading" class="alert alert-info" style="display: none;">
        <i class="fas fa-spinner fa-spin"></i> Loading products...
    </div>

    <div id="error-message" class="alert alert-danger" style="display: none;">
        <i class="fas fa-exclamation-triangle"></i> <span id="error-text"></span>
    </div>

    <div id="success-message" class="alert alert-success" style="display: none;">
        <i class="fas fa-check-circle"></i> <span id="success-text"></span>
    </div>

    {{-- Products Table --}}
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-boxes"></i> Products
                <span id="product-count" class="badge bg-secondary"></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        {{-- Products will be loaded here --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    <div id="pagination" class="d-flex justify-content-between align-items-center mt-4">
        <div>
            <span id="pagination-info" class="text-muted"></span>
        </div>
        <div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" id="prev-page-item">
                        <button id="prev-page-btn" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                    </li>
                    <li class="page-item active">
                        <span id="current-page" class="page-link">Page 1</span>
                    </li>
                    <li class="page-item" id="next-page-item">
                        <button id="next-page-btn" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

{{-- Product Modal --}}
<div id="product-modal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">
                    <i class="fas fa-box"></i> Add Product
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="product-form">
                    <input type="hidden" id="product-id">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="product-name" class="form-label">
                                    Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="product-name" class="form-control" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product-sku" class="form-label">SKU</label>
                                <input type="text" id="product-sku" class="form-control">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="product-description" class="form-label">Description</label>
                        <textarea id="product-description" class="form-control" rows="3"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product-price" class="form-label">
                                    Price <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" id="product-price" class="form-control"
                                           step="0.01" min="0" required>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product-stock" class="form-label">
                                    Stock <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="product-stock" class="form-control"
                                       min="0" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product-category" class="form-label">Category</label>
                                <input type="text" id="product-category" class="form-control">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="product-active" class="form-check-input" checked>
                            <label for="product-active" class="form-check-label">
                                <i class="fas fa-toggle-on"></i> Active
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="save-product-btn">
                    <i class="fas fa-save"></i> Save Product
                </button>
            </div>
        </div>
    </div>
</div>

{{-- CSRF Token Meta Tag --}}
@if(!isset($csrfToken))
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endif

<script>
// Configuration
const API_BASE_URL = '/{{ config("products-package.route_prefix", "api/v1") }}';

// Secure CSRF token retrieval - only use meta tag
let CSRF_TOKEN = null;

// Initialize CSRF token securely
function initializeCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        CSRF_TOKEN = metaTag.getAttribute('content');
    } else {
        console.error('CSRF token not found. Please ensure meta tag is present.');
    }
}

// Setup AJAX defaults for CSRF protection
function setupAjaxDefaults() {
    // Setup jQuery AJAX if available
    if (typeof $ !== 'undefined') {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            }
        });
    }
}

// State management
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};
let editingProductId = null;
let searchTimeout = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApplication();
});

/**
 * Initialize the application
 */
function initializeApplication() {
    // Initialize CSRF token first
    initializeCSRFToken();
    setupAjaxDefaults();

    setupEventListeners();
    loadProducts();
    loadCategories();

    // Initialize Bootstrap modal if available
    if (typeof bootstrap !== 'undefined') {
        window.productModal = new bootstrap.Modal(document.getElementById('product-modal'));
    }
}

/**
 * Setup all event listeners
 */
function setupEventListeners() {
    // Search functionality
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (e.target.value.trim()) {
                searchProducts(e.target.value.trim());
            } else {
                loadProducts();
            }
        }, 500);
    });

    // Filter changes
    ['category-filter', 'status-filter', 'sort-filter', 'sort-order'].forEach(id => {
        document.getElementById(id).addEventListener('change', function(e) {
            updateFiltersAndReload();
        });
    });

    document.getElementById('show-deleted').addEventListener('change', function(e) {
        updateFiltersAndReload();
    });

    // Button clicks
    document.getElementById('add-product-btn').addEventListener('click', openAddModal);
    document.getElementById('export-csv-btn').addEventListener('click', exportProducts);
    document.getElementById('import-csv-file').addEventListener('change', importProducts);
    document.getElementById('save-product-btn').addEventListener('click', saveProduct);

    // Pagination
    document.getElementById('prev-page-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadProducts();
        }
    });

    document.getElementById('next-page-btn').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadProducts();
        }
    });

    // Form validation
    setupFormValidation();
}

/**
 * Update filters from form and reload products
 */
function updateFiltersAndReload() {
    currentFilters = {
        category: document.getElementById('category-filter').value,
        is_active: document.getElementById('status-filter').value,
        sort_by: document.getElementById('sort-filter').value,
        sort_order: document.getElementById('sort-order').value,
        with_trashed: document.getElementById('show-deleted').checked
    };

    // Remove empty values
    Object.keys(currentFilters).forEach(key => {
        if (!currentFilters[key] && currentFilters[key] !== false) {
            delete currentFilters[key];
        }
    });

    currentPage = 1; // Reset to first page
    loadProducts();
}

/**
 * Load products from API
 */
async function loadProducts() {
    showLoading(true);
    hideMessages();

    try {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 15,
            ...currentFilters
        });

        const response = await fetch(`${API_BASE_URL}/products?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        renderProducts(data.data || data);
        updatePagination(data);
        updateProductCount(data);

    } catch (error) {
        console.error('Failed to load products:', error);
        showError('Failed to load products: ' + error.message);
    } finally {
        showLoading(false);
    }
}

/**
 * Load categories for filter dropdown
 */
async function loadCategories() {
    try {
        const response = await fetch(`${API_BASE_URL}/products`, {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) return;

        const products = await response.json();
        const data = products.data || products;

        const categories = [...new Set(
            data.map(p => p.category).filter(Boolean)
        )].sort();

        const select = document.getElementById('category-filter');

        // Clear existing options except "All Categories"
        select.innerHTML = '<option value="">All Categories</option>';

        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
}

/**
 * Search products
 */
async function searchProducts(query) {
    showLoading(true);
    hideMessages();

    try {
        const params = new URLSearchParams({
            q: query,
            ...currentFilters
        });

        const response = await fetch(`${API_BASE_URL}/products/search?${params}`, {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const products = await response.json();

        renderProducts(products);
        hidePagination(); // Search results don't use pagination
        updateProductCount({ total: products.length });

    } catch (error) {
        console.error('Search failed:', error);
        showError('Search failed: ' + error.message);
    } finally {
        showLoading(false);
    }
}

/**
 * Render products in the table
 */
function renderProducts(products) {
    const tbody = document.getElementById('products-tbody');
    tbody.innerHTML = '';

    if (!products || products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4">
                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                    <br>No products found
                </td>
            </tr>
        `;
        return;
    }

    products.forEach(product => {
        const row = document.createElement('tr');

        if (product.deleted_at) {
            row.classList.add('table-danger', 'opacity-75');
        }

        const stockClass = getStockClass(product.stock);
        const stockIcon = getStockIcon(product.stock);

        row.innerHTML = `
            <td>${product.id}</td>
            <td>
                <strong>${escapeHtml(product.name)}</strong>
                ${product.description ? `<br><small class="text-muted">${escapeHtml(truncate(product.description, 50))}</small>` : ''}
            </td>
            <td>
                ${product.sku ? `<code>${escapeHtml(product.sku)}</code>` : '<span class="text-muted">-</span>'}
            </td>
            <td>
                <strong>$${parseFloat(product.price).toFixed(2)}</strong>
            </td>
            <td>
                <span class="badge ${stockClass}">
                    <i class="${stockIcon}"></i> ${product.stock}
                </span>
            </td>
            <td>
                ${product.category ? `<span class="badge bg-secondary">${escapeHtml(product.category)}</span>` : '<span class="text-muted">-</span>'}
            </td>
            <td>
                <span class="badge ${product.is_active ? 'bg-success' : 'bg-secondary'}">
                    <i class="fas ${product.is_active ? 'fa-check' : 'fa-times'}"></i>
                    ${product.is_active ? 'Active' : 'Inactive'}
                </span>
                ${product.deleted_at ? '<br><span class="badge bg-danger mt-1"><i class="fas fa-trash"></i> Deleted</span>' : ''}
            </td>
            <td>
                <small class="text-muted">${formatDate(product.created_at)}</small>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    ${!product.deleted_at ? `
                        <button class="btn btn-outline-primary" onclick="editProduct(${product.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteProduct(${product.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : `
                        <button class="btn btn-outline-success" onclick="restoreProduct(${product.id})" title="Restore">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="forceDeleteProduct(${product.id})" title="Permanent Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    `}
                </div>
            </td>
        `;

        tbody.appendChild(row);
    });
}

/**
 * Update pagination controls
 */
function updatePagination(data) {
    const paginationDiv = document.getElementById('pagination');

    if (data.last_page) {
        totalPages = data.last_page;
        currentPage = data.current_page;

        document.getElementById('pagination-info').textContent =
            `Showing ${data.from || 0} to ${data.to || 0} of ${data.total || 0} products`;
        document.getElementById('current-page').textContent = `Page ${currentPage}`;

        // Update pagination button states
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');
        const prevItem = document.getElementById('prev-page-item');
        const nextItem = document.getElementById('next-page-item');

        if (currentPage === 1) {
            prevBtn.disabled = true;
            prevItem.classList.add('disabled');
        } else {
            prevBtn.disabled = false;
            prevItem.classList.remove('disabled');
        }

        if (currentPage === totalPages) {
            nextBtn.disabled = true;
            nextItem.classList.add('disabled');
        } else {
            nextBtn.disabled = false;
            nextItem.classList.remove('disabled');
        }

        paginationDiv.style.display = 'flex';
    } else {
        hidePagination();
    }
}

/**
 * Hide pagination
 */
function hidePagination() {
    document.getElementById('pagination').style.display = 'none';
}

/**
 * Update product count badge
 */
function updateProductCount(data) {
    const countElement = document.getElementById('product-count');
    const count = data.total || (data.data ? data.data.length : 0);
    countElement.textContent = count;
}

/**
 * Delete product
 */
async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product? It can be restored later.')) {
        return;
    }

    if (!CSRF_TOKEN) {
        showError('Security token not available. Please refresh the page.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/products/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            showSuccess('Product deleted successfully');
            loadProducts();
        } else {
            const error = await response.json();
            throw new Error(error.message || 'Delete failed');
        }
    } catch (error) {
        console.error('Delete failed:', error);
        showError('Failed to delete product: ' + error.message);
    }
}

/**
 * Restore product
 */
async function restoreProduct(id) {
    if (!CSRF_TOKEN) {
        showError('Security token not available. Please refresh the page.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/products/${id}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            showSuccess('Product restored successfully');
            loadProducts();
        } else {
            const error = await response.json();
            throw new Error(error.message || 'Restore failed');
        }
    } catch (error) {
        console.error('Restore failed:', error);
        showError('Failed to restore product: ' + error.message);
    }
}

/**
 * Force delete product
 */
async function forceDeleteProduct(id) {
    if (!confirm('Permanently delete this product? This action cannot be undone!')) {
        return;
    }

    if (!CSRF_TOKEN) {
        showError('Security token not available. Please refresh the page.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/products/${id}/force`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            showSuccess('Product permanently deleted');
            loadProducts();
        } else {
            const error = await response.json();
            throw new Error(error.message || 'Force delete failed');
        }
    } catch (error) {
        console.error('Force delete failed:', error);
        showError('Failed to permanently delete product: ' + error.message);
    }
}

/**
 * Edit product
 */
async function editProduct(id) {
    try {
        const response = await fetch(`${API_BASE_URL}/products/${id}`, {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to load product');
        }

        const product = await response.json();

        editingProductId = id;
        document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit"></i> Edit Product';

        // Populate form
        document.getElementById('product-id').value = id;
        document.getElementById('product-name').value = product.name || '';
        document.getElementById('product-sku').value = product.sku || '';
        document.getElementById('product-description').value = product.description || '';
        document.getElementById('product-price').value = product.price || '';
        document.getElementById('product-stock').value = product.stock || '';
        document.getElementById('product-category').value = product.category || '';
        document.getElementById('product-active').checked = product.is_active;

        openProductModal();
    } catch (error) {
        console.error('Failed to load product:', error);
        showError('Failed to load product: ' + error.message);
    }
}

/**
 * Open add modal
 */
function openAddModal() {
    editingProductId = null;
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus"></i> Add Product';
    document.getElementById('product-form').reset();
    document.getElementById('product-active').checked = true;
    clearFormValidation();
    openProductModal();
}

/**
 * Open product modal
 */
function openProductModal() {
    if (window.productModal) {
        window.productModal.show();
    } else {
        document.getElementById('product-modal').style.display = 'block';
    }
}

/**
 * Close product modal
 */
function closeProductModal() {
    if (window.productModal) {
        window.productModal.hide();
    } else {
        document.getElementById('product-modal').style.display = 'none';
    }

    document.getElementById('product-form').reset();
    editingProductId = null;
    clearFormValidation();
}

/**
 * Save product
 */
async function saveProduct() {
    const form = document.getElementById('product-form');

    if (!validateForm()) {
        return;
    }

    if (!CSRF_TOKEN) {
        showError('Security token not available. Please refresh the page.');
        return;
    }

    const data = {
        name: document.getElementById('product-name').value.trim(),
        sku: document.getElementById('product-sku').value.trim(),
        description: document.getElementById('product-description').value.trim(),
        price: parseFloat(document.getElementById('product-price').value) || 0,
        stock: parseInt(document.getElementById('product-stock').value) || 0,
        category: document.getElementById('product-category').value.trim(),
        is_active: document.getElementById('product-active').checked
    };

    try {
        const url = editingProductId
            ? `${API_BASE_URL}/products/${editingProductId}`
            : `${API_BASE_URL}/products`;

        const method = editingProductId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            closeProductModal();
            showSuccess(editingProductId ? 'Product updated successfully' : 'Product created successfully');
            loadProducts();
            loadCategories(); // Refresh categories in case a new one was added
        } else {
            const error = await response.json();
            if (error.errors) {
                showValidationErrors(error.errors);
            } else {
                throw new Error(error.message || 'Save failed');
            }
        }
    } catch (error) {
        console.error('Save failed:', error);
        showError('Failed to save product: ' + error.message);
    }
}

/**
 * Export products to CSV
 */
async function exportProducts() {
    try {
        const params = new URLSearchParams(currentFilters);
        const response = await fetch(`${API_BASE_URL}/products/export?${params}`, {
            headers: {
                'Accept': 'text/csv'
            }
        });

        if (!response.ok) {
            throw new Error('Export failed');
        }

        const blob = await response.blob();

        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `products-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showSuccess('Products exported successfully');
    } catch (error) {
        console.error('Export failed:', error);
        showError('Export failed: ' + error.message);
    }
}

/**
 * Import products from CSV
 */
async function importProducts(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (!CSRF_TOKEN) {
        showError('Security token not available. Please refresh the page.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        showLoading(true);

        const response = await fetch(`${API_BASE_URL}/products/import`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok) {
            const message = `Import completed! Imported: ${result.imported}, Errors: ${result.total_errors}`;
            showSuccess(message);

            if (result.errors && result.errors.length > 0) {
                console.warn('Import errors:', result.errors);
            }

            loadProducts();
            loadCategories();
        } else {
            throw new Error(result.message || 'Import failed');
        }
    } catch (error) {
        console.error('Import failed:', error);
        showError('Import failed: ' + error.message);
    } finally {
        showLoading(false);
        event.target.value = ''; // Clear file input
    }
}

/**
 * Form validation
 */
function setupFormValidation() {
    // Add real-time validation
    document.getElementById('product-name').addEventListener('blur', validateField);
    document.getElementById('product-price').addEventListener('blur', validateField);
    document.getElementById('product-stock').addEventListener('blur', validateField);
}

function validateField(event) {
    const field = event.target;
    const value = field.value.trim();

    clearFieldError(field);

    switch (field.id) {
        case 'product-name':
            if (!value) {
                showFieldError(field, 'Product name is required');
                return false;
            }
            break;
        case 'product-price':
            if (!value || isNaN(value) || parseFloat(value) < 0) {
                showFieldError(field, 'Please enter a valid price');
                return false;
            }
            break;
        case 'product-stock':
            if (!value || isNaN(value) || parseInt(value) < 0) {
                showFieldError(field, 'Please enter a valid stock quantity');
                return false;
            }
            break;
    }

    return true;
}

function validateForm() {
    const requiredFields = ['product-name', 'product-price', 'product-stock'];
    let isValid = true;

    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!validateField({ target: field })) {
            isValid = false;
        }
    });

    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = message;
    }
}

function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = '';
    }
}

function clearFormValidation() {
    document.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
    });
    document.querySelectorAll('.invalid-feedback').forEach(feedback => {
        feedback.textContent = '';
    });
}

function showValidationErrors(errors) {
    Object.keys(errors).forEach(field => {
        const fieldElement = document.getElementById(`product-${field}`);
        if (fieldElement) {
            showFieldError(fieldElement, errors[field][0]);
        }
    });
}

/**
 * Utility functions
 */
function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

function showError(message) {
    document.getElementById('error-text').textContent = message;
    document.getElementById('error-message').style.display = 'block';

    // Auto-hide after 5 seconds
    setTimeout(() => {
        document.getElementById('error-message').style.display = 'none';
    }, 5000);
}

function showSuccess(message) {
    document.getElementById('success-text').textContent = message;
    document.getElementById('success-message').style.display = 'block';

    // Auto-hide after 3 seconds
    setTimeout(() => {
        document.getElementById('success-message').style.display = 'none';
    }, 3000);
}

function hideMessages() {
    document.getElementById('error-message').style.display = 'none';
    document.getElementById('success-message').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncate(text, length) {
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

function getStockClass(stock) {
    if (stock === 0) return 'bg-danger';
    if (stock <= 10) return 'bg-warning text-dark';
    return 'bg-success';
}

function getStockIcon(stock) {
    if (stock === 0) return 'fas fa-times';
    if (stock <= 10) return 'fas fa-exclamation-triangle';
    return 'fas fa-check';
}

// Global function for modal close (Bootstrap fallback)
window.closeProductModal = closeProductModal;
</script>

<style>
.products-package-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.products-package-container .card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.products-package-container .table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.products-package-container .btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.products-package-container .badge {
    font-size: 0.75em;
}

.products-package-container .opacity-75 {
    opacity: 0.75;
}

.products-package-container .modal-lg {
    max-width: 800px;
}

.products-package-container .table-responsive {
    border-radius: 0.375rem;
}

.products-package-container .pagination-sm .page-link {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .products-package-container .btn-group {
        display: flex;
        flex-direction: column;
    }

    .products-package-container .btn-group > .btn {
        margin-bottom: 0.25rem;
    }
}
</style>