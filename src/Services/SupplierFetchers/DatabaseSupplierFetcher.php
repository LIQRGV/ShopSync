<?php

namespace TheDiamondBox\ShopSync\Services\SupplierFetchers;

use TheDiamondBox\ShopSync\Models\Supplier;
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
        $supplierModel = Supplier::class;

        return $supplierModel::whereNull('deleted_at')
            ->orderBy('company_name')
            ->get();
    }
}
