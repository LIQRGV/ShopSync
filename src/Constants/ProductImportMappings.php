<?php

namespace TheDiamondBox\ShopSync\Constants;

/**
 * Product Import Mappings
 *
 * This class contains all CSV text-to-integer mappings for product import.
 * When importing CSV files, text values need to be converted to their integer equivalents
 * that match the database column types.
 *
 * Usage:
 * - Add new mappings here when new columns require text-to-integer conversion
 * - Each mapping array should have text as key and integer as value
 * - Use case-insensitive matching for better user experience
 */
class ProductImportMappings
{
    /**
     * Product Status Mapping
     * Database column: status (integer)
     *
     * Maps CSV text values to database integer values for product status
     */
    public const PRODUCT_STATUS = [
        'in stock' => 1,
        'out of stock & show' => 2,
        'out of stock and show' => 2,
        'out of stock & hide' => 3,
        'out of stock and hide' => 3,
        'out of stock &amp; hide' => 3,
        'hidden completely' => 3,
        'repair' => 4,
        'coming soon' => 5,
        'in stock & hide' => 6,
        'in stock and hide' => 6,
        'in stock &amp; hide' => 6,
        'unlisted' => 7,
    ];

    /**
     * Sell Status Mapping
     * Database column: sell_status (integer)
     *
     * Maps CSV text values to database integer values for sell status
     */
    public const SELL_STATUS = [
        'sell as standard' => 1,
        'standard' => 1,
        'oversell' => 2,
        'pre-orders' => 3,
        'pre orders' => 3,
        'preorders' => 3,
        'quantity controlled' => 4,
    ];

    /**
     * VAT Scheme Mapping
     * Database column: vat_scheme (integer)
     *
     * Maps CSV text values to database integer values for VAT scheme
     */
    public const VAT_SCHEME = [
        'none' => 0,
        '' => 0,
        'new' => 1,
        'bought with vat' => 2,
        'margin scheme' => 3,
        'margin scheme/second hand' => 3,
        'second hand' => 3,
    ];

    /**
     * Get mapped integer value from text
     *
     * @param string $mappingType The constant name (e.g., 'PRODUCT_STATUS', 'SELL_STATUS', 'VAT_SCHEME')
     * @param string|int $value The value to map (text or already integer)
     * @param int $default Default value if mapping not found
     * @return int The mapped integer value
     */
    public static function map(string $mappingType, $value, int $default = 0): int
    {
        // If already an integer, return it
        if (is_numeric($value) && (int)$value == $value) {
            return (int)$value;
        }

        // Get the mapping array
        $mapping = constant("self::$mappingType");

        if (!is_array($mapping)) {
            return $default;
        }

        // Convert to lowercase for case-insensitive matching
        $normalizedValue = strtolower(trim($value));

        // Return mapped value or default
        return $mapping[$normalizedValue] ?? $default;
    }

    /**
     * Get all available mappings for a type
     *
     * @param string $mappingType The constant name
     * @return array The mapping array
     */
    public static function getMappings(string $mappingType): array
    {
        $mapping = constant("self::$mappingType");
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Check if a value exists in a mapping
     *
     * @param string $mappingType The constant name
     * @param string $value The value to check
     * @return bool
     */
    public static function exists(string $mappingType, string $value): bool
    {
        $mapping = constant("self::$mappingType");

        if (!is_array($mapping)) {
            return false;
        }

        $normalizedValue = strtolower(trim($value));
        return isset($mapping[$normalizedValue]);
    }
}
