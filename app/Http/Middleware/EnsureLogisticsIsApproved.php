<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'Logistics') {
            abort(403);
        }

        if (! $user->logisticsProfile) {
            return redirect()
                ->route('logistics.logistics-dashboard')
                ->with('error', 'Your logistics sorting center application must be approved before you can access this page.');
        }

        return $next($request);
    }
}