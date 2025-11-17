<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use TheDiamondBox\ShopSync\Models\Brand;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    /**
     * Display a listing of brands
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search', '');
            $limit = min((int) $request->query('limit', 100), 500); // Max 500

            $query = Brand::query();

            // Apply search filter if provided
            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            // Order by name and limit results
            $brands = $query->orderBy('name')
                           ->limit($limit)
                           ->get();

            return response()->json([
                'data' => $brands
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch brands', [
                'error' => $e->getMessage(),
                'search' => $request->query('search'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch brands',
                'message' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $brand = Brand::findOrFail($id);

            return response()->json([
                'data' => $brand
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch brand', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'error' => 'Brand not found'
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
