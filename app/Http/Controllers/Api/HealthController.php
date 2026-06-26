<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            DB::connection()->getPdo();

            return $this->success([
                'status' => 'healthy',
                'database' => 'connected',
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
            ], 'API is healthy');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'API is unhealthy',
                'data' => [
                    'status' => 'unhealthy',
                    'database' => 'disconnected',
                    'timestamp' => now()->toISOString(),
                ],
            ], 503);
        }
    }
}