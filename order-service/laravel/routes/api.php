<?php


// ========================================
// FILE: order-service/routes/api.php
// ========================================

use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Order routes
Route::apiResource('orders', OrderController::class);
Route::get('/orders/summary', [OrderController::class, 'summary']);
Route::get('/orders/user/{userId}', [OrderController::class, 'userOrders']);

// Cart routes
Route::get('/cart/{userId}', [CartController::class, 'show']);
Route::post('/cart/add', [CartController::class, 'addItem']);
Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
Route::delete('/cart/{userId}/clear', [CartController::class, 'clear']);
Route::post('/cart/{userId}/checkout', [CartController::class, 'checkout']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'service' => 'order',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});