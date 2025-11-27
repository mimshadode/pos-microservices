<?php

// ========================================
// FILE: reporting-service/app/Models/ProductReport.php
// ========================================

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProductReport extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'product_reports';

    protected $fillable = [
        'product_id',
        'product_name',
        'period_start',
        'period_end',
        'total_quantity_sold',
        'total_revenue',
        'order_count',
        'average_quantity_per_order',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'average_quantity_per_order' => 'decimal:2',
    ];
}