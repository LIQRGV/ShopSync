<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

/**
 * Search Product Request
 *
 * Handles validation for product search with PHP 7.2 compatibility
 */
class SearchProductRequest extends BaseProductRequest
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
        return [
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
            'q' => 'search query',
            'category_id' => 'category',
            'brand_id' => 'brand',
            'location_id' => 'location',
            'supplier_id' => 'supplier',
            'sell_status' => 'sell status',
            'min_price' => 'minimum price',
            'max_price' => 'maximum price',
            'with_trashed' => 'include deleted',
            'sort_by' => 'sort field',
            'sort_order' => 'sort order',
            'per_page' => 'items per page'
        ];
    }

    /**
     * Get search filters from validated data
     *
     * @return array
     */
    public function getSearchFilters()
    {
        $validated = $this->validated();

        return [
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'min_price' => $validated['min_price'] ?? null,
            'max_price' => $validated['max_price'] ?? null,
            'sell_status' => $validated['sell_status'] ?? null,
            'with_trashed' => $validated['with_trashed'] ?? false,
            'sort_by' => $validated['sort_by'] ?? 'name',
            'sort_order' => $validated['sort_order'] ?? 'asc',
        ];
    }

    /**
     * Get the search term
     *
     * @return string
     */
    public function getSearchTerm()
    {
        return $this->validated()['q'];
    }

    /**
     * Get pagination settings
     *
     * @return array
     */
    public function getPaginationSettings()
    {
        $validated = $this->validated();

        return [
            'per_page' => $validated['per_page'] ?? config('shopsync.per_page', 15),
            'page' => $this->get('page', 1)
        ];
    }
}