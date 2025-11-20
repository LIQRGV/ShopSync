<?php

namespace TheDiamondBox\ShopSync\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use TheDiamondBox\ShopSync\Models\Product;
use TheDiamondBox\ShopSync\Models\Category;
use TheDiamondBox\ShopSync\Models\Brand;
use TheDiamondBox\ShopSync\Models\Attribute;
use TheDiamondBox\ShopSync\Services\ProductFetchers\ProductFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;
use TheDiamondBox\ShopSync\Transformers\ProductJsonApiTransformer;
use TheDiamondBox\ShopSync\Helpers\JsonApiIncludeParser;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Product Service
 *
 * This service provides high-level operations for working with
 * products and their relationships.
 */
class ProductService
{
    /***
     * @var ProductFetcherInterface
     */
    protected $productFetcher;
    protected $transformer;

    public function __construct(ProductJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->productFetcher = ProductFetcherFactory::makeFromConfig($request);

        // Always create transformer with productFetcher, even if one was injected
        // This ensures transformer has access to productFetcher for getAllEnabledAttributes()
        if ($transformer && !$transformer->hasProductFetcher()) {
            // If transformer was injected but doesn't have productFetcher, set it
            $transformer->setProductFetcher($this->productFetcher);
            $this->transformer = $transformer;
        } else {
            // Create new transformer with productFetcher
            $this->transformer = new ProductJsonApiTransformer($this->productFetcher);
        }
    }

    /**
     * Get product statistics
     *
     * @return array
     */
    public function getProductStatistics(): array
    {
        // For statistics, we need to check the mode and handle accordingly
        $mode = config('products-package.mode', 'wl');

        if ($mode === 'wl') {
            // Direct database access for WL mode
            return [
                'total_products' => Product::count(),
                'active_products' => Product::active()->count(),
                'products_on_sale' => Product::whereNotNull('sale_price')->count(),
                'categories_count' => Category::active()->count(),
                'brands_count' => Brand::active()->count(),
                'average_price' => Product::active()->avg('price'),
                'total_inventory_value' => Product::active()->sum(DB::raw('price * 1')), // Assuming quantity of 1
            ];
        } else {
            // For WTM mode, get all products and calculate statistics from them
            $allProducts = $this->productFetcher->getAll(['active' => true]);
            $totalProducts = $this->productFetcher->getAll([]);

            return [
                'total_products' => $totalProducts->count(),
                'active_products' => $allProducts->count(),
                'products_on_sale' => $allProducts->whereNotNull('sale_price')->count(),
                'categories_count' => 0, // Would need separate API endpoints for categories
                'brands_count' => 0, // Would need separate API endpoints for brands
                'average_price' => $allProducts->avg('price'),
                'total_inventory_value' => $allProducts->sum('price'),
            ];
        }
    }

    /**
     * Validate includes and return errors if any
     *
     * @param array $includes
     * @return array
     */
    public function validateIncludes(array $includes)
    {
        return $this->transformer->validateIncludes($includes);
    }

    /**
     * Get products with pagination and includes
     *
     * @param array $filters
     * @param array $includes
     * @param array $pagination
     * @return array
     */
    public function getProductsWithPagination(array $filters, array $includes, array $pagination)
    {
        if ($pagination['paginate']) {
            $products = $this->productFetcher->paginate($pagination['per_page'], $filters, $includes);

            // Transform to JSON API format
            $result = $this->transformer->transformProducts($products->items(), $includes);

            // Add pagination meta
            $result['meta'] = [
                'pagination' => [
                    'count' => $products->count(),
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'total_pages' => $products->lastPage(),
                    'from' => $products->firstItem() ?: 0,
                    'to' => $products->lastItem() ?: 0
                ]
            ];

            $result['links'] = [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl()
            ];
        } else {
            $products = $this->productFetcher->getAll($filters, $includes);

            $result = $this->transformer->transformProducts($products, $includes);
        }

        return $result;
    }

    /**
     * Get relationship loading array based on includes
     *
     * @param array $includes
     * @return array
     */
    protected function getRelationshipsWith(array $includes)
    {
        $with = [];
        if (in_array('category', $includes)) $with[] = 'category';
        if (in_array('brand', $includes)) $with[] = 'brand';
        if (in_array('location', $includes)) $with[] = 'location';
        if (in_array('supplier', $includes)) $with[] = 'supplier';
        if (in_array('attributes', $includes)) $with[] = 'attributes';
        if (in_array('productAttributes', $includes)) $with[] = 'productAttributes.attribute';

        return $with;
    }

    /**
     * Create a new product
     *
     * @param array $data
     * @param array $includes
     * @return array
     */
    public function createProduct(array $data, array $includes = [])
    {
        $product = $this->productFetcher->create($data);

        if (!$product) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($product, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $product->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformProduct($product, $includes);
    }

    /**
     * Find a product by ID with includes
     *
     * @param mixed $id
     * @param array $includes
     * @param bool $withTrashed
     * @return array|null
     */
    public function findProduct($id, array $includes = [], $withTrashed = false)
    {
        $product = $this->productFetcher->find($id, $withTrashed);

        if (!$product) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($product, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $product->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformProduct($product, $includes);
    }

    /**
     * Update a product
     *
     * @param mixed $id
     * @param array $data
     * @param array $includes
     * @return array|null
     */
    public function updateProduct($id, array $data, array $includes = [])
    {
        $product = $this->productFetcher->update($id, $data);

        if (!$product) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($product, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $product->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformProduct($product, $includes);
    }

    /**
     * Update a product attribute value
     *
     * @param mixed $productId
     * @param mixed $attributeId
     * @param string $value
     * @param array $includes
     * @return array|null
     */
    public function updateProductAttribute($productId, $attributeId, $value, array $includes = [])
    {

        // Check mode based on ProductFetcher type (NOT product model type)
        // ApiProductFetcher = WTM mode (proxy only)
        // DatabaseProductFetcher = WL mode (direct database)
        $isWlMode = !($this->productFetcher instanceof \TheDiamondBox\ShopSync\Services\ProductFetchers\ApiProductFetcher);

        if ($isWlMode) {
            // WL mode: Need to fetch product for database operations
            $product = $this->productFetcher->find($productId);

            if (!$product) {
                \Log::error('Product not found', ['product_id' => $productId]);
                return null;
            }

            // WL mode: Direct database update via Eloquent

            // If value is empty, detach the attribute (remove from product)
            // Otherwise, sync the attribute with the new value
            if (empty($value) && $value !== '0') {
                $product->attributes()->detach($attributeId);
            } else {
                $product->attributes()->syncWithoutDetaching([
                    $attributeId => ['value' => $value]
                ]);
            }

            // Reload product from database with fresh attributes
            // Use fresh() to get clean state, then load attributes with pivot and options
            $product = $product->fresh();

            // IMPORTANT: Set attributes relation to empty Collection FIRST to prevent lazy loading
            // Then load the actual data - this prevents any intermediate queries
            $product->setRelation('attributes', new \Illuminate\Database\Eloquent\Collection());

            $product->load(['attributes' => function ($query) {
                $query->where('enabled_on_dropship', true)
                      ->with('inputTypeValues:id,attribute_id,value,sortby')
                      ->orderBy('sortby')
                      ->orderBy('name');
            }]);


            // CRITICAL: Manually fire the 'updated' event to trigger ProductObserver and SSE broadcast
            // touch() only updates timestamp but doesn't fire model events
            // This must be AFTER reload so the broadcasted product includes updated attributes
            // Use event() helper for compatibility instead of fireModelEvent()
            event('eloquent.updated: ' . get_class($product), $product);

            // Load additional relationships if needed for response (WL mode only)
            if (!empty($includes) && method_exists($product, 'load')) {
                $with = $this->getRelationshipsWith($includes);
                if (!empty($with)) {
                    // Remove 'attributes' from with since already loaded
                    $with = array_diff($with, ['attributes']);
                    if (!empty($with)) {
                        $product->load($with);
                    }
                }
            }

            // Transform to JSON API format
            return $this->transformer->transformProduct($product, $includes);
        } else {
            // WTM mode: Proxy attribute update to client shop API
            // Send attribute update in format expected by ProductController
            // Controller looks for attribute_id and value at root level
            $updateData = [
                'attribute_id' => (string) $attributeId,
                'value' => (string) $value
            ];

            // Use update() which returns Product model
            $product = $this->productFetcher->update($productId, $updateData);

            if (!$product) {
                \Log::error('Failed to update attribute via WTM API', [
                    'product_id' => $productId,
                    'attribute_id' => $attributeId
                ]);
                return null;
            }

            // Transform to JSON API format (consistent with WL mode)
            return $this->transformer->transformProduct($product, $includes);
        }
    }

    /**
     * Delete a product
     *
     * @param mixed $id
     * @return bool
     */
    public function deleteProduct($id)
    {
        return $this->productFetcher->delete($id);
    }

    /**
     * Restore a product
     *
     * @param mixed $id
     * @param array $includes
     * @return array|null
     */
    public function restoreProduct($id, array $includes = [])
    {
        $product = $this->productFetcher->restore($id);

        if (!$product) {
            return null;
        }

        // Load relationships if needed for response (WL mode only)
        if (!empty($includes) && method_exists($product, 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $product->load($with);
            }
        }

        // Transform to JSON API format
        return $this->transformer->transformProduct($product, $includes);
    }

    /**
     * Force delete a product
     *
     * @param mixed $id
     * @return bool
     */
    public function forceDeleteProduct($id)
    {
        return $this->productFetcher->forceDelete($id);
    }

    /**
     * Search products with pagination and includes
     *
     * @param string $searchTerm
     * @param array $filters
     * @param array $includes
     * @param array $pagination
     * @return array
     */
    public function searchProductsWithPagination($searchTerm, array $filters, array $includes, array $pagination)
    {
        $products = $this->productFetcher->search($searchTerm, $filters, $includes);

        // Handle pagination
        if ($pagination['paginate']) {
            $perPage = $pagination['per_page'];
            $currentPage = $pagination['page'];
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = $products->slice($offset, $perPage);
            $total = $products->count();

            // Transform to JSON API format
            $result = $this->transformer->transformProducts($paginatedItems, $includes);

            // Add pagination meta and search meta
            $result['meta'] = [
                'search' => [
                    'query' => $searchTerm,
                    'total_results' => $total
                ],
                'pagination' => [
                    'count' => $paginatedItems->count(),
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => $offset + $paginatedItems->count()
                ]
            ];

            $baseUrl = request()->url();
            $queryParams = request()->except(['page']);
            $lastPage = (int) ceil($total / $perPage);

            $result['links'] = [
                'first' => $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => 1])),
                'last' => $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $lastPage])),
                'prev' => $currentPage > 1 ? $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) : null,
                'next' => $currentPage < $lastPage ? $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) : null
            ];
        } else {
            // Transform to JSON API format
            $result = $this->transformer->transformProducts($products, $includes);

            // Add search meta
            $result['meta'] = [
                'search' => [
                    'query' => $searchTerm,
                    'total_results' => $products->count()
                ]
            ];
        }

        return $result;
    }

    /**
     * Export products to CSV
     *
     * @param array $filters
     * @return string
     */
    public function exportProducts(array $filters)
    {
        return $this->productFetcher->exportToCsv($filters);
    }

    /**
     * Import products from CSV
     *
     * @param string $csvContent
     * @return array
     */
    public function importProducts($csvContent)
    {
        return $this->productFetcher->importFromCsv($csvContent);
    }

    /**
     * Get product status with statistics
     *
     * @return array
     */
    public function getProductStatus()
    {
        $statistics = $this->getProductStatistics();

        return [
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
    }

    /**
     * Upload product image
     * WTM mode: proxy file to WL via API
     * WL mode: save directly to storage
     *
     * @param mixed $id Product ID
     * @param \Illuminate\Http\UploadedFile $file Uploaded image file
     * @return array|null
     */
    public function uploadProductImage($id, $file)
    {
        // Use fetcher to handle upload
        // DatabaseProductFetcher: save directly to storage (WL mode)
        // ApiProductFetcher: proxy to WL via multipart HTTP request (WTM mode)
        $product = $this->productFetcher->uploadProductImage($id, $file);

        if (!$product) {
            return null;
        }

        // Transform to JSON API format without includes (keep response simple)
        return $this->transformer->transformProduct($product, []);
    }
}