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
        $fromDateForQuery = $fromDate;
        $toDateForQuery = $toDate;
        if (strlen($fromDateForQuery) == 10) {
            $fromDateForQuery .= ' 00:00:00';
        }
        if (strlen($toDateForQuery) == 10) {
            $toDateForQuery .= ' 23:59:59';
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
            ->whereBetween('orders.order_date', [$fromDateForQuery, $toDateForQuery])
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
            ->whereBetween('orders.order_date', [$fromDateForQuery, $toDateForQuery])
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
            ->whereBetween('orders.order_date', [$fromDateForQuery, $toDateForQuery])
            ->where('orders.type', 'CN');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($returnsQuery);
        $applyCustFilter($returnsQuery);
        $returns = $returnsQuery->sum('orders.net_amount');

        $caReturnQuery = clone $returnsQuery;
        $caReturnQuery->whereIn('customers.customer_type', ['CASH']);
        $totalCashReturn = $caReturnQuery->sum('orders.net_amount') ?? 0;

        $crReturnQuery = clone $returnsQuery;
        $crReturnQuery->whereIn('customers.customer_type', ['CREDITOR']);
        $totalCrReturn = $crReturnQuery->sum('orders.net_amount') ?? 0;

        $nettSales = $totalSales - $returns;

        // --- Collections ---
        // IMPORTANT: CA and CR collections should follow customer_type, not payment_type
        // - CA collection = all receipts from customers with customer_type = 'Cash'
        // - CR collection = all receipts from customers with customer_type = 'CREDITOR'
        // - Cheque collections are tracked separately by payment_type but included in totals

        // Base query for all collections
        $collectionsBaseQuery = DB::table('receipts')
            ->join('customers', 'receipts.customer_id', '=', 'customers.id')
            ->whereBetween('receipts.receipt_date', [$fromDateForQuery, $toDateForQuery])
            ->whereNull('receipts.deleted_at'); // Exclude soft-deleted receipts

        if ($agentNoToFilter) {
            $collectionsBaseQuery->where('customers.agent_no', $agentNoToFilter);
        }
        if ($customerId) {
            $collectionsBaseQuery->where('receipts.customer_id', $customerId);
        }
        if ($customerSearch) {
            $collectionsBaseQuery->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }

        // CA Collection: All receipts from Cash customers (regardless of payment type)
        // Matches CA Sales logic: customer_type = 'Cash'
        $caCollectionQuery = clone $collectionsBaseQuery;
        $caCollectionQuery->whereIn('customers.customer_type', ['CASH']);
        $totalCashCollect = $caCollectionQuery->sum('receipts.paid_amount') ?? 0;

        // CR Collection: All receipts from CREDITOR customers (regardless of payment type)
        // Matches CR Sales logic: customer_type = 'CREDITOR'
        $crCollectionQuery = clone $collectionsBaseQuery;
        $crCollectionQuery->whereIn('customers.customer_type', ['CREDITOR']);
        $totalCrCollect = $crCollectionQuery->sum('receipts.paid_amount') ?? 0;

        // Cheque collections: Track by payment_type (separate from CA/CR categorization)
        $chequeQuery = clone $collectionsBaseQuery;
        $chequeCollections = $chequeQuery
            ->where(function($q) {
                $q->whereRaw('UPPER(TRIM(receipts.payment_type)) = ?', ['CHEQUE'])
                  ->orWhereRaw('UPPER(TRIM(receipts.payment_type)) = ?', ['PD CHEQUE']);
            })
            ->select('receipts.payment_type', DB::raw('SUM(receipts.paid_amount) as total'))
            ->groupBy('receipts.payment_type')
            ->pluck('total', 'payment_type');

        $chequeCollect = 0;
        $pdchequeCollect = 0;

        foreach ($chequeCollections as $paymentType => $total) {
            $upperType = strtoupper(trim($paymentType));
            if ($upperType === 'CHEQUE') {
                $chequeCollect = $total ?? 0;
            } elseif ($upperType === 'PD CHEQUE') {
                $pdchequeCollect = $total ?? 0;
            }
        }

        $totalCrCollect = $totalCrCollect - $totalCrReturn;
        $totalCashCollect = $totalCashCollect - $totalCashReturn;

        // Total collection = CA + CR (cheques are already included in CA/CR totals)
        // Cheques are shown separately for reporting but are part of CA/CR based on customer_type
        $totalCollection = $totalCrCollect + $totalCashCollect;

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
        $fromDateForQuery = $fromDate;
        $toDateForQuery = $toDate;
        if (strlen($fromDateForQuery) == 10) {
            $fromDateForQuery .= ' 00:00:00';
        }
        if (strlen($toDateForQuery) == 10) {
            $toDateForQuery .= ' 23:59:59';
        }

        $query = DB::table('orders')
            ->where('type', 'INV')
            ->select('id', 'reference_no', 'order_date', 'net_amount', 'customer_code', 'customer_name', 'customer_id', 'status', 'agent_no')
            ->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery]);

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
