<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\Fetchers\Supplier\SupplierFetcherFactory;
use TheDiamondBox\ShopSync\Transformers\SupplierTransformer;
use Illuminate\Http\Request;

/**
 * Supplier Service
 *
 * Handles business logic for supplier operations.
 * Follows the same pattern as ProductService with fetcher and transformer.
 */
class SupplierService
{
    protected $supplierFetcher;
    protected $transformer;

    public function __construct(SupplierTransformer $transformer = null, Request $request = null)
    {
        $this->supplierFetcher = SupplierFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new SupplierTransformer();
    }

    /**
     * Get all active suppliers with JSON API transformation
     *
     * @return array
     */
    public function getAllSuppliers(): array
    {
        $suppliers = $this->supplierFetcher->getAll();

        // Transform to JSON API format
        return $this->transformer->transformSuppliers($suppliers);
    }
}
