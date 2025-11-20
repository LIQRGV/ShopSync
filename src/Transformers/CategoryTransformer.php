<?php

namespace TheDiamondBox\ShopSync\Transformers;

use Illuminate\Database\Eloquent\Model;

/**
 * Category Transformer
 *
 * Handles transformation of Category models to JSON API format.
 * Follows Single Responsibility Principle - only transforms categories.
 */
class CategoryTransformer extends JsonApiTransformer
{
    /**
     * Transform a single Category into JSON API format
     */
    public function transformCategory(Model $category): array
    {
        return $this->transformItem($category, 'categories');
    }

    /**
     * Transform a collection of Categories into JSON API format
     */
    public function transformCategories($categories): array
    {
        return $this->transformCollection($categories, 'categories');
    }

    /**
     * Get model attributes, customized for Category
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

        // Include useful computed attributes for categories
        if (method_exists($model, 'getProductsCountAttribute')) {
            $attributes['products_count'] = $model->products_count;
        }

        return $attributes;
    }

    /**
     * Get type name for Category models
     */
    protected function getTypeFromModel(Model $model): string
    {
        return 'categories';
    }
}
