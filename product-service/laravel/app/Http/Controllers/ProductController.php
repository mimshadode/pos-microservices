<?php

// ========================================
// FILE: product-service/app/Http/Controllers/ProductController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\Product;
use App\Events\ProductCreated;
use App\Events\ProductUpdated;
use App\Events\StockAdjusted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Get all products
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:products,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $validated['status'] = $validated['stock'] > 0 ? 'available' : 'out_of_stock';

        $product = Product::create($validated);

        // Publish event
        event(new ProductCreated($product));

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('category')
        ], 201);
    }

    /**
     * Get single product
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load('category')
        ]);
    }

    /**
     * Update product
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:products,code,' . $product->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'status' => 'sometimes|in:available,out_of_stock',
        ]);

        if (isset($validated['stock'])) {
            $validated['status'] = $validated['stock'] > 0 ? 'available' : 'out_of_stock';
        }

        $product->update($validated);

        // Publish event
        event(new ProductUpdated($product));

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('category')
        ]);
    }

    /**
     * Delete product
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Adjust stock (for order processing)
     */
    public function adjustStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:increase,decrease',
            'reason' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $oldStock = $product->stock;

            if ($validated['type'] === 'decrease') {
                if ($product->stock < $validated['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock'
                    ], 400);
                }
                $product->decrement('stock', $validated['quantity']);
            } else {
                $product->increment('stock', $validated['quantity']);
            }

            $product->refresh();
            $product->update([
                'status' => $product->stock > 0 ? 'available' : 'out_of_stock'
            ]);

            DB::commit();

            // Publish event
            event(new StockAdjusted([
                'product_id' => $product->id,
                'old_stock' => $oldStock,
                'new_stock' => $product->stock,
                'quantity' => $validated['quantity'],
                'type' => $validated['type'],
                'reason' => $validated['reason'] ?? 'Manual adjustment',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check stock availability
     */
    public function checkStock(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $availability = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $availability[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'requested' => $item['quantity'],
                'available' => $product->stock,
                'is_available' => $product->stock >= $item['quantity'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $availability
        ]);
    }

    /**
     * Get product summary (for dashboard)
     */
    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => Product::count(),
                'available_products' => Product::where('status', 'available')->count(),
                'out_of_stock_products' => Product::where('status', 'out_of_stock')->count(),
                'low_stock_products' => Product::where('stock', '<=', 10)->where('stock', '>', 0)->count(),
            ]
        ]);
    }
}