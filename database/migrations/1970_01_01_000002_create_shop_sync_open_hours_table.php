<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CreateShopSyncOpenHoursTable extends Migration
{
    private function shouldRunMigration(): bool
    {
        try {
            $mode = config('products-package.mode', 'wl');
            $shouldMigrate = config('products-package.should_migrate', false);

            Log::info('Open hours migration mode check', ['mode' => $mode]);

            return $mode === 'wl' && $shouldMigrate;
        } catch (\Exception $e) {
            Log::warning('Failed to read products-package mode configuration, skipping migration', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function up(): void
    {
        if (!$this->shouldRunMigration()) {
            Log::info('Open hours table migration skipped - Package is in WTM mode or should_migrate is false.');
            return;
        }

        Log::info('Running open hours table migration - Package is in WL mode');

        if (!Schema::hasTable('open_hours')) {
            Schema::create('open_hours', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->string('day', 191);
                $table->boolean('is_open')->default(false);
                $table->time('open_at')->nullable();
                $table->time('close_at')->nullable();
                $table->timestamps();

                $table->index('shop_id');
            });

            Log::info('Open hours table created successfully');
        } else {
            Log::info('Open hours table already exists, skipping creation');
        }
    }

    public function down(): void
    {
        if (!$this->shouldRunMigration()) {
            Log::info('Open hours table rollback skipped - Package is in WTM mode or should_migrate is false.');
            return;
        }

        Log::info('Rolling back open hours table migration - Package is in WL mode');

        Schema::dropIfExists('open_hours');

        Log::info('Open hours table dropped successfully');
    }
}
