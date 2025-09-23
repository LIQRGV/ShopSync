<?php

namespace Liqrgv\ShopSync\Http\Requests;

use Illuminate\Http\Request;
use Liqrgv\ShopSync\Http\Traits\ProductRequestHelpers;

/**
 * Get Product Request
 *
 * Simple request class for getting products with helper methods
 */
class GetProductRequest extends Request
{
    use ProductRequestHelpers;
}