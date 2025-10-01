<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\SseStreamers\SseStreamerFactory;
use TheDiamondBox\ShopSync\Services\Contracts\SseStreamerInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * SSE Service
 *
 * This service provides high-level operations for SSE streaming
 * with support for both WL (direct) and WTM (proxy) modes.
 */
class SseService
{
    protected $sseStreamer;

    public function __construct(Request $request = null)
    {
        $this->sseStreamer = SseStreamerFactory::makeFromConfig($request);
    }

    /**
     * Stream SSE events to client
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function streamEvents(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () use ($request) {
            // Generate unique session ID for this connection
            $sessionId = $this->generateSessionId();

            // Log service initialization
            $mode = config('products-package.mode', 'wl');
            $streamerClass = get_class($this->sseStreamer);
            Log::info("SSE Service [{$sessionId}]: Starting stream", [
                'mode' => $mode,
                'streamer' => $streamerClass,
                'client_ip' => $request->ip()
            ]);

            try {
                // Delegate streaming to the appropriate streamer
                $this->sseStreamer->stream($sessionId, $request);
            } catch (\Exception $e) {
                Log::error("SSE Service [{$sessionId}]: Streaming error", [
                    'error' => $e->getMessage(),
                    'streamer' => $streamerClass,
                    'trace' => $e->getTraceAsString()
                ]);

                // Try to send error event to client if possible
                try {
                    $this->sendErrorEvent($sessionId, $e->getMessage());
                } catch (\Exception $errorSendException) {
                    Log::error("SSE Service [{$sessionId}]: Failed to send error event", [
                        'error' => $errorSendException->getMessage()
                    ]);
                }
            }

            Log::info("SSE Service [{$sessionId}]: Stream ended");
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Generate a unique session ID for this SSE connection
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        // Format: SSE_YYYYMMDD_HHMMSS_RANDOM
        $date = date('Ymd');
        $time = date('His');
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 6);

        return "SSE_{$date}_{$time}_{$random}";
    }

    /**
     * Send an error event to the client
     *
     * @param string $sessionId
     * @param string $errorMessage
     * @return void
     */
    private function sendErrorEvent(string $sessionId, string $errorMessage): void
    {
        $errorData = [
            'message' => 'SSE Service Error',
            'error' => $errorMessage,
            'session_id' => $sessionId,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        echo "event: error\n";
        echo "data: " . json_encode($errorData) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * Get the current SSE streamer instance
     *
     * @return SseStreamerInterface
     */
    public function getStreamer(): SseStreamerInterface
    {
        return $this->sseStreamer;
    }

    /**
     * Get configuration status for the SSE service
     *
     * @return array
     */
    public function getConfigStatus(): array
    {
        $mode = config('products-package.mode', 'wl');
        $streamerClass = get_class($this->sseStreamer);

        $status = [
            'mode' => $mode,
            'streamer_class' => $streamerClass,
            'service_ready' => $this->sseStreamer !== null,
            'active_connections' => $this->getActiveConnectionsCount(),
        ];

        // Get streamer-specific status if available
        if (method_exists($this->sseStreamer, 'getConfigStatus')) {
            $status['streamer_status'] = $this->sseStreamer->getConfigStatus();
        }

        return $status;
    }

    /**
     * Validate SSE service configuration
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Check if streamer was created successfully
        if ($this->sseStreamer === null) {
            $errors[] = 'SSE streamer could not be created';
            return $errors;
        }

        $mode = config('products-package.mode', 'wl');

        // Validate mode-specific configuration
        if (!SseStreamerFactory::isValidMode($mode)) {
            $errors[] = "Invalid SSE mode configuration: {$mode}";
        }

        // Additional validation based on streamer type
        $streamerClass = get_class($this->sseStreamer);
        if (strpos($streamerClass, 'ProxySseStreamer') !== false) {
            // Validate WTM proxy configuration
            if (method_exists($this->sseStreamer, 'getConfigStatus')) {
                $proxyStatus = $this->sseStreamer->getConfigStatus();

                if (empty($proxyStatus['wl_server_url'])) {
                    $errors[] = 'WTM mode: WL server URL is not configured';
                }

                if (!$proxyStatus['has_api_key']) {
                    $errors[] = 'WTM mode: API key is not configured';
                }
            }
        }

        return $errors;
    }

    /**
     * Get the current count of active SSE connections from Redis
     *
     * @return int
     */
    public function getActiveConnectionsCount(): int
    {
        try {
            $count = Redis::get('sse:active_connections');
            return (int) ($count ?? 0);
        } catch (\Exception $e) {
            Log::warning('SSE Service: Failed to get active connections count from Redis', [
                'error' => $e->getMessage()
            ]);
            return -1; // Return -1 to indicate Redis unavailable
        }
    }
}