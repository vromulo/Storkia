<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Seller' && $this->user()->sellerProfile !== null;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'min:2', 'max:100'],
            'subcategory'             => ['required', 'string', 'max:100'],
            'discount'                => ['nullable', 'numeric', 'min:0', 'max:99'],
            'existing_pictures'       => ['nullable', 'array', 'max:10'],
            'existing_pictures.*'     => ['required', 'string', 'max:255'],
            'pictures'                => ['nullable', 'array', 'max:10'],
            'pictures.*'              => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_names'           => ['nullable', 'array'],
            'variant_names.*'         => ['nullable', 'string', 'max:80'],
            'variant_pictures'        => ['nullable', 'array'],
            'variant_pictures.*'      => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_prices'          => ['nullable', 'array'],
            'variant_prices.*'        => ['nullable', 'numeric', 'min:20', 'max:9999999.99'],
            'variant_weights'         => ['nullable', 'array'],
            'variant_weights.*'       => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'variant_stocks'          => ['nullable', 'array'],
            'variant_stocks.*'        => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'new_variant_names'       => ['nullable', 'array'],
            'new_variant_names.*'     => ['required', 'string', 'max:80'],
            'new_variant_pictures'    => ['nullable', 'array'],
            'new_variant_pictures.*'  => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'new_variant_prices'      => ['nullable', 'array'],
            'new_variant_prices.*'    => ['nullable', 'numeric', 'min:20', 'max:9999999.99'],
            'new_variant_weights'     => ['nullable', 'array'],
            'new_variant_weights.*'   => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'new_variant_stocks'      => ['nullable', 'array'],
            'new_variant_stocks.*'    => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'specifications'          => ['nullable', 'array'],
            'specifications.*.title'       => ['nullable', 'string', 'max:80'],
            'specifications.*.description' => ['nullable', 'string', 'max:255'],
            'description'             => ['nullable', 'string', 'max:5000'],
            'additional_descriptions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $uploadedCount = is_array($this->file('pictures')) ? count($this->file('pictures')) : 0;
                $existingCount = is_array($this->input('existing_pictures')) ? count($this->input('existing_pictures')) : 0;

                if ($this->has('existing_pictures') || $uploadedCount > 0) {
                    $total = $existingCount + $uploadedCount;
                    if ($total > 10) {
                        $validator->errors()->add(
                            'pictures',
                            'Total main product pictures (retained + newly uploaded) cannot exceed 10 images.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'pictures.max'         => 'You can upload at most 10 main product pictures.',
            'pictures.*.mimetypes' => 'Each main product picture must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'pictures.*.max'       => 'Each main product picture must not exceed 5 MB.',
        ];
    }
}