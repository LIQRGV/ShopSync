<?php

namespace TheDiamondBox\ShopSync\Services\SupplierFetchers;

use TheDiamondBox\ShopSync\Models\Supplier;
use TheDiamondBox\ShopSync\Services\Contracts\SupplierFetcherInterface;

class DatabaseSupplierFetcher implements SupplierFetcherInterface
{
    /**
     * Get all suppliers
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
     * Paginate suppliers
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
     * Find supplier by ID
     *
     * @param mixed $id
     * @return \TheDiamondBox\ShopSync\Models\Supplier|null
     */
    public function find($id)
    {
        return Supplier::find($id);
    }

    /**
     * Create a new supplier
     *
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Supplier
     */
    public function create(array $data)
    {
        return Supplier::create($data);
    }

    /**
     * Update supplier
     *
     * @param mixed $id
     * @param array $data
     * @return \TheDiamondBox\ShopSync\Models\Supplier|null
     */
    public function update($id, array $data)
    {
        $supplier = $this->find($id);

        if ($supplier) {
            $supplier->update($data);
            return $supplier->fresh();
        }

        return null;
    }

    /**
     * Delete supplier
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        $supplier = $this->find($id);

        if ($supplier) {
            return $supplier->delete();
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
        $query = Supplier::query();

        // Apply search filter across multiple fields
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%");
            });
        }

        // Apply limit filter
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        // Order by company_name, then first_name
        $query->orderBy('company_name')->orderBy('first_name');

        return $query;
    }
}
