<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    protected const MAX_ADDRESSES = 10;

    public function __construct(protected PsgcService $psgc) {}

    public function index(Request $request)
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        $provinces = $this->psgc->getProvinces();

        return view('buyer.option.account.address-book', compact('addresses', 'provinces'));
    }

    public function getMunicipalities(string $provinceCode): JsonResponse
    {
        return response()->json($this->psgc->getMunicipalities($provinceCode));
    }

    public function municipalities(string $provinceCode): JsonResponse
    {
        return $this->getMunicipalities($provinceCode);
    }

    public function getBarangays(string $municipalityCode): JsonResponse
    {
        return response()->json($this->psgc->getBarangays($municipalityCode));
    }

    public function barangays(string $municipalityCode): JsonResponse
    {
        return $this->getBarangays($municipalityCode);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
            return response()->json([
                'success' => false,
                'message' => 'You can only save up to ' . self::MAX_ADDRESSES . ' delivery addresses.',
            ], 422);
        }

        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($user, $validated) {
            $hasExisting = $user->addresses()->exists();
            $makeDefault = ! $hasExisting || ! empty($validated['is_default']);

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$validated,
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Shipping address saved successfully.',
            'address'   => $address,
            'addresses' => $this->getOrderedAddresses($user),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $id instanceof UserAddress
            ? $id
            : UserAddress::where('user_id', $user->id)->findOrFail($id);

        if ((int) $address->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($user, $address, $validated) {
            $wantsDefault = ! empty($validated['is_default']);

            if ($wantsDefault) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            } elseif ($address->is_default) {
                $otherExists = $user->addresses()->where('id', '!=', $address->id)->exists();
                if (! $otherExists) {
                    $wantsDefault = true;
                } else {
                    $nextDefault = $user->addresses()->where('id', '!=', $address->id)->latest('id')->first();
                    $nextDefault?->update(['is_default' => true]);
                }
            }

            $address->update([
                ...$validated,
                'is_default' => $wantsDefault,
            ]);
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Shipping address updated successfully.',
            'address'   => $address->fresh(),
            'addresses' => $this->getOrderedAddresses($user),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $id instanceof UserAddress
            ? $id
            : UserAddress::where('user_id', $user->id)->findOrFail($id);

        if ((int) $address->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($address->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Your default shipping address cannot be deleted. Please set another address as default first.',
            ], 422);
        }

        $address->delete();

        return response()->json([
            'success'   => true,
            'message'   => 'Address deleted successfully.',
            'addresses' => $this->getOrderedAddresses($user),
        ]);
    }

    public function setDefault(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $id instanceof UserAddress
            ? $id
            : UserAddress::where('user_id', $user->id)->findOrFail($id);

        if ((int) $address->user_id !== (int) $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($user, $address) {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Default shipping address updated.',
            'addresses' => $this->getOrderedAddresses($user),
        ]);
    }

    protected function validateAddress(Request $request): array
    {
        $rawPhone = preg_replace('/\D/', '', (string) $request->input('phone_number', ''));
        if (str_starts_with($rawPhone, '63') && strlen($rawPhone) >= 12) {
            $rawPhone = substr($rawPhone, 2);
        }
        $cleanPhone = ltrim($rawPhone, '0');

        $municipalityCode = trim((string) $request->input('municipality_code', ''));
        $barangayCode = trim((string) $request->input('barangay_code', ''));
        $barangayName = trim((string) $request->input('barangay', ''));

        if (($barangayCode === '' || $barangayName === '') && $municipalityCode !== '') {
            $barangays = $this->psgc->getBarangays($municipalityCode);
            if (! empty($barangays)) {
                $firstBrgy = $barangays[0];
                $barangayCode = $barangayCode ?: (string) ($firstBrgy['code'] ?? 'BRGY-01');
                $barangayName = $barangayName ?: (string) ($firstBrgy['name'] ?? 'Poblacion');
            }
        }

        $request->merge([
            'first_name'       => trim((string) $request->input('first_name', '')),
            'last_name'        => trim((string) $request->input('last_name', '')),
            'phone_number'     => $cleanPhone,
            'barangay_code'    => $barangayCode,
            'barangay'         => $barangayName,
            'postcode'         => trim((string) $request->input('postcode', '')),
            'street_address'   => trim((string) $request->input('street_address', '')),
            'building_details' => $request->filled('building_details') ? trim((string) $request->input('building_details')) : null,
        ]);

        $validated = $request->validate([
            'first_name'       => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'last_name'        => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'phone_number'     => ['required', 'string', 'regex:/^9\d{9}$/'],
            'province_code'    => ['required', 'string', 'max:30'],
            'province'         => ['required', 'string', 'max:100'],
            'municipality_code'=> ['required', 'string', 'max:30'],
            'municipality'     => ['required', 'string', 'max:100'],
            'barangay_code'    => ['required', 'string', 'max:30'],
            'barangay'         => ['required', 'string', 'max:120'],
            'postcode'         => ['required', 'string', 'regex:/^\d{4}$/'],
            'street_address'   => ['required', 'string', 'min:5', 'max:150'],
            'building_details' => ['nullable', 'string', 'max:150'],
            'is_default'       => ['nullable', 'boolean'],
        ], [
            'first_name.regex'   => 'First name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'last_name.regex'    => 'Last name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'phone_number.regex' => 'Please enter a valid 10-digit Philippine mobile number starting with 9 (e.g. 917 123 4567).',
            'barangay.required'  => 'Please select a barangay.',
            'postcode.regex'     => 'Postal code must be a valid 4-digit Philippine zip code.',
            'street_address.min' => 'Please enter a complete street address (at least 5 characters).',
        ]);

        if (! $this->psgc->isLocationServiceable($validated['province'], $validated['municipality'])) {
            throw ValidationException::withMessages([
                'province' => 'Sorry, this island or municipality is currently outside our serviceable delivery zones.',
            ]);
        }

        return $validated;
    }

    protected function getOrderedAddresses($user)
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }
}