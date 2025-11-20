<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\Fetchers\Category\CategoryFetcherFactory;
use TheDiamondBox\ShopSync\Transformers\CategoryTransformer;
use Illuminate\Http\Request;

/**
 * Category Service
 *
 * Handles business logic for category operations.
 * Follows the same pattern as ProductService with fetcher and transformer.
 */
class CategoryService
{
    protected $categoryFetcher;
    protected $transformer;

    public function __construct(CategoryTransformer $transformer = null, Request $request = null)
    {
        $this->categoryFetcher = CategoryFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new CategoryTransformer();
    }

    /**
     * Get all active categories with JSON API transformation
     *
     * @return array
     */
    public function getAllCategories(): array
    {
        $categories = $this->categoryFetcher->getAll();

        // Transform to JSON API format
        return $this->transformer->transformCategories($categories);
    }
}
