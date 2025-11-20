<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\Attribute;

use TheDiamondBox\ShopSync\Models\Attribute;
use TheDiamondBox\ShopSync\Services\Contracts\AttributeFetcherInterface;

/**
 * Database Attribute Fetcher
 *
 * Fetches attributes directly from database (WL mode).
 */
class DatabaseAttributeFetcher implements AttributeFetcherInterface
{
    /**
     * Get all enabled attributes with their options
     *
     * @return array
     */
    public function getAll(): array
    {
        $attributes = Attribute::where('enabled_on_dropship', true)
            ->with('inputTypeValues:id,attribute_id,value,sortby')
            ->orderBy('sortby')
            ->orderBy('name')
            ->get();

        // Transform to array format matching API response
        $result = $attributes->map(function ($attr) {
            $data = $attr->toArray();

            // Transform input_type_values to options array for frontend
            if (isset($data['input_type_values']) && is_array($data['input_type_values'])) {
                $data['options'] = array_map(function ($opt) {
                    return $opt['value'];
                }, $data['input_type_values']);
            } else {
                $data['options'] = [];
            }

            return $data;
        })->toArray();

        return $result;
    }
}
