<?php

namespace App\Http\Middleware;

use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes sure the Sanctum token on an API request belongs to the kind of
 * account the route is meant for.
 *
 * Usage in routes:  ->middleware(['auth:sanctum', 'api.actor:buyer'])
 *
 * Two checks run together:
 *  1. The authenticated model must be the right type (a Rider token can never
 *     act as a Buyer, even if both rows happen to share the same numeric id).
 *  2. The token must carry the matching ability. Tokens issued before
 *     abilities were introduced carry "*", which passes this check, so check 1
 *     is what protects them.
 */
class EnsureApiActor
{
    public function handle(Request $request, Closure $next, string $actor): Response
    {
        if (! $this->matchesActor($request->user(), $actor)) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'code'    => 'wrong_token_type',
                'message' => 'This access token cannot be used for this resource.',
            ], 403);
        }

        return $next($request);
    }

    protected function matchesActor(mixed $user, string $actor): bool
    {
        if (! $user) {
            return false;
        }

        $typeMatches = match ($actor) {
            'buyer'   => $user instanceof User && $user->role === 'Buyer',
            'rider'   => $user instanceof Rider,
            'courier' => $user instanceof Courier,
            default   => false,
        };

        return $typeMatches && $user->tokenCan($actor);
    }
}