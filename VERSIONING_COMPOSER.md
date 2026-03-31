# Composer Package - Technical Changes

Technical documentation for `thediamondbox/shopsync` Composer package.

---

## Package Information

- **Package**: `thediamondbox/shopsync`
- **Current Version**: v2.0.1
- **Type**: Laravel PHP Package
- **Repository**: https://github.com/The-Diamond-Box/stock-sync

---

## Version History

### v2.0.1 - 2025-10-24

**Type**: Patch Release (Bug Fix)

**Changes**:
- Fixed ShopInfo transformer compatibility issues with Laravel 7.x-12.x
- Improved type hinting for better cross-version compatibility
- Enhanced class checking using `class_basename()` instead of `instanceof`
- Added missing `$parentId` parameter in `addToIncluded()` method

**Files Changed**:
- `src/Transformers/ShopInfoJsonApiTransformer.php`

**Upgrade**: No breaking changes, safe to upgrade from v2.0.0
```bash
composer update thediamondbox/shopsync
```

---

### v2.0.0 - 2025-10-22

**Type**: Major Release (Initial Stable Release)

### What's New in v2.0.0

This is the first stable release of ShopSync package with dual-mode architecture supporting both White Label (WL) and Watch the Market (WTM) operations.

**Key Features**:
- Shop Info Management System
- Product Image Upload
- Dynamic Attribute System
- Server-Sent Events (SSE)
- JSON API Standard Support
- Dual-mode Architecture (WL/WTM)

---

## New Features

### 1. Shop Info Management

Complete shop/business information management with 90+ fields.

#### New Endpoints

**GET /api/v1/shop-info**
- Retrieve complete shop information
- Returns singleton record
- Supports JSON API format

**PUT /api/v1/shop-info**
- Full replacement update
- All fields required
- Validates complete data structure

**PATCH /api/v1/shop-info**
- Partial update
- Only updates provided fields
- Prevents empty value override
- Solves two-way sync issue

**POST /api/v1/shop-info/images**
- Upload shop images (logo, favicon, banners)
- Automatic format conversion (WebP → JPEG, SVG → PNG)
- Dual storage (processed + original)
- Max file size: 7MB
- Supported formats: JPEG, PNG, GIF, WebP, SVG

#### New Models

**ShopInfo Model**

```php
namespace TheDiamondBox\ShopSync\Models;

class ShopInfo extends Model
{
    protected $table = 'shop_info';

    protected $fillable = [
        // Business Information
        'name', 'legal_name', 'email', 'phone_number', 'website',
        'about_us', 'vat_no', 'company_no',

        // Address
        'address_line_1', 'address_line_2', 'city', 'country',
        'postal_code', 'gmap_address',

        // Registered Office
        'registered_office_address_line_1', 'registered_office_address_line_2',
        'registered_office_city', 'registered_office_country',
        'registered_office_postal_code',

        // Financial
        'bank_name', 'account_name', 'account_number', 'sort_code',
        'bic', 'iban',

        // Social Media
        'facebook', 'instagram', 'tiktok', 'youtube', 'whatsapp_link',

        // Media
        'logo', 'original_logo', 'favicon', 'original_favicon',
        'banner_1', 'banner_2', 'sell_watch_image',
        'valuations_additional_logo',

        // SEO
        'seo_title', 'seo_description', 'seo_keywords', 'seo_author',

        // Twitter Card
        'twitter_title', 'twitter_card', 'twitter_description', 'twitter_image',

        // Open Graph
        'og_title', 'og_type', 'og_url', 'og_image', 'og_site_name', 'og_description',

        // Integrations
        'google_analytics_id', 'trustpilot_url', 'captcha_site_key',
        'captcha_secret_key', 'smartsupp_key',

        // Payment Gateways
        'stripe_payment', 'stripe_publishable_key', 'stripe_secret_key',
        'take_payment', 'take_payment_gateway_id', 'take_payment_api_key',
        'dna_payment', 'dna_payment_terminal_id', 'dna_payment_client_id',

        // Product Settings
        'sku_prefix_watches', 'sku_prefix_jewellery', 'catalogue_mode',
    ];
}
```

**OpenHours Model**

```php
namespace TheDiamondBox\ShopSync\Models;

class OpenHours extends Model
{
    protected $table = 'open_hours';

    protected $fillable = [
        'shop_info_id',
        'day_of_week',   // 0 = Sunday, 6 = Saturday
        'open_time',     // TIME format
        'close_time',    // TIME format
        'is_closed',     // BOOLEAN
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function shopInfo()
    {
        return $this->belongsTo(ShopInfo::class);
    }
}
```

#### Database Schema

**shop_info table**:
```sql
CREATE TABLE shop_info (
    id BIGINT UNSIGNED PRIMARY KEY,
    -- Business Information (8 fields)
    name VARCHAR(255),
    legal_name VARCHAR(255),
    email VARCHAR(255),
    phone_number VARCHAR(255),
    website VARCHAR(255),
    about_us TEXT,
    vat_no VARCHAR(255),
    company_no VARCHAR(255),

    -- Address (6 fields)
    address_line_1 VARCHAR(255),
    address_line_2 VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255),
    postal_code VARCHAR(255),
    gmap_address TEXT,

    -- Registered Office (5 fields)
    registered_office_address_line_1 VARCHAR(255),
    registered_office_address_line_2 VARCHAR(255),
    registered_office_city VARCHAR(255),
    registered_office_country VARCHAR(255),
    registered_office_postal_code VARCHAR(255),

    -- Financial (6 fields)
    bank_name VARCHAR(255),
    account_name VARCHAR(255),
    account_number VARCHAR(255),
    sort_code VARCHAR(255),
    bic VARCHAR(255),
    iban VARCHAR(255),

    -- Social Media (5 fields)
    facebook VARCHAR(255),
    instagram VARCHAR(255),
    tiktok VARCHAR(255),
    youtube VARCHAR(255),
    whatsapp_link VARCHAR(255),

    -- Media (8 fields)
    logo VARCHAR(255),
    original_logo VARCHAR(255),
    favicon VARCHAR(255),
    original_favicon VARCHAR(255),
    banner_1 VARCHAR(255),
    banner_2 VARCHAR(255),
    sell_watch_image VARCHAR(255),
    valuations_additional_logo VARCHAR(255),

    -- SEO (4 fields)
    seo_title VARCHAR(255),
    seo_description TEXT,
    seo_keywords TEXT,
    seo_author VARCHAR(255),

    -- Twitter Card (4 fields)
    twitter_title VARCHAR(255),
    twitter_card VARCHAR(255),
    twitter_description TEXT,
    twitter_image VARCHAR(255),

    -- Open Graph (6 fields)
    og_title VARCHAR(255),
    og_type VARCHAR(255),
    og_url VARCHAR(255),
    og_image VARCHAR(255),
    og_site_name VARCHAR(255),
    og_description TEXT,

    -- Integrations (5 fields)
    google_analytics_id VARCHAR(255),
    trustpilot_url VARCHAR(255),
    captcha_site_key VARCHAR(255),
    captcha_secret_key VARCHAR(255),
    smartsupp_key VARCHAR(255),

    -- Payment Gateways (9 fields)
    stripe_payment BOOLEAN DEFAULT 0,
    stripe_publishable_key VARCHAR(255),
    stripe_secret_key VARCHAR(255),
    take_payment BOOLEAN DEFAULT 0,
    take_payment_gateway_id VARCHAR(255),
    take_payment_api_key VARCHAR(255),
    dna_payment BOOLEAN DEFAULT 0,
    dna_payment_terminal_id VARCHAR(255),
    dna_payment_client_id VARCHAR(255),

    -- Product Settings (3 fields)
    sku_prefix_watches VARCHAR(255),
    sku_prefix_jewellery VARCHAR(255),
    catalogue_mode BOOLEAN DEFAULT 0,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**open_hours table**:
```sql
CREATE TABLE open_hours (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    shop_info_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT NOT NULL,
    open_time TIME NULL,
    close_time TIME NULL,
    is_closed BOOLEAN DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (shop_info_id) REFERENCES shop_info(id) ON DELETE CASCADE
);
```

#### Service Implementation

**ShopInfoService**:
```php
namespace TheDiamondBox\ShopSync\Services;

class ShopInfoService
{
    protected $fetcher;

    public function __construct(ShopInfoFetcherInterface $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    public function get()
    {
        return $this->fetcher->get();
    }

    public function update(array $data)
    {
        return $this->fetcher->update($data);
    }

    public function updatePartial(array $data)
    {
        return $this->fetcher->updatePartial($data);
    }

    public function uploadImage(string $field, $file)
    {
        return $this->fetcher->uploadImage($field, $file);
    }
}
```

**DatabaseShopInfoFetcher** (WL Mode):
```php
namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

class DatabaseShopInfoFetcher implements ShopInfoFetcherInterface
{
    public function get()
    {
        return ShopInfo::firstOrFail();
    }

    public function update(array $data)
    {
        $shopInfo = $this->get();
        $shopInfo->update($data);
        return $shopInfo->fresh();
    }

    public function updatePartial(array $data)
    {
        $shopInfo = $this->get();

        // Only update non-empty values
        $filteredData = array_filter($data, function($value) {
            return !is_null($value) && $value !== '';
        });

        $shopInfo->update($filteredData);
        return $shopInfo->fresh();
    }

    public function uploadImage(string $field, $file)
    {
        // Image upload logic with format conversion
        // WebP → JPEG, SVG → PNG
        // Dual storage: uploads/shop_images & uploads/original_shop_images
    }
}
```

**ApiShopInfoFetcher** (WTM Mode):
```php
namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

class ApiShopInfoFetcher implements ShopInfoFetcherInterface
{
    public function get()
    {
        // Proxy to WL server
        return Http::withHeaders([
            'client-id' => request()->header('client-id'),
        ])->get($this->apiUrl . '/shop-info')->json();
    }

    public function update(array $data)
    {
        // Proxy PUT request to WL
    }

    public function updatePartial(array $data)
    {
        // Proxy PATCH request to WL
    }

    public function uploadImage(string $field, $file)
    {
        // Proxy multipart/form-data to WL
    }
}
```

#### Usage Example

```php
// Get shop info
$shopInfo = app(ShopInfoService::class)->get();

// Full update
$updated = app(ShopInfoService::class)->update([
    'name' => 'New Shop Name',
    'email' => 'new@email.com',
    // ... all fields
]);

// Partial update (only provided fields)
$updated = app(ShopInfoService::class)->updatePartial([
    'name' => 'New Shop Name',
    // Other fields remain unchanged
]);

// Upload logo
$result = app(ShopInfoService::class)->uploadImage('logo', $request->file('image'));
```

---

### 2. Product Image Upload

Direct product image upload capability.

#### New Endpoint

**POST /api/v1/products/{id}/image**

Request:
```http
POST /api/v1/products/123/image
Content-Type: multipart/form-data

image: [binary file]
```

Response:
```json
{
    "id": 123,
    "name": "Product Name",
    "image": "uploads/products/1234567890_image.jpg",
    "original_image": "uploads/original_products/1234567890_image.jpg"
}
```

#### Implementation

**Controller Method**:
```php
public function uploadImage(UploadProductImageRequest $request, $id)
{
    $product = $this->productService->getById($id);

    if ($request->hasFile('image')) {
        $file = $request->file('image');

        // Store image
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/products', $filename, 'public');

        // Store original
        $originalPath = $file->storeAs('uploads/original_products', $filename, 'public');

        // Update product
        $product->update([
            'image' => $path,
            'original_image' => $originalPath,
        ]);
    }

    return response()->json($product);
}
```

#### Validation

```php
class UploadProductImageRequest extends FormRequest
{
    public function rules()
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,webp,svg',
                'max:7168', // 7MB
            ],
        ];
    }
}
```

---

### 3. Dynamic Attribute System

Enhanced attribute system with grouping and dropship filtering.

#### Database Changes

**attributes table** - New columns:
```sql
ALTER TABLE attributes
ADD COLUMN enabled_on_dropship BOOLEAN DEFAULT 0 AFTER is_active,
ADD COLUMN group_name VARCHAR(255) NULL AFTER enabled_on_dropship;
```

#### Model Updates

```php
namespace TheDiamondBox\ShopSync\Models;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'options',
        'is_required',
        'is_filterable',
        'is_searchable',
        'is_active',
        'sort_order',
        'validation_rules',
        'default_value',
        'enabled_on_dropship', // NEW
        'group_name',          // NEW
    ];

    protected $casts = [
        'enabled_on_dropship' => 'boolean', // NEW
        // ... other casts
    ];

    // NEW: Scope for dropship-enabled attributes
    public function scopeEnabledOnDropship($query)
    {
        return $query->where('enabled_on_dropship', true);
    }
}
```

#### Usage

```php
// Get all dropship-enabled attributes
$attributes = Attribute::enabledOnDropship()->get();

// Get attributes grouped by group_name
$grouped = Attribute::enabledOnDropship()
    ->orderBy('group_name')
    ->orderBy('sort_order')
    ->get()
    ->groupBy('group_name');

// Result:
[
    'Specifications' => [
        { name: 'Size', group_name: 'Specifications' },
        { name: 'Weight', group_name: 'Specifications' }
    ],
    'Features' => [
        { name: 'Color', group_name: 'Features' },
        { name: 'Material', group_name: 'Features' }
    ]
]
```

#### Grid Integration

Used by Grid Sync package to generate dynamic attribute columns grouped by category.

---

### 4. Server-Sent Events (SSE)

Real-time product update broadcasting.

#### New Endpoints

**GET /api/v1/sse/events**
- Establishes SSE connection
- Streams real-time product updates
- Reconnects automatically on disconnect

**GET /api/v1/sse/status**
- Returns SSE connection status
- Shows active connections count
- Provides health check

#### Implementation

**SseController**:
```php
namespace TheDiamondBox\ShopSync\Http\Controllers;

class SseController extends Controller
{
    public function events(Request $request)
    {
        return response()->stream(function() use ($request) {
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');

            // Stream events
            while (true) {
                $events = $this->sseService->getEvents();

                foreach ($events as $event) {
                    echo "event: {$event['type']}\n";
                    echo "data: " . json_encode($event['data']) . "\n\n";
                    ob_flush();
                    flush();
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function status()
    {
        return response()->json([
            'enabled' => config('products-package.sse.enabled'),
            'connections' => $this->sseService->getConnectionCount(),
            'uptime' => $this->sseService->getUptime(),
        ]);
    }
}
```

#### Event Types

**product.created**:
```json
{
    "event": "product.created",
    "data": {
        "id": 123,
        "name": "New Product",
        "price": "29.99"
    }
}
```

**product.updated**:
```json
{
    "event": "product.updated",
    "data": {
        "id": 123,
        "name": "Updated Product",
        "price": "24.99"
    }
}
```

**product.deleted**:
```json
{
    "event": "product.deleted",
    "data": {
        "id": 123
    }
}
```

**product.restored**:
```json
{
    "event": "product.restored",
    "data": {
        "id": 123
    }
}
```

#### Observer

**ProductObserver**:
```php
namespace TheDiamondBox\ShopSync\Observers;

class ProductObserver
{
    protected $sseService;

    public function created(Product $product)
    {
        $this->sseService->broadcast('product.created', $product->toArray());
    }

    public function updated(Product $product)
    {
        $this->sseService->broadcast('product.updated', $product->toArray());
    }

    public function deleted(Product $product)
    {
        $this->sseService->broadcast('product.deleted', ['id' => $product->id]);
    }

    public function restored(Product $product)
    {
        $this->sseService->broadcast('product.restored', $product->toArray());
    }
}
```

#### Dual-Mode Support

**WL Mode** (DirectSseStreamer):
- Direct broadcasting from database observer
- In-memory event queue
- Direct client connections

**WTM Mode** (ProxySseStreamer):
- Proxies SSE stream from WL server
- Passes through client-id header
- Maintains persistent connection to WL

---

### 5. JSON API Standard Support

Full JSON API specification compliance.

#### Response Transformers

**ProductJsonApiTransformer**:
```php
namespace TheDiamondBox\ShopSync\Transformers;

class ProductJsonApiTransformer extends JsonApiTransformer
{
    protected $type = 'products';

    protected $availableIncludes = [
        'category',
        'brand',
        'supplier',
        'location',
        'attributes',
    ];

    public function transform($product)
    {
        return [
            'type' => 'products',
            'id' => (string) $product->id,
            'attributes' => [
                'name' => $product->name,
                'sku_prefix' => $product->sku_prefix,
                'rol_number' => $product->rol_number,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'status' => $product->status,
                'image' => $product->image,
                'created_at' => $product->created_at->toIso8601String(),
                'updated_at' => $product->updated_at->toIso8601String(),
            ],
        ];
    }

    public function includeCategory($product)
    {
        if (!$product->category) return null;

        return [
            'data' => [
                'type' => 'categories',
                'id' => (string) $product->category->id,
                'attributes' => [
                    'name' => $product->category->name,
                ],
            ],
        ];
    }
}
```

#### Request/Response Examples

**Request with includes**:
```http
GET /api/v1/products?include=category,brand,attributes
Accept: application/vnd.api+json
```

**Response**:
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
                    "data": {
                        "type": "categories",
                        "id": "1"
                    }
                },
                "brand": {
                    "data": {
                        "type": "brands",
                        "id": "2"
                    }
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
        },
        {
            "type": "brands",
            "id": "2",
            "attributes": {
                "name": "Samsung"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 150
    },
    "links": {
        "first": "/api/v1/products?page=1",
        "last": "/api/v1/products?page=10",
        "next": "/api/v1/products?page=2"
    }
}
```

---

## Configuration Changes

### New Configuration File

`config/products-package.php`:

```php
return [
    // Operating mode: 'wl' (White Label) or 'wtm' (Watch the Market)
    'mode' => env('PRODUCT_PACKAGE_MODE', 'wl'),

    // WTM mode API configuration
    'wtm' => [
        'api_url' => env('PRODUCT_PACKAGE_WTM_API_URL'),
        'api_key' => env('PRODUCT_PACKAGE_WTM_API_KEY'),
        'api_timeout' => env('PRODUCT_PACKAGE_WTM_API_TIMEOUT', 30),
    ],

    // Server-Sent Events configuration
    'sse' => [
        'enabled' => env('PRODUCT_PACKAGE_SSE_ENABLED', true),
        'connection_timeout' => env('PRODUCT_PACKAGE_SSE_TIMEOUT', 300),
        'retry_timeout' => env('PRODUCT_PACKAGE_SSE_RETRY', 3000),
    ],

    // Migration control
    'migrations' => [
        'run' => env('PRODUCT_PACKAGE_RUN_MIGRATIONS', true),
    ],

    // Route configuration
    'route_prefix' => env('PRODUCT_PACKAGE_ROUTE_PREFIX', 'api/v1'),
    'auth_middleware' => env('PRODUCT_PACKAGE_AUTH_MIDDLEWARE', 'auth:sanctum'),

    // Feature toggles
    'features' => [
        'soft_deletes' => true,
        'search' => true,
        'export' => true,
        'import' => true,
        'status_endpoint' => true,
    ],
];
```

### Environment Variables

```env
# Operating mode
PRODUCT_PACKAGE_MODE=wl

# WTM API configuration
PRODUCT_PACKAGE_WTM_API_URL=https://api.example.com
PRODUCT_PACKAGE_WTM_API_KEY=your-api-key
PRODUCT_PACKAGE_WTM_API_TIMEOUT=30

# SSE configuration
PRODUCT_PACKAGE_SSE_ENABLED=true
PRODUCT_PACKAGE_SSE_TIMEOUT=300
PRODUCT_PACKAGE_SSE_RETRY=3000

# Migration control
PRODUCT_PACKAGE_RUN_MIGRATIONS=true

# Route configuration
PRODUCT_PACKAGE_ROUTE_PREFIX=api/v1
PRODUCT_PACKAGE_AUTH_MIDDLEWARE=auth:sanctum
```

---

## Dual-Mode Architecture

### WL Mode (White Label)

**Characteristics**:
- Direct database access
- Runs migrations
- Full CRUD operations on local database
- Broadcasts SSE events directly
- Independent operation

**Service Implementation**:
- `DatabaseProductFetcher` - Direct Eloquent queries
- `DatabaseShopInfoFetcher` - Direct database access
- `DirectSseStreamer` - Direct event broadcasting

**Usage**: Single-instance applications with own database

### WTM Mode (Watch the Market)

**Characteristics**:
- API proxy to WL server
- NO migrations (API-only)
- All operations forwarded to WL
- SSE events proxied from WL
- Requires WL API configuration

**Service Implementation**:
- `ApiProductFetcher` - HTTP requests to WL
- `ApiShopInfoFetcher` - HTTP proxy to WL
- `ProxySseStreamer` - Proxies SSE from WL

**Headers**:
- `client-id` - Identifies marketplace client
- `Authorization` - Authentication token

**Usage**: Marketplace applications managing multiple WL instances

---

## Migration Guide

### Database Migrations

**WL Mode**: Runs migrations automatically

```bash
php artisan migrate
```

**Migrations**:
1. `1970_01_01_000000_create_shop_sync_products_table.php`
2. `1970_01_01_000001_create_shop_sync_shop_info_table.php`
3. `1970_01_01_000002_create_shop_sync_open_hours_table.php`

**WTM Mode**: Migrations automatically skipped

Configuration check in ServiceProvider:
```php
if (config('products-package.migrations.run', true)) {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
}
```

### Breaking Changes

**None**

v2.0.0 is the first stable release. No breaking changes from previous dev branches.

---

## Performance Considerations

### Database Optimization

**Indexes**:
- Products: `id`, `name`, `sku_prefix`, `status`, `category_id`, `brand_id`
- ShopInfo: `id` (primary, singleton)
- Attributes: `enabled_on_dropship`, `group_name`, `sort_order`

**Eager Loading**:
```php
Product::with(['category', 'brand', 'attributes'])->get();
```

**Query Optimization**:
- Chunked processing for large datasets
- Cursor pagination for exports
- Database query caching

### API Performance (WTM Mode)

**HTTP Client**:
- Connection pooling
- Configurable timeout (default: 30s)
- Retry logic with exponential backoff
- Response caching (where applicable)

### SSE Performance

**Connection Management**:
- Configurable timeout (default: 300s)
- Automatic cleanup of stale connections
- Event filtering by client-id
- Memory-efficient streaming

---

## Security

### Input Validation

All requests validated via Form Request classes:
- `StoreProductRequest`
- `UpdateProductRequest`
- `UploadProductImageRequest`
- `UpdateShopInfoRequest`
- `UploadShopInfoImageRequest`

### File Upload Security

**Image Upload**:
- MIME type validation
- File extension whitelist: `jpeg,jpg,png,gif,webp,svg`
- Maximum file size: 7MB
- Sanitized filename generation
- Separate directories for processed/original

### API Security

**Authentication**:
- Configurable middleware (default: `auth:sanctum`)
- Optional package-level authentication
- Client-id validation for WTM mode

**Authorization**:
- Implement via Laravel policies (not included in package)
- Recommended: Define Gates in consuming application

---

## Dependencies

### PHP Requirements

```json
{
    "php": "^7.2|^8.0"
}
```

### Laravel Requirements

```json
{
    "illuminate/support": "^7.0|^8.0|^9.0|^10.0|^11.0|^12.0",
    "illuminate/database": "^7.0|^8.0|^9.0|^10.0|^11.0|^12.0",
    "illuminate/http": "^7.0|^8.0|^9.0|^10.0|^11.0|^12.0"
}
```

### No New Dependencies

v2.0.0 does not introduce any new Composer dependencies beyond existing Laravel packages.

---

## Testing

### Test Coverage

Package includes tests for:
- Product CRUD operations
- Shop info management
- Dual-mode functionality (WL/WTM)
- SSE broadcasting
- JSON API transformations
- Image upload handling
- Migration conditional loading

### Running Tests

```bash
composer test
```

---

## References

- Laravel Package Development: https://laravel.com/docs/packages
- JSON API Specification: https://jsonapi.org/
- Server-Sent Events: https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events
- Semantic Versioning: https://semver.org/
