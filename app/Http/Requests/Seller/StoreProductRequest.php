<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Seller' && $this->user()->sellerProfile !== null;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'min:2', 'max:100'],
            'category'                => ['required', 'string', 'max:80'],
            'subcategory'             => ['required', 'string', 'max:100'],
            'discount'                => ['nullable', 'numeric', 'min:0', 'max:99'],
            'pictures'                => ['required', 'array', 'min:1', 'max:10'],
            'pictures.*'              => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_title'           => ['required', 'string', 'max:60'],
            'sub_variant_title'       => ['nullable', 'string', 'max:60'],
            'variant_names'           => ['required', 'array', 'min:1'],
            'variant_names.*'         => ['required', 'string', 'max:80'],
            'variant_pictures'        => ['required', 'array', 'min:1'],
            'variant_pictures.*'      => [
                'required',
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
            'sub_variant_names'       => ['nullable', 'array'],
            'sub_variant_names.*.*'   => ['required', 'string', 'max:80'],
            'sub_variant_prices'      => ['nullable', 'array'],
            'sub_variant_prices.*.*'  => ['required', 'numeric', 'min:20', 'max:9999999.99'],
            'sub_variant_weights'     => ['nullable', 'array'],
            'sub_variant_weights.*.*' => ['required', 'numeric', 'min:0.01', 'max:5000'],
            'sub_variant_stocks'      => ['nullable', 'array'],
            'sub_variant_stocks.*.*'  => ['required', 'integer', 'min:0', 'max:1000000'],
            'specifications'          => ['nullable', 'array'],
            'specifications.*.title'       => ['nullable', 'string', 'max:80'],
            'specifications.*.description' => ['nullable', 'string', 'max:255'],
            'description'             => ['nullable', 'string', 'max:5000'],
            'additional_descriptions' => ['nullable', 'string', 'max:5000'],
            'per_buyer_limit'         => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pictures.required'           => 'Please upload at least 1 main product picture (up to 10 pictures allowed).',
            'pictures.min'                => 'Please upload at least 1 main product picture.',
            'pictures.max'                => 'You can upload a maximum of 10 main product pictures. The 1st picture serves as the product cover.',
            'pictures.*.mimetypes'        => 'Each main product picture must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'pictures.*.max'              => 'Each main product picture must not exceed 5 MB.',
            'variant_pictures.*.required' => 'Each product variant must have its own dedicated variant image.',
            'variant_pictures.*.mimetypes'=> 'Each variant image must be a valid JPG, PNG, or WEBP file (max 5 MB).',
            'variant_prices.*.min'        => 'Variant price must be at least ₱20.00.',
            'sub_variant_prices.*.*.min'  => 'Sub-variant price must be at least ₱20.00.',
        ];
    }
}