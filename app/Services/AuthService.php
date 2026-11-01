<?php

namespace App\Services;

use App\Models\BuyerAccountSanction;
use App\Models\Courier;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        $courier = Courier::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if ($courier && Hash::check($credentials['password'], $courier->password)) {
            $allowedCourierStatuses = (array) config('security.allowed_statuses.courier', ['active', 'on_delivery', 'off_duty']);

            if (! in_array($courier->status, $allowedCourierStatuses, true)) {
                throw ValidationException::withMessages([
                    'email' => 'Your courier account is currently suspended by your logistics hub.',
                ]);
            }

            Auth::guard('courier')->login($courier, $request->filled('remember'));
            $request->session()->regenerate();

            return redirect()->route('courier.dashboard')->with('success', "Welcome back, Courier {$courier->first_name}!");
        }

        $rider = Rider::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if ($rider && Hash::check($credentials['password'], $rider->password)) {
            $allowedRiderStatuses = (array) config('security.allowed_statuses.rider', ['approved']);

            if ($rider->status === 'pending') {
                throw ValidationException::withMessages([
                    'email' => 'Your rider account is currently pending activation by your logistics company.',
                ]);
            }

            if (! in_array($rider->status, $allowedRiderStatuses, true)) {
                throw ValidationException::withMessages([
                    'email' => 'Your rider account is currently deactivated or suspended by your logistics company.',
                ]);
            }

            Auth::guard('rider')->login($rider, $request->filled('remember'));
            $request->session()->regenerate();

            return redirect()->route('rider.dashboard')->with('success', "Welcome back, Rider {$rider->first_name}!");
        }

        if (! Auth::attempt([
            'email'    => $normalizedEmail,
            'password' => $credentials['password'],
        ], $request->filled('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // A deleted (archived) account can never sign in, whichever portal
        // form was used. Checked before the role check so the message is the
        // same everywhere and nobody is sent to another portal's login.
        if ($user->isArchived()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been deleted. Contact Storkia support if you think this is a mistake.',
            ]);
        }

        if ($user->role !== $expectedRole) {
            Auth::logout();
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

        if ($user->role === 'Logistics' && $user->isBranchLogisticsAccount()) {
            $branchHub = $user->branchLogisticsHub;
            if (! $branchHub || $branchHub->status !== 'active' || ! $branchHub->parentLogisticsProfile) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'Your Branch Hub account is currently deactivated by your Main Logistics company.',
                ]);
            }
        }

        // Bans and suspensions are enforced for every role, whichever login
        // form was used (the Livewire login form calls this service directly).
        $sanction = BuyerAccountSanction::where('buyer_id', $user->id)->first();

        if ($sanction && $sanction->isBlockedFromMarketplace()) {
            if ($user->role === 'Buyer') {
                // Same hand-off the buyer login controller uses for the
                // suspended page, so the appeal flow keeps working.
                $request->session()->regenerate();
                Auth::logout();
                $request->session()->put('suspended_buyer_id', $user->id);

                return redirect()->route('buyer.suspended');
            }

            Auth::logout();
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