<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Services\AttributeService;

/**
 * Attribute Controller
 *
 * Handles HTTP requests for attribute operations.
 * Provides enabled attributes for AG Grid column rendering.
 */
class AttributeController extends Controller
{
    protected $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    /**
     * Get all enabled attributes
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $attributes = $this->attributeService->getAllAttributes();

            return response()->json([
                'data' => $attributes,
                'meta' => [
                    'count' => count($attributes)
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch attributes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch attributes'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
