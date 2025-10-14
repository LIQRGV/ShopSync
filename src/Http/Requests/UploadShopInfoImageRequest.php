<?php

namespace TheDiamondBox\ShopSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadShopInfoImageRequest extends FormRequest
{
    private const VALID_IMAGE_FIELDS = [
        'logo',
        'favicon',
        'banner_1',
        'banner_2',
        'sell_watch_image',
        'valuations_additional_logo',
    ];

    private const FIELDS_WITH_ORIGINAL = [
        'logo',
        'favicon',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field' => [
                'required',
                'string',
                'in:' . implode(',', self::VALID_IMAGE_FIELDS),
            ],
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif,webp,svg',
                'max:7168',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'field.required' => 'Please specify which image field to update.',
            'field.in' => 'Invalid image field. Allowed fields: ' . implode(', ', self::VALID_IMAGE_FIELDS),
            'image.required' => 'Please provide an image file.',
            'image.file' => 'The uploaded file must be a valid file.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, jpg, png, gif, webp, svg.',
            'image.max' => 'The image size must be less than 7MB.',
        ];
    }

    public function getImageField(): string
    {
        return $this->validated()['field'];
    }

    public function getImageFile()
    {
        return $this->file('image');
    }

    public function hasOriginalField(): bool
    {
        return in_array($this->getImageField(), self::FIELDS_WITH_ORIGINAL);
    }

    public function getOriginalFieldName(): ?string
    {
        if (!$this->hasOriginalField()) {
            return null;
        }

        return 'original_' . $this->getImageField();
    }
}
