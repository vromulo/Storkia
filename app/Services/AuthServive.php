<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate credentials and enforce portal matching.
     *
     * @throws ValidationException
     */
    public function authenticate(Request $request, string $expectedRole)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Check if user is logging into the matching portal
        if ($user->role !== $expectedRole) {
            // Immediately log out to prevent unauthorized portal access
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Option B: Redirect to designated login page with warning notice
            $target = match ($user->role) {
                'Seller'    => ['route' => 'seller.login', 'portal' => 'Seller Portal'],
                'Logistics' => ['route' => 'logistics.login', 'portal' => 'Logistics Portal'],
                default     => ['route' => 'login', 'portal' => 'Buyer Storefront'],
            };

            return redirect()->route($target['route'])->withErrors([
                'email' => "You have a {$user->role} account. Please sign in through the {$target['portal']} instead."
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