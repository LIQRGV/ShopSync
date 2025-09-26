<?php

namespace Liqrgv\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    /**
     * Stream server-sent events
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function events(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () use ($request) {
            // Generate unique session ID for this connection
            $sessionId = $this->generateSessionId();
            $clientIp = $request->ip();
            $userAgent = substr($request->userAgent() ?? 'Unknown', 0, 50);

            // Log connection start
            Log::info("SSE [{$sessionId}]: New connection from {$clientIp} - {$userAgent}");

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
                'timestamp' => date('Y-m-d H:i:s'),
                'client_ip' => $clientIp
            ];

            if (!$this->sendEventWithCheck('connected', $connectionData, null, $sessionId)) {
                Log::error("SSE [{$sessionId}]: Failed initial write, closing immediately");
                return; // Exit if initial write fails
            }

            Log::info("SSE [{$sessionId}]: Connection established successfully");

            // Keep the connection alive and send timestamp every minute
            $lastSent = time();
            $lastHeartbeat = time();
            $counter = 0;
            $failedWrites = 0;
            $maxFailedWrites = 3; // Allow up to 3 failed writes before disconnecting

            while (true) {
                // Check if client disconnected
                if (connection_aborted()) {
                    Log::info("SSE [{$sessionId}]: Connection aborted by client");
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
                        'session_id' => $sessionId
                    ], null, $sessionId);

                    if (!$writeSuccess) {
                        $failedWrites++;
                        Log::warning("SSE [{$sessionId}]: Failed to write timestamp event #{$counter}. Failed writes: {$failedWrites}");

                        if ($failedWrites >= $maxFailedWrites) {
                            Log::error("SSE [{$sessionId}]: Too many failed writes ({$failedWrites}). Disconnecting.");
                            break;
                        }
                    } else {
                        $failedWrites = 0; // Reset counter on successful write
                        $lastSent = $currentTime;
                        Log::debug("SSE [{$sessionId}]: Sent timestamp event #{$counter}");
                    }
                }

                // Send heartbeat every 30 seconds to keep connection alive
                if ($currentTime - $lastHeartbeat >= 30) {
                    $heartbeatSuccess = $this->sendHeartbeatWithCheck($sessionId);

                    if (!$heartbeatSuccess) {
                        $failedWrites++;
                        Log::warning("SSE [{$sessionId}]: Failed to write heartbeat. Failed writes: {$failedWrites}");

                        if ($failedWrites >= $maxFailedWrites) {
                            Log::error("SSE [{$sessionId}]: Too many failed heartbeat writes. Disconnecting.");
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

            // Log final connection status
            $duration = time() - ($lastSent - (60 * $counter));
            Log::info("SSE [{$sessionId}]: Connection closed after {$duration} seconds, sent {$counter} timestamp events");
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send an SSE event
     *
     * @param string $event
     * @param mixed $data
     * @param string|null $id
     * @return void
     */
    private function sendEvent(string $event, $data, ?string $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }

        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";

        ob_flush();
        flush();
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

        // Check if output was successful by flushing
        if (ob_get_level() > 0) {
            $flushResult = @ob_flush();
        }
        $flushResult = @flush();

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
}