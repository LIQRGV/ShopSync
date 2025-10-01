<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Location Model for Diamond Box Integration
 */
class Location extends Model
{
    use SoftDeletes;

    protected $table = 'locations';

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'is_active',
        'is_warehouse',
        'is_store',
        'capacity',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_warehouse' => 'boolean',
        'is_store' => 'boolean',
        'capacity' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the products for the location
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'location_id');
    }

    /**
     * Get active products for the location
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->active();
    }

    /**
     * Scope to filter active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter warehouse locations
     */
    public function scopeWarehouses($query)
    {
        return $query->where('is_warehouse', true);
    }

    /**
     * Scope to filter store locations
     */
    public function scopeStores($query)
    {
        return $query->where('is_store', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get products count
     */
    public function getProductsCountAttribute(): int
    {
        if (config('products-package.mode') === 'wtm') {
            return $this->attributes['products_count'] ?? 0;
        }
        return $this->products()->count();
    }

    /**
     * Get active products count
     */
    public function getActiveProductsCountAttribute(): int
    {
        if (config('products-package.mode') === 'wtm') {
            return $this->attributes['active_products_count'] ?? 0;
        }
        return $this->activeProducts()->count();
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if location has contact info
     */
    public function hasContactInfo(): bool
    {
        return !empty($this->phone) || !empty($this->email);
    }
}
