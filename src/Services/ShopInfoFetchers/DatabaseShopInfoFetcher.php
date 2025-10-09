<?php

namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use TheDiamondBox\ShopSync\Models\ShopInfo;

/**
 * DatabaseShopInfoFetcher - WL Mode
 *
 * Direct database access for shop info
 */
class DatabaseShopInfoFetcher implements ShopInfoFetcherInterface
{
    /**
     * Get shop info
     *
     * @return ShopInfo|null
     */
    public function get()
    {
        return ShopInfo::first();
    }

    /**
     * Update shop info (full replace)
     *
     * @param array $data
     * @return ShopInfo
     */
    public function update(array $data)
    {
        $shopInfo = ShopInfo::first();

        if ($shopInfo) {
            $shopInfo->update($data);
            return $shopInfo->fresh();
        }

        return ShopInfo::create($data);
    }

    /**
     * Update shop info (partial - only non-empty values)
     * This prevents empty data from overriding existing values
     *
     * @param array $data
     * @return ShopInfo|null
     */
    public function updatePartial(array $data)
    {
        // Filter out null and empty string values
        $filtered = array_filter($data, function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($filtered)) {
            return $this->get();
        }

        return $this->update($filtered);
    }
}
