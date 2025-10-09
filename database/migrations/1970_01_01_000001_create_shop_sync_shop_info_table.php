<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CreateShopSyncShopInfoTable extends Migration
{
    /**
     * Check if this migration should run based on the package mode configuration.
     *
     * WL Mode (WhiteLabel): Should run migrations normally - creates shop_info table
     * WTM Mode (Watch the Market): Should NEVER run migrations - skip all database operations
     *
     * @return bool True if migration should run, false if it should be skipped
     */
    private function shouldRunMigration(): bool
    {
        try {
            // Get the package mode from configuration with fallback to 'wl' for backward compatibility
            $mode = config('products-package.mode', 'wl');
            $shouldMigrate = config('products-package.should_migrate', false);

            // Log the current mode for debugging purposes
            Log::info('Shop info migration mode check', ['mode' => $mode]);

            // Only run migration in WhiteLabel mode
            return $mode === 'wl' && $shouldMigrate;
        } catch (\Exception $e) {
            // If there's any issue reading config, log the error and default to not migrating
            // This ensures existing installations continue to work
            Log::warning('Failed to read products-package mode configuration, skipping migration', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Run the migrations.
     *
     * This migration creates the shop_info table with all necessary columns
     * for WhiteLabel mode operation. In WTM mode, this migration is completely skipped
     * to prevent any database schema changes since WTM mode operates in API-only mode.
     */
    public function up(): void
    {
        // Check if migration should run based on package mode configuration
        if (!$this->shouldRunMigration()) {
            Log::info('Shop info table migration skipped - Package is in WTM (Watch the Market) mode or should_migrate is false. No database operations will be performed.');
            return;
        }

        Log::info('Running shop info table migration - Package is in WL (WhiteLabel) mode');

        if (!Schema::hasTable('shop_info')) {
            Schema::create('shop_info', function (Blueprint $table) {
                // Basic shop information
                $table->string('name')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('email')->nullable();

                // Social media links
                $table->string('facebook')->nullable();
                $table->string('tiktok')->nullable();
                $table->string('youtube')->nullable();
                $table->string('instagram')->nullable();

                // Address information
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('postal_code')->nullable();

                // Invoice settings
                $table->integer('invoice_tc_enabled')->default(0)->comment('0:Disable | 1:Enable');
                $table->unsignedBigInteger('invoice_tc_selected_page_id')->nullable();
                $table->integer('vat_no')->nullable();
                $table->integer('company_no')->nullable();

                // Registered office address
                $table->string('registered_office_address_line_1')->nullable();
                $table->string('registered_office_address_line_2')->nullable();
                $table->string('registered_office_city')->nullable();
                $table->string('registered_office_country')->nullable();
                $table->string('registered_office_postal_code')->nullable();

                // Bank information
                $table->string('bank_name')->nullable();
                $table->string('account_name')->nullable();
                $table->integer('account_number')->nullable();
                $table->string('sort_code')->nullable();

                // Images
                $table->string('logo')->nullable();
                $table->longText('original_logo')->nullable();
                $table->string('favicon')->nullable();
                $table->longText('original_favicon')->nullable();
                $table->string('banner_1')->nullable();
                $table->string('banner_1_title')->nullable();
                $table->string('banner_1_url')->nullable();
                $table->string('banner_2')->nullable();
                $table->string('banner_2_title')->nullable();
                $table->string('banner_2_url')->nullable();
                $table->string('watches_workshop_image')->nullable();
                $table->string('sell_watch_image')->nullable();
                $table->string('valuations_additional_logo')->nullable();

                // Timestamps
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                // Additional company information
                $table->string('legal_name')->nullable();
                $table->string('website')->nullable();
                $table->text('gmap_address')->nullable();
                $table->text('about_us')->nullable();

                // Captcha configuration
                $table->string('captcha_site_key')->nullable();
                $table->string('captcha_secret_key')->nullable();

                // Third-party integrations
                $table->text('trustpilot_embed_code')->nullable();
                $table->string('trustpilot_business_unit_id')->nullable();
                $table->string('google_review_business_unit_id')->nullable();
                $table->text('google_maps_api_key')->nullable();
                $table->string('gtag_key')->nullable()->comment('Key for google tag manager');
                $table->string('whatsapp_link')->nullable();
                $table->text('smartsupp_embed_code')->nullable();
                $table->text('trustpilot_review_grid')->nullable();

                // E-commerce settings
                $table->integer('catalogue_mode')->default(0)->comment('0:Disabled | 1:Enabled');

                // Stripe payment configuration
                $table->integer('stripe_payment')->default(0)->comment('0:Disabled | 1:Enabled');
                $table->text('stripe_secret_key')->nullable();
                $table->text('stripe_publish_key')->nullable();
                $table->text('stripe_webhook_secret_key')->nullable();
                $table->integer('stripe_allow_accept_card_payments')->default(0)->comment('0:Disabled | 1:Enabled');
                $table->integer('stripe_allow_pay_with_link')->default(0)->comment('0:Disabled | 1:Enabled');

                // Take payment configuration
                $table->integer('take_payment')->default(0)->comment('0:Disabled | 1:Enabled');
                $table->text('take_payment_redirect_url')->nullable();
                $table->text('take_payment_secret')->nullable();
                $table->text('take_payment_terminal_id')->nullable();
                $table->text('take_payment_category_code')->nullable();

                // DNA payment configuration
                $table->integer('dna_payment')->default(0)->comment('0:Disabled | 1:Enabled');
                $table->text('dna_payment_client_id')->nullable();
                $table->text('dna_payment_client_secret')->nullable();
                $table->text('dna_payment_terminal_id')->nullable();

                // SKU prefixes
                $table->string('sku_prefix_watches')->nullable()->comment('Prefix for watches SKU');
                $table->string('sku_prefix_jewellery')->nullable()->comment('Prefix for jewellery SKU');

                // Custom settings
                $table->string('custom_website_link')->nullable();
                $table->string('title_for_navigation_menu')->nullable();

                // SEO configuration
                $table->string('seo_author')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('seo_keywords')->nullable();

                // Twitter/X meta tags
                $table->string('twitter_title')->nullable();
                $table->string('twitter_site')->nullable();
                $table->string('twitter_card')->nullable();
                $table->text('twitter_description')->nullable();
                $table->string('twitter_creator')->nullable();
                $table->string('twitter_image')->nullable();

                // Open Graph meta tags
                $table->string('og_title')->nullable();
                $table->string('og_type')->nullable();
                $table->string('og_url')->nullable();
                $table->string('og_image')->nullable();
                $table->string('og_site_name')->nullable();
                $table->text('og_description')->nullable();

                // Additional fields
                $table->dateTime('document_attribute_last_updated_at')->nullable();
                $table->string('bic')->nullable();
                $table->string('iban')->nullable();
                $table->string('marketplace_stripe_customer_id')->nullable();
            });

            Log::info('Shop info table created successfully');
        } else {
            Log::info('Shop info table already exists, skipping creation');
        }
    }

    /**
     * Reverse the migrations.
     *
     * This method drops the shop_info table. Like the up() method, it respects
     * the package mode configuration and will not perform any database operations
     * when in WTM mode.
     */
    public function down(): void
    {
        // Check if migration should run based on package mode configuration
        if (!$this->shouldRunMigration()) {
            Log::info('Shop info table rollback skipped - Package is in WTM (Watch the Market) mode or should_migrate is false. No database operations will be performed.');
            return;
        }

        Log::info('Rolling back shop info table migration - Package is in WL (WhiteLabel) mode');

        Schema::dropIfExists('shop_info');

        Log::info('Shop info table dropped successfully');
    }
}
