<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface SupplierFetcherInterface
{
    /**
     * Get all active suppliers
     *
     * @return mixed
     */
    public function getAll();
}
