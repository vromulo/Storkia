<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AddressService
{
    protected const CACHE_TTL_HOURS = 24;

    /**
     * Retrieve all Philippine provinces (cached for 24 hours).
     */
    public function getProvinces(): array
    {
        return Cache::remember('psgc_provinces', now()->addHours(self::CACHE_TTL_HOURS), function () {
            try {
                $response = Http::get('https://psgc.gitlab.io/api/provinces');
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Retrieve cities/municipalities for a specific province.
     */
    public function getMunicipalities(string $provinceCode): array
    {
        if (empty($provinceCode)) {
            return [];
        }

        return Cache::remember("psgc_municipalities_{$provinceCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($provinceCode) {
            try {
                $response = Http::get("https://psgc.gitlab.io/api/provinces/{$provinceCode}/cities-municipalities");
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Retrieve barangays for a specific city/municipality.
     */
    public function getBarangays(string $municipalityCode): array
    {
        if (empty($municipalityCode)) {
            return [];
        }

        return Cache::remember("psgc_barangays_{$municipalityCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($municipalityCode) {
            try {
                $response = Http::get("https://psgc.gitlab.io/api/cities-municipalities/{$municipalityCode}/barangays");
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}