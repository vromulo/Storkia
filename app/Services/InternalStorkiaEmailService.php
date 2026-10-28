<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use Closure;

class InternalStorkiaEmailService
{
    public const DOMAIN = 'storkia.shop';

    public static function normalizePrefix(?string $rawPrefix): string
    {
        $cleaned = strtolower(trim((string) $rawPrefix));
        if (str_contains($cleaned, '@')) {
            $cleaned = explode('@', $cleaned, 2)[0];
        }

        return trim($cleaned);
    }

    public static function sanitizeLivePrefix(?string $rawPrefix): string
    {
        $cleaned = strtolower((string) $rawPrefix);
        if (str_contains($cleaned, '@')) {
            $cleaned = explode('@', $cleaned, 2)[0];
        }

        $cleaned = preg_replace('/[^a-z0-9._-]/', '', $cleaned);

        return substr((string) $cleaned, 0, 40);
    }

    public static function buildEmail(?string $rawPrefix): string
    {
        $prefix = self::normalizePrefix($rawPrefix);

        return $prefix !== '' ? $prefix . '@' . self::DOMAIN : '';
    }

    public static function emailExists(
        string $fullEmail,
        ?int $ignoreUserId = null,
        ?int $ignoreRiderId = null,
        ?int $ignoreCourierId = null
    ): bool {
        $normalized = strtolower(trim($fullEmail));
        if ($normalized === '') {
            return false;
        }

        $inUsers = User::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($inUsers) {
            return true;
        }

        $inRiders = Rider::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->when($ignoreRiderId, fn ($q) => $q->where('id', '!=', $ignoreRiderId))
            ->exists();

        if ($inRiders) {
            return true;
        }

        $inCouriers = Courier::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->when($ignoreCourierId, fn ($q) => $q->where('id', '!=', $ignoreCourierId))
            ->exists();

        if ($inCouriers) {
            return true;
        }

        return Admin::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])->exists();
    }

    public static function prefixValidationRules(
        ?int $ignoreUserId = null,
        ?int $ignoreRiderId = null,
        ?int $ignoreCourierId = null
    ): array {
        return [
            'required',
            'string',
            'min:3',
            'max:40',
            'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreUserId, $ignoreRiderId, $ignoreCourierId) {
                $raw = (string) $value;

                if (preg_match('/\s/', $raw)) {
                    $fail('Email username cannot contain spaces.');
                    return;
                }

                if (str_contains($raw, '@')) {
                    $fail('Enter only the username prefix without @' . self::DOMAIN . '.');
                    return;
                }

                $fullEmail = self::buildEmail($raw);

                if (self::emailExists($fullEmail, $ignoreUserId, $ignoreRiderId, $ignoreCourierId)) {
                    $fail("The account email {$fullEmail} is already in use across Storkia.");
                }
            },
        ];
    }

    public static function prefixValidationMessages(string $field = 'email_prefix'): array
    {
        return [
            "{$field}.required" => 'Email username prefix is required.',
            "{$field}.min"      => 'Email username must be at least 3 characters.',
            "{$field}.max"      => 'Email username must not exceed 40 characters.',
            "{$field}.regex"    => 'Use only lowercase letters, numbers, dots (.), underscores (_), or hyphens (-), starting and ending with a letter or number.',
        ];
    }
}