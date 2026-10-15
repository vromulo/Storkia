<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request and enforce strict role boundaries.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Immediately abort with 403 Forbidden if unauthenticated or role does not match
        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized access: You do not have permission to access this portal.');
        }

        return $next($request);
    }
}