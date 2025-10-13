<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use TheDiamondBox\ShopSync\Helpers\JsonApiIncludeParser;

abstract class BaseShopInfoRequest extends JsonApiRequest
{
    protected function expectedResourceType()
    {
        return 'shop-info';
    }

    protected function relationshipMappings()
    {
        return [];
    }

    public function getIncludes()
    {
        return JsonApiIncludeParser::parseFromRequest($this);
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        if ($this->has('open_hours')) {
            return;
        }

        if ($this->isJsonApiFormat() && isset($this->jsonApiRelationships['openHours'])) {
            $openHoursData = $this->jsonApiRelationships['openHours'];

            if (is_array($openHoursData)) {
                if (isset($openHoursData['data']) && is_array($openHoursData['data'])) {
                    $openHoursData = $openHoursData['data'];
                }

                $converted = [];

                foreach ($openHoursData as $item) {
                    if (isset($item['attributes'])) {
                        $converted[] = $item['attributes'];
                    } else {
                        $converted[] = $item;
                    }
                }

                $this->merge(['open_hours' => $converted]);
            }
        }
    }

    protected function commonRules()
    {
        return [
            // Basic Business Info
            'name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'legal_name' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'about_us' => 'nullable|string',

            // Social Media
            'facebook' => 'nullable|max:255',
            'tiktok' => 'nullable|max:255',
            'youtube' => 'nullable|max:255',
            'instagram' => 'nullable|max:255',
            'whatsapp_link' => 'nullable|max:255',

            // Primary Address
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'gmap_address' => 'nullable|url',

            // Registered Office Address
            'registered_office_address_line_1' => 'nullable|string|max:255',
            'registered_office_address_line_2' => 'nullable|string|max:255',
            'registered_office_city' => 'nullable|string|max:100',
            'registered_office_country' => 'nullable|string|max:100',
            'registered_office_postal_code' => 'nullable|string|max:20',

            // Financial/Banking Info
            'vat_no' => 'nullable|integer',
            'company_no' => 'nullable|integer',
            'bank_name' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|integer',
            'sort_code' => 'nullable|string|max:20',
            'bic' => 'nullable|string|max:20',
            'iban' => 'nullable|string|max:50',

            // Invoice Settings
            'invoice_tc_enabled' => 'nullable|boolean',
            'invoice_tc_selected_page_id' => 'nullable|integer',

            // Media/Images
            'logo' => 'nullable|string|max:500',
            'original_logo' => 'nullable|string|max:500',
            'favicon' => 'nullable|string|max:500',
            'original_favicon' => 'nullable|string|max:500',
            'banner_1' => 'nullable|string|max:500',
            'banner_1_title' => 'nullable|string|max:255',
            'banner_1_url' => 'nullable|url|max:500',
            'banner_2' => 'nullable|string|max:500',
            'banner_2_title' => 'nullable|string|max:255',
            'banner_2_url' => 'nullable|url|max:500',
            'watches_workshop_image' => 'nullable|string|max:500',
            'sell_watch_image' => 'nullable|string|max:500',
            'valuations_additional_logo' => 'nullable|string|max:500',

            // SEO
            'seo_author' => 'nullable|string|max:255',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
            'custom_website_link' => 'nullable|url|max:500',
            'title_for_navigation_menu' => 'nullable|string|max:255',

            // Twitter/X Card
            'twitter_title' => 'nullable|string|max:255',
            'twitter_site' => 'nullable|string|max:255',
            'twitter_card' => 'nullable|string|max:50',
            'twitter_description' => 'nullable|string',
            'twitter_creator' => 'nullable|string|max:255',
            'twitter_image' => 'nullable|string|max:500',

            // Open Graph
            'og_title' => 'nullable|string|max:255',
            'og_type' => 'nullable|string|max:100',
            'og_url' => 'nullable|url|max:500',
            'og_image' => 'nullable|string|max:500',
            'og_site_name' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',

            // Third-party Integrations
            'captcha_site_key' => 'nullable|string|max:255',
            'captcha_secret_key' => 'nullable|string|max:255',
            'trustpilot_embed_code' => 'nullable|string',
            'trustpilot_business_unit_id' => 'nullable|string|max:255',
            'trustpilot_review_grid' => 'nullable|string',
            'google_review_business_unit_id' => 'nullable|string|max:255',
            'google_maps_api_key' => 'nullable|string|max:255',
            'gtag_key' => 'nullable|string|max:255',
            'smartsupp_embed_code' => 'nullable|string',

            // Payment Gateway - Stripe
            'stripe_payment' => 'nullable|boolean',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_publish_key' => 'nullable|string|max:255',
            'stripe_webhook_secret_key' => 'nullable|string|max:255',
            'stripe_allow_accept_card_payments' => 'nullable|boolean',
            'stripe_allow_pay_with_link' => 'nullable|boolean',
            'marketplace_stripe_customer_id' => 'nullable|string|max:255',

            // Payment Gateway - TakePayment
            'take_payment' => 'nullable|boolean',
            'take_payment_redirect_url' => 'nullable|url|max:500',
            'take_payment_secret' => 'nullable|string|max:255',
            'take_payment_terminal_id' => 'nullable|string|max:255',
            'take_payment_category_code' => 'nullable|string|max:255',

            // Payment Gateway - DNA
            'dna_payment' => 'nullable|boolean',
            'dna_payment_client_id' => 'nullable|string|max:255',
            'dna_payment_client_secret' => 'nullable|string|max:255',
            'dna_payment_terminal_id' => 'nullable|string|max:255',

            // Product/SKU Settings
            'sku_prefix_watches' => 'nullable|string|max:50',
            'sku_prefix_jewellery' => 'nullable|string|max:50',
            'catalogue_mode' => 'nullable|boolean',

            // Open Hours
            'open_hours' => 'nullable|array',
            'open_hours.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'open_hours.*.is_open' => 'required|boolean',
            'open_hours.*.open_at' => 'nullable|date_format:H:i:s',
            'open_hours.*.close_at' => 'nullable|date_format:H:i:s',
        ];
    }
}
