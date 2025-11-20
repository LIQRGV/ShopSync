<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface ProductFetcherInterface
{
    /**
     * Get all products with optional filters and includes
     *
     * @param array $filters
     * @param array $includes
     * @return mixed
     */
    public function getAll(array $filters = [], array $includes = []);

    /**
     * Get paginated products
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return mixed
     */
    public function paginate(int $perPage = 15, array $filters = [], array $includes = []);

    /**
     * Create a new product
     *
     * @param array $data
     * @return mixed
     */
    public function create(array $data);

    /**
     * Update an existing product
     *
     * @param int|string $id
     * @param array $data
     * @return mixed
     */
    public function update($id, array $data);

    /**
     * Delete a product (soft delete)
     *
     * @param int|string $id
     * @return bool True if successful, false otherwise
     */
    public function delete($id);

    /**
     * Restore a soft-deleted product
     *
     * @param int|string $id
     * @return mixed
     */
    public function restore($id);

    /**
     * Permanently delete a product
     *
     * @param int|string $id
     * @return bool True if successful, false otherwise
     */
    public function forceDelete($id);

    /**
     * Find a single product by ID
     *
     * @param int|string $id
     * @param bool $withTrashed
     * @return mixed
     */
    public function find($id, $withTrashed = false);

    /**
     * Search products
     *
     * @param string $query
     * @param array $filters
     * @param array $includes
     * @return mixed
     */
    public function search($query, array $filters = [], array $includes = []);

    /**
     * Export products to CSV
     *
     * @param array $filters
     * @return string CSV content
     */
    public function exportToCsv(array $filters = []);

    /**
     * Import products from CSV
     *
     * @param string $csvContent
     * @return array Import results
     */
    public function importFromCsv($csvContent);

    /**
     * Upload product image
     * WTM: proxy to WL
     * WL: save directly to storage
     *
     * @param int|string $id
     * @param \Illuminate\Http\UploadedFile $file
     * @return mixed Updated product or null
     */
    public function uploadProductImage($id, $file);

    /**
     * Get original included data from last API call
     *
     * In WTM mode: Returns the complete included data from the WL API response,
     * preserving ALL enabled attributes (even those not used by products on current page).
     * This is necessary because the transformer only includes attributes that are actively
     * used, but the grid needs all enabled attributes to show all columns.
     *
     * In WL mode: Returns empty array (not needed for direct database queries).
     *
     * @return array The original included data from API response, or empty array
     */
    public function getOriginalIncludedData(): array;
}