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
        $mode = config('products-package.mode', 'wl');
        $isWlMode = $mode === 'wl';

        $rules = [
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
            'seo_description' => 'nullable|string',
            'related_products' => 'nullable|array',
        ];

        // Only validate slug uniqueness in WL mode
        if ($isWlMode) {
            $rules['slug'] = 'nullable|string|max:255|unique:products,slug';
        } else {
            $rules['slug'] = 'nullable|string|max:255';
        }

        // Only validate related products existence in WL mode
        if ($isWlMode) {
            $rules['related_products.*'] = 'integer|exists:products,id';
        } else {
            $rules['related_products.*'] = 'integer';
        }

        // Category validation - only check database in WL mode
        if ($isWlMode) {
            $rules['category_id'] = [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Allow null/empty
                    if (empty($value)) {
                        return;
                    }

                    // Parse category IDs from different formats
                    if (is_array($value)) {
                        // JSON:API to-many relationship: [1, 2, 3]
                        $categoryIds = $value;
                    } elseif (is_string($value) && strpos($value, ',') !== false) {
                        // Comma-separated string: "1,2,3"
                        $categoryIds = array_filter(array_map('trim', explode(',', $value)));
                    } elseif (is_numeric($value) || is_string($value)) {
                        // Single ID: 1 or "1"
                        $categoryIds = [$value];
                    } else {
                        $fail('The category format is invalid.');
                        return;
                    }

                    // Validate each ID is numeric
                    foreach ($categoryIds as $categoryId) {
                        if (!is_numeric($categoryId)) {
                            $fail('The category must contain only valid integers.');
                            return;
                        }
                    }

                    // Validate all IDs exist in database
                    $categoryModel = config('products-package.models.category', \App\Category::class);
                    $existingIds = $categoryModel::whereIn('id', $categoryIds)->pluck('id')->toArray();
                    $missingIds = array_diff(
                        array_map('strval', $categoryIds),
                        array_map('strval', $existingIds)
                    );

                    if (!empty($missingIds)) {
                        $fail('The following category IDs do not exist: ' . implode(', ', $missingIds));
                    }
                }
            ];
        } else {
            // WTM mode - just validate format, WL will validate existence
            $rules['category_id'] = 'nullable';
        }

        // Foreign key validations - only check database in WL mode
        if ($isWlMode) {
            $rules['brand_id'] = 'nullable|integer|exists:brands,id';
            $rules['location_id'] = 'nullable|integer|exists:locations,id';
            $rules['supplier_id'] = 'nullable|integer|exists:suppliers,id';
        } else {
            // WTM mode - just validate data type, WL will validate existence
            $rules['brand_id'] = 'nullable|integer';
            $rules['location_id'] = 'nullable|integer';
            $rules['supplier_id'] = 'nullable|integer';
        }

        return array_merge(parent::rules(), $rules);
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
        $mode = config('products-package.mode', 'wl');

        // Convert related_products array to JSON for storage
        if (isset($validated['related_products'])) {
            $validated['related_products'] = json_encode($validated['related_products']);
        }

        // Separate category_id array into parent categories and subcategories
        // Only do this in WL mode where we have direct database access
        if ($mode === 'wl' && isset($validated['category_id']) && is_array($validated['category_id'])) {
            $categoryIds = $validated['category_id'];

            // Get category model to check parent_id
            $categoryModel = config('products-package.models.category', \App\Category::class);
            $categories = $categoryModel::whereIn('id', $categoryIds)->get();

            // Separate parent categories (parent_id = 0 or NULL) from subcategories
            $parentIds = [];
            $subCategoryIds = [];

            foreach ($categories as $category) {
                if (empty($category->parent_id) || $category->parent_id == 0) {
                    // This is a parent category
                    $parentIds[] = $category->id;
                } else {
                    // This is a subcategory
                    $subCategoryIds[] = $category->id;
                }
            }

            // Store comma-separated values
            $validated['category_id'] = !empty($parentIds) ? implode(',', $parentIds) : null;
            $validated['sub_category_id'] = !empty($subCategoryIds) ? implode(',', $subCategoryIds) : null;
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