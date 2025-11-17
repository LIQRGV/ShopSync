<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Http\Requests\GetCategoryRequest;
use TheDiamondBox\ShopSync\Services\CategoryService;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of categories
     *
     * @param GetCategoryRequest $request
     * @return JsonResponse
     */
    public function index(GetCategoryRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();

            $result = $this->categoryService->getCategories($filters, $pagination);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch categories', [
                'error' => $e->getMessage(),
                'filters' => $request->getFilters(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch categories'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified category
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->categoryService->findCategory($id);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('category', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch category', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch category'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
