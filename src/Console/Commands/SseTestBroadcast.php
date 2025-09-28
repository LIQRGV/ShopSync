<?php

namespace Liqrgv\ShopSync\Console\Commands;

use Illuminate\Console\Command;
use Liqrgv\ShopSync\Services\ProductBroadcastService;
use Liqrgv\ShopSync\Services\SseBroadcastListener;

class SseTestBroadcast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sse:test-broadcast
                           {--type=product : Type of test (product, custom, queue-status)}
                           {--count=1 : Number of test messages to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the SSE broadcast system';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->option('type');
        $count = (int) $this->option('count');

        $this->info("Running SSE broadcast test: {$type}");

        switch ($type) {
            case 'product':
                $this->testProductBroadcast($count);
                break;

            case 'custom':
                $this->testCustomMessage($count);
                break;

            case 'queue-status':
                $this->showQueueStatus();
                break;

            default:
                $this->error("Unknown test type: {$type}");
                $this->line("Available types: product, custom, queue-status");
                return 1;
        }

        return 0;
    }

    /**
     * Test product broadcast
     *
     * @param int $count
     * @return void
     */
    private function testProductBroadcast(int $count): void
    {
        $this->info("Sending {$count} test product broadcast(s)...");

        for ($i = 1; $i <= $count; $i++) {
            $testProduct = [
                'id' => 'test_product_' . time() . '_' . $i,
                'name' => "Test Product #{$i}",
                'price' => rand(10, 100) + (rand(0, 99) / 100),
                'stock' => rand(0, 200),
                'category' => 'test-category',
                'description' => "This is test product number {$i}"
            ];

            $changes = [
                'price' => 'Updated in test',
                'stock' => 'Updated in test'
            ];

            ProductBroadcastService::broadcastProductUpdate($testProduct, $changes, 'test_update');

            $this->line("✓ Sent test broadcast #{$i} for product: {$testProduct['id']}");

            if ($count > 1 && $i < $count) {
                sleep(1); // Small delay between messages
            }
        }

        $this->info("✓ All test broadcasts sent successfully!");
        $this->line("Check your SSE endpoint to see the events in real-time.");
    }

    /**
     * Test custom message
     *
     * @param int $count
     * @return void
     */
    private function testCustomMessage(int $count): void
    {
        $this->info("Sending {$count} test custom message(s)...");

        for ($i = 1; $i <= $count; $i++) {
            $customData = [
                'event' => 'test.custom',
                'data' => [
                    'message' => "Test custom message #{$i}",
                    'timestamp' => now()->toISOString(),
                    'test_id' => 'custom_' . time() . '_' . $i,
                    'random_data' => [
                        'number' => rand(1, 1000),
                        'boolean' => rand(0, 1) === 1,
                        'array' => ['a', 'b', 'c']
                    ]
                ]
            ];

            ProductBroadcastService::queueCustomMessage($customData);

            $this->line("✓ Sent custom message #{$i}");

            if ($count > 1 && $i < $count) {
                sleep(1);
            }
        }

        $this->info("✓ All custom messages sent successfully!");
    }

    /**
     * Show stream status
     *
     * @return void
     */
    private function showQueueStatus(): void
    {
        $this->info("SSE Broadcast Stream Status:");

        $streamLength = SseBroadcastListener::getQueueLength();
        $stats = SseBroadcastListener::getStreamStats();

        $this->line("Stream length: {$streamLength} messages");

        if (isset($stats['consumer_groups'])) {
            $this->line("Consumer groups: {$stats['consumer_groups']}");
        }

        if (isset($stats['first_entry_id']) && $stats['first_entry_id']) {
            $this->line("First entry ID: {$stats['first_entry_id']}");
            $this->line("Last entry ID: {$stats['last_entry_id']}");
        }

        if (!empty($stats['groups'])) {
            $this->line("");
            $this->line("Consumer Groups:");
            foreach ($stats['groups'] as $group) {
                $groupName = $group[1] ?? 'unknown';
                $consumers = $group[3] ?? 0;
                $pending = $group[5] ?? 0;
                $this->line("  - {$groupName}: {$consumers} consumers, {$pending} pending");
            }
        }

        if ($streamLength > 0) {
            $this->line("");
            $this->line("There are messages in the stream.");

            if ($this->confirm('Clear the stream?')) {
                $cleared = SseBroadcastListener::clearQueue();
                $this->info("✓ Cleared {$cleared} messages from the stream.");
            }
        } else {
            $this->line("Stream is empty.");
        }

        // Show some general info
        $this->line("");
        $this->line("To start the broadcast listener:");
        $this->line("  php artisan sse:listen-broadcasts");
        $this->line("");
        $this->line("To test the SSE endpoint, visit:");
        $this->line("  GET /api/v1/sse/events");
        $this->line("");
        $this->line("Each SSE connection will create its own consumer in the 'sse-consumers' group.");
        $this->line("This ensures all connected clients receive the same broadcast messages.");
    }
}