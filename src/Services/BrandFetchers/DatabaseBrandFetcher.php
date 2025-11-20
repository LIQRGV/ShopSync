<?php

namespace TheDiamondBox\ShopSync\Services\BrandFetchers;

use TheDiamondBox\ShopSync\Models\Brand;
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
        $brandModel = Brand::class;

        return $brandModel::whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
