<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Icitem;
use App\Models\ItemTransaction;
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
        ]);

        // Determine date range. Default to the current month if not provided.
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

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
        
        // Build customer filter query (only filter by user's allowed customers if applicable)
        $customerFilterQuery = Customer::query();
        if ($allowedCustomerIds) {
            $customerFilterQuery->whereIn('id', $allowedCustomerIds);
        }
        $filteredCustomerIds = $customerFilterQuery->pluck('id')->filter()->toArray();
        $filteredCustomerCodes = $customerFilterQuery->pluck('customer_code')
            ->filter(function($code) {
                return !empty($code) && $code !== null;
            })
            ->values()
            ->toArray(); // Filter out null/empty codes and re-index array
        
        // Debug: Log the filtered customer codes to help diagnose
        \Log::info('Dashboard Query', [
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

        // Revenue: Sum of order grand amounts (gross + tax) in the date range.
        // Invoices are orders with type='INV', also include CB, CS, DN types
        $revenueQuery = Order::whereBetween('order_date', [$dateFrom, $dateTo])
            ->whereIn('type', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerIds)) {
            $revenueQuery->whereIn('customer_id', $filteredCustomerIds);
        }
        $revenue = $revenueQuery->sum('grand_amount');

        // Nett Sales: Sum of order net amounts (after discount) in the date range.
        $nettSalesQuery = Order::whereBetween('order_date', [$dateFrom, $dateTo])
            ->whereIn('type', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerIds)) {
            $nettSalesQuery->whereIn('customer_id', $filteredCustomerIds);
        }
        $nettSales = $nettSalesQuery->sum('net_amount');

        // Invoices Issued: Count of orders (invoices) in the date range.
        $invoicesQuery = Order::whereBetween('order_date', [$dateFrom, $dateTo])
            ->whereIn('type', ['INV','CB','CS','DN']);
        if (!empty($filteredCustomerIds)) {
            $invoicesQuery->whereIn('customer_id', $filteredCustomerIds);
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

        // Inventory Value: Total value of all items in stock.
        // Calculate current stock from ItemTransaction and multiply by PRICE from icitem table.
        $items = Icitem::select('ITEMNO', 'PRICE')->get();
        $inventoryValue = 0;
        $lowStockThreshold = 10;
        $lowStockItems = 0;
        
        foreach ($items as $item) {
            $currentStock = $this->calculateCurrentStock($item->ITEMNO);
            $price = (float)($item->PRICE ?? 0);
            $inventoryValue += $currentStock * $price;
            
            // Count low stock items (stock > 0 and < threshold)
            if ($currentStock > 0 && $currentStock < $lowStockThreshold) {
                $lowStockItems++;
            }
        }

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

    /**
     * Calculate current stock from transactions
     *
     * @param string $itemno
     * @return float
     */
    private function calculateCurrentStock($itemno)
    {
        // Sum all quantities from transactions
        $total = ItemTransaction::where('ITEMNO', $itemno)
            ->sum('quantity');

        // If no transactions, get from icitem.QTY
        if ($total === null) {
            $item = Icitem::find($itemno);
            return $item ? (float)($item->QTY ?? 0) : 0;
        }

        return (float)$total;
    }
}
