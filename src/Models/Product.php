<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Product Model
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
        'sub_category_id',
        'brand_id',
        'location_id',
        'supplier_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'trade_price' => 'decimal:2',
        'related_products' => 'array',
        // category_id removed from casts to support comma-separated IDs (multi-category)
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
     * Get the category that owns the product (primary category)
     */
    public function category(): BelongsTo
    {
        $categoryModel = Category::class;
        return $this->belongsTo($categoryModel, 'category_id');
    }

    /**
     * Get all categories for the product (supports comma-separated category_ids)
     * Returns a collection combining both category_id (parents) and sub_category_id (children)
     */
    public function categories()
    {
        $categoryModel = Category::class;

        $categoryIds = [];

        // Get parent category IDs from category_id field
        if (!empty($this->category_id)) {
            $parentIds = is_string($this->category_id)
                ? array_filter(explode(',', $this->category_id))
                : [$this->category_id];
            $categoryIds = array_merge($categoryIds, $parentIds);
        }

        // Get subcategory IDs from sub_category_id field
        if (!empty($this->sub_category_id)) {
            $subIds = is_string($this->sub_category_id)
                ? array_filter(explode(',', $this->sub_category_id))
                : [$this->sub_category_id];
            $categoryIds = array_merge($categoryIds, $subIds);
        }

        if (empty($categoryIds)) {
            return collect([]);
        }

        return $categoryModel::whereIn('id', $categoryIds)->get();
    }

    /**
     * Get the brand that owns the product
     */
    public function brand(): BelongsTo
    {
        $brandModel = Brand::class;
        return $this->belongsTo($brandModel, 'brand_id');
    }

    /**
     * Get the location that owns the product
     */
    public function location(): BelongsTo
    {
        $locationModel = Location::class;
        return $this->belongsTo($locationModel, 'location_id');
    }

    /**
     * Get the supplier that owns the product
     */
    public function supplier(): BelongsTo
    {
        $supplierModel = Supplier::class;
        return $this->belongsTo($supplierModel, 'supplier_id');
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
        return $this->hasMany(ProductAttribute::class, 'product_id')->with('attribute');
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

    /**
     * Get the purchase_date attribute with proper date handling
     */
    public function getPurchaseDateAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If it's already a Carbon instance, return it
        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            // Try to parse different date formats
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                // Already in Y-m-d format
                return Carbon::parse($value);
            }

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                // Try d/m/Y format first (26/08/2025)
                try {
                    return Carbon::createFromFormat('d/m/Y', $value);
                } catch (\Exception $e) {
                    // Try m/d/Y format if d/m/Y fails
                    return Carbon::createFromFormat('m/d/Y', $value);
                }
            }

            // Fallback to Carbon's automatic parsing
            return Carbon::parse($value);
        } catch (\Exception $e) {
            // If all parsing fails, log and return null
            \Log::warning("Could not parse purchase_date in Product: {$value}", [
                'exception' => $e->getMessage(),
                'product_id' => $this->id ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Set the purchase_date attribute with proper formatting
     */
    public function setPurchaseDateAttribute($value)
    {
        if (!$value) {
            $this->attributes['purchase_date'] = null;
            return;
        }

        try {
            // If it's already a Carbon instance, format it
            if ($value instanceof Carbon) {
                $this->attributes['purchase_date'] = $value->format('Y-m-d');
                return;
            }

            // Try to parse different date formats and convert to Y-m-d
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                // Try d/m/Y format first (26/08/2025)
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $value);
                    $this->attributes['purchase_date'] = $date->format('Y-m-d');
                    return;
                } catch (\Exception $e) {
                    // Try m/d/Y format (08/26/2025)
                    $date = Carbon::createFromFormat('m/d/Y', $value);
                    $this->attributes['purchase_date'] = $date->format('Y-m-d');
                    return;
                }
            }

            // For standard formats, let Carbon handle it
            $date = Carbon::parse($value);
            $this->attributes['purchase_date'] = $date->format('Y-m-d');
        } catch (\Exception $e) {
            \Log::warning("Could not parse purchase_date for setting in Product: {$value}", [
                'exception' => $e->getMessage(),
                'product_id' => $this->id ?? 'unknown'
            ]);
            $this->attributes['purchase_date'] = null;
        }
    }
}
