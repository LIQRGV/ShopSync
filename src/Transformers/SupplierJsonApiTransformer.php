<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Support\Collection;

class SupplierJsonApiTransformer
{
    /**
     * Transform single supplier to JSON:API format
     *
     * @param mixed $supplier
     * @return array
     */
    public function transformSupplier($supplier): array
    {
        if (is_array($supplier)) {
            // Already in JSON:API format from WTM mode
            return $supplier;
        }

        return [
            'data' => $this->formatSupplierResource($supplier)
        ];
    }

    /**
     * Transform collection of suppliers to JSON:API format
     *
     * @param mixed $suppliers
     * @param int|null $total
     * @param int|null $perPage
     * @param int|null $currentPage
     * @return array
     */
    public function transformSuppliers($suppliers, $total = null, $perPage = null, $currentPage = null): array
    {
        if (is_array($suppliers)) {
            // Already in JSON:API format from WTM mode
            return $suppliers;
        }

        $data = [];

        if ($suppliers instanceof Collection || is_array($suppliers)) {
            foreach ($suppliers as $supplier) {
                $data[] = $this->formatSupplierResource($supplier);
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
     * Format supplier as JSON:API resource
     *
     * @param mixed $supplier
     * @return array
     */
    protected function formatSupplierResource($supplier): array
    {
        $attributes = is_array($supplier) ? $supplier : $supplier->toArray();

        // Remove id from attributes (it belongs in root level)
        $id = $attributes['id'] ?? null;
        unset($attributes['id']);

        return [
            'type' => 'suppliers',
            'id' => (string) $id,
            'attributes' => $attributes
        ];
    }
}
