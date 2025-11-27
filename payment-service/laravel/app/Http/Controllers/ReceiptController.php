<?php


// ========================================
// FILE: payment-service/app/Http/Controllers/ReceiptController.php
// ========================================

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt)
    {
        return response()->json([
            'success' => true,
            'data' => $receipt->load('payment.method')
        ]);
    }

    public function print(Receipt $receipt)
    {
        // Generate printable receipt
        return view('receipts.print', compact('receipt'));
    }

    public function download(Receipt $receipt)
    {
        // Generate PDF receipt
        // Implementation depends on PDF library (dompdf, etc.)
        
        return response()->json([
            'success' => true,
            'message' => 'PDF generation not yet implemented'
        ]);
    }
}
