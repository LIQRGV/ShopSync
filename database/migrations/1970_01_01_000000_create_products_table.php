<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};