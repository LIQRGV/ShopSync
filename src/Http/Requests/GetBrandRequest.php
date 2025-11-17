<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:500',
            'per_page' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get filters from request
     *
     * @return array
     */
    public function getFilters(): array
    {
        $filters = [];

        if ($this->filled('search')) {
            $filters['search'] = $this->input('search');
        }

        if ($this->filled('limit')) {
            $filters['limit'] = (int) $this->input('limit');
        }

        return $filters;
    }

    /**
     * Get pagination parameters
     *
     * @return array
     */
    public function getPagination(): array
    {
        return [
            'paginate' => $this->has('per_page'),
            'per_page' => (int) $this->input('per_page', 100),
            'page' => (int) $this->input('page', 1),
        ];
    }
}
