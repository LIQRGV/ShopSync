<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Http\Requests\GetSupplierRequest;
use TheDiamondBox\ShopSync\Services\SupplierService;

class SupplierController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    /**
     * Display a listing of suppliers
     *
     * @param GetSupplierRequest $request
     * @return JsonResponse
     */
    public function index(GetSupplierRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();

            $result = $this->supplierService->getSuppliers($filters, $pagination);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch suppliers', [
                'error' => $e->getMessage(),
                'filters' => $request->getFilters(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch suppliers'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $result = $this->supplierService->findSupplier($id);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('supplier', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch supplier', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch supplier'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
