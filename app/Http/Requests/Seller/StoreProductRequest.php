<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Seller' && $this->user()->sellerProfile !== null;
    }

    public function rules(): array
    {
        $hasSubVariantTitle = trim((string) $this->input('sub_variant_title', '')) !== '';

        return [
            'name'                      => ['required', 'string', 'min:2', 'max:100'],
            'category'                  => ['required', 'string', 'max:80'],
            'subcategory'               => ['required', 'string', 'max:100'],
            'discount'                  => ['nullable', 'numeric', 'min:0', 'max:99'],
            'pictures'                  => ['required', 'array', 'min:1', 'max:10'],
            'pictures.*'                => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_title'             => ['required', 'string', 'max:60'],
            'sub_variant_title'         => ['nullable', 'string', 'max:60'],
            'variant_names'             => ['required', 'array', 'min:1'],
            'variant_names.*'           => ['required', 'string', 'max:80'],
            'variant_pictures'          => ['nullable', 'array'],
            'variant_pictures.*'        => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_pictures_base64'   => ['nullable', 'array'],
            'variant_pictures_base64.*' => ['nullable', 'string'],
            'variant_prices'            => $hasSubVariantTitle ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'variant_prices.*'          => $hasSubVariantTitle ? ['nullable', 'numeric', 'min:20', 'max:9999999.99'] : ['required', 'numeric', 'min:20', 'max:9999999.99'],
            'variant_weights'           => $hasSubVariantTitle ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'variant_weights.*'         => $hasSubVariantTitle ? ['nullable', 'numeric', 'min:0.01', 'max:5000'] : ['required', 'numeric', 'min:0.01', 'max:5000'],
            'variant_stocks'            => $hasSubVariantTitle ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'variant_stocks.*'          => $hasSubVariantTitle ? ['nullable', 'integer', 'min:0', 'max:1000000'] : ['required', 'integer', 'min:0', 'max:1000000'],
            'sub_variant_names'         => $hasSubVariantTitle ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'sub_variant_names.*.*'     => $hasSubVariantTitle ? ['required', 'string', 'max:80'] : ['nullable', 'string', 'max:80'],
            'sub_variant_prices'        => $hasSubVariantTitle ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'sub_variant_prices.*.*'    => $hasSubVariantTitle ? ['required', 'numeric', 'min:20', 'max:9999999.99'] : ['nullable', 'numeric', 'min:20', 'max:9999999.99'],
            'sub_variant_weights'       => $hasSubVariantTitle ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'sub_variant_weights.*.*'   => $hasSubVariantTitle ? ['required', 'numeric', 'min:0.01', 'max:5000'] : ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'sub_variant_stocks'        => $hasSubVariantTitle ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'sub_variant_stocks.*.*'    => $hasSubVariantTitle ? ['required', 'integer', 'min:0', 'max:1000000'] : ['nullable', 'integer', 'min:0', 'max:1000000'],
            'specifications'            => ['nullable', 'array'],
            'specifications.*.title'       => ['nullable', 'string', 'max:80'],
            'specifications.*.description' => ['nullable', 'string', 'max:255'],
            'description'               => ['nullable', 'string', 'max:5000'],
            'additional_descriptions'   => ['nullable', 'string', 'max:5000'],
            'per_buyer_limit'           => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $variantNames = (array) $this->input('variant_names', []);
                $uploadedFiles = (array) $this->file('variant_pictures', []);
                $base64Map = (array) $this->input('variant_pictures_base64', []);

                foreach ($variantNames as $vKey => $vName) {
                    $hasFile = isset($uploadedFiles[$vKey]) && $uploadedFiles[$vKey] !== null;
                    $rawB64 = isset($base64Map[$vKey]) ? trim((string) $base64Map[$vKey]) : '';
                    $hasBase64 = str_starts_with($rawB64, 'data:image/');

                    if (! $hasFile && ! $hasBase64) {
                        $label = trim((string) $vName) !== '' ? trim((string) $vName) : 'Variant';
                        $validator->errors()->add(
                            "variant_pictures.{$vKey}",
                            "Please upload a dedicated photo for \"{$label}\"."
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                   => 'Product Name is required.',
            'subcategory.required'            => 'Please select a Subcategory for your product.',
            'pictures.required'               => 'Please upload at least 1 main product picture (up to 10 pictures allowed).',
            'pictures.min'                    => 'Please upload at least 1 main product picture.',
            'pictures.max'                    => 'You can upload a maximum of 10 main product pictures. The 1st picture serves as the product cover.',
            'pictures.*.mimetypes'            => 'Each main product picture must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'pictures.*.max'                  => 'Each main product picture must not exceed 5 MB.',
            'variant_title.required'          => 'Please enter a Variant Title (e.g., Color, Style, Pack Size).',
            'variant_names.required'          => 'Please add at least 1 product variant.',
            'variant_names.*.required'        => 'Every variant must have a name.',
            'variant_pictures.*.required'     => 'Each product variant must have its own dedicated variant image.',
            'variant_pictures.*.mimetypes'    => 'Each variant image must be a valid JPG, PNG, or WEBP file (max 5 MB).',
            'variant_prices.*.required'       => 'Every variant must have a price (minimum ₱20.00).',
            'variant_prices.*.min'            => 'Variant price must be at least ₱20.00.',
            'variant_weights.*.required'      => 'Every variant must have a weight in KG (minimum 0.01 KG).',
            'variant_stocks.*.required'       => 'Every variant must have a stock quantity.',
            'sub_variant_names.required'      => 'When Sub-Variant Title is set, please add at least 1 sub-variant for each main variant.',
            'sub_variant_names.*.*.required'  => 'Every sub-variant must have a name.',
            'sub_variant_prices.*.*.required' => 'Every sub-variant must have a price (minimum ₱20.00).',
            'sub_variant_prices.*.*.min'      => 'Sub-variant price must be at least ₱20.00.',
            'sub_variant_weights.*.*.required'=> 'Every sub-variant must have a weight in KG (minimum 0.01 KG).',
            'sub_variant_stocks.*.*.required' => 'Every sub-variant must have a stock quantity.',
        ];
    }
}