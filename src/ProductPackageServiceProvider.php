<?php

namespace Liqrgv\ShopSync;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Liqrgv\ShopSync\Services\ProductService;
use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use Liqrgv\ShopSync\Services\ProductFetchers\ProductFetcherFactory;

class ProductPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
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

        // Bind the ProductService
        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService($app->make(ProductFetcherInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->bootViews();
        $this->bootMigrations();
        $this->bootMiddleware();
        $this->bootRoutes();
        $this->bootPublishing();
    }

    /**
     * Boot configuration
     */
    protected function bootConfig(): void
    {
        // Configuration is already merged in register method
    }

    /**
     * Boot views
     */
    protected function bootViews(): void
    {
        // Load views from package
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'products-package');
    }

    /**
     * Boot migrations
     */
    protected function bootMigrations(): void
    {
        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Boot middleware
     */
    protected function bootMiddleware(): void
    {
        // Register package authentication middleware
        $this->app['router']->aliasMiddleware('package.auth', \Liqrgv\ShopSync\Http\Middleware\PackageAuth::class);
    }

    /**
     * Boot routes
     */
    protected function bootRoutes(): void
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
    protected function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/products-package.php' => config_path('products-package.php'),
            ], ['products-package-config', 'config']);

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/products-package'),
            ], ['products-package-views', 'views']);

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], ['products-package-migrations', 'migrations']);

            // Publish assets (if any)
            $this->publishes([
                __DIR__ . '/../resources/assets' => public_path('vendor/products-package'),
            ], ['products-package-assets', 'assets']);

            // Publish everything
            $this->publishes([
                __DIR__ . '/../config/products-package.php' => config_path('products-package.php'),
                __DIR__ . '/../resources/views' => resource_path('views/vendor/products-package'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
                __DIR__ . '/../resources/assets' => public_path('vendor/products-package'),
            ], 'products-package');
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
            'namespace' => 'Liqrgv\\ShopSync\\Http\\Controllers',
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
    public function provides(): array
    {
        return [
            ProductService::class,
            ProductFetcherInterface::class,
        ];
    }
}