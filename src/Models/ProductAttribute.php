<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Carbon\Carbon;

/**
 * Product Attribute Pivot Model for Diamond Box Integration
 *
 * This model represents the many-to-many relationship between
 * products and attributes with additional pivot data.
 */
class ProductAttribute extends Pivot
{
    protected $table = 'product_attributes';

    protected $fillable = [
        'product_id',
        'attribute_id',
        'value',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'attribute_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the product that owns this attribute relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the attribute that owns this relationship
     */
    public function attribute() {
        return $this->hasOne(Attribute::class, 'id', 'attribute_id')->withTrashed();
    }

    /**
     * Scope to filter by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by attribute
     */
    public function scopeForAttribute($query, $attributeId)
    {
        return $query->where('attribute_id', $attributeId);
    }

    /**
     * Scope to filter by attribute type
     */
    public function scopeByAttributeType($query, $type)
    {
        return $query->whereHas('attribute', function ($q) use ($type) {
            $q->where('type', $type);
        });
    }

    /**
     * Get formatted value based on attribute type
     */
    public function getFormattedValueAttribute()
    {
        if (!$this->attribute) {
            return $this->value;
        }

        switch ($this->attribute->type) {
            case Attribute::TYPE_BOOLEAN:
                return $this->value ? 'Yes' : 'No';

            case Attribute::TYPE_DATE:
                if (!$this->value) {
                    return null;
                }

                try {
                    // Try to parse different date formats
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->value)) {
                        // Already in Y-m-d format
                        return $this->value;
                    }

                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $this->value)) {
                        // Try d/m/Y format first (26/08/2025)
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $this->value);
                            return $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            // Try m/d/Y format if d/m/Y fails
                            $date = Carbon::createFromFormat('m/d/Y', $this->value);
                            return $date->format('Y-m-d');
                        }
                    }

                    // Fallback to Carbon's automatic parsing
                    $date = Carbon::parse($this->value);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // If all parsing fails, log and return the original value
                    \Log::warning("Could not parse date value in ProductAttribute: {$this->value}", [
                        'exception' => $e->getMessage(),
                        'product_id' => $this->product_id,
                        'attribute_id' => $this->attribute_id
                    ]);
                    return $this->value;
                }

            case Attribute::TYPE_SELECT:
            case Attribute::TYPE_MULTISELECT:
                if ($this->attribute->hasOptions()) {
                    $options = $this->attribute->options;
                    if (is_array($this->value)) {
                        return array_map(function ($val) use ($options) {
                            return $options[$val] ?? $val;
                        }, $this->value);
                    }
                    return $options[$this->value] ?? $this->value;
                }
                return $this->value;

            default:
                return $this->value;
        }
    }

    /**
     * Validate the attribute value against the attribute's validation rules
     */
    public function validateValue(): bool
    {
        if (!$this->attribute) {
            return false;
        }

        return $this->attribute->validateValue($this->value);
    }

    /**
     * Get the attribute name for easy access
     */
    public function getAttributeNameAttribute(): ?string
    {
        return $this->attribute ? $this->attribute->name : null;
    }

    /**
     * Get the attribute code for easy access
     */
    public function getAttributeCodeAttribute(): ?string
    {
        return $this->attribute ? $this->attribute->code : null;
    }

    /**
     * Check if this attribute is required
     */
    public function isRequired(): bool
    {
        return $this->attribute ? $this->attribute->is_required : false;
    }

    /**
     * Check if this attribute is filterable
     */
    public function isFilterable(): bool
    {
        return $this->attribute ? $this->attribute->is_filterable : false;
    }

    /**
     * Check if this attribute is searchable
     */
    public function isSearchable(): bool
    {
        return $this->attribute ? $this->attribute->is_searchable : false;
    }
}