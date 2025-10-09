<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use TheDiamondBox\ShopSync\Services\ShopInfoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * ShopInfoController
 *
 * Handle shop info API requests (WL mode)
 * Proxied by WTM mode
 */
class ShopInfoController extends Controller
{
    /**
     * Get shop info
     *
     * GET /shop-info
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $shopInfoService = new ShopInfoService($request);
        $shopInfo = $shopInfoService->getShopInfo();

        if (!$shopInfo) {
            return response()->json([
                'message' => 'Shop info not found'
            ], 404);
        }

        return response()->json($shopInfo);
    }

    /**
     * Update shop info (full replace)
     *
     * PUT /shop-info
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $shopInfoService = new ShopInfoService($request);
        $data = $request->all();

        $shopInfo = $shopInfoService->updateShopInfo($data);

        if (!$shopInfo) {
            return response()->json([
                'message' => 'Failed to update shop info'
            ], 500);
        }

        return response()->json($shopInfo);
    }

    /**
     * Update shop info (partial - only non-empty values)
     * Prevents empty data from overriding existing values
     *
     * PATCH /shop-info
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePartial(Request $request): JsonResponse
    {
        $shopInfoService = new ShopInfoService($request);
        $data = $request->all();

        $shopInfo = $shopInfoService->updateShopInfoPartial($data);

        if (!$shopInfo) {
            return response()->json([
                'message' => 'Failed to update shop info'
            ], 500);
        }

        return response()->json($shopInfo);
    }
}
