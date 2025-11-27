<?php

// ========================================
// FILE: reporting-service/routes/api.php
// ========================================

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Report generation
Route::post('/reports/generate', [ReportController::class, 'generate']);
Route::get('/reports', [ReportController::class, 'index']);
Route::get('/reports/{id}', [ReportController::class, 'show']);
Route::get('/reports/{id}/export', [ReportController::class, 'export']);

// Sales reports
Route::get('/reports/sales/daily', [ReportController::class, 'dailySales']);
Route::get('/reports/sales/monthly', [ReportController::class, 'monthlySales']);

// Product reports
Route::post('/reports/products/performance', [ReportController::class, 'productPerformance']);
Route::get('/reports/products/top-selling', [ReportController::class, 'topSellingProducts']);

// Analytics
Route::get('/analytics', [ReportController::class, 'analytics']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'service' => 'reporting',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});