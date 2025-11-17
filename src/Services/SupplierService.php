<?php

namespace TheDiamondBox\ShopSync\Services;

use Illuminate\Http\Request;
use TheDiamondBox\ShopSync\Services\SupplierFetchers\SupplierFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\SupplierFetcherInterface;
use TheDiamondBox\ShopSync\Transformers\SupplierJsonApiTransformer;

class SupplierService
{
    protected $supplierFetcher;
    protected $transformer;

    public function __construct(SupplierJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->supplierFetcher = SupplierFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new SupplierJsonApiTransformer();
    }

    /**
     * Get suppliers with optional pagination
     *
     * @param array $filters
     * @param array $pagination
     * @return array
     */
    public function getSuppliers(array $filters = [], array $pagination = [])
    {
        $paginate = $pagination['paginate'] ?? false;
        $perPage = $pagination['per_page'] ?? 100;

        if ($paginate) {
            $result = $this->supplierFetcher->paginate($perPage, $filters, []);

            // Handle both Eloquent pagination and API array response
            if (is_array($result)) {
                // WTM mode - already formatted
                return $result;
            }

            // WL mode - Eloquent paginator
            return $this->transformer->transformSuppliers(
                $result->items(),
                $result->total(),
                $result->perPage(),
                $result->currentPage()
            );
        }

        $suppliers = $this->supplierFetcher->getAll($filters, []);

        return $this->transformer->transformSuppliers($suppliers);
    }

    /**
     * Find supplier by ID
     *
     * @param mixed $id
     * @return array|null
     */
    public function findSupplier($id)
    {
        $supplier = $this->supplierFetcher->find($id);

        if (!$supplier) {
            return null;
        }

        return $this->transformer->transformSupplier($supplier);
    }

    /**
     * Create new supplier
     *
     * @param array $data
     * @return array
     */
    public function createSupplier(array $data)
    {
        $supplier = $this->supplierFetcher->create($data);

        return $this->transformer->transformSupplier($supplier);
    }

    /**
     * Update supplier
     *
     * @param mixed $id
     * @param array $data
     * @return array|null
     */
    public function updateSupplier($id, array $data)
    {
        $supplier = $this->supplierFetcher->update($id, $data);

        if (!$supplier) {
            return null;
        }

        return $this->transformer->transformSupplier($supplier);
    }

    /**
     * Delete supplier
     *
     * @param mixed $id
     * @return bool
     */
    public function deleteSupplier($id)
    {
        return $this->supplierFetcher->delete($id);
    }
}
