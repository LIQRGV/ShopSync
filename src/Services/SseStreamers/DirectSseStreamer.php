<?php

namespace Liqrgv\ShopSync\Services\SseStreamers;

use Liqrgv\ShopSync\Services\Contracts\SseStreamerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DirectSseStreamer implements SseStreamerInterface
{
    /**
     * Stream SSE events directly (WL mode)
     *
     * @param string $sessionId Unique session identifier
     * @param Request $request The HTTP request
     * @return void
     */
    public function stream(string $sessionId, Request $request): void
    {
        $clientIp = $request->ip();
        $userAgent = substr($request->userAgent() ?? 'Unknown', 0, 50);

        // Log connection start
        Log::info("SSE [WL][{$sessionId}]: New connection from {$clientIp} - {$userAgent}");

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering

        // Enable implicit flush to detect write failures immediately
        ob_implicit_flush(true);

        // Send initial connection message with session ID
        $connectionData = [
            'message' => 'SSE connection established',
            'session_id' => $sessionId,
            'mode' => 'wl',
            'timestamp' => date('Y-m-d H:i:s'),
            'client_ip' => $clientIp
        ];

        if (!$this->sendEventWithCheck('connected', $connectionData, null, $sessionId)) {
            Log::error("SSE [WL][{$sessionId}]: Failed initial write, closing immediately");
            return; // Exit if initial write fails
        }

        // Increment active connections counter
        $this->incrementConnectionCounter($sessionId);

        Log::info("SSE [WL][{$sessionId}]: Connection established successfully");

        // Keep the connection alive and send timestamp every minute
        $lastSent = time();
        $lastHeartbeat = time();
        $counter = 0;
        $failedWrites = 0;
        $maxFailedWrites = 3; // Allow up to 3 failed writes before disconnecting
        $connectionDecrementedOnExit = false;

        while (true) {
            // Check if client disconnected
            if (connection_aborted()) {
                Log::info("SSE [WL][{$sessionId}]: Connection aborted by client");
                $this->decrementConnectionCounter($sessionId);
                $connectionDecrementedOnExit = true;
                break;
            }

            $currentTime = time();

            // Send timestamp event every 60 seconds
            if ($currentTime - $lastSent >= 60) {
                $counter++;
                $writeSuccess = $this->sendEventWithCheck('timestamp', [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'unix_timestamp' => $currentTime,
                    'counter' => $counter,
                    'session_id' => $sessionId,
                    'mode' => 'wl'
                ], null, $sessionId);

                if (!$writeSuccess) {
                    $failedWrites++;
                    Log::warning("SSE [WL][{$sessionId}]: Failed to write timestamp event #{$counter}. Failed writes: {$failedWrites}");

                    if ($failedWrites >= $maxFailedWrites) {
                        Log::error("SSE [WL][{$sessionId}]: Too many failed writes ({$failedWrites}). Disconnecting.");
                        $this->decrementConnectionCounter($sessionId);
                        $connectionDecrementedOnExit = true;
                        break;
                    }
                } else {
                    $failedWrites = 0; // Reset counter on successful write
                    $lastSent = $currentTime;
                    Log::debug("SSE [WL][{$sessionId}]: Sent timestamp event #{$counter}");
                }
            }

            // Send heartbeat every 30 seconds to keep connection alive
            if ($currentTime - $lastHeartbeat >= 30) {
                $heartbeatSuccess = $this->sendHeartbeatWithCheck($sessionId);

                if (!$heartbeatSuccess) {
                    $failedWrites++;
                    Log::warning("SSE [WL][{$sessionId}]: Failed to write heartbeat. Failed writes: {$failedWrites}");

                    if ($failedWrites >= $maxFailedWrites) {
                        Log::error("SSE [WL][{$sessionId}]: Too many failed heartbeat writes. Disconnecting.");
                        $this->decrementConnectionCounter($sessionId);
                        $connectionDecrementedOnExit = true;
                        break;
                    }
                } else {
                    $failedWrites = 0; // Reset counter on successful write
                    $lastHeartbeat = $currentTime;
                }
            }

            // Sleep for 1 second before checking again
            sleep(1);
        }

        // Decrement connection counter on normal exit (only if not already decremented)
        if (!$connectionDecrementedOnExit) {
            $this->decrementConnectionCounter($sessionId);
        }

        // Log final connection status
        $duration = time() - ($lastSent - (60 * $counter));
        Log::info("SSE [WL][{$sessionId}]: Connection closed after {$duration} seconds, sent {$counter} timestamp events");
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

        // Try to write using echo and capture any output errors
        $writeSuccess = false;

        // Set error handler to catch write errors
        set_error_handler(function($errno, $errstr) use (&$writeSuccess) {
            if (strpos($errstr, 'failed to write') !== false || strpos($errstr, 'Broken pipe') !== false) {
                $writeSuccess = false;
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

        // If flush failed, likely the connection is broken
        if ($flushResult === false) {
            return false;
        }

        // Additional check for connection status
        if (connection_aborted()) {
            return false;
        }

        return true;
    }

    /**
     * Send a heartbeat with write checking
     *
     * @param string|null $sessionId
     * @return bool True if write succeeded, false if failed
     */
    private function sendHeartbeatWithCheck(?string $sessionId = null): bool
    {
        $message = ": heartbeat\n\n";

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

        // Check if output was successful by flushing
        if (ob_get_level() > 0) {
            $flushResult = @ob_flush();
        }
        $flushResult = @flush();

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
     * Increment the active connections counter in Redis
     *
     * @param string $sessionId
     * @return void
     */
    private function incrementConnectionCounter(string $sessionId): void
    {
        try {
            $newCount = Redis::incr('sse:active_connections');
            Log::debug("SSE [WL][{$sessionId}]: Incremented connection counter to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("SSE [WL][{$sessionId}]: Failed to increment Redis connection counter", [
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
            Log::debug("SSE [WL][{$sessionId}]: Decremented connection counter to {$newCount}");
        } catch (\Exception $e) {
            Log::warning("SSE [WL][{$sessionId}]: Failed to decrement Redis connection counter", [
                'error' => $e->getMessage()
            ]);
        }
    }
}