<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\PsgcService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    protected const MAX_ADDRESSES = 10;

    public function __construct(protected PsgcService $psgc)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->orderedAddresses($request->user());

        return response()->json([
            'success' => true,
            'data'    => $addresses,
            'meta'    => $this->meta($addresses->count()),
        ]);
    }

    public function getProvinces(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->psgc->getProvinces(),
        ]);
    }

    public function getMunicipalities(string $provinceCode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->psgc->getMunicipalities($provinceCode),
        ]);
    }

    public function getBarangays(string $municipalityCode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->psgc->getBarangays($municipalityCode),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (UserAddress::where('user_id', $user->id)->count() >= self::MAX_ADDRESSES) {
            return response()->json([
                'success' => false,
                'message' => 'You can only save up to ' . self::MAX_ADDRESSES . ' delivery addresses.',
                'code'    => 'address_limit_reached',
            ], 422);
        }

        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($user, $validated) {
            $hasExisting = UserAddress::where('user_id', $user->id)->exists();
            $makeDefault = ! $hasExisting || ! empty($validated['is_default']);

            if ($makeDefault) {
                UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }

            return UserAddress::create([
                ...$validated,
                'user_id'    => $user->id,
                'is_default' => $makeDefault,
            ]);
        });

        return $this->saved($user, 'Shipping address saved successfully.', $address, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $this->ownedAddress($user, $id);

        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($user, $address, $validated) {
            $wantsDefault = ! empty($validated['is_default']);

            if ($wantsDefault) {
                UserAddress::where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            } elseif ($address->is_default) {
                $nextDefault = UserAddress::where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->latest('id')
                    ->first();

                if ($nextDefault) {
                    $nextDefault->update(['is_default' => true]);
                } else {
                    $wantsDefault = true;
                }
            }

            $address->update([
                ...$validated,
                'is_default' => $wantsDefault,
            ]);
        });

        return $this->saved($user, 'Shipping address updated successfully.', $address->fresh());
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $this->ownedAddress($user, $id);

        if ($address->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Your default shipping address cannot be deleted. Please set another address as default first.',
                'code'    => 'default_address',
            ], 422);
        }

        $address->delete();

        return $this->saved($user, 'Address deleted successfully.', null);
    }

    public function setDefault(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $address = $this->ownedAddress($user, $id);

        DB::transaction(function () use ($user, $address) {
            UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return $this->saved($user, 'Default shipping address updated.', $address->fresh());
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

        $flag = $request->input('is_default');
        if (is_string($flag) && in_array(strtolower($flag), ['true', 'false'], true)) {
            $request->merge(['is_default' => strtolower($flag) === 'true']);
        }

        $validated = $request->validate([
            'first_name'        => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'last_name'         => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'phone_number'      => ['required', 'string', 'regex:/^9\d{9}$/'],
            'province_code'     => ['required', 'string', 'max:30'],
            'province'          => ['required', 'string', 'max:100'],
            'municipality_code' => ['required', 'string', 'max:30'],
            'municipality'      => ['required', 'string', 'max:100'],
            'barangay_code'     => ['required', 'string', 'max:30'],
            'barangay'          => ['required', 'string', 'max:120'],
            'postcode'          => ['required', 'string', 'regex:/^\d{4}$/'],
            'street_address'    => ['required', 'string', 'min:5', 'max:150'],
            'building_details'  => ['nullable', 'string', 'max:150'],
            'is_default'        => ['nullable', 'boolean'],
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

    protected function ownedAddress(User $user, $id): UserAddress
    {
        return UserAddress::where('user_id', $user->id)->findOrFail($id);
    }

    protected function orderedAddresses(User $user): Collection
    {
        return UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    protected function meta(int $count): array
    {
        return [
            'count'   => $count,
            'max'     => self::MAX_ADDRESSES,
            'can_add' => $count < self::MAX_ADDRESSES,
        ];
    }

    protected function saved(User $user, string $message, ?UserAddress $address, int $status = 200): JsonResponse
    {
        $addresses = $this->orderedAddresses($user);

        return response()->json([
            'success'   => true,
            'message'   => $message,
            'data'      => $address,
            'addresses' => $addresses,
            'meta'      => $this->meta($addresses->count()),
        ], $status);
    }
}