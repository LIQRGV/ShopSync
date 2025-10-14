<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

/**
 * Patch ShopInfo Request (PATCH)
 *
 * Handles validation for partial updates of shop info
 * Only validates and updates non-null/non-empty fields
 * Supports JSON API format
 */
class PatchShopInfoRequest extends BaseShopInfoRequest
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
        // For PATCH, all fields remain nullable (partial update)
        return array_merge(parent::rules(), $this->commonRules());
    }

    /**
     * Get the validation attributes for better error messages.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'shop name',
            'phone_number' => 'phone number',
            'email' => 'email address',
            'legal_name' => 'legal name',
            'vat_no' => 'VAT number',
            'company_no' => 'company number',
            'account_number' => 'account number',
            'open_hours.*.day' => 'day of week',
            'open_hours.*.is_open' => 'open status',
            'open_hours.*.open_at' => 'opening time',
            'open_hours.*.close_at' => 'closing time',
        ];
    }
}
