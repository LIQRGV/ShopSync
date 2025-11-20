<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Services\BrandService;

/**
 * Brand Controller
 *
 * Handles HTTP requests for brand operations.
 * Provides dropdown options for AG Grid editors.
 */
class BrandController extends Controller
{
    protected $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    /**
     * Get all active brands
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $result = $this->brandService->getAllBrands();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch brands', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch brands'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
