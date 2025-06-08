<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use DB;

class DashboardController extends Controller
{
    /**
     * Retrieve dashboard summary data.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary(Request $request)
    {
        // Validate request parameters for date range
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'branchId' => 'nullable|string', // Or 'integer' depending on what it is
        ]);

        // Determine date range. Default to the current month if not provided.
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        // Note: The 'branchId' filter is not applied in these queries yet.
        // You would add ->where('branch_id', $request->input('branchId')) to each query if applicable.

        // --- Calculations based on date range ---

        // Total Collections: Sum of all paid amounts from receipts in the date range.
        $totalCollections = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo])->sum('paid_amount');

        // Nett Sales / Revenue: Sum of completed/shipped order totals in the date range.
        // This assumes 'completed' or 'shipped' are your final order statuses.
        $nettSales = Order::whereIn('status', ['completed', 'shipped', 'processing']) // Adjust statuses as needed
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total_amount');

        // Invoices Issued: Count of all orders in the date range.
        $invoicesIssued = Order::whereBetween('order_date', [$dateFrom, $dateTo])->count();

        // Receipts Issued: Count of all receipts in the date range.
        $receiptsIssued = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo])->count();

        // New Customers: Count of customers created in the date range.
        $newCustomers = Customer::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Pending Orders: Count of orders with a 'pending' status in the date range.
        $pendingOrders = Order::where('status', 'pending')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->count();

        // --- Calculations NOT based on date range (total/current values) ---

        // Outstanding Debt: A simplified calculation of total order amounts minus total collections.
        // A more complex ledger system would be needed for perfect accuracy.
        $totalOrderedAmount = Order::sum('total_amount');
        $totalCollectedAmount = Receipt::sum('paid_amount');
        $outstandingDebt = $totalOrderedAmount - $totalCollectedAmount;

        // Inventory Value: Total value of all products in stock. Requires `CurrentStock` column.
        // This is a more intensive query and should be used with caution on large datasets.
        $inventoryValue = Product::sum(DB::raw('Unit_price * CurrentStock'));

        // Low Stock Items: Count of products with stock below a certain threshold (e.g., 10).
        $lowStockThreshold = 10;
        $lowStockItems = Product::where('CurrentStock', '<', $lowStockThreshold)->where('CurrentStock', '>', 0)->count();

        // Prepare the data payload
        $data = [
            'totalRevenue' => 'RM ' . number_format($nettSales, 2),
            'nettSales' => 'RM ' . number_format($nettSales, 2),
            'totalCollections' => 'RM ' . number_format($totalCollections, 2),
            'outstandingDebt' => 'RM ' . number_format($outstandingDebt, 2),
            'inventoryValue' => 'RM ' . number_format($inventoryValue, 2),
            'invoicesIssued' => (string)$invoicesIssued,
            'receiptsIssued' => (string)$receiptsIssued,
            'newCustomers' => (string)$newCustomers,
            'pendingOrders' => (string)$pendingOrders,
            'lowStockItems' => (string)$lowStockItems,
        ];

        return makeResponse(200, 'Dashboard summary retrieved successfully.', $data);
    }
}
