<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Database\Eloquent\Model;

/**
 * Supplier Transformer
 *
 * Handles transformation of Supplier models to JSON API format.
 * Follows Single Responsibility Principle - only transforms suppliers.
 */
class SupplierTransformer extends JsonApiTransformer
{
    /**
     * Transform a single Supplier into JSON API format
     */
    public function transformSupplier(Model $supplier): array
    {
        return $this->transformItem($supplier, 'suppliers');
    }

    /**
     * Transform a collection of Suppliers into JSON API format
     */
    public function transformSuppliers($suppliers): array
    {
        return $this->transformCollection($suppliers, 'suppliers');
    }

    /**
     * Get model attributes, customized for Supplier
     */
    protected function getModelAttributes(Model $model): array
    {
        $attributes = $model->toArray();

        // Remove primary key from attributes (it's in the id field)
        unset($attributes[$model->getKeyName()]);

        // Remove loaded relationship data from attributes
        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        // Include supplier-specific computed attributes
        if (method_exists($model, 'getFullAddressAttribute')) {
            $attributes['full_address'] = $model->full_address;
        }
        if (method_exists($model, 'hasContactInfo')) {
            $attributes['has_contact_info'] = $model->hasContactInfo();
        }
        if (method_exists($model, 'getRatingStarsAttribute')) {
            $attributes['rating_stars'] = $model->rating_stars;
        }

        return $attributes;
    }

    /**
     * Get type name for Supplier models
     */
    protected function getTypeFromModel(Model $model): string
    {
        return 'suppliers';
    }
}
