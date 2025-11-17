<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface SupplierFetcherInterface
{
    /**
     * Get all suppliers
     *
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getAll(array $filters = [], array $includes = []);

    /**
     * Paginate suppliers
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|array
     */
    public function paginate(int $perPage = 25, array $filters = [], array $includes = []);

    /**
     * Find supplier by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Supplier|array|null
     */
    public function find($id);

    /**
     * Create a new supplier
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Supplier|array
     */
    public function create(array $data);

    /**
     * Update supplier
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Supplier|array|null
     */
    public function update($id, array $data);

    /**
     * Delete supplier
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id);
}
