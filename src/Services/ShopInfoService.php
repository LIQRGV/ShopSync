<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\ShopInfoFetchers\ShopInfoFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use TheDiamondBox\ShopSync\Transformers\ShopInfoJsonApiTransformer;
use Illuminate\Http\Request;

/**
 * ShopInfoService
 *
 * High-level service for shop info operations
 */
class ShopInfoService
{
    protected $shopInfoFetcher;
    protected $transformer;

    public function __construct(ShopInfoJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->shopInfoFetcher = ShopInfoFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new ShopInfoJsonApiTransformer();
    }

    /**
     * Validate includes and return errors if any
     *
     * @param array $includes
     * @return array
     */
    public function validateIncludes(array $includes)
    {
        return $this->transformer->validateIncludes($includes);
    }

    /**
     * Get shop info
     *
     * @param array $includes
     * @return array|null
     */
    public function getShopInfo(array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->get();

        if (!$shopInfo) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($shopInfo, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $shopInfo->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    /**
     * Update shop info (full replace)
     *
     * @param array $data
     * @param array $includes
     * @return array|null
     */
    public function updateShopInfo(array $data, array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->update($data);

        if (!$shopInfo) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($shopInfo, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $shopInfo->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    /**
     * Update shop info (partial - prevents empty override)
     *
     * @param array $data
     * @param array $includes
     * @return array|null
     */
    public function updateShopInfoPartial(array $data, array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->updatePartial($data);

        if (!$shopInfo) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($shopInfo, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $shopInfo->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    /**
     * Get relationship loading array based on includes
     *
     * @param array $includes
     * @return array
     */
    protected function getRelationshipsWith(array $includes)
    {
        $with = [];
        if (in_array('openHours', $includes)) $with[] = 'openHours';

        return $with;
    }
}
