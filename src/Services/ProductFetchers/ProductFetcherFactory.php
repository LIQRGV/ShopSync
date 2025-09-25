<?php

namespace Liqrgv\ShopSync\Services\ProductFetchers;

use Liqrgv\ShopSync\Exceptions\ClientNotFoundException;
use Liqrgv\ShopSync\Models\Client;
use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class ProductFetcherFactory
{
    /**
     * Create a product fetcher instance based on mode
     *
     * @param string $mode The mode to create a fetcher for ('wl' or 'wtm')
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return ProductFetcherInterface
     * @throws InvalidArgumentException When mode is invalid
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function make($mode, $request = null)
    {
        switch (strtolower($mode)) {
            case 'wl':
                return new DatabaseProductFetcher();
            case 'wtm':
                if ($request === null) {
                    throw new InvalidArgumentException(
                        "Request object is required when using 'wtm' mode."
                    );
                }

                if (app()->runningInConsole()) {
                    return;
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

                return new ApiProductFetcher($client);
            default:
                throw new InvalidArgumentException(
                    "Invalid mode: {$mode}. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market)."
                );
        }
    }

    /**
     * Create a product fetcher instance from config
     *
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return ProductFetcherInterface
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function makeFromConfig($request = null)
    {
        $mode = config('products-package.mode', 'wl');

        Log::info('Creating ProductFetcher', ['mode' => $mode]);

        try {
            return static::make($mode, $request);
        } catch (InvalidArgumentException $e) {
            // Check if it's a mode validation error or a request/client-id error
            if (strpos($e->getMessage(), 'Invalid mode:') === 0) {
                Log::error('Invalid ProductFetcher mode in config, falling back to WL mode', [
                    'invalid_mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                // Fallback to WL mode if config mode is invalid
                return static::make('wl', $request);
            } else {
                // For request/client-id validation errors, don't fallback - re-throw
                Log::error('ProductFetcher creation failed due to request validation', [
                    'mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        } catch (ClientNotFoundException $e) {
            Log::error('Client not found', [
                'mode' => $mode,
                'client_id' => $e->getClientId(),
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);

            throw $e;
        }
    }

    /**
     * Get available modes
     *
     * @return array
     */
    public static function getAvailableModes(): array
    {
        return [
            'wl' => [
                'name' => 'WhiteLabel',
                'description' => 'Direct database manipulation for local shop pages',
                'class' => DatabaseProductFetcher::class
            ],
            'wtm' => [
                'name' => 'Watch the Market',
                'description' => 'API-based manipulation for admin panel/market monitoring',
                'class' => ApiProductFetcher::class
            ]
        ];
    }

    /**
     * Validate mode configuration
     *
     * @param string $mode
     * @return bool
     */
    public static function isValidMode(string $mode): bool
    {
        return in_array(strtolower($mode), ['wl', 'wtm']);
    }

    /**
     * Get current configuration status
     *
     * @return array
     */
    public static function getConfigStatus(): array
    {
        $mode = config('products-package.mode', 'wl');
        $isValid = static::isValidMode($mode);

        $status = [
            'current_mode' => $mode,
            'is_valid' => $isValid,
            'available_modes' => static::getAvailableModes()
        ];

        if ($isValid) {
            try {
                $fetcher = static::make($mode, null);

                // Check if API fetcher has additional status info
                if ($fetcher instanceof ApiProductFetcher) {
                    $status['api_status'] = $fetcher->getConfigStatus();
                }

                $status['fetcher_created'] = true;
            } catch (\Exception $e) {
                $status['fetcher_created'] = false;
                $status['error'] = $e->getMessage();

                // Add specific context for ClientNotFoundException
                if ($e instanceof ClientNotFoundException) {
                    $status['client_id'] = $e->getClientId();
                    $status['exception_context'] = $e->getContext();
                }
            }
        }

        return $status;
    }
}