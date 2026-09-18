<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogisticsAuthController extends Controller
{
    public function showRegister()
    {
        return view('pages.logistics.auth.register');
    }
}