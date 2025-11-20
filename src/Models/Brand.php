<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Brand Model for Diamond Box Integration
 */
class Brand extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'store_id',
        'name',
        'category_id',
        'sub_category_id',
        'status',
        'sortby',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Get the products for the brand
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /**
     * Get active products for the brand
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->active();
    }

    /**
     * Scope to filter active brands
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sortby')->orderBy('name');
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
     * Get logo URL (compatibility method)
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ?? null;
    }

    /**
     * Check if brand has logo
     */
    public function hasLogo(): bool
    {
        return !empty($this->logo);
    }
}
