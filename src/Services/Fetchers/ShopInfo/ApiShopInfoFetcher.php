<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\ShopInfo;

use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use TheDiamondBox\ShopSync\Models\ShopInfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiShopInfoFetcher implements ShopInfoFetcherInterface
{
    protected $baseUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct($client)
    {
        if (!$client) {
            throw new \InvalidArgumentException('Client is required for ApiShopInfoFetcher');
        }

        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
        $this->timeout = config('products-package.wtm_api_timeout', 30);
    }

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

    protected function handleRequest(callable $callback, $defaultValue = null)
    {
        try {
            $response = $callback();

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('WTM ShopInfo API error', [
                'status' => $response->status(),
                'message' => 'Failed to fetch shop info from WL server'
            ]);

            return $defaultValue;
        } catch (\Exception $e) {
            Log::error('WTM ShopInfo API exception', [
                'error' => $e->getMessage()
            ]);

            return $defaultValue;
        }
    }

    protected function convertToShopInfo($data)
    {
        if (empty($data) || !is_array($data)) {
            return null;
        }

        if (isset($data['data']['attributes'])) {
            $data = $data['data']['attributes'];
        }

        $openHoursData = $data['open_hours'] ?? null;
        unset($data['open_hours']);

        $shopInfo = new ShopInfo();
        $shopInfo->exists = true;
        $shopInfo->fill($data);

        if ($openHoursData && is_array($openHoursData)) {
            $openHoursClass = class_exists('App\Models\OpenHours')
                ? 'App\Models\OpenHours'
                : (class_exists('App\OpenHours')
                    ? 'App\OpenHours'
                    : 'TheDiamondBox\ShopSync\Models\OpenHours');

            $openHours = collect($openHoursData)->map(function ($item) use ($openHoursClass) {
                $openHour = new $openHoursClass();
                $openHour->exists = true;
                $openHour->fill($item);
                if (isset($item['id'])) {
                    $openHour->id = $item['id'];
                }
                return $openHour;
            });

            $shopInfo->setRelation('openHours', $openHours);
        }

        return $shopInfo;
    }

    public function get()
    {
        $response = $this->handleRequest(function () {
            return $this->client()->get('/shop-info');
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }

    public function update(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->put('/shop-info', $data);
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }

    public function updatePartial(array $data)
    {
        $response = $this->handleRequest(function () use ($data) {
            return $this->client()->patch('/shop-info', $data);
        }, null);

        return $response ? $this->convertToShopInfo($response) : null;
    }

    public function uploadImage(string $field, $file)
    {
        Log::info('ApiShopInfoFetcher::uploadImage called', [
            'field' => $field,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'full_url' => $this->baseUrl . "/shop-info/images",
        ]);

        try {
            $fullUrl = $this->baseUrl . "/shop-info/images";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->attach(
                    'image',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post($fullUrl, [
                    'field' => $field,
                ]);

            Log::info('ApiShopInfoFetcher::uploadImage response', [
                'status' => $response->status(),
                'success' => $response->successful(),
            ]);

            if (!$response->successful()) {
                Log::error('Failed to upload shop info image to WL', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Failed to upload image to WL: ' . $response->body());
            }

            $data = $response->json();
            return $this->convertToShopInfo($data);
        } catch (\Exception $e) {
            Log::error('ApiShopInfoFetcher::uploadImage exception', [
                'error' => $e->getMessage(),
                'field' => $field,
            ]);

            throw $e;
        }
    }
}
