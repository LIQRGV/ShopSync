<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\Category;

use TheDiamondBox\ShopSync\Models\Category;
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
        $categoryModel = Category::class;

        return $categoryModel::where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
