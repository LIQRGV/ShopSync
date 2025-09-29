<?php

namespace Liqrgv\ShopSync\Services\SseStreamers;

use Liqrgv\ShopSync\Services\Contracts\SseStreamerInterface;
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

    public function __construct($client)
    {
        if (!$client) {
            return;
        }

        $this->client = $client;
        $this->baseUrl = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
        $this->apiKey = decrypt($client->access_token);
        $this->timeout = config('products-package.wtm_api_timeout', 30); // Longer timeout for SSE

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
            $connectionDecrementedOnError = $this->proxyFromWlServer($sessionId, $clientId);
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
                'response_status' => $e->response?->status() ?? 'unknown'
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
     * Proxy SSE stream from WL server
     *
     * @param string $sessionId
     * @param string $clientId
     * @return bool True if connection counter was already decremented, false otherwise
     */
    private function proxyFromWlServer(string $sessionId, string $clientId): bool
    {
        $sseUrl = $this->baseUrl . '/sse/events';

        // Create HTTP client for SSE connection
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ])
        ->timeout(self::CONNECTION_TIMEOUT)
        ->get($sseUrl);

        if (!$response->successful()) {
            throw new RequestException($response);
        }

        Log::info("SSE [WTM][{$sessionId}]: Connected to WL server SSE stream");

        // Get the response body as a stream
        $stream = $response->getBody();
        $buffer = '';
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
            $chunk = $stream->read(8192);
            if ($chunk === false || $chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // Process complete SSE messages
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $sseMessage = substr($buffer, 0, $pos + 2);
                $buffer = substr($buffer, $pos + 2);

                // Modify the SSE message to add WTM context
                $modifiedMessage = $this->modifySseMessage($sseMessage, $sessionId, $clientId);

                // Forward to client with write check
                if (!$this->forwardSseMessage($modifiedMessage, $sessionId)) {
                    $failedWrites++;
                    Log::warning("SSE [WTM][{$sessionId}]: Failed to forward message. Failed writes: {$failedWrites}");

                    if ($failedWrites >= $maxFailedWrites) {
                        Log::error("SSE [WTM][{$sessionId}]: Too many failed writes. Disconnecting proxy.");
                        $this->decrementConnectionCounter($sessionId);
                        $connectionDecrementedEarly = true;
                        break 2;
                    }
                } else {
                    $failedWrites = 0; // Reset on successful write
                }
            }
        }

        Log::info("SSE [WTM][{$sessionId}]: WL server stream ended");
        return $connectionDecrementedEarly;
    }

    /**
     * Modify SSE message to add WTM context
     *
     * @param string $sseMessage
     * @param string $sessionId
     * @param string $clientId
     * @return string
     */
    private function modifySseMessage(string $sseMessage, string $sessionId, string $clientId): string
    {
        // Parse the SSE message
        $lines = explode("\n", trim($sseMessage));
        $event = null;
        $data = null;
        $id = null;

        foreach ($lines as $line) {
            if (strpos($line, 'event:') === 0) {
                $event = trim(substr($line, 6));
            } elseif (strpos($line, 'data:') === 0) {
                $data = trim(substr($line, 5));
            } elseif (strpos($line, 'id:') === 0) {
                $id = trim(substr($line, 3));
            }
        }

        // Skip heartbeat messages
        if (strpos($sseMessage, ': heartbeat') === 0) {
            return $sseMessage;
        }

        // If we have data, modify it to add WTM context
        if ($data && $event) {
            try {
                $parsedData = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
                    // Add WTM context
                    $parsedData['wtm_session_id'] = $sessionId;
                    $parsedData['wtm_client_id'] = $clientId;
                    $parsedData['proxied_from'] = $this->baseUrl;

                    // Rebuild the message
                    $modifiedMessage = '';
                    if ($id) {
                        $modifiedMessage .= "id: {$id}\n";
                    }
                    $modifiedMessage .= "event: {$event}\n";
                    $modifiedMessage .= "data: " . json_encode($parsedData) . "\n\n";

                    return $modifiedMessage;
                }
            } catch (\Exception $e) {
                Log::debug("SSE [WTM][{$sessionId}]: Could not parse SSE data as JSON, forwarding as-is");
            }
        }

        // Return original message if we couldn't modify it
        return $sseMessage;
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