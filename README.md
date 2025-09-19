# Product Management Package

A Laravel package for product management with flexible authentication and dual-mode operations (Database/API).

## Features

- **Dual Operation Modes**: WL (WhiteLabel) for direct database operations, WTM (Watch the Market) for API-based operations
- **Complete REST API**: Full CRUD operations with search, export, and import functionality
- **Flexible Authentication**: Support for multiple authentication methods
- **Soft Deletes**: Products can be soft deleted and restored
- **Search Functionality**: Advanced search with database optimization
- **Export/Import**: CSV export and import capabilities
- **Frontend Interface**: Complete Bootstrap-based frontend component
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

### 3. Publish Views (Optional)

```bash
php artisan vendor:publish --tag=products-package-views
```

### 4. Run Migrations

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

### Include in Your View

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Product Management</h1>

    @include('products-package::partials.product-table')
</div>
@endsection
```

### API Endpoints

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

resources/
└── views/
    └── partials/
        └── product-table.blade.php

config/
└── products-package.php
```

## Authentication

The package supports multiple authentication methods:

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

### Caching

Enable caching for better performance:

```env
PRODUCT_PACKAGE_CACHE_ENABLED=true
PRODUCT_PACKAGE_CACHE_TTL=3600
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