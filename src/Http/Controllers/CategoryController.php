<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use TheDiamondBox\ShopSync\Models\Category;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search', '');
            $limit = min((int) $request->query('limit', 100), 500); // Max 500

            $query = Category::query();

            // Apply search filter if provided
            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            // Order by name and limit results
            $categories = $query->orderBy('name')
                               ->limit($limit)
                               ->get();

            return response()->json([
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch categories', [
                'error' => $e->getMessage(),
                'search' => $request->query('search'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch categories',
                'message' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $category = Category::findOrFail($id);

            return response()->json([
                'data' => $category
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch category', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'error' => 'Category not found'
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
