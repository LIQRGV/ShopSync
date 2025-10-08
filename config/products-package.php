<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operating Mode
    |--------------------------------------------------------------------------
    |
    | This package can operate in two modes:
    | - 'wl' (WhiteLabel): Direct database manipulation for local shop pages
    | - 'wtm' (Watch the Market): API-based manipulation for admin panel/market monitoring
    |
    */
    'mode' => env('SHOP_SYNC_PRODUCT_PACKAGE_MODE', 'wl'),

    /*
    |--------------------------------------------------------------------------
    | WTM API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the WTM (Watch the Market) mode.
    | These settings are only used when mode is set to 'wtm'.
    |
    */
    'wtm_api_timeout' => env('SHOP_SYNC_PRODUCT_PACKAGE_WTM_API_TIMEOUT', 30), // seconds
    'sse_stream_chunk_size' => env('SHOP_SYNC_PRODUCT_PACKAGE_SSE_STREAM_CHUNK_SIZE', 8), // bytes - buffer size for reading SSE streams

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing for the package endpoints.
    |
    */
    'route_prefix' => env('SHOP_SYNC_PRODUCT_PACKAGE_ROUTE_PREFIX', 'api/v1'),
    'route_middleware' => ['api'],
    'register_routes' => env('SHOP_SYNC_PRODUCT_PACKAGE_REGISTER_ROUTES', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication and authorization for package endpoints.
    |
    */
    'auth_middleware' => env('SHOP_SYNC_PRODUCT_PACKAGE_AUTH_MIDDLEWARE'),
    'enable_package_auth' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_PACKAGE_AUTH', false),
    'package_auth_key' => env('SHOP_SYNC_PRODUCT_PACKAGE_AUTH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Pagination Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default pagination settings.
    |
    */
    'per_page' => env('SHOP_SYNC_PRODUCT_PACKAGE_PER_PAGE', 25),
    'max_per_page' => env('SHOP_SYNC_PRODUCT_PACKAGE_MAX_PER_PAGE', 100),

    /*
    |--------------------------------------------------------------------------
    | Export/Import Configuration
    |--------------------------------------------------------------------------
    |
    | Configure export and import functionality.
    |
    */
    'export_chunk_size' => env('SHOP_SYNC_PRODUCT_PACKAGE_EXPORT_CHUNK_SIZE', 1000),
    'import_chunk_size' => env('SHOP_SYNC_PRODUCT_PACKAGE_IMPORT_CHUNK_SIZE', 500),
    'max_import_file_size' => env('SHOP_SYNC_PRODUCT_PACKAGE_MAX_IMPORT_FILE_SIZE', 10240), // KB
    'allowed_import_extensions' => ['csv', 'txt'],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search functionality.
    |
    */
    'search_min_length' => env('SHOP_SYNC_PRODUCT_PACKAGE_SEARCH_MIN_LENGTH', 1),
    'search_max_length' => env('SHOP_SYNC_PRODUCT_PACKAGE_SEARCH_MAX_LENGTH', 255),
    'use_fulltext_search' => env('SHOP_SYNC_PRODUCT_PACKAGE_USE_FULLTEXT_SEARCH', null), // auto-detect if null

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for better performance.
    |
    */
    'cache_enabled' => env('SHOP_SYNC_PRODUCT_PACKAGE_CACHE_ENABLED', false),
    'cache_ttl' => env('SHOP_SYNC_PRODUCT_PACKAGE_CACHE_TTL', 3600), // seconds
    'cache_prefix' => env('SHOP_SYNC_PRODUCT_PACKAGE_CACHE_PREFIX', 'products_package'),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for the package.
    |
    */
    'log_queries' => env('SHOP_SYNC_PRODUCT_PACKAGE_LOG_QUERIES', false),
    'log_api_calls' => env('SHOP_SYNC_PRODUCT_PACKAGE_LOG_API_CALLS', false),
    'log_level' => env('SHOP_SYNC_PRODUCT_PACKAGE_LOG_LEVEL', 'info'),


    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database-specific configuration.
    |
    */
    'connection' => env('SHOP_SYNC_PRODUCT_PACKAGE_DB_CONNECTION'),
    'table_prefix' => env('SHOP_SYNC_PRODUCT_PACKAGE_TABLE_PREFIX', ''),
    'should_migrate' => env('SHOP_SYNC_PRODUCT_PACKAGE_SHOULD_MIGRATE', false),

    /*
    |--------------------------------------------------------------------------
    | JSON API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for JSON API format responses.
    |
    */
    'json_api' => [
        'include_meta' => env('JSON_API_INCLUDE_META', true),
        'include_links' => env('JSON_API_INCLUDE_LINKS', true),
        'max_include_depth' => env('JSON_API_MAX_INCLUDE_DEPTH', 3),
        'allowed_includes' => [
            'category',
            'brand',
            'location',
            'supplier',
            'attributes',
        ],
        'default_includes' => env('JSON_API_DEFAULT_INCLUDES', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure validation rules and limits.
    |
    */
    'validation' => [
        'name_max_length' => 255,
        'sku_max_length' => 255,
        'category_max_length' => 255,
        'description_max_length' => 65535,
        'price_max' => 999999.99,
        'stock_max' => 2147483647,
        'metadata_max_keys' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific package features.
    |
    */
    'features' => [
        'soft_deletes' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_SOFT_DELETES', true),
        'export' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_EXPORT', true),
        'import' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_IMPORT', true),
        'search' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_SEARCH', true),
        'metadata' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_METADATA', true),
        'categories' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_CATEGORIES', true),
        'status_endpoint' => env('SHOP_SYNC_PRODUCT_PACKAGE_ENABLE_STATUS_ENDPOINT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure error handling behavior.
    |
    */
    'error_handling' => [
        'expose_errors_in_dev' => env('SHOP_SYNC_PRODUCT_PACKAGE_EXPOSE_ERRORS_IN_DEV', true),
        'log_stack_traces' => env('SHOP_SYNC_PRODUCT_PACKAGE_LOG_STACK_TRACES', true),
        'return_error_codes' => env('SHOP_SYNC_PRODUCT_PACKAGE_RETURN_ERROR_CODES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance-related settings.
    |
    */
    'performance' => [
        'eager_load_relationships' => env('SHOP_SYNC_PRODUCT_PACKAGE_EAGER_LOAD', false),
        'use_database_transactions' => env('SHOP_SYNC_PRODUCT_PACKAGE_USE_TRANSACTIONS', true),
        'optimize_queries' => env('SHOP_SYNC_PRODUCT_PACKAGE_OPTIMIZE_QUERIES', true),
    ],
];