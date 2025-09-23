<?php

namespace Liqrgv\ShopSync\Services;

use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Models\Category;
use Liqrgv\ShopSync\Models\Brand;
use Liqrgv\ShopSync\Models\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product Service
 *
 * This service provides high-level operations for working with
 * products and their relationships.
 */
class ProductService
{
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
        $query = Product::with(['category', 'brand']);

        // Basic text search
        $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhere('seo_keywords', 'LIKE', "%{$searchTerm}%")
              ->orWhere('slug', 'LIKE', "%{$searchTerm}%")
              ->orWhereRaw("CONCAT(sku_prefix, rol_number) LIKE ?", ["%{$searchTerm}%"]);
        });

        // Search in related models
        $query->orWhereHas('category', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        });

        $query->orWhereHas('brand', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        });

        // Search in attributes
        $query->orWhereHas('attributes', function ($q) use ($searchTerm) {
            $q->where('is_searchable', true)
              ->where(function ($subQ) use ($searchTerm) {
                  $subQ->where('name', 'LIKE', "%{$searchTerm}%")
                       ->orWherePivot('value', 'LIKE', "%{$searchTerm}%");
              });
        });

        // Apply additional filters
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        $query->active();

        return $query->distinct()->get();
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
        return [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'products_on_sale' => Product::whereNotNull('sale_price')->count(),
            'categories_count' => Category::active()->count(),
            'brands_count' => Brand::active()->count(),
            'average_price' => Product::active()->avg('price'),
            'total_inventory_value' => Product::active()->sum(DB::raw('price * 1')), // Assuming quantity of 1
        ];
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
}