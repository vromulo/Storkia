<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a per-URL session lifetime (see "session_lifetime_overrides" in
 * config/security.php) so riders and couriers are not logged out mid-shift.
 *
 * This is registered as a GLOBAL middleware (prepended in bootstrap/app.php)
 * so it runs before the "web" group's StartSession. The session driver reads
 * session.lifetime lazily when it is first built, so changing the value here
 * affects both the server-side expiry check and the cookie expiry.
 */
class ApplySessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ((array) config('security.session_lifetime_overrides', []) as $pattern => $minutes) {
            if ((int) $minutes > 0 && $request->is($pattern)) {
                config(['session.lifetime' => (int) $minutes]);
                break;
            }
        }

        return $next($request);
    }
}