<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Support\Collection;

class CategoryJsonApiTransformer
{
    /**
     * Transform single category to JSON:API format
     *
     * @param mixed $category
     * @return array
     */
    public function transformCategory($category): array
    {
        if (is_array($category)) {
            // Already in JSON:API format from WTM mode
            return $category;
        }

        return [
            'data' => $this->formatCategoryResource($category)
        ];
    }

    /**
     * Transform collection of categories to JSON:API format
     *
     * @param mixed $categories
     * @param int|null $total
     * @param int|null $perPage
     * @param int|null $currentPage
     * @return array
     */
    public function transformCategories($categories, $total = null, $perPage = null, $currentPage = null): array
    {
        if (is_array($categories)) {
            // Already in JSON:API format from WTM mode
            return $categories;
        }

        $data = [];

        if ($categories instanceof Collection || is_array($categories)) {
            foreach ($categories as $category) {
                $data[] = $this->formatCategoryResource($category);
            }
        }

        $result = ['data' => $data];

        // Add pagination meta if provided
        if ($total !== null) {
            $result['meta'] = [
                'pagination' => [
                    'total' => $total,
                    'count' => count($data),
                    'per_page' => $perPage ?? count($data),
                    'current_page' => $currentPage ?? 1,
                    'total_pages' => $perPage ? (int) ceil($total / $perPage) : 1
                ]
            ];
        }

        return $result;
    }

    /**
     * Format category as JSON:API resource
     *
     * @param mixed $category
     * @return array
     */
    protected function formatCategoryResource($category): array
    {
        $attributes = is_array($category) ? $category : $category->toArray();

        // Remove id from attributes (it belongs in root level)
        $id = $attributes['id'] ?? null;
        unset($attributes['id']);

        return [
            'type' => 'categories',
            'id' => (string) $id,
            'attributes' => $attributes
        ];
    }
}
