<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Collection as BaseCollection;

/**
 * JSON API Transformer
 * 
 * Transforms Laravel models and collections into JSON API format
 * according to the JSON API specification (https://jsonapi.org/)
 */
class JsonApiTransformer
{
    protected $includes = [];
    protected $included = [];
    protected $maxIncludeDepth;
    protected $currentDepth = 0;
    protected $allowedIncludes;

    public function __construct(array $allowedIncludes = [], int $maxIncludeDepth = 3)
    {
        $this->allowedIncludes = $allowedIncludes;
        $this->maxIncludeDepth = $maxIncludeDepth;
    }

    /**
     * Set the includes to load
     */
    public function setIncludes(array $includes): self
    {
        // Filter includes to only allowed ones
        $this->includes = array_intersect($includes, $this->allowedIncludes);
        return $this;
    }

    /**
     * Transform a single model into JSON API format
     */
    public function transformItem(Model $model, string $type = null): array
    {
        $type = $type ?: $this->getTypeFromModel($model);
        $this->included = []; // Reset included for each transformation
        
        $data = $this->buildResourceData($model, $type);
        
        $response = ['data' => $data];
        
        if (!empty($this->included)) {
            $response['included'] = array_values($this->included);
        }
        
        return $response;
    }

    /**
     * Transform a collection into JSON API format
     */
    public function transformCollection($collection, string $type = null): array
    {
        $this->included = []; // Reset included for each transformation
        
        $data = [];
        
        foreach ($collection as $item) {
            $itemType = $type ?: $this->getTypeFromModel($item);
            $data[] = $this->buildResourceData($item, $itemType);
        }
        
        $response = ['data' => $data];
        
        // Add pagination meta if collection is paginated
        if ($collection instanceof LengthAwarePaginator) {
            $response['meta'] = $this->buildPaginationMeta($collection);
            $response['links'] = $this->buildPaginationLinks($collection);
        }
        
        if (!empty($this->included)) {
            $response['included'] = array_values($this->included);
        }
        
        return $response;
    }

    /**
     * Build resource data for a model
     */
    protected function buildResourceData(Model $model, string $type): array
    {
        $data = [
            'type' => $type,
            'id' => (string) $model->getKey(),
            'attributes' => $this->getModelAttributes($model),
        ];
        
        // Add relationships if includes are requested
        if (!empty($this->includes) && $this->currentDepth < $this->maxIncludeDepth) {
            $relationships = $this->buildRelationships($model);
            if (!empty($relationships)) {
                $data['relationships'] = $relationships;
            }
        }
        
        return $data;
    }

    /**
     * Get model attributes, excluding relationships and internal fields
     */
    protected function getModelAttributes(Model $model): array
    {
        $attributes = $model->toArray();
        
        // Remove primary key from attributes (it's in the id field)
        unset($attributes[$model->getKeyName()]);
        
        // Remove foreign keys and relationship data
        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }
        
        // Remove foreign key fields
        $foreignKeys = $this->getForeignKeys($model);
        foreach ($foreignKeys as $foreignKey) {
            unset($attributes[$foreignKey]);
        }
        
        // Remove timestamps if they should be hidden
        if ($model->getHidden() && in_array('created_at', $model->getHidden())) {
            unset($attributes['created_at']);
        }
        if ($model->getHidden() && in_array('updated_at', $model->getHidden())) {
            unset($attributes['updated_at']);
        }
        
        return $attributes;
    }

    /**
     * Build relationships for a model
     */
    protected function buildRelationships(Model $model): array
    {
        $relationships = [];
        
        foreach ($this->includes as $include) {
            if (!str_contains($include, '.')) {
                // Direct relationship
                $relationship = $this->buildRelationship($model, $include);
                if ($relationship !== null) {
                    $relationships[$include] = $relationship;
                }
            } else {
                // Nested relationship (e.g., 'category.parent')
                $parts = explode('.', $include, 2);
                $mainRelation = $parts[0];
                
                if (!isset($relationships[$mainRelation])) {
                    $relationship = $this->buildRelationship($model, $mainRelation);
                    if ($relationship !== null) {
                        $relationships[$mainRelation] = $relationship;
                    }
                }
            }
        }
        
        return $relationships;
    }

    /**
     * Build a single relationship
     */
    protected function buildRelationship(Model $model, string $relationshipName): ?array
    {
        if (!$model->relationLoaded($relationshipName)) {
            return null;
        }

        $related = $model->getRelation($relationshipName);

        if ($related === null) {
            return ['data' => []];
        }

        if ($related instanceof Collection || $related instanceof BaseCollection || is_array($related)) {
            // Has many relationship
            $data = [];
            foreach ($related as $item) {
                if (!($item instanceof Model)) continue;
                $type = $this->getTypeFromModel($item);
                $data[] = [
                    'type' => $type,
                    'id' => (string) $item->getKey(),
                ];

                // Add to included resources with parent context for pivot relationships
                $this->addToIncluded($item, $type, $model->getKey());
            }

            return ['data' => $data];
        } else if ($related instanceof Model) {
            // Belongs to relationship
            $type = $this->getTypeFromModel($related);
            $relationshipData = [
                'data' => [
                    'type' => $type,
                    'id' => (string) $related->getKey(),
                ]
            ];

            // Add to included resources
            $this->addToIncluded($related, $type);

            return $relationshipData;
        } else {
            // Unknown relationship type, return empty
            return ['data' => []];
        }
    }

    /**
     * Add a model to the included resources
     */
    protected function addToIncluded(Model $model, string $type, $parentId = null): void
    {
        // For models with pivot (like attributes in a many-to-many relationship),
        // create a unique key that includes the parent ID to allow duplicate attribute IDs
        // with different pivot data for different products
        $key = $type . ':' . $model->getKey();
        if ($parentId !== null && isset($model->pivot)) {
            $key = $type . ':' . $model->getKey() . ':' . $parentId;
        }

        if (!isset($this->included[$key])) {
            $this->currentDepth++;

            $includedData = [
                'type' => $type,
                'id' => (string) $model->getKey(),
                'attributes' => $this->getModelAttributes($model),
            ];

            // Add nested relationships if we haven't reached max depth
            if ($this->currentDepth < $this->maxIncludeDepth) {
                $nestedIncludes = $this->getNestedIncludes($type);
                if (!empty($nestedIncludes)) {
                    $oldIncludes = $this->includes;
                    $this->includes = $nestedIncludes;

                    $relationships = $this->buildRelationships($model);
                    if (!empty($relationships)) {
                        $includedData['relationships'] = $relationships;
                    }

                    $this->includes = $oldIncludes;
                }
            }

            $this->included[$key] = $includedData;
            $this->currentDepth--;
        }
    }

    /**
     * Get nested includes for a specific type
     */
    protected function getNestedIncludes(string $type): array
    {
        $nestedIncludes = [];
        
        foreach ($this->includes as $include) {
            if (str_contains($include, '.')) {
                $parts = explode('.', $include, 2);
                if ($this->getTypeFromRelationshipName($parts[0]) === $type) {
                    $nestedIncludes[] = $parts[1];
                }
            }
        }
        
        return $nestedIncludes;
    }

    /**
     * Get type name from model
     */
    protected function getTypeFromModel(Model $model): string
    {
        $className = class_basename($model);
        
        // Convert model name to JSON API type
        // Product -> products
        // Category -> categories
        if ($className === 'Product') {
            return 'products';
        }
        
        return Str::snake(Str::pluralStudly($className));
    }

    /**
     * Get type from relationship name
     */
    protected function getTypeFromRelationshipName(string $relationshipName): string
    {
        // Convert relationship name to type
        // category -> categories
        // brand -> brands
        return Str::snake(Str::pluralStudly($relationshipName));
    }

    /**
     * Get foreign keys from model
     */
    protected function getForeignKeys(Model $model): array
    {
        $foreignKeys = [];
        
        // Common foreign key patterns
        $possibleKeys = [
            'category_id',
            'brand_id',
            'location_id',
            'supplier_id',
            'parent_id',
        ];
        
        foreach ($possibleKeys as $key) {
            if ($model->hasAttribute($key)) {
                $foreignKeys[] = $key;
            }
        }
        
        return $foreignKeys;
    }

    /**
     * Build pagination meta data
     */
    protected function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Build pagination links
     */
    protected function buildPaginationLinks(LengthAwarePaginator $paginator): array
    {
        $links = [
            'self' => $paginator->url($paginator->currentPage()),
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
        ];
        
        if ($paginator->previousPageUrl()) {
            $links['prev'] = $paginator->previousPageUrl();
        }
        
        if ($paginator->nextPageUrl()) {
            $links['next'] = $paginator->nextPageUrl();
        }
        
        return $links;
    }

    /**
     * Parse include parameter from request
     */
    public static function parseIncludes(string $includeParameter): array
    {
        return array_filter(explode(',', $includeParameter));
    }

    /**
     * Parse include parameter from array format (Laravel style)
     */
    public static function parseIncludesArray(array $includeArray): array
    {
        $includes = [];
        
        foreach ($includeArray as $include) {
            if (is_string($include)) {
                $includes[] = $include;
            }
        }
        
        return $includes;
    }
}
