<?php

// ========================================
// FILE: payment-service/app/Models/Payment.php
// ========================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method_id',
        'amount',
        'order_total',
        'change_amount',
        'status',
        'transaction_id',
        'payment_data',
        'error_message',
        'paid_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'order_total' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'payment_data' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}