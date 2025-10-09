<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use TheDiamondBox\ShopSync\Helpers\JsonApiIncludeParser;

/**
 * Get ShopInfo Request
 *
 * Handles validation for retrieving shop info with includes
 */
class GetShopInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'include' => 'nullable|string',
        ];
    }

    /**
     * Get parsed include parameters from the request
     *
     * @return array
     */
    public function getIncludes()
    {
        return JsonApiIncludeParser::parseFromRequest($this);
    }
}
