<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use Illuminate\Support\Str;

/**
 * Store Product Request
 *
 * Handles validation for creating new products with PHP 7.2 compatibility
 */
class StoreProductRequest extends BaseProductRequest
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
        return array_merge(parent::rules(), [
            'name' => 'required|string|max:255',
            'sku_prefix' => 'nullable|string|max:50',
            'rol_number' => 'nullable|string|max:100',
            'sku_custom_ref' => 'nullable|string|max:255',
            'status' => 'nullable|numeric|in:1,2,3,4,5,6,7',
            'sell_status' => 'nullable|numeric|in:1,2,3,4',
            'purchase_date' => 'nullable|date',
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'trade_price' => 'nullable|numeric|min:0',
            'vat_scheme' => 'nullable|numeric|in:0,1,2',
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
     * Get validated data with defaults and formatting applied
     *
     * @return array
     */
    public function getValidatedDataWithDefaults()
    {
        $validated = $this->validated();

        // Convert related_products array to JSON for storage
        if (isset($validated['related_products'])) {
            $validated['related_products'] = json_encode($validated['related_products']);
        }

        // Set default values
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['sell_status'] = $validated['sell_status'] ?? 'available';

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        }

        return $validated;
    }

    /**
     * Generate a unique slug for the product
     *
     * @param string $name
     * @return string
     */
    protected function generateUniqueSlug($name)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // For WL mode, check for uniqueness
        if (config('products-package.mode', 'wl') === 'wl') {
            while (\TheDiamondBox\ShopSync\Models\Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        return $slug;
    }
}