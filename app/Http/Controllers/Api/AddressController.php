<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function __construct(protected AddressService $addressService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        
        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    public function getProvinces(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->addressService->getProvinces()
        ]);
    }

    public function getMunicipalities(string $provinceCode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->addressService->getMunicipalities($provinceCode)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);
        $user = $request->user();

        $address = DB::transaction(function () use ($user, $validated) {
            $hasExisting = $user->addresses()->exists();
            $makeDefault = $validated['is_default'] || !$hasExisting;

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$validated,
                'country' => 'Philippines',
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'data' => collect([$address]) // Wrap in collection to match index format if needed
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);
        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($user, $address, $validated) {
            if ($validated['is_default'] && !$address->is_default) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $isOnlyAddress = $user->addresses()->count() === 1;
            $newDefault = $isOnlyAddress ? true : $validated['is_default'];

            $address->update([
                ...$validated,
                'country' => 'Philippines',
                'is_default' => $newDefault,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => $address->fresh()
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = $user->addresses()->latest('id')->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.'
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
            'barangay_code'     => ['nullable', 'string'],
            'barangay'          => ['nullable', 'string'],
            'postcode'          => ['required', 'string', 'max:10'],
            'street_address'    => ['required', 'string', 'max:255'],
            'building_details'  => ['nullable', 'string', 'max:255'],
            'is_default'        => ['boolean'],
        ]);
    }
}