<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        // Validate token with Auth Service
        try {
            $response = Http::withToken($token)
                ->post('http://auth-service/api/auth/validate');

            if (!$response->successful()) {
                return response()->json(['error' => 'Invalid token'], 401);
            }

            $user = $response->json('user');
            $request->attributes->set('user', $user);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication service unavailable'
            ], 503);
        }

        return $next($request);
    }
}