<?php

namespace TheDiamondBox\ShopSync\Console\Commands;

use Illuminate\Console\Command;
use TheDiamondBox\ShopSync\Models\Product;
use TheDiamondBox\ShopSync\Services\SseBroadcastListener;

class TestProductBroadcasting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:test-broadcasting
                           {--action=all : Action to test (create, update, delete, all)}
                           {--count=1 : Number of test products to create}
                           {--delay=2 : Delay between operations in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test real Product model broadcasting via SSE';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->option('action');
        $count = (int) $this->option('count');
        $delay = (int) $this->option('delay');

        $this->info("Testing Product model broadcasting...");
        $this->line("Action: {$action}");
        $this->line("Count: {$count}");
        $this->line("Delay: {$delay}s between operations");
        $this->line("");

        // Show current stream status
        $this->showStreamStatus();

        switch ($action) {
            case 'create':
                $this->testProductCreation($count, $delay);
                break;

            case 'update':
                $this->testProductUpdate($count, $delay);
                break;

            case 'delete':
                $this->testProductDeletion($count, $delay);
                break;

            case 'all':
                $this->testAllOperations($count, $delay);
                break;

            default:
                $this->error("Unknown action: {$action}");
                $this->line("Available actions: create, update, delete, all");
                return 1;
        }

        $this->line("");
        $this->showStreamStatus();

        return 0;
    }

    /**
     * Test product creation
     *
     * @param int $count
     * @param int $delay
     * @return void
     */
    private function testProductCreation(int $count, int $delay): void
    {
        $this->info("Creating {$count} test products...");

        for ($i = 1; $i <= $count; $i++) {
            $product = Product::create([
                'name' => "Test Product #{$i} - " . now()->format('H:i:s'),
                'sku_prefix' => 'TEST',
                'rol_number' => 'PROD-' . str_pad($i, 4, '0', STR_PAD_LEFT) . '-' . time(),
                'status' => 'active',
                'sell_status' => 'available',
                'price' => rand(10, 500) + (rand(0, 99) / 100),
                'description' => "This is test product number {$i} created for broadcasting testing",
                'purchase_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
            ]);

            $this->line("✓ Created Product ID: {$product->id} - {$product->name}");

            if ($i < $count && $delay > 0) {
                sleep($delay);
            }
        }

        $this->info("✓ Product creation test completed!");
    }

    /**
     * Test product updates
     *
     * @param int $count
     * @param int $delay
     * @return void
     */
    private function testProductUpdate(int $count, int $delay): void
    {
        // Get existing products or create some if none exist
        $products = Product::limit($count)->get();

        if ($products->count() === 0) {
            $this->comment("No products found, creating test products first...");
            $this->testProductCreation(max(1, $count), 0);
            $products = Product::limit($count)->get();
        }

        $this->info("Updating {$products->count()} products...");

        foreach ($products as $index => $product) {
            $updateData = $this->generateUpdateData();
            $oldData = $product->only(array_keys($updateData));

            $product->update($updateData);

            $this->line("✓ Updated Product ID: {$product->id}");
            $this->line("  Changes:");
            foreach ($updateData as $field => $newValue) {
                $oldValue = $oldData[$field] ?? 'null';
                $this->line("    {$field}: {$oldValue} → {$newValue}");
            }

            if (($index + 1) < $products->count() && $delay > 0) {
                sleep($delay);
            }
        }

        $this->info("✓ Product update test completed!");
    }

    /**
     * Test product deletion
     *
     * @param int $count
     * @param int $delay
     * @return void
     */
    private function testProductDeletion(int $count, int $delay): void
    {
        // Get test products (only delete products with 'test' in the name)
        $products = Product::where('name', 'like', '%Test Product%')
                          ->limit($count)
                          ->get();

        if ($products->count() === 0) {
            $this->comment("No test products found to delete. Creating some first...");
            $this->testProductCreation($count, 0);
            $products = Product::where('name', 'like', '%Test Product%')
                              ->limit($count)
                              ->get();
        }

        if ($this->confirm("Are you sure you want to delete {$products->count()} test products?")) {
            $this->info("Deleting {$products->count()} test products...");

            foreach ($products as $index => $product) {
                $productName = $product->name;
                $productId = $product->id;

                $product->delete();

                $this->line("✓ Deleted Product ID: {$productId} - {$productName}");

                if (($index + 1) < $products->count() && $delay > 0) {
                    sleep($delay);
                }
            }

            $this->info("✓ Product deletion test completed!");
        } else {
            $this->line("Product deletion cancelled.");
        }
    }

    /**
     * Test all operations
     *
     * @param int $count
     * @param int $delay
     * @return void
     */
    private function testAllOperations(int $count, int $delay): void
    {
        $this->info("Testing all Product operations...");

        // 1. Create products
        $this->line("\n1. Testing Product Creation:");
        $this->testProductCreation($count, $delay);

        if ($delay > 0) {
            $this->line("\nWaiting {$delay} seconds before updates...");
            sleep($delay);
        }

        // 2. Update products
        $this->line("\n2. Testing Product Updates:");
        $this->testProductUpdate($count, $delay);

        if ($delay > 0) {
            $this->line("\nWaiting {$delay} seconds before deletions...");
            sleep($delay);
        }

        // 3. Delete products (only test products)
        $this->line("\n3. Testing Product Deletions:");
        $this->testProductDeletion($count, $delay);

        $this->info("\n✓ All Product operations tested!");
    }

    /**
     * Generate random update data
     *
     * @return array
     */
    private function generateUpdateData(): array
    {
        $updates = [];
        $possibleUpdates = [
            'price' => rand(10, 500) + (rand(0, 99) / 100),
            'sale_price' => rand(5, 250) + (rand(0, 99) / 100),
            'status' => collect(['active', 'inactive', 'draft'])->random(),
            'sell_status' => collect(['available', 'sold', 'reserved'])->random(),
            'description' => 'Updated description at ' . now()->format('H:i:s'),
        ];

        // Randomly select 1-3 fields to update
        $fieldsToUpdate = collect(array_keys($possibleUpdates))
                            ->random(rand(1, 3))
                            ->toArray();

        foreach ($fieldsToUpdate as $field) {
            $updates[$field] = $possibleUpdates[$field];
        }

        return $updates;
    }

    /**
     * Show current stream status
     *
     * @return void
     */
    private function showStreamStatus(): void
    {
        $stats = SseBroadcastListener::getStreamStats();

        $this->line("📊 Current Stream Status:");
        $this->line("Stream length: " . ($stats['stream_length'] ?? 0));
        $this->line("Consumer groups: " . ($stats['consumer_groups'] ?? 0));

        if (!empty($stats['groups'])) {
            foreach ($stats['groups'] as $group) {
                $groupName = $group[1] ?? 'unknown';
                $consumers = $group[3] ?? 0;
                $pending = $group[5] ?? 0;
                $this->line("  Group '{$groupName}': {$consumers} consumers, {$pending} pending");
            }
        }

        $this->line("");
    }
}