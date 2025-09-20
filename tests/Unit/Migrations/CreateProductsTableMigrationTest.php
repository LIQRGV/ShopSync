<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test suite for the products table migration conditional logic.
 *
 * This test ensures that the migration respects the PRODUCT_PACKAGE_MODE
 * configuration and behaves correctly in both WL and WTM modes.
 */
class CreateProductsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we start with a clean state
        if (Schema::hasTable('products')) {
            Schema::dropIfExists('products');
        }
    }

    /**
     * Test that migration runs normally in WL (WhiteLabel) mode.
     *
     * @test
     */
    public function it_runs_migration_in_wl_mode(): void
    {
        // Set package mode to WhiteLabel
        Config::set('products-package.mode', 'wl');

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assert that the products table was created
        $this->assertTrue(Schema::hasTable('products'));

        // Assert that the table has the expected columns
        $this->assertTrue(Schema::hasColumn('products', 'id'));
        $this->assertTrue(Schema::hasColumn('products', 'name'));
        $this->assertTrue(Schema::hasColumn('products', 'description'));
        $this->assertTrue(Schema::hasColumn('products', 'price'));
        $this->assertTrue(Schema::hasColumn('products', 'stock'));
        $this->assertTrue(Schema::hasColumn('products', 'sku'));
        $this->assertTrue(Schema::hasColumn('products', 'category'));
        $this->assertTrue(Schema::hasColumn('products', 'metadata'));
        $this->assertTrue(Schema::hasColumn('products', 'is_active'));
        $this->assertTrue(Schema::hasColumn('products', 'created_at'));
        $this->assertTrue(Schema::hasColumn('products', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('products', 'deleted_at'));
    }

    /**
     * Test that migration is skipped in WTM (Watch the Market) mode.
     *
     * @test
     */
    public function it_skips_migration_in_wtm_mode(): void
    {
        // Set package mode to Watcher of the Market
        Config::set('products-package.mode', 'wtm');

        // Ensure the table doesn't exist before migration
        $this->assertFalse(Schema::hasTable('products'));

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assert that the products table was NOT created
        $this->assertFalse(Schema::hasTable('products'));
    }

    /**
     * Test that migration runs with default configuration (backward compatibility).
     *
     * @test
     */
    public function it_runs_migration_with_default_config(): void
    {
        // Don't set any configuration (test default behavior)
        Config::forget('products-package.mode');

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assert that the products table was created (default should be WL mode)
        $this->assertTrue(Schema::hasTable('products'));
    }

    /**
     * Test that migration handles missing config gracefully.
     *
     * @test
     */
    public function it_handles_missing_config_gracefully(): void
    {
        // Set an invalid configuration that might cause config() to fail
        Config::set('products-package', null);

        // Run the migration - it should not crash and should default to WL mode
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assert that the products table was created (fallback to WL mode)
        $this->assertTrue(Schema::hasTable('products'));
    }

    /**
     * Test that rollback is also conditional on package mode.
     *
     * @test
     */
    public function it_skips_rollback_in_wtm_mode(): void
    {
        // First, create the table in WL mode
        Config::set('products-package.mode', 'wl');
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Verify table exists
        $this->assertTrue(Schema::hasTable('products'));

        // Now switch to WTM mode and try to rollback
        Config::set('products-package.mode', 'wtm');
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations',
            '--force' => true
        ]);

        // Table should still exist because rollback was skipped in WTM mode
        $this->assertTrue(Schema::hasTable('products'));
    }

    /**
     * Test that rollback works normally in WL mode.
     *
     * @test
     */
    public function it_runs_rollback_in_wl_mode(): void
    {
        // First, create the table in WL mode
        Config::set('products-package.mode', 'wl');
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Verify table exists
        $this->assertTrue(Schema::hasTable('products'));

        // Rollback in WL mode
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations',
            '--force' => true
        ]);

        // Table should be dropped
        $this->assertFalse(Schema::hasTable('products'));
    }

    /**
     * Test that appropriate logging occurs when migration is skipped.
     *
     * @test
     */
    public function it_logs_when_migration_is_skipped(): void
    {
        // Mock the Log facade to capture log messages
        Log::shouldReceive('info')
            ->with('Products migration mode check', ['mode' => 'wtm'])
            ->once();

        Log::shouldReceive('info')
            ->with('Products table migration skipped - Package is in WTM (Watcher of the Market) mode. No database operations will be performed.')
            ->once();

        // Set package mode to WTM
        Config::set('products-package.mode', 'wtm');

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assertions are handled by the shouldReceive expectations above
    }

    /**
     * Test that appropriate logging occurs when migration runs.
     *
     * @test
     */
    public function it_logs_when_migration_runs(): void
    {
        // Mock the Log facade to capture log messages
        Log::shouldReceive('info')
            ->with('Products migration mode check', ['mode' => 'wl'])
            ->once();

        Log::shouldReceive('info')
            ->with('Running products table migration - Package is in WL (WhiteLabel) mode')
            ->once();

        Log::shouldReceive('info')
            ->with('Products table created successfully with all indexes')
            ->once();

        // Set package mode to WL
        Config::set('products-package.mode', 'wl');

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
            '--force' => true
        ]);

        // Assertions are handled by the shouldReceive expectations above
    }

    /**
     * Test various invalid mode configurations.
     *
     * @test
     */
    public function it_handles_invalid_mode_configurations(): void
    {
        $invalidModes = ['', 'invalid', 'WL', 'WTM', 0, false, null];

        foreach ($invalidModes as $mode) {
            // Reset state
            if (Schema::hasTable('products')) {
                Schema::dropIfExists('products');
            }

            Config::set('products-package.mode', $mode);

            // Run the migration
            Artisan::call('migrate', [
                '--path' => 'database/migrations/1970_01_01_000000_create_products_table.php',
                '--force' => true
            ]);

            // For any invalid mode, migration should default to WL behavior
            // and create the table (backward compatibility)
            $this->assertTrue(
                Schema::hasTable('products'),
                "Migration should default to WL mode for invalid mode: " . var_export($mode, true)
            );
        }
    }
}