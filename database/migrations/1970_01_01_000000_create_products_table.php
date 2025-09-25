<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateProductsTable extends Migration
{
    /**
     * Check if this migration should run based on the package mode configuration.
     *
     * WL Mode (WhiteLabel): Should run migrations normally - creates products table and indexes
     * WTM Mode (Watcher of the Market): Should NEVER run migrations - skip all database operations
     *
     * @return bool True if migration should run, false if it should be skipped
     */
    private function shouldRunMigration(): bool
    {
        try {
            // Get the package mode from configuration with fallback to 'wl' for backward compatibility
            $mode = config('products-package.mode', 'wl');

            // Log the current mode for debugging purposes
            Log::info('Products migration mode check', ['mode' => $mode]);

            // Only run migration in WhiteLabel mode
            return $mode === 'wl' && !Schema::hasTable('products');
        } catch (\Exception $e) {
            // If there's any issue reading config, log the error and default to WL mode
            // This ensures existing installations continue to work
            Log::warning('Failed to read products-package mode configuration, defaulting to WL mode', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Run the migrations.
     *
     * This migration creates the products table with all necessary columns and indexes
     * for WhiteLabel mode operation. In WTM mode, this migration is completely skipped
     * to prevent any database schema changes since WTM mode operates in API-only mode.
     */
    public function up(): void
    {
        // Check if migration should run based on package mode configuration
        if (!$this->shouldRunMigration()) {
            Log::info('Products table migration skipped - Package is in WTM (Watcher of the Market) mode. No database operations will be performed.');
            return;
        }

        Log::info('Running products table migration - Package is in WL (WhiteLabel) mode');

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('stock')->default(0);
                $table->string('sku')->unique()->nullable();
                $table->string('category')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                // Regular indexes for performance
                $table->index('name');
                $table->index('category');
                $table->index('is_active');
                $table->index('created_at');
                $table->index('sku');
                $table->index(['price', 'is_active']); // Composite index for price filtering
                $table->index(['stock', 'is_active']); // Composite index for stock filtering
                $table->index(['category', 'is_active']); // Composite index for category filtering
            });

            // Add fulltext index for MySQL - this enables fast text search
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_index (name, description, sku)');
            }

            Log::info('Products table created successfully with all indexes');
        } else {
            Log::info('Products table already exists, skipping creation');
        }
    }

    /**
     * Reverse the migrations.
     *
     * This method drops the products table. Like the up() method, it respects
     * the package mode configuration and will not perform any database operations
     * when in WTM mode.
     */
    public function down(): void
    {
        // Check if migration should run based on package mode configuration
        if (!$this->shouldRunMigration()) {
            Log::info('Products table rollback skipped - Package is in WTM (Watcher of the Market) mode. No database operations will be performed.');
            return;
        }

        Log::info('Rolling back products table migration - Package is in WL (WhiteLabel) mode');

        Schema::dropIfExists('products');

        Log::info('Products table dropped successfully');
    }
};