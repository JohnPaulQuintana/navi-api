<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->error(
                message: 'Invalid credentials',
                status: 401
            );
        }

        $user = Auth::guard('api')->user();

        if (! $user->is_active) {
            Auth::guard('api')->logout();

            return $this->error(
                message: 'Account disabled',
                status: 403
            );
        }

        // Prevent technicians from logging into the admin dashboard
        if ($user->role === 'technician') {
            Auth::guard('api')->logout();

            return $this->error(
                message: 'Technician accounts are only allowed to access the mobile application.',
                status: 403
            );
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    public function refresh()
    {
        try {

            $newToken = JWTAuth::parseToken()->refresh();

            return $this->success([
                'token' => $newToken,
            ], 'Token refreshed');

        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], 500);
        }
    }

    public function me()
    {
        return $this->success([
            'user' => new UserResource(
                Auth::guard('api')->user()
            ),
        ]);
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return $this->success(
            null,
            'Logged out successfully'
        );
    }
}
