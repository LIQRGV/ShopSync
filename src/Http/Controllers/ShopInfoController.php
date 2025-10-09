<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use TheDiamondBox\ShopSync\Services\ShopInfoService;
use TheDiamondBox\ShopSync\Http\Requests\GetShopInfoRequest;
use TheDiamondBox\ShopSync\Http\Requests\UpdateShopInfoRequest;
use TheDiamondBox\ShopSync\Http\Requests\PatchShopInfoRequest;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ShopInfoController
 *
 * Handle shop info API requests (WL mode)
 * Proxied by WTM mode
 * Supports JSON API format for requests and responses
 */
class ShopInfoController extends Controller
{
    protected $shopInfoService;

    public function __construct(Request $request)
    {
        $this->shopInfoService = new ShopInfoService(null, $request);
    }

    /**
     * Get shop info with JSON API support
     *
     * GET /shop-info
     *
     * @param GetShopInfoRequest $request
     * @return JsonResponse
     */
    public function show(GetShopInfoRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->shopInfoService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $shopInfo = $this->shopInfoService->getShopInfo($includes);

            if (!$shopInfo) {
                $error = JsonApiErrorResponse::notFound('shop-info', '1');
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($shopInfo);

        } catch (\Exception $e) {
            Log::error('Failed to fetch shop info', [
                'error' => $e->getMessage(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch shop info'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update shop info (full replace) with JSON API support
     *
     * PUT /shop-info
     *
     * @param UpdateShopInfoRequest $request
     * @return JsonResponse
     */
    public function update(UpdateShopInfoRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->shopInfoService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $data = $request->validated();
            $shopInfo = $this->shopInfoService->updateShopInfo($data, $includes);

            if (!$shopInfo) {
                $error = JsonApiErrorResponse::internalError('Failed to update shop info');
                return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json($shopInfo);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update shop info', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to update shop info'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update shop info (partial - only non-empty values) with JSON API support
     * Prevents empty data from overriding existing values
     *
     * PATCH /shop-info
     *
     * @param PatchShopInfoRequest $request
     * @return JsonResponse
     */
    public function updatePartial(PatchShopInfoRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->shopInfoService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $data = $request->validated();
            $shopInfo = $this->shopInfoService->updateShopInfoPartial($data, $includes);

            if (!$shopInfo) {
                $error = JsonApiErrorResponse::internalError('Failed to update shop info');
                return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json($shopInfo);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update shop info (partial)', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to update shop info'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
