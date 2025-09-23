<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product Model
 *
 * Unified product model with comprehensive field mapping and relationships.
 * Supports all 23 product fields for complete e-commerce functionality.
 */
class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'sku_prefix',
        'rol_number',
        'sku_custom_ref',
        'status',
        'sell_status',
        'purchase_date',
        'cost_price',
        'price',
        'sale_price',
        'trade_price',
        'vat_scheme',
        'image',
        'original_image',
        'description',
        'seo_keywords',
        'slug',
        'seo_description',
        'related_products',
        'category_id',
        'brand_id',
        'location_id',
        'supplier_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'trade_price' => 'decimal:2',
        'purchase_date' => 'date',
        'related_products' => 'array',
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'location_id' => 'integer',
        'supplier_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'cost_price', // Hide cost price from API responses
    ];

    /**
     * Get the category that owns the product
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the brand that owns the product
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Get the location that owns the product
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the supplier that owns the product
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the attributes for the product
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_attributes',
            'product_id',
            'attribute_id'
        )->withPivot('value')
          ->withTimestamps()
          ->using(ProductAttribute::class);
    }

    /**
     * Get the product attributes (pivot records) for the product
     */
    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class, 'product_id');
    }

    /**
     * Scope to filter active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by sell status
     */
    public function scopeBySellStatus($query, $sellStatus)
    {
        return $query->where('sell_status', $sellStatus);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to filter by brand
     */
    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope to filter by price range
     */
    public function scopePriceRange($query, $minPrice = null, $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * Get the full SKU (combines prefix and value)
     */
    public function getFullSkuAttribute(): string
    {
        return $this->sku_prefix . $this->rol_number;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format($this->price, 2);
    }

    /**
     * Get formatted sale price
     */
    public function getFormattedSalePriceAttribute(): ?string
    {
        return $this->sale_price ? '£' . number_format($this->sale_price, 2) : null;
    }

    /**
     * Check if product is on sale
     */
    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    /**
     * Get the effective selling price (sale price if available, otherwise regular price)
     */
    public function getEffectivePriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Get the image URL with fallback
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ?: $this->original_image;
    }

    /**
     * Check if product has image
     */
    public function hasImage(): bool
    {
        return !empty($this->image) || !empty($this->original_image);
    }

    /**
     * Get related products as collection
     */
    public function getRelatedProductsCollectionAttribute()
    {
        if (empty($this->related_products)) {
            return collect([]);
        }

        $relatedIds = is_array($this->related_products) 
            ? $this->related_products 
            : json_decode($this->related_products, true);

        return static::whereIn('id', $relatedIds)->get();
    }
}
