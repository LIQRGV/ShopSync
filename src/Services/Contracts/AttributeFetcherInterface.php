<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface AttributeFetcherInterface
{
    /**
     * Get all enabled attributes
     *
     * @return array
     */
    public function getAll(): array;
}
