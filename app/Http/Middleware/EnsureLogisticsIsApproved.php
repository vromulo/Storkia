<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'Logistics') {
            abort(403);
        }

        if ($user->isBranchLogisticsAccount()) {
            $branchHub = $user->branchLogisticsHub;
            if (! $branchHub || $branchHub->status !== 'active' || ! $branchHub->parentLogisticsProfile) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('logistics.login')
                    ->withErrors(['email' => 'This Branch Hub account is currently deactivated by the Main Logistics account.']);
            }

            return $next($request);
        }

        if (! $user->logisticsProfile) {
            return redirect()
                ->route('logistics.logistics-dashboard')
                ->with('error', 'Your logistics sorting center application must be approved before you can access this page.');
        }

        return $next($request);
    }
}