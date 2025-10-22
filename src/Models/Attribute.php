<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Attribute Model for Diamond Box Integration
 */
class Attribute extends Model
{
    use SoftDeletes;

    protected $table = 'attributes';

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'options',
        'is_required',
        'is_filterable',
        'is_searchable',
        'is_active',
        'sort_order',
        'validation_rules',
        'default_value',
        'enabled_on_dropship',
        'group_name',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'validation_rules' => 'array',
        'enabled_on_dropship' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Attribute types
     */
    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SELECT = 'select';
    const TYPE_MULTISELECT = 'multiselect';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_DATE = 'date';
    const TYPE_COLOR = 'color';
    const TYPE_FILE = 'file';

    /**
     * Get the products that have this attribute
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_attributes',
            'attribute_id',
            'product_id'
        )->withPivot('value', 'created_at', 'updated_at')
          ->withTimestamps();
    }

    /**
     * Scope to filter active attributes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter filterable attributes
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Scope to filter searchable attributes
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    /**
     * Scope to filter required attributes
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter attributes enabled for dropship
     */
    public function scopeEnabledOnDropship($query)
    {
        return $query->where('enabled_on_dropship', true);
    }

    /**
     * Get available attribute types
     */
    public static function getTypes(): array
    {
        return [
            static::TYPE_TEXT => 'Text',
            static::TYPE_TEXTAREA => 'Textarea',
            static::TYPE_SELECT => 'Select',
            static::TYPE_MULTISELECT => 'Multi-select',
            static::TYPE_BOOLEAN => 'Boolean',
            static::TYPE_NUMBER => 'Number',
            static::TYPE_DATE => 'Date',
            static::TYPE_COLOR => 'Color',
            static::TYPE_FILE => 'File',
        ];
    }

    /**
     * Check if attribute has options
     */
    public function hasOptions(): bool
    {
        return in_array($this->type, [static::TYPE_SELECT, static::TYPE_MULTISELECT]) 
            && !empty($this->options);
    }

    /**
     * Get formatted options for display
     */
    public function getFormattedOptionsAttribute(): array
    {
        if (!$this->hasOptions()) {
            return [];
        }

        return collect($this->options)->map(function ($option, $key) {
            return [
                'value' => is_numeric($key) ? $option : $key,
                'label' => is_array($option) ? $option['label'] ?? $option['value'] : $option,
            ];
        })->toArray();
    }

    /**
     * Validate attribute value
     */
    public function validateValue($value): bool
    {
        // Basic type validation
        switch ($this->type) {
            case static::TYPE_BOOLEAN:
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false']);
            
            case static::TYPE_NUMBER:
                return is_numeric($value);
            
            case static::TYPE_DATE:
                return strtotime($value) !== false;
            
            case static::TYPE_SELECT:
                return $this->hasOptions() && in_array($value, array_keys($this->options));
            
            case static::TYPE_MULTISELECT:
                if (!is_array($value)) {
                    return false;
                }
                return $this->hasOptions() && 
                    empty(array_diff($value, array_keys($this->options)));
            
            default:
                return true; // Text, textarea, color, file - basic validation passed
        }
    }

    /**
     * Get products count with this attribute
     */
    public function getProductsCountAttribute(): int
    {
        if (config('products-package.mode') === 'wtm') {
            return $this->attributes['products_count'] ?? 0;
        }
        return $this->products()->count();
    }
}
