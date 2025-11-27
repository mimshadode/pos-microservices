<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CircuitBreakerMiddleware
{
    private int $failureThreshold = 5;
    private int $timeoutSeconds = 60;

    public function handle(Request $request, Closure $next, string $service): Response
    {
        $key = "circuit-breaker:{$service}";
        $failures = Cache::get($key, 0);

        // Circuit is OPEN (too many failures)
        if ($failures >= $this->failureThreshold) {
            $resetTime = Cache::get("{$key}:reset");
            
            if ($resetTime && now()->lt($resetTime)) {
                return response()->json([
                    'error' => 'Service temporarily unavailable',
                    'service' => $service,
                    'retry_after' => $resetTime->diffInSeconds(now())
                ], 503);
            }

            // Try to reset circuit
            Cache::forget($key);
            Cache::forget("{$key}:reset");
        }

        try {
            $response = $next($request);

            // Success - reset failures
            if ($response->status() < 500) {
                Cache::forget($key);
            }

            return $response;

        } catch (\Exception $e) {
            // Increment failure count
            Cache::increment($key);
            Cache::put("{$key}:reset", now()->addSeconds($this->timeoutSeconds));

            throw $e;
        }
    }
}
