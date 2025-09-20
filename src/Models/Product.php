<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'sku',
        'category',
        'metadata',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Maximum size for metadata JSON in bytes
     */
    const METADATA_MAX_SIZE = 65536; // 64KB

    /**
     * Maximum nesting depth for metadata
     */
    const METADATA_MAX_DEPTH = 10;

    protected $hidden = [];

    /**
     * Scope to filter active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter inactive products
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
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
     * Scope to filter by stock level
     */
    public function scopeMinStock($query, $minStock)
    {
        return $query->where('stock', '>=', $minStock);
    }

    /**
     * Check if product is in stock
     */
    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if product is out of stock
     */
    public function outOfStock(): bool
    {
        return $this->stock <= 0;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get stock status
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock > 10) {
            return 'In Stock';
        } elseif ($this->stock > 0) {
            return 'Low Stock';
        } else {
            return 'Out of Stock';
        }
    }

    /**
     * Set metadata attribute with validation and sanitization
     */
    public function setMetadataAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['metadata'] = null;
            return;
        }

        // Convert to array if it's a string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON provided for metadata field');
            }
            $value = $decoded;
        }

        // Ensure it's an array
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Metadata must be an array or valid JSON string');
        }

        // Validate and sanitize the metadata
        $sanitized = $this->validateAndSanitizeMetadata($value);

        // Check size constraints
        $jsonString = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        if (strlen($jsonString) > self::METADATA_MAX_SIZE) {
            throw new \InvalidArgumentException('Metadata exceeds maximum size limit of ' . self::METADATA_MAX_SIZE . ' bytes');
        }

        $this->attributes['metadata'] = $jsonString;
    }

    /**
     * Validate and sanitize metadata array
     */
    protected function validateAndSanitizeMetadata(array $metadata, int $depth = 1): array
    {
        // Check nesting depth to prevent deep recursion attacks
        if ($depth > self::METADATA_MAX_DEPTH) {
            throw new \InvalidArgumentException('Metadata nesting depth exceeds maximum allowed depth of ' . self::METADATA_MAX_DEPTH);
        }

        $sanitized = [];
        $keyCount = 0;

        foreach ($metadata as $key => $value) {
            // Limit number of keys to prevent memory exhaustion
            if (++$keyCount > 1000) {
                throw new \InvalidArgumentException('Metadata contains too many keys (maximum 1000 allowed)');
            }

            // Sanitize and validate key
            $sanitizedKey = $this->sanitizeMetadataKey($key);
            if ($sanitizedKey === null) {
                continue; // Skip invalid keys
            }

            // Sanitize and validate value
            $sanitizedValue = $this->sanitizeMetadataValue($value, $depth);
            if ($sanitizedValue !== null) {
                $sanitized[$sanitizedKey] = $sanitizedValue;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata key
     */
    protected function sanitizeMetadataKey($key): ?string
    {
        // Ensure key is a string
        if (!is_string($key) && !is_numeric($key)) {
            return null;
        }

        $key = (string) $key;

        // Limit key length
        if (strlen($key) > 255) {
            return null;
        }

        // Remove control characters and null bytes
        $key = preg_replace('/[\x00-\x1F\x7F]/', '', $key);

        // Trim whitespace
        $key = trim($key);

        return empty($key) ? null : $key;
    }

    /**
     * Sanitize metadata value
     */
    protected function sanitizeMetadataValue($value, int $depth)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            // Prevent extremely large numbers that could cause issues
            if (is_float($value) && !is_finite($value)) {
                return null;
            }
            return $value;
        }

        if (is_string($value)) {
            // Limit string length to prevent memory issues
            if (strlen($value) > 10000) {
                return substr($value, 0, 10000);
            }

            // Remove null bytes and control characters (except newlines and tabs)
            $value = preg_replace('/[\x00\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

            return $value;
        }

        if (is_array($value)) {
            return $this->validateAndSanitizeMetadata($value, $depth + 1);
        }

        // Reject objects and other types
        return null;
    }

    /**
     * Get metadata attribute with safe deserialization
     */
    public function getMetadataAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        // Safe JSON decode with error handling
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::warning('Invalid JSON in product metadata', [
                'product_id' => $this->id,
                'error' => json_last_error_msg()
            ]);
            return [];
        }

        return $decoded ?: [];
    }
}