<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Database\Eloquent\Model;

/**
 * Brand Transformer
 *
 * Handles transformation of Brand models to JSON API format.
 * Follows Single Responsibility Principle - only transforms brands.
 */
class BrandTransformer extends JsonApiTransformer
{
    /**
     * Transform a single Brand into JSON API format
     */
    public function transformBrand(Model $brand): array
    {
        return $this->transformItem($brand, 'brands');
    }

    /**
     * Transform a collection of Brands into JSON API format
     */
    public function transformBrands($brands): array
    {
        return $this->transformCollection($brands, 'brands');
    }

    /**
     * Get model attributes, customized for Brand
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

        // Include brand-specific computed attributes
        if (method_exists($model, 'hasLogo')) {
            $attributes['has_logo'] = $model->hasLogo();
        }
        if (method_exists($model, 'getProductsCountAttribute')) {
            $attributes['products_count'] = $model->products_count;
        }

        return $attributes;
    }

    /**
     * Get type name for Brand models
     */
    protected function getTypeFromModel(Model $model): string
    {
        return 'brands';
    }
}
