<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

/**
 * ShopInfoFetcherInterface
 *
 * Contract for shop info fetchers (Database for WL, API for WTM)
 */
interface ShopInfoFetcherInterface
{
    /**
     * Get shop info
     *
     * @return mixed
     */
    public function get();

    /**
     * Update shop info (full replace)
     *
     * @param array $data
     * @return mixed
     */
    public function update(array $data);

    /**
     * Update shop info (partial - only non-empty values)
     * Prevents empty data from overriding existing values
     *
     * @param array $data
     * @return mixed
     */
    public function updatePartial(array $data);
}
