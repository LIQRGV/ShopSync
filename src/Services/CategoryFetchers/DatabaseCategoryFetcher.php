<?php

namespace TheDiamondBox\ShopSync\Services\CategoryFetchers;

use TheDiamondBox\ShopSync\Models\Category;
use TheDiamondBox\ShopSync\Services\Contracts\CategoryFetcherInterface;

class DatabaseCategoryFetcher implements CategoryFetcherInterface
{
    /**
     * Get all categories
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
     * Paginate categories
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
     * Find category by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Category|null
     */
    public function find($id)
    {
        return Category::find($id);
    }

    /**
     * Create a new category
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Category
     */
    public function create(array $data)
    {
        return Category::create($data);
    }

    /**
     * Update category
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Category|null
     */
    public function update($id, array $data)
    {
        $category = $this->find($id);

        if ($category) {
            $category->update($data);
            return $category->fresh();
        }

        return null;
    }

    /**
     * Delete category
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        $category = $this->find($id);

        if ($category) {
            return $category->delete();
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
        $query = Category::query();

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
