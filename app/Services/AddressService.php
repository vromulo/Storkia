<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AddressService
{
    protected const CACHE_TTL_HOURS = 24;

    public function getProvinces(): array
    {
        return Cache::remember('psgc_provinces', now()->addHours(self::CACHE_TTL_HOURS), function () {
            try {
                $response = Http::timeout(8)->get('https://psgc.gitlab.io/api/provinces');
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function getMunicipalities(string $provinceCode): array
    {
        if (empty($provinceCode)) {
            return [];
        }

        return Cache::remember("psgc_municipalities_{$provinceCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($provinceCode) {
            try {
                $response = Http::timeout(8)->get("https://psgc.gitlab.io/api/provinces/{$provinceCode}/cities-municipalities");
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function getBarangays(string $municipalityCode): array
    {
        if (empty($municipalityCode)) {
            return [];
        }

        return Cache::remember("psgc_barangays_{$municipalityCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($municipalityCode) {
            try {
                $response = Http::timeout(8)->get("https://psgc.gitlab.io/api/cities-municipalities/{$municipalityCode}/barangays");
                return $response->successful() ? $response->json() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function resolveProvinceByName(string $provinceName): ?array
    {
        $target = $this->normalizeName($provinceName);
        if ($target === '') {
            return null;
        }

        foreach ($this->getProvinces() as $prov) {
            if ($this->normalizeName($prov['name'] ?? '') === $target) {
                return $prov;
            }
        }

        return null;
    }

    public function getMunicipalitiesByProvinceName(string $provinceName): array
    {
        $prov = $this->resolveProvinceByName($provinceName);
        if (! $prov || empty($prov['code'])) {
            return [];
        }

        return $this->getMunicipalities((string) $prov['code']);
    }

    public function resolveMunicipalityByName(string $provinceName, string $municipalityName): ?array
    {
        $targetMun = $this->normalizeName($municipalityName);
        if ($targetMun === '') {
            return null;
        }

        $municipalities = $this->getMunicipalitiesByProvinceName($provinceName);
        foreach ($municipalities as $mun) {
            if ($this->normalizeName($mun['name'] ?? '') === $targetMun) {
                return $mun;
            }
        }

        return null;
    }

    public function getBarangaysForLocation(string $provinceName, string $municipalityName, ?string $municipalityCode = null): array
    {
        if (! empty($municipalityCode)) {
            $fromCode = $this->getBarangays($municipalityCode);
            if (! empty($fromCode)) {
                return $fromCode;
            }
        }

        $resolvedMun = $this->resolveMunicipalityByName($provinceName, $municipalityName);
        if ($resolvedMun && ! empty($resolvedMun['code'])) {
            $fromResolved = $this->getBarangays((string) $resolvedMun['code']);
            if (! empty($fromResolved)) {
                return $fromResolved;
            }
        }

        return [
            ['code' => 'BRGY-POB-1', 'name' => 'Poblacion'],
            ['code' => 'BRGY-POB-2', 'name' => 'Poblacion I'],
            ['code' => 'BRGY-POB-3', 'name' => 'Poblacion II'],
            ['code' => 'BRGY-SAN-ROQUE', 'name' => 'San Roque'],
            ['code' => 'BRGY-SAN-ISIDRO', 'name' => 'San Isidro'],
            ['code' => 'BRGY-SAN-JOSE', 'name' => 'San Jose'],
            ['code' => 'BRGY-STA-CRUZ', 'name' => 'Santa Cruz'],
        ];
    }

    public function normalizeName(?string $value): string
    {
        if (! $value) {
            return '';
        }

        $cleaned = strtolower(trim($value));
        $cleaned = preg_replace('/\b(city of|municipality of|province of|city)\b/', '', $cleaned);
        $cleaned = preg_replace('/[^a-z0-9\s]/', ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim((string) $cleaned);
    }
}