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

        // Get user's name and username for filtering orders directly (unless user has full access)
        // Note: Orders can have agent_no stored as either user->name OR user->username
        // So we need to check both to match all orders created by this user
        $userName = null;
        $userUsername = null;
        // Get user's allowed customer codes for filtering receipts (unless user has full access)
        $allowedCustomerCodes = null;
        if ($user && !hasFullAccess()) {
            $userName = $user->name;
            $userUsername = $user->username;
            // For customers, check both name and username as agent_no
            $agentNos = array_filter([$userName, $userUsername]);
            if (!empty($agentNos)) {
                $allowedCustomerCodes = DB::table('customers')
                    ->whereIn('agent_no', $agentNos)
                    ->pluck('customer_code')
                    ->toArray();
            }
        }

        // Helper function to apply agent_no filter (checks both name and username)
        $applyAgentFilter = function($query) use ($userName, $userUsername) {
            if ($userName || $userUsername) {
                $query->where(function($q) use ($userName, $userUsername) {
                    if ($userName) {
                        $q->where('agent_no', $userName);
                    }
                    if ($userUsername) {
                        if ($userName) {
                            $q->orWhere('agent_no', $userUsername);
                        } else {
                            $q->where('agent_no', $userUsername);
                        }
                    }
                });
            }
        };

        // --- Sales ---
        // CA Sales: Cash Sales (type='CS')
        $caSalesQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'CS');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($caSalesQuery);
        $caSales = $caSalesQuery->sum('net_amount');

        // CR Sales: Credit Sales/Invoices (type='INV')
        $crSalesQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'INV');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($crSalesQuery);
        $crSales = $crSalesQuery->sum('net_amount');

        $totalSales = $caSales + $crSales;

        // Returns: Credit Notes (type='CN')
        $returnsQuery = DB::table('orders')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->where('type', 'CN');
        // Filter by agent_no directly on orders table (if user doesn't have full access)
        $applyAgentFilter($returnsQuery);
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

        $query->where('agent_no', $user->name);

        // Filter by agent: if user doesn't have full access, only show their own data
        // Users with full access can filter by any agent_no if provided
        // Users without full access are always restricted to their own agent_no
        // Note: Orders can have agent_no stored as either user->name OR user->username
        // So we need to check both to match all orders created by this user
        // if ($user && !hasFullAccess()) {
        //     // Always filter by logged-in user's agent_no (ignore any agent_no in request)
        //     $userName = $user->name;
        //     $userUsername = $user->username;
            
        //     if ($userName || $userUsername) {
        //         // Check if agent_no matches either name or username
        //         $query->where(function($q) use ($userName, $userUsername) {
        //             if ($userName) {
        //                 $q->where('agent_no', $userName);
        //             }
        //             if ($userUsername) {
        //                 if ($userName) {
        //                     $q->orWhere('agent_no', $userUsername);
        //                 } else {
                            
        //                 }
        //             }
        //         });
        //     } else {
        //         // User has no agent_no, return empty result
        //         return response()->json([]);
        //     }
        // } elseif ($agentNo) {
        //     // User has full access, allow filtering by provided agent_no
        //     $query->where('agent_no', $agentNo);
        // }

        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }

        $orders = $query->orderBy('order_date')->get();

        // Get user's name and username for filtering linked CN orders (if user doesn't have full access)
        // Orders can have agent_no stored as either name or username, so we need both
        $userName = ($user && !hasFullAccess()) ? $user->name : null;
        $userUsername = ($user && !hasFullAccess()) ? $user->username : null;

        // Calculate adjusted net amount for each invoice (deduct linked CN totals)
        $adjustedOrders = $orders->map(function ($order) use ($userName, $userUsername) {
            // Get linked CN orders for this invoice
            $linkedCNsQuery = DB::table('orders')
                ->where('credit_invoice_no', $order->reference_no)
                ->where('type', 'CN');
            
            // Filter linked CN orders by agent_no if user doesn't have full access
            // Check both name and username since orders can have either
            if ($userName || $userUsername) {
                $linkedCNsQuery->where(function($q) use ($userName, $userUsername) {
                    if ($userName) {
                        $q->where('agent_no', $userName);
                    }
                    if ($userUsername) {
                        if ($userName) {
                            $q->orWhere('agent_no', $userUsername);
                        } else {
                            $q->where('agent_no', $userUsername);
                        }
                    }
                });
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
