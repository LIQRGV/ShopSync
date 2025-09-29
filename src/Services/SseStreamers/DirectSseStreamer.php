<?php

namespace Liqrgv\ShopSync\Services\SseStreamers;

use Liqrgv\ShopSync\Services\Contracts\SseStreamerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DirectSseStreamer implements SseStreamerInterface
{
    const MESSAGE_INTERVAL = 10;
    const CONNECTION_TIMEOUT = 600; // 10 minutes in seconds
    const HEARTBEAT_DELAY = 60; // Send heartbeat every 60 seconds
    /**
     * Queue of broadcast messages to send via SSE
     *
     * @var array
     */
    private $broadcastQueue = [];

    /**
     * Redis connection for listening to broadcast channels
     *
     * @var mixed
     */
    private $redisListener;

    /**
     * Last message ID processed to avoid duplicates
     *
     * @var string|null
     */
    private $lastProcessedMessageId = null;
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

        // Initialize Redis broadcast listener
        $this->initializeRedisListener($sessionId);

        Log::info("SSE [WL][{$sessionId}]: Connection established successfully");

        // Keep the connection alive and send timestamp every minute
        $lastSent = time();
        $lastHeartbeat = time();
        $counter = 0;
        $failedWrites = 0;
        $maxFailedWrites = 3; // Allow up to 3 failed writes before disconnecting
        $connectionDecrementedOnExit = false;
        $connectionStartTime = time();

        while (true) {
            // Check for connection timeout
            if (time() - $connectionStartTime >= self::CONNECTION_TIMEOUT) {
                Log::info("SSE [WL][{$sessionId}]: Connection timeout reached (" . self::CONNECTION_TIMEOUT . " seconds)");
                $this->decrementConnectionCounter($sessionId);
                $connectionDecrementedOnExit = true;
                break;
            }
            // Check if client disconnected
            if (connection_aborted()) {
                Log::info("SSE [WL][{$sessionId}]: Connection aborted by client");
                $this->decrementConnectionCounter($sessionId);
                $connectionDecrementedOnExit = true;
                break;
            }

            $currentTime = time();

            // Process any pending broadcast messages
            $this->processBroadcastQueue($sessionId, $failedWrites, $maxFailedWrites, $connectionDecrementedOnExit);

            // Break if connection was closed due to failed writes
            if ($connectionDecrementedOnExit) {
                break;
            }

            // Send timestamp event every 60 seconds
            if ($currentTime - $lastSent >= self::MESSAGE_INTERVAL) {
                $counter++;
                $writeSuccess = $this->sendEventWithCheck('timestamp', [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'unix_timestamp' => $currentTime,
                    'counter' => $counter,
                    'session_id' => $sessionId,
                    'mode' => 'wl'
                ], null, $sessionId);

                Log::info("connection aborted", [connection_aborted(), connection_status()]);

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

            // Send heartbeat every HEARTBEAT_DELAY seconds to keep connection alive
            if ($currentTime - $lastHeartbeat >= self::HEARTBEAT_DELAY) {
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

                    $this->extendSessionTTL($sessionId);
                }
            }

            // Sleep for 1 second before checking again
            sleep(1);
        }

        // Cleanup Redis listener
        $this->cleanupRedisListener($sessionId);

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
        // Pre-connection test: send a minimal ping to test the connection
        if (!$this->testConnectionHealth($sessionId)) {
            return false;
        }

        // Build the SSE message
        $message = '';
        if ($id !== null) {
            $message .= "id: {$id}\n";
        }
        $message .= "event: {$event}\n";
        $message .= "data: " . json_encode($data) . "\n\n";

        $writeSuccess = true;

        // Set error handler to catch write errors
        set_error_handler(function($errno, $errstr) use (&$writeSuccess) {
            if (strpos($errstr, 'failed to write') !== false ||
                strpos($errstr, 'Broken pipe') !== false ||
                strpos($errstr, 'Connection reset') !== false) {
                $writeSuccess = false;
                return true;
            }
            return false;
        });

        // Attempt to write
        echo $message;

        // Flush output (flush() returns void, so we can't check return value)
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();

        // Restore error handler
        restore_error_handler();

        // Check for socket-level errors after write
        $socketError = $this->checkSocketErrors();
        if ($socketError) {
            if ($sessionId) {
                Log::debug("SSE [WL][{$sessionId}]: Socket error detected: {$socketError}");
            }
            return false;
        }

        // If write failed during echo operation
        if (!$writeSuccess) {
            return false;
        }

        // Check stream metadata for connection status
        if (!$this->validateStreamStatus()) {
            return false;
        }

        // Final connection check
        if (connection_aborted()) {
            return false;
        }

        return true;
    }

    /**
     * Test connection health with a minimal write
     *
     * @param string|null $sessionId
     * @return bool True if connection is healthy, false if broken
     */
    private function testConnectionHealth(?string $sessionId = null): bool
    {
        // Send a minimal comment to test connection
        echo ": ping\n\n";

        // Try to flush and detect immediate failures
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();

        // Check for immediate connection abort
        if (connection_aborted()) {
            return false;
        }

        // Check for any errors from the ping write
        $socketError = $this->checkSocketErrors();
        if ($socketError) {
            return false;
        }

        return true;
    }

    /**
     * Check for socket-level errors
     *
     * @return string|null Error description if found, null if no errors
     */
    private function checkSocketErrors(): ?string
    {
        $lastError = error_get_last();

        if ($lastError && isset($lastError['message'])) {
            $errorMessage = $lastError['message'];

            // Check for connection-related errors
            $connectionErrors = [
                'Broken pipe',
                'Connection reset',
                'No such file or directory',
                'Bad file descriptor',
                'Connection refused',
                'Resource temporarily unavailable'
            ];

            foreach ($connectionErrors as $errorPattern) {
                if (strpos($errorMessage, $errorPattern) !== false) {
                    return $errorPattern;
                }
            }
        }

        return null;
    }

    /**
     * Validate stream status using metadata
     *
     * @return bool True if stream is healthy, false if broken
     */
    private function validateStreamStatus(): bool
    {
        // Check STDOUT stream metadata if available
        if (is_resource(STDOUT)) {
            $streamMeta = stream_get_meta_data(STDOUT);

            if ($streamMeta['eof'] || $streamMeta['timed_out']) {
                return false;
            }

            // Check if stream is still writable
            if (isset($streamMeta['mode']) && strpos($streamMeta['mode'], 'w') === false) {
                return false;
            }
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

    /**
     * Initialize Redis listener for broadcast messages
     *
     * @param string $sessionId
     * @return void
     */
    private function initializeRedisListener(string $sessionId): void
    {
        try {
            // Create a separate Redis connection for reading
            $this->redisListener = Redis::connection('default');

            // Set up unique consumer group for this SSE connection (for broadcasting)
            $streamKey = 'products.updates.stream';
            $consumerGroup = "sse-group-{$sessionId}";
            $consumerName = "consumer-{$sessionId}";

            // Try to create unique consumer group for this session
            try {
                $this->redisListener->xgroup('CREATE', $streamKey, $consumerGroup, '0', true);
            } catch (\Exception $e) {
                // Group likely already exists, which is fine
            }

            Log::debug("SSE [WL][{$sessionId}]: Initialized Redis stream listener", [
                'stream' => $streamKey,
                'group' => $consumerGroup,
                'consumer' => $consumerName
            ]);

        } catch (\Exception $e) {
            Log::warning("SSE [WL][{$sessionId}]: Failed to initialize Redis listener", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process broadcast messages from Redis queue
     *
     * @param string $sessionId
     * @param int &$failedWrites
     * @param int $maxFailedWrites
     * @param bool &$connectionDecrementedOnExit
     * @return void
     */
    private function processBroadcastQueue(string $sessionId, int &$failedWrites, int $maxFailedWrites, bool &$connectionDecrementedOnExit): void
    {
        try {
            // Check for new broadcast messages (non-blocking)
            $this->checkForBroadcastMessages($sessionId);

            // Process queued messages
            while (!empty($this->broadcastQueue)) {
                $message = array_shift($this->broadcastQueue);

                $writeSuccess = $this->sendEventWithCheck(
                    $message['event'] ?? 'broadcast',
                    $message['data'] ?? [],
                    $message['id'] ?? null,
                    $sessionId
                );

                if (!$writeSuccess) {
                    $failedWrites++;
                    Log::warning("SSE [WL][{$sessionId}]: Failed to write broadcast message. Failed writes: {$failedWrites}");

                    if ($failedWrites >= $maxFailedWrites) {
                        Log::error("SSE [WL][{$sessionId}]: Too many failed writes for broadcast messages. Disconnecting.");
                        $this->decrementConnectionCounter($sessionId);
                        $connectionDecrementedOnExit = true;
                        break;
                    }
                } else {
                    $failedWrites = 0; // Reset counter on successful write
                    Log::debug("SSE [WL][{$sessionId}]: Successfully sent broadcast message: {$message['event']}");
                }
            }
        } catch (\Exception $e) {
            Log::warning("SSE [WL][{$sessionId}]: Error processing broadcast queue", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check for new broadcast messages from Redis streams (non-blocking)
     *
     * @param string $sessionId
     * @return void
     */
    private function checkForBroadcastMessages(string $sessionId): void
    {
        if (!$this->redisListener) {
            return;
        }

        try {
            $streamKey = 'products.updates.stream';
            // Use unique consumer group per session for broadcasting
            $consumerGroup = "sse-group-{$sessionId}";
            $consumerName = "consumer-{$sessionId}";

            // Read new messages from the stream with very short timeout
            $messages = $this->redisListener->xreadgroup(
                $consumerGroup,
                $consumerName,
                [$streamKey => '>'],
                1,  // Count: read 1 message at a time
                100 // Block for 100ms max
            );

            if (!empty($messages[$streamKey])) {
                foreach ($messages[$streamKey] as $messageId => $fields) {
                    $this->processStreamMessage($messageId, $fields, $sessionId);
                }
            }

        } catch (\Exception $e) {
            // Log but don't break the SSE stream for Redis issues
            Log::debug("SSE [WL][{$sessionId}]: Redis stream check failed (non-critical)", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process a message from Redis stream
     *
     * @param string $messageId
     * @param array $fields
     * @param string $sessionId
     * @return void
     */
    private function processStreamMessage(string $messageId, array $fields, string $sessionId): void
    {
        try {
            // Decode the message data
            $messageData = json_decode($fields['data'] ?? '{}', true);

            if ($messageData) {
                // Add to our internal queue for processing
                $this->broadcastQueue[] = [
                    'event' => $messageData['event'] ?? 'product.updated',
                    'data' => $messageData['data'] ?? $messageData,
                    'id' => $messageId
                ];

                Log::debug("SSE [WL][{$sessionId}]: Processed stream message", [
                    'message_id' => $messageId,
                    'event' => $messageData['event'] ?? 'unknown'
                ]);

                // Acknowledge the message for this session's consumer group
                $this->redisListener->xack(
                    'products.updates.stream',
                    "sse-group-{$sessionId}",
                    [$messageId]
                );
            }

        } catch (\Exception $e) {
            Log::warning("SSE [WL][{$sessionId}]: Failed to process stream message", [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup Redis listener resources
     *
     * @param string $sessionId
     * @return void
     */
    private function cleanupRedisListener(string $sessionId): void
    {
        try {
            if ($this->redisListener) {
                // Clean up the entire consumer group for this session
                $consumerGroup = "sse-group-{$sessionId}";
                $this->redisListener->xgroup('DESTROY', 'products.updates.stream', $consumerGroup);

                Log::debug("SSE [WL][{$sessionId}]: Cleaned up Redis stream consumer group");
            }
        } catch (\Exception $e) {
            Log::debug("SSE [WL][{$sessionId}]: Redis cleanup failed (non-critical)", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add a broadcast message to the queue
     * This method can be called from external sources
     *
     * @param array $messageData
     * @return void
     */
    public function queueBroadcastMessage(array $messageData): void
    {
        $this->broadcastQueue[] = [
            'event' => $messageData['event'] ?? 'broadcast',
            'data' => $messageData['data'] ?? [],
            'id' => $messageData['id'] ?? uniqid('broadcast_')
        ];
    }
}