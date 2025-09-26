<?php

use Illuminate\Support\Facades\Route;
use Liqrgv\ShopSync\Http\Controllers\ProductController;
use Liqrgv\ShopSync\Http\Controllers\SseController;

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