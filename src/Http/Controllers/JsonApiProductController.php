<?php

namespace Liqrgv\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Transformers\ProductJsonApiTransformer;
use Liqrgv\ShopSync\Helpers\JsonApiIncludeParser;
use Liqrgv\ShopSync\Helpers\JsonApiErrorResponse;

/**
 * JSON API Product Controller
 *
 * Example controller demonstrating how to use the JSON API transformer system
 * with Product models following the JSON API specification.
 */
class JsonApiProductController extends Controller
{
    protected ProductJsonApiTransformer $transformer;

    public function __construct(ProductJsonApiTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Display a listing of products in JSON API format
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Parse include parameters
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate format first
            $formatErrors = JsonApiIncludeParser::getFormatErrors($includes);
            if (!empty($formatErrors)) {
                return $this->errorResponse(JsonApiErrorResponse::multiple($formatErrors), 400);
            }

            // Validate includes against allowed includes
            $validationErrors = $this->transformer->validateIncludes($includes);
            if (!empty($validationErrors)) {
                return $this->errorResponse(['errors' => $validationErrors], 400);
            }

            // Build query with eager loading for performance
            $query = Product::query();

            // Add eager loading for requested includes
            if (!empty($includes)) {
                $eagerLoads = JsonApiIncludeParser::toEagerLoadFormat($includes);
                $query->with($eagerLoads);
            }

            // Apply filters
            $this->applyFilters($query, $request);

            // Apply sorting
            $this->applySorting($query, $request);

            // Paginate results
            $perPage = min((int) $request->get('page.size', 15), 100); // Max 100 items per page
            $page = (int) $request->get('page.number', 1);

            $products = $query->paginate($perPage, ['*'], 'page[number]', $page);

            // Transform to JSON API format
            $response = $this->transformer->transformProducts($products, $includes);

            return new JsonResponse($response);

        } catch (\Exception $e) {
            $errorResponse = JsonApiErrorResponse::fromException($e, config('app.debug', false));
            return $this->errorResponse($errorResponse, 500);
        }
    }

    /**
     * Display the specified product in JSON API format
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Parse include parameters
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate format first
            $formatErrors = JsonApiIncludeParser::getFormatErrors($includes);
            if (!empty($formatErrors)) {
                return $this->errorResponse(JsonApiErrorResponse::multiple($formatErrors), 400);
            }

            // Validate includes against allowed includes
            $validationErrors = $this->transformer->validateIncludes($includes);
            if (!empty($validationErrors)) {
                return $this->errorResponse(['errors' => $validationErrors], 400);
            }

            // Build query with eager loading
            $query = Product::query();

            if (!empty($includes)) {
                $eagerLoads = JsonApiIncludeParser::toEagerLoadFormat($includes);
                $query->with($eagerLoads);
            }

            // Find the product
            $product = $query->find($id);

            if (!$product) {
                return $this->errorResponse(
                    JsonApiErrorResponse::notFound('product', $id),
                    404
                );
            }

            // Transform to JSON API format
            $response = $this->transformer->transformProduct($product, $includes);

            return new JsonResponse($response);

        } catch (\Exception $e) {
            $errorResponse = JsonApiErrorResponse::fromException($e, config('app.debug', false));
            return $this->errorResponse($errorResponse, 500);
        }
    }

    /**
     * Apply filters to the query based on request parameters
     */
    protected function applyFilters($query, Request $request): void
    {
        // Filter by category
        if ($request->has('filter.category')) {
            $categoryId = $request->get('filter.category');
            if (is_numeric($categoryId)) {
                $query->where('category_id', $categoryId);
            }
        }

        // Filter by brand
        if ($request->has('filter.brand')) {
            $brandId = $request->get('filter.brand');
            if (is_numeric($brandId)) {
                $query->where('brand_id', $brandId);
            }
        }

        // Filter by status
        if ($request->has('filter.status')) {
            $status = $request->get('filter.status');
            $query->where('status', $status);
        }

        // Filter by sell status
        if ($request->has('filter.sell_status')) {
            $sellStatus = $request->get('filter.sell_status');
            $query->where('sell_status', $sellStatus);
        }

        // Filter by price range
        if ($request->has('filter.price_min')) {
            $priceMin = $request->get('filter.price_min');
            if (is_numeric($priceMin)) {
                $query->where('price', '>=', $priceMin);
            }
        }

        if ($request->has('filter.price_max')) {
            $priceMax = $request->get('filter.price_max');
            if (is_numeric($priceMax)) {
                $query->where('price', '<=', $priceMax);
            }
        }

        // Search by name or description
        if ($request->has('filter.search')) {
            $searchTerm = $request->get('filter.search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('sku_custom_ref', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by location
        if ($request->has('filter.location')) {
            $locationId = $request->get('filter.location');
            if (is_numeric($locationId)) {
                $query->where('location_id', $locationId);
            }
        }

        // Filter by supplier
        if ($request->has('filter.supplier')) {
            $supplierId = $request->get('filter.supplier');
            if (is_numeric($supplierId)) {
                $query->where('supplier_id', $supplierId);
            }
        }

        // Filter products on sale
        if ($request->has('filter.on_sale') && $request->get('filter.on_sale') === 'true') {
            $query->whereNotNull('sale_price')
                  ->whereColumn('sale_price', '<', 'price');
        }
    }

    /**
     * Apply sorting to the query based on request parameters
     */
    protected function applySorting($query, Request $request): void
    {
        $sortParam = $request->get('sort', 'id');
        $allowedSorts = [
            'id', 'name', 'price', 'sale_price', 'cost_price', 'trade_price',
            'created_at', 'updated_at', 'purchase_date', 'status', 'sell_status'
        ];

        // Parse sort parameter (can be comma-separated)
        $sorts = explode(',', $sortParam);

        foreach ($sorts as $sort) {
            $direction = 'asc';
            $field = $sort;

            // Check for descending order (prefixed with -)
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $field = substr($sort, 1);
            }

            // Validate sort field
            if (in_array($field, $allowedSorts)) {
                $query->orderBy($field, $direction);
            }
        }
    }

    /**
     * Get available includes for the API documentation
     */
    public function getAvailableIncludes(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'type' => 'meta',
                'attributes' => [
                    'available_includes' => $this->transformer->getAvailableIncludes(),
                    'include_examples' => [
                        'single' => '?include=category',
                        'multiple' => '?include=category,brand,location',
                        'nested' => '?include=category.parent',
                        'array_format' => '?include[]=category&include[]=brand'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Create error response with proper JSON API format
     */
    protected function errorResponse(array $errorData, int $statusCode): JsonResponse
    {
        return new JsonResponse($errorData, $statusCode);
    }
}