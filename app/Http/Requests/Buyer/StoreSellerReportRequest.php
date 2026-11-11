<?php

namespace App\Http\Requests\Buyer;

use App\Models\SellerProfile;
use App\Models\SellerReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'Buyer';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description'     => trim((string) $this->input('description', '')),
            'seller_order_id' => $this->filled('seller_order_id') ? $this->input('seller_order_id') : null,
        ]);
    }

    public function rules(): array
    {
        $seller = $this->route('seller');
        $sellerProfileId = $seller instanceof SellerProfile ? (int) $seller->id : (int) $seller;
        $buyerId = (int) $this->user()->id;

        return [
            'reason_category'    => ['required', 'string', Rule::in(SellerReport::categoryKeys())],
            'description'        => [
                'required',
                'string',
                'min:' . SellerReport::MIN_DESCRIPTION_LENGTH,
                'max:' . SellerReport::MAX_DESCRIPTION_LENGTH,
            ],
            // Optional, but when given it must be one of THIS buyer's orders from THIS seller.
            'seller_order_id'    => [
                'nullable',
                'integer',
                Rule::exists('seller_orders', 'id')->where(
                    fn ($query) => $query
                        ->where('buyer_id', $buyerId)
                        ->where('seller_profile_id', $sellerProfileId)
                ),
            ],
            'evidence_photos'    => ['nullable', 'array', 'max:' . SellerReport::MAX_EVIDENCE_PHOTOS],
            'evidence_photos.*'  => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:' . SellerReport::MAX_EVIDENCE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_category.required'   => 'Please choose what you are reporting this seller for.',
            'reason_category.in'         => 'Please choose one of the listed reasons.',
            'description.required'       => 'Please describe what happened.',
            'description.min'            => 'Please give a little more detail (at least ' . SellerReport::MIN_DESCRIPTION_LENGTH . ' characters).',
            'description.max'            => 'Your description is too long (maximum ' . SellerReport::MAX_DESCRIPTION_LENGTH . ' characters).',
            'seller_order_id.exists'     => 'That order does not belong to you or to this seller.',
            'evidence_photos.max'        => 'You may upload a maximum of ' . SellerReport::MAX_EVIDENCE_PHOTOS . ' evidence photos.',
            'evidence_photos.*.file'     => 'Each evidence file must be a valid upload.',
            'evidence_photos.*.mimetypes' => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.mimes'    => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.max'      => 'Each evidence photo must not exceed 5MB.',
        ];
    }
}