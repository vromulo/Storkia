<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $courier = Courier::where('email', strtolower(trim($request->email)))->first();

        if (! $courier || ! Hash::check($request->password, $courier->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials provided.',
            ], 401);
        }

        if ($courier->status === 'suspended') {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        $token = $courier->createToken('courier_auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $courier,
        ], 200);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}