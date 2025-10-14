<?php

namespace TheDiamondBox\ShopSync\Transformers;

use TheDiamondBox\ShopSync\Models\ShopInfo;
use Illuminate\Database\Eloquent\Model;

class ShopInfoJsonApiTransformer extends JsonApiTransformer
{
    protected $availableIncludes = [];
    protected $maxDepth = 2;

    public function __construct()
    {
        parent::__construct($this->availableIncludes, $this->maxDepth);
    }

    public function transformShopInfo(ShopInfo $shopInfo, array $includes = []): array
    {
        $this->setIncludes($includes);
        return $this->transformItem($shopInfo, 'shop-info');
    }

    protected function getModelAttributes(Model $model): array
    {
        if (!$model instanceof ShopInfo) {
            return parent::getModelAttributes($model);
        }

        $attributes = $model->toArray();

        if ($model->getKeyName()) {
            unset($attributes[$model->getKeyName()]);
        }

        if ($model->relationLoaded('openHours')) {
            $openHours = $model->openHours->map(function ($openHour) {
                return [
                    'day' => $openHour->day,
                    'is_open' => (bool) $openHour->is_open,
                    'open_at' => $openHour->open_at,
                    'close_at' => $openHour->close_at,
                ];
            })->toArray();

            $attributes['open_hours'] = $openHours;
        }

        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        $attributes = $this->transformShopInfoAttributes($attributes, $model);

        return $attributes;
    }

    protected function transformShopInfoAttributes(array $attributes, ShopInfo $shopInfo): array
    {
        if (isset($attributes['document_attribute_last_updated_at']) && $attributes['document_attribute_last_updated_at']) {
            $attributes['document_attribute_last_updated_at'] = $shopInfo->document_attribute_last_updated_at
                ? $shopInfo->document_attribute_last_updated_at->toISOString()
                : null;
        }

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

    protected function getTypeFromModel(Model $model): string
    {
        $className = class_basename($model);

        $typeMap = [
            'ShopInfo' => 'shop-info',
            'OpenHours' => 'open-hours'
        ];

        return $typeMap[$className] ?? parent::getTypeFromModel($model);
    }

    protected function getRelatedModelAttributes(Model $model): array
    {
        $className = class_basename($model);
        $attributes = $model->toArray();

        unset($attributes[$model->getKeyName()]);

        if ($className === 'OpenHours') {
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

            if (isset($attributes['is_open'])) {
                $attributes['is_open'] = (bool) $attributes['is_open'];
            }

            unset($attributes['shop_id']);
        }

        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    protected function addToIncluded(Model $model, string $type): void
    {
        $key = $type . ':' . $model->getKey();

        if (!isset($this->included[$key])) {
            $this->currentDepth++;

            $attributes = $this->getRelatedModelAttributes($model);

            $includedData = [
                'type' => $type,
                'id' => (string) $model->getKey(),
                'attributes' => $attributes,
            ];

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

    public function validateIncludes(array $includes): array
    {
        $errors = [];

        foreach ($includes as $include) {
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

    public function getAvailableIncludes(): array
    {
        return $this->availableIncludes;
    }
}
