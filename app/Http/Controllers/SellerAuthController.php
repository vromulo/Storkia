<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;

class SellerAuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLogin()
    {
        return view('pages.seller.auth.login');
    }

    public function login(Request $request)
    {
        return $this->authService->authenticate($request, expectedRole: 'Seller');
    }

    public function showRegister()
    {
        return view('pages.seller.auth.register');
    }
}