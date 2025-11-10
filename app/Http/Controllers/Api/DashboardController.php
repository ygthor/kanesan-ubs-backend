<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Artran;
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
            'state' => 'nullable|string', // Customer state filter
        ]);

        // Determine date range. Default to the current month if not provided.
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());
        
        // Get state filter
        $selectedState = $request->input('state');

        // Get user's allowed customer IDs (unless KBS user or admin role)
        $allowedCustomerIds = null;
        if ($user && !hasFullAccess()) {
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
        
        // Build customer filter query for state
        $customerFilterQuery = Customer::query();
        if ($allowedCustomerIds) {
            $customerFilterQuery->whereIn('id', $allowedCustomerIds);
        }
        if ($selectedState) {
            $customerFilterQuery->where('state', $selectedState);
        }
        $filteredCustomerIds = $customerFilterQuery->pluck('id')->filter()->toArray();
        $filteredCustomerCodes = $customerFilterQuery->pluck('customer_code')
            ->filter(function($code) {
                return !empty($code) && $code !== null;
            })
            ->values()
            ->toArray(); // Filter out null/empty codes and re-index array
        
        // If state filter is applied but no customers found, return empty dashboard
        if ($selectedState && empty($filteredCustomerIds)) {
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
        
        // Debug: Log the filtered customer codes to help diagnose
        \Log::info('Dashboard Query', [
            'selectedState' => $selectedState,
            'allowedCustomerIds' => $allowedCustomerIds ? count($allowedCustomerIds) : 'all',
            'filteredCustomerIds' => count($filteredCustomerIds),
            'filteredCustomerCodes' => count($filteredCustomerCodes),
            'sampleCodes' => array_slice($filteredCustomerCodes, 0, 5),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        // --- Calculations based on date range ---

        // Total Collections: Sum of all paid amounts from receipts in the date range.
        $totalCollectionsQuery = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo]);
        if (!empty($filteredCustomerIds)) {
            $totalCollectionsQuery->whereIn('customer_id', $filteredCustomerIds);
        }
        $totalCollections = $totalCollectionsQuery->sum('paid_amount');

        // Revenue: Sum of invoice grand amounts (gross + tax) in the date range.
        $revenueQuery = Artran::whereBetween('DATE', [$dateFrom, $dateTo])
            ->whereIn('TYPE', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerCodes)) {
            $revenueQuery->whereIn('CUSTNO', $filteredCustomerCodes);
        }
        $revenue = $revenueQuery->sum('GRAND_BIL');

        // Nett Sales: Sum of invoice net amounts (after discount) in the date range.
        $nettSalesQuery = Artran::whereBetween('DATE', [$dateFrom, $dateTo])
            ->whereIn('TYPE', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerCodes)) {
            $nettSalesQuery->whereIn('CUSTNO', $filteredCustomerCodes);
        }
        $nettSales = $nettSalesQuery->sum('NET_BIL');

        // Invoices Issued: Count of invoices in the date range.
        $invoicesQuery = Artran::whereBetween('DATE', [$dateFrom, $dateTo])
            ->whereIn('TYPE', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerCodes)) {
            $invoicesQuery->whereIn('CUSTNO', $filteredCustomerCodes);
        }
        $invoicesIssued = $invoicesQuery->count();

        // Receipts Issued: Count of all receipts in the date range.
        $receiptsQuery = Receipt::whereBetween('receipt_date', [$dateFrom, $dateTo]);
        if (!empty($filteredCustomerIds)) {
            $receiptsQuery->whereIn('customer_id', $filteredCustomerIds);
        }
        $receiptsIssued = $receiptsQuery->count();

        // New Customers: Count of customers created in the date range.
        $newCustomersQuery = Customer::whereBetween('created_at', [$dateFrom, $dateTo]);
        if (!empty($filteredCustomerIds)) {
            $newCustomersQuery->whereIn('id', $filteredCustomerIds);
        }
        $newCustomers = $newCustomersQuery->count();

        // Pending Orders: Count of orders with a 'pending' status in the date range.
        $pendingOrdersQuery = Order::where('status', 'pending')
            ->whereBetween('order_date', [$dateFrom, $dateTo]);
        if (!empty($filteredCustomerIds)) {
            $pendingOrdersQuery->whereIn('customer_id', $filteredCustomerIds);
        }
        $pendingOrders = $pendingOrdersQuery->count();

        // --- Calculations NOT based on date range (total/current values) ---

        // Outstanding Debt: A simplified calculation of total order amounts minus total collections.
        $totalOrderedQuery = Order::query();
        $totalCollectedQuery = Receipt::query();
        
        if (!empty($filteredCustomerIds)) {
            $totalOrderedQuery->whereIn('customer_id', $filteredCustomerIds);
            $totalCollectedQuery->whereIn('customer_id', $filteredCustomerIds);
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
            'totalRevenue' => 'RM ' . number_format($revenue, 2),
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
