<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
class CheckTokenVersion
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        $payload = JWTAuth::parseToken()->getPayload();

        $tokenVersion = $payload->get('token_version');

        if ($tokenVersion !== $user->token_version) {

            auth('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Session expired',
            ], 401);
        }

        return $next($request);
    }
}
