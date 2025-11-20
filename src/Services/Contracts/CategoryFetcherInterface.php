<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface CategoryFetcherInterface
{
    /**
     * Get all active categories
     *
     * @return mixed
     */
    public function getAll();
}
