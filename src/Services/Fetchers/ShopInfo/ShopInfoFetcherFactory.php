<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\ShopInfo;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * ShopInfoFetcherFactory
 *
 * Factory to create appropriate fetcher based on mode (WL/WTM)
 */
class ShopInfoFetcherFactory
{
    /**
     * Create a shop info fetcher instance based on mode
     *
     * @param string $mode 'wl' or 'wtm'
     * @param mixed $request Request object (required for WTM mode)
     * @return ShopInfoFetcherInterface
     * @throws InvalidArgumentException
     * @throws ClientNotFoundException
     */
    public static function make($mode, $request = null)
    {
        switch (strtolower($mode)) {
            case 'wl':
                return app(DatabaseShopInfoFetcher::class);

            case 'wtm':
                if ($request === null) {
                    throw new InvalidArgumentException(
                        "Request is required when using 'wtm' mode."
                    );
                }

                $clientID = $request->header('client-id');

                if (empty($clientID)) {
                    throw new InvalidArgumentException(
                        "Client ID header is required when using 'wtm' mode."
                    );
                }

                // Use App\Models\Client if available (WTM application context)
                // Otherwise fall back to package Client model
                $clientModel = class_exists('App\Models\Client')
                    ? 'App\Models\Client'
                    : Client::class;

                $client = $clientModel::query()->find($clientID);

                if (!$client) {
                    throw ClientNotFoundException::forClientId($clientID);
                }

                return new ApiShopInfoFetcher($client);

            default:
                throw new InvalidArgumentException(
                    "Invalid mode: {$mode}. Must be 'wl' or 'wtm'."
                );
        }
    }

    /**
     * Create fetcher from config
     *
     * @param mixed $request Request object (required for WTM mode)
     * @return ShopInfoFetcherInterface|null
     * @throws ClientNotFoundException
     */
    public static function makeFromConfig($request = null)
    {
        $mode = config('products-package.mode', 'wl');

        Log::info('Creating ShopInfoFetcher', ['mode' => $mode]);

        try {
            return static::make($mode, $request);
        } catch (InvalidArgumentException $e) {
            // Check if it's a mode validation error
            if (strpos($e->getMessage(), 'Invalid mode:') === 0) {
                Log::error('Invalid ShopInfoFetcher mode, falling back to WL', [
                    'invalid_mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                return static::make('wl', $request);
            }

            // Re-throw request/client-id errors
            Log::error('ShopInfoFetcher creation failed', [
                'mode' => $mode,
                'error' => $e->getMessage()
            ]);

            throw $e;
        } catch (ClientNotFoundException $e) {
            Log::error('Client not found for ShopInfo', [
                'mode' => $mode,
                'client_id' => $e->getClientId(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
