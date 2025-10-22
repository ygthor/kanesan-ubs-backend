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
        $user = auth()->user();
        
        // Validate request parameters for date range
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'branchId' => 'nullable|string', // Or 'integer' depending on what it is
        ]);

        // Determine date range. Default to the current month if not provided.
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        // Get user's allowed customer IDs (unless KBS user)
        $allowedCustomerIds = null;
        if ($user && !($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty dashboard
                return makeResponse(200, 'Dashboard summary retrieved successfully.', [
                    'totalRevenue' => 'RM 0.00',
                    'nettSales' => 'RM 0.00',
                    'totalCollections' => 'RM 0.00',
                    'outstandingDebt' => 'RM 0.00',
                    'inventoryValue' => 'RM 0.00',
                    'invoicesIssued' => '0',
                    'receiptsIssued' => '0',
                    'newCustomers' => '0',
                    'pendingOrders' => '0',
                    'lowStockItems' => '0',
                ]);
            }
        }

        // --- Calculations based on date range ---

        // Total Collections: Sum of all paid amounts from receipts in the date range.
        $totalCollectionsQuery = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $totalCollectionsQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $totalCollections = $totalCollectionsQuery->sum('paid_amount');

        // Nett Sales / Revenue: Sum of completed/shipped order totals in the date range.
        $nettSalesQuery = Order::whereIn('status', ['completed', 'shipped', 'processing'])
            ->whereBetween('order_date', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $nettSalesQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $nettSales = $nettSalesQuery->sum('net_amount');

        // Invoices Issued: Count of all orders in the date range.
        $invoicesQuery = Order::whereBetween('order_date', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $invoicesQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $invoicesIssued = $invoicesQuery->count();

        // Receipts Issued: Count of all receipts in the date range.
        $receiptsQuery = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $receiptsQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $receiptsIssued = $receiptsQuery->count();

        // New Customers: Count of customers created in the date range.
        $newCustomersQuery = Customer::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $newCustomersQuery->whereIn('id', $allowedCustomerIds);
        }
        $newCustomers = $newCustomersQuery->count();

        // Pending Orders: Count of orders with a 'pending' status in the date range.
        $pendingOrdersQuery = Order::where('status', 'pending')
            ->whereBetween('order_date', [$dateFrom, $dateTo]);
        if ($allowedCustomerIds) {
            $pendingOrdersQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        $pendingOrders = $pendingOrdersQuery->count();

        // --- Calculations NOT based on date range (total/current values) ---

        // Outstanding Debt: A simplified calculation of total order amounts minus total collections.
        $totalOrderedQuery = Order::query();
        $totalCollectedQuery = Receipt::query();
        
        if ($allowedCustomerIds) {
            $totalOrderedQuery->whereIn('customer_id', $allowedCustomerIds);
            $totalCollectedQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        
        $totalOrderedAmount = $totalOrderedQuery->sum('net_amount');
        $totalCollectedAmount = $totalCollectedQuery->sum('paid_amount');
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
