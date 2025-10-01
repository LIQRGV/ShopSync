<?php

namespace TheDiamondBox\ShopSync\Services\ProductFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;
use TheDiamondBox\ShopSync\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseProductFetcher implements ProductFetcherInterface
{
    public function getAll(array $filters = [], array $includes = [])
    {
        return $this->buildQuery($filters, $includes)->get();
    }

    public function paginate(int $perPage = 25, array $filters = [], array $includes = [])
    {
        return $this->buildQuery($filters, $includes)->paginate($perPage);
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update($id, array $data)
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product->fresh();
    }

    public function delete($id)
    {
        Product::findOrFail($id)->delete();
    }

    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return $product->fresh();
    }

    public function forceDelete($id)
    {
        Product::withTrashed()->findOrFail($id)->forceDelete();
    }

    public function find($id, $withTrashed = false)
    {
        $query = $withTrashed ? Product::withTrashed() : Product::query();
        return $query->findOrFail($id);
    }

    public function search($query, array $filters = [], array $includes = [])
    {
        $queryBuilder = $this->buildQuery($filters, $includes);

        // Sanitize search query to prevent SQL injection
        $sanitizedQuery = $this->sanitizeSearchQuery($query);

        // Return empty results if query is empty after sanitization
        if (empty($sanitizedQuery)) {
            return collect();
        }

        // Check database driver for search strategy
        if (DB::connection()->getDriverName() === 'mysql') {
            // Use MySQL FULLTEXT search for better performance
            // Additional sanitization for FULLTEXT search boolean mode
            $fullTextQuery = $this->prepareFullTextQuery($sanitizedQuery);
            return $queryBuilder->whereRaw(
                "MATCH(name, description, sku) AGAINST(? IN BOOLEAN MODE)",
                [$fullTextQuery]
            )->get();
        } else {
            // Use escaped LIKE search for other databases
            $escapedQuery = $this->escapeLikeQuery($sanitizedQuery);
            return $queryBuilder->where(function ($q) use ($escapedQuery) {
                $q->where('name', 'like', $escapedQuery . '%')
                  ->orWhere('description', 'like', $escapedQuery . '%')
                  ->orWhere('sku', 'like', $escapedQuery . '%');
            })->get();
        }
    }

    /**
     * Sanitize search query input to prevent SQL injection
     */
    protected function sanitizeSearchQuery(string $query): string
    {
        // Remove null bytes and control characters
        $query = preg_replace('/[\x00-\x1F\x7F]/', '', $query);

        // Trim whitespace
        $query = trim($query);

        // Limit length to prevent excessively long queries
        $query = substr($query, 0, 255);

        // Remove potentially dangerous characters for database queries
        // Allow only alphanumeric, spaces, hyphens, underscores, and basic punctuation
        $query = preg_replace('/[^a-zA-Z0-9\s\-_.,!?@#]/', '', $query);

        return $query;
    }

    /**
     * Prepare query for MySQL FULLTEXT search in boolean mode
     */
    protected function prepareFullTextQuery(string $query): string
    {
        // For FULLTEXT boolean mode, we need to be extra careful with special characters
        // Remove boolean operators and special characters that could cause issues
        $query = preg_replace('/[+\-><()~*"@]/', '', $query);

        // Split into words and add wildcard to each word
        $words = array_filter(explode(' ', $query));
        $words = array_map(function($word) {
            return '+' . trim($word) . '*';
        }, $words);

        return implode(' ', $words);
    }

    /**
     * Escape LIKE query for non-MySQL databases
     */
    protected function escapeLikeQuery(string $query): string
    {
        // Escape LIKE special characters
        $query = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $query);

        return $query;
    }

    public function exportToCsv(array $filters = [])
    {
        // Use chunked processing to prevent memory exhaustion
        $chunkSize = 1000; // Process 1000 records at a time
        $csv = "ID,Name,Description,Price,Stock,SKU,Category,Active,Created At\n";

        // Use output buffering and temporary file for large exports
        $tempHandle = fopen('php://temp/maxmemory:' . (5 * 1024 * 1024), 'r+'); // 5MB memory limit

        if (!$tempHandle) {
            throw new \RuntimeException('Unable to create temporary file for CSV export');
        }

        try {
            // Write header to temp file
            fwrite($tempHandle, $csv);

            // Process data in chunks to prevent memory exhaustion
            $this->buildQuery($filters, [])->chunk($chunkSize, function ($products) use ($tempHandle) {
                foreach ($products as $product) {
                    $row = $this->formatCsvRow($product);
                    fwrite($tempHandle, $row . "\n");
                }

                // Force garbage collection after each chunk
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            });

            // Read the complete CSV content
            rewind($tempHandle);
            $csvContent = stream_get_contents($tempHandle);

            return $csvContent;

        } finally {
            // Always close the temporary file handle
            fclose($tempHandle);
        }
    }

    /**
     * Format a single product row for CSV export
     */
    protected function formatCsvRow($product): string
    {
        return implode(',', [
            $product->id,
            '"' . str_replace('"', '""', $product->name ?? '') . '"',
            '"' . str_replace('"', '""', $product->description ?? '') . '"',
            $product->price ?? 0,
            $product->stock ?? 0,
            '"' . str_replace('"', '""', $product->sku ?? '') . '"',
            '"' . str_replace('"', '""', $product->category ?? '') . '"',
            $product->is_active ? 'Yes' : 'No',
            $product->created_at ? $product->created_at->toDateTimeString() : '',
        ]);
    }

    public function importFromCsv($csvContent)
    {
        $rows = array_map('str_getcsv', explode("\n", $csvContent));
        $header = array_shift($rows);

        if (empty($header)) {
            return ['imported' => 0, 'errors' => ['Invalid CSV format: No header row found']];
        }

        $imported = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if (count($row) < 2 || empty(trim($row[0]))) {
                    continue; // Skip empty rows
                }

                try {
                    $data = array_combine($header, $row);

                    if (!$data || !isset($data['Name']) || empty(trim($data['Name']))) {
                        $errors[] = "Row " . ($index + 2) . ": Product name is required";
                        continue;
                    }

                    $productData = [
                        'name' => trim($data['Name']),
                        'description' => isset($data['Description']) ? trim($data['Description']) : null,
                        'price' => isset($data['Price']) ? (float)$data['Price'] : 0,
                        'stock' => isset($data['Stock']) ? (int)$data['Stock'] : 0,
                        'category' => isset($data['Category']) ? trim($data['Category']) : null,
                        'is_active' => !isset($data['Active']) || trim($data['Active']) === 'Yes',
                    ];

                    // Handle SKU separately for update or create logic
                    $sku = isset($data['SKU']) ? trim($data['SKU']) : null;
                    if (!empty($sku)) {
                        $productData['sku'] = $sku;
                    }

                    // Use updateOrCreate with SKU if provided, otherwise just create
                    if (!empty($sku)) {
                        Product::updateOrCreate(
                            ['sku' => $sku],
                            $productData
                        );
                    } else {
                        Product::create($productData);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CSV import failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Build query with filters and eager loading
     */
    protected function buildQuery(array $filters = [], array $includes = [])
    {
        $query = Product::query();

        // Add eager loading for relationships
        if (!empty($includes)) {
            $with = $this->mapIncludesToRelationships($includes);
            if (!empty($with)) {
                $query->with($with);
            }
        }

        // Include trashed if requested
        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        }

        // Only trashed
        if (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        // Filter by category
        if (isset($filters['category']) && !empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filter by active status
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool)$filters['is_active']);
        }

        // Price range
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Stock range
        if (isset($filters['min_stock']) && is_numeric($filters['min_stock'])) {
            $query->where('stock', '>=', $filters['min_stock']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['id', 'name', 'price', 'stock', 'category', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc');
        }

        return $query;
    }

    /**
     * Map include parameters to Eloquent relationships
     */
    protected function mapIncludesToRelationships(array $includes): array
    {
        $relationships = [];

        foreach ($includes as $include) {
            switch ($include) {
                case 'category':
                    $relationships[] = 'category';
                    break;
                case 'brand':
                    $relationships[] = 'brand';
                    break;
                case 'location':
                    $relationships[] = 'location';
                    break;
                case 'supplier':
                    $relationships[] = 'supplier';
                    break;
                case 'attributes':
                    $relationships[] = 'attributes';
                    break;
                case 'productAttributes':
                    $relationships[] = 'productAttributes.attribute';
                    break;
            }
        }

        return $relationships;
    }
}