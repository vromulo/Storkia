<?php

namespace App\Services;

use App\Models\BuyerAccountSanction;
use App\Models\Courier;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function authenticate(Request $request, string $expectedRole)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $normalizedEmail = strtolower(trim($credentials['email']));

        $isCourierEmail = Courier::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists();
        if ($isCourierEmail) {
            throw ValidationException::withMessages([
                'email' => 'First-Mile Courier accounts cannot sign in through the web portal. Please use the Storkia Mobile App.',
            ]);
        }

        $isRiderEmail = Rider::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists();
        if ($isRiderEmail) {
            throw ValidationException::withMessages([
                'email' => 'Last-Mile Rider accounts cannot sign in through the web portal. Please use the Storkia Mobile App.',
            ]);
        }

        if (! Auth::guard('web')->attempt([
            'email'    => $normalizedEmail,
            'password' => $credentials['password'],
        ], $request->filled('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user();

        if ($user->isArchived()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been deleted. Contact Storkia support if you think this is a mistake.',
            ]);
        }

        if ($user->role !== $expectedRole) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $target = match ($user->role) {
                'Seller'    => ['route' => 'seller.login', 'portal' => 'Seller Portal'],
                'Logistics' => ['route' => 'logistics.login', 'portal' => 'Logistics Portal'],
                default     => ['route' => 'login', 'portal' => 'Buyer Storefront'],
            };

            $errors = new MessageBag([
                'email' => "You have a {$user->role} account. Please sign in through the {$target['portal']} instead.",
            ]);

            session()->flash('errors', (new ViewErrorBag)->put('default', $errors));

            return redirect()->route($target['route']);
        }

        if ($user->role === 'Logistics') {
            if ($user->isBranchLogisticsAccount()) {
                $branchHub = $user->branchLogisticsHub;
                if (! $branchHub || $branchHub->status !== 'active' || ! $branchHub->parentLogisticsProfile) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw ValidationException::withMessages([
                        'email' => 'Your Branch Hub account is currently deactivated by your Main Logistics company.',
                    ]);
                }
            } elseif (! $user->isMainLogisticsAccount() && ! $user->logisticsApplications()->exists()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'This account is not an authorized Main Logistics or Branch Logistics hub account.',
                ]);
            }
        }

        $sanction = BuyerAccountSanction::where('buyer_id', $user->id)->first();

        if ($sanction && $sanction->isBlockedFromMarketplace()) {
            if ($user->role === 'Buyer') {
                $request->session()->regenerate();
                Auth::guard('web')->logout();
                $request->session()->put('suspended_buyer_id', $user->id);

                return redirect()->route('buyer.suspended');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account is currently suspended by Storkia Admin.',
            ]);
        }

        $request->session()->regenerate();

        $greeting = "Welcome back, {$user->first_name}!";

        return match ($user->role) {
            'Seller'    => redirect()->route('seller.seller-dashboard')->with('success', $greeting),
            'Logistics' => redirect()->route('logistics.logistics-dashboard')->with('success', $greeting),
            default     => redirect()->route('home')->with('success', $greeting),
        };
    }
}