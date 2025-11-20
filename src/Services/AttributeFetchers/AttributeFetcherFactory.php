<?php

namespace TheDiamondBox\ShopSync\Services\AttributeFetchers;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\AttributeFetcherInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class AttributeFetcherFactory
{
    /**
     * Create an attribute fetcher instance based on mode
     *
     * @param string $mode The mode to create a fetcher for ('wl' or 'wtm')
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return AttributeFetcherInterface
     * @throws InvalidArgumentException When mode is invalid
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function make($mode, $request = null)
    {
        switch (strtolower($mode)) {
            case 'wl':
                return app(DatabaseAttributeFetcher::class);
            case 'wtm':
                if ($request === null) {
                    throw new InvalidArgumentException(
                        "Request client is required when using 'wtm' mode."
                    );
                }

                if (app()->runningInConsole()) {
                    return null;
                }

                $clientID = $request->header('client-id');

                if (empty($clientID)) {
                    throw new InvalidArgumentException(
                        "Client ID header is required when using 'wtm' mode."
                    );
                }

                $client = Client::query()->find($clientID);

                if (!$client) {
                    throw ClientNotFoundException::forClientId($clientID);
                }

                return new ApiAttributeFetcher($client);
            default:
                throw new InvalidArgumentException(
                    "Invalid mode: {$mode}. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market)."
                );
        }
    }

    /**
     * Create an attribute fetcher instance from config
     *
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return AttributeFetcherInterface
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function makeFromConfig($request = null)
    {
        $mode = config('products-package.mode', 'wl');

        Log::info('Creating AttributeFetcher', ['mode' => $mode]);

        try {
            return static::make($mode, $request);
        } catch (InvalidArgumentException $e) {
            if (strpos($e->getMessage(), 'Invalid mode:') === 0) {
                Log::error('Invalid AttributeFetcher mode in config, falling back to WL mode', [
                    'invalid_mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                return static::make('wl', $request);
            } else {
                Log::error('AttributeFetcher creation failed due to request validation', [
                    'mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        } catch (ClientNotFoundException $e) {
            Log::error('Client not found for AttributeFetcher', [
                'mode' => $mode,
                'client_id' => $e->getClientId(),
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);

            throw $e;
        }
    }
}
