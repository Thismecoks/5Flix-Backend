<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $user = User::where('username', $request->username)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                // Delete existing tokens
                $user->tokens()->delete();

                // Delete existing refresh tokens
                RefreshToken::where('user_id', $user->id)->delete();

                // Create access token (15 minutes)
                $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15));

                // Create refresh token (30 days)
                $refreshToken = RefreshToken::create([
                    'user_id' => $user->id,
                    'token' => hash('sha256', $plainTextRefreshToken = Str::random(80)),
                    'device_name' => $request->header('User-Agent') ?? 'Unknown Device',
                    'ip_address' => $request->ip(),
                    'expires_at' => now()->addDays(30)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'role' => $user->role
                    ],
                    'access_token' => $accessToken->plainTextToken,
                    'refresh_token' => $plainTextRefreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 900 // 15 minutes in seconds
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:50|unique:users,username',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => 'user'
            ]);

            // Create access token (15 minutes)
            $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15));

            // Create refresh token (30 days)
            $refreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $plainTextRefreshToken = Str::random(80)),
                'device_name' => $request->header('User-Agent') ?? 'Unknown Device',
                'ip_address' => $request->ip(),
                'expires_at' => now()->addDays(30)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role
                ],
                'access_token' => $accessToken->plainTextToken,
                'refresh_token' => $plainTextRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 900 // 15 minutes in seconds
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $request->validate([
                'refresh_token' => 'required|string'
            ]);

            // Find refresh token
            $hashedToken = hash('sha256', $request->refresh_token);
            $refreshToken = RefreshToken::where('token', $hashedToken)
                ->where('expires_at', '>', now())
                ->first();

            if (!$refreshToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired refresh token'
                ], 401);
            }

            $user = $refreshToken->user;

            // Delete old access tokens (keep refresh token)
            $user->tokens()->delete();

            // Create new access token
            $newAccessToken = $user->createToken('access_token', ['*'], now()->addDays(7));

            // Update refresh token last used (optional)
            $refreshToken->touch();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'access_token' => $newAccessToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => 25200
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Delete current access token
            $request->user()->currentAccessToken()->delete();

            // Or delete specific refresh token if sent
            if ($request->has('refresh_token')) {
                $hashedToken = hash('sha256', $request->refresh_token);
                RefreshToken::where('token', $hashedToken)
                    ->where('user_id', $request->user()->id)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function logoutAll(Request $request)
    {
        try {
            // Delete all access tokens
            $request->user()->tokens()->delete();

            // Delete all refresh tokens
            RefreshToken::where('user_id', $request->user()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout dari semua device berhasil'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }
}