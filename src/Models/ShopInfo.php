<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ShopInfo Model - WL Mode Only
 *
 * Singleton table for shop/business information.
 * Proxied to WTM mode via API.
 */
class ShopInfo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shop_info';

    /**
     * No primary key (singleton table)
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key for the model.
     *
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        // Basic Business Info
        'name',
        'phone_number',
        'email',
        'legal_name',
        'website',
        'about_us',

        // Social Media
        'facebook',
        'tiktok',
        'youtube',
        'instagram',
        'whatsapp_link',

        // Primary Address
        'address_line_1',
        'address_line_2',
        'city',
        'country',
        'postal_code',
        'gmap_address',

        // Registered Office Address
        'registered_office_address_line_1',
        'registered_office_address_line_2',
        'registered_office_city',
        'registered_office_country',
        'registered_office_postal_code',

        // Financial/Banking Info
        'vat_no',
        'company_no',
        'bank_name',
        'account_name',
        'account_number',
        'sort_code',
        'bic',
        'iban',

        // Invoice Settings
        'invoice_tc_enabled',
        'invoice_tc_selected_page_id',

        // Media/Images
        'logo',
        'original_logo',
        'favicon',
        'original_favicon',
        'banner_1',
        'banner_1_title',
        'banner_1_url',
        'banner_2',
        'banner_2_title',
        'banner_2_url',
        'watches_workshop_image',
        'sell_watch_image',
        'valuations_additional_logo',

        // SEO
        'seo_author',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'custom_website_link',
        'title_for_navigation_menu',

        // Twitter/X Card
        'twitter_title',
        'twitter_site',
        'twitter_card',
        'twitter_description',
        'twitter_creator',
        'twitter_image',

        // Open Graph
        'og_title',
        'og_type',
        'og_url',
        'og_image',
        'og_site_name',
        'og_description',

        // Third-party Integrations
        'captcha_site_key',
        'captcha_secret_key',
        'trustpilot_embed_code',
        'trustpilot_business_unit_id',
        'trustpilot_review_grid',
        'google_review_business_unit_id',
        'google_maps_api_key',
        'gtag_key',
        'smartsupp_embed_code',

        // Payment Gateway - Stripe
        'stripe_payment',
        'stripe_secret_key',
        'stripe_publish_key',
        'stripe_webhook_secret_key',
        'stripe_allow_accept_card_payments',
        'stripe_allow_pay_with_link',
        'marketplace_stripe_customer_id',

        // Payment Gateway - TakePayment
        'take_payment',
        'take_payment_redirect_url',
        'take_payment_secret',
        'take_payment_terminal_id',
        'take_payment_category_code',

        // Payment Gateway - DNA
        'dna_payment',
        'dna_payment_client_id',
        'dna_payment_client_secret',
        'dna_payment_terminal_id',

        // Product/SKU Settings
        'sku_prefix_watches',
        'sku_prefix_jewellery',
        'catalogue_mode',

        // Misc
        'document_attribute_last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'invoice_tc_enabled' => 'boolean',
        'invoice_tc_selected_page_id' => 'integer',
        'vat_no' => 'integer',
        'company_no' => 'integer',
        'account_number' => 'integer',
        'catalogue_mode' => 'boolean',
        'stripe_payment' => 'boolean',
        'stripe_allow_accept_card_payments' => 'boolean',
        'stripe_allow_pay_with_link' => 'boolean',
        'take_payment' => 'boolean',
        'dna_payment' => 'boolean',
        'document_attribute_last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
