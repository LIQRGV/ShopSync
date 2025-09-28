<?php

namespace Liqrgv\ShopSync\Observers;

use Liqrgv\ShopSync\Models\Product;
use Liqrgv\ShopSync\Events\ProductUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Product Observer
 *
 * Handles product model events and broadcasts them via SSE
 */
class ProductObserver
{
    /**
     * Handle the Product "created" event.
     *
     * @param Product $product
     * @return void
     */
    public function created(Model $product)
    {
        $this->broadcastProductEvent($product, [], 'created');
    }

    /**
     * Handle the Product "updated" event.
     *
     * @param Product $product
     * @return void
     */
    public function updated(Product $product)
    {
        // Get the changes that were made
        $changes = $this->formatChanges($product->getChanges(), $product->getOriginal());

        $this->broadcastProductEvent($product, $changes, 'updated');
    }

    /**
     * Handle the Product "deleted" event.
     *
     * @param Product $product
     * @return void
     */
    public function deleted(Product $product)
    {
        $this->broadcastProductEvent($product, [], 'deleted');
    }

    /**
     * Handle the Product "restored" event.
     *
     * @param Product $product
     * @return void
     */
    public function restored(Product $product)
    {
        $this->broadcastProductEvent($product, [], 'restored');
    }

    /**
     * Broadcast a product event via SSE
     *
     * @param Product $product
     * @param array $changes
     * @param string $eventType
     * @return void
     */
    private function broadcastProductEvent(Product $product, array $changes, string $eventType): void
    {
        try {
            // Create the broadcast event
            $event = new ProductUpdated($product, $changes, $eventType);

            // Broadcast the event
            broadcast($event);

            Log::info("Product Observer: Broadcasted {$eventType} event", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'event_type' => $eventType,
                'changes_count' => count($changes),
                'changes' => array_keys($changes)
            ]);

        } catch (\Exception $e) {
            Log::error("Product Observer: Failed to broadcast {$eventType} event", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Format changes for broadcasting
     *
     * @param array $changes
     * @param array $original
     * @return array
     */
    private function formatChanges(array $changes, array $original): array
    {
        $formattedChanges = [];

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            // Handle special fields
            $formattedChanges[$field] = $this->formatFieldChange($field, $oldValue, $newValue);
        }

        return $formattedChanges;
    }

    /**
     * Format a specific field change
     *
     * @param string $field
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return array
     */
    private function formatFieldChange(string $field, $oldValue, $newValue): array
    {
        $change = [
            'old' => $oldValue,
            'new' => $newValue
        ];

        // Add special formatting for specific fields
        switch ($field) {
            case 'price':
            case 'sale_price':
            case 'cost_price':
            case 'trade_price':
                $change['old_formatted'] = $oldValue ? '£' . number_format($oldValue, 2) : null;
                $change['new_formatted'] = $newValue ? '£' . number_format($newValue, 2) : null;
                $change['difference'] = $newValue - $oldValue;
                $change['percentage_change'] = $oldValue > 0 ? (($newValue - $oldValue) / $oldValue) * 100 : null;
                break;

            case 'status':
            case 'sell_status':
                $change['status_change'] = "{$oldValue} → {$newValue}";
                break;

            case 'category_id':
            case 'brand_id':
            case 'location_id':
            case 'supplier_id':
                // Could add relationship name resolution here if needed
                $change['relationship_field'] = str_replace('_id', '', $field);
                break;

            case 'purchase_date':
                if ($oldValue) {
                    $change['old_formatted'] = \Carbon\Carbon::parse($oldValue)->format('d/m/Y');
                }
                if ($newValue) {
                    $change['new_formatted'] = \Carbon\Carbon::parse($newValue)->format('d/m/Y');
                }
                break;
        }

        return $change;
    }

    /**
     * Get important field changes for priority notifications
     *
     * @param array $changes
     * @return array
     */
    private function getImportantChanges(array $changes): array
    {
        $importantFields = [
            'price', 'sale_price', 'status', 'sell_status',
            'name', 'sku_prefix', 'rol_number'
        ];

        return array_intersect_key($changes, array_flip($importantFields));
    }

    /**
     * Determine if changes should trigger a high-priority broadcast
     *
     * @param array $changes
     * @return bool
     */
    private function isHighPriorityChange(array $changes): bool
    {
        $highPriorityFields = ['price', 'sale_price', 'status', 'sell_status'];

        return !empty(array_intersect(array_keys($changes), $highPriorityFields));
    }
}