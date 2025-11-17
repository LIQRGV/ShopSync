<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Support\Collection;

class BrandJsonApiTransformer
{
    /**
     * Transform single brand to JSON:API format
     *
     * @param mixed $brand
     * @return array
     */
    public function transformBrand($brand): array
    {
        if (is_array($brand)) {
            // Already in JSON:API format from WTM mode
            return $brand;
        }

        return [
            'data' => $this->formatBrandResource($brand)
        ];
    }

    /**
     * Transform collection of brands to JSON:API format
     *
     * @param mixed $brands
     * @param int|null $total
     * @param int|null $perPage
     * @param int|null $currentPage
     * @return array
     */
    public function transformBrands($brands, $total = null, $perPage = null, $currentPage = null): array
    {
        if (is_array($brands)) {
            // Already in JSON:API format from WTM mode
            return $brands;
        }

        $data = [];

        if ($brands instanceof Collection || is_array($brands)) {
            foreach ($brands as $brand) {
                $data[] = $this->formatBrandResource($brand);
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
     * Format brand as JSON:API resource
     *
     * @param mixed $brand
     * @return array
     */
    protected function formatBrandResource($brand): array
    {
        $attributes = is_array($brand) ? $brand : $brand->toArray();

        // Remove id from attributes (it belongs in root level)
        $id = $attributes['id'] ?? null;
        unset($attributes['id']);

        return [
            'type' => 'brands',
            'id' => (string) $id,
            'attributes' => $attributes
        ];
    }
}
