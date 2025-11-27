<?php


// ========================================
// FILE: reporting-service/app/Models/SalesReport.php
// ========================================

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SalesReport extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'sales_reports';

    protected $fillable = [
        'type',
        'start_date',
        'end_date',
        'total_transactions',
        'total_revenue',
        'total_items_sold',
        'average_order_value',
        'by_payment_method',
        'daily_breakdown',
        'generated_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'total_revenue' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'by_payment_method' => 'array',
        'daily_breakdown' => 'array',
        'generated_at' => 'datetime',
    ];
}
