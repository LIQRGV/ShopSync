<?php

namespace Liqrgv\ShopSync\Services\Contracts;

interface ProductFetcherInterface
{
    /**
     * Get all products with optional filters
     *
     * @param array $filters
     * @return mixed
     */
    public function getAll(array $filters = []);

    /**
     * Get paginated products
     *
     * @param int $perPage
     * @param array $filters
     * @return mixed
     */
    public function paginate(int $perPage = 15, array $filters = []);

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
     * @return void
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
     * @return void
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
     * @return mixed
     */
    public function search($query, array $filters = []);

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
}