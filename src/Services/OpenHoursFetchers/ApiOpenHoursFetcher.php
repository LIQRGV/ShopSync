<?php

namespace TheDiamondBox\ShopSync\Services\OpenHoursFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\OpenHoursFetcherInterface;
use TheDiamondBox\ShopSync\Models\OpenHours;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * ApiOpenHoursFetcher - WTM Mode
 *
 * Proxy all open hours operations to WL via API
 */
class ApiOpenHoursFetcher implements OpenHoursFetcherInterface
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

            Log::warning('WTM OpenHours API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('WTM OpenHours API exception', [
                'error' => $e->getMessage()
            ]);

            return $defaultValue;
        }
    }

    /**
     * Convert API response to collection of OpenHours models
     */
    protected function convertToCollection($data)
    {
        if (empty($data) || !is_array($data)) {
            return collect();
        }

        // Handle JSON API format
        if (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
        } else {
            $items = $data;
        }

        return collect($items)->map(function ($item) {
            return $this->convertToOpenHours($item);
        });
    }

    /**
     * Convert API response to OpenHours model
     */
    protected function convertToOpenHours($data)
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        // Handle JSON API format
        if (isset($data['attributes'])) {
            $data = array_merge(['id' => $data['id'] ?? null], $data['attributes']);
        }

        $openHour = new OpenHours();
        $openHour->exists = true;
        $openHour->fill($data);

        if (isset($data['id'])) {
            $openHour->id = $data['id'];
        }

        return $openHour;
    }

    /**
     * Get all open hours from WL
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAll()
    {
        $response = $this->handleRequest(function () {
            return $this->client()->get('/open-hours');
        }, []);

        return $this->convertToCollection($response);
    }

    /**
     * Get open hours for a specific day from WL
     *
     * @param string $day
     * @return mixed
     */
    public function getByDay(string $day)
    {
        $response = $this->handleRequest(function () use ($day) {
            return $this->client()->get("/open-hours/{$day}");
        }, null);

        return $response ? $this->convertToOpenHours($response) : null;
    }

    /**
     * Update open hours for a specific day on WL
     *
     * @param string $day
     * @param array $data
     * @return mixed
     */
    public function updateByDay(string $day, array $data)
    {
        $response = $this->handleRequest(function () use ($day, $data) {
            return $this->client()->put("/open-hours/{$day}", $data);
        }, null);

        return $response ? $this->convertToOpenHours($response) : null;
    }

    /**
     * Bulk update all open hours on WL
     *
     * @param array $data Array of open hours data
     * @return \Illuminate\Support\Collection
     */
    public function bulkUpdate(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->put('/open-hours', $data);
        }, []);

        return $this->convertToCollection($response);
    }

    /**
     * Initialize default open hours on WL
     *
     * @return \Illuminate\Support\Collection
     */
    public function initializeDefaults()
    {
        $response = $this->handleRequest(function () {
            return $this->client()->post('/open-hours/initialize');
        }, []);

        return $this->convertToCollection($response);
    }
}
