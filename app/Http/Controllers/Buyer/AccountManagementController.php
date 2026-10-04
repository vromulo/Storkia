<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountManagementController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return view('buyer.option.account.account-management', compact('user'));
    }
}