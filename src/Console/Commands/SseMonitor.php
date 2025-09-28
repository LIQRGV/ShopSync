<?php

namespace Liqrgv\ShopSync\Console\Commands;

use Illuminate\Console\Command;
use Liqrgv\ShopSync\Services\SseBroadcastListener;
use Illuminate\Support\Facades\Redis;

class SseMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sse:monitor
                           {--refresh=5 : Refresh interval in seconds}
                           {--once : Run once and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor SSE connections and broadcast stream status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $refreshInterval = (int) $this->option('refresh');
        $runOnce = $this->option('once');

        do {
            $this->displayStatus();

            if (!$runOnce) {
                $this->line("");
                $this->line("Press Ctrl+C to stop monitoring...");
                sleep($refreshInterval);

                // Clear screen for next iteration
                if (PHP_OS_FAMILY !== 'Windows') {
                    system('clear');
                } else {
                    system('cls');
                }
            }
        } while (!$runOnce);

        return 0;
    }

    /**
     * Display current SSE status
     *
     * @return void
     */
    private function displayStatus(): void
    {
        $this->info("=== SSE Broadcast Monitor ===");
        $this->line("Time: " . now()->format('Y-m-d H:i:s'));
        $this->line("");

        // Active SSE connections
        $this->showConnectionStatus();
        $this->line("");

        // Stream status
        $this->showStreamStatus();
        $this->line("");

        // Consumer group details
        $this->showConsumerDetails();
    }

    /**
     * Show active SSE connection count
     *
     * @return void
     */
    private function showConnectionStatus(): void
    {
        try {
            $activeConnections = Redis::get('sse:active_connections') ?? 0;
            $this->info("Active SSE Connections: {$activeConnections}");

            if ($activeConnections > 0) {
                $this->line("✓ SSE clients are connected and ready to receive broadcasts");
            } else {
                $this->comment("⚠ No active SSE connections");
            }
        } catch (\Exception $e) {
            $this->error("Failed to get connection count: " . $e->getMessage());
        }
    }

    /**
     * Show Redis stream status
     *
     * @return void
     */
    private function showStreamStatus(): void
    {
        $stats = SseBroadcastListener::getStreamStats();

        $this->info("Broadcast Stream Status:");

        if (isset($stats['error'])) {
            $this->comment("Stream not yet created (will be created on first broadcast)");
            return;
        }

        $this->line("Stream length: " . ($stats['stream_length'] ?? 0) . " messages");
        $this->line("Consumer groups: " . ($stats['consumer_groups'] ?? 0));

        if (isset($stats['first_entry_id']) && $stats['first_entry_id']) {
            $this->line("First entry: " . $stats['first_entry_id']);
            $this->line("Last entry: " . $stats['last_entry_id']);
        }
    }

    /**
     * Show consumer group details
     *
     * @return void
     */
    private function showConsumerDetails(): void
    {
        try {
            $streamKey = 'products.updates.stream';
            $groups = Redis::xinfo('GROUPS', $streamKey);

            if (empty($groups)) {
                $this->comment("No consumer groups found");
                return;
            }

            $this->info("Consumer Group Details:");

            foreach ($groups as $group) {
                $groupName = $group[1] ?? 'unknown';
                $consumers = $group[3] ?? 0;
                $pending = $group[5] ?? 0;
                $lastDeliveredId = $group[7] ?? 'none';

                $this->line("Group: {$groupName}");
                $this->line("  Active consumers: {$consumers}");
                $this->line("  Pending messages: {$pending}");
                $this->line("  Last delivered: {$lastDeliveredId}");

                // Get consumer details
                if ($consumers > 0) {
                    try {
                        $consumerInfo = Redis::xinfo('CONSUMERS', $streamKey, $groupName);
                        foreach ($consumerInfo as $consumer) {
                            $consumerName = $consumer[1] ?? 'unknown';
                            $pendingCount = $consumer[3] ?? 0;
                            $idle = $consumer[5] ?? 0;

                            $idleSeconds = round($idle / 1000, 1);
                            $this->line("    Consumer: {$consumerName} (pending: {$pendingCount}, idle: {$idleSeconds}s)");
                        }
                    } catch (\Exception $e) {
                        $this->line("    (Could not get consumer details)");
                    }
                }
                $this->line("");
            }

        } catch (\Exception $e) {
            $this->comment("Consumer details not available: " . $e->getMessage());
        }
    }
}