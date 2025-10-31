<?php

namespace TheDiamondBox\ShopSync\Services\ProductFetchers;

use TheDiamondBox\ShopSync\Observers\ProductObserver;
use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;
use TheDiamondBox\ShopSync\Models\Product;
use TheDiamondBox\ShopSync\Constants\ProductImportMappings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseProductFetcher implements ProductFetcherInterface
{

    /***
     * @var ProductObserver
     */
    protected $observer;

    public function __construct(ProductObserver $observer)
    {
        $this->observer = $observer;
    }

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
        // Use withoutGlobalScopes() to bypass any global scopes that might interfere
        $product = Product::withoutGlobalScopes()->findOrFail($id);
        $product->update($data);
        return $product->fresh();
    }

    public function delete($id)
    {
        // Use withoutGlobalScopes() to bypass any global scopes that might interfere
        // (e.g., App\Product has global scopes that add extra query conditions)
        return Product::withoutGlobalScopes()->findOrFail($id)->delete();
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
        // Get all attributes that should be exported (enabled_on_dropship)
        // Support dual mode: WL (direct column) vs WTM (via attribute_groups join)
        $mode = config('products-package.mode', 'wl');

        if ($mode === 'wl') {
            // WL mode: Direct column query
            $enabledAttributes = \TheDiamondBox\ShopSync\Models\Attribute::where('enabled_on_dropship', true)
                ->orderBy('sortby')
                ->orderBy('name')
                ->get();
        } else {
            // WTM mode: Join with attribute_groups table
            $enabledAttributes = \TheDiamondBox\ShopSync\Models\Attribute::join('attribute_groups', 'attributes.group_name', '=', 'attribute_groups.group_name')
                ->where('attribute_groups.enabled_on_dropship', true)
                ->select('attributes.*')
                ->orderBy('attributes.sortby')
                ->orderBy('attributes.name')
                ->get();
        }

        // Use chunked processing to prevent memory exhaustion
        $chunkSize = 1000; // Process 1000 records at a time

        $headerColumns = ['ID', 'Name', 'Description', 'Price', 'Stock', 'SKU', 'Category', 'Active', 'Created At'];
        foreach ($enabledAttributes as $attribute) {
            $headerColumns[] = $attribute->name;
        }
        $csv = implode(',', $headerColumns) . "\n";

        // Use output buffering and temporary file for large exports
        $tempHandle = fopen('php://temp/maxmemory:' . (5 * 1024 * 1024), 'r+'); // 5MB memory limit

        if (!$tempHandle) {
            throw new \RuntimeException('Unable to create temporary file for CSV export');
        }

        try {
            // Write header to temp file
            fwrite($tempHandle, $csv);

            // Process data in chunks to prevent memory exhaustion
            // Include attributes relationship for export
            $this->buildQuery($filters, ['attributes'])->chunk($chunkSize, function ($products) use ($tempHandle, $enabledAttributes) {
                foreach ($products as $product) {
                    $row = $this->formatCsvRow($product, $enabledAttributes);
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
    protected function formatCsvRow($product, $enabledAttributes = []): string
    {
        $columns = [
            $product->id,
            '"' . str_replace('"', '""', $product->name ?? '') . '"',
            '"' . str_replace('"', '""', $product->description ?? '') . '"',
            $product->price ?? 0,
            $product->stock ?? 0,
            '"' . str_replace('"', '""', $product->sku ?? '') . '"',
            '"' . str_replace('"', '""', $product->category ?? '') . '"',
            $product->is_active ? 'Yes' : 'No',
            $product->created_at ? $product->created_at->toDateTimeString() : '',
        ];

        // Add attribute values
        foreach ($enabledAttributes as $attribute) {
            $value = '';

            // Find the attribute value from product's attributes relationship
            if ($product->relationLoaded('attributes')) {
                $productAttribute = $product->attributes->where('id', $attribute->id)->first();
                if ($productAttribute) {
                    // Try to get value from pivot first
                    if (isset($productAttribute->pivot) && isset($productAttribute->pivot->value)) {
                        $value = $productAttribute->pivot->value;
                    }
                    // Fallback: try direct property access (in case pivot is accessed differently)
                    elseif (isset($productAttribute->value)) {
                        $value = $productAttribute->value;
                    }
                }
            } else {
                // If relationship not loaded, query directly from database
                $productAttributeValue = DB::table('product_attributes')
                    ->where('product_id', $product->id)
                    ->where('attribute_id', $attribute->id)
                    ->value('value');

                if ($productAttributeValue !== null) {
                    $value = $productAttributeValue;
                }
            }

            // Escape and add to columns
            $columns[] = '"' . str_replace('"', '""', $value) . '"';
        }

        return implode(',', $columns);
    }

    public function importFromCsv($csvContent)
    {
        // Remove UTF-8 BOM if present
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);

        $rows = array_map('str_getcsv', explode("\n", $csvContent));
        $header = array_shift($rows);

        if (empty($header)) {
            return ['imported' => 0, 'errors' => ['Invalid CSV format: No header row found']];
        }

        // Additional safety: trim any remaining BOM or whitespace from first header
        if (!empty($header[0])) {
            $header[0] = trim($header[0], "\xEF\xBB\xBF\t\n\r\0\x0B ");
        }

        // Validate header format
        $expectedHeaders = [
            'Product Name', 'SKU Prefix', 'SKU Value', 'SKU Custom Ref', 'Product Status',
            'Sell Status', 'Purchase Date', 'Current Price', 'Sale Price', 'Trade Price',
            'VAT Scheme', 'Description', 'Category', 'Brand', 'Supplier', 'SEO Title',
            'SEO Keywords', 'SEO Description', 'URL Slug'
        ];

        $headerDiff = array_diff($expectedHeaders, $header);
        if (!empty($headerDiff)) {
            return [
                'imported' => 0,
                'errors' => [
                    'Invalid CSV header format. Expected headers: ' . implode(', ', $expectedHeaders),
                    'Missing headers: ' . implode(', ', $headerDiff)
                ]
            ];
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

                    if (!$data || !isset($data['Product Name']) || empty(trim($data['Product Name']))) {
                        $errors[] = "Row " . ($index + 2) . ": Product name is required";
                        continue;
                    }

                    $productData = [
                        'name' => trim($data['Product Name']),
                        'sku_prefix' => isset($data['SKU Prefix']) ? trim($data['SKU Prefix']) : null,
                        'rol_number' => isset($data['SKU Value']) ? trim($data['SKU Value']) : null,
                        'sku_custom_ref' => isset($data['SKU Custom Ref']) ? trim($data['SKU Custom Ref']) : null,
                        'status' => isset($data['Product Status']) ? ProductImportMappings::map('PRODUCT_STATUS', $data['Product Status'], 1) : 1,
                        'sell_status' => isset($data['Sell Status']) ? ProductImportMappings::map('SELL_STATUS', $data['Sell Status'], 1) : 1,
                        'purchase_date' => isset($data['Purchase Date']) ? trim($data['Purchase Date']) : null,
                        'price' => isset($data['Current Price']) ? (float)$data['Current Price'] : 0,
                        'sale_price' => isset($data['Sale Price']) && !empty(trim($data['Sale Price'])) ? (float)$data['Sale Price'] : null,
                        'trade_price' => isset($data['Trade Price']) && !empty(trim($data['Trade Price'])) ? (float)$data['Trade Price'] : null,
                        'vat_scheme' => isset($data['VAT Scheme']) ? ProductImportMappings::map('VAT_SCHEME', $data['VAT Scheme'], 0) : 0,
                        'description' => isset($data['Description']) ? trim($data['Description']) : null,
                        'seo_keywords' => isset($data['SEO Keywords']) ? trim($data['SEO Keywords']) : null,
                        'seo_description' => isset($data['SEO Description']) ? trim($data['SEO Description']) : null,
                        'slug' => isset($data['URL Slug']) ? trim($data['URL Slug']) : null,
                    ];

                    // TODO: Handle Category, Brand, Supplier lookups/creation (they need IDs, not names)
                    // category_id, brand_id, supplier_id would need to be resolved from names

                    // Use updateOrCreate with sku_prefix + rol_number (SKU Value) if provided
                    $uniqueKey = !empty($productData['sku_prefix']) && !empty($productData['rol_number'])
                        ? ['sku_prefix' => $productData['sku_prefix'], 'rol_number' => $productData['rol_number']]
                        : ['name' => $productData['name']];

                    // updateOrCreate might hurt performance. Need to do filter data before insert later
                    Product::withoutEvents(function () use ($uniqueKey, $productData) {
                        Product::updateOrCreate($uniqueKey, $productData);
                    });

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

        $this->observer->imported();

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
                    // Only load attributes that are enabled_on_dropship
                    $relationships['attributes'] = function ($query) {
                        $query->where('enabled_on_dropship', true)
                              ->orderBy('sortby')
                              ->orderBy('name');
                    };
                    break;
                case 'productAttributes':
                    // Load product attributes with only enabled_on_dropship attributes
                    $relationships['productAttributes'] = function ($query) {
                        $query->whereHas('attribute', function ($q) {
                            $q->where('enabled_on_dropship', true);
                        })->with(['attribute' => function ($q) {
                            $q->where('enabled_on_dropship', true)
                              ->orderBy('sortby')
                              ->orderBy('name');
                        }]);
                    };
                    break;
            }
        }

        return $relationships;
    }

    /**
     * Upload product image (WL mode - save directly to storage)
     *
     * @param int|string $id
     * @param \Illuminate\Http\UploadedFile $file
     * @return mixed Updated product or null
     */
    public function uploadProductImage($id, $file)
    {
        // Use withoutGlobalScopes() to bypass any global scopes that might interfere
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        // Store the uploaded image (only to original_image directory)
        $originalImagePath = $this->storeProductImage($file);

        if (!$originalImagePath) {
            return null;
        }

        $product->update([
            'image' => $originalImagePath,
            'original_image' => $originalImagePath
        ]);

        return $product->fresh();
    }

    /**
     * Store product image to filesystem (following main app pattern)
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string|null Path to processed image
     */
    protected function storeProductImage($file): ?string
    {
        try {
            $checkExt = $file->getClientOriginalExtension();
            $name = md5(uniqid(rand(), true)) . '.' . $checkExt;

            $originalImagePath = 'uploads/original_image';

            // Ensure directory exists
            $originalDir = public_path($originalImagePath);

            if (!file_exists($originalDir)) {
                mkdir($originalDir, 0777, true);
            }

            // Move to original_image directory only
            $file->move(public_path($originalImagePath), $name);

            $originalFullPath = public_path($originalImagePath . '/' . $name);

            // Return path to original image
            return $originalImagePath . '/' . $name;

        } catch (\Exception $e) {
            Log::error('Failed to store product image', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            return null;
        }
    }
}
