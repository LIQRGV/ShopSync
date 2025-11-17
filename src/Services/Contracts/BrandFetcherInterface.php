<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

interface BrandFetcherInterface
{
    /**
     * Get all brands
     *
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getAll(array $filters = [], array $includes = []);

    /**
     * Paginate brands
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|array
     */
    public function paginate(int $perPage = 25, array $filters = [], array $includes = []);

    /**
     * Find brand by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Brand|array|null
     */
    public function find($id);

    /**
     * Create a new brand
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Brand|array
     */
    public function create(array $data);

    /**
     * Update brand
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Brand|array|null
     */
    public function update($id, array $data);

    /**
     * Delete brand
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id);
}
