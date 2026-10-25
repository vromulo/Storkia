<?php

namespace App\Services;

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

        $rider = Rider::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if ($rider && Hash::check($credentials['password'], $rider->password)) {
            if ($rider->status === 'pending') {
                throw ValidationException::withMessages([
                    'email' => 'Your rider application is still pending approval by your selected logistics company.',
                ]);
            }

            if ($rider->status === 'rejected') {
                throw ValidationException::withMessages([
                    'email' => 'Your rider application was declined by the logistics company.',
                ]);
            }

            return redirect()->route('rider.download-app');
        }

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

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
                'email' => "You have a {$user->role} account. Please sign in through the {$target['portal']} instead."
            ]);

            session()->flash('errors', (new ViewErrorBag)->put('default', $errors));

            return redirect()->route($target['route']);
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