<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use TheDiamondBox\ShopSync\Models\Supplier;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search', '');
            $limit = min((int) $request->query('limit', 100), 500); // Max 500

            $query = Supplier::query();

            // Apply search filter if provided
            // Search across company_name, first_name, and last_name
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'LIKE', "%{$search}%")
                      ->orWhere('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%");
                });
            }

            // Order by company_name, then first_name and limit results
            $suppliers = $query->orderBy('company_name')
                              ->orderBy('first_name')
                              ->limit($limit)
                              ->get();

            return response()->json([
                'data' => $suppliers
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch suppliers', [
                'error' => $e->getMessage(),
                'search' => $request->query('search'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch suppliers',
                'message' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified supplier
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($id);

            return response()->json([
                'data' => $supplier
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch supplier', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'error' => 'Supplier not found'
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
