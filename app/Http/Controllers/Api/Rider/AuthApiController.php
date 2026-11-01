<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    use IssuesApiTokens;

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

        $allowedStatuses = (array) config('security.allowed_statuses.rider', ['approved']);

        if (! in_array($rider->status, $allowedStatuses, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is pending approval or has been restricted.',
            ], 403);
        }

        $token = $this->issueApiToken($rider, 'rider', 'rider_auth_token');

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