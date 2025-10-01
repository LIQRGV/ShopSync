<?php

namespace TheDiamondBox\ShopSync\Helpers;

use Illuminate\Http\Request;

/**
 * JSON API Include Parser Helper
 *
 * Helper class for parsing and validating include parameters
 * according to the JSON API specification (https://jsonapi.org/format/#fetching-includes)
 */
class JsonApiIncludeParser
{
    /**
     * Parse include parameter from request
     *
     * Supports both comma-separated string format and Laravel array format:
     * - ?include=category,brand,location
     * - ?include[]=category&include[]=brand&include[]=location
     */
    public static function parseFromRequest(Request $request): array
    {
        $includes = [];

        // Check for string format: ?include=category,brand,location
        if ($request->has('include') && is_string($request->get('include'))) {
            $includeString = $request->get('include');
            $includes = self::parseFromString($includeString);
        }

        // Check for array format: ?include[]=category&include[]=brand
        if ($request->has('include') && is_array($request->get('include'))) {
            $includeArray = $request->get('include');
            $includes = array_merge($includes, self::parseFromArray($includeArray));
        }

        return array_unique($includes);
    }

    /**
     * Parse includes from comma-separated string
     */
    public static function parseFromString(string $includeString): array
    {
        if (empty(trim($includeString))) {
            return [];
        }

        // Split by comma and clean up whitespace
        $includes = array_map('trim', explode(',', $includeString));

        // Filter out empty values
        return array_filter($includes, function ($include) {
            return !empty($include);
        });
    }

    /**
     * Parse includes from array format
     */
    public static function parseFromArray(array $includeArray): array
    {
        $includes = [];

        foreach ($includeArray as $include) {
            if (is_string($include) && !empty(trim($include))) {
                $includes[] = trim($include);
            }
        }

        return $includes;
    }

    /**
     * Validate includes against allowed list
     */
    public static function validate(array $includes, array $allowedIncludes): array
    {
        $errors = [];

        foreach ($includes as $include) {
            $error = self::validateSingleInclude($include, $allowedIncludes);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Validate a single include parameter
     */
    public static function validateSingleInclude(string $include, array $allowedIncludes): ?array
    {
        // Handle nested includes (e.g., 'category.parent')
        $parts = explode('.', $include);
        $mainInclude = $parts[0];

        // Check if the main include is allowed
        if (!in_array($mainInclude, $allowedIncludes)) {
            return [
                'status' => '400',
                'title' => 'Invalid include parameter',
                'detail' => "The include parameter '{$include}' is not supported. Available includes: " . implode(', ', $allowedIncludes),
                'code' => 'INVALID_INCLUDE',
                'source' => ['parameter' => 'include']
            ];
        }

        return null;
    }

    /**
     * Filter valid includes from a list
     */
    public static function filterValid(array $includes, array $allowedIncludes): array
    {
        $validIncludes = [];

        foreach ($includes as $include) {
            $parts = explode('.', $include);
            $mainInclude = $parts[0];

            if (in_array($mainInclude, $allowedIncludes)) {
                $validIncludes[] = $include;
            }
        }

        return $validIncludes;
    }

    /**
     * Get nested includes for a specific relationship
     */
    public static function getNestedIncludes(array $includes, string $relationship): array
    {
        $nestedIncludes = [];

        foreach ($includes as $include) {
            if (strpos($include, $relationship . '.') === 0) {
                // Remove the relationship prefix and the dot
                $nested = substr($include, strlen($relationship) + 1);
                if (!empty($nested)) {
                    $nestedIncludes[] = $nested;
                }
            }
        }

        return $nestedIncludes;
    }

    /**
     * Get direct includes (no nested relationships)
     */
    public static function getDirectIncludes(array $includes): array
    {
        return array_filter($includes, function ($include) {
            return !str_contains($include, '.');
        });
    }

    /**
     * Check if an include is nested
     */
    public static function isNested(string $include): bool
    {
        return str_contains($include, '.');
    }

    /**
     * Get the depth of nested includes
     */
    public static function getIncludeDepth(string $include): int
    {
        return substr_count($include, '.') + 1;
    }

    /**
     * Get maximum depth from includes array
     */
    public static function getMaxDepth(array $includes): int
    {
        $maxDepth = 1;

        foreach ($includes as $include) {
            $depth = self::getIncludeDepth($include);
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * Normalize includes by removing duplicates and sorting
     */
    public static function normalize(array $includes): array
    {
        // Remove duplicates and empty values
        $normalized = array_unique(array_filter($includes, function ($include) {
            return !empty(trim($include));
        }));

        // Sort for consistency
        sort($normalized);

        return array_values($normalized);
    }

    /**
     * Convert includes to eager loading format for Eloquent
     */
    public static function toEagerLoadFormat(array $includes): array
    {
        $eagerLoads = [];

        foreach ($includes as $include) {
            // Convert dot notation to Eloquent eager loading format
            // 'category.parent' becomes 'category.parent'
            $eagerLoads[] = $include;
        }

        return $eagerLoads;
    }

    /**
     * Group includes by their main relationship
     */
    public static function groupByMainRelationship(array $includes): array
    {
        $grouped = [];

        foreach ($includes as $include) {
            $parts = explode('.', $include);
            $mainRelationship = $parts[0];

            if (!isset($grouped[$mainRelationship])) {
                $grouped[$mainRelationship] = [];
            }

            $grouped[$mainRelationship][] = $include;
        }

        return $grouped;
    }

    /**
     * Check if includes contain a specific relationship
     */
    public static function hasRelationship(array $includes, string $relationship): bool
    {
        foreach ($includes as $include) {
            $parts = explode('.', $include);
            if ($parts[0] === $relationship) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all unique relationships from includes
     */
    public static function getUniqueRelationships(array $includes): array
    {
        $relationships = [];

        foreach ($includes as $include) {
            $parts = explode('.', $include);
            $relationships[] = $parts[0];
        }

        return array_unique($relationships);
    }

    /**
     * Create include string from array (useful for URLs)
     */
    public static function toString(array $includes): string
    {
        return implode(',', self::normalize($includes));
    }

    /**
     * Merge multiple include arrays
     */
    public static function merge(array ...$includeArrays): array
    {
        $merged = [];

        foreach ($includeArrays as $includes) {
            $merged = array_merge($merged, $includes);
        }

        return self::normalize($merged);
    }

    /**
     * Check if includes are valid according to JSON API spec
     */
    public static function isValidFormat(array $includes): bool
    {
        foreach ($includes as $include) {
            if (!is_string($include)) {
                return false;
            }

            // Check for invalid characters
            if (preg_match('/[^a-zA-Z0-9._-]/', $include)) {
                return false;
            }

            // Check for invalid patterns
            if (str_starts_with($include, '.') || str_ends_with($include, '.')) {
                return false;
            }

            // Check for consecutive dots
            if (str_contains($include, '..')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get include validation errors
     */
    public static function getFormatErrors(array $includes): array
    {
        $errors = [];

        foreach ($includes as $include) {
            if (!is_string($include)) {
                $errors[] = [
                    'status' => '400',
                    'title' => 'Invalid include format',
                    'detail' => 'Include parameters must be strings',
                    'code' => 'INVALID_INCLUDE_FORMAT',
                    'source' => ['parameter' => 'include']
                ];
                continue;
            }

            if (preg_match('/[^a-zA-Z0-9._-]/', $include)) {
                $errors[] = [
                    'status' => '400',
                    'title' => 'Invalid include format',
                    'detail' => "Include parameter '{$include}' contains invalid characters. Only letters, numbers, dots, underscores, and hyphens are allowed.",
                    'code' => 'INVALID_INCLUDE_FORMAT',
                    'source' => ['parameter' => 'include']
                ];
            }

            if (str_starts_with($include, '.') || str_ends_with($include, '.')) {
                $errors[] = [
                    'status' => '400',
                    'title' => 'Invalid include format',
                    'detail' => "Include parameter '{$include}' cannot start or end with a dot.",
                    'code' => 'INVALID_INCLUDE_FORMAT',
                    'source' => ['parameter' => 'include']
                ];
            }

            if (str_contains($include, '..')) {
                $errors[] = [
                    'status' => '400',
                    'title' => 'Invalid include format',
                    'detail' => "Include parameter '{$include}' cannot contain consecutive dots.",
                    'code' => 'INVALID_INCLUDE_FORMAT',
                    'source' => ['parameter' => 'include']
                ];
            }
        }

        return $errors;
    }
}