<?php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

// Product routes
Route::apiResource('products', ProductController::class);
Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
Route::post('/products/check-stock', [ProductController::class, 'checkStock']);
Route::get('/products/summary', [ProductController::class, 'summary']);

// Category routes
Route::apiResource('categories', CategoryController::class);

// Health check
Route::get('/health', function () {
    return response()->json([
        'service' => 'product',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});
