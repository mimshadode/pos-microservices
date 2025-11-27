<?php

// ========================================
// FILE: order-service/app/Http/Controllers/OrderController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\OrderCancelled;
use App\Services\RabbitMQ\EventPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    public function __construct(private EventPublisher $publisher)
    {
    }

    /**
     * Get all orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'user']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $orders = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Check stock availability with Product Service
            $stockCheck = $this->checkProductStock($validated['items']);
            
            if (!$stockCheck['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock not available',
                    'details' => $stockCheck['unavailable']
                ], 400);
            }

            // Calculate total
            $total = collect($validated['items'])->sum(function($item) {
                return $item['quantity'] * $item['price'];
            });

            // Create order
            $order = Order::create([
                'user_id' => $validated['user_id'],
                'order_number' => $this->generateOrderNumber(),
                'total_amount' => $total,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create order items
            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                ]);
            }

            DB::commit();

            $order->load('items');

            // Publish OrderCreated event
            event(new OrderCreated($order));
            $this->publisher->publish('order.created', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'items' => $order->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ])->toArray(),
                'created_at' => optional($order->created_at)->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order
     */
    public function show(Order $order)
    {
        return response()->json([
            'success' => true,
            'data' => $order->load(['items.product', 'user', 'payment'])
        ]);
    }

    /**
     * Update order status
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $order->status;

        $order->update($validated);

        if ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
            event(new OrderCancelled($order));
            $this->publisher->publish('order.cancelled', [
                'order_id' => $order->id,
                'status' => $order->status,
                'items' => $order->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->toArray(),
                'updated_at' => optional($order->updated_at)->toIso8601String(),
            ]);
        } else {
            event(new OrderUpdated($order));
            $this->publisher->publish('order.updated', [
                'order_id' => $order->id,
                'status' => $order->status,
                'updated_at' => optional($order->updated_at)->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load('items')
        ]);
    }

    /**
     * Delete order
     */
    public function destroy(Order $order)
    {
        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete completed order'
            ], 400);
        }

        // Publish cancel event to restore stock via consumer
        if ($order->status !== 'cancelled') {
            $this->publisher->publish('order.cancelled', [
                'order_id' => $order->id,
                'status' => 'cancelled',
                'items' => $order->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->toArray(),
                'updated_at' => optional($order->updated_at)->toIso8601String(),
            ]);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
    }

    /**
     * Get order summary
     */
    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'processing_orders' => Order::where('status', 'processing')->count(),
                'completed_orders' => Order::where('status', 'completed')->count(),
                'cancelled_orders' => Order::where('status', 'cancelled')->count(),
                'today_orders' => Order::whereDate('created_at', today())->count(),
                'today_revenue' => Order::whereDate('created_at', today())
                    ->where('status', 'completed')
                    ->sum('total_amount'),
            ]
        ]);
    }

    /**
     * Get user orders
     */
    public function userOrders(Request $request, int $userId)
    {
        $orders = Order::with('items')
            ->where('user_id', $userId)
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Check product stock with Product Service
     */
    private function checkProductStock(array $items): array
    {
        try {
            $response = Http::timeout(5)
                ->post('http://product-service/api/products/check-stock', [
                    'items' => collect($items)->map(fn($item) => [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity']
                    ])->toArray()
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'unavailable' => []];
            }

            $data = $response->json('data', []);
            $unavailable = collect($data)->filter(fn($item) => !$item['is_available'])->values();

            return [
                'success' => $unavailable->isEmpty(),
                'unavailable' => $unavailable->toArray()
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'unavailable' => []];
        }
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
