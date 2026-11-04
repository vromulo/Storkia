<?php

namespace App\Http\Middleware;

use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $normalizedActor = strtolower(trim($actor));

        $typeMatches = match ($normalizedActor) {
            'buyer'   => $user instanceof User && $user->role === 'Buyer',
            'rider'   => $user instanceof Rider,
            'courier' => $user instanceof Courier,
            default   => false,
        };

        if (! $typeMatches) {
            return false;
        }

        return $user->tokenCan($normalizedActor)
            || $user->tokenCan("role:{$normalizedActor}");
    }
}