<?php

namespace TheDiamondBox\ShopSync\Services\CategoryFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\CategoryFetcherInterface;

/**
 * Database Category Fetcher
 *
 * Fetches categories directly from database (WL mode).
 */
class DatabaseCategoryFetcher implements CategoryFetcherInterface
{
    /**
     * Get all active categories
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        $categoryModel = config('products-package.models.category', \App\Category::class);

        return $categoryModel::where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
