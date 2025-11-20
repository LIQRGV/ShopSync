<?php

namespace TheDiamondBox\ShopSync;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use TheDiamondBox\ShopSync\Services\ProductService;
use TheDiamondBox\ShopSync\Services\ShopInfoService;
use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use TheDiamondBox\ShopSync\Services\Fetchers\Product\ProductFetcherFactory;
use TheDiamondBox\ShopSync\Services\Fetchers\ShopInfo\ShopInfoFetcherFactory;
use TheDiamondBox\ShopSync\Models\Product;
use TheDiamondBox\ShopSync\Observers\ProductObserver;

class ProductPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/products-package.php',
            'products-package'
        );

        // Bind the ProductFetcherInterface
        $this->app->bind(ProductFetcherInterface::class, function ($app) {
            return ProductFetcherFactory::makeFromConfig();
        });

        // Bind the ShopInfoFetcherInterface
        $this->app->bind(ShopInfoFetcherInterface::class, function ($app) {
            return ShopInfoFetcherFactory::makeFromConfig();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->bootConfig();
        $this->bootMigrations();
        $this->bootMiddleware();
        $this->bootRoutes();
        $this->bootPublishing();
        $this->bootObservers();
    }

    /**
     * Boot configuration
     */
    protected function bootConfig()
    {
        // Configuration is already merged in register method
    }

    /**
     * Boot migrations
     */
    protected function bootMigrations()
    {
        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Boot middleware
     */
    protected function bootMiddleware()
    {
        // Register package authentication middleware
        $this->app['router']->aliasMiddleware('package.auth', \TheDiamondBox\ShopSync\Http\Middleware\PackageAuth::class);
    }

    /**
     * Boot routes
     */
    protected function bootRoutes()
    {
        // Only register routes if they haven't been disabled
        if (config('products-package.register_routes', true)) {
            Route::group($this->routeConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });
        }
    }

    /**
     * Boot publishing
     */
    protected function bootPublishing()
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/products-package.php' => config_path('products-package.php'),
            ], ['products-package-config', 'config']);

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], ['products-package-migrations', 'migrations']);

            // Publish everything
            $this->publishes([
                __DIR__ . '/../config/products-package.php' => config_path('products-package.php'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'products-package');
        }
    }

    /**
     * Boot model observers
     */
    protected function bootObservers()
    {
        // Register Product Observer for SSE broadcasting
        // ONLY in WL mode - WTM mode proxies to WL, no need for observers
        $mode = config('products-package.mode', 'wl');
        if ($mode === 'wl') {
            Product::observe(ProductObserver::class);
        }
    }

    /**
     * Get the route configuration for the package
     */
    protected function routeConfiguration(): array
    {
        // Build middleware array
        $middleware = $this->getRouteMiddleware();

        return [
            'prefix' => config('products-package.route_prefix', 'api/v1'),
            'middleware' => $middleware,
            'namespace' => 'TheDiamondBox\\ShopSync\\Http\\Controllers',
        ];
    }

    /**
     * Get route middleware configuration
     */
    protected function getRouteMiddleware(): array
    {
        $middleware = config('products-package.route_middleware', ['api']);

        // Ensure middleware is always an array
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        // Add custom auth middleware if configured
        $authMiddleware = config('products-package.auth_middleware');
        if ($authMiddleware) {
            if (is_array($authMiddleware)) {
                $middleware = array_merge($middleware, $authMiddleware);
            } else {
                $middleware[] = $authMiddleware;
            }
        }

        // Add package auth middleware if enabled
        if (config('products-package.enable_package_auth', false)) {
            $middleware[] = 'package.auth';
        }

        return array_unique($middleware);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides()
    {
        return [
            ProductService::class,
            ProductFetcherInterface::class,
            ShopInfoService::class,
            ShopInfoFetcherInterface::class,
        ];
    }
}