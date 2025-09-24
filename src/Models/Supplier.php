<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Supplier Model for Diamond Box Integration
 */
class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'code',
        'description',
        'contact_person',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_number',
        'payment_terms',
        'is_active',
        'rating',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'decimal:1',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the products for the supplier
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    /**
     * Get active products for the supplier
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->active();
    }

    /**
     * Scope to filter active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope to filter by rating
     */
    public function scopeMinRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
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
     * Check if supplier has contact info
     */
    public function hasContactInfo(): bool
    {
        return !empty($this->email) || !empty($this->phone);
    }

    /**
     * Get rating stars (for display)
     */
    public function getRatingStarsAttribute(): string
    {
        if (!$this->rating) {
            return 'No rating';
        }

        $stars = str_repeat('★', (int) $this->rating);
        $emptyStars = str_repeat('☆', 5 - (int) $this->rating);
        
        return $stars . $emptyStars;
    }
}
