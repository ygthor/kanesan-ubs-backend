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
        $user = auth()->user();
        
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
        
        // Get user's allowed customer codes (unless KBS user)
        $allowedCustomerCodes = null;
        if ($user && !($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            $allowedCustomerCodes = $user->customers()->pluck('customers.customer_code')->toArray();
            if (empty($allowedCustomerCodes)) {
                // User has no assigned customers, return empty report
                return makeResponse(200, 'Business summary retrieved successfully.', [
                    'totalSales' => 0,
                    'nettSales' => 0,
                    'collections' => 0,
                    'outstandingDebt' => 0,
                    'invoicesIssued' => 0,
                    'receiptsIssued' => 0,
                ]);
            }
        }

        // Example tables: invoices, receipts (adjust to your schema!)

        // --- Sales ---
        $caSalesQuery = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'CS');
        if ($allowedCustomerCodes) {
            $caSalesQuery->whereIn('CUSTNO', $allowedCustomerCodes);
        }
        $caSales = $caSalesQuery->sum('NET_BIL');

        $crSalesQuery = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'INV');
        if ($allowedCustomerCodes) {
            $crSalesQuery->whereIn('CUSTNO', $allowedCustomerCodes);
        }
        $crSales = $crSalesQuery->sum('NET_BIL');

        $totalSales = $caSales + $crSales;

        $returnsQuery = DB::table('artrans')
            ->whereBetween('DATE', [$fromDate, $toDate])
            ->where('TYPE', 'CN');
        if ($allowedCustomerCodes) {
            $returnsQuery->whereIn('CUSTNO', $allowedCustomerCodes);
        }
        $returns = $returnsQuery->sum('NET_BIL');

        $nettSales = $totalSales - $returns;

        // --- Collections ---
        $totalCrCollectQuery = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Card');
        if ($allowedCustomerCodes) {
            $totalCrCollectQuery->whereIn('customer_code', $allowedCustomerCodes);
        }
        $totalCrCollect = $totalCrCollectQuery->sum('paid_amount');

        $totalCashCollectQuery = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Cash');
        if ($allowedCustomerCodes) {
            $totalCashCollectQuery->whereIn('customer_code', $allowedCustomerCodes);
        }
        $totalCashCollect = $totalCashCollectQuery->sum('paid_amount');

        $totalBankCollectQuery = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Online Transfer');
        if ($allowedCustomerCodes) {
            $totalBankCollectQuery->whereIn('customer_code', $allowedCustomerCodes);
        }
        $totalBankCollect = $totalBankCollectQuery->sum('paid_amount');

        $chequeCollectQuery = DB::table('receipts')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->where('payment_type', 'Cheque');
        if ($allowedCustomerCodes) {
            $chequeCollectQuery->whereIn('customer_code', $allowedCustomerCodes);
        }
        $chequeCollect = $chequeCollectQuery->sum('paid_amount');


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
