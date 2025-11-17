<?php

namespace TheDiamondBox\ShopSync\Services\SupplierFetchers;

use Illuminate\Http\Request;
use InvalidArgumentException;
use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\SupplierFetcherInterface;

class SupplierFetcherFactory
{
    /**
     * Create fetcher from config
     *
     * @param Request|null $request
     * @return SupplierFetcherInterface
     */
    public static function makeFromConfig($request = null): SupplierFetcherInterface
    {
        $mode = config('products-package.mode', 'wl');
        return static::make($mode, $request);
    }

    /**
     * Create fetcher for specified mode
     *
     * @param string $mode
     * @param Request|null $request
     * @return SupplierFetcherInterface
     * @throws InvalidArgumentException
     * @throws ClientNotFoundException
     */
    public static function make(string $mode, $request = null): SupplierFetcherInterface
    {
        switch (strtolower($mode)) {
            case 'wl':
                return app(DatabaseSupplierFetcher::class);

            case 'wtm':
                $clientID = $request ? $request->header('client-id') : null;

                if (!$clientID) {
                    throw new ClientNotFoundException('client-id header is required for WTM mode');
                }

                $client = Client::query()->find($clientID);

                if (!$client) {
                    throw ClientNotFoundException::forClientId($clientID);
                }

                return new ApiSupplierFetcher($client);

            default:
                throw new InvalidArgumentException("Invalid mode: {$mode}. Must be 'wl' or 'wtm'");
        }
    }
}
