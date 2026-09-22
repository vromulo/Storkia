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
        return view('logistics.auth.login');
    }

    public function login(Request $request)
    {
        return $this->authService->authenticate($request, expectedRole: 'Logistics');
    }

    public function showRegister()
    {
        return view('logistics.auth.register');
    }
}