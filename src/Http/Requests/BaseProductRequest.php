<?php

namespace Liqrgv\ShopSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Liqrgv\ShopSync\Http\Traits\ProductRequestHelpers;

/**
 * Base Product Request
 *
 * Provides common functionality for all product-related requests with PHP 7.2 compatibility
 */
abstract class BaseProductRequest extends FormRequest
{
    use ProductRequestHelpers;
}