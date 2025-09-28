<?php

namespace Liqrgv\ShopSync\Providers;

use Illuminate\Support\ServiceProvider;
use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Observers\ProductObserver;

/**
 * Product Event Service Provider
 *
 * Registers product model observers for SSE broadcasting
 */
class ProductEventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Register the Product Observer
        Product::observe(ProductObserver::class);
    }
}