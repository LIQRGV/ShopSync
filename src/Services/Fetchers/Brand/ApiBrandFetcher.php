<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\Brand;

use TheDiamondBox\ShopSync\Services\Contracts\BrandFetcherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

/**
 * API Brand Fetcher
 *
 * Fetches brands from WL shop API (WTM mode).
 */
class ApiBrandFetcher implements BrandFetcherInterface
{
    protected $baseUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct($client)
    {
        if (!$client) {
            return;
        }

        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
        $this->timeout = config('products-package.wtm_api_timeout', 5);

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('WTM API configuration is incomplete for brands', [
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
                Log::warning('WTM API returned error response for brands', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $defaultValue;
            }
        } catch (ConnectionException $e) {
            Log::warning('WTM API timeout or connection error for brands', [
                'error' => $e->getMessage(),
                'timeout' => $this->timeout
            ]);
            return $defaultValue;
        } catch (RequestException $e) {
            Log::error('WTM API request error for brands', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null
            ]);
            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('WTM API unexpected error for brands', [
                'error' => $e->getMessage()
            ]);
            return $defaultValue;
        }
    }

    /**
     * Get all active brands
     *
     * @return Collection
     */
    public function getAll()
    {
        $response = $this->handleRequest(function () {
            return $this->client()->get('/brands');
        }, ['data' => []]);

        // Return the JSON API response data as a collection
        return collect($response['data'] ?? []);
    }
}
