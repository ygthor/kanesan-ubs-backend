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
        
        // Ensure dates include full time range for datetime fields
        // fromDate should start at 00:00:00, toDate should end at 23:59:59
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }
        
        // Use Orders table - artrans is deprecated

        // Get user's allowed customer IDs for filtering (unless KBS user)
        $allowedCustomerIds = null;
        $allowedCustomerCodes = null;
        if ($user && !($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            $allowedCustomerIds = DB::table('customers')
                ->whereIn('agent_no', [$user->name])
                ->pluck('id')
                ->toArray();
            if (empty($allowedCustomerIds)) {
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
            $allowedCustomerCodes = DB::table('customers')
                ->whereIn('agent_no', [$user->name])
                ->pluck('customer_code')
                ->toArray();
        }

        // --- Sales ---
        // CA Sales: Cash Sales (type='CS')
        $caSalesQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'CS');
        if ($allowedCustomerIds) {
            $caSalesQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $caSales = $caSalesQuery->sum('net_amount');

        // CR Sales: Credit Sales/Invoices (type='INV')
        $crSalesQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'INV');
        if ($allowedCustomerIds) {
            $crSalesQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $crSales = $crSalesQuery->sum('net_amount');

        $totalSales = $caSales + $crSales;

        // Returns: Credit Notes (type='CN')
        $returnsQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'CN');
        if ($allowedCustomerIds) {
            $returnsQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $returns = $returnsQuery->sum('net_amount');

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
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search'); // Search by customer code or name
        $type = $request->input('type', 'Sales Order'); // Sales Order, Invoice, etc.

        // Ensure dates include full time range for datetime fields
        // fromDate should start at 00:00:00, toDate should end at 23:59:59
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        $query = DB::table('orders')
            ->where('type', 'INV')
            ->select('id', 'reference_no', 'order_date', 'net_amount', 'customer_code', 'customer_name', 'customer_id', 'status', 'agent_no')
            ->whereBetween('order_date', [$fromDate, $toDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($agentNo) {
            $query->where('agent_no', $agentNo);
        }

        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('name', 'like', "%{$customerSearch}%")
                  ->orWhere('company_name', 'like', "%{$customerSearch}%");
            });
        }

        $orders = $query->orderBy('order_date')->get();

        return response()->json($orders);
    }
}
