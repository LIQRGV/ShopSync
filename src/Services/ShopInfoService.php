<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\Fetchers\ShopInfo\ShopInfoFetcherFactory;
use TheDiamondBox\ShopSync\Transformers\ShopInfoJsonApiTransformer;
use Illuminate\Http\Request;

class ShopInfoService
{
    protected $shopInfoFetcher;
    protected $transformer;

    public function __construct(ShopInfoJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->shopInfoFetcher = ShopInfoFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new ShopInfoJsonApiTransformer();
    }

    public function validateIncludes(array $includes)
    {
        return $this->transformer->validateIncludes($includes);
    }

    public function getShopInfo(array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->get();

        if (!$shopInfo) {
            return null;
        }

        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    public function updateShopInfo(array $data, array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->update($data);

        if (!$shopInfo) {
            return null;
        }

        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    public function updateShopInfoPartial(array $data, array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->updatePartial($data);

        if (!$shopInfo) {
            return null;
        }

        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }

    public function uploadShopInfoImage(string $field, $file, array $includes = [])
    {
        $shopInfo = $this->shopInfoFetcher->uploadImage($field, $file);

        if (!$shopInfo) {
            return null;
        }

        return $this->transformer->transformShopInfo($shopInfo, $includes);
    }
}
