<?php

/**
 * Test script for multiple concurrent SSE connections
 *
 * This script simulates multiple SSE clients connecting simultaneously
 * to test that all connections receive the same broadcast messages.
 */

namespace Liqrgv\ShopSync\Tests;

class SseMultiConnectionTest
{
    private $baseUrl;
    private $connections = [];
    private $receivedMessages = [];

    public function __construct(string $baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Test multiple SSE connections
     *
     * @param int $connectionCount
     * @param int $testDurationSeconds
     * @return array
     */
    public function testMultipleConnections(int $connectionCount = 3, int $testDurationSeconds = 30): array
    {
        echo "Testing {$connectionCount} concurrent SSE connections for {$testDurationSeconds} seconds...\n";
        echo "Base URL: {$this->baseUrl}\n\n";

        // Start multiple SSE connections
        for ($i = 1; $i <= $connectionCount; $i++) {
            $this->startSseConnection($i);
            echo "Started SSE connection #{$i}\n";
            usleep(500000); // Wait 0.5s between connections
        }

        echo "\nAll connections started. Listening for broadcasts...\n";
        echo "You can now send test broadcasts using: php artisan sse:test-broadcast\n\n";

        // Listen for messages
        $startTime = time();
        while ((time() - $startTime) < $testDurationSeconds) {
            $this->readFromConnections();
            usleep(100000); // Check every 100ms
        }

        // Close connections
        $this->closeAllConnections();

        // Analyze results
        return $this->analyzeResults();
    }

    /**
     * Start an SSE connection
     *
     * @param int $connectionId
     * @return void
     */
    private function startSseConnection(int $connectionId): void
    {
        $url = $this->baseUrl . '/api/v1/sse/events';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: text/event-stream',
                    'Cache-Control: no-cache',
                    'Connection: keep-alive'
                ],
                'timeout' => 60
            ]
        ]);

        $stream = fopen($url, 'r', false, $context);

        if ($stream) {
            stream_set_blocking($stream, false);
            $this->connections[$connectionId] = [
                'stream' => $stream,
                'buffer' => '',
                'connected_at' => time()
            ];
            $this->receivedMessages[$connectionId] = [];
        } else {
            echo "Failed to connect SSE connection #{$connectionId}\n";
        }
    }

    /**
     * Read data from all connections
     *
     * @return void
     */
    private function readFromConnections(): void
    {
        foreach ($this->connections as $connectionId => $connection) {
            if (!is_resource($connection['stream'])) {
                continue;
            }

            $data = fread($connection['stream'], 8192);
            if ($data === false || $data === '') {
                continue;
            }

            $this->connections[$connectionId]['buffer'] .= $data;
            $this->processBuffer($connectionId);
        }
    }

    /**
     * Process buffered data for SSE messages
     *
     * @param int $connectionId
     * @return void
     */
    private function processBuffer(int $connectionId): void
    {
        $buffer = &$this->connections[$connectionId]['buffer'];

        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $message = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 2);

            if (trim($message)) {
                $this->processMessage($connectionId, $message);
            }
        }
    }

    /**
     * Process an SSE message
     *
     * @param int $connectionId
     * @param string $message
     * @return void
     */
    private function processMessage(int $connectionId, string $message): void
    {
        $lines = explode("\n", $message);
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

        if ($event && $data) {
            $parsedData = json_decode($data, true);

            $messageInfo = [
                'timestamp' => microtime(true),
                'event' => $event,
                'data' => $parsedData,
                'id' => $id,
                'raw' => $message
            ];

            $this->receivedMessages[$connectionId][] = $messageInfo;

            // Print real-time output
            echo "[Connection #{$connectionId}] Event: {$event}";
            if (isset($parsedData['message'])) {
                echo " - {$parsedData['message']}";
            }
            echo "\n";
        }
    }

    /**
     * Close all connections
     *
     * @return void
     */
    private function closeAllConnections(): void
    {
        echo "\nClosing connections...\n";
        foreach ($this->connections as $connectionId => $connection) {
            if (is_resource($connection['stream'])) {
                fclose($connection['stream']);
            }
        }
        $this->connections = [];
    }

    /**
     * Analyze the test results
     *
     * @return array
     */
    private function analyzeResults(): array
    {
        echo "\n=== Test Results ===\n";

        $totalConnections = count($this->receivedMessages);
        $results = [
            'total_connections' => $totalConnections,
            'messages_per_connection' => [],
            'unique_events' => [],
            'broadcast_delivery_analysis' => []
        ];

        // Count messages per connection
        foreach ($this->receivedMessages as $connectionId => $messages) {
            $count = count($messages);
            $results['messages_per_connection'][$connectionId] = $count;
            echo "Connection #{$connectionId}: {$count} messages received\n";

            // Collect unique events
            foreach ($messages as $message) {
                $eventKey = $message['event'] . '|' . ($message['id'] ?? 'no-id');
                if (!isset($results['unique_events'][$eventKey])) {
                    $results['unique_events'][$eventKey] = [];
                }
                $results['unique_events'][$eventKey][] = $connectionId;
            }
        }

        echo "\n=== Broadcast Delivery Analysis ===\n";

        // Analyze broadcast delivery
        foreach ($results['unique_events'] as $eventKey => $connectionIds) {
            $deliveredTo = count($connectionIds);
            $deliveryRate = ($deliveredTo / $totalConnections) * 100;

            [$event, $id] = explode('|', $eventKey, 2);

            if (strpos($event, 'product.') === 0 || strpos($event, 'broadcast') !== false) {
                $results['broadcast_delivery_analysis'][$eventKey] = [
                    'event' => $event,
                    'id' => $id,
                    'delivered_to' => $deliveredTo,
                    'total_connections' => $totalConnections,
                    'delivery_rate' => $deliveryRate,
                    'connection_ids' => $connectionIds
                ];

                echo "Event: {$event} (ID: {$id})\n";
                echo "  Delivered to: {$deliveredTo}/{$totalConnections} connections ({$deliveryRate}%)\n";

                if ($deliveryRate < 100) {
                    $missedConnections = array_diff(array_keys($this->receivedMessages), $connectionIds);
                    echo "  Missed by connections: " . implode(', ', $missedConnections) . "\n";
                }
            }
        }

        // Summary
        echo "\n=== Summary ===\n";
        $broadcastEvents = count($results['broadcast_delivery_analysis']);
        $perfectDeliveries = 0;

        foreach ($results['broadcast_delivery_analysis'] as $analysis) {
            if ($analysis['delivery_rate'] == 100) {
                $perfectDeliveries++;
            }
        }

        echo "Total broadcast events: {$broadcastEvents}\n";
        echo "Perfect deliveries (100%): {$perfectDeliveries}\n";
        echo "Success rate: " . ($broadcastEvents > 0 ? round(($perfectDeliveries / $broadcastEvents) * 100, 1) : 0) . "%\n";

        if ($perfectDeliveries == $broadcastEvents && $broadcastEvents > 0) {
            echo "✅ All broadcast messages were delivered to all connections!\n";
        } elseif ($broadcastEvents > 0) {
            echo "⚠️  Some messages were not delivered to all connections.\n";
        } else {
            echo "ℹ️  No broadcast messages detected during test period.\n";
        }

        return $results;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $baseUrl = $argv[1] ?? 'http://localhost:8000';
    $connections = (int) ($argv[2] ?? 3);
    $duration = (int) ($argv[3] ?? 30);

    echo "SSE Multi-Connection Test\n";
    echo "Usage: php SseMultiConnectionTest.php [base_url] [connections] [duration]\n\n";

    $test = new SseMultiConnectionTest($baseUrl);
    $results = $test->testMultipleConnections($connections, $duration);

    // Exit with appropriate code
    if (isset($results['broadcast_delivery_analysis']) && !empty($results['broadcast_delivery_analysis'])) {
        $successRate = 0;
        $total = count($results['broadcast_delivery_analysis']);
        foreach ($results['broadcast_delivery_analysis'] as $analysis) {
            if ($analysis['delivery_rate'] == 100) {
                $successRate++;
            }
        }
        exit($successRate == $total ? 0 : 1);
    }
    exit(0);
}