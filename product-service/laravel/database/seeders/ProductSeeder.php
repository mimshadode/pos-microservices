<?php

// ========================================
// FILE: product-service/database/seeders/ProductSeeder.php
// ========================================

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        $products = [
            [
                'code' => 'MKN-001',
                'name' => 'Nasi Goreng Spesial',
                'description' => 'Nasi goreng dengan ayam dan telur',
                'price' => 25000,
                'stock' => 50,
                'status' => 'available',
                'category' => 'Makanan',
            ],
            [
                'code' => 'MKN-002',
                'name' => 'Mie Goreng',
                'description' => 'Mie goreng spesial',
                'price' => 20000,
                'stock' => 30,
                'status' => 'available',
                'category' => 'Makanan',
            ],
            [
                'code' => 'MKN-003',
                'name' => 'Ayam Bakar',
                'description' => 'Ayam bakar bumbu kecap',
                'price' => 35000,
                'stock' => 20,
                'status' => 'available',
                'category' => 'Makanan',
            ],
            [
                'code' => 'MNM-001',
                'name' => 'Es Teh Manis',
                'description' => 'Teh manis dingin',
                'price' => 8000,
                'stock' => 100,
                'status' => 'available',
                'category' => 'Minuman',
            ],
            [
                'code' => 'MNM-002',
                'name' => 'Jus Jeruk',
                'description' => 'Jus jeruk segar',
                'price' => 15000,
                'stock' => 50,
                'status' => 'available',
                'category' => 'Minuman',
            ],
            [
                'code' => 'MNM-003',
                'name' => 'Kopi Susu',
                'description' => 'Kopi susu gula aren',
                'price' => 18000,
                'stock' => 40,
                'status' => 'available',
                'category' => 'Minuman',
            ],
            [
                'code' => 'SNK-001',
                'name' => 'Keripik Singkong',
                'description' => 'Keripik singkong pedas',
                'price' => 12000,
                'stock' => 40,
                'status' => 'available',
                'category' => 'Snack',
            ],
            [
                'code' => 'SNK-002',
                'name' => 'French Fries',
                'description' => 'Kentang goreng crispy',
                'price' => 15000,
                'stock' => 30,
                'status' => 'available',
                'category' => 'Snack',
            ],
            [
                'code' => 'DST-001',
                'name' => 'Es Krim Vanilla',
                'description' => 'Es krim vanilla premium',
                'price' => 20000,
                'stock' => 25,
                'status' => 'available',
                'category' => 'Dessert',
            ],
            [
                'code' => 'DST-002',
                'name' => 'Pudding Coklat',
                'description' => 'Pudding coklat lembut',
                'price' => 15000,
                'stock' => 20,
                'status' => 'available',
                'category' => 'Dessert',
            ],
        ];

        foreach ($products as $product) {
            $categoryId = $categories[$product['category']] ?? null;

            if (!$categoryId) {
                continue;
            }

            Product::updateOrCreate(
                ['code' => $product['code']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'status' => $product['status'],
                    'category_id' => $categoryId,
                ]
            );
        }
    }
}