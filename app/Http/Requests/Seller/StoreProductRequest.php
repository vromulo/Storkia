<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Seller' && (bool) $this->user()->sellerProfile;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:100'],
            'category'                => ['nullable', 'string'],
            'subcategory'             => ['required', 'string'],
            'discount'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description'             => ['nullable', 'string'],
            'additional_descriptions' => ['nullable', 'string'],
            'pictures'                => ['required', 'array', 'min:1'],
            'pictures.*'              => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // Variant Rules
            'variant_title'           => ['required', 'string', 'max:50'],
            'sub_variant_title'       => ['nullable', 'string', 'max:50'],
            'variant_names'           => ['required', 'array', 'min:1'],
            'variant_names.*'         => ['required', 'string', 'max:50'],
            'variant_pictures'        => ['nullable', 'array'],
            'variant_pictures.*'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // When no sub-variants are present (price depends on main variant)
            'variant_prices'          => ['nullable', 'array'],
            'variant_prices.*'        => ['nullable', 'numeric', 'min:0'],
            'variant_weights'         => ['nullable', 'array'],
            'variant_weights.*'       => ['nullable', 'numeric', 'min:0'],
            'variant_stocks'          => ['nullable', 'array'],
            'variant_stocks.*'        => ['nullable', 'integer', 'min:0'],

            // When sub-variants are present
            'sub_variant_names'       => ['nullable', 'array'],
            'sub_variant_names.*'     => ['nullable', 'array'],
            'sub_variant_names.*.*'   => ['nullable', 'string', 'max:50'],
            'sub_variant_prices'      => ['nullable', 'array'],
            'sub_variant_prices.*'    => ['nullable', 'array'],
            'sub_variant_prices.*.*'  => ['nullable', 'numeric', 'min:0'],
            'sub_variant_weights'     => ['nullable', 'array'],
            'sub_variant_weights.*'   => ['nullable', 'array'],
            'sub_variant_weights.*.*' => ['nullable', 'numeric', 'min:0'],
            'sub_variant_stocks'      => ['nullable', 'array'],
            'sub_variant_stocks.*'    => ['nullable', 'array'],
            'sub_variant_stocks.*.*'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Custom validation attribute names.
     */
    public function attributes(): array
    {
        return [
            'variant_title'    => 'variant title',
            'variant_names.*'  => 'variant name',
            'pictures.*'       => 'product picture',
        ];
    }
}