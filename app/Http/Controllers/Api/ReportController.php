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
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search'); // Search by customer code or name

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

        // Filter by agent: if user doesn't have full access, only show their own data
        // Users with full access can filter by any agent_no if provided
        // Users without full access are always restricted to their own agent_no
        $agentNoToFilter = null;
        if ($user && !hasFullAccess()) {
            // Always filter by logged-in user's name (ignore any agent_no in request)
            $agentNoToFilter = $user->name;
        } elseif ($agentNo) {
            // User has full access, allow filtering by provided agent_no
            $agentNoToFilter = $agentNo;
        }

        // Helper function to apply agent_no filter
        $applyAgentFilter = function($query) use ($agentNoToFilter) {
            if ($agentNoToFilter) {
                $query->where('orders.agent_no', $agentNoToFilter);
            }
        };

        $applyCustFilter = function($query) use ($customerId, $customerSearch) {
            if ($customerId) {
                $query->where('orders.customer_id', $customerId);
            }
            if ($customerSearch) {
                $query->where(function($q) use ($customerSearch) {
                    $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                      ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                      ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
                });
            }
        };


        // --- Sales ---
        // CA Sales: Cash Sales from customers with payment_type 'Cash Sales' or 'Cash'
        $caSalesQuery = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->whereBetween('orders.order_date', [$fromDate, $toDate])
            ->where('orders.type', 'INV')
            ->whereIn('customers.customer_type', ['Cash']);

        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($caSalesQuery);
        $applyCustFilter($caSalesQuery);
        $caSales = $caSalesQuery->sum('orders.net_amount');


        // CR Sales: Credit Sales/Invoices (type='INV')
        /* $crSalesQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'INV');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($crSalesQuery);
        $crSales = $crSalesQuery->sum('net_amount'); */
        $crSalesQuery = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->whereBetween('orders.order_date', [$fromDate, $toDate])
            ->where('orders.type', 'INV')
            ->whereIn('customers.customer_type', ['CREDITOR']);

        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($crSalesQuery);
        $applyCustFilter($crSalesQuery);
        $crSales = $crSalesQuery->sum('orders.net_amount');

        $totalSales = $caSales + $crSales;

        // Returns: Credit Notes (type='CN')
        $returnsQuery = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->whereBetween('orders.order_date', [$fromDate, $toDate])
            ->where('orders.type', 'CN');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($returnsQuery);
        $applyCustFilter($returnsQuery);
        $returns = $returnsQuery->sum('orders.net_amount');

        $nettSales = $totalSales - $returns;

        // --- Collections ---
        // Single query with GROUP BY for all payment types (more efficient - world standard)
        // Use INNER JOIN since customer_id is required (foreign key constraint)
        $collectionsQuery = DB::table('receipts')
            ->join('customers', 'receipts.customer_id', '=', 'customers.id')
            ->whereBetween('receipts.receipt_date', [$fromDate, $toDate])
            ->whereNull('receipts.deleted_at') // Exclude soft-deleted receipts
            ->select('receipts.payment_type', DB::raw('SUM(receipts.paid_amount) as total'));

        if ($agentNoToFilter) {
            $collectionsQuery->where('customers.agent_no', $agentNoToFilter);
        }
        if ($customerId) {
            $collectionsQuery->where('receipts.customer_id', $customerId);
        }
        if ($customerSearch) {
            $collectionsQuery->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }

        $collections = $collectionsQuery->groupBy('receipts.payment_type')->pluck('total', 'payment_type');

        // Handle case-insensitive matching (frontend sends: 'CASH', 'Card', 'Online Transfer', 'CHEQUE')
        // Database might store in different cases, so we search case-insensitively
        $totalCrCollect = 0;
        $totalCashCollect = 0;
        //$totalBankCollect = 0;
        $chequeCollect = 0;
        $pdchequeCollect = 0;

        foreach ($collections as $paymentType => $total) {
            $upperType = strtoupper(trim($paymentType));
            if ($upperType === 'CARD' || $upperType === 'E-WALLET' || $upperType === 'ONLINE TRANSFER')
            {
                $totalCrCollect = $total;
            } elseif ($upperType === 'CASH') {
                $totalCashCollect = $total;
            }
            // elseif ($upperType === 'ONLINE TRANSFER') {
            //     $totalBankCollect = $total;
            // }
            elseif ($upperType === 'CHEQUE') {
                $chequeCollect = $total;
            }
            elseif ($upperType === 'PD CHEQUE') {
                $pdchequeCollect = $total;
            }
        }


        $totalCollection = $totalCrCollect + $totalCashCollect + $chequeCollect + $pdchequeCollect;

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
            //'total_bank_collect' => $totalBankCollect,
            'total_collection' => $totalCollection,
            'pd_cheque_collect' => $pdchequeCollect,
        ]);
    }

    public function salesOrders(Request $request)
    {
        $user = auth()->user();

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

        // Filter by agent: if user doesn't have full access, only show their own data
        // Users with full access can filter by any agent_no if provided
        // Users without full access are always restricted to their own agent_no
        if ($user && !hasFullAccess()) {
            // Always filter by logged-in user's name (ignore any agent_no in request)
            $userName = $user->name;
            if ($userName) {
                $query->where('agent_no', $userName);
            } else {
                // User has no name, return empty result
                return response()->json([]);
            }
        } elseif ($agentNo) {
            // User has full access, allow filtering by provided agent_no
            $query->where('agent_no', $agentNo);
        }

        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }

        $orders = $query->orderBy('order_date')->get();

        // Get user's name for filtering linked CN orders (if user doesn't have full access)
        $userName = ($user && !hasFullAccess()) ? $user->name : null;

        // Calculate adjusted net amount for each invoice (deduct linked CN totals)
        $adjustedOrders = $orders->map(function ($order) use ($userName) {
            // Get linked CN orders for this invoice
            $linkedCNsQuery = DB::table('orders')
                ->where('credit_invoice_no', $order->reference_no)
                ->where('type', 'CN');

            // Filter linked CN orders by agent_no if user doesn't have full access
            if ($userName) {
                $linkedCNsQuery->where('agent_no', $userName);
            }

            $linkedCNs = $linkedCNsQuery->get();

            // Calculate total from linked CN orders
            $cnTotal = $linkedCNs->sum('net_amount');

            // Calculate adjusted net amount (original net_amount - CN total)
            $adjustedNetAmount = max(0, ($order->net_amount ?? 0) - $cnTotal);

            // Return order with adjusted amount
            return (object) [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'order_date' => $order->order_date,
                'net_amount' => $adjustedNetAmount, // Adjusted amount (deducted linked CN totals)
                'customer_code' => $order->customer_code,
                'customer_name' => $order->customer_name,
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'agent_no' => $order->agent_no,
            ];
        });

        return response()->json($adjustedOrders->values());
    }
}
