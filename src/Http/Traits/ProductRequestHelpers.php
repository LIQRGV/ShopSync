<?php

namespace TheDiamondBox\ShopSync\Http\Traits;

use TheDiamondBox\ShopSync\Helpers\JsonApiIncludeParser;

/**
 * Product Request Helpers Trait
 *
 * Provides common functionality for product-related requests
 */
trait ProductRequestHelpers
{
    /**
     * Get parsed include parameters from the request
     *
     * @return array
     */
    public function getIncludes()
    {
        return JsonApiIncludeParser::parseFromRequest($this);
    }

    /**
     * Get filters for product queries from the request
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->only([
            'category_id',
            'brand_id',
            'location_id',
            'supplier_id',
            'active',
            'sell_status',
            'min_price',
            'max_price',
            'with_trashed',
            'only_trashed',
            'sort_by',
            'sort_order',
        ]);
    }

    /**
     * Get pagination parameters from the request
     *
     * @return array
     */
    public function getPagination()
    {
        $perPage = (int) $this->get('per_page', config('products-package.per_page', 25));
        $perPage = min($perPage, 100); // Max 100 items per page

        return [
            'per_page' => $perPage,
            'page' => (int) $this->get('page', 1),
            'paginate' => true
        ];
    }

    /**
     * Get relationship loading array based on includes
     *
     * @param array $includes
     * @return array
     */
    public function getRelationshipsWith(array $includes = null)
    {
        $includes = $includes ?? $this->getIncludes();
        $with = [];

        if (in_array('category', $includes)) {
            $with[] = 'category';
        }
        if (in_array('brand', $includes)) {
            $with[] = 'brand';
        }
        if (in_array('location', $includes)) {
            $with[] = 'location';
        }
        if (in_array('supplier', $includes)) {
            $with[] = 'supplier';
        }
        if (in_array('attributes', $includes)) {
            $with[] = 'attributes';
        }
        if (in_array('productAttributes', $includes)) {
            $with[] = 'productAttributes.attribute';
        }

        return $with;
    }

    /**
     * Check if the request wants trashed records
     *
     * @return bool
     */
    public function includesTrashed()
    {
        return $this->boolean('with_trashed');
    }
}