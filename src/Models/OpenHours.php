<?php

namespace TheDiamondBox\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OpenHours Model
 *
 * Manages shop opening hours for each day of the week.
 * Used in both WL and WTM modes.
 */
class OpenHours extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'open_hours';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'shop_id',      // For WL mode (nullable)
        'client_id',    // For WTM mode
        'day',
        'is_open',
        'open_at',
        'close_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_open' => 'boolean',
        'open_at' => 'datetime:H:i:s',
        'close_at' => 'datetime:H:i:s',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The days of the week
     *
     * @var array<string>
     */
    public static $days = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];
}
