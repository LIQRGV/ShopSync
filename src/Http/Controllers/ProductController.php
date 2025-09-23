<?php

namespace Liqrgv\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Liqrgv\ShopSync\Services\ProductService;
use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Transformers\ProductJsonApiTransformer;
use Liqrgv\ShopSync\Helpers\JsonApiIncludeParser;
use Liqrgv\ShopSync\Helpers\JsonApiErrorResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Product Controller - Laravel 12 Compatible with JSON API Support
 *
 * This controller provides JSON API compliant endpoints for Product management including:
 * - Modern response handling with proper return types
 * - JSON API transformation with include parameters
 * - Enhanced validation error handling
 * - Support for category, brand, location, supplier, and attributes relationships
 * - Improved type hints and error responses
 */
class ProductController extends Controller
{
    protected ProductService $productService;
    protected ProductJsonApiTransformer $transformer;

    public function __construct(ProductService $productService, ProductJsonApiTransformer $transformer)
    {
        $this->productService = $productService;
        $this->transformer = $transformer;
    }

    /**
     * Display a listing of products with JSON API support
     *
     * Supports include parameters: category, brand, location, supplier, attributes
     * Example: GET /api/products?include=category,brand&page=1
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Parse include parameters
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            // Build filters for Product
            $filters = $request->only([
                'category_id',
                'brand_id',
                'location_id',
                'supplier_id',
                'active',
                'sell_status',
                'min_price',
                'max_price',
                'with_trashed',
                'only_trashed',
                'sort_by',
                'sort_order',
            ]);

            // Get products using the service
            $query = Product::query();

            // Apply eager loading based on includes
            $with = [];
            if (in_array('category', $includes)) $with[] = 'category';
            if (in_array('brand', $includes)) $with[] = 'brand';
            if (in_array('location', $includes)) $with[] = 'location';
            if (in_array('supplier', $includes)) $with[] = 'supplier';
            if (in_array('attributes', $includes)) $with[] = 'attributes';
            if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

            if (!empty($with)) {
                $query->with($with);
            }

            // Apply filters
            if (isset($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            if (isset($filters['brand_id'])) {
                $query->where('brand_id', $filters['brand_id']);
            }

            if (isset($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }

            if (isset($filters['supplier_id'])) {
                $query->where('supplier_id', $filters['supplier_id']);
            }

            if (isset($filters['sell_status'])) {
                $query->where('sell_status', $filters['sell_status']);
            }

            if (isset($filters['min_price']) || isset($filters['max_price'])) {
                $query->where(function ($q) use ($filters) {
                    if (isset($filters['min_price'])) {
                        $q->where('price', '>=', $filters['min_price']);
                    }
                    if (isset($filters['max_price'])) {
                        $q->where('price', '<=', $filters['max_price']);
                    }
                });
            }

            // Handle trashed records
            if (isset($filters['with_trashed']) && $filters['with_trashed']) {
                $query->withTrashed();
            } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
                $query->onlyTrashed();
            }

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'id';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $allowedSorts = ['id', 'name', 'price', 'cost_price', 'created_at', 'updated_at', 'purchase_date'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Handle pagination
            if ($request->has('page')) {
                $perPage = (int) $request->get('per_page', config('shopsync.per_page', 15));
                $perPage = min($perPage, 100); // Max 100 items per page

                $products = $query->paginate($perPage);

                // Transform to JSON API format
                $result = $this->transformer->transformProducts($products->items(), $includes);

                // Add pagination meta
                $result['meta'] = [
                    'pagination' => [
                        'count' => $products->count(),
                        'current_page' => $products->currentPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'total_pages' => $products->lastPage()
                    ]
                ];
            } else {
                $products = $query->get();
                $result = $this->transformer->transformProducts($products, $includes);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch products', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? [],
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch products'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created Product with JSON API response
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Parse include parameters for response transformation
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            // Validate Product data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'sku_prefix' => 'nullable|string|max:50',
                'rol_number' => 'nullable|string|max:100',
                'sku_custom_ref' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive,draft',
                'sell_status' => 'nullable|string|in:available,sold,reserved,pending',
                'purchase_date' => 'nullable|date',
                'cost_price' => 'nullable|numeric|min:0',
                'price' => 'required|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'trade_price' => 'nullable|numeric|min:0',
                'vat_scheme' => 'nullable|string|max:50',
                'image' => 'nullable|string|max:500',
                'original_image' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'seo_keywords' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:products,slug',
                'seo_description' => 'nullable|string',
                'related_products' => 'nullable|array',
                'related_products.*' => 'integer|exists:products,id',
                'category_id' => 'nullable|integer|exists:categories,id',
                'brand_id' => 'nullable|integer|exists:brands,id',
                'location_id' => 'nullable|integer|exists:locations,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
            ]);

            // Convert related_products array to JSON for storage
            if (isset($validated['related_products'])) {
                $validated['related_products'] = json_encode($validated['related_products']);
            }

            // Set default values
            $validated['status'] = $validated['status'] ?? 'active';
            $validated['sell_status'] = $validated['sell_status'] ?? 'available';

            // Generate slug if not provided
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);

                // Ensure unique slug
                $baseSlug = $validated['slug'];
                $counter = 1;
                while (Product::where('slug', $validated['slug'])->exists()) {
                    $validated['slug'] = $baseSlug . '-' . $counter;
                    $counter++;
                }
            }

            // Create the product
            $product = Product::create($validated);

            // Load relationships if needed for response
            if (!empty($includes)) {
                $with = [];
                if (in_array('category', $includes)) $with[] = 'category';
                if (in_array('brand', $includes)) $with[] = 'brand';
                if (in_array('location', $includes)) $with[] = 'location';
                if (in_array('supplier', $includes)) $with[] = 'supplier';
                if (in_array('attributes', $includes)) $with[] = 'attributes';
                if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

                if (!empty($with)) {
                    $product->load($with);
                }
            }

            // Transform to JSON API format
            $result = $this->transformer->transformProduct($product, $includes);

            return response()->json($result, Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to create product', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to create product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified product with JSON API support
     *
     * Supports include parameters: category, brand, location, supplier, attributes
     * Example: GET /api/products/1?include=category,brand,attributes
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Parse include parameters
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            // Build query with eager loading based on includes
            $query = Product::query();

            $with = [];
            if (in_array('category', $includes)) $with[] = 'category';
            if (in_array('brand', $includes)) $with[] = 'brand';
            if (in_array('location', $includes)) $with[] = 'location';
            if (in_array('supplier', $includes)) $with[] = 'supplier';
            if (in_array('attributes', $includes)) $with[] = 'attributes';
            if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

            if (!empty($with)) {
                $query->with($with);
            }

            // Handle trashed records
            $withTrashed = $request->boolean('with_trashed');
            if ($withTrashed) {
                $query->withTrashed();
            }

            $product = $query->findOrFail($id);

            // Transform to JSON API format
            $result = $this->transformer->transformProduct($product, $includes);

            return response()->json($result);

        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified Product with JSON API response
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Parse include parameters for response transformation
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            // Find the product
            $product = Product::findOrFail($id);

            // Validate Product update data
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'sku_prefix' => 'nullable|string|max:50',
                'rol_number' => 'nullable|string|max:100',
                'sku_custom_ref' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive,draft',
                'sell_status' => 'nullable|string|in:available,sold,reserved,pending',
                'purchase_date' => 'nullable|date',
                'cost_price' => 'nullable|numeric|min:0',
                'price' => 'sometimes|required|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'trade_price' => 'nullable|numeric|min:0',
                'vat_scheme' => 'nullable|string|max:50',
                'image' => 'nullable|string|max:500',
                'original_image' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'seo_keywords' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:products,slug,' . $id,
                'seo_description' => 'nullable|string',
                'related_products' => 'nullable|array',
                'related_products.*' => 'integer|exists:products,id',
                'category_id' => 'nullable|integer|exists:categories,id',
                'brand_id' => 'nullable|integer|exists:brands,id',
                'location_id' => 'nullable|integer|exists:locations,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
            ]);

            // Convert related_products array to JSON for storage
            if (isset($validated['related_products'])) {
                $validated['related_products'] = json_encode($validated['related_products']);
            }

            // Generate slug if name changed and slug not provided
            if (isset($validated['name']) && !isset($validated['slug'])) {
                $baseSlug = Str::slug($validated['name']);
                $validated['slug'] = $baseSlug;

                // Ensure unique slug
                $counter = 1;
                while (Product::where('slug', $validated['slug'])
                    ->where('id', '!=', $id)->exists()) {
                    $validated['slug'] = $baseSlug . '-' . $counter;
                    $counter++;
                }
            }

            // Update the product
            $product->update($validated);

            // Load relationships if needed for response
            if (!empty($includes)) {
                $with = [];
                if (in_array('category', $includes)) $with[] = 'category';
                if (in_array('brand', $includes)) $with[] = 'brand';
                if (in_array('location', $includes)) $with[] = 'location';
                if (in_array('supplier', $includes)) $with[] = 'supplier';
                if (in_array('attributes', $includes)) $with[] = 'attributes';
                if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

                if (!empty($with)) {
                    $product->load($with);
                }
            }

            // Transform to JSON API format
            $result = $this->transformer->transformProduct($product, $includes);

            return response()->json($result);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to update product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to update product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified Product (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to delete product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted Product with JSON API response
     */
    public function restore(Request $request, $id): JsonResponse
    {
        try {
            // Parse include parameters for response transformation
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $product = Product::withTrashed()->findOrFail($id);

            if (!$product->trashed()) {
                $error = JsonApiErrorResponse::badRequest('Product is not deleted and cannot be restored.');
                return response()->json($error, Response::HTTP_BAD_REQUEST);
            }

            $product->restore();

            // Load relationships if needed for response
            if (!empty($includes)) {
                $with = [];
                if (in_array('category', $includes)) $with[] = 'category';
                if (in_array('brand', $includes)) $with[] = 'brand';
                if (in_array('location', $includes)) $with[] = 'location';
                if (in_array('supplier', $includes)) $with[] = 'supplier';
                if (in_array('attributes', $includes)) $with[] = 'attributes';
                if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

                if (!empty($with)) {
                    $product->load($with);
                }
            }

            // Transform to JSON API format
            $result = $this->transformer->transformProduct($product, $includes);

            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to restore product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to restore product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Permanently delete the specified Product
     */
    public function forceDelete($id): JsonResponse
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->forceDelete();

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to force delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to permanently delete product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search Products with JSON API support
     *
     * Supports include parameters: category, brand, location, supplier, attributes
     * Example: GET /api/products/search?q=diamond&include=category,brand
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Parse include parameters
            $includes = JsonApiIncludeParser::parseFromRequest($request);

            // Validate includes
            $includeErrors = $this->transformer->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            // Validate search parameters
            $validated = $request->validate([
                'q' => 'required|string|min:1|max:255',
                'category_id' => 'nullable|integer|exists:categories,id',
                'brand_id' => 'nullable|integer|exists:brands,id',
                'location_id' => 'nullable|integer|exists:locations,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'sell_status' => 'nullable|string|in:available,sold,reserved,pending',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'with_trashed' => 'nullable|boolean',
                'sort_by' => 'nullable|string|in:id,name,price,cost_price,created_at,updated_at,purchase_date',
                'sort_order' => 'nullable|string|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $searchTerm = $validated['q'];

            // Use the ProductService search functionality
            $products = $this->productService->searchProducts($searchTerm, [
                'category_id' => $validated['category_id'] ?? null,
                'brand_id' => $validated['brand_id'] ?? null,
                'location_id' => $validated['location_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'min_price' => $validated['min_price'] ?? null,
                'max_price' => $validated['max_price'] ?? null,
            ]);

            // Apply additional filters manually if needed
            $query = $products->toQuery();

            // Apply eager loading based on includes
            $with = [];
            if (in_array('category', $includes)) $with[] = 'category';
            if (in_array('brand', $includes)) $with[] = 'brand';
            if (in_array('location', $includes)) $with[] = 'location';
            if (in_array('supplier', $includes)) $with[] = 'supplier';
            if (in_array('attributes', $includes)) $with[] = 'attributes';
            if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

            if (!empty($with)) {
                $query->with($with);
            }

            // Apply sell status filter
            if (isset($validated['sell_status'])) {
                $query->where('sell_status', $validated['sell_status']);
            }

            // Handle trashed records
            if (isset($validated['with_trashed']) && $validated['with_trashed']) {
                $query->withTrashed();
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Handle pagination
            if ($request->has('page')) {
                $perPage = (int) ($validated['per_page'] ?? config('shopsync.per_page', 15));
                $perPage = min($perPage, 100); // Max 100 items per page

                $paginatedResults = $query->paginate($perPage);

                // Transform to JSON API format
                $result = $this->transformer->transformProducts($paginatedResults->items(), $includes);

                // Add pagination meta and search meta
                $result['meta'] = [
                    'search' => [
                        'query' => $searchTerm,
                        'total_results' => $paginatedResults->total()
                    ],
                    'pagination' => [
                        'count' => $paginatedResults->count(),
                        'current_page' => $paginatedResults->currentPage(),
                        'per_page' => $paginatedResults->perPage(),
                        'total' => $paginatedResults->total(),
                        'total_pages' => $paginatedResults->lastPage()
                    ]
                ];
            } else {
                $searchResults = $query->get();

                // Transform to JSON API format
                $result = $this->transformer->transformProducts($searchResults, $includes);

                // Add search meta
                $result['meta'] = [
                    'search' => [
                        'query' => $searchTerm,
                        'total_results' => $searchResults->count()
                    ]
                ];
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to search products', [
                'error' => $e->getMessage(),
                'query' => $request->get('q'),
                'filters' => $request->except('q'),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Search failed'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->validate([
                'category' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'with_trashed' => 'nullable|boolean',
                'only_trashed' => 'nullable|boolean',
            ]);

            $csv = $this->productService->exportToCsv($filters);

            $filename = 'products-' . date('Y-m-d-H-i-s') . '.csv';

            return response($csv, Response::HTTP_OK)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to export products', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'message' => 'Export failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:2048', // Reduced to 2MB max for security
            ]);

            $file = $validated['file'];

            // Enhanced security validation
            if (!$this->validateUploadedFile($file)) {
                return response()->json([
                    'message' => 'Invalid file format or content detected'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $csvContent = file_get_contents($file->getRealPath());

            if (empty($csvContent)) {
                return response()->json([
                    'message' => 'The uploaded file is empty'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Additional content validation
            if (!$this->validateCsvContent($csvContent)) {
                return response()->json([
                    'message' => 'Invalid CSV content or potentially malicious file detected'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $result = $this->productService->importFromCsv($csvContent);

            return response()->json([
                'message' => 'Import completed',
                'imported' => $result['imported'],
                'errors' => $result['errors'],
                'total_errors' => count($result['errors'])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to import products', [
                'error' => $e->getMessage(),
                'file_info' => $request->hasFile('file') ? [
                    'name' => $request->file('file')->getClientOriginalName(),
                    'size' => $request->file('file')->getSize(),
                    'mime' => $request->file('file')->getMimeType()
                ] : 'No file'
            ]);

            return response()->json([
                'message' => 'Import failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get Product statistics and status with JSON API support
     */
    public function status(): JsonResponse
    {
        try {
            $statistics = $this->productService->getProductStatistics();

            $result = [
                'data' => [
                    'type' => 'product-status',
                    'id' => '1',
                    'attributes' => [
                        'status' => 'healthy',
                        'model' => 'Product',
                        'database_table' => 'products',
                        'statistics' => $statistics,
                        'supported_includes' => $this->transformer->getAvailableIncludes(),
                        'json_api_version' => '1.1',
                        'timestamp' => now()->toISOString()
                    ]
                ]
            ];

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get product status', [
                'error' => $e->getMessage()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to get product status'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate uploaded file for security issues
     */
    protected function validateUploadedFile($file): bool
    {
        // Check if file exists and is readable
        if (!$file || !$file->isValid()) {
            return false;
        }

        // Verify file extension
        $allowedExtensions = ['csv', 'txt'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return false;
        }

        // Content-based MIME type detection for additional security
        $detectedMimeType = mime_content_type($file->getRealPath());
        $allowedMimeTypes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'text/comma-separated-values',
            'application/octet-stream', // Some systems might return this for CSV
        ];

        if (!in_array($detectedMimeType, $allowedMimeTypes)) {
            Log::warning('File upload blocked: Invalid MIME type detected', [
                'uploaded_mime' => $file->getMimeType(),
                'detected_mime' => $detectedMimeType,
                'filename' => $file->getClientOriginalName()
            ]);
            return false;
        }

        // Check file size (additional check beyond validation rule)
        $maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if ($file->getSize() > $maxSize) {
            return false;
        }

        // Check for common malicious file signatures
        $fileContent = file_get_contents($file->getRealPath());
        if ($this->containsMaliciousSignatures($fileContent)) {
            Log::warning('File upload blocked: Malicious signatures detected', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Validate CSV content for potential security issues
     */
    protected function validateCsvContent(string $content): bool
    {
        // Check for extremely long lines that might indicate malicious content
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strlen($line) > 10000) { // 10KB per line max
                return false;
            }
        }

        // Check for excessive number of lines
        if (count($lines) > 50000) { // 50K lines max
            return false;
        }

        // Check for suspicious patterns that might indicate code injection
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/=\s*CALL\s+/i', // Excel formula injection
            '/=\s*CMD\s*\|/i',
            '/=\s*SYSTEM\s*\(/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Log::warning('CSV content blocked: Suspicious pattern detected', [
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        // Validate CSV structure - should be parseable
        try {
            $lines = array_filter(explode("\n", $content));
            if (empty($lines)) {
                return false;
            }

            // Check if first line looks like a header
            $header = str_getcsv($lines[0]);
            if (empty($header) || count($header) < 2) {
                return false;
            }

            // Validate that at least some lines can be parsed as CSV
            $validLines = 0;
            $samplesToCheck = min(10, count($lines) - 1); // Check up to 10 lines after header

            for ($i = 1; $i <= $samplesToCheck; $i++) {
                if (isset($lines[$i])) {
                    $parsed = str_getcsv($lines[$i]);
                    if (is_array($parsed) && !empty($parsed)) {
                        $validLines++;
                    }
                }
            }

            // At least 50% of sampled lines should be valid CSV
            return $validLines >= ($samplesToCheck * 0.5);

        } catch (\Exception $e) {
            Log::warning('CSV validation failed during parsing', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for malicious file signatures
     */
    protected function containsMaliciousSignatures(string $content): bool
    {
        $maliciousSignatures = [
            "\x00", // Null bytes
            "\xFF\xFE", // UTF-16 LE BOM (unusual for CSV)
            "\xFE\xFF", // UTF-16 BE BOM (unusual for CSV)
            "\xFF\xFE\x00\x00", // UTF-32 LE BOM
            "\x00\x00\xFE\xFF", // UTF-32 BE BOM
            "\x7FELF", // ELF executable
            "\x4D\x5A", // Windows PE executable
            "\x50\x4B", // ZIP file signature (potential zip bomb)
            "%PDF", // PDF file
            "GIF8", // GIF image
            "\xFF\xD8\xFF", // JPEG image
            "\x89PNG", // PNG image
            "<?xml", // XML content
            "<!DOCTYPE", // HTML/XML DOCTYPE
        ];

        $contentStart = substr($content, 0, 256); // Check first 256 bytes
        foreach ($maliciousSignatures as $signature) {
            if (strpos($contentStart, $signature) !== false) {
                return true;
            }
        }

        return false;
    }
}