@props([
    'address' => null,
    'isDefault' => false,
    'firstName' => '',
    'lastName' => '',
    'phoneNumber' => '',
    'houseDetails' => '',
    'street' => '',
    'barangay' => '',
    'city' => '',
    'province' => '',
    'postcode' => '',
    'country' => 'Philippines',
])

@php
    // Allow passing either an Eloquent model/array via $address or individual attributes
    $fname = $address->first_name ?? $address['first_name'] ?? $firstName;
    $lname = $address->last_name ?? $address['last_name'] ?? $lastName;
    $phone = $address->phone_number ?? $address['phone_number'] ?? $address->contact_no ?? $address['contact_no'] ?? $phoneNumber;
    $house = $address->house_details ?? $address['house_details'] ?? $houseDetails;
    $strt  = $address->street ?? $address['street'] ?? $street;
    $brgy  = $address->barangay ?? $address['barangay'] ?? $barangay;
    $cty   = $address->city ?? $address['city'] ?? $address->municipality ?? $address['municipality'] ?? $city;
    $prov  = $address->province ?? $address['province'] ?? $province;
    $code  = $address->postcode ?? $address['postcode'] ?? $postcode;
    $cntry = $address->country ?? $address['country'] ?? $country;
    $def   = (bool) ($address->is_default ?? $address['is_default'] ?? $isDefault);
    $addrId = $address->id ?? $address['id'] ?? null;

    // Line 1: Street Address, Building, House, Barangay
    $line1Parts = array_filter([$house, $strt, $brgy ? 'Barangay ' . $brgy : null]);
    $line1 = implode(', ', $line1Parts);

    // Line 2: Region, City, Province, Country, Postcode
    $line2Parts = array_filter([
        $brgy ? strtoupper($brgy) : null,
        ($prov && $cty) ? strtoupper($prov) . '-' . strtoupper(str_replace(' ', '-', $cty)) : null,
        $prov ? strtoupper($prov) : null,
        $cntry,
        $code
    ]);
    $line2 = implode(' ', $line2Parts);
@endphp

<div {{ $attributes->merge(['class' => 'relative bg-surface border border-border-subtle rounded-none p-5 sm:p-6 overflow-hidden select-none flex flex-col justify-between h-full']) }}>
    <!-- Airmail Left Flap Pattern -->
    <div class="absolute left-0 top-0 bottom-0 w-2.5 sm:w-3 pointer-events-none"
         style="background: repeating-linear-gradient(
            180deg,
            #4A779D 0px,
            #4A779D 14px,
            transparent 14px,
            transparent 22px,
            #C45050 22px,
            #C45050 36px,
            transparent 36px,
            transparent 44px
         );">
    </div>

    <!-- Inner Card Content -->
    <div class="pl-4 sm:pl-5 space-y-3.5 flex-1 flex flex-col justify-between">
        <div class="space-y-3.5">
            <!-- Recipient & Phone Number -->
            <div class="flex items-baseline gap-3 text-sm sm:text-base font-normal">
                <span class="font-bold text-text-main text-base sm:text-lg">
                    {{ trim("{$fname} {$lname}") }}
                </span>
                <span class="text-text-muted text-sm sm:text-base">
                    {{ $phone }}
                </span>
            </div>

            <!-- Address Lines -->
            <div class="space-y-1 text-xs sm:text-sm text-text-main leading-relaxed">
                @if($line1)
                    <p class="font-normal">{{ $line1 }}</p>
                @endif
                @if($line2)
                    <p class="font-normal uppercase">{{ $line2 }}</p>
                @endif
            </div>
        </div>

        <!-- Card Footer: Default Badge + Action Buttons -->
        <div class="pt-4 flex items-center justify-between border-t border-border-subtle/40 mt-3">
            <div>
                @if($def)
                    <span class="inline-block px-2.5 py-1 text-xs font-medium text-emerald-700 border border-emerald-600 rounded-none bg-emerald-50/40">
                        Default Address
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3 text-xs sm:text-sm font-medium">
                <button type="button" 
                        @click="$dispatch('delete-address', { id: '{{ $addrId }}' })"
                        class="text-blue-600 hover:text-blue-800 hover:underline transition-colors cursor-pointer">
                    Delete
                </button>
                <button type="button" 
                        @click="$dispatch('edit-address', { id: '{{ $addrId }}' })"
                        class="text-blue-600 hover:text-blue-800 hover:underline transition-colors cursor-pointer">
                    Edit
                </button>
            </div>
        </div>
    </div>
</div>