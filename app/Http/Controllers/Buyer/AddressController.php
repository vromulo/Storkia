<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    public const MAX_ADDRESSES_PER_BUYER = 10;

    public function __construct(
        protected AddressService $addressService
    ) {}

    public function index()
    {
        $user = auth()->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $provinces = $this->addressService->getProvinces();

        return view('buyer.option.account.address-book', compact('user', 'addresses', 'provinces'));
    }

    public function getMunicipalities(string $provinceCode): JsonResponse
    {
        return response()->json($this->addressService->getMunicipalities($provinceCode));
    }

    public function getBarangays(string $municipalityCode): JsonResponse
    {
        return response()->json($this->addressService->getBarangays($municipalityCode));
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->addresses()->count() >= self::MAX_ADDRESSES_PER_BUYER) {
            throw ValidationException::withMessages([
                'address_limit' => 'You can save a maximum of ' . self::MAX_ADDRESSES_PER_BUYER . ' addresses in your Address Book. Please delete or edit an existing address first.',
            ]);
        }

        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($user, $validated) {
            $hasExisting = $user->addresses()->lockForUpdate()->exists();
            $makeDefault = ($validated['is_default'] ?? false) || ! $hasExisting;

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$validated,
                'country'    => 'Philippines',
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Address saved successfully.',
            'address'   => $address,
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest('id')->get(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);
        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($user, $address, $validated) {
            if (($validated['is_default'] ?? false) && ! $address->is_default) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $isOnlyAddress = $user->addresses()->count() === 1;
            $newDefault = ($isOnlyAddress || $address->is_default) ? true : (bool) ($validated['is_default'] ?? false);

            $address->update([
                ...$validated,
                'country'    => 'Philippines',
                'is_default' => $newDefault,
            ]);
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Address updated successfully.',
            'address'   => $address->fresh(),
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest('id')->get(),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        if ($address->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Your default address cannot be deleted. Please set another address as your default first before deleting this one.',
                'errors'  => [
                    'default_address' => ['Your default address cannot be deleted. Please choose another default address first.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($address) {
            $address->delete();
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Address deleted successfully.',
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest('id')->get(),
        ]);
    }

    protected function validateAddress(Request $request): array
    {
        $cleanPhone = ltrim(preg_replace('/\D/', '', (string) $request->input('phone_number')), '0');
        if (str_starts_with($cleanPhone, '63')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $request->merge(['phone_number' => $cleanPhone]);

        return $request->validate([
            'first_name'        => ['required', 'string', 'max:60', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'         => ['required', 'string', 'max:60', 'regex:/^[A-Za-z\s]+$/'],
            'phone_number'      => ['required', 'regex:/^9\d{9}$/'],
            'province_code'     => ['required', 'string'],
            'province'          => ['required', 'string'],
            'municipality_code' => ['required', 'string'],
            'municipality'      => ['required', 'string'],
            'barangay_code'     => ['required', 'string'],
            'barangay'          => ['required', 'string'],
            'postcode'          => ['required', 'string', 'max:10'],
            'street_address'    => ['required', 'string', 'max:255'],
            'building_details'  => ['nullable', 'string', 'max:255'],
            'is_default'        => ['boolean'],
        ], [
            'first_name.regex'       => 'First name must contain only letters and spaces.',
            'last_name.regex'        => 'Last name must contain only letters and spaces.',
            'phone_number.regex'     => 'Please enter a valid 11-digit mobile number starting with 09 or +639.',
            'barangay.required'      => 'Please select a Barangay.',
            'barangay_code.required' => 'Please select a Barangay.',
        ]);
    }
}