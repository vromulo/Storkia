<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
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

        $rider = Rider::where('email', strtolower(trim($request->email)))->first();

        if (! $rider || ! Hash::check($request->password, $rider->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials provided.',
            ], 401);
        }

        if ($rider->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is pending approval or has been restricted.',
            ], 403);
        }

        $token = $rider->createToken('rider_auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $rider,
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