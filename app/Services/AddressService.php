<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AddressService
{
    protected const CACHE_TTL_HOURS = 24;
    public const NCR_PROVINCE_CODE = '130000000';

    public function getProvinces(): array
    {
        return Cache::remember('psgc_provinces_v3', now()->addHours(self::CACHE_TTL_HOURS), function () {
            $provinces = [];

            try {
                $response = Http::withoutVerifying()->timeout(10)->get('https://psgc.gitlab.io/api/provinces');
                if ($response->successful() && is_array($response->json())) {
                    $provinces = $response->json();
                }
            } catch (\Throwable $e) {
                $provinces = [];
            }

            $hasNcr = collect($provinces)->contains(function ($prov) {
                $code = (string) ($prov['code'] ?? '');
                $name = strtolower(trim((string) ($prov['name'] ?? '')));
                return $code === self::NCR_PROVINCE_CODE
                    || str_contains($name, 'metro manila')
                    || str_contains($name, 'national capital region');
            });

            if (! $hasNcr) {
                array_unshift($provinces, [
                    'code'            => self::NCR_PROVINCE_CODE,
                    'name'            => 'Metro Manila',
                    'regionCode'      => self::NCR_PROVINCE_CODE,
                    'islandGroupCode' => 'luzon',
                ]);
            }

            usort($provinces, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

            return $provinces;
        });
    }

    public function getMunicipalities(string $provinceCode): array
    {
        $cleanCode = trim($provinceCode);
        if ($cleanCode === '') {
            return [];
        }

        return Cache::remember("psgc_municipalities_v3_{$cleanCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($cleanCode) {
            $normalizedInput = $this->normalizeName($cleanCode);

            if (
                $cleanCode === self::NCR_PROVINCE_CODE
                || in_array($normalizedInput, ['metro manila', 'ncr', 'national capital region', 'manila'], true)
            ) {
                return $this->getNcrCitiesAndMunicipalities();
            }

            $municipalities = [];

            try {
                $provResponse = Http::withoutVerifying()->timeout(10)->get("https://psgc.gitlab.io/api/provinces/{$cleanCode}/cities-municipalities");
                if ($provResponse->successful() && is_array($provResponse->json())) {
                    $municipalities = $provResponse->json();
                }

                $matchedProv = collect($this->getProvinces())->firstWhere('code', $cleanCode);
                $regionCode = $matchedProv['regionCode'] ?? null;

                // Merge Independent Cities (HUCs / ICCs) in the same region that have provinceCode === false
                if ($regionCode) {
                    $regResponse = Http::withoutVerifying()->timeout(10)->get("https://psgc.gitlab.io/api/regions/{$regionCode}/cities-municipalities");
                    if ($regResponse->successful() && is_array($regResponse->json())) {
                        $existingCodes = collect($municipalities)->pluck('code')->all();
                        foreach ($regResponse->json() as $cityItem) {
                            $itemProvCode = $cityItem['provinceCode'] ?? null;
                            if (
                                ($itemProvCode === false || $itemProvCode === null || $itemProvCode === $cleanCode)
                                && ! in_array($cityItem['code'] ?? '', $existingCodes, true)
                            ) {
                                $municipalities[] = $cityItem;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }

            usort($municipalities, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

            return $municipalities;
        });
    }

    protected function getNcrCitiesAndMunicipalities(): array
    {
        try {
            $response = Http::withoutVerifying()->timeout(10)->get('https://psgc.gitlab.io/api/regions/130000000/cities-municipalities');
            if ($response->successful() && is_array($response->json()) && ! empty($response->json())) {
                $items = $response->json();
                usort($items, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
                return $items;
            }
        } catch (\Throwable $e) {
        }

        return [
            ['code' => '137501000', 'name' => 'City of Caloocan'],
            ['code' => '137601000', 'name' => 'City of Las Piñas'],
            ['code' => '137602000', 'name' => 'City of Makati'],
            ['code' => '137502000', 'name' => 'City of Malabon'],
            ['code' => '137401000', 'name' => 'City of Mandaluyong'],
            ['code' => '133900000', 'name' => 'City of Manila'],
            ['code' => '137402000', 'name' => 'City of Marikina'],
            ['code' => '137603000', 'name' => 'City of Muntinlupa'],
            ['code' => '137503000', 'name' => 'City of Navotas'],
            ['code' => '137604000', 'name' => 'City of Parañaque'],
            ['code' => '137605000', 'name' => 'City of Pasay'],
            ['code' => '137403000', 'name' => 'City of Pasig'],
            ['code' => '137404000', 'name' => 'Quezon City'],
            ['code' => '137405000', 'name' => 'City of San Juan'],
            ['code' => '137607000', 'name' => 'City of Taguig'],
            ['code' => '137504000', 'name' => 'City of Valenzuela'],
            ['code' => '137606000', 'name' => 'Pateros'],
        ];
    }

    public function getBarangays(string $municipalityCode): array
    {
        $cleanCode = trim($municipalityCode);
        if ($cleanCode === '') {
            return [];
        }

        return Cache::remember("psgc_barangays_v3_{$cleanCode}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($cleanCode) {
            $endpoints = [
                "https://psgc.gitlab.io/api/cities-municipalities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/cities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/municipalities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/sub-municipalities/{$cleanCode}/barangays",
            ];

            foreach ($endpoints as $url) {
                try {
                    $response = Http::withoutVerifying()->timeout(10)->get($url);
                    if ($response->successful() && is_array($response->json()) && ! empty($response->json())) {
                        $barangays = $response->json();
                        usort($barangays, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
                        return $barangays;
                    }
                } catch (\Throwable $e) {
                }
            }

            return [];
        });
    }

    public function resolveProvinceByName(string $provinceName): ?array
    {
        $target = $this->normalizeName($provinceName);
        if ($target === '') {
            return null;
        }

        if (in_array($target, ['metro manila', 'ncr', 'national capital region', 'manila'], true)) {
            return [
                'code' => self::NCR_PROVINCE_CODE,
                'name' => 'Metro Manila',
            ];
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