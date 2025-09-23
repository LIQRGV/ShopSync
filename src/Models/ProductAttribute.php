<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product Attribute Pivot Model for Diamond Box Integration
 *
 * This model represents the many-to-many relationship between
 * products and attributes with additional pivot data.
 */
class ProductAttribute extends Model
{
    use HasFactory;

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
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
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
                return $this->value ? date('Y-m-d', strtotime($this->value)) : null;

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