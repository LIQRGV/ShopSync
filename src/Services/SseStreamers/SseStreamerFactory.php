<?php

namespace TheDiamondBox\ShopSync\Services\SseStreamers;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\SseStreamerInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class SseStreamerFactory
{
    /**
     * Create an SSE streamer instance based on mode
     *
     * @param string $mode The mode to create a streamer for ('wl' or 'wtm')
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return SseStreamerInterface
     * @throws InvalidArgumentException When mode is invalid
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function make($mode, $request = null)
    {
        switch (strtolower($mode)) {
            case 'wl':
                return new DirectSseStreamer();
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

                return new ProxySseStreamer($client);
            default:
                throw new InvalidArgumentException(
                    "Invalid mode: {$mode}. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market)."
                );
        }
    }

    /**
     * Create an SSE streamer instance from config
     *
     * @param mixed $request The request object (required for 'wtm' mode)
     * @return SseStreamerInterface
     * @throws ClientNotFoundException When client is not found in 'wtm' mode
     */
    public static function makeFromConfig($request = null)
    {
        $mode = config('products-package.mode', 'wl');

        Log::info('Creating SseStreamer', ['mode' => $mode]);

        try {
            return static::make($mode, $request);
        } catch (InvalidArgumentException $e) {
            // Check if it's a mode validation error or a request/client-id error
            if (strpos($e->getMessage(), 'Invalid mode:') === 0) {
                Log::error('Invalid SseStreamer mode in config, falling back to WL mode', [
                    'invalid_mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                // Fallback to WL mode if config mode is invalid
                return static::make('wl', $request);
            } else {
                // For request/client-id validation errors, don't fallback - re-throw
                Log::error('SseStreamer creation failed due to request validation', [
                    'mode' => $mode,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        } catch (ClientNotFoundException $e) {
            Log::error('Client not found for SSE streaming', [
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
                'description' => 'Direct SSE event streaming for local shop pages',
                'class' => DirectSseStreamer::class
            ],
            'wtm' => [
                'name' => 'Watch the Market',
                'description' => 'Proxy SSE events from WL server for market monitoring',
                'class' => ProxySseStreamer::class
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
                $streamer = static::make($mode, null);

                // Check if proxy streamer has additional status info
                if ($streamer instanceof ProxySseStreamer) {
                    $status['proxy_status'] = $streamer->getConfigStatus();
                }

                $status['streamer_created'] = true;
            } catch (\Exception $e) {
                $status['streamer_created'] = false;
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