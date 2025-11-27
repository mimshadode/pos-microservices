<?php

// ========================================
// FILE: payment-service/routes/api.php
// ========================================

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

// Payment routes
Route::post('/payments/process', [PaymentController::class, 'process']);
Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{payment}', [PaymentController::class, 'show']);
Route::get('/payments/order/{orderId}', [PaymentController::class, 'byOrder']);
Route::post('/payments/verify', [PaymentController::class, 'verify']);
Route::get('/payments/summary', [PaymentController::class, 'summary']);

// Payment method routes
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::post('/payment-methods', [PaymentMethodController::class, 'store']);

// Receipt routes
Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);
Route::get('/receipts/{receipt}/print', [ReceiptController::class, 'print']);
Route::get('/receipts/{receipt}/download', [ReceiptController::class, 'download']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'service' => 'payment',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});