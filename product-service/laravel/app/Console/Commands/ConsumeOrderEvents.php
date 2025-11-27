<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\RabbitMQ\RabbitMQClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsumeOrderEvents extends Command
{
    protected $signature = 'rabbitmq:consume-orders';
    protected $description = 'Consume OrderCreated events from RabbitMQ to adjust stock in Product Service';

    public function handle(RabbitMQClient $client): int
    {
        $queue = config('rabbitmq.queues.order_created');
        $routingKeys = ['order.created', 'order.cancelled'];

        $this->info("Listening for order events on queue {$queue}...");

        $client->consume($queue, $routingKeys, function (array $payload) {
            $orderId = $payload['order_id'] ?? null;
            $items = $payload['items'] ?? [];
            $status = $payload['status'] ?? 'pending';
            $direction = $status === 'cancelled' ? 'increase' : 'decrease';

            if (!$orderId || empty($items)) {
                \Log::warning('Received OrderCreated payload missing order_id or items', $payload);
                return;
            }

            DB::transaction(function () use ($items, $orderId) {
                foreach ($items as $item) {
                    $productId = $item['product_id'] ?? null;
                    $qty = $item['quantity'] ?? 0;
                    if (!$productId || $qty <= 0) {
                        continue;
                    }

                    $product = Product::find($productId);
                    if (!$product) {
                        \Log::warning("Product {$productId} not found for order {$orderId}");
                        continue;
                    }

                    if ($direction === 'decrease') {
                        if ($product->stock < $qty) {
                            \Log::warning("Insufficient stock for product {$productId} on order {$orderId}", [
                                'stock' => $product->stock,
                                'requested' => $qty,
                            ]);
                            continue;
                        }

                        $product->decrement('stock', $qty);
                    } else {
                        $product->increment('stock', $qty);
                    }
                    $product->refresh();
                    $product->update([
                        'status' => $product->stock > 0 ? 'available' : 'out_of_stock',
                    ]);

                    \Log::info("Stock adjusted for product {$productId} (order {$orderId})", [
                        'quantity' => $qty,
                        'direction' => $direction,
                        'remaining' => $product->stock,
                    ]);
                }
            });
        });

        return self::SUCCESS;
    }
}
