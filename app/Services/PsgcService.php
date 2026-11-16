<?php

namespace App\Services;

class PsgcService extends AddressService
{
    public function isLocationServiceable(?string $province, ?string $municipality, ?string $barangay = null): bool
    {
        return app(ShippingCalculatorService::class)->isAddressServiceable($province, $municipality, $barangay);
    }
}