<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLogin()
    {
        return view('buyer.auth.login');
    }

    public function login(Request $request)
    {
        return $this->authService->authenticate($request, expectedRole: 'Buyer');
    }

    public function showRegister()
    {
        return view('buyer.auth.register');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('info', 'Successfully signed out.');
    }
}