<?php

namespace Liqrgv\ShopSync\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;
    public $changes;
    public $updateType;

    /**
     * Create a new event instance.
     *
     * @param mixed $product The updated product
     * @param array $changes Array of changed attributes
     * @param string $updateType Type of update (created, updated, deleted, etc.)
     */
    public function __construct($product, array $changes = [], string $updateType = 'updated')
    {
        $this->product = $product;
        $this->changes = $changes;
        $this->updateType = $updateType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('products.updates');
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'product.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'product_id' => $this->product->id ?? $this->product['id'] ?? null,
            'product' => $this->product,
            'changes' => $this->changes,
            'update_type' => $this->updateType,
            'timestamp' => now()->toISOString(),
            'message' => "Product {$this->updateType}: " . ($this->product->name ?? $this->product['name'] ?? 'Unknown Product')
        ];
    }
}