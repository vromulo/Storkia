<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle Timeouts (minutes), per auth guard
    |--------------------------------------------------------------------------
    |
    | SESSION_LIFETIME is the hard ceiling for every portal. Any guard listed
    | here is signed out earlier once it has been inactive for this many
    | minutes. Passive Livewire polling (wire:poll) does not count as activity.
    | Set a value to 0 to disable the extra idle check for that guard.
    |
    */

    'idle_timeouts' => [
        'admin' => (int) env('SECURITY_ADMIN_IDLE_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idle Sign-out Destination
    |--------------------------------------------------------------------------
    |
    | Route name each guard is sent to after an idle sign-out.
    |
    */

    'idle_login_routes' => [
        'admin' => 'admin.login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Longer Sessions For Field Staff (minutes)
    |--------------------------------------------------------------------------
    |
    | Riders and couriers can sit on a delivery for hours before they submit
    | proof. Requests matching these URL patterns use the longer lifetime
    | instead of SESSION_LIFETIME.
    |
    */

    'session_lifetime_overrides' => [
        'rider/*'   => (int) env('SECURITY_FIELD_SESSION_MINUTES', 480),
        'courier/*' => (int) env('SECURITY_FIELD_SESSION_MINUTES', 480),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statuses That May Stay Signed In
    |--------------------------------------------------------------------------
    |
    | A rider or courier whose status is not in these lists is signed out on
    | their next request and cannot use the API.
    |
    */

    'allowed_statuses' => [
        'rider'   => ['approved'],
        'courier' => ['active', 'on_delivery', 'off_duty'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | api_per_minute          - general limit per user (or IP when guest)
    | api_auth_per_identity   - login / OTP / register attempts per email + IP
    | api_auth_per_ip         - login / OTP / register attempts per IP
    |
    */

    'rate_limits' => [
        'api_per_minute'        => (int) env('SECURITY_API_PER_MINUTE', 120),
        'api_auth_per_identity' => (int) env('SECURITY_API_AUTH_PER_IDENTITY', 5),
        'api_auth_per_ip'       => (int) env('SECURITY_API_AUTH_PER_IP', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Login OTP
    |--------------------------------------------------------------------------
    */

    'admin_otp' => [
        'max_attempts'            => 5,
        'resend_cooldown_seconds' => 60,
        'pre_auth_minutes'        => 10,
    ],

];