<?php

namespace TheDiamondBox\ShopSync\Services;

use Illuminate\Http\Request;
use TheDiamondBox\ShopSync\Services\BrandFetchers\BrandFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\BrandFetcherInterface;
use TheDiamondBox\ShopSync\Transformers\BrandJsonApiTransformer;

class BrandService
{
    protected $brandFetcher;
    protected $transformer;

    public function __construct(BrandJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->brandFetcher = BrandFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new BrandJsonApiTransformer();
    }

    /**
     * Get brands with optional pagination
     *
     * @param array $filters
     * @param array $pagination
     * @return array
     */
    public function getBrands(array $filters = [], array $pagination = [])
    {
        $paginate = $pagination['paginate'] ?? false;
        $perPage = $pagination['per_page'] ?? 100;

        if ($paginate) {
            $result = $this->brandFetcher->paginate($perPage, $filters, []);

            // Handle both Eloquent pagination and API array response
            if (is_array($result)) {
                // WTM mode - already formatted
                return $result;
            }

            // WL mode - Eloquent paginator
            return $this->transformer->transformBrands(
                $result->items(),
                $result->total(),
                $result->perPage(),
                $result->currentPage()
            );
        }

        $brands = $this->brandFetcher->getAll($filters, []);

        return $this->transformer->transformBrands($brands);
    }

    /**
     * Find brand by ID
     *
     * @param mixed $id
     * @return array|null
     */
    public function findBrand($id)
    {
        $brand = $this->brandFetcher->find($id);

        if (!$brand) {
            return null;
        }

        return $this->transformer->transformBrand($brand);
    }

    /**
     * Create new brand
     *
     * @param array $data
     * @return array
     */
    public function createBrand(array $data)
    {
        $brand = $this->brandFetcher->create($data);

        return $this->transformer->transformBrand($brand);
    }

    /**
     * Update brand
     *
     * @param mixed $id
     * @param array $data
     * @return array|null
     */
    public function updateBrand($id, array $data)
    {
        $brand = $this->brandFetcher->update($id, $data);

        if (!$brand) {
            return null;
        }

        return $this->transformer->transformBrand($brand);
    }

    /**
     * Delete brand
     *
     * @param mixed $id
     * @return bool
     */
    public function deleteBrand($id)
    {
        return $this->brandFetcher->delete($id);
    }
}
