<?php

namespace App\Services;

class PsgcService extends AddressService
{
    public function isLocationServiceable(?string $province, ?string $municipality): bool
    {
        return app(ShippingCalculatorService::class)->isAddressServiceable($province, $municipality);
    }
}