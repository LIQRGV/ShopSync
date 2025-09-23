<?php

namespace Liqrgv\ShopSync\Services;

use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Models\Category;
use Liqrgv\ShopSync\Models\Brand;
use Liqrgv\ShopSync\Models\Attribute;
use Liqrgv\ShopSync\Services\ProductFetchers\ProductFetcherFactory;
use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use Liqrgv\ShopSync\Transformers\ProductJsonApiTransformer;
use Liqrgv\ShopSync\Helpers\JsonApiIncludeParser;
use Liqrgv\ShopSync\Helpers\JsonApiErrorResponse;
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
    protected $productFetcher;
    protected $transformer;

    public function __construct(ProductJsonApiTransformer $transformer = null)
    {
        $this->productFetcher = ProductFetcherFactory::makeFromConfig();
        $this->transformer = $transformer ?? new ProductJsonApiTransformer();
    }
    /**
     * Get products with all relationships loaded
     *
     * @param array $filters
     * @return Collection
     */
    public function getProductsWithRelationships(array $filters = []): Collection
    {
        $query = Product::with([
            'category',
            'brand',
            'location',
            'supplier',
            'attributes'
        ]);

        // Apply filters
        if (isset($filters['active']) && $filters['active']) {
            $query->active();
        }

        if (isset($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        if (isset($filters['brand_id'])) {
            $query->byBrand($filters['brand_id']);
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->priceRange($filters['min_price'] ?? null, $filters['max_price'] ?? null);
        }

        if (isset($filters['sell_status'])) {
            $query->bySellStatus($filters['sell_status']);
        }

        return $query->get();
    }

    /**
     * Get product hierarchy data for display
     *
     * @param int $productId
     * @return array
     */
    public function getProductHierarchy(int $productId): array
    {
        $product = Product::with([
            'category.ancestors',
            'brand',
            'location',
            'supplier',
            'productAttributes.attribute'
        ])->findOrFail($productId);

        return [
            'product' => $product,
            'category_breadcrumb' => $this->getCategoryBreadcrumb($product->category),
            'attributes' => $this->formatProductAttributes($product->productAttributes),
            'related_products' => $this->getRelatedProducts($product),
            'pricing' => $this->getProductPricing($product),
        ];
    }

    /**
     * Get category breadcrumb trail
     *
     * @param Category|null $category
     * @return array
     */
    protected function getCategoryBreadcrumb(?Category $category): array
    {
        if (!$category) {
            return [];
        }

        $breadcrumb = [];

        // Add ancestors
        foreach ($category->ancestors() as $ancestor) {
            $breadcrumb[] = [
                'id' => $ancestor->id,
                'name' => $ancestor->name,
                'slug' => $ancestor->slug,
            ];
        }

        // Add current category
        $breadcrumb[] = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];

        return $breadcrumb;
    }

    /**
     * Format product attributes for display
     *
     * @param Collection $productAttributes
     * @return array
     */
    protected function formatProductAttributes(Collection $productAttributes): array
    {
        return $productAttributes->map(function ($productAttribute) {
            return [
                'name' => $productAttribute->attribute_name,
                'code' => $productAttribute->attribute_code,
                'value' => $productAttribute->value,
                'formatted_value' => $productAttribute->formatted_value,
                'type' => $productAttribute->attribute->type,
                'is_filterable' => $productAttribute->isFilterable(),
                'is_searchable' => $productAttribute->isSearchable(),
            ];
        })->groupBy('code')->toArray();
    }

    /**
     * Get related products
     *
     * @param Product $product
     * @return Collection
     */
    protected function getRelatedProducts(Product $product): Collection
    {
        return $product->related_products_collection ?? collect();
    }

    /**
     * Get comprehensive pricing information
     *
     * @param Product $product
     * @return array
     */
    protected function getProductPricing(Product $product): array
    {
        return [
            'cost_price' => $product->cost_price,
            'regular_price' => $product->price,
            'sale_price' => $product->sale_price,
            'trade_price' => $product->trade_price,
            'effective_price' => $product->effective_price,
            'formatted_price' => $product->formatted_price,
            'formatted_sale_price' => $product->formatted_sale_price,
            'is_on_sale' => $product->isOnSale(),
            'vat_scheme' => $product->vat_scheme,
        ];
    }

    /**
     * Search products by various criteria
     *
     * @param string $searchTerm
     * @param array $filters
     * @return Collection
     */
    public function searchProducts(string $searchTerm, array $filters = []): Collection
    {
        return $this->productFetcher->search($searchTerm, $filters);
    }

    /**
     * Get products by attribute filters
     *
     * @param array $attributeFilters ['attribute_code' => 'value']
     * @param array $additionalFilters
     * @return Collection
     */
    public function getProductsByAttributes(array $attributeFilters, array $additionalFilters = []): Collection
    {
        $query = Product::with(['category', 'brand', 'attributes']);

        // Apply attribute filters
        foreach ($attributeFilters as $attributeCode => $value) {
            $query->whereHas('attributes', function ($q) use ($attributeCode, $value) {
                $q->where('code', $attributeCode)
                  ->where('is_filterable', true)
                  ->wherePivot('value', $value);
            });
        }

        // Apply additional filters
        if (isset($additionalFilters['category_id'])) {
            $query->byCategory($additionalFilters['category_id']);
        }

        if (isset($additionalFilters['brand_id'])) {
            $query->byBrand($additionalFilters['brand_id']);
        }

        if (isset($additionalFilters['min_price']) || isset($additionalFilters['max_price'])) {
            $query->priceRange(
                $additionalFilters['min_price'] ?? null,
                $additionalFilters['max_price'] ?? null
            );
        }

        $query->active();

        return $query->get();
    }

    /**
     * Get filterable attributes for a category
     *
     * @param int|null $categoryId
     * @return Collection
     */
    public function getFilterableAttributes(?int $categoryId = null): Collection
    {
        $attributeQuery = Attribute::active()->filterable()->ordered();

        if ($categoryId) {
            // Get attributes that are actually used by products in this category
            $attributeQuery->whereHas('products', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        return $attributeQuery->get();
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
     * Sync related products for a product
     *
     * @param int $productId
     * @param array $relatedProductIds
     * @return bool
     */
    public function syncRelatedProducts(int $productId, array $relatedProductIds): bool
    {
        $product = Product::findOrFail($productId);

        // Validate that all related product IDs exist
        $validIds = Product::whereIn('id', $relatedProductIds)->pluck('id')->toArray();

        $product->update([
            'related_products' => $validIds
        ]);

        return true;
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
            $products = $this->productFetcher->paginate($pagination['per_page'], $filters);

            // Load relationships if needed for WL mode
            if ($products->count() > 0 && method_exists($products->first(), 'load')) {
                $with = $this->getRelationshipsWith($includes);
                if (!empty($with)) {
                    $products->getCollection()->load($with);
                }
            }

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
            $products = $this->productFetcher->getAll($filters);

            // Load relationships if needed for WL mode
            if ($products->count() > 0 && method_exists($products->first(), 'load')) {
                $with = $this->getRelationshipsWith($includes);
                if (!empty($with)) {
                    $products->load($with);
                }
            }

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
        $products = $this->productFetcher->search($searchTerm, $filters);

        // Load relationships if needed for WL mode
        if ($products->count() > 0 && method_exists($products->first(), 'load')) {
            $with = $this->getRelationshipsWith($includes);
            if (!empty($with)) {
                $products->load($with);
            }
        }

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
                    'total_pages' => (int) ceil($total / $perPage)
                ]
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
}