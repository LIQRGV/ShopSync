<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Brand Model for Diamond Box Integration
 */
class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
     * Get products count
     */
    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }

    /**
     * Get active products count
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->activeProducts()->count();
    }

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo;
    }

    /**
     * Check if brand has logo
     */
    public function hasLogo(): bool
    {
        return !empty($this->logo);
    }
}
