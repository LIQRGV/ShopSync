<?php

namespace Liqrgv\ShopSync\Services\ProductFetchers;

use Liqrgv\ShopSync\Services\Contracts\ProductFetcherInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class ProductFetcherFactory
{
    /**
     * Create a product fetcher instance based on mode
     *
     * @param string $mode
     * @return ProductFetcherInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $mode): ProductFetcherInterface
    {
        return match(strtolower($mode)) {
            'wl' => new DatabaseProductFetcher(),
            'wtm' => new ApiProductFetcher(),
            default => throw new InvalidArgumentException(
                "Invalid mode: {$mode}. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market)."
            )
        };
    }

    /**
     * Create a product fetcher instance from config
     *
     * @return ProductFetcherInterface
     */
    public static function makeFromConfig(): ProductFetcherInterface
    {
        $mode = config('products-package.mode', 'wl');

        Log::info('Creating ProductFetcher', ['mode' => $mode]);

        try {
            return static::make($mode);
        } catch (InvalidArgumentException $e) {
            Log::error('Invalid ProductFetcher mode in config, falling back to WL mode', [
                'invalid_mode' => $mode,
                'error' => $e->getMessage()
            ]);

            // Fallback to WL mode if config is invalid
            return static::make('wl');
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
                $fetcher = static::make($mode);

                // Check if API fetcher has additional status info
                if ($fetcher instanceof ApiProductFetcher) {
                    $status['api_status'] = $fetcher->getConfigStatus();
                }

                $status['fetcher_created'] = true;
            } catch (\Exception $e) {
                $status['fetcher_created'] = false;
                $status['error'] = $e->getMessage();
            }
        }

        return $status;
    }
}