<?php

namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use TheDiamondBox\ShopSync\Models\ShopInfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ApiShopInfoFetcher - WTM Mode
 *
 * Proxy all shop info operations to WL via API
 */
class ApiShopInfoFetcher implements ShopInfoFetcherInterface
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
        $this->timeout = config('products-package.wtm_api_timeout', 30);
    }

    /**
     * Create HTTP client with auth headers
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
     * Handle API request with error handling
     */
    protected function handleRequest(callable $callback, $defaultValue = null)
    {
        try {
            $response = $callback();

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('WTM ShopInfo API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('WTM ShopInfo API exception', [
                'error' => $e->getMessage()
            ]);

            return $defaultValue;
        }
    }

    /**
     * Convert API response to ShopInfo model
     */
    protected function convertToShopInfo($data)
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        // Handle JSON API format
        if (isset($data['data']['attributes'])) {
            $data = $data['data']['attributes'];
        }

        $shopInfo = new ShopInfo();
        $shopInfo->exists = true;
        $shopInfo->fill($data);

        return $shopInfo;
    }

    /**
     * Get shop info from WL
     *
     * @return ShopInfo|null
     */
    public function get()
    {
        $response = $this->handleRequest(function () {
            return $this->client()->get('/shop-info');
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }

    /**
     * Update shop info on WL (full replace)
     *
     * @param array $data
     * @return ShopInfo|null
     */
    public function update(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->put('/shop-info', $data);
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }

    /**
     * Update shop info on WL (partial - only non-empty values)
     *
     * @param array $data
     * @return ShopInfo|null
     */
    public function updatePartial(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->patch('/shop-info', $data);
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }
}
