<?php

namespace Liqrgv\ShopSync\Http\Requests;

use Illuminate\Support\Str;

/**
 * Update Product Request
 *
 * Handles validation for updating existing products with PHP 7.2 compatibility
 */
class UpdateProductRequest extends BaseProductRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $productId = $this->route('product') ?? $this->route('id');

        return [
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
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $productId,
            'seo_description' => 'nullable|string',
            'related_products' => 'nullable|array',
            'related_products.*' => 'integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
        ];
    }

    /**
     * Get the validation attributes for better error messages.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'product name',
            'sku_prefix' => 'SKU prefix',
            'rol_number' => 'ROL number',
            'sku_custom_ref' => 'custom SKU reference',
            'cost_price' => 'cost price',
            'sale_price' => 'sale price',
            'trade_price' => 'trade price',
            'vat_scheme' => 'VAT scheme',
            'seo_keywords' => 'SEO keywords',
            'seo_description' => 'SEO description',
            'related_products' => 'related products',
            'category_id' => 'category',
            'brand_id' => 'brand',
            'location_id' => 'location',
            'supplier_id' => 'supplier',
        ];
    }

    /**
     * Get validated data with formatting applied
     *
     * @return array
     */
    public function getValidatedDataWithFormatting()
    {
        $validated = $this->validated();
        $productId = $this->route('product') ?? $this->route('id');

        // Convert related_products array to JSON for storage
        if (isset($validated['related_products'])) {
            $validated['related_products'] = json_encode($validated['related_products']);
        }

        // Generate slug if name changed and slug not provided
        if (isset($validated['name']) && !isset($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $productId);
        }

        return $validated;
    }

    /**
     * Generate a unique slug for the product update
     *
     * @param string $name
     * @param mixed $productId
     * @return string
     */
    protected function generateUniqueSlug($name, $productId)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;

        // For WL mode only, ensure unique slug
        if (config('products-package.mode', 'wl') === 'wl') {
            $counter = 1;
            while (\Liqrgv\ShopSync\Models\Product::where('slug', $slug)
                ->where('id', '!=', $productId)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        return $slug;
    }
}