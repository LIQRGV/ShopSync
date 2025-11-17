<?php

use Illuminate\Support\Facades\Route;
use TheDiamondBox\ShopSync\Http\Controllers\ProductController;
use TheDiamondBox\ShopSync\Http\Controllers\SseController;
use TheDiamondBox\ShopSync\Http\Controllers\ShopInfoController;
use TheDiamondBox\ShopSync\Http\Controllers\CategoryController;
use TheDiamondBox\ShopSync\Http\Controllers\BrandController;
use TheDiamondBox\ShopSync\Http\Controllers\SupplierController;

/*
|--------------------------------------------------------------------------
| Package API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the Products Package.
| These routes are loaded by the ProductPackageServiceProvider within
| a group which is assigned the "api" middleware group.
|
| Route order is important - specific routes must come before parameterized ones
|
*/

// Server-Sent Events endpoints
Route::get('/sse/events', [SseController::class, 'events'])
    ->name('sse.events');

Route::get('/sse/status', [SseController::class, 'status'])
    ->name('sse.status');

// Shop Info endpoints (WL mode, proxied by WTM)
Route::get('/shop-info', [ShopInfoController::class, 'show'])
    ->name('shop-info.show');

Route::put('/shop-info', [ShopInfoController::class, 'update'])
    ->name('shop-info.update');

Route::patch('/shop-info', [ShopInfoController::class, 'updatePartial'])
    ->name('shop-info.update-partial');

// Upload shop info image endpoint
Route::post('/shop-info/images', [ShopInfoController::class, 'uploadImage'])
    ->name('shop-info.upload-image');

// Resource endpoints for relationships
Route::get('/categories', [CategoryController::class, 'index'])
    ->name('categories.index');

Route::get('/categories/{id}', [CategoryController::class, 'show'])
    ->name('categories.show')
    ->where('id', '[0-9]+');

Route::get('/brands', [BrandController::class, 'index'])
    ->name('brands.index');

Route::get('/brands/{id}', [BrandController::class, 'show'])
    ->name('brands.show')
    ->where('id', '[0-9]+');

Route::get('/suppliers', [SupplierController::class, 'index'])
    ->name('suppliers.index');

Route::get('/suppliers/{id}', [SupplierController::class, 'show'])
    ->name('suppliers.show')
    ->where('id', '[0-9]+');

// Package status endpoint (if enabled)
if (config('products-package.features.status_endpoint', true)) {
    Route::get('/products/status', [ProductController::class, 'status'])
        ->name('products.status');
}

// Search endpoint - must come before {id} routes
if (config('products-package.features.search', true)) {
    Route::get('/products/search', [ProductController::class, 'search'])
        ->name('products.search');
}

// Export endpoint - must come before {id} routes
if (config('products-package.features.export', true)) {
    Route::get('/products/export', [ProductController::class, 'export'])
        ->name('products.export');
}

// Import endpoint - must come before {id} routes
if (config('products-package.features.import', true)) {
    Route::post('/products/import', [ProductController::class, 'import'])
        ->name('products.import');
}

// Get all enabled attributes - must come before {id} routes
// Used by WTM to fetch attributes from WL without database queries
Route::get('/products/attributes', [ProductController::class, 'getAttributes'])
    ->name('products.attributes');

// Main CRUD routes
Route::get('/products', [ProductController::class, 'index'])
    ->name('products.index');

Route::post('/products', [ProductController::class, 'store'])
    ->name('products.store');

Route::get('/products/{id}', [ProductController::class, 'show'])
    ->name('products.show')
    ->where('id', '[0-9]+');

Route::put('/products/{id}', [ProductController::class, 'update'])
    ->name('products.update')
    ->where('id', '[0-9]+');

Route::patch('/products/{id}', [ProductController::class, 'update'])
    ->name('products.patch')
    ->where('id', '[0-9]+');

// Upload product image endpoint - must come before delete route
// Note: Using POST because browsers don't properly support multipart/form-data with PUT
Route::post('/products/{id}/image', [ProductController::class, 'uploadImage'])
    ->name('products.upload-image')
    ->where('id', '[0-9]+');

Route::delete('/products/{id}', [ProductController::class, 'destroy'])
    ->name('products.destroy')
    ->where('id', '[0-9]+');

// Soft delete operations (if enabled)
if (config('products-package.features.soft_deletes', true)) {
    Route::post('/products/{id}/restore', [ProductController::class, 'restore'])
        ->name('products.restore')
        ->where('id', '[0-9]+');

    Route::delete('/products/{id}/force', [ProductController::class, 'forceDelete'])
        ->name('products.force-delete')
        ->where('id', '[0-9]+');
}