<?php

namespace TheDiamondBox\ShopSync\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * SSE Broadcast Listener Service
 *
 * This service listens to Laravel broadcast events and queues them
 * for SSE streams to pick up and send to connected clients.
 */
class SseBroadcastListener
{
    /**
     * Listen to broadcast events and queue them for SSE
     *
     * @param string $channel The broadcast channel name
     * @param callable $callback Optional callback to process messages
     * @return void
     */
    public static function listen(string $channel = 'products.updates', ?callable $callback = null): void
    {
        try {
            Redis::subscribe([$channel], function ($message, $channelName) use ($callback) {
                Log::debug("SSE Broadcast Listener: Received message on channel {$channelName}");

                try {
                    $data = json_decode($message, true);

                    if (!$data) {
                        Log::warning("SSE Broadcast Listener: Invalid JSON message received", [
                            'message' => $message,
                            'channel' => $channelName
                        ]);
                        return;
                    }

                    // Process the broadcast message
                    static::processBroadcastMessage($data, $channelName);

                    // Call custom callback if provided
                    if ($callback && is_callable($callback)) {
                        $callback($data, $channelName);
                    }

                } catch (\Exception $e) {
                    Log::error("SSE Broadcast Listener: Error processing message", [
                        'error' => $e->getMessage(),
                        'channel' => $channelName,
                        'message' => $message
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error("SSE Broadcast Listener: Failed to subscribe to Redis channel", [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);
        }
    }

    /**
     * Process a broadcast message and add it to Redis stream for all SSE connections
     *
     * @param array $data
     * @param string $channel
     * @return void
     */
    protected static function processBroadcastMessage(array $data, string $channel): void
    {
        try {
            // Extract event information from Laravel's broadcast format
            $eventName = $data['event'] ?? 'unknown';
            $eventData = $data['data'] ?? [];
            $socketId = $data['socket'] ?? null;

            // Prepare message for Redis stream
            $sseMessage = [
                'event' => $eventName,
                'data' => $eventData,
                'channel' => $channel,
                'timestamp' => now()->toISOString(),
                'socket_id' => $socketId
            ];

            // Add to Redis stream (this will be delivered to ALL SSE consumers)
            $messageId = Redis::xadd(
                'products.updates.stream',
                '*',  // Auto-generate ID
                ['data' => json_encode($sseMessage)]
            );

            Log::debug("SSE Broadcast Listener: Added message to stream for all SSE connections", [
                'event' => $eventName,
                'channel' => $channel,
                'message_id' => $messageId
            ]);

        } catch (\Exception $e) {
            Log::error("SSE Broadcast Listener: Failed to process broadcast message", [
                'error' => $e->getMessage(),
                'data' => $data,
                'channel' => $channel
            ]);
        }
    }

    /**
     * Start listening in a background process (for Artisan commands)
     *
     * @param string $channel
     * @return void
     */
    public static function startBackgroundListener(string $channel = 'products.updates'): void
    {
        Log::info("SSE Broadcast Listener: Starting background listener for channel: {$channel}");

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function() {
                Log::info("SSE Broadcast Listener: Received SIGTERM, shutting down gracefully");
                exit(0);
            });

            pcntl_signal(SIGINT, function() {
                Log::info("SSE Broadcast Listener: Received SIGINT, shutting down gracefully");
                exit(0);
            });
        }

        // Start listening (this will run indefinitely)
        static::listen($channel);
    }

    /**
     * Manually add a message to the SSE stream
     *
     * @param array $messageData
     * @return void
     */
    public static function queueMessage(array $messageData): void
    {
        try {
            $sseMessage = [
                'event' => $messageData['event'] ?? 'custom.message',
                'data' => $messageData['data'] ?? [],
                'channel' => $messageData['channel'] ?? 'default',
                'timestamp' => now()->toISOString(),
                'socket_id' => $messageData['socket_id'] ?? null
            ];

            $messageId = Redis::xadd(
                'products.updates.stream',
                '*',
                ['data' => json_encode($sseMessage)]
            );

            Log::debug("SSE Broadcast Listener: Manually added message to stream", [
                'event' => $sseMessage['event'],
                'message_id' => $messageId
            ]);

        } catch (\Exception $e) {
            Log::error("SSE Broadcast Listener: Failed to manually add message to stream", [
                'error' => $e->getMessage(),
                'data' => $messageData
            ]);
        }
    }

    /**
     * Clear the SSE message stream
     *
     * @return int Number of messages cleared
     */
    public static function clearQueue(): int
    {
        try {
            $streamKey = 'products.updates.stream';

            // Get current stream length
            $info = Redis::xinfo('STREAM', $streamKey);
            $count = $info[1] ?? 0; // Index 1 contains the length

            // Delete the entire stream
            Redis::del($streamKey);

            Log::info("SSE Broadcast Listener: Cleared stream", ['messages_cleared' => $count]);

            return $count;
        } catch (\Exception $e) {
            Log::error("SSE Broadcast Listener: Failed to clear stream", [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Get the current stream length
     *
     * @return int
     */
    public static function getQueueLength(): int
    {
        try {
            $streamKey = 'products.updates.stream';
            $info = Redis::xinfo('STREAM', $streamKey);
            return $info[1] ?? 0; // Index 1 contains the length
        } catch (\Exception $e) {
            // Stream might not exist yet
            Log::debug("SSE Broadcast Listener: Failed to get stream length (stream may not exist)", [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Get stream statistics
     *
     * @return array
     */
    public static function getStreamStats(): array
    {
        try {
            $streamKey = 'products.updates.stream';
            $info = Redis::xinfo('STREAM', $streamKey);
            $groups = Redis::xinfo('GROUPS', $streamKey);

            return [
                'stream_length' => $info[1] ?? 0,
                'first_entry_id' => $info[9] ?? null,
                'last_entry_id' => $info[11] ?? null,
                'consumer_groups' => count($groups),
                'groups' => $groups
            ];
        } catch (\Exception $e) {
            return [
                'stream_length' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}