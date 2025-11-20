<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\Fetchers\Brand\BrandFetcherFactory;
use TheDiamondBox\ShopSync\Transformers\BrandTransformer;
use Illuminate\Http\Request;

/**
 * Brand Service
 *
 * Handles business logic for brand operations.
 * Follows the same pattern as ProductService with fetcher and transformer.
 */
class BrandService
{
    protected $brandFetcher;
    protected $transformer;

    public function __construct(BrandTransformer $transformer = null, Request $request = null)
    {
        $this->brandFetcher = BrandFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new BrandTransformer();
    }

    /**
     * Get all active brands with JSON API transformation
     *
     * @return array
     */
    public function getAllBrands(): array
    {
        $brands = $this->brandFetcher->getAll();

        // Transform to JSON API format
        return $this->transformer->transformBrands($brands);
    }
}
