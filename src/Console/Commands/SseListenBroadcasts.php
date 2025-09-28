<?php

namespace Liqrgv\ShopSync\Console\Commands;

use Illuminate\Console\Command;
use Liqrgv\ShopSync\Services\SseBroadcastListener;

class SseListenBroadcasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sse:listen-broadcasts
                           {--channel=products.updates : The Redis channel to listen to}
                           {--timeout=0 : Timeout in seconds (0 for infinite)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to broadcast events and queue them for SSE streams';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $channel = $this->option('channel');
        $timeout = (int) $this->option('timeout');

        $this->info("Starting SSE broadcast listener for channel: {$channel}");

        if ($timeout > 0) {
            $this->info("Timeout set to: {$timeout} seconds");
        } else {
            $this->info("Running indefinitely (use Ctrl+C to stop)");
        }

        try {
            // Set timeout if specified
            if ($timeout > 0) {
                set_time_limit($timeout);
            }

            // Start the listener
            SseBroadcastListener::startBackgroundListener($channel);

        } catch (\Exception $e) {
            $this->error("Failed to start SSE broadcast listener: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}