<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('pages.buyer.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Check the user's role and redirect to the correct dashboard
            $role = Auth::user()->role;
            $success = "Successfully signed in";
            
            if ($role === 'Seller') {
                return redirect()
                    ->route('seller.seller-dashboard')
                    ->with('success', $success);
            } elseif ($role === 'Logistics') {
                return redirect()
                    ->route('logistics.logistics-dashboard')
                    ->with('success', $success);
            }
            
            // DEFAULT: Buyer dashboard (home)
            return redirect()
                ->route('home')
                ->with('success', $success);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function showRegister()
    {
        return view('pages.buyer.auth.register');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('info', 'Successfully signed out');
    }
}