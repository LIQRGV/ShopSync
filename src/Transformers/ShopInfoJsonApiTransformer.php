<?php

namespace TheDiamondBox\ShopSync\Transformers;

use TheDiamondBox\ShopSync\Models\ShopInfo;
use Illuminate\Database\Eloquent\Model;

/**
 * ShopInfo JSON API Transformer
 *
 * Specific transformer for ShopInfo models that extends the base
 * JsonApiTransformer with shop-info-specific transformations and relationships.
 */
class ShopInfoJsonApiTransformer extends JsonApiTransformer
{
    /**
     * Available relationships for shop info
     */
    protected $availableIncludes = [
        'openHours'
    ];

    /**
     * Maximum include depth to prevent infinite recursion
     */
    protected $maxDepth = 2;

    public function __construct()
    {
        parent::__construct($this->availableIncludes, $this->maxDepth);
    }

    /**
     * Transform a single ShopInfo into JSON API format
     */
    public function transformShopInfo(ShopInfo $shopInfo, array $includes = []): array
    {
        $this->setIncludes($includes);
        return $this->transformItem($shopInfo, 'shop-info');
    }

    /**
     * Get model attributes, customized for ShopInfo
     */
    protected function getModelAttributes(Model $model): array
    {
        if (!$model instanceof ShopInfo) {
            return parent::getModelAttributes($model);
        }

        $attributes = $model->toArray();

        // Remove primary key from attributes (it's in the id field)
        // Note: ShopInfo has no primary key (singleton), but we handle it gracefully
        if ($model->getKeyName()) {
            unset($attributes[$model->getKeyName()]);
        }

        // Remove loaded relationship data from attributes
        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        // Handle special transformations for ShopInfo
        $attributes = $this->transformShopInfoAttributes($attributes, $model);

        return $attributes;
    }

    /**
     * Transform shop-info-specific attributes
     */
    protected function transformShopInfoAttributes(array $attributes, ShopInfo $shopInfo): array
    {
        // Format dates consistently
        if (isset($attributes['document_attribute_last_updated_at']) && $attributes['document_attribute_last_updated_at']) {
            $attributes['document_attribute_last_updated_at'] = $shopInfo->document_attribute_last_updated_at
                ? $shopInfo->document_attribute_last_updated_at->toISOString()
                : null;
        }

        // Ensure boolean fields are properly cast
        $booleanFields = [
            'invoice_tc_enabled',
            'catalogue_mode',
            'stripe_payment',
            'stripe_allow_accept_card_payments',
            'stripe_allow_pay_with_link',
            'take_payment',
            'dna_payment'
        ];

        foreach ($booleanFields as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = (bool) $attributes[$field];
            }
        }

        // Ensure integer fields are properly cast
        $integerFields = [
            'invoice_tc_selected_page_id',
            'vat_no',
            'company_no',
            'account_number'
        ];

        foreach ($integerFields as $field) {
            if (isset($attributes[$field]) && $attributes[$field] !== null) {
                $attributes[$field] = (int) $attributes[$field];
            }
        }

        return $attributes;
    }

    /**
     * Get type name for specific models
     */
    protected function getTypeFromModel(Model $model): string
    {
        $className = class_basename($model);

        // Map specific model classes to JSON API types
        $typeMap = [
            'ShopInfo' => 'shop-info',
            'OpenHours' => 'open-hours'
        ];

        return $typeMap[$className] ?? parent::getTypeFromModel($model);
    }

    /**
     * Get attributes for related models to ensure proper transformation
     */
    protected function getRelatedModelAttributes(Model $model): array
    {
        $className = class_basename($model);
        $attributes = $model->toArray();

        // Remove primary key
        unset($attributes[$model->getKeyName()]);

        // Model-specific attribute handling
        if ($className === 'OpenHours') {
            // Format time fields for open hours
            if (isset($attributes['open_at']) && $attributes['open_at']) {
                $attributes['open_at'] = is_string($attributes['open_at'])
                    ? $attributes['open_at']
                    : $attributes['open_at']->format('H:i:s');
            }

            if (isset($attributes['close_at']) && $attributes['close_at']) {
                $attributes['close_at'] = is_string($attributes['close_at'])
                    ? $attributes['close_at']
                    : $attributes['close_at']->format('H:i:s');
            }

            // Ensure is_open is boolean
            if (isset($attributes['is_open'])) {
                $attributes['is_open'] = (bool) $attributes['is_open'];
            }

            // Remove foreign key
            unset($attributes['shop_id']);
        }

        // Remove relationship data that got loaded
        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    /**
     * Override addToIncluded to use custom attribute handling for related models
     */
    protected function addToIncluded(Model $model, string $type): void
    {
        $key = $type . ':' . $model->getKey();

        if (!isset($this->included[$key])) {
            $this->currentDepth++;

            // Use custom attribute method for related models
            $attributes = $this->getRelatedModelAttributes($model);

            $includedData = [
                'type' => $type,
                'id' => (string) $model->getKey(),
                'attributes' => $attributes,
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
     * Validate include parameter against allowed includes
     */
    public function validateIncludes(array $includes): array
    {
        $errors = [];

        foreach ($includes as $include) {
            // Handle nested includes (if we add more relationships later)
            $mainInclude = explode('.', $include)[0];

            if (!in_array($mainInclude, $this->availableIncludes)) {
                $errors[] = [
                    'status' => '400',
                    'title' => 'Invalid include parameter',
                    'detail' => "The include parameter '{$include}' is not supported. Available includes: " . implode(', ', $this->availableIncludes),
                    'source' => ['parameter' => 'include']
                ];
            }
        }

        return $errors;
    }

    /**
     * Get available includes for this transformer
     */
    public function getAvailableIncludes(): array
    {
        return $this->availableIncludes;
    }
}
