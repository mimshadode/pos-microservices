<?php

// ========================================
// FILE: payment-service/app/Models/Receipt.php
// ========================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'payment_id',
        'receipt_number',
        'order_data',
        'payment_data',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'order_data' => 'array',
            'payment_data' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
