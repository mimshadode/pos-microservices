<?php


use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('/auth/login', [GatewayController::class, 'route'])
    ->defaults('service', 'auth')
    ->defaults('path', 'auth/login');

Route::post('/auth/register', [GatewayController::class, 'route'])
    ->defaults('service', 'auth')
    ->defaults('path', 'auth/register');

// Protected routes (require JWT)
Route::middleware(['jwt', 'rate.limit:100', 'circuit.breaker:default'])->group(function () {
    
    // Dashboard - aggregate data
    Route::get('/dashboard', [GatewayController::class, 'aggregate']);

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::any('{path}', [GatewayController::class, 'route'])
            ->where('path', '.*')
            ->defaults('service', 'auth');
    });

    // Product routes
    Route::prefix('products')->middleware('circuit.breaker:product')->group(function () {
        Route::any('{path?}', [GatewayController::class, 'route'])
            ->where('path', '.*')
            ->defaults('service', 'product')
            ->defaults('path', 'products');
    });

    // Order routes
    Route::prefix('orders')->middleware('circuit.breaker:order')->group(function () {
        Route::any('{path?}', [GatewayController::class, 'route'])
            ->where('path', '.*')
            ->defaults('service', 'order')
            ->defaults('path', 'orders');
    });

    // Payment routes
    Route::prefix('payments')->middleware('circuit.breaker:payment')->group(function () {
        Route::any('{path?}', [GatewayController::class, 'route'])
            ->where('path', '.*')
            ->defaults('service', 'payment')
            ->defaults('path', 'payments');
    });

    // Reporting routes
    Route::prefix('reports')->middleware('circuit.breaker:reporting')->group(function () {
        Route::any('{path?}', [GatewayController::class, 'route'])
            ->where('path', '.*')
            ->defaults('service', 'reporting')
            ->defaults('path', 'reports');
    });
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'services' => [
            'auth' => checkServiceHealth('http://auth-service/health'),
            'product' => checkServiceHealth('http://product-service/health'),
            'order' => checkServiceHealth('http://order-service/health'),
            'payment' => checkServiceHealth('http://payment-service/health'),
            'reporting' => checkServiceHealth('http://reporting-service/health'),
        ]
    ]);
});

function checkServiceHealth(string $url): string {
    try {
        $response = Http::timeout(2)->get($url);
        return $response->successful() ? 'healthy' : 'unhealthy';
    } catch (\Exception $e) {
        return 'unreachable';
    }
}
