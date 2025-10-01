<?php

namespace TheDiamondBox\ShopSync\Services\SseStreamers;

use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Services\Contracts\SseStreamerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class ProxySseStreamer implements SseStreamerInterface
{
    const CONNECTION_TIMEOUT = 600; // 10 minutes in seconds

    protected $baseUrl;
    protected $apiKey;
    protected $timeout;
    protected $client;
    protected $streamChunkSize;

    public function __construct($client)
    {
        if (!$client) {
            return;
        }

        $this->client = $client;
        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
        $this->timeout = config('products-package.wtm_api_timeout', 30); // Longer timeout for SSE
        $this->streamChunkSize = config('products-package.sse_stream_chunk_size', 8);

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('WTM SSE proxy configuration is incomplete', [
                'base_url' => $this->baseUrl,
                'api_key_provided' => !empty($this->apiKey),
                'client_id' => $client->id ?? 'unknown'
            ]);
        }
    }

    /**
     * Stream SSE events by proxying from WL server (WTM mode)
     *
     * @param string $sessionId Unique session identifier
     * @param Request $request The HTTP request
     * @return void
     */
    public function stream(string $sessionId, Request $request): void
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            header('Content-Type: text/event-stream');
            $this->sendEventWithCheck('error', [
                'message' => 'SSE proxy not configured correctly',
                'error' => 'Missing WL server configuration'
            ], null, $sessionId);
            return;
        }

        $clientIp = $request->ip();
        $userAgent = substr($request->userAgent() ?? 'Unknown', 0, 50);
        $clientId = $this->client->id ?? 'unknown';

        // Log connection start
        Log::info("SSE [WTM][{$sessionId}]: New connection from {$clientIp} - {$userAgent} (Client: {$clientId})");

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering

        // Enable implicit flush to detect write failures immediately
        ob_implicit_flush(true);

        // Send initial connection message
        $connectionData = [
            'message' => 'SSE connection established (proxying from WL server)',
            'session_id' => $sessionId,
            'mode' => 'wtm',
            'client_id' => $clientId,
            'wl_server' => $this->baseUrl,
            'timestamp' => date('Y-m-d H:i:s'),
            'client_ip' => $clientIp
        ];

        if (!$this->sendEventWithCheck('connected', $connectionData, null, $sessionId)) {
            Log::error("SSE [WTM][{$sessionId}]: Failed initial write, closing immediately");
            return;
        }

        // Increment active connections counter
        $this->incrementConnectionCounter($sessionId);

        Log::info("SSE [WTM][{$sessionId}]: Starting proxy connection to {$this->baseUrl}/sse/events");

        $connectionDecrementedOnError = false;

        try {
            $connectionDecrementedOnError = $this->proxyFromWlServer($sessionId, $this->client);
        } catch (ConnectionException $e) {
            Log::error("SSE [WTM][{$sessionId}]: Connection error to WL server", [
                'error' => $e->getMessage(),
                'wl_server' => $this->baseUrl
            ]);

            $this->sendEventWithCheck('error', [
                'message' => 'Connection lost to WL server',
                'error' => 'Connection failed',
                'session_id' => $sessionId
            ], null, $sessionId);

            $this->decrementConnectionCounter($sessionId);
            $connectionDecrementedOnError = true;

        } catch (RequestException $e) {
            Log::error("SSE [WTM][{$sessionId}]: Request error to WL server", [
                'error' => $e->getMessage(),
                'response_status' => empty($e->response) ? 'unknown' : $e->response->status()
            ]);

            $this->sendEventWithCheck('error', [
                'message' => 'Error response from WL server',
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ], null, $sessionId);

            $this->decrementConnectionCounter($sessionId);
            $connectionDecrementedOnError = true;

        } catch (\Exception $e) {
            Log::error("SSE [WTM][{$sessionId}]: Unexpected error during proxy", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendEventWithCheck('error', [
                'message' => 'Unexpected proxy error',
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ], null, $sessionId);

            $this->decrementConnectionCounter($sessionId);
            $connectionDecrementedOnError = true;
        }

        // Decrement connection counter on normal exit (only if not already decremented on error)
        if (!$connectionDecrementedOnError) {
            $this->decrementConnectionCounter($sessionId);
        }

        Log::info("SSE [WTM][{$sessionId}]: Proxy connection closed");
    }

    /**
     * Proxy SSE stream from WL server (transparent mode - immediate forwarding)
     *
     * @param string $sessionId
     * @param string $clientId
     * @return bool True if connection counter was already decremented, false otherwise
     */
    private function proxyFromWlServer(string $sessionId, Client $client): bool
    {
        $sseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1') . '/sse/events';

        // Create HTTP client for SSE connection with streaming enabled
        $response = Http::withOptions(['stream' => true])
            ->withHeaders([
                'Authorization' => 'Bearer ' . decrypt($client->access_token),
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ])
            ->timeout(self::CONNECTION_TIMEOUT)
            ->get($sseUrl);

        if (!$response->successful()) {
            throw new RequestException($response);
        }

        Log::info("SSE [WTM][{$sessionId}]: Connected to WL server SSE stream (transparent proxy mode)");

        // Get the response body as a stream
        $stream = $response->getBody();

        $buffer = "";
        $failedWrites = 0;
        $maxFailedWrites = 3;
        $connectionDecrementedEarly = false;
        $connectionStartTime = time();

        while (!$stream->eof()) {
            // Check for connection timeout
            if (time() - $connectionStartTime >= self::CONNECTION_TIMEOUT) {
                Log::info("SSE [WTM][{$sessionId}]: Connection timeout reached (" . self::CONNECTION_TIMEOUT . " seconds)");
                $this->decrementConnectionCounter($sessionId);
                $connectionDecrementedEarly = true;
                break;
            }
            // Check if client disconnected
            if (connection_aborted()) {
                Log::info("SSE [WTM][{$sessionId}]: Client disconnected, closing proxy");
                $this->decrementConnectionCounter($sessionId);
                $connectionDecrementedEarly = true;
                break;
            }

            // Read chunk from WL server
            $chunk = $stream->read($this->streamChunkSize);
            if ($chunk === false || $chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // Process complete SSE messages (end with \n\n)
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $message = substr($buffer, 0, $pos + 2); // Include the \n\n
                $buffer = substr($buffer, $pos + 2);     // Remove processed message

                // Log only complete messages, not every chunk
                Log::debug("SSE [WTM][{$sessionId}]: Forwarding message", ['size' => strlen($message)]);

                // Forward complete SSE message
                if (!$this->forwardSseMessage($message, $sessionId)) {
                    $failedWrites++;
                    Log::warning("SSE [WTM][{$sessionId}]: Failed to forward message. Failed writes: {$failedWrites}");

                    if ($failedWrites >= $maxFailedWrites) {
                        Log::error("SSE [WTM][{$sessionId}]: Too many failed writes. Disconnecting proxy.");
                        $this->decrementConnectionCounter($sessionId);
                        return true;
                    }
                } else {
                    $failedWrites = 0; // Reset on successful write
                }
            }
        }

        // Send any remaining buffer at the end
        if (!empty($buffer) && !connection_aborted()) {
            Log::debug("SSE [WTM][{$sessionId}]: Sending remaining buffer", ['size' => strlen($buffer)]);
            $this->forwardSseMessage($buffer, $sessionId);
        }

        // Send graceful disconnect event if connection is still alive
        if (!connection_aborted()) {
            $this->sendEventWithCheck('disconnected', [
                'message' => 'WL server stream ended normally',
                'session_id' => $sessionId,
                'reason' => 'stream_ended',
                'timestamp' => date('Y-m-d H:i:s')
            ], null, $sessionId);
            Log::info("SSE [WTM][{$sessionId}]: Sent graceful disconnect event");
        }

        Log::info("SSE [WTM][{$sessionId}]: WL server stream ended");
        return $connectionDecrementedEarly;
    }

    /**
     * Forward SSE message to client
     *
     * @param string $message
     * @param string $sessionId
     * @return bool
     */
    private function forwardSseMessage(string $message, string $sessionId): bool
    {
        // Set error handler to catch write errors
        $writeError = false;
        set_error_handler(function($errno, $errstr) use (&$writeError) {
            if (strpos($errstr, 'failed to write') !== false || strpos($errstr, 'Broken pipe') !== false) {
                $writeError = true;
                return true;
            }
            return false;
        });

        // Attempt to write
        echo $message;

        $flushResult = true;

        // Check if output was successful by flushing
        if (ob_get_level() > 0) {
            $flushResult = ob_flush();
        }

        flush();

        // Restore error handler
        restore_error_handler();

        // If write error occurred or flush failed
        if ($writeError || $flushResult === false) {
            return false;
        }

        // Additional check for connection status
        if (connection_aborted()) {
            return false;
        }

        return true;
    }

    /**
     * Send an SSE event with write checking
     *
     * @param string $event
     * @param mixed $data
     * @param string|null $id
     * @param string|null $sessionId
     * @return bool True if write succeeded, false if failed
     */
    private function sendEventWithCheck(string $event, $data, ?string $id = null, ?string $sessionId = null): bool
    {
        // Build the SSE message
        $message = '';
        if ($id !== null) {
            $message .= "id: {$id}\n";
        }
        $message .= "event: {$event}\n";
        $message .= "data: " . json_encode($data) . "\n\n";

        return $this->forwardSseMessage($message, $sessionId ?? 'unknown');
    }

    /**
     * Get configuration status for the proxy
     *
     * @return array
     */
    public function getConfigStatus(): array
    {
        return [
            'wl_server_url' => $this->baseUrl,
            'has_api_key' => !empty($this->apiKey),
            'timeout' => $this->timeout,
            'client_id' => $this->client->id ?? null,
        ];
    }

    /**
     * Increment the active connections counter in Redis
     *
     * @param string $sessionId
     * @return void
     */
    private function incrementConnectionCounter(string $sessionId): void
    {
        try {
            $newCount = Redis::incr('sse:active_connections');
            Log::debug("SSE [WTM][{$sessionId}]: Incremented connection counter to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("SSE [WTM][{$sessionId}]: Failed to increment Redis connection counter", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Decrement the active connections counter in Redis
     *
     * @param string $sessionId
     * @return void
     */
    private function decrementConnectionCounter(string $sessionId): void
    {
        try {
            $newCount = Redis::decr('sse:active_connections');
            // Ensure counter doesn't go below 0
            if ($newCount < 0) {
                Redis::set('sse:active_connections', 0);
                $newCount = 0;
            }
            Log::debug("SSE [WTM][{$sessionId}]: Decremented connection counter to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("SSE [WTM][{$sessionId}]: Failed to decrement Redis connection counter", [
                'error' => $e->getMessage()
            ]);
        }
    }
}