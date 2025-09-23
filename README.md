# ShopSync - Laravel Product Management API Package

A Laravel API-only package for product management with flexible authentication and dual-mode operations (Database/API). This package is designed for headless applications and API integrations.

## Features

- **API-Only Architecture**: Pure REST API implementation for headless applications
- **Dual Operation Modes**: WL (WhiteLabel) for direct database operations, WTM (Watch the Market) for API-based operations
- **Complete REST API**: Full CRUD operations with search, export, and import functionality
- **Flexible Authentication**: Support for multiple authentication methods including Bearer tokens, API keys, and Basic auth
- **Soft Deletes**: Products can be soft deleted and restored
- **Advanced Search**: Database-optimized search with MySQL FULLTEXT support
- **Export/Import**: Secure CSV export and import with validation
- **PHP 7.2+ & Laravel 7-12 Compatible**: Works across a wide range of PHP and Laravel versions
- **Comprehensive Configuration**: Highly configurable with sensible defaults

## Installation

### 1. Install via Composer

```bash
composer require liqrgv/shopsync
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=products-package-config
```

### 3. Run Migrations

```bash
php artisan migrate
```

## Configuration

The package can be configured via the `config/products-package.php` file. Key configuration options include:

### Environment Variables

```env
# Operating mode
PRODUCT_PACKAGE_MODE=wl

# WTM Mode Settings (only if mode is 'wtm')
PRODUCT_PACKAGE_WTM_API_URL=https://api.example.com
PRODUCT_PACKAGE_WTM_API_KEY=your-api-key-here
PRODUCT_PACKAGE_WTM_API_TIMEOUT=30

# Route configuration
PRODUCT_PACKAGE_ROUTE_PREFIX=api/v1
PRODUCT_PACKAGE_AUTH_MIDDLEWARE=auth:sanctum

# Package authentication
PRODUCT_PACKAGE_ENABLE_PACKAGE_AUTH=false
PRODUCT_PACKAGE_AUTH_KEY=your-secret-key
```

## Usage

### API Endpoints

All endpoints are API-only and return JSON responses. The package does not include any Blade views or frontend components.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | List products (supports pagination) |
| GET | `/products/search?q=query` | Search products |
| GET | `/products/export` | Export products to CSV |
| POST | `/products/import` | Import products from CSV |
| GET | `/products/{id}` | Get single product |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Soft delete product |
| POST | `/products/{id}/restore` | Restore soft-deleted product |
| DELETE | `/products/{id}/force` | Permanently delete product |

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number for pagination |
| `per_page` | integer | Items per page |
| `q` | string | Search query |
| `category` | string | Filter by category |
| `is_active` | boolean | Filter by active status |
| `min_price` | decimal | Minimum price |
| `max_price` | decimal | Maximum price |
| `min_stock` | integer | Minimum stock level |
| `with_trashed` | boolean | Include soft-deleted products |
| `only_trashed` | boolean | Show only soft-deleted products |
| `sort_by` | string | Sort column |
| `sort_order` | string | `asc` or `desc` |

### Response Format

All API responses follow a consistent JSON format:

#### Single Product Response
```json
{
  "id": 1,
  "name": "Product Name",
  "description": "Product description",
  "price": "29.99",
  "stock": 100,
  "sku": "PROD-001",
  "category": "Electronics",
  "metadata": {},
  "is_active": true,
  "created_at": "2023-01-01T00:00:00.000000Z",
  "updated_at": "2023-01-01T00:00:00.000000Z",
  "deleted_at": null
}
```

#### Collection Response
```json
{
  "data": [
    // Array of product objects
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

#### Error Response
```json
{
  "message": "Error description",
  "error": "Detailed error information",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Package Structure

```
src/
├── Http/
│   ├── Controllers/
│   │   └── ProductController.php
│   └── Middleware/
│       └── PackageAuth.php
├── Services/
│   ├── Contracts/
│   │   └── ProductFetcherInterface.php
│   ├── ProductFetchers/
│   │   ├── DatabaseProductFetcher.php
│   │   ├── ApiProductFetcher.php
│   │   └── ProductFetcherFactory.php
│   └── ProductService.php
├── Models/
│   └── Product.php
└── ProductPackageServiceProvider.php

database/
└── migrations/
    └── 1970_01_01_000000_create_products_table.php

routes/
└── api.php

config/
└── products-package.php
```

## Authentication

The package supports multiple authentication methods for API access:

### Laravel Sanctum
```php
'auth_middleware' => 'auth:sanctum',
```

### Custom Middleware
```php
'auth_middleware' => \App\Http\Middleware\ProductPackageAuth::class,
```

### Package Authentication
```php
'enable_package_auth' => true,
'package_auth_key' => 'your-secret-key',
```

#### Bearer Token Authentication
```bash
curl -H "Authorization: Bearer your-secret-key" \
     https://your-app.com/api/v1/products
```

#### API Key Authentication
```bash
# Header method
curl -H "X-API-Key: your-secret-key" \
     https://your-app.com/api/v1/products

# Query parameter method
curl "https://your-app.com/api/v1/products?api_key=your-secret-key"
```

#### Basic Authentication
```bash
curl -u "username:your-secret-key" \
     https://your-app.com/api/v1/products
```

## Advanced Features

### Custom Fetcher Implementation

You can create your own custom fetcher by implementing the `ProductFetcherInterface`:

```php
<?php

namespace App\Services\ProductFetchers;

use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;

class CustomProductFetcher implements ProductFetcherInterface
{
    // Implement all interface methods
}
```

### Security Features

- **Input Validation**: All inputs are validated and sanitized
- **SQL Injection Protection**: Parameterized queries and input sanitization
- **File Upload Security**: CSV imports include malicious file detection
- **Authentication Options**: Multiple secure authentication methods
- **Rate Limiting**: Compatible with Laravel's built-in rate limiting

### Performance Optimization

- **Database Indexing**: Optimized database queries with proper indexing
- **Chunked Processing**: Large exports/imports use chunked processing
- **MySQL FULLTEXT**: Native MySQL search optimization when available
- **Memory Management**: Efficient memory usage for large datasets

## Frontend Integration

Since this is an API-only package, you can integrate it with any frontend framework:

### JavaScript/Vue.js Example
```javascript
// Fetch products
const response = await fetch('/api/v1/products', {
  headers: {
    'Authorization': 'Bearer your-api-key',
    'Accept': 'application/json'
  }
});
const products = await response.json();
```

### React Example
```javascript
const fetchProducts = async () => {
  try {
    const response = await axios.get('/api/v1/products', {
      headers: {
        'Authorization': 'Bearer your-api-key'
      }
    });
    setProducts(response.data.data);
  } catch (error) {
    console.error('Error fetching products:', error);
  }
};
```

## Testing

The package includes comprehensive tests:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).