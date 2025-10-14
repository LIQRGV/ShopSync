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
- **PHP 7.2+ & Laravel 7-12 Compatible**: Full support for Laravel 12.x and PHP 8.x
- **JSON API Support**: Built-in JSON API transformers with relationship includes
- **Enhanced Product Model**: 23+ comprehensive product fields including SKU management, pricing tiers, and metadata
- **Relationship Management**: Full support for Categories, Brands, Locations, Suppliers, and custom Attributes
- **Comprehensive Configuration**: Highly configurable with sensible defaults

## Installation

### 1. Install via Composer

```bash
composer require thediamondbox/shopsync
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=products-package-config
```

### 3. Run Migrations

**Important**: Migrations behavior depends on your package mode configuration.

```bash
# For WL (WhiteLabel) mode - runs migrations normally
php artisan migrate

# For WTM (Watch the Market) mode - migrations are automatically skipped
# No additional action needed, package will use API mode only
```

The package intelligently handles migrations based on your configuration:
- **WL Mode**: Creates all necessary database tables and indexes
- **WTM Mode**: Skips migrations entirely as it operates in API-only mode

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

### JSON API Support

The package supports JSON API specification format responses with relationship includes:

```bash
# Get products with category and brand relationships
GET /api/v1/products?include=category,brand

# Get products with nested relationships
GET /api/v1/products?include=category,brand,supplier,attributes
```

#### JSON API Response Format
```json
{
  "data": [
    {
      "type": "products",
      "id": "1",
      "attributes": {
        "name": "Product Name",
        "price": "29.99",
        "status": "active"
      },
      "relationships": {
        "category": {
          "data": {"type": "categories", "id": "1"}
        }
      }
    }
  ],
  "included": [
    {
      "type": "categories",
      "id": "1",
      "attributes": {
        "name": "Electronics"
      }
    }
  ]
}
```

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

#### Shop Info Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/shop-info` | Get shop/business information |
| PUT | `/shop-info` | Update shop info (full replace) |
| PATCH | `/shop-info` | Update shop info (partial - prevents empty override) |
| POST | `/shop-info/images` | Upload shop info images (logo, favicon, banners, etc.) |

**Note**: Shop info is a singleton resource (single record). The PATCH endpoint prevents empty data from overriding existing values, solving the two-way sync issue.

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number for pagination |
| `per_page` | integer | Items per page |
| `q` | string | Search query |
| `category_id` | integer | Filter by category ID |
| `brand_id` | integer | Filter by brand ID |
| `location_id` | integer | Filter by location ID |
| `supplier_id` | integer | Filter by supplier ID |
| `status` | string | Filter by status (active, inactive) |
| `sell_status` | string | Filter by sell status |
| `min_price` | decimal | Minimum price |
| `max_price` | decimal | Maximum price |
| `with_trashed` | boolean | Include soft-deleted products |
| `only_trashed` | boolean | Show only soft-deleted products |
| `sort_by` | string | Sort column |
| `sort_order` | string | `asc` or `desc` |
| `include` | string | JSON API relationships to include |

### Response Format

All API responses follow a consistent JSON format:

#### Single Product Response
```json
{
  "id": 1,
  "name": "Product Name",
  "sku_prefix": "PROD",
  "rol_number": "001",
  "sku_custom_ref": "CUSTOM-REF",
  "status": "active",
  "sell_status": "for_sale",
  "purchase_date": "2023-01-01",
  "cost_price": "15.00",
  "price": "29.99",
  "sale_price": "24.99",
  "trade_price": "20.00",
  "vat_scheme": "standard",
  "image": "product-image.jpg",
  "original_image": "original-image.jpg",
  "description": "Product description",
  "seo_keywords": "product, electronics",
  "slug": "product-name",
  "seo_description": "SEO description",
  "related_products": [2, 3, 4],
  "category_id": 1,
  "brand_id": 1,
  "location_id": 1,
  "supplier_id": 1,
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

#### Shop Info Response
```json
{
  "data": {
    "type": "shop-info",
    "id": "1",
    "attributes": {
      "name": "My Store",
      "email": "info@mystore.com",
      "phone_number": "+1234567890",
      "address_line_1": "123 Main St",
      "city": "New York",
      "country": "USA",
      "logo": "uploads/logo.png",
      "seo_title": "My Store - Best Products",
      "stripe_payment": true,
      ...
    }
  }
}
```

## Package Structure

```
src/
├── Http/
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   └── ShopInfoController.php
│   ├── Middleware/
│   │   └── PackageAuth.php
│   └── Requests/
│       ├── BaseProductRequest.php
│       ├── StoreProductRequest.php
│       ├── UpdateProductRequest.php
│       └── SearchProductRequest.php
├── Services/
│   ├── Contracts/
│   │   ├── ProductFetcherInterface.php
│   │   └── ShopInfoFetcherInterface.php
│   ├── ProductFetchers/
│   │   ├── DatabaseProductFetcher.php
│   │   ├── ApiProductFetcher.php
│   │   └── ProductFetcherFactory.php
│   ├── ShopInfoFetchers/
│   │   ├── DatabaseShopInfoFetcher.php
│   │   ├── ApiShopInfoFetcher.php
│   │   └── ShopInfoFetcherFactory.php
│   ├── ProductService.php
│   └── ShopInfoService.php
├── Models/
│   ├── Product.php
│   ├── ShopInfo.php
│   ├── Category.php
│   ├── Brand.php
│   ├── Location.php
│   ├── Supplier.php
│   ├── Attribute.php
│   └── ProductAttribute.php
├── Transformers/
│   ├── JsonApiTransformer.php
│   └── ProductJsonApiTransformer.php
├── Helpers/
│   ├── JsonApiIncludeParser.php
│   └── JsonApiErrorResponse.php
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

### Shop Info Management

The package includes comprehensive shop/business information management with dual-mode support:

#### WL Mode (WhiteLabel)
- Direct database access to `shop_info` table
- Singleton pattern (single record)
- Full CRUD operations

#### WTM Mode (Watch the Market)
- All shop info requests are proxied to WL via API
- Client authentication using `client-id` header
- Marketplace only stores auth-related client information

#### Preventing Empty Override Issue
The `PATCH /shop-info` endpoint solves the two-way sync problem by only updating non-empty values:

```php
// WL Mode - DatabaseShopInfoFetcher
$shopInfo->updatePartial([
    'name' => 'New Store Name',
    'email' => '',  // Empty - will be ignored
    'phone' => null // Null - will be ignored
]);
// Result: Only 'name' is updated, 'email' and 'phone' remain unchanged
```

```javascript
// WTM Mode - Proxy to WL
const response = await fetch('/api/v1/shop-info', {
  method: 'PATCH',
  headers: {
    'Authorization': 'Bearer token',
    'client-id': '123',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'New Store Name',
    email: '', // Empty - will be filtered out
  })
});
```

#### Shop Info Fields
The ShopInfo model includes 90+ fields organized by category:
- **Business Info**: name, legal_name, email, phone, website, about_us, vat_no, company_no
- **Address**: address_line_1, address_line_2, city, country, postal_code, gmap_address
- **Registered Office**: registered_office_address_line_1, city, country, postal_code
- **Financial**: bank_name, account_name, account_number, sort_code, bic, iban
- **Social Media**: facebook, instagram, tiktok, youtube, whatsapp_link
- **Media**: logo, original_logo, favicon, banner_1, banner_2, various images
- **SEO**: seo_title, seo_description, seo_keywords, seo_author
- **Twitter Card**: twitter_title, twitter_card, twitter_description, twitter_image
- **Open Graph**: og_title, og_type, og_url, og_image, og_site_name, og_description
- **Integrations**: Google Analytics, Trustpilot, Captcha, Smartsupp
- **Payment Gateways**: Stripe, TakePayment, DNA Payment
- **Product Settings**: sku_prefix_watches, sku_prefix_jewellery, catalogue_mode

#### Shop Info Image Upload

Upload images for shop branding and pages (logo, favicon, banners, etc.):

**Supported Image Fields:**
- `logo` - Primary shop logo (with `original_logo` for processing)
- `favicon` - Website favicon (with `original_favicon`)
- `banner_1` - Homepage banner 1
- `banner_2` - Homepage banner 2
- `sell_watch_image` - Sell watch page image
- `valuations_additional_logo` - Additional logo for valuation PDFs

**Image Requirements:**
- Formats: JPEG, JPG, PNG, GIF, WebP, SVG
- Max size: 7MB
- Automatic processing for logo/favicon:
  - WebP → JPEG conversion for `original_*` fields
  - SVG → PNG conversion (if tools available)
  - Dual storage: `uploads/shop_images` and `uploads/original_shop_images`

**Usage Example:**
```bash
# WL Mode - Direct upload
curl -X POST https://your-app.com/api/v1/shop-info/images \
  -H "Authorization: Bearer your-api-key" \
  -F "field=logo" \
  -F "image=@/path/to/logo.png"

# WTM Mode - Proxied to WL
curl -X POST https://marketplace.com/api/v1/shop-info/images \
  -H "Authorization: Bearer marketplace-token" \
  -H "client-id: 123" \
  -F "field=banner_1" \
  -F "image=@/path/to/banner.jpg"
```

**Response:**
```json
{
  "data": {
    "type": "shop-info",
    "id": "1",
    "attributes": {
      "name": "My Store",
      "logo": "uploads/shop_images/1234567890_logo.png",
      "original_logo": "uploads/original_shop_images/1234567890_logo.png",
      ...
    }
  }
}
```

**Features:**
- ✅ WL Mode: Direct filesystem storage with automatic format conversion
- ✅ WTM Mode: Automatic proxy to WL server
- ✅ Supports both primary and original_* fields for logo/favicon
- ✅ SVG to PNG conversion (requires ImageMagick or rsvg-convert)
- ✅ WebP to JPEG conversion for compatibility
- ✅ JSON API response format

### Enhanced Product Model

The Product model includes comprehensive fields for complete e-commerce functionality:

#### Core Product Fields
- **Basic Info**: name, sku_prefix, rol_number, sku_custom_ref, description
- **Pricing**: cost_price, price, sale_price, trade_price, vat_scheme
- **Status Management**: status, sell_status, purchase_date
- **Media**: image, original_image
- **SEO**: seo_keywords, slug, seo_description
- **Relationships**: category_id, brand_id, location_id, supplier_id
- **Metadata**: related_products array

#### Product Relationships
- **Category**: Products belong to categories for organization
- **Brand**: Products are associated with brands
- **Location**: Track product storage locations
- **Supplier**: Manage supplier information
- **Attributes**: Custom attributes with pivot values (size, color, etc.)

#### Helper Methods
```php
$product->getFullSkuAttribute(); // Returns: sku_prefix + rol_number
$product->getFormattedPriceAttribute(); // Returns: £29.99
$product->isOnSale(); // Checks if sale_price < price
$product->getEffectivePriceAttribute(); // Returns sale_price or price
$product->getRelatedProductsCollectionAttribute(); // Returns related products
```

### Custom Fetcher Implementation

You can create your own custom fetcher by implementing the `ProductFetcherInterface`:

```php
<?php

namespace App\Services\ProductFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;

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

## Development & Testing

The package includes comprehensive development tools and testing:

### Available Scripts

```bash
# Run tests
composer test

# Run tests with coverage report
composer test-coverage

# Format code using Laravel Pint
composer format

# Run static analysis with PHPStan
composer analyse
```

### Testing
The package includes comprehensive tests covering:
- Product CRUD operations
- Authentication mechanisms
- JSON API transformations
- Relationship management
- Migration conditional logic

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).