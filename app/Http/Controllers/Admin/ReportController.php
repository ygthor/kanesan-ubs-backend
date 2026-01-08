<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
     * Reports Index - List all available reports
     */
    public function index()
    {
        $this->checkAccess();
        
        $reports = [
            [
                'name' => 'Sales Report',
                'description' => 'Display INV & CN Orders & Receipt with sales summary and collection summary',
                'route' => route('admin.reports.sales'),
                'icon' => 'fas fa-chart-line',
            ],
            [
                'name' => 'Transaction Report',
                'description' => 'Display all orders (INV, DO, CN) with transaction details',
                'route' => route('admin.reports.transactions'),
                'icon' => 'fas fa-exchange-alt',
            ],
            [
                'name' => 'Customer Report',
                'description' => 'Display all customers with their details',
                'route' => route('admin.reports.customers'),
                'icon' => 'fas fa-users',
            ],
            [
                'name' => 'Receipt Report',
                'description' => 'Display all receipts with payment details',
                'route' => route('admin.reports.receipts'),
                'icon' => 'fas fa-receipt',
            ],
            [
                'name' => 'Customer Balance Report',
                'description' => 'Display customer balances (Receipts - INV + CN) with P&L detail view',
                'route' => route('admin.reports.customer-balance'),
                'icon' => 'fas fa-balance-scale',
            ],
        ];
        
        return view('admin.reports.index', compact('reports'));
    }

    /**
     * Sales Report - Display orders where type='INV' OR type='CN'
     */
    public function salesReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');

        $query = Order::whereIn('type', ['INV', 'CN']);

        // Apply date filter only if provided
        if ($fromDate && $toDate) {
            // Ensure dates include full time range
            if (strlen($fromDate) == 10) {
                $fromDateTime = $fromDate.' 00:00:00';
            }
            if (strlen($toDate) == 10) {
                $toDateTime = $toDate.' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDateTime, $toDateTime]);
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

        $orders = $query->with('customer')->orderBy('order_date', 'desc')->get();

        // Fetch receipts with same filters
        $receiptQuery = Receipt::whereNull('deleted_at');
        
        // Apply date filter only if provided
        if ($fromDateTime && $toDateTime) {
            $receiptCalcFromDate = $fromDateTime;
            $receiptCalcToDate = $toDateTime;
            if (strlen($receiptCalcFromDate) == 10) {
                $receiptCalcFromDate .= ' 00:00:00';
            }
            if (strlen($receiptCalcToDate) == 10) {
                $receiptCalcToDate .= ' 23:59:59';
            }
            $receiptQuery->whereBetween('receipt_date', [$receiptCalcFromDate, $receiptCalcToDate]);
        }
        
        // Filter by customer_id
        if ($customerId) {
            $receiptQuery->where('customer_id', $customerId);
        }
        
        // Filter by agent_no (through customer)
        if ($agentNo) {
            $receiptQuery->whereHas('customer', function($q) use ($agentNo) {
                $q->where('agent_no', $agentNo);
            });
        }
        
        // Filter by customer search
        if ($customerSearch) {
            $receiptQuery->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }
        
        $receipts = $receiptQuery->with('customer')->orderBy('receipt_date', 'desc')->get();

        // Calculate summary totals
        // Prepare date variables for calculations
        $calcFromDate = $fromDateTime;
        $calcToDate = $toDateTime;
        if ($calcFromDate && $calcToDate) {
            if (strlen($calcFromDate) == 10) {
                $calcFromDate .= ' 00:00:00';
            }
            if (strlen($calcToDate) == 10) {
                $calcToDate .= ' 23:59:59';
            }
        }

        // CA Sales = INV orders with customer_type 'Cash' or 'Cash Sales'
        $caSales = Order::whereIn('type', ['INV'])
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where(function($q) {
                $q->whereIn('customers.customer_type', ['Cash', 'Cash Sales']);
            });
        
        // Apply same filters as main query
        if ($calcFromDate && $calcToDate) {
            $caSales->whereBetween('orders.order_date', [$calcFromDate, $calcToDate]);
        }
        if ($customerId) {
            $caSales->where('orders.customer_id', $customerId);
        }
        if ($agentNo) {
            $caSales->where('orders.agent_no', $agentNo);
        }
        if ($customerSearch) {
            $caSales->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }
        $caSalesTotal = $caSales->sum('orders.net_amount') ?? 0;

        // CR Sales = INV orders with customer_type 'CREDITOR' or 'Creditor'
        $crSales = Order::whereIn('type', ['INV'])
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where(function($q) {
                $q->whereIn('customers.customer_type', ['CREDITOR', 'Creditor']);
            });
        
        // Apply same filters as main query
        if ($calcFromDate && $calcToDate) {
            $crSales->whereBetween('orders.order_date', [$calcFromDate, $calcToDate]);
        }
        if ($customerId) {
            $crSales->where('orders.customer_id', $customerId);
        }
        if ($agentNo) {
            $crSales->where('orders.agent_no', $agentNo);
        }
        if ($customerSearch) {
            $crSales->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }
        $crSalesTotal = $crSales->sum('orders.net_amount') ?? 0;

        // Return = CN orders (all CN orders are returns)
        $returns = OrderItem::leftJoin('orders', 'order_items.reference_no', '=', 'orders.reference_no');        
        $returns->where('type', 'CN');
        
        // Apply same filters as main query
        if ($calcFromDate && $calcToDate) {
            $returns->whereBetween('order_date', [$calcFromDate, $calcToDate]);
        }
        if ($customerId) {
            $returns->where('customer_id', $customerId);
        }
        if ($agentNo) {
            $returns->where('agent_no', $agentNo);
        }
        if ($customerSearch) {
            $returns->where(function($q) use ($customerSearch) {
                $q->where('customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customer_name', 'like', "%{$customerSearch}%");
            });
        }
        $returns->SelectRaw('
            SUM(IF(trade_return_is_good = 1, amount, 0)) as return_good,
            SUM(IF(trade_return_is_good = 0, amount, 0)) as return_bad,
            SUM(IF(trade_return_is_good = 1, 1, 0)) as return_good_count,
            SUM(IF(trade_return_is_good = 0, 1, 0)) as return_bad_count,
            SUM(amount) as total_amount
        ');
        $returns = $returns->first();
        
        $returnsTotal = $returns->total_amount ?? 0;
        $returnsGood = $returns->return_good ?? 0;
        $returnsBad = $returns->return_bad ?? 0;

        // Calculate totals
        $totalSales = $caSalesTotal + $crSalesTotal;
        $nettSales = $totalSales - $returnsTotal;

        // Calculate receipt collections by payment type
        $receiptCalcFromDate = $fromDateTime;
        $receiptCalcToDate = $toDateTime;
        if ($receiptCalcFromDate && $receiptCalcToDate) {
            if (strlen($receiptCalcFromDate) == 10) {
                $receiptCalcFromDate .= ' 00:00:00';
            }
            if (strlen($receiptCalcToDate) == 10) {
                $receiptCalcToDate .= ' 23:59:59';
            }
        }

        // Helper function to build receipt query with filters
        $buildReceiptQuery = function() use ($receiptCalcFromDate, $receiptCalcToDate, $customerId, $agentNo, $customerSearch) {
            $query = Receipt::whereNull('deleted_at');
            
            if ($receiptCalcFromDate && $receiptCalcToDate) {
                $query->whereBetween('receipt_date', [$receiptCalcFromDate, $receiptCalcToDate]);
            }
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
            if ($agentNo) {
                $query->whereHas('customer', function($q) use ($agentNo) {
                    $q->where('agent_no', $agentNo);
                });
            }
            if ($customerSearch) {
                $query->where(function($q) use ($customerSearch) {
                    $q->where('customer_code', 'like', "%{$customerSearch}%")
                      ->orWhere('customer_name', 'like', "%{$customerSearch}%");
                });
            }
            
            return $query;
        };

        // Get collections by payment type
        $cashCollection = $buildReceiptQuery()->where('payment_type', 'CASH')->sum('paid_amount') ?? 0;
        $ewalletCollection = $buildReceiptQuery()->where('payment_type', 'E-WALLET')->sum('paid_amount') ?? 0;
        $onlineTransferCollection = $buildReceiptQuery()->where('payment_type', 'ONLINE TRANSFER')->sum('paid_amount') ?? 0;
        $cardCollection = $buildReceiptQuery()->where('payment_type', 'CARD')->sum('paid_amount') ?? 0;
        $chequeCollection = $buildReceiptQuery()->where('payment_type', 'CHEQUE')->sum('paid_amount') ?? 0;
        $pdChequeCollection = $buildReceiptQuery()->where('payment_type', 'PD CHEQUE')->sum('paid_amount') ?? 0;
        
        $totalCollection = $cashCollection + $ewalletCollection + $onlineTransferCollection + $cardCollection + $chequeCollection + $pdChequeCollection;
        
        // Account Balance = Nett Sales - Total Collection
        $accountBalance = $nettSales - $totalCollection;

        // Get agents for filter dropdown
        $agents = $this->getAgents();
        request()->flash();
        
        return view('admin.reports.sales-report', compact('orders', 'receipts', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'agents', 'caSalesTotal', 'crSalesTotal', 'returnsTotal','returnsGood','returnsBad', 'totalSales', 'nettSales', 'cashCollection', 'ewalletCollection', 'onlineTransferCollection', 'cardCollection', 'chequeCollection', 'pdChequeCollection', 'totalCollection', 'accountBalance'));
    }

    /**
     * Transaction Report - Display all orders (all types)
     */
    public function transactionReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDateTime = $request->input('from_date');
        $toDateTime = $request->input('to_date');
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $type = $request->input('type'); // Optional type filter

        $query = Order::query();

        // Apply date filter only if provided
        if ($fromDateTime && $toDateTime) {
            // Ensure dates include full time range
            if (strlen($fromDateTime) == 10) {
                $fromDateTime .= ' 00:00:00';
            }
            if (strlen($toDateTime) == 10) {
                $toDateTime .= ' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDateTime, $toDateTime]);
        }

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

        $orders = $query->with('customer')->orderBy('order_date', 'desc')->get();

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.transaction-report', compact('orders', 'fromDateTime', 'toDateTime', 'customerId', 'agentNo', 'customerSearch', 'type', 'agents'));
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
        $agentNo = $request->input('agent_no');

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

        // Filter by agent_no
        if ($agentNo) {
            $query->where('agent_no', $agentNo);
        }

        $customers = $query->orderBy('customer_code', 'asc')->get();

        // Get agents for filter dropdown
        $agents = $this->getAgents();

        return view('admin.reports.customer-report', compact('customers', 'customerSearch', 'customerType', 'territoryId', 'agentNo', 'agents'));
    }

    /**
     * Receipt Report - Display all receipts
     */
    public function receiptReport(Request $request)
    {
        $this->checkAccess();
        
        $fromDateTime = $request->input('from_date');
        $toDateTime = $request->input('to_date');
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $paymentType = $request->input('payment_type');

        $query = Receipt::whereNull('deleted_at');

        // Apply date filter only if provided
        if ($fromDateTime && $toDateTime) {
            // Ensure dates include full time range
            if (strlen($fromDateTime) == 10) {
                $fromDateTime .= ' 00:00:00';
            }
            if (strlen($toDateTime) == 10) {
                $toDateTime .= ' 23:59:59';
            }
            $query->whereBetween('receipt_date', [$fromDateTime, $toDateTime]);
        }

        // Filter by customer_id
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by payment type (from receipts table, not customer)
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

        // Get receipts with customer relationship - payment_type comes from receipts table
        $receipts = $query->with('customer')->orderBy('receipt_date', 'desc')->get();

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
        
        $fromDate = $request->input('from_date'); // Optional
        $toDate = $request->input('to_date'); // Optional
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');

        // Set default dates if not provided
        if (!$fromDate) {
            $fromDate = date('Y-01-01');
        }
        if (!$toDate) {
            $toDate = date('Y-m-d');
        }

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
        
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Set default dates if not provided
        if (!$fromDate) {
            $fromDate = date('Y-01-01');
        }
        if (!$toDate) {
            $toDate = date('Y-m-d');
        }

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
     * Get order detail with items for modal (web route)
     */
    public function getOrderDetail($id)
    {
        $this->checkAccess();
        
        // Try to find by reference_no first, then by ID
        $order = Order::where('reference_no', $id)
            ->orWhere('id', $id)
            ->with('items.item', 'customer')
            ->first();
        
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
        }
        
        return response()->json([
            'error' => false,
            'data' => $order->toArray()
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
