<?php

namespace TheDiamondBox\ShopSync\Transformers;

use TheDiamondBox\ShopSync\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product JSON API Transformer
 *
 * Specific transformer for Product models that extends the base
 * JsonApiTransformer with product-specific transformations and relationships.
 */
class ProductJsonApiTransformer extends JsonApiTransformer
{
    /**
     * Available relationships for products
     */
    protected $availableIncludes = [
        'category',
        'brand',
        'location',
        'supplier',
        'attributes',
        'productAttributes'
    ];

    /**
     * Maximum include depth to prevent infinite recursion
     */
    protected $maxDepth = 3;

    public function __construct()
    {
        parent::__construct($this->availableIncludes, $this->maxDepth);
    }

    /**
     * Transform a single Product into JSON API format
     */
    public function transformProduct(Product $product, array $includes = []): array
    {
        $this->setIncludes($includes);
        return $this->transformItem($product, 'products');
    }

    /**
     * Transform a collection of Products into JSON API format
     */
    public function transformProducts($products, array $includes = []): array
    {
        $this->setIncludes($includes);
        return $this->transformCollection($products, 'products');
    }

    /**
     * Get model attributes, customized for Product
     */
    protected function getModelAttributes(Model $model): array
    {
        if (!$model instanceof Product) {
            return parent::getModelAttributes($model);
        }

        $attributes = $model->toArray();

        // Remove primary key from attributes (it's in the id field)
        unset($attributes[$model->getKeyName()]);

        // Remove foreign keys from attributes (they're in relationships)
        $foreignKeys = [
            'category_id',
            'brand_id',
            'location_id',
            'supplier_id'
        ];

        foreach ($foreignKeys as $foreignKey) {
            unset($attributes[$foreignKey]);
        }

        // Remove loaded relationship data from attributes
        $relationships = $model->getRelations();
        foreach ($relationships as $key => $relationship) {
            unset($attributes[$key]);
        }

        // Handle special transformations
        $attributes = $this->transformProductAttributes($attributes, $model);

        return $attributes;
    }

    /**
     * Transform product-specific attributes
     */
    protected function transformProductAttributes(array $attributes, Product $product): array
    {
        // Convert related_products from JSON string to array for consistency
        if (isset($attributes['related_products'])) {
            if (is_string($attributes['related_products'])) {
                $attributes['related_products'] = json_decode($attributes['related_products'], true) ?: [];
            }
        }

        // Format dates consistently
        if (isset($attributes['purchase_date']) && $attributes['purchase_date']) {
            $attributes['purchase_date'] = $product->purchase_date ? $product->purchase_date->format('Y-m-d') : null;
        }

        // Format decimal prices to ensure consistent formatting
        $priceFields = ['cost_price', 'price', 'sale_price', 'trade_price'];
        foreach ($priceFields as $field) {
            if (isset($attributes[$field]) && $attributes[$field] !== null) {
                $attributes[$field] = number_format((float) $attributes[$field], 2, '.', '');
            }
        }

        // Add computed attributes that might be useful for API consumers
        $attributes['full_sku'] = $product->full_sku;
        $attributes['formatted_price'] = $product->formatted_price;
        $attributes['effective_price'] = number_format($product->effective_price, 2, '.', '');
        $attributes['is_on_sale'] = $product->isOnSale();
        $attributes['has_image'] = $product->hasImage();
        $attributes['image_url'] = $product->image_url;

        // Remove cost_price if it's hidden (security)
        if (in_array('cost_price', $product->getHidden())) {
            unset($attributes['cost_price']);
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
            'Product' => 'products',
            'Category' => 'categories',
            'Brand' => 'brands',
            'Location' => 'locations',
            'Supplier' => 'suppliers',
            'Attribute' => 'attributes',
            'ProductAttribute' => 'product-attributes'
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

        // Remove primary key and timestamps for cleaner output
        unset($attributes[$model->getKeyName()]);

        // Model-specific attribute handling
        switch ($className) {
            case 'Category':
                // Include useful computed attributes for categories
                if (method_exists($model, 'getProductsCountAttribute')) {
                    $attributes['products_count'] = $model->products_count;
                }
                break;

            case 'Brand':
                // Include brand-specific computed attributes
                if (method_exists($model, 'hasLogo')) {
                    $attributes['has_logo'] = $model->hasLogo();
                }
                if (method_exists($model, 'getProductsCountAttribute')) {
                    $attributes['products_count'] = $model->products_count;
                }
                break;

            case 'Location':
                // Include location-specific computed attributes
                if (method_exists($model, 'getFullAddressAttribute')) {
                    $attributes['full_address'] = $model->full_address;
                }
                if (method_exists($model, 'hasContactInfo')) {
                    $attributes['has_contact_info'] = $model->hasContactInfo();
                }
                break;

            case 'Supplier':
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
                break;

            case 'Attribute':
                // Include attribute-specific computed attributes
                if (method_exists($model, 'hasOptions')) {
                    $attributes['has_options'] = $model->hasOptions();
                }
                if (method_exists($model, 'getFormattedOptionsAttribute')) {
                    $attributes['formatted_options'] = $model->formatted_options;
                }
                break;

            case 'ProductAttribute':
                // For product attributes, include the formatted value and attribute details
                if (method_exists($model, 'getFormattedValueAttribute')) {
                    $attributes['formatted_value'] = $model->formatted_value;
                }
                if (method_exists($model, 'getAttributeNameAttribute')) {
                    $attributes['attribute_name'] = $model->attribute_name;
                }
                if (method_exists($model, 'getAttributeCodeAttribute')) {
                    $attributes['attribute_code'] = $model->attribute_code;
                }
                break;
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
            // Handle nested includes (e.g., 'category.parent')
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

    /**
     * Transform with validation
     */
    public function transformWithValidation(Product $product, array $includes = []): array
    {
        // Validate includes first
        $errors = $this->validateIncludes($includes);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return $this->transformProduct($product, $includes);
    }

    /**
     * Transform collection with validation
     */
    public function transformCollectionWithValidation($products, array $includes = []): array
    {
        // Validate includes first
        $errors = $this->validateIncludes($includes);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return $this->transformProducts($products, $includes);
    }
}