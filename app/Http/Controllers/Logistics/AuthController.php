<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLogin()
    {
        return view('pages.logistics.auth.login');
    }

    public function login(Request $request)
    {
        return $this->authService->authenticate($request, expectedRole: 'Logistics');
    }

    public function showRegister()
    {
        return view('pages.logistics.auth.register');
    }
}