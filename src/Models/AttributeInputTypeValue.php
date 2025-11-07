<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttributeInputTypeValue Model
 * Stores dropdown/select options for attributes
 */
class AttributeInputTypeValue extends Model
{
    protected $table = 'attribute_input_type_value';

    protected $fillable = [
        'attribute_id',
        'value',
        'sortby',
        'icon',
        'image',
        'marketplace_attribute_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attribute_id' => 'integer',
        'sortby' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the attribute that owns this value
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
}
