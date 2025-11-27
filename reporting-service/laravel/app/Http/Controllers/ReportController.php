<?php

// ========================================
// FILE: reporting-service/app/Http/Controllers/ReportController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\SalesReport;
use App\Models\ProductReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Generate sales report
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:daily,weekly,monthly,custom',
        ]);

        try {
            // Fetch transactions from Payment Service
            $transactions = $this->getTransactions(
                $validated['start_date'],
                $validated['end_date']
            );

            // Aggregate data
            $reportData = $this->aggregateSalesData($transactions);

            // Save report
            $report = SalesReport::create([
                'type' => $validated['type'],
                'start_date' => Carbon::parse($validated['start_date']),
                'end_date' => Carbon::parse($validated['end_date']),
                'total_transactions' => $reportData['total_transactions'],
                'total_revenue' => $reportData['total_revenue'],
                'total_items_sold' => $reportData['total_items_sold'],
                'average_order_value' => $reportData['average_order_value'],
                'by_payment_method' => $reportData['by_payment_method'],
                'daily_breakdown' => $reportData['daily_breakdown'],
                'generated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => $report
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales report
     */
    public function show(string $id)
    {
        $report = SalesReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Get all reports
     */
    public function index(Request $request)
    {
        $query = SalesReport::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Get daily sales report
     */
    public function dailySales(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

        $report = SalesReport::where('type', 'daily')
            ->where('start_date', $date)
            ->first();

        if (!$report) {
            // Generate on-the-fly
            $transactions = $this->getTransactions($date, $date);
            $data = $this->aggregateSalesData($transactions);

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'total_transactions' => $data['total_transactions'],
                    'total_revenue' => $data['total_revenue'],
                    'total_items_sold' => $data['total_items_sold'],
                    'average_order_value' => $data['average_order_value'],
                    'by_payment_method' => $data['by_payment_method'],
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Get monthly sales report
     */
    public function monthlySales(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $report = SalesReport::where('type', 'monthly')
            ->where('start_date', $startDate->toDateString())
            ->first();

        if (!$report) {
            // Generate on-the-fly
            $transactions = $this->getTransactions(
                $startDate->toDateString(),
                $endDate->toDateString()
            );
            
            $data = $this->aggregateSalesData($transactions);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $startDate->format('F Y'),
                    'total_transactions' => $data['total_transactions'],
                    'total_revenue' => $data['total_revenue'],
                    'daily_breakdown' => $data['daily_breakdown'],
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Get product performance report
     */
    public function productPerformance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get orders from Order Service
        $orders = $this->getOrders(
            $validated['start_date'],
            $validated['end_date']
        );

        // Aggregate product data
        $productData = $this->aggregateProductData($orders);

        // Save or update product reports
        foreach ($productData as $data) {
            ProductReport::updateOrCreate(
                [
                    'product_id' => $data['product_id'],
                    'period_start' => $validated['start_date'],
                    'period_end' => $validated['end_date'],
                ],
                [
                    'product_name' => $data['product_name'],
                    'total_quantity_sold' => $data['quantity_sold'],
                    'total_revenue' => $data['revenue'],
                    'order_count' => $data['order_count'],
                    'average_quantity_per_order' => $data['avg_quantity'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $productData
        ]);
    }

    /**
     * Get top selling products
     */
    public function topSellingProducts(Request $request)
    {
        $limit = $request->input('limit', 10);
        $period = $request->input('period', 'month'); // day, week, month

        $startDate = match($period) {
            'day' => today()->toDateString(),
            'week' => now()->startOfWeek()->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            default => now()->startOfMonth()->toDateString()
        };

        $endDate = today()->toDateString();

        $products = ProductReport::where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate)
            ->orderBy('total_quantity_sold', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Export report
     */
    public function export(Request $request, string $id)
    {
        $report = SalesReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
            ], 404);
        }

        $format = $request->input('format', 'json'); // json, csv, pdf

        return match($format) {
            'csv' => $this->exportToCsv($report),
            'pdf' => $this->exportToPdf($report),
            default => response()->json($report)
        };
    }

    /**
     * Get dashboard analytics
     */
    public function analytics()
    {
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $this->getQuickStats($today, $today),
                'yesterday' => $this->getQuickStats($yesterday, $yesterday),
                'this_month' => $this->getQuickStats($thisMonth, $today),
                'top_products' => $this->getTopProducts(5),
                'revenue_trend' => $this->getRevenueTrend(7), // Last 7 days
            ]
        ]);
    }

    /**
     * Get transactions from Payment Service
     */
    private function getTransactions(string $startDate, string $endDate): array
    {
        try {
            $response = Http::timeout(10)
                ->get('http://payment-service/api/payments', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'completed'
                ]);

            if ($response->successful()) {
                return $response->json('data.data', []);
            }

            return [];

        } catch (\Exception $e) {
            \Log::error('Failed to get transactions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get orders from Order Service
     */
    private function getOrders(string $startDate, string $endDate): array
    {
        try {
            $response = Http::timeout(10)
                ->get('http://order-service/api/orders', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'completed'
                ]);

            if ($response->successful()) {
                return $response->json('data.data', []);
            }

            return [];

        } catch (\Exception $e) {
            \Log::error('Failed to get orders: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Aggregate sales data
     */
    private function aggregateSalesData(array $transactions): array
    {
        $totalTransactions = count($transactions);
        $totalRevenue = collect($transactions)->sum('amount');
        $totalItemsSold = 0;

        $byPaymentMethod = [];
        $dailyBreakdown = [];

        foreach ($transactions as $transaction) {
            // Count items (would need to fetch from orders)
            $totalItemsSold += 1; // Simplified

            // By payment method
            $methodId = $transaction['payment_method_id'] ?? 'unknown';
            if (!isset($byPaymentMethod[$methodId])) {
                $byPaymentMethod[$methodId] = [
                    'count' => 0,
                    'total' => 0
                ];
            }
            $byPaymentMethod[$methodId]['count']++;
            $byPaymentMethod[$methodId]['total'] += $transaction['amount'];

            // Daily breakdown
            $date = Carbon::parse($transaction['created_at'])->toDateString();
            if (!isset($dailyBreakdown[$date])) {
                $dailyBreakdown[$date] = [
                    'count' => 0,
                    'total' => 0
                ];
            }
            $dailyBreakdown[$date]['count']++;
            $dailyBreakdown[$date]['total'] += $transaction['amount'];
        }

        return [
            'total_transactions' => $totalTransactions,
            'total_revenue' => $totalRevenue,
            'total_items_sold' => $totalItemsSold,
            'average_order_value' => $totalTransactions > 0 
                ? $totalRevenue / $totalTransactions 
                : 0,
            'by_payment_method' => $byPaymentMethod,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Aggregate product data
     */
    private function aggregateProductData(array $orders): array
    {
        $productData = [];

        foreach ($orders as $order) {
            foreach ($order['items'] ?? [] as $item) {
                $productId = $item['product_id'];

                if (!isset($productData[$productId])) {
                    $productData[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $item['product_name'] ?? 'Unknown',
                        'quantity_sold' => 0,
                        'revenue' => 0,
                        'order_count' => 0,
                    ];
                }

                $productData[$productId]['quantity_sold'] += $item['quantity'];
                $productData[$productId]['revenue'] += $item['subtotal'];
                $productData[$productId]['order_count']++;
            }
        }

        // Calculate averages
        foreach ($productData as &$data) {
            $data['avg_quantity'] = $data['order_count'] > 0 
                ? $data['quantity_sold'] / $data['order_count'] 
                : 0;
        }

        return array_values($productData);
    }

    /**
     * Get quick stats
     */
    private function getQuickStats(string $startDate, string $endDate): array
    {
        $transactions = $this->getTransactions($startDate, $endDate);
        $data = $this->aggregateSalesData($transactions);

        return [
            'transactions' => $data['total_transactions'],
            'revenue' => $data['total_revenue'],
            'average_order_value' => $data['average_order_value'],
        ];
    }

    /**
     * Get top products
     */
    private function getTopProducts(int $limit): array
    {
        return ProductReport::orderBy('total_quantity_sold', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get revenue trend
     */
    private function getRevenueTrend(int $days): array
    {
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i)->toDateString();
            $transactions = $this->getTransactions($date, $date);
            $data = $this->aggregateSalesData($transactions);

            $trend[] = [
                'date' => $date,
                'revenue' => $data['total_revenue'],
                'transactions' => $data['total_transactions'],
            ];
        }

        return $trend;
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(SalesReport $report)
    {
        // Implementation for CSV export
        return response()->json([
            'success' => true,
            'message' => 'CSV export not yet implemented'
        ]);
    }

    /**
     * Export to PDF
     */
    private function exportToPdf(SalesReport $report)
    {
        // Implementation for PDF export
        return response()->json([
            'success' => true,
            'message' => 'PDF export not yet implemented'
        ]);
    }
}