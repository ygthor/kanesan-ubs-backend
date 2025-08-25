<?php
// app/Http/Controllers/ReportController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function businessSummary(Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        if (empty($fromDate)) {
            $fromDate = date('Y-01-01');
        }
        if (empty($toDate)) {
            $toDate = date('Y-m-d');
        }


        if (!$fromDate || !$toDate) {
            return response()->json(['error' => 'from_date and to_date are required'], 422);
        }

        // Example tables: invoices, receipts (adjust to your schema!)

        // --- Sales ---
        $caSales = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'CS')
            ->sum('NET_BIL');

        $crSales = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'INV')
            ->sum('NET_BIL');

        $totalSales = $caSales + $crSales;

        $returns = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'CN')
            ->sum('NET_BIL');

        $nettSales = $totalSales - $returns;

        // --- Collections ---
        $totalCrCollect = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Card')
            ->sum('paid_amount');

        $totalCashCollect = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Cash')
            ->sum('paid_amount');


        $totalBankCollect = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Online Transfer')
            ->sum('paid_amount');

        $chequeCollect = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Cheque')
            ->sum('paid_amount');


        $totalCollection = $totalCrCollect + $totalCashCollect + $chequeCollect + $totalBankCollect;

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ca_sales' => $caSales,
            'cr_sales' => $crSales,
            'total_sales' => $totalSales,
            'returns' => $returns,
            'nett_sales' => $nettSales,
            'total_cr_collect' => $totalCrCollect,
            'total_cash_collect' => $totalCashCollect,
            'cheque_collect' => $chequeCollect,
            'total_bank_collect' => $totalBankCollect,
            'total_collection' => $totalCollection,
        ]);
    }

    public function salesOrders(Request $request)
    {
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate   = $request->input('to_date', date('Y-m-d'));
        $customerId = $request->input('customer_id');
        $type = $request->input('type', 'Sales Order'); // Sales Order, Invoice, etc.

        $query = DB::table('orders')
            ->where('type', 'SO')
            ->select('id', 'reference_no', 'order_date', 'net_amount', 'customer_code', 'customer_name', 'customer_id', 'status')
            ->whereBetween('order_date', [$fromDate, $toDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $orders = $query->orderBy('order_date')->get();

        return response()->json($orders);
    }
}
