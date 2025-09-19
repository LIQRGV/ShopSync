<?php

namespace Liqrgv\ShopSync\Services;

use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use Liqrgv\ShopSync\Services\ProductFetchers\ProductFetcherFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    protected ProductFetcherInterface $productFetcher;

    public function __construct(ProductFetcherInterface $productFetcher = null)
    {
        $this->productFetcher = $productFetcher ?? ProductFetcherFactory::makeFromConfig();
    }

    /**
     * Get all products with filters
     */
    public function getAll(array $filters = [])
    {
        $validatedFilters = $this->validateFilters($filters);
        return $this->productFetcher->getAll($validatedFilters);
    }

    /**
     * Get paginated products
     */
    public function paginate(int $perPage = null, array $filters = [])
    {
        $perPage = $perPage ?? config('products-package.per_page', 15);

        // Validate per page limit
        $perPage = max(1, min($perPage, 100)); // Limit between 1 and 100

        $validatedFilters = $this->validateFilters($filters);
        return $this->productFetcher->paginate($perPage, $validatedFilters);
    }

    /**
     * Create a new product
     */
    public function create(array $data)
    {
        $validatedData = $this->validateProductData($data);

        Log::info('Creating new product', ['data' => $validatedData]);

        return $this->productFetcher->create($validatedData);
    }

    /**
     * Update an existing product
     */
    public function update($id, array $data)
    {
        $validatedData = $this->validateProductData($data, $id);

        Log::info('Updating product', ['id' => $id, 'data' => $validatedData]);

        return $this->productFetcher->update($id, $validatedData);
    }

    /**
     * Delete a product (soft delete)
     */
    public function delete($id)
    {
        Log::info('Soft deleting product', ['id' => $id]);

        $this->productFetcher->delete($id);
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id)
    {
        Log::info('Restoring product', ['id' => $id]);

        return $this->productFetcher->restore($id);
    }

    /**
     * Permanently delete a product
     */
    public function forceDelete($id)
    {
        Log::warning('Force deleting product', ['id' => $id]);

        $this->productFetcher->forceDelete($id);
    }

    /**
     * Find a single product by ID
     */
    public function find($id, bool $withTrashed = false)
    {
        return $this->productFetcher->find($id, $withTrashed);
    }

    /**
     * Search products
     */
    public function search(string $query, array $filters = [])
    {
        if (empty(trim($query))) {
            return $this->getAll($filters);
        }

        $validatedFilters = $this->validateFilters($filters);

        Log::debug('Searching products', ['query' => $query, 'filters' => $validatedFilters]);

        return $this->productFetcher->search(trim($query), $validatedFilters);
    }

    /**
     * Export products to CSV
     */
    public function exportToCsv(array $filters = []): string
    {
        $validatedFilters = $this->validateFilters($filters);

        Log::info('Exporting products to CSV', ['filters' => $validatedFilters]);

        return $this->productFetcher->exportToCsv($validatedFilters);
    }

    /**
     * Import products from CSV
     */
    public function importFromCsv(string $csvContent): array
    {
        if (empty(trim($csvContent))) {
            throw new \InvalidArgumentException('CSV content cannot be empty');
        }

        Log::info('Importing products from CSV', ['content_length' => strlen($csvContent)]);

        $result = $this->productFetcher->importFromCsv($csvContent);

        Log::info('CSV import completed', [
            'imported' => $result['imported'] ?? 0,
            'errors_count' => count($result['errors'] ?? [])
        ]);

        return $result;
    }

    /**
     * Get current mode and configuration status
     */
    public function getStatus(): array
    {
        return [
            'fetcher_class' => get_class($this->productFetcher),
            'config_status' => ProductFetcherFactory::getConfigStatus(),
            'mode' => config('products-package.mode', 'wl')
        ];
    }

    /**
     * Validate product data
     */
    protected function validateProductData(array $data, $id = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ];

        // Add unique rule for SKU if provided and not updating
        if (isset($data['sku']) && !empty($data['sku'])) {
            $rules['sku'] .= $id ? "|unique:products,sku,{$id}" : '|unique:products,sku';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate filter parameters
     */
    protected function validateFilters(array $filters): array
    {
        $validator = Validator::make($filters, [
            'category' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'with_trashed' => 'nullable|boolean',
            'only_trashed' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:id,name,price,stock,category,created_at,updated_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid filters provided', ['errors' => $validator->errors()->toArray()]);
            // Return empty array for invalid filters instead of throwing exception
            return [];
        }

        $validated = $validator->validated();

        // Additional validation logic
        if (isset($validated['min_price']) && isset($validated['max_price'])
            && $validated['min_price'] > $validated['max_price']) {
            Log::warning('min_price is greater than max_price, ignoring max_price');
            unset($validated['max_price']);
        }

        return $validated;
    }
}