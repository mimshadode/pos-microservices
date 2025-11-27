<?php


// ========================================
// FILE: order-service/app/Http/Controllers/CartController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get user cart
     */
    public function show(int $userId)
    {
        $cart = Cart::with('items.product')
            ->firstOrCreate(['user_id' => $userId]);

        return response()->json([
            'success' => true,
            'data' => $cart
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $cart = Cart::firstOrCreate(['user_id' => $validated['user_id']]);

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $validated['product_id'])
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity', $validated['quantity']);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $validated['product_id'],
                    'quantity' => $validated['quantity'],
                    'price' => $validated['price'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart',
                'data' => $cart->fresh()->load('items.product')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, CartItem $item)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'data' => $item->cart->load('items.product')
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(CartItem $item)
    {
        $cart = $item->cart;
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'data' => $cart->load('items.product')
        ]);
    }

    /**
     * Clear cart
     */
    public function clear(int $userId)
    {
        $cart = Cart::where('user_id', $userId)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared'
        ]);
    }

    /**
     * Checkout cart (convert to order)
     */
    public function checkout(Request $request, int $userId)
    {
        $cart = Cart::with('items')->where('user_id', $userId)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Create order from cart
        $orderData = [
            'user_id' => $userId,
            'items' => $cart->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->toArray(),
            'notes' => $request->notes,
        ];

        $orderController = new OrderController();
        $orderResponse = $orderController->store(new Request($orderData));

        if ($orderResponse->status() === 201) {
            // Clear cart after successful order
            $cart->items()->delete();
        }

        return $orderResponse;
    }
}