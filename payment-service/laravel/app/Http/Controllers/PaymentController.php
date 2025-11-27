<?php

// ========================================
// FILE: payment-service/app/Http/Controllers/PaymentController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Services\RabbitMQ\EventPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function __construct(private EventPublisher $publisher)
    {
    }

    /**
     * Get all payments
     */
    public function index(Request $request)
    {
        $query = Payment::with(['method', 'receipt']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by method
        if ($request->has('method_id')) {
            $query->where('payment_method_id', $request->method_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Process payment
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'payment_data' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Get order details from Order Service
            $order = $this->getOrderDetails($validated['order_id']);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Validate payment amount
            if ($validated['amount'] < $order['total_amount']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount is less than order total',
                    'order_total' => $order['total_amount'],
                    'payment_amount' => $validated['amount']
                ], 400);
            }

            // Create payment
            $payment = Payment::create([
                'order_id' => $validated['order_id'],
                'payment_method_id' => $validated['payment_method_id'],
                'amount' => $validated['amount'],
                'order_total' => $order['total_amount'],
                'change_amount' => $validated['amount'] - $order['total_amount'],
                'status' => 'pending',
                'payment_data' => $validated['payment_data'] ?? null,
            ]);

            // Process payment based on method
            $paymentResult = $this->processPaymentMethod(
                $payment,
                $validated['payment_method_id'],
                $validated['payment_data'] ?? []
            );

            if ($paymentResult['success']) {
                $payment->update([
                    'status' => 'completed',
                    'transaction_id' => $paymentResult['transaction_id'] ?? null,
                    'paid_at' => now(),
                ]);

                // Generate receipt
                $receipt = $this->generateReceipt($payment, $order);

                // Update order status in Order Service
                $this->updateOrderStatus($validated['order_id'], 'completed');

                DB::commit();

                // Publish PaymentCompleted event
                event(new PaymentCompleted($payment));
                $this->publishPaymentEvent('payment.completed', $payment, [
                    'items_count' => count($order['items'] ?? []),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'payment' => $payment->load(['method', 'receipt']),
                        'receipt' => $receipt,
                    ]
                ], 201);

            } else {
                $payment->update([
                    'status' => 'failed',
                    'error_message' => $paymentResult['error'] ?? 'Payment failed',
                ]);

                DB::commit();

                // Publish PaymentFailed event
                event(new PaymentFailed($payment));
                $this->publishPaymentEvent('payment.failed', $payment, [
                    'error' => $paymentResult['error'] ?? 'Payment failed',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'error' => $paymentResult['error'] ?? 'Unknown error',
                    'data' => $payment
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details
     */
    public function show(Payment $payment)
    {
        return response()->json([
            'success' => true,
            'data' => $payment->load(['method', 'receipt'])
        ]);
    }

    /**
     * Get payment by order
     */
    public function byOrder(int $orderId)
    {
        $payment = Payment::with(['method', 'receipt'])
            ->where('order_id', $orderId)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }

    /**
     * Verify payment (for bank transfer/QRIS)
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'verification_data' => 'required|array',
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);

        if ($payment->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Payment already completed'
            ], 400);
        }

        // Verify payment with payment gateway
        $verificationResult = $this->verifyPaymentGateway(
            $payment,
            $validated['verification_data']
        );

        if ($verificationResult['success']) {
            $payment->update([
                'status' => 'completed',
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            // Update order status
            $this->updateOrderStatus($payment->order_id, 'completed');

            // Publish event
            event(new PaymentCompleted($payment));
            $this->publishPaymentEvent('payment.completed', $payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => $payment
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
            'error' => $verificationResult['error'] ?? 'Verification failed'
        ], 400);
    }

    /**
     * Get payment summary
     */
    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_payments' => Payment::count(),
                'completed_payments' => Payment::where('status', 'completed')->count(),
                'pending_payments' => Payment::where('status', 'pending')->count(),
                'failed_payments' => Payment::where('status', 'failed')->count(),
                'today_payments' => Payment::whereDate('created_at', today())->count(),
                'today_revenue' => Payment::whereDate('created_at', today())
                    ->where('status', 'completed')
                    ->sum('amount'),
                'by_method' => Payment::where('status', 'completed')
                    ->selectRaw('payment_method_id, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('payment_method_id')
                    ->with('method')
                    ->get(),
            ]
        ]);
    }

    /**
     * Process payment based on method
     */
    private function processPaymentMethod(Payment $payment, int $methodId, array $data): array
    {
        $method = PaymentMethod::find($methodId);

        return match($method->code) {
            'cash' => $this->processCash($payment, $data),
            'bank_transfer' => $this->processBankTransfer($payment, $data),
            'qris' => $this->processQRIS($payment, $data),
            'credit_card' => $this->processCreditCard($payment, $data),
            default => ['success' => false, 'error' => 'Invalid payment method']
        };
    }

    /**
     * Process cash payment
     */
    private function processCash(Payment $payment, array $data): array
    {
        // Cash payment is always immediately successful
        return [
            'success' => true,
            'transaction_id' => 'CASH-' . uniqid()
        ];
    }

    /**
     * Process bank transfer
     */
    private function processBankTransfer(Payment $payment, array $data): array
    {
        // In real implementation, integrate with bank API
        // For now, mark as pending and require manual verification
        
        return [
            'success' => true,
            'transaction_id' => 'BANK-' . uniqid(),
            'requires_verification' => true
        ];
    }

    /**
     * Process QRIS payment
     */
    private function processQRIS(Payment $payment, array $data): array
    {
        // In real implementation, integrate with QRIS provider
        // For now, simulate successful payment
        
        return [
            'success' => true,
            'transaction_id' => 'QRIS-' . uniqid()
        ];
    }

    /**
     * Process credit card payment
     */
    private function processCreditCard(Payment $payment, array $data): array
    {
        // In real implementation, integrate with payment gateway (Stripe, Midtrans, etc.)
        // For now, simulate payment
        
        if (!isset($data['card_number']) || !isset($data['cvv'])) {
            return ['success' => false, 'error' => 'Invalid card data'];
        }

        return [
            'success' => true,
            'transaction_id' => 'CC-' . uniqid()
        ];
    }

    /**
     * Verify payment with gateway
     */
    private function verifyPaymentGateway(Payment $payment, array $data): array
    {
        // In real implementation, call payment gateway API
        // For now, simulate verification
        
        return [
            'success' => true,
            'verified_at' => now()
        ];
    }

    /**
     * Generate receipt
     */
    private function generateReceipt(Payment $payment, array $order): Receipt
    {
        return Receipt::create([
            'payment_id' => $payment->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'order_data' => $order,
            'payment_data' => [
                'method' => $payment->method->name,
                'amount' => $payment->amount,
                'change' => $payment->change_amount,
                'transaction_id' => $payment->transaction_id,
            ],
            'issued_at' => now(),
        ]);
    }

    /**
     * Get order details from Order Service
     */
    private function getOrderDetails(int $orderId): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get("http://order-service/api/orders/{$orderId}");

            if ($response->successful()) {
                return $response->json('data');
            }

            return null;

        } catch (\Exception $e) {
            \Log::error("Failed to get order details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update order status in Order Service
     */
    private function updateOrderStatus(int $orderId, string $status): void
    {
        try {
            Http::timeout(5)->put(
                "http://order-service/api/orders/{$orderId}",
                ['status' => $status]
            );
        } catch (\Exception $e) {
            \Log::error("Failed to update order status: " . $e->getMessage());
        }
    }

    /**
     * Generate unique receipt number
     */
    private function generateReceiptNumber(): string
    {
        return 'RCP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Publish payment event to RabbitMQ
     */
    private function publishPaymentEvent(string $routingKey, Payment $payment, array $extra = []): void
    {
        $payload = array_merge([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'payment_method_id' => $payment->payment_method_id,
            'amount' => (float) $payment->amount,
            'change_amount' => (float) $payment->change_amount,
            'status' => $payment->status,
            'paid_at' => optional($payment->paid_at)->toIso8601String(),
        ], $extra);

        $this->publisher->publish($routingKey, $payload);
    }
}
