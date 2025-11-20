<?php

namespace TheDiamondBox\ShopSync\Services\BrandFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\BrandFetcherInterface;

/**
 * Database Brand Fetcher
 *
 * Fetches brands directly from database (WL mode).
 */
class DatabaseBrandFetcher implements BrandFetcherInterface
{
    /**
     * Get all active brands
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        $brandModel = config('products-package.models.brand', \App\Brand::class);

        return $brandModel::whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
