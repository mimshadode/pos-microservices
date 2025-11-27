<?php

namespace App\Console\Commands;

use App\Models\SalesReport;
use App\Services\RabbitMQ\RabbitMQClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ConsumePaymentEvents extends Command
{
    protected $signature = 'rabbitmq:consume-payments';
    protected $description = 'Consume PaymentCompleted events to update reporting aggregates';

    public function handle(RabbitMQClient $client): int
    {
        $queue = config('rabbitmq.queues.payment_completed');
        $routingKey = 'payment.completed';

        $this->info("Listening for {$routingKey} events on queue {$queue}...");

        $client->consume($queue, $routingKey, function (array $payload) {
            $orderId = $payload['order_id'] ?? null;
            $amount = (float) ($payload['amount'] ?? 0);
            $paymentMethodId = (string) ($payload['payment_method_id'] ?? 'unknown');
            $paidAt = isset($payload['paid_at'])
                ? Carbon::parse($payload['paid_at'])
                : now();

            $date = $paidAt->toDateString();

            $report = SalesReport::firstOrNew([
                'type' => 'daily',
                'start_date' => $date,
                'end_date' => $date,
            ]);

            $report->total_transactions = ($report->total_transactions ?? 0) + 1;
            $report->total_revenue = ($report->total_revenue ?? 0) + $amount;
            $report->total_items_sold = ($report->total_items_sold ?? 0) + (int) ($payload['items_count'] ?? 0);

            $byPayment = $report->by_payment_method ?? [];
            if (!isset($byPayment[$paymentMethodId])) {
                $byPayment[$paymentMethodId] = ['count' => 0, 'total' => 0];
            }
            $byPayment[$paymentMethodId]['count'] += 1;
            $byPayment[$paymentMethodId]['total'] += $amount;
            $report->by_payment_method = $byPayment;

            $daily = $report->daily_breakdown ?? [];
            if (!isset($daily[$date])) {
                $daily[$date] = ['count' => 0, 'total' => 0];
            }
            $daily[$date]['count'] += 1;
            $daily[$date]['total'] += $amount;
            $report->daily_breakdown = $daily;

            $report->average_order_value = $report->total_transactions > 0
                ? $report->total_revenue / $report->total_transactions
                : 0;

            $report->generated_at = now();
            $report->save();

            \Log::info("PaymentCompleted processed for order {$orderId}", [
                'amount' => $amount,
                'method' => $paymentMethodId,
                'date' => $date,
            ]);
        });

        return self::SUCCESS;
    }
}
