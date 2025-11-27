<?php


// ========================================
// FILE: product-service/database/seeders/CategorySeeder.php
// ========================================

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan', 'description' => 'Produk makanan'],
            ['name' => 'Minuman', 'description' => 'Produk minuman'],
            ['name' => 'Snack', 'description' => 'Makanan ringan'],
            ['name' => 'Dessert', 'description' => 'Makanan penutup'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}