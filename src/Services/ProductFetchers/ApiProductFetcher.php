<?php

namespace Liqrgv\ShopSync\Services\ProductFetchers;

use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use Liqrgv\ShopSync\Models\Product;
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

    public function __construct()
    {
        $this->baseUrl = config('products-package.wtm_api_url');
        $this->apiKey = config('products-package.wtm_api_key');
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
     * @return Collection
     */
    public function getAll(array $filters = [])
    {
        $response = $this->handleRequest(function () use ($filters) {
            return $this->client()->get('/products', $filters);
        }, ['data' => []]);

        return $this->convertToProductCollection($response['data'] ?? []);
    }

    /**
     * Get paginated products as LengthAwarePaginator with Product models
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate($perPage = 15, array $filters = [])
    {
        $filters['per_page'] = $perPage;
        $response = $this->handleRequest(function () use ($filters) {
            return $this->client()->get('/products', $filters);
        }, ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => $perPage]);

        $products = $this->convertToProductCollection($response['data'] ?? []);

        return new LengthAwarePaginator(
            $products,
            $response['total'] ?? 0,
            $response['per_page'] ?? $perPage,
            $response['current_page'] ?? 1,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
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
        $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}");
        });
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
        $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}/force");
        });
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
    public function search($query, array $filters = [])
    {
        $response = $this->handleRequest(function () use ($query, $filters) {
            $filters['q'] = $query;
            return $this->client()->get('/products/search', $filters);
        }, ['data' => []]);

        return $this->convertToProductCollection($response['data'] ?? []);
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
     * @return Product|null
     */
    protected function convertToProduct($data)
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        // Handle JSON API format
        if (isset($data['type']) && $data['type'] === 'product' && isset($data['attributes'])) {
            $attributes = $data['attributes'];
            $attributes['id'] = $data['id'];
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

        return $product;
    }

    /**
     * Convert API response data array to Collection of Product models
     *
     * @param array $dataArray
     * @return Collection
     */
    protected function convertToProductCollection($dataArray)
    {
        if (empty($dataArray) || !is_array($dataArray)) {
            return collect([]);
        }

        $products = [];
        foreach ($dataArray as $productData) {
            $product = $this->convertToProduct($productData);
            if ($product) {
                $products[] = $product;
            }
        }

        return collect($products);
    }
}