<?php

namespace TheDiamondBox\ShopSync\Services\ProductFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\ProductFetcherInterface;
use TheDiamondBox\ShopSync\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiProductFetcher implements ProductFetcherInterface
{
    protected $baseUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct($client)
    {
        if (!$client) { // possibly for route:list
            return;
        }

        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
        $this->timeout = config('products-package.wtm_api_timeout', 5);

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('WTM API configuration is incomplete', [
                'base_url' => $this->baseUrl,
                'api_key_provided' => !empty($this->apiKey)
            ]);
        }
    }

    /**
     * Create HTTP client with proper headers and timeout
     */
    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->baseUrl($this->baseUrl);
    }

    /**
     * Create HTTP client for multipart requests (file uploads)
     * Don't set Content-Type - let Laravel handle it automatically
     * DON'T use baseUrl() for multipart - use full URL instead
     */
    protected function multipartClient()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            // DON'T set Content-Type for multipart - Laravel sets it automatically with boundary
        ])
            ->timeout($this->timeout);
        // DON'T use ->baseUrl() here - it causes issues with attach()
    }

    /**
     * Handle API requests with proper error handling and logging
     */
    protected function handleRequest(callable $callback, $defaultValue = [])
    {
        try {
            $response = $callback();

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning('WTM API returned error response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $defaultValue;
            }
        } catch (ConnectionException $e) {
            Log::warning('WTM API timeout or connection error', [
                'error' => $e->getMessage(),
                'timeout' => $this->timeout
            ]);
            return $defaultValue;
        } catch (RequestException $e) {
            Log::error('WTM API request error', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null
            ]);
            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('WTM API unexpected error', [
                'error' => $e->getMessage()
            ]);
            return $defaultValue;
        }
    }

    /**
     * Get all products as Collection of Product models
     *
     * @param array $filters
     * @param array $includes
     * @return Collection
     */
    public function getAll(array $filters = [], array $includes = [])
    {
        // Add includes to the request parameters
        $params = $filters;
        if (!empty($includes)) {
            $params['include'] = implode(',', $includes);
        }

        $response = $this->handleRequest(function () use ($params) {
            return $this->client()->get('/products', $params);
        }, ['data' => []]);

        return $this->convertToProductCollection($response['data'] ?? [], $response['included'] ?? []);
    }

    /**
     * Get paginated products as LengthAwarePaginator with Product models
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return LengthAwarePaginator
     */
    public function paginate($perPage = 25, array $filters = [], array $includes = [])
    {
        $currentPage = $this->getCurrentPage();
        $perPage = $this->getPerPage($perPage);

        $params = array_merge($filters, [
            'page' => $currentPage,
            'per_page' => $perPage
        ]);

        if (!empty($includes)) {
            $params['include'] = implode(',', $includes);
        }

        $response = $this->handleRequest(function () use ($params) {
            return $this->client()->get('/products', $params);
        }, [
            'data' => [],
            'meta' => [
                'pagination' => [
                    'total' => 0,
                    'count' => 0,
                    'per_page' => $perPage,
                    'current_page' => $currentPage,
                    'total_pages' => 1,
                    'from' => 0,
                    'to' => 0
                ]
            ]
        ]);

        $paginationMeta = $response['meta']['pagination'] ?? null;

        if (!$paginationMeta) {
            $items = $this->convertToProductCollection($response['data'] ?? [], $response['included'] ?? []);
            $total = $response['meta']['total'] ?? count($response['data'] ?? []);

            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'pageName' => 'page'
                ]
            );
        }

        $items = $this->convertToProductCollection($response['data'] ?? [], $response['included'] ?? []);

        return new LengthAwarePaginator(
            $items,
            $paginationMeta['total'] ?? $items->count(),
            $paginationMeta['per_page'] ?? $perPage,
            $paginationMeta['current_page'] ?? $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page'
            ]
        );
    }

    /**
     * Get current page number from request
     *
     * @return int
     */
    protected function getCurrentPage(): int
    {
        $page = request()->input('page', 1);
        return max(1, (int) $page);
    }

    /**
     * Get per page limit from request with reasonable bounds
     *
     * @param int $default
     * @return int
     */
    protected function getPerPage(int $default): int
    {
        $perPage = request()->input('per_page', $default);
        return min(100, max(1, (int) $perPage)); // Limit: 1-100 items per page
    }

    /**
     * Create a new product and return Product model
     *
     * @param array $data
     * @return Product|null
     */
    public function create(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->post('/products', $data);
        }, null);

        return $response ? $this->convertToProduct($response['data'] ?? $response) : null;
    }

    /**
     * Update an existing product and return Product model
     *
     * @param int|string $id
     * @param array $data
     * @return Product|null
     */
    public function update($id, array $data)
    {
        $response = $this->handleRequest(function () use ($id, $data) {
            return $this->client()->put("/products/{$id}", $data);
        }, null);

        return $response ? $this->convertToProduct($response['data'] ?? $response) : null;
    }

    public function delete($id)
    {
        $response = $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}");
        }, null);

        // Return true if response is successful (not null)
        return $response !== null;
    }

    /**
     * Restore a soft-deleted product and return Product model
     *
     * @param int|string $id
     * @return Product|null
     */
    public function restore($id)
    {
        $response = $this->handleRequest(function () use ($id) {
            return $this->client()->post("/products/{$id}/restore");
        }, null);

        return $response ? $this->convertToProduct($response['data'] ?? $response) : null;
    }

    public function forceDelete($id)
    {
        $response = $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}/force");
        }, null);

        // Return true if response is successful (not null)
        return $response !== null;
    }

    /**
     * Find a single product by ID and return Product model
     *
     * @param int|string $id
     * @param bool $withTrashed
     * @return Product|null
     */
    public function find($id, $withTrashed = false)
    {
        $response = $this->handleRequest(function () use ($id, $withTrashed) {
            $params = $withTrashed ? ['with_trashed' => true] : [];
            return $this->client()->get("/products/{$id}", $params);
        }, null);

        return $response ? $this->convertToProduct($response['data'] ?? $response) : null;
    }

    /**
     * Search products and return Collection of Product models
     *
     * @param string $query
     * @param array $filters
     * @return Collection
     */
    public function search($query, array $filters = [], array $includes = [])
    {
        $params = $filters;
        $params['q'] = $query;
        if (!empty($includes)) {
            $params['include'] = implode(',', $includes);
        }

        $response = $this->handleRequest(function () use ($params) {
            return $this->client()->get('/products/search', $params);
        }, ['data' => []]);

        return $this->convertToProductCollection($response['data'] ?? [], $response['included'] ?? []);
    }

    public function exportToCsv(array $filters = [])
    {
        return $this->handleRequest(function () use ($filters) {
            $response = $this->client()->get('/products/export', $filters);

            if ($response->successful()) {
                return $response->body();
            }

            return '';
        }, '');
    }

    public function importFromCsv($csvContent)
    {
        return $this->handleRequest(function () use ($csvContent) {
            return $this->client()
                ->attach('file', $csvContent, 'products.csv')
                ->post('/products/import');
        }, ['imported' => 0, 'errors' => ['API import failed - connection error']]);
    }

    /**
     * Check if API is available and configured
     */
    public function isAvailable(): bool
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return false;
        }

        try {
            $response = $this->client()->timeout(2)->get('/health');
            return $response->successful();
        } catch (\Exception $e) {
            Log::debug('WTM API health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get API configuration status
     */
    public function getConfigStatus(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'api_key_configured' => !empty($this->apiKey),
            'timeout' => $this->timeout,
            'available' => $this->isAvailable()
        ];
    }

    /**
     * Convert API response data to Product model
     *
     * @param array $data
     * @param array $includedMap
     * @return Product|null
     */
    protected function convertToProduct($data, $includedMap = [])
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        // Handle JSON API format
        if (isset($data['type']) && $data['type'] === 'products' && isset($data['attributes'])) {
            $attributes = $data['attributes'];
            $attributes['id'] = $data['id'];

            // Process relationships if present
            if (isset($data['relationships'])) {
                foreach ($data['relationships'] as $relationName => $relationData) {
                    if (isset($relationData['data'])) {
                        if (is_array($relationData['data'])) {
                            // Check if it's an empty array (no relationships)
                            if (empty($relationData['data'])) {
                                $attributes[$relationName] = null; // Set to null for empty relationships
                            }
                            // Check if it's a single relationship with type and id
                            elseif (isset($relationData['data']['type']) && isset($relationData['data']['id'])) {
                                // Single relationship
                                $key = $relationData['data']['type'] . ':' . $relationData['data']['id'];
                                if (isset($includedMap[$key])) {
                                    $attributes[$relationName] = $includedMap[$key]['attributes'] ?? [];
                                    $attributes[$relationName]['id'] = $includedMap[$key]['id'];
                                }
                            }
                            // Handle multiple relationships (array of objects with type and id)
                            else {
                                $relatedItems = [];
                                foreach ($relationData['data'] as $item) {
                                    if (isset($item['type']) && isset($item['id'])) {
                                        $key = $item['type'] . ':' . $item['id'];
                                        if (isset($includedMap[$key])) {
                                            $relatedItem = $includedMap[$key]['attributes'] ?? [];
                                            $relatedItem['id'] = $includedMap[$key]['id'];
                                            $relatedItems[] = $relatedItem;
                                        }
                                    }
                                }
                                $attributes[$relationName] = $relatedItems;
                            }
                        }
                    }
                }
            }

            $data = $attributes;
        }

        // Create a new Product instance and fill it with data
        $product = new Product();
        $product->exists = true; // Mark as existing since it came from API
        $product->fill($data);

        // Set the primary key
        if (isset($data['id'])) {
            $product->setAttribute($product->getKeyName(), $data['id']);
        }

        // Set relationships if they were included - convert arrays to proper model instances
        $relationshipModelMap = [
            'category' => \TheDiamondBox\ShopSync\Models\Category::class,
            'brand' => \TheDiamondBox\ShopSync\Models\Brand::class,
            'location' => \TheDiamondBox\ShopSync\Models\Location::class,
            'supplier' => \TheDiamondBox\ShopSync\Models\Supplier::class,
            'attributes' => \TheDiamondBox\ShopSync\Models\Attribute::class,
        ];

        foreach (['category', 'brand', 'location', 'supplier', 'attributes'] as $relation) {
            if (isset($data[$relation])) {
                $relationData = $data[$relation];

                if ($relationData === null) {
                    $product->setRelation($relation, null);
                } elseif (is_array($relationData)) {
                    $modelClass = $relationshipModelMap[$relation];

                    if ($relation === 'attributes') {
                        // Has-many relationship - create collection of models
                        $models = collect($relationData)->map(function ($item) use ($modelClass) {
                            if (is_array($item)) {
                                $model = new $modelClass();
                                $model->fill($item);
                                if (isset($item['id'])) {
                                    $model->setAttribute($model->getKeyName(), $item['id']);
                                }
                                $model->exists = true;
                                return $model;
                            }
                            return null;
                        })->filter();

                        $product->setRelation($relation, $models);
                    } else {
                        // Belongs-to relationship - create single model
                        if (!empty($relationData)) {
                            $model = new $modelClass();
                            $model->fill($relationData);

                            // Set count data for specific models to prevent database queries
                            if ($relation === 'category' && isset($relationData['products_count'])) {
                                $model->setAttribute('products_count', $relationData['products_count']);
                            }
                            if ($relation === 'category' && isset($relationData['active_products_count'])) {
                                $model->setAttribute('active_products_count', $relationData['active_products_count']);
                            }
                            if ($relation === 'brand' && isset($relationData['products_count'])) {
                                $model->setAttribute('products_count', $relationData['products_count']);
                            }
                            if ($relation === 'supplier' && isset($relationData['products_count'])) {
                                $model->setAttribute('products_count', $relationData['products_count']);
                            }
                            if ($relation === 'location' && isset($relationData['products_count'])) {
                                $model->setAttribute('products_count', $relationData['products_count']);
                            }

                            if (isset($relationData['id'])) {
                                $model->setAttribute($model->getKeyName(), $relationData['id']);
                            }
                            $model->exists = true;
                            $product->setRelation($relation, $model);
                        } else {
                            $product->setRelation($relation, null);
                        }
                    }
                }
            } else {
                // Mark relationship as loaded but empty to prevent further loading attempts
                $product->setRelation($relation, null);
            }
        }

        return $product;
    }

    /**
     * Convert API response data array to Collection of Product models
     *
     * @param array $dataArray
     * @param array $includedData
     * @return Collection
     */
    protected function convertToProductCollection($dataArray, $includedData = [])
    {
        if (empty($dataArray) || !is_array($dataArray)) {
            return collect([]);
        }

        // Build a map of included resources for easier access
        $includedMap = [];
        if (!empty($includedData) && is_array($includedData)) {
            foreach ($includedData as $included) {
                if (isset($included['type']) && isset($included['id'])) {
                    $key = $included['type'] . ':' . $included['id'];
                    $includedMap[$key] = $included;
                }
            }
        }

        $products = [];
        foreach ($dataArray as $productData) {
            $product = $this->convertToProduct($productData, $includedMap);
            if ($product) {
                $products[] = $product;
            }
        }

        return collect($products);
    }

    /**
     * Upload product image (WTM mode - proxy to WL)
     *
     * @param int|string $id
     * @param \Illuminate\Http\UploadedFile $file
     * @return Product|null
     */
    public function uploadProductImage($id, $file)
    {
        Log::info('ApiProductFetcher::uploadProductImage called', [
            'id' => $id,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'base_url' => $this->baseUrl,
            'full_url' => $this->baseUrl . "/products/{$id}/image",
            'api_key_length' => strlen($this->apiKey ?? '')
        ]);

        // First verify product exists with a GET request
        $productCheck = $this->find($id);
        if (!$productCheck) {
            Log::warning('Product not found in WTM before upload', ['id' => $id]);
            return null;
        }

        Log::info('Product found in WTM, proceeding with upload', [
            'product_id' => $productCheck->id,
            'product_name' => $productCheck->name ?? 'N/A'
        ]);

        $response = $this->handleRequest(function () use ($id, $file) {
            // Use multipartClient() for file uploads (no Content-Type: application/json)
            $client = $this->multipartClient();

            // Build full URL (don't use baseUrl() with attach())
            $fullUrl = $this->baseUrl . "/products/{$id}/image";

            Log::info('About to send request', [
                'full_url' => $fullUrl,
                'file_name' => $file->getClientOriginalName()
            ]);

            $httpResponse = $client->attach(
                'image',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )
                ->post($fullUrl);

            Log::info('HTTP Response details', [
                'status' => $httpResponse->status(),
                'successful' => $httpResponse->successful(),
                'body' => $httpResponse->body()
            ]);

            return $httpResponse;
        }, null);

        Log::info('ApiProductFetcher::uploadProductImage response', [
            'response_is_null' => $response === null,
            'response' => $response
        ]);

        return $response ? $this->convertToProduct($response['data'] ?? $response) : null;
    }
}
