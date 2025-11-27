<?php


// ========================================
// FILE: payment-service/app/Http/Controllers/PaymentMethodController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $methods
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:payment_methods,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $method = PaymentMethod::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payment method created',
            'data' => $method
        ], 201);
    }
}