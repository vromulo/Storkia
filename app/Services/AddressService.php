<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressService
{
    protected const CACHE_TTL_HOURS = 24;
    protected const STALE_TTL_DAYS = 30;
    public const NCR_PROVINCE_CODE = '130000000';

    protected function fetchJson(string $url): ?array
    {
        try {
            $response = Http::withoutVerifying()->timeout(10)->retry(2, 200)->get($url);
            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
            Log::warning('PSGC request returned an unusable response', [
                'url'    => $url,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PSGC request failed', [
                'url'     => $url,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function rememberList(string $key, callable $loader): array
    {
        $cached = Cache::get($key);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $fresh = $loader();
        if (is_array($fresh) && ! empty($fresh)) {
            Cache::put($key, $fresh, now()->addHours(self::CACHE_TTL_HOURS));
            Cache::put($key . '_stale', $fresh, now()->addDays(self::STALE_TTL_DAYS));
            return $fresh;
        }

        $stale = Cache::get($key . '_stale');

        return is_array($stale) ? $stale : [];
    }

    protected function sortByName(array $items): array
    {
        usort($items, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $items;
    }

    public function getProvinces(): array
    {
        $provinces = $this->rememberList('psgc_provinces_v4', function () {
            $provinces = $this->fetchJson('https://psgc.gitlab.io/api/provinces');
            if (empty($provinces)) {
                return [];
            }

            $hasNcr = collect($provinces)->contains(function ($prov) {
                $code = (string) ($prov['code'] ?? '');
                $name = strtolower(trim((string) ($prov['name'] ?? '')));
                return $code === self::NCR_PROVINCE_CODE
                    || str_contains($name, 'metro manila')
                    || str_contains($name, 'national capital region');
            });

            if (! $hasNcr) {
                $provinces[] = [
                    'code'            => self::NCR_PROVINCE_CODE,
                    'name'            => 'Metro Manila',
                    'regionCode'      => self::NCR_PROVINCE_CODE,
                    'islandGroupCode' => 'luzon',
                ];
            }

            return $this->sortByName($provinces);
        });

        if (empty($provinces)) {
            return [[
                'code'            => self::NCR_PROVINCE_CODE,
                'name'            => 'Metro Manila',
                'regionCode'      => self::NCR_PROVINCE_CODE,
                'islandGroupCode' => 'luzon',
            ]];
        }

        return $provinces;
    }

    public function getMunicipalities(string $provinceCode): array
    {
        $cleanCode = trim($provinceCode);
        if ($cleanCode === '') {
            return [];
        }

        $normalizedInput = $this->normalizeName($cleanCode);
        $isNcr = $cleanCode === self::NCR_PROVINCE_CODE
            || in_array($normalizedInput, ['metro manila', 'ncr', 'national capital region', 'manila'], true);

        if ($isNcr) {
            $items = $this->rememberList('psgc_municipalities_v4_ncr', function () {
                $items = $this->fetchJson('https://psgc.gitlab.io/api/regions/130000000/cities-municipalities');

                return empty($items) ? [] : $this->sortByName($items);
            });

            return empty($items) ? $this->ncrFallbackCities() : $items;
        }

        return $this->rememberList("psgc_municipalities_v4_{$cleanCode}", function () use ($cleanCode) {
            $municipalities = $this->fetchJson("https://psgc.gitlab.io/api/provinces/{$cleanCode}/cities-municipalities");
            if (empty($municipalities)) {
                return [];
            }

            $matchedProv = collect($this->getProvinces())->firstWhere('code', $cleanCode);
            $regionCode = $matchedProv['regionCode'] ?? null;

            if ($regionCode) {
                $regionItems = $this->fetchJson("https://psgc.gitlab.io/api/regions/{$regionCode}/cities-municipalities");
                if (is_array($regionItems)) {
                    $existingCodes = collect($municipalities)->pluck('code')->all();
                    foreach ($regionItems as $cityItem) {
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

            return $this->sortByName($municipalities);
        });
    }

    protected function ncrFallbackCities(): array
    {
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

        return $this->rememberList("psgc_barangays_v4_{$cleanCode}", function () use ($cleanCode) {
            $endpoints = [
                "https://psgc.gitlab.io/api/cities-municipalities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/cities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/municipalities/{$cleanCode}/barangays",
                "https://psgc.gitlab.io/api/sub-municipalities/{$cleanCode}/barangays",
            ];

            foreach ($endpoints as $url) {
                $barangays = $this->fetchJson($url);
                if (! empty($barangays)) {
                    return $this->sortByName($barangays);
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