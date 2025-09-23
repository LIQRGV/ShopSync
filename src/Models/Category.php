<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category Model for Diamond Box Integration
 */
class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'parent_id',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the products for the category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get active products for the category
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->active();
    }

    /**
     * Scope to filter active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the parent category
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Get all ancestors (breadcrumb trail)
     */
    public function ancestors()
    {
        $ancestors = collect();
        $category = $this;
        
        while ($category->parent) {
            $category = $category->parent;
            $ancestors->prepend($category);
        }
        
        return $ancestors;
    }

    /**
     * Get all descendants
     */
    public function descendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }
        
        return $descendants;
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
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
}
