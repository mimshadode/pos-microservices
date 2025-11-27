<?php

// ========================================
// FILE: payment-service/database/seeders/PaymentMethodSeeder.php
// ========================================

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'Cash', 'code' => 'cash', 'description' => 'Cash payment'],
            ['name' => 'Bank Transfer', 'code' => 'bank_transfer', 'description' => 'Bank transfer payment'],
            ['name' => 'QRIS', 'code' => 'qris', 'description' => 'QRIS payment'],
            ['name' => 'Credit Card', 'code' => 'credit_card', 'description' => 'Credit card payment'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}