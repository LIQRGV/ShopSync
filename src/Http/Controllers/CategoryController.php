<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Services\CategoryService;

/**
 * Category Controller
 *
 * Handles HTTP requests for category operations.
 * Provides dropdown options for AG Grid editors.
 */
class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Get all active categories
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $result = $this->categoryService->getAllCategories();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch categories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch categories'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
