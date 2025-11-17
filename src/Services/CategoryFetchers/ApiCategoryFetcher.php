<?php

namespace TheDiamondBox\ShopSync\Services\CategoryFetchers;

use Illuminate\Support\Facades\Http;
use TheDiamondBox\ShopSync\Models\Category;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\CategoryFetcherInterface;

class ApiCategoryFetcher implements CategoryFetcherInterface
{
    protected $client;
    protected $baseUrl;
    protected $apiKey;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
    }

    /**
     * Get all categories from client API
     *
     * @param array $filters
     * @param array $includes
     * @return array
     */
    public function getAll(array $filters = [], array $includes = [])
    {
        $params = $this->buildParams($filters, $includes);

        $response = $this->handleRequest(function () use ($params) {
            return $this->httpClient()->get('/categories', $params);
        }, ['data' => []]);

        return $response['data'] ?? [];
    }

    /**
     * Paginate categories from client API
     *
     * @param int $perPage
     * @param array $filters
     * @param array $includes
     * @return array
     */
    public function paginate(int $perPage = 25, array $filters = [], array $includes = [])
    {
        $filters['per_page'] = $perPage;
        $params = $this->buildParams($filters, $includes);

        $response = $this->handleRequest(function () use ($params) {
            return $this->httpClient()->get('/categories', $params);
        }, ['data' => [], 'meta' => []]);

        return $response;
    }

    /**
     * Find category by ID from client API
     *
     * @param mixed $id
     * @return array|null
     */
    public function find($id)
    {
        $response = $this->handleRequest(function () use ($id) {
            return $this->httpClient()->get("/categories/{$id}");
        }, null);

        return $response ? ($response['data'] ?? $response) : null;
    }

    /**
     * Create category via client API
     *
     * @param array $data
     * @return array
     */
    public function create(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->httpClient()->post('/categories', $data);
        }, ['data' => []]);

        return $response['data'] ?? [];
    }

    /**
     * Update category via client API
     *
     * @param mixed $id
     * @param array $data
     * @return array|null
     */
    public function update($id, array $data)
    {
        $response = $this->handleRequest(function () use ($id, $data) {
            return $this->httpClient()->put("/categories/{$id}", $data);
        }, null);

        return $response ? ($response['data'] ?? $response) : null;
    }

    /**
     * Delete category via client API
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id)
    {
        $response = $this->handleRequest(function () use ($id) {
            return $this->httpClient()->delete("/categories/{$id}");
        }, null);

        return $response !== null;
    }

    /**
     * Build query parameters
     *
     * @param array $filters
     * @param array $includes
     * @return array
     */
    protected function buildParams(array $filters = [], array $includes = [])
    {
        $params = $filters;

        if (!empty($includes)) {
            $params['include'] = is_array($includes) ? implode(',', $includes) : $includes;
        }

        return $params;
    }

    /**
     * Get HTTP client instance
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function httpClient()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(config('products-package.wtm_api_timeout', 30))
          ->baseUrl($this->baseUrl);
    }

    /**
     * Handle API request with error handling
     *
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    protected function handleRequest(callable $callback, $default = null)
    {
        try {
            $response = $callback();

            if ($response->successful()) {
                return $response->json();
            }

            \Log::error('API Category Fetcher request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'client_id' => $this->client->id
            ]);

            return $default;

        } catch (\Exception $e) {
            \Log::error('API Category Fetcher exception', [
                'error' => $e->getMessage(),
                'client_id' => $this->client->id
            ]);

            return $default;
        }
    }
}
