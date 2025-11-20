<?php

namespace TheDiamondBox\ShopSync\Services\CategoryFetchers;

use Illuminate\Http\Request;
use InvalidArgumentException;
use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\CategoryFetcherInterface;

class CategoryFetcherFactory
{
    /**
     * Create fetcher from config
     *
     * @param Request|null $request
     * @return CategoryFetcherInterface
     */
    public static function makeFromConfig($request = null): CategoryFetcherInterface
    {
        $mode = config('products-package.mode', 'wl');
        return static::make($mode, $request);
    }

    /**
     * Create fetcher for specified mode
     *
     * @param string $mode
     * @param Request|null $request
     * @return CategoryFetcherInterface
     * @throws InvalidArgumentException
     * @throws ClientNotFoundException
     */
    public static function make(string $mode, $request = null): CategoryFetcherInterface
    {
        switch (strtolower($mode)) {
            case 'wl':
                return app(DatabaseCategoryFetcher::class);

            case 'wtm':
                $clientID = $request ? $request->header('client-id') : null;

                if (!$clientID) {
                    throw new ClientNotFoundException('client-id header is required for WTM mode');
                }

                $client = Client::query()->find($clientID);

                if (!$client) {
                    throw ClientNotFoundException::forClientId($clientID);
                }

                return new ApiCategoryFetcher($client);

            default:
                throw new InvalidArgumentException("Invalid mode: {$mode}. Must be 'wl' or 'wtm'");
        }
    }
}
