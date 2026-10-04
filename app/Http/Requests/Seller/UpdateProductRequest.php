<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:100'],
            'subcategory'             => ['required', 'string'],
            'discount'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description'             => ['nullable', 'string'],
            'additional_descriptions' => ['nullable', 'string'],
            'pictures'                => ['nullable', 'array', 'min:1'],
            'pictures.*'              => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // Variant validations for editing
            'variant_names'           => ['nullable', 'array'],
            'variant_names.*'         => ['required', 'string', 'max:50'],
            'variant_pictures'        => ['nullable', 'array'],
            'variant_pictures.*'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            
            'variant_prices'          => ['nullable', 'array'],
            'variant_weights'         => ['nullable', 'array'],
            'variant_stocks'          => ['nullable', 'array'],
            
            'sub_variant_names'       => ['nullable', 'array'],
            'sub_variant_prices'      => ['nullable', 'array'],
            'sub_variant_weights'     => ['nullable', 'array'],
            'sub_variant_stocks'      => ['nullable', 'array'],
        ];
    }
}