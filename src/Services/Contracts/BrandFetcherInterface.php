<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface BrandFetcherInterface
{
    /**
     * Get all active brands
     *
     * @return mixed
     */
    public function getAll();
}
