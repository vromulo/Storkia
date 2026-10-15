<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Seller' && (bool) $this->user()->sellerProfile;
    }

    public function rules(): array
    {
        $hasSubVariants = $this->filled('sub_variant_title');

        $rules = [
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
        ];

        if (! $hasSubVariants) {
            $rules['variant_prices']    = ['required', 'array'];
            $rules['variant_prices.*']  = ['required', 'numeric', 'min:0'];
            $rules['variant_weights']   = ['required', 'array'];
            $rules['variant_weights.*'] = ['required', 'numeric', 'min:0'];
            $rules['variant_stocks']    = ['required', 'array'];
            $rules['variant_stocks.*']  = ['required', 'integer', 'min:0'];
        } else {
            $rules['sub_variant_names']       = ['required', 'array'];
            $rules['sub_variant_names.*']     = ['required', 'array', 'min:1'];
            $rules['sub_variant_names.*.*']   = ['required', 'string', 'max:50'];
            $rules['sub_variant_prices']      = ['required', 'array'];
            $rules['sub_variant_prices.*']    = ['required', 'array', 'min:1'];
            $rules['sub_variant_prices.*.*']  = ['required', 'numeric', 'min:0'];
            $rules['sub_variant_weights']     = ['required', 'array'];
            $rules['sub_variant_weights.*']   = ['required', 'array', 'min:1'];
            $rules['sub_variant_weights.*.*'] = ['required', 'numeric', 'min:0'];
            $rules['sub_variant_stocks']      = ['required', 'array'];
            $rules['sub_variant_stocks.*']    = ['required', 'array', 'min:1'];
            $rules['sub_variant_stocks.*.*']  = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'variant_title'          => 'variant title',
            'sub_variant_title'      => 'sub-variant title',
            'variant_names.*'        => 'variant name',
            'variant_prices.*'       => 'variant price',
            'variant_weights.*'      => 'variant weight',
            'variant_stocks.*'       => 'variant stock',
            'sub_variant_names.*.*'  => 'sub-variant name',
            'sub_variant_prices.*.*' => 'sub-variant price',
            'sub_variant_weights.*.*'=> 'sub-variant weight',
            'sub_variant_stocks.*.*' => 'sub-variant stock',
            'pictures.*'             => 'product picture',
            'variant_pictures.*'     => 'variant picture',
        ];
    }
}