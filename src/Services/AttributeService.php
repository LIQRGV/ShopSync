<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\AttributeFetchers\AttributeFetcherFactory;
use Illuminate\Http\Request;

/**
 * Attribute Service
 *
 * Handles business logic for attribute operations.
 * Follows the same pattern as CategoryService, BrandService, and SupplierService.
 */
class AttributeService
{
    protected $attributeFetcher;

    public function __construct(Request $request = null)
    {
        $this->attributeFetcher = AttributeFetcherFactory::makeFromConfig($request);
    }

    /**
     * Get all enabled attributes
     *
     * @return array
     */
    public function getAllAttributes(): array
    {
        return $this->attributeFetcher->getAll();
    }
}
