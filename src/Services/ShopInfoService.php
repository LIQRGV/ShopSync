<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\ShopInfoFetchers\ShopInfoFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use Illuminate\Http\Request;

/**
 * ShopInfoService
 *
 * High-level service for shop info operations
 */
class ShopInfoService
{
    protected $shopInfoFetcher;
    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
        // Delay fetcher creation until needed, or create in controller
        // This prevents issues with Request not being available during service provider binding
    }

    /**
     * Get or create the shop info fetcher
     *
     * @return ShopInfoFetcherInterface
     */
    protected function getFetcher()
    {
        if (!$this->shopInfoFetcher) {
            $this->shopInfoFetcher = ShopInfoFetcherFactory::makeFromConfig($this->request);
        }

        return $this->shopInfoFetcher;
    }

    /**
     * Get shop info
     *
     * @return array|null
     */
    public function getShopInfo()
    {
        $shopInfo = $this->getFetcher()->get();

        if (!$shopInfo) {
            return null;
        }

        return $this->formatResponse($shopInfo);
    }

    /**
     * Update shop info (full replace)
     *
     * @param array $data
     * @return array|null
     */
    public function updateShopInfo(array $data)
    {
        $shopInfo = $this->getFetcher()->update($data);

        if (!$shopInfo) {
            return null;
        }

        return $this->formatResponse($shopInfo);
    }

    /**
     * Update shop info (partial - prevents empty override)
     *
     * @param array $data
     * @return array|null
     */
    public function updateShopInfoPartial(array $data)
    {
        $shopInfo = $this->getFetcher()->updatePartial($data);

        if (!$shopInfo) {
            return null;
        }

        return $this->formatResponse($shopInfo);
    }

    /**
     * Format response
     *
     * @param mixed $shopInfo
     * @return array
     */
    protected function formatResponse($shopInfo)
    {
        return [
            'data' => [
                'type' => 'shop-info',
                'id' => '1',
                'attributes' => $shopInfo->toArray()
            ]
        ];
    }
}
