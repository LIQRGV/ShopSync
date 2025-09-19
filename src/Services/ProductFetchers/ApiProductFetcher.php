<?php

namespace Liqrgv\ShopSync\Services\ProductFetchers;

use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class ApiProductFetcher implements ProductFetcherInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

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

    public function getAll(array $filters = [])
    {
        return $this->handleRequest(function () use ($filters) {
            return $this->client()->get('/products', $filters);
        }, []);
    }

    public function paginate(int $perPage = 15, array $filters = [])
    {
        return $this->handleRequest(function () use ($perPage, $filters) {
            $filters['per_page'] = $perPage;
            return $this->client()->get('/products', $filters);
        }, ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
    }

    public function create(array $data)
    {
        return $this->handleRequest(function () use ($data) {
            return $this->client()->post('/products', $data);
        }, null);
    }

    public function update($id, array $data)
    {
        return $this->handleRequest(function () use ($id, $data) {
            return $this->client()->put("/products/{$id}", $data);
        }, null);
    }

    public function delete($id)
    {
        $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}");
        });
    }

    public function restore($id)
    {
        return $this->handleRequest(function () use ($id) {
            return $this->client()->post("/products/{$id}/restore");
        }, null);
    }

    public function forceDelete($id)
    {
        $this->handleRequest(function () use ($id) {
            return $this->client()->delete("/products/{$id}/force");
        });
    }

    public function find($id, bool $withTrashed = false)
    {
        return $this->handleRequest(function () use ($id, $withTrashed) {
            $params = $withTrashed ? ['with_trashed' => true] : [];
            return $this->client()->get("/products/{$id}", $params);
        }, null);
    }

    public function search(string $query, array $filters = [])
    {
        return $this->handleRequest(function () use ($query, $filters) {
            $filters['q'] = $query;
            return $this->client()->get('/products/search', $filters);
        }, []);
    }

    public function exportToCsv(array $filters = []): string
    {
        return $this->handleRequest(function () use ($filters) {
            $response = $this->client()->get('/products/export', $filters);

            if ($response->successful()) {
                return $response->body();
            }

            return '';
        }, '');
    }

    public function importFromCsv(string $csvContent): array
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
}