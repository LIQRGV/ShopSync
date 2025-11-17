<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface CategoryFetcherInterface
{
    /**
     * Get all categories
     *
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getAll(array $filters = [], array $includes = []);

    /**
     * Paginate categories
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|array
     */
    public function paginate(int $perPage = 25, array $filters = [], array $includes = []);

    /**
     * Find category by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Category|array|null
     */
    public function find($id);

    /**
     * Create a new category
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Category|array
     */
    public function create(array $data);

    /**
     * Update category
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Category|array|null
     */
    public function update($id, array $data);

    /**
     * Delete category
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id);
}
