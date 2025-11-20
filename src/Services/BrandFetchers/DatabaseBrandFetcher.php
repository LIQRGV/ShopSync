<?php

namespace TheDiamondBox\ShopSync\Services\BrandFetchers;

use TheDiamondBox\ShopSync\Models\Brand;
use TheDiamondBox\ShopSync\Services\Contracts\BrandFetcherInterface;

class DatabaseBrandFetcher implements BrandFetcherInterface
{
    /**
     * Get all brands
     *
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll(array $filters = [], array $includes = [])
    {
        return $this->buildQuery($filters, $includes)->get();
    }

    /**
     * Paginate brands
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 25, array $filters = [], array $includes = [])
    {
        return $this->buildQuery($filters, $includes)->paginate($perPage);
    }

    /**
     * Find brand by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Brand|null
     */
    public function find($id)
    {
        return Brand::find($id);
    }

    /**
     * Create a new brand
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Brand
     */
    public function create(array $data)
    {
        return Brand::create($data);
    }

    /**
     * Update brand
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Brand|null
     */
    public function update($id, array $data)
    {
        $brand = $this->find($id);

        if ($brand) {
            $brand->update($data);
            return $brand->fresh();
        }

        return null;
    }

    /**
     * Delete brand
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        $brand = $this->find($id);

        if ($brand) {
            return $brand->delete();
        }

        return false;
    }

    /**
     * Build query with filters
     *
     * @param array $filters
     * @param array $includes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery(array $filters = [], array $includes = [])
    {
        $query = Brand::query();

        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where('name', 'LIKE', "%{$filters['search']}%");
        }

        // Apply limit filter
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        // Order by name
        $query->orderBy('name');

        return $query;
    }
}
