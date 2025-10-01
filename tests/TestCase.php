<?php

namespace TheDiamondBox\ShopSync\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TheDiamondBox\ShopSync\ProductPackageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run the package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            ProductPackageServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default configuration for testing
        $app['config']->set('products-package.mode', 'wl');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}