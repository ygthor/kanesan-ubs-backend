<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Territory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Check if user is admin or KBS
     */
    private function checkAccess()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Reports are only available for administrators and KBS users.');
        }
    }

    /**
     * Sales Report - Display orders where type='INV' OR type='CN'
     */
    public function salesReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate = $request->input('to_date', date('Y-m-d'));
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');

        // Ensure dates include full time range
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        $query = Order::whereIn('type', ['INV', 'CN'])
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->with('customer')
            ->orderBy('order_date', 'desc');

        // Filter by customer_id
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by agent_no
        if ($agentNo) {
            $query->where('agent_no', $agentNo);
        }

        // Filter by customer search
        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }

        $orders = $query->get();

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.sales-report', compact('orders', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'agents'));
    }

    /**
     * Transaction Report - Display all orders (all types)
     */
    public function transactionReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate = $request->input('to_date', date('Y-m-d'));
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $type = $request->input('type'); // Optional type filter

        // Ensure dates include full time range
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        $query = Order::whereBetween('order_date', [$fromDate, $toDate])
            ->with('customer')
            ->orderBy('order_date', 'desc');

        // Filter by type if provided
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by customer_id
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by agent_no
        if ($agentNo) {
            $query->where('agent_no', $agentNo);
        }

        // Filter by customer search
        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }

        $orders = $query->get();

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.transaction-report', compact('orders', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'type', 'agents'));
    }

    /**
     * Customer Report - Display all customers
     */
    public function customerReport(Request $request)
    {
        $this->checkAccess();
        
        $customerSearch = $request->input('customer_search');
        $customerType = $request->input('customer_type');
        $territoryId = $request->input('territory_id');

        $query = Customer::query();

        // Filter by customer search
        if ($customerSearch) {
            $query->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('name', 'like', "%{$customerSearch}%")
                  ->orWhere('company_name', 'like', "%{$customerSearch}%");
            });
        }

        // Filter by customer type
        if ($customerType) {
            $query->where('customer_type', $customerType);
        }

        // Filter by territory (territory is stored as area code string)
        if ($territoryId) {
            $territory = \App\Models\Territory::find($territoryId);
            if ($territory) {
                $query->where('territory', $territory->area);
            }
        }

        $customers = $query->orderBy('customer_code', 'asc')->get();

        return view('admin.reports.customer-report', compact('customers', 'customerSearch', 'customerType', 'territoryId'));
    }

    /**
     * Receipt Report - Display all receipts
     */
    public function receiptReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate = $request->input('to_date', date('Y-m-d'));
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $paymentType = $request->input('payment_type');

        // Ensure dates include full time range
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        $query = Receipt::whereNull('deleted_at')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->with('customer')
            ->orderBy('receipt_date', 'desc');

        // Filter by customer_id
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by payment type
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }

        // Filter by agent_no (through customer)
        if ($agentNo) {
            $query->whereHas('customer', function($q) use ($agentNo) {
                $q->where('agent_no', $agentNo);
            });
        }

        // Filter by customer search
        if ($customerSearch) {
            $query->whereHas('customer', function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('name', 'like', "%{$customerSearch}%")
                  ->orWhere('company_name', 'like', "%{$customerSearch}%");
            });
        }

        $receipts = $query->get();

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.receipt-report', compact('receipts', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'paymentType', 'agents'));
    }

    /**
     * Customer Balance Report - Receipts - (INV + CN) = Outstanding Balance
     */
    public function customerBalanceReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date', date('Y-01-01')); // Default to start of year
        $toDate = $request->input('to_date', date('Y-m-d'));
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');

        // Ensure dates include full time range
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        // Get all customers (or filtered)
        $customerQuery = Customer::query();

        if ($customerId) {
            $customerQuery->where('id', $customerId);
        }

        if ($customerSearch) {
            $customerQuery->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('name', 'like', "%{$customerSearch}%")
                  ->orWhere('company_name', 'like', "%{$customerSearch}%");
            });
        }

        if ($agentNo) {
            $customerQuery->where('agent_no', $agentNo);
        }

        $customers = $customerQuery->orderBy('customer_code', 'asc')->get();

        // Calculate balance for each customer
        $customerBalances = [];
        foreach ($customers as $customer) {
            // Total Receipts
            $totalReceipts = Receipt::where('customer_id', $customer->id)
                ->whereNull('deleted_at')
                ->whereBetween('receipt_date', [$fromDate, $toDate])
                ->sum('paid_amount');

            // Total INV (Debit)
            $totalInv = Order::where('customer_id', $customer->id)
                ->where('type', 'INV')
                ->whereBetween('order_date', [$fromDate, $toDate])
                ->sum('net_amount');

            // Total CN (Credit - reduces debt)
            $totalCn = Order::where('customer_id', $customer->id)
                ->where('type', 'CN')
                ->whereBetween('order_date', [$fromDate, $toDate])
                ->sum('net_amount');

            // Balance = Receipts - (INV - CN)
            // CN reduces debt, so: Receipts - INV + CN
            $balance = $totalReceipts - $totalInv + $totalCn;

            $customerBalances[] = [
                'customer' => $customer,
                'total_receipts' => $totalReceipts,
                'total_inv' => $totalInv,
                'total_cn' => $totalCn,
                'balance' => $balance,
            ];
        }

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.customer-balance-report', compact('customerBalances', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'agents'));
    }

    /**
     * Get customer balance detail (P&L data) for modal
     */
    public function getCustomerBalanceDetail(Request $request, $customerId)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date', date('Y-01-01'));
        $toDate = $request->input('to_date', date('Y-m-d'));

        // Ensure dates include full time range
        if (strlen($fromDate) == 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) == 10) {
            $toDate .= ' 23:59:59';
        }

        $customer = Customer::findOrFail($customerId);

        // Get all receipts
        $receipts = Receipt::where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->orderBy('receipt_date', 'asc')
            ->get();

        // Get all INV orders
        $invOrders = Order::where('customer_id', $customerId)
            ->where('type', 'INV')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->orderBy('order_date', 'asc')
            ->get();

        // Get all CN orders
        $cnOrders = Order::where('customer_id', $customerId)
            ->where('type', 'CN')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->orderBy('order_date', 'asc')
            ->get();

        // Build P&L data
        $plData = [];

        // Add receipts (Credit)
        foreach ($receipts as $receipt) {
            $plData[] = [
                'description' => 'RECEIPT ' . $receipt->receipt_no,
                'date' => $receipt->receipt_date,
                'credit' => $receipt->paid_amount,
                'debit' => 0,
                'type' => 'receipt',
            ];
        }

        // Add INV orders (Debit)
        foreach ($invOrders as $order) {
            $plData[] = [
                'description' => 'INV' . $order->reference_no,
                'date' => $order->order_date,
                'credit' => 0,
                'debit' => $order->net_amount,
                'type' => 'inv',
            ];
        }

        // Add CN orders (Credit - reduces debt)
        foreach ($cnOrders as $order) {
            $plData[] = [
                'description' => 'CN' . $order->reference_no,
                'date' => $order->order_date,
                'credit' => $order->net_amount,
                'debit' => 0,
                'type' => 'cn',
            ];
        }

        // Sort by date
        usort($plData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Calculate totals
        $totalCredit = collect($plData)->sum('credit');
        $totalDebit = collect($plData)->sum('debit');
        $balance = $totalCredit - $totalDebit;

        return response()->json([
            'customer' => $customer,
            'pl_data' => $plData,
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
            'balance' => $balance,
        ]);
    }

    /**
     * Get agents for filter dropdown
     */
    private function getAgents()
    {
        return User::select('id', 'name', 'username', 'email')
            ->where(function($query) {
                $query->where('username', '!=', 'KBS')
                      ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function($user) {
                return !$user->hasRole('admin');
            })
            ->values();
    }
}
