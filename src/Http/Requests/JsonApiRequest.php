<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * JsonApiRequest
 *
 * Base request class that enforces strict JSON API format compliance
 *
 * STRICT MODE: All requests MUST use JSON API format with 'data' wrapper.
 * No backwards compatibility with flat format.
 *
 * Extracts data.attributes and data.relationships into a flat structure
 * for internal validation and processing.
 *
 * Expected request format:
 * {
 *   "data": {
 *     "type": "resource-type",
 *     "id": "123",  // optional for POST, included for PUT/PATCH
 *     "attributes": { ... },
 *     "relationships": { ... }
 *   }
 * }
 */
abstract class JsonApiRequest extends FormRequest
{
    /**
     * Parsed attributes from data.attributes
     *
     * @var array
     */
    protected $jsonApiAttributes = [];

    /**
     * Parsed relationships from data.relationships
     *
     * @var array
     */
    protected $jsonApiRelationships = [];

    /**
     * Resource type from data.type
     *
     * @var string|null
     */
    protected $jsonApiType = null;

    /**
     * Resource ID from data.id
     *
     * @var string|null
     */
    protected $jsonApiId = null;

    /**
     * Whether the request is in JSON API format
     *
     * @var bool
     */
    protected $isJsonApiFormat = false;

    /**
     * Expected resource type for this request
     * Override in child classes
     *
     * @return string|null
     */
    protected function expectedResourceType()
    {
        return null;
    }

    /**
     * Relationship mappings to convert JSON API relationships to foreign keys
     * Override in child classes to customize
     *
     * Example: ['category' => 'category_id', 'brand' => 'brand_id']
     *
     * @return array
     */
    protected function relationshipMappings()
    {
        return [];
    }

    /**
     * Prepare the data for validation by extracting JSON API format
     * STRICT MODE: Always requires JSON API format, no backwards compatibility
     */
    protected function prepareForValidation()
    {
        // Strict JSON API format required - 'data' key must exist
        $this->isJsonApiFormat = true;
        $data = $this->input('data', []);

        // Extract type and id
        $this->jsonApiType = Arr::get($data, 'type');
        $this->jsonApiId = Arr::get($data, 'id');

        // Extract attributes
        $this->jsonApiAttributes = Arr::get($data, 'attributes', []);

        // Extract relationships
        $rawRelationships = Arr::get($data, 'relationships', []);
        $this->jsonApiRelationships = $this->parseRelationships($rawRelationships);

        // Merge attributes and converted relationships for validation
        $merged = array_merge($this->jsonApiAttributes, $this->jsonApiRelationships);

        // Replace request data with flattened structure
        $this->merge($merged);
    }

    /**
     * Parse JSON API relationships into a flat structure
     *
     * @param array $relationships
     * @return array
     */
    protected function parseRelationships(array $relationships)
    {
        $parsed = [];
        $mappings = $this->relationshipMappings();

        foreach ($relationships as $name => $relationship) {
            $relationshipData = Arr::get($relationship, 'data');

            if ($relationshipData === null) {
                // Null relationship
                if (isset($mappings[$name])) {
                    $parsed[$mappings[$name]] = null;
                }
                continue;
            }

            // Check if it's a to-many or to-one relationship
            if (Arr::isAssoc($relationshipData)) {
                // To-one relationship: { "type": "categories", "id": "5" }
                $id = Arr::get($relationshipData, 'id');

                if (isset($mappings[$name])) {
                    $parsed[$mappings[$name]] = $id;
                } else {
                    // Store the full relationship data for custom handling
                    $parsed[$name] = $relationshipData;
                }
            } else {
                // To-many relationship: [{ "type": "...", "id": "..." }, ...]
                $ids = array_map(function ($item) {
                    return Arr::get($item, 'id');
                }, $relationshipData);

                if (isset($mappings[$name])) {
                    $parsed[$mappings[$name]] = $ids;
                } else {
                    // Store the full relationship data for custom handling
                    $parsed[$name] = $relationshipData;
                }
            }
        }

        return $parsed;
    }

    /**
     * Get the JSON API attributes
     *
     * @return array
     */
    public function getJsonApiAttributes()
    {
        return $this->jsonApiAttributes;
    }

    /**
     * Get the JSON API relationships (parsed)
     *
     * @return array
     */
    public function getJsonApiRelationships()
    {
        return $this->jsonApiRelationships;
    }

    /**
     * Get the JSON API resource type
     *
     * @return string|null
     */
    public function getJsonApiType()
    {
        return $this->jsonApiType;
    }

    /**
     * Get the JSON API resource ID
     *
     * @return string|null
     */
    public function getJsonApiId()
    {
        return $this->jsonApiId;
    }

    /**
     * Check if request is in JSON API format
     *
     * @return bool
     */
    public function isJsonApiFormat()
    {
        return $this->isJsonApiFormat;
    }

    /**
     * Get validation rules for JSON API structure
     * STRICT MODE: Always requires JSON API structure
     *
     * @return array
     */
    protected function jsonApiStructureRules()
    {
        $rules = [
            'data' => 'required|array',
            'data.type' => 'required|string',
            'data.attributes' => 'sometimes|array',
            'data.relationships' => 'sometimes|array',
        ];

        // Add type validation if expected type is defined
        if ($this->expectedResourceType()) {
            $rules['data.type'] = 'required|string|in:' . $this->expectedResourceType();
        }

        // ID is optional for creation, required for updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['data.id'] = 'sometimes|string';
        }

        return $rules;
    }

    /**
     * Override this to add JSON API structure validation
     * Call parent::rules() in child classes and merge with custom rules
     *
     * @return array
     */
    public function rules()
    {
        return $this->jsonApiStructureRules();
    }
}
