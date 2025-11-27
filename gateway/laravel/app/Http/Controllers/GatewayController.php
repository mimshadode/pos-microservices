<?php

// ========================================
// FILE: gateway/app/Http/Controllers/GatewayController.php
// ========================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GatewayController extends Controller
{
    private array $services = [
        'auth' => 'http://auth-service',
        'product' => 'http://product-service',
        'order' => 'http://order-service',
        'payment' => 'http://payment-service',
        'reporting' => 'http://reporting-service',
    ];

    /**
     * Route requests to appropriate microservice
     */
    public function route(Request $request, string $service, string $path)
    {
        // Validate service exists
        if (!isset($this->services[$service])) {
            return response()->json([
                'error' => 'Service not found',
                'service' => $service
            ], 404);
        }

        $serviceUrl = $this->services[$service];
        $fullUrl = "{$serviceUrl}/api/{$path}";

        // Extract JWT token from request
        $token = $request->bearerToken();

        try {
            // Forward request to microservice
            $response = Http::withToken($token)
                ->timeout(30)
                ->withHeaders([
                    'X-Request-ID' => $this->generateRequestId(),
                    'X-Forwarded-For' => $request->ip(),
                ])
                ->{strtolower($request->method())}($fullUrl, $request->all());

            return response()->json(
                $response->json(),
                $response->status()
            );

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Service unavailable',
                'message' => $e->getMessage(),
                'service' => $service
            ], 503);
        }
    }

    /**
     * Aggregate data from multiple services
     */
    public function aggregate(Request $request)
    {
        $token = $request->bearerToken();

        // Parallel requests to multiple services
        $responses = Http::pool(fn ($pool) => [
            $pool->withToken($token)->get($this->services['product'] . '/api/products/summary'),
            $pool->withToken($token)->get($this->services['order'] . '/api/orders/summary'),
            $pool->withToken($token)->get($this->services['payment'] . '/api/payments/summary'),
        ]);

        return response()->json([
            'products' => $responses[0]->successful() ? $responses[0]->json() : null,
            'orders' => $responses[1]->successful() ? $responses[1]->json() : null,
            'payments' => $responses[2]->successful() ? $responses[2]->json() : null,
        ]);
    }

    /**
     * Generate unique request ID for tracing
     */
    private function generateRequestId(): string
    {
        return uniqid('req-', true);
    }
}