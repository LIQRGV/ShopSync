<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use TheDiamondBox\ShopSync\Http\Traits\ProductRequestHelpers;

/**
 * Base Product Request
 *
 * Provides common functionality for all product-related requests with PHP 7.2 compatibility
 * Supports JSON API format for requests
 */
abstract class BaseProductRequest extends JsonApiRequest
{
    use ProductRequestHelpers;

    /**
     * Expected resource type for product requests
     *
     * @return string
     */
    protected function expectedResourceType()
    {
        return 'products';
    }

    /**
     * Relationship mappings for products
     * Maps JSON API relationship names to database foreign keys
     *
     * @return array
     */
    protected function relationshipMappings()
    {
        return [
            'category' => 'category_id',
            'brand' => 'brand_id',
            'location' => 'location_id',
            'supplier' => 'supplier_id',
        ];
    }
}