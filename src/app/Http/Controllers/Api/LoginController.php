<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1) Validate
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('username', 'password');

        // 2) Attempt login
        if (! $token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $user = auth()->user();

        // 3) Build all the data you want inside the token
        $ttl       = auth()->factory()->getTTL() * 60;
        $now       = now()->timestamp;
        $expiresAt = $now + $ttl;

        $customClaims = [
            // Standard claims
            'sub'       => $user->id,
            'iat'       => $now,
            'exp'       => $expiresAt,

            // Your usual login response fields
            'success'      => true,
            'token_type'   => 'bearer',
            'expires_in'   => $ttl,

            // Embed the entire user object (or pick just the fields you need)
            'user' => [
                'id'               => $user->id,
                'username'         => $user->username,
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $user->role,
                'email_verified_at'=> $user->email_verified_at,
                'created_at'       => $user->created_at->toISOString(),
                'updated_at'       => $user->updated_at->toISOString(),
            ],
        ];

        // 4) Regenerate token with those claims
        $token = JWTAuth::claims($customClaims)->fromUser($user);

        // 5) Return only the token string
        return response()->json([
            'token' => $token,
        ]);
    }
}
