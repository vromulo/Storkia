<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a guard out after a configurable stretch of inactivity, independent of
 * the (longer) global session lifetime. See config/security.php.
 *
 * Runs inside the "web" group, after StartSession, so it also covers Livewire
 * update requests. Passive Livewire polling (wire:poll sends only "$refresh")
 * does not reset the timer, otherwise an open admin dashboard tab would keep
 * the admin signed in forever.
 */
class EnforceIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $now = time();
        $expiredGuards = [];

        foreach ((array) config('security.idle_timeouts', []) as $guard => $minutes) {
            if ((int) $minutes <= 0) {
                continue;
            }

            $key = "security.last_activity.{$guard}";

            if (! Auth::guard($guard)->check()) {
                $session->forget($key);
                continue;
            }

            $lastActivity = $session->get($key);

            if ($lastActivity !== null && ($now - (int) $lastActivity) > ((int) $minutes * 60)) {
                Auth::guard($guard)->logout();
                $session->forget($key);
                $expiredGuards[] = $guard;
                continue;
            }

            if ($lastActivity === null || ! $this->isPassiveRequest($request)) {
                $session->put($key, $now);
            }
        }

        if ($expiredGuards !== []) {
            return $this->expiredResponse($request, $expiredGuards[0]);
        }

        return $next($request);
    }

    protected function expiredResponse(Request $request, string $guard): Response
    {
        $message = 'You were signed out after a period of inactivity. Please sign in again.';
        $route = (string) config("security.idle_login_routes.{$guard}", 'login');

        if ($request->expectsJson() || $this->isLivewireRequest($request)) {
            // 419 is what Livewire and the front-end session guard treat as
            // "session expired".
            return response()->json([
                'message'  => $message,
                'code'     => 'session_expired',
                'redirect' => route($route),
            ], 419);
        }

        return redirect()->route($route)->withErrors(['email' => $message]);
    }

    protected function isLivewireRequest(Request $request): bool
    {
        return $request->hasHeader('X-Livewire') || $request->is('livewire/*', 'livewire-*/*');
    }

    /**
     * A Livewire request is "passive" when it changes nothing and only
     * refreshes the component, which is what wire:poll sends. If the payload
     * has an unexpected shape the request counts as normal activity.
     */
    protected function isPassiveRequest(Request $request): bool
    {
        if (! $this->isLivewireRequest($request)) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! empty(data_get($component, 'updates'))) {
                return false;
            }

            foreach ((array) data_get($component, 'calls', []) as $call) {
                if (! in_array(data_get($call, 'method'), ['$refresh', '$commit'], true)) {
                    return false;
                }
            }
        }

        return true;
    }
}