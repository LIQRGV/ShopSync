<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Abstract base class for WTM (Watch the Market) specific models.
 *
 * This class ensures that WTM-specific models can only be used when
 * the package is configured in 'wtm' mode. It throws an exception
 * if instantiated in 'wl' (WhiteLabel) mode.
 *
 * @package Liqrgv\ShopSync\Models
 */
abstract class WtmModel extends Model
{
    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     * @throws RuntimeException if not in WTM mode
     */
    public function __construct(array $attributes = [])
    {
        $this->validateMode();
        parent::__construct($attributes);
    }

    /**
     * Validate that the current mode is WTM.
     *
     * @return void
     * @throws RuntimeException if not in WTM mode
     */
    protected function validateMode(): void
    {
        if (!static::isWtmMode()) {
            $currentMode = config('products-package.mode', 'wl');
            $modelClass = static::class;

            throw new RuntimeException(
                "Model '{$modelClass}' can only be used in WTM (Watch the Market) mode. " .
                "Current mode is '{$currentMode}'. " .
                "Please set PRODUCT_PACKAGE_MODE=wtm in your environment configuration."
            );
        }
    }

    /**
     * Check if the current mode is WTM.
     *
     * @return bool
     */
    public static function isWtmMode(): bool
    {
        return strtolower(config('products-package.mode', 'wl')) === 'wtm';
    }

    /**
     * Check if the current mode is WL.
     *
     * @return bool
     */
    public static function isWlMode(): bool
    {
        return strtolower(config('products-package.mode', 'wl')) === 'wl';
    }

    /**
     * Get the current mode.
     *
     * @return string
     */
    public static function getCurrentMode(): string
    {
        return strtolower(config('products-package.mode', 'wl'));
    }

    /**
     * Check if it's safe to use this model in the current mode.
     *
     * @return bool
     */
    public static function canUse(): bool
    {
        return static::isWtmMode();
    }

    /**
     * Override the newInstance method to ensure mode validation on all instances.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     * @throws RuntimeException if not in WTM mode
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // Validate mode before creating any new instance
        $this->validateMode();

        return parent::newInstance($attributes, $exists);
    }

    /**
     * Override the newFromBuilder method to ensure mode validation when hydrating from database.
     *
     * @param  array  $attributes
     * @param  string|null  $connection
     * @return static
     * @throws RuntimeException if not in WTM mode
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        // Create instance without constructor to avoid double validation
        $model = (new static)->newInstance([], true);

        // Validate mode before hydrating
        $model->validateMode();

        $model->exists = true;
        $model->setRawAttributes((array) $attributes, true);
        $model->setConnection($connection ?: $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Override query method to validate mode before any database operations.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws RuntimeException if not in WTM mode
     */
    public static function query()
    {
        // Static validation for query operations
        if (!static::isWtmMode()) {
            $currentMode = config('products-package.mode', 'wl');
            $modelClass = static::class;

            throw new RuntimeException(
                "Cannot query '{$modelClass}' in '{$currentMode}' mode. " .
                "This model is only available in WTM (Watch the Market) mode."
            );
        }

        return parent::query();
    }
}