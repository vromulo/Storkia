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

    protected function prepareForValidation(): void
    {
        $rawDiscount = $this->input('discount');
        if ($rawDiscount === null || trim((string) $rawDiscount) === '') {
            $this->merge(['discount' => 0]);
        }

        if ($this->has('specifications') && is_array($this->input('specifications'))) {
            $filteredSpecs = array_values(array_filter(
                $this->input('specifications', []),
                function ($spec) {
                    if (! is_array($spec)) {
                        return false;
                    }
                    $title = trim((string) ($spec['title'] ?? ''));
                    $desc = trim((string) ($spec['description'] ?? ''));
                    return $title !== '' || $desc !== '';
                }
            ));
            $this->merge(['specifications' => $filteredSpecs]);
        }

        $base64MainList = array_values(array_filter(
            (array) $this->input('pictures_base64', []),
            fn ($item) => is_string($item) && str_starts_with(trim($item), 'data:image/')
        ));

        if (! empty($base64MainList)) {
            $this->merge(['pictures_base64' => array_slice($base64MainList, 0, 10)]);
            $this->files->remove('pictures');
            $this->request->remove('pictures');
        }

        $variantBase64Map = (array) $this->input('variant_pictures_base64', []);
        if (! empty($variantBase64Map) && $this->files->has('variant_pictures')) {
            $uploadedVariantFiles = (array) $this->file('variant_pictures', []);
            foreach ($variantBase64Map as $vKey => $b64Val) {
                if (is_string($b64Val) && str_starts_with(trim($b64Val), 'data:image/')) {
                    unset($uploadedVariantFiles[$vKey]);
                }
            }
            if (empty($uploadedVariantFiles)) {
                $this->files->remove('variant_pictures');
                $this->request->remove('variant_pictures');
            } else {
                $this->files->set('variant_pictures', $uploadedVariantFiles);
            }
        }

        $newVariantBase64Map = (array) $this->input('new_variant_pictures_base64', []);
        if (! empty($newVariantBase64Map) && $this->files->has('new_variant_pictures')) {
            $uploadedNewVariantFiles = (array) $this->file('new_variant_pictures', []);
            foreach ($newVariantBase64Map as $nvKey => $b64Val) {
                if (is_string($b64Val) && str_starts_with(trim($b64Val), 'data:image/')) {
                    unset($uploadedNewVariantFiles[$nvKey]);
                }
            }
            if (empty($uploadedNewVariantFiles)) {
                $this->files->remove('new_variant_pictures');
                $this->request->remove('new_variant_pictures');
            } else {
                $this->files->set('new_variant_pictures', $uploadedNewVariantFiles);
            }
        }
    }

    public function rules(): array
    {
        $hasBase64Main = ! empty($this->input('pictures_base64', []));

        return [
            'name'                          => ['required', 'string', 'min:2', 'max:100'],
            'subcategory'                   => ['required', 'string', 'max:100'],
            'discount'                      => ['nullable', 'numeric', 'min:0', 'max:99'],
            'existing_pictures'             => ['nullable', 'array', 'max:10'],
            'existing_pictures.*'           => ['required', 'string', 'max:255'],
            'pictures'                      => ['nullable', 'array', 'max:10'],
            'pictures.*'                    => $hasBase64Main
                ? ['nullable']
                : [
                    'nullable',
                    'file',
                    'mimetypes:image/jpeg,image/png,image/webp',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
            'pictures_base64'               => ['nullable', 'array', 'max:10'],
            'pictures_base64.*'             => ['nullable', 'string'],
            'variant_names'                 => ['nullable', 'array'],
            'variant_names.*'               => ['nullable', 'string', 'max:80'],
            'variant_pictures'              => ['nullable', 'array'],
            'variant_pictures.*'            => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variant_pictures_base64'       => ['nullable', 'array'],
            'variant_pictures_base64.*'     => ['nullable', 'string'],
            'variant_prices'                => ['nullable', 'array'],
            'variant_prices.*'              => ['nullable', 'numeric', 'min:20', 'max:9999999.99'],
            'variant_weights'               => ['nullable', 'array'],
            'variant_weights.*'             => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'variant_stocks'                => ['nullable', 'array'],
            'variant_stocks.*'              => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'new_variant_names'             => ['nullable', 'array'],
            'new_variant_names.*'           => ['required', 'string', 'max:80'],
            'new_variant_pictures'          => ['nullable', 'array'],
            'new_variant_pictures.*'        => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'new_variant_pictures_base64'   => ['nullable', 'array'],
            'new_variant_pictures_base64.*' => ['nullable', 'string'],
            'new_variant_prices'            => ['nullable', 'array'],
            'new_variant_prices.*'          => ['nullable', 'numeric', 'min:20', 'max:9999999.99'],
            'new_variant_weights'           => ['nullable', 'array'],
            'new_variant_weights.*'         => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'new_variant_stocks'            => ['nullable', 'array'],
            'new_variant_stocks.*'          => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'specifications'                => ['nullable', 'array'],
            'specifications.*.title'        => ['nullable', 'string', 'max:80'],
            'specifications.*.description'  => ['nullable', 'string', 'max:255'],
            'description'                   => ['nullable', 'string', 'max:5000'],
            'additional_descriptions'       => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $uploadedFiles = array_filter(
                    (array) $this->file('pictures', []),
                    fn ($f) => $f && $f->isValid()
                );
                $base64Pictures = array_values(array_filter(
                    (array) $this->input('pictures_base64', []),
                    fn ($item) => is_string($item) && str_starts_with(trim($item), 'data:image/')
                ));
                $newPicturesCount = count($base64Pictures) > 0 ? count($base64Pictures) : count($uploadedFiles);
                $existingCount = is_array($this->input('existing_pictures')) ? count($this->input('existing_pictures')) : 0;

                if ($this->has('existing_pictures') || $newPicturesCount > 0) {
                    $total = $existingCount + $newPicturesCount;
                    if ($total > 10) {
                        $validator->errors()->add(
                            'pictures',
                            'Total main product pictures (retained + newly uploaded) cannot exceed 10 images.'
                        );
                    }
                }

                $newVariantNames = (array) $this->input('new_variant_names', []);
                $newVariantFiles = (array) $this->file('new_variant_pictures', []);
                $newVariantBase64 = (array) $this->input('new_variant_pictures_base64', []);

                foreach ($newVariantNames as $nvId => $nvName) {
                    if (trim((string) $nvName) === '') {
                        continue;
                    }
                    $hasFile = isset($newVariantFiles[$nvId]) && $newVariantFiles[$nvId] !== null && $newVariantFiles[$nvId]->isValid();
                    $rawB64 = isset($newVariantBase64[$nvId]) ? trim((string) $newVariantBase64[$nvId]) : '';
                    $hasBase64 = str_starts_with($rawB64, 'data:image/');

                    if (! $hasFile && ! $hasBase64) {
                        $validator->errors()->add(
                            "new_variant_pictures.{$nvId}",
                            'Please upload a dedicated photo for new variant "' . trim((string) $nvName) . '".'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'pictures.max'                    => 'You can upload at most 10 main product pictures.',
            'pictures.*.uploaded'             => 'One of the main product pictures could not be uploaded. Please re-select the image (up to 5 MB).',
            'pictures.*.mimetypes'            => 'Each main product picture must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'pictures.*.max'                  => 'Each main product picture must not exceed 5 MB.',
            'new_variant_pictures.*.uploaded' => 'One of the new variant photos could not be uploaded. Please re-select the image (up to 5 MB).',
        ];
    }
}