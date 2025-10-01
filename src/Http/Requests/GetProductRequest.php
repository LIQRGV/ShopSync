<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

/**
 * Get Product Request
 *
 * Simple request class for getting products with helper methods
 */
class GetProductRequest extends BaseProductRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Allow all requests for now
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // No validation rules for GET requests
        ];
    }
}