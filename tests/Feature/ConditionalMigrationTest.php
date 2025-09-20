<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Integration test for conditional migration behavior.
 *
 * This test demonstrates the complete workflow of how the package
 * behaves differently based on the PRODUCT_PACKAGE_MODE configuration.
 */
class ConditionalMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete WhiteLabel mode workflow.
     *
     * @test
     */
    public function it_supports_complete_wl_workflow(): void
    {
        // Set environment to WhiteLabel mode
        Config::set('products-package.mode', 'wl');

        // Step 1: Run migrations - should create tables
        Artisan::call('migrate', ['--force' => true]);

        // Verify database structure is created
        $this->assertTrue(Schema::hasTable('products'));

        // Step 2: Verify we can perform database operations
        // This would be where you'd test actual product operations
        // For now, we'll just verify the table exists and has the right structure

        $columns = Schema::getColumnListing('products');
        $expectedColumns = [
            'id', 'name', 'description', 'price', 'stock', 'sku',
            'category', 'metadata', 'is_active', 'created_at', 'updated_at', 'deleted_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} should exist in products table");
        }

        // Step 3: Verify rollback works
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertFalse(Schema::hasTable('products'));
    }

    /**
     * Test complete WTM mode workflow.
     *
     * @test
     */
    public function it_supports_complete_wtm_workflow(): void
    {
        // Set environment to Watcher of the Market mode
        Config::set('products-package.mode', 'wtm');

        // Step 1: Run migrations - should NOT create tables
        Artisan::call('migrate', ['--force' => true]);

        // Verify no database structure is created
        $this->assertFalse(Schema::hasTable('products'));

        // Step 2: Verify that even if we try multiple times, tables are never created
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasTable('products'));

        // Step 3: Verify rollback also does nothing
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertFalse(Schema::hasTable('products'));
    }

    /**
     * Test switching between modes.
     *
     * @test
     */
    public function it_handles_mode_switching_correctly(): void
    {
        // Start in WTM mode
        Config::set('products-package.mode', 'wtm');
        Artisan::call('migrate', ['--force' => true]);
        $this->assertFalse(Schema::hasTable('products'));

        // Switch to WL mode
        Config::set('products-package.mode', 'wl');
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('products'));

        // Switch back to WTM mode - existing table should remain
        Config::set('products-package.mode', 'wtm');

        // Rollback should not work in WTM mode
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('products'), 'Table should remain when rollback is attempted in WTM mode');

        // Switch back to WL mode to properly clean up
        Config::set('products-package.mode', 'wl');
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertFalse(Schema::hasTable('products'));
    }

    /**
     * Test that configuration changes are respected immediately.
     *
     * @test
     */
    public function it_respects_configuration_changes_immediately(): void
    {
        // Test configuration changes are picked up on each migration run
        $modes = ['wl', 'wtm', 'wl', 'wtm'];

        foreach ($modes as $index => $mode) {
            // Clean up any existing table first
            if (Schema::hasTable('products')) {
                Config::set('products-package.mode', 'wl');
                Artisan::call('migrate:rollback', ['--force' => true]);
            }

            Config::set('products-package.mode', $mode);
            Artisan::call('migrate', ['--force' => true]);

            if ($mode === 'wl') {
                $this->assertTrue(Schema::hasTable('products'), "Iteration {$index}: Table should exist in WL mode");
            } else {
                $this->assertFalse(Schema::hasTable('products'), "Iteration {$index}: Table should not exist in WTM mode");
            }
        }
    }

    /**
     * Test that the package gracefully handles configuration issues.
     *
     * @test
     */
    public function it_handles_configuration_edge_cases(): void
    {
        // Test with completely missing config
        Config::set('products-package', []);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('products'), 'Should default to WL mode when config is missing');

        // Clean up
        Artisan::call('migrate:rollback', ['--force' => true]);

        // Test with null config
        Config::set('products-package.mode', null);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('products'), 'Should default to WL mode when config is null');
    }

    protected function tearDown(): void
    {
        // Ensure we clean up any tables that might have been created
        if (Schema::hasTable('products')) {
            Config::set('products-package.mode', 'wl');
            Artisan::call('migrate:rollback', ['--force' => true]);
        }

        parent::tearDown();
    }
}