<?php

namespace TheDiamondBox\ShopSync\Services;

use Illuminate\Http\Request;
use TheDiamondBox\ShopSync\Services\CategoryFetchers\CategoryFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\CategoryFetcherInterface;
use TheDiamondBox\ShopSync\Transformers\CategoryJsonApiTransformer;

class CategoryService
{
    protected $categoryFetcher;
    protected $transformer;

    public function __construct(CategoryJsonApiTransformer $transformer = null, Request $request = null)
    {
        $this->categoryFetcher = CategoryFetcherFactory::makeFromConfig($request);
        $this->transformer = $transformer ?? new CategoryJsonApiTransformer();
    }

    /**
     * Get categories with optional pagination
     *
     * @param array $filters
     * @param array $pagination
     * @return array
     */
    public function getCategories(array $filters = [], array $pagination = [])
    {
        $paginate = $pagination['paginate'] ?? false;
        $perPage = $pagination['per_page'] ?? 100;

        if ($paginate) {
            $result = $this->categoryFetcher->paginate($perPage, $filters, []);

            // Handle both Eloquent pagination and API array response
            if (is_array($result)) {
                // WTM mode - already formatted
                return $result;
            }

            // WL mode - Eloquent paginator
            return $this->transformer->transformCategories(
                $result->items(),
                $result->total(),
                $result->perPage(),
                $result->currentPage()
            );
        }

        $categories = $this->categoryFetcher->getAll($filters, []);

        return $this->transformer->transformCategories($categories);
    }

    /**
     * Find category by ID
     *
     * @param mixed $id
     * @return array|null
     */
    public function findCategory($id)
    {
        $category = $this->categoryFetcher->find($id);

        if (!$category) {
            return null;
        }

        return $this->transformer->transformCategory($category);
    }

    /**
     * Create new category
     *
     * @param array $data
     * @return array
     */
    public function createCategory(array $data)
    {
        $category = $this->categoryFetcher->create($data);

        return $this->transformer->transformCategory($category);
    }

    /**
     * Update category
     *
     * @param mixed $id
     * @param array $data
     * @return array|null
     */
    public function updateCategory($id, array $data)
    {
        $category = $this->categoryFetcher->update($id, $data);

        if (!$category) {
            return null;
        }

        return $this->transformer->transformCategory($category);
    }

    /**
     * Delete category
     *
     * @param mixed $id
     * @return bool
     */
    public function deleteCategory($id)
    {
        return $this->categoryFetcher->delete($id);
    }
}
