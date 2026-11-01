<?php

namespace App\Http\Middleware;

use App\Models\BuyerAccountSanction;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces account suspensions after login, not only at login. Archived
 * (deleted) accounts are treated the same way, which also covers session
 * drivers where AccountSecurityObserver cannot delete the stored sessions.
 *
 * Web (appended to the "web" group in bootstrap/app.php):
 *   checks the signed-in User (buyer / seller / logistics), Rider and Courier
 *   guards on every request and signs out whichever one is blocked.
 *
 * API (alias "account.active" on routes under auth:sanctum):
 *   returns 403 JSON for a blocked token owner. Routes a blocked buyer still
 *   needs (sanction status, appeal, logout) must stay outside this middleware.
 */
class EnsureAccountActive
{
    /** Routes that must stay reachable for a blocked web account. */
    protected const EXEMPT_ROUTES = ['logout', 'buyer.suspended', 'buyer.suspended.appeal'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return $request->is('api/*')
            ? $this->handleApi($request, $next)
            : $this->handleWeb($request, $next);
    }

    protected function handleApi(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor && ($reason = $this->blockedReason($actor)) !== null) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'code'    => match (true) {
                    $actor instanceof User && $actor->isArchived() => 'account_archived',
                    $actor instanceof User                         => 'account_suspended',
                    default                                        => 'account_blocked',
                },
                'message' => $reason,
            ], 403);
        }

        return $next($request);
    }

    protected function handleWeb(Request $request, Closure $next): Response
    {
        foreach (['web', 'rider', 'courier'] as $guard) {
            $guardInstance = Auth::guard($guard);

            if (! $guardInstance->check()) {
                continue;
            }

            $actor = $guardInstance->user();
            $reason = $this->blockedReason($actor);

            if ($reason === null) {
                continue;
            }

            $guardInstance->logout();

            return $this->rejectWeb($request, $actor, $reason);
        }

        return $next($request);
    }

    /**
     * Returns a human-readable reason when the account must not stay signed
     * in, or null when the account is fine.
     */
    protected function blockedReason(Authenticatable $actor): ?string
    {
        if ($actor instanceof User) {
            if ($actor->isArchived()) {
                return 'This account has been deleted. Contact Storkia support if you think this is a mistake.';
            }

            $sanction = BuyerAccountSanction::where('buyer_id', $actor->getKey())->first();

            return ($sanction && $sanction->isBlockedFromMarketplace())
                ? 'Your account is currently suspended by Storkia Admin.'
                : null;
        }

        if ($actor instanceof Rider) {
            $allowed = (array) config('security.allowed_statuses.rider', ['approved']);

            return in_array($actor->status, $allowed, true)
                ? null
                : 'Your rider account is not active. Please contact your logistics company.';
        }

        if ($actor instanceof Courier) {
            $allowed = (array) config('security.allowed_statuses.courier', ['active', 'on_delivery', 'off_duty']);

            return in_array($actor->status, $allowed, true)
                ? null
                : 'Your courier account is currently suspended by your logistics hub.';
        }

        return null;
    }

    protected function rejectWeb(Request $request, Authenticatable $actor, string $reason): Response
    {
        $isArchived = $actor instanceof User && $actor->isArchived();

        // A deleted buyer must not land on the suspended page: it offers a
        // ban appeal, which does not apply to a deleted account.
        $isBuyer = $actor instanceof User && $actor->role === 'Buyer' && ! $isArchived;

        if ($isBuyer) {
            // Same hand-off the buyer login already uses for the suspended page.
            $request->session()->put('suspended_buyer_id', $actor->getAuthIdentifier());
        }

        $target = $isBuyer
            ? route('buyer.suspended')
            : route($this->loginRouteFor($actor));

        if ($request->expectsJson() || $request->ajax() || $request->hasHeader('X-Livewire')) {
            return response()->json([
                'message'  => $reason,
                'code'     => $isArchived ? 'account_archived' : 'account_suspended',
                'redirect' => $target,
            ], 403);
        }

        $redirect = redirect($target);

        return $isBuyer ? $redirect : $redirect->withErrors(['email' => $reason]);
    }

    protected function loginRouteFor(Authenticatable $actor): string
    {
        if ($actor instanceof User) {
            return match ($actor->role) {
                'Seller'    => 'seller.login',
                'Logistics' => 'logistics.login',
                default     => 'login',
            };
        }

        // Riders and couriers sign in through the logistics portal.
        return 'logistics.login';
    }
}