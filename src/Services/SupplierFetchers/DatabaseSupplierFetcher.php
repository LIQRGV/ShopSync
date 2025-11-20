<?php

namespace TheDiamondBox\ShopSync\Services\SupplierFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\SupplierFetcherInterface;

/**
 * Database Supplier Fetcher
 *
 * Fetches suppliers directly from database (WL mode).
 */
class DatabaseSupplierFetcher implements SupplierFetcherInterface
{
    /**
     * Get all active suppliers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        $supplierModel = config('products-package.models.supplier', \App\Supplier::class);

        return $supplierModel::whereNull('deleted_at')
            ->orderBy('company_name')
            ->get();
    }
}
