<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Http\Requests\GetBrandRequest;
use TheDiamondBox\ShopSync\Services\BrandService;

class BrandController extends Controller
{
    protected $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    /**
     * Display a listing of brands
     *
     * @param GetBrandRequest $request
     * @return JsonResponse
     */
    public function index(GetBrandRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();

            $result = $this->brandService->getBrands($filters, $pagination);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch brands', [
                'error' => $e->getMessage(),
                'filters' => $request->getFilters(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch brands'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified brand
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->brandService->findBrand($id);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('brand', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch brand', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch brand'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
