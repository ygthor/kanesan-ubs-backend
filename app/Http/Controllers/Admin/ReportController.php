<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Territory;
use App\Services\BusinessReportService;
use App\Exports\GroupProductSalesByYearExport;
use App\Exports\GroupProductSalesByAgentExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
            [
                'name' => 'Group Product Sales Report By Year',
                'description' => 'Grouped product sales with monthly quantity columns',
                'route' => route('admin.reports.group-product-sales-year'),
                'icon' => 'fas fa-calendar-alt',
            ],
            [
                'name' => 'Group Product Sales Report By Agent',
                'description' => 'Grouped product sales with agent quantity columns',
                'route' => route('admin.reports.group-product-sales-agent'),
                'icon' => 'fas fa-user-friends',
            ],
        ];

        return view('admin.reports.index', compact('reports'));
    }

    /**
     * Group Product Sales Report By Year (Preview)
     */
    public function groupProductSalesByYearReport(Request $request)
    {
        $this->checkAccess();

        $filters = [
            'year' => (int) $request->input('year', now()->year),
            'agent_no' => $request->input('agent_no'),
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
        ];

        $report = $this->buildGroupProductSalesByYearData($filters);
        $agents = $this->getAgents();
        $groups = $this->getItemGroups();

        return view('admin.reports.group-product-sales-year-report', array_merge($report, [
            'filters' => $filters,
            'agents' => $agents,
            'groups' => $groups,
        ]));
    }

    /**
     * Group Product Sales Report By Agent (Preview)
     */
    public function groupProductSalesByAgentReport(Request $request)
    {
        $this->checkAccess();

        [$fromDate, $toDate, $datePreset] = $this->resolveDateRangeByPreset(
            $request->input('date_preset', 'this_month'),
            $request->input('from_date'),
            $request->input('to_date')
        );

        $filters = [
            'date_preset' => $datePreset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
            'period_label' => $this->getAgentPeriodLabel($datePreset, $fromDate, $toDate),
        ];

        $report = $this->buildGroupProductSalesByAgentData($filters);
        $groups = $this->getItemGroups();

        return view('admin.reports.group-product-sales-agent-report', array_merge($report, [
            'filters' => $filters,
            'groups' => $groups,
        ]));
    }

    /**
     * Group Product Sales Report By Year (PDF - Landscape)
     */
    public function exportGroupProductSalesByYearPdf(Request $request)
    {
        $this->checkAccess();

        $filters = [
            'year' => (int) $request->input('year', now()->year),
            'agent_no' => $request->input('agent_no'),
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
        ];

        $report = $this->buildGroupProductSalesByYearData($filters);

        $printedAt = now()->format('Y-m-d H:i:s');
        $filename = 'group_product_sales_by_year_' . $filters['year'] . '_' . now()->format('Ymd_His') . '.pdf';
        $pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $printedAt = '';

            public function Footer()
            {
                $this->SetY(-7);
                $this->SetFont('helvetica', '', 8);
                $this->Cell(0, 4, 'Printed at ' . $this->printedAt, 0, 0, 'L');
                $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->printedAt = $printedAt;
        $pdf->SetCreator('KBS System');
        $pdf->SetAuthor(auth()->user()->name ?? 'System');
        $pdf->SetTitle('Group Product Sales Report By Year');
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetAutoPageBreak(true, 9);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $this->renderGroupProductSalesByYearPdfCells($pdf, $report, $filters);

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Group Product Sales Report By Year (Excel)
     */
    public function exportGroupProductSalesByYearExcel(Request $request)
    {
        $this->checkAccess();

        $filters = [
            'year' => (int) $request->input('year', now()->year),
            'agent_no' => $request->input('agent_no'),
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
        ];

        $report = $this->buildGroupProductSalesByYearData($filters);
        $filename = 'group_product_sales_by_year_' . $filters['year'] . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new GroupProductSalesByYearExport(array_merge($report, ['filters' => $filters])), $filename);
    }

    /**
     * Group Product Sales Report By Agent (PDF - Portrait)
     */
    public function exportGroupProductSalesByAgentPdf(Request $request)
    {
        $this->checkAccess();

        [$fromDate, $toDate, $datePreset] = $this->resolveDateRangeByPreset(
            $request->input('date_preset', 'this_month'),
            $request->input('from_date'),
            $request->input('to_date')
        );

        $filters = [
            'date_preset' => $datePreset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
            'period_label' => $this->getAgentPeriodLabel($datePreset, $fromDate, $toDate),
        ];

        $report = $this->buildGroupProductSalesByAgentData($filters);

        $printedAt = now()->format('Y-m-d H:i:s');
        $filename = 'group_product_sales_by_agent_' . now()->format('Ymd_His') . '.pdf';
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $printedAt = '';

            public function Footer()
            {
                $this->SetY(-7);
                $this->SetFont('helvetica', '', 8);
                $this->Cell(0, 4, 'Printed at ' . $this->printedAt, 0, 0, 'L');
                $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->printedAt = $printedAt;
        $pdf->SetCreator('KBS System');
        $pdf->SetAuthor(auth()->user()->name ?? 'System');
        $pdf->SetTitle('Group Product Sales Report By Agent');
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetAutoPageBreak(true, 9);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $this->renderGroupProductSalesByAgentPdfCells($pdf, $report, $filters);

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Group Product Sales Report By Agent (Excel)
     */
    public function exportGroupProductSalesByAgentExcel(Request $request)
    {
        $this->checkAccess();

        [$fromDate, $toDate, $datePreset] = $this->resolveDateRangeByPreset(
            $request->input('date_preset', 'this_month'),
            $request->input('from_date'),
            $request->input('to_date')
        );

        $filters = [
            'date_preset' => $datePreset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'product_search' => trim((string) $request->input('product_search', '')),
            'group' => trim((string) $request->input('group', '')),
            'period_label' => $this->getAgentPeriodLabel($datePreset, $fromDate, $toDate),
        ];

        $report = $this->buildGroupProductSalesByAgentData($filters);
        $filename = 'group_product_sales_by_agent_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new GroupProductSalesByAgentExport(array_merge($report, ['filters' => $filters])), $filename);
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
            // Ensure dates include full time range for database query
            $fromDateTime = $fromDate;
            $toDateTime = $toDate;
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
        if ($fromDate && $toDate) {
            $receiptCalcFromDate = $fromDate;
            $receiptCalcToDate = $toDate;
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
        $calcFromDate = $fromDate;
        $calcToDate = $toDate;
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
                $q->whereIn('customers.customer_type', ['Cash', 'Cash Sales', 'CASH']);
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
        $receiptCalcFromDate = $fromDate;
        $receiptCalcToDate = $toDate;
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

        $totalCollectionByPaymentType = $cashCollection + $ewalletCollection + $onlineTransferCollection + $cardCollection + $chequeCollection + $pdChequeCollection;

        // --- Collections by Customer Type (for verification) ---
        // This matches the API/mobile app calculation logic

        // CA Collection: All receipts from Cash customers (regardless of payment type)
        $caCollectionQuery = Receipt::whereNull('deleted_at')
            ->join('customers', 'receipts.customer_id', '=', 'customers.id')
            ->whereIn('customers.customer_type', ['Cash', 'CASH']);

        if ($receiptCalcFromDate && $receiptCalcToDate) {
            $caCollectionQuery->whereBetween('receipts.receipt_date', [$receiptCalcFromDate, $receiptCalcToDate]);
        }
        if ($customerId) {
            $caCollectionQuery->where('receipts.customer_id', $customerId);
        }
        if ($agentNo) {
            $caCollectionQuery->where('customers.agent_no', $agentNo);
        }
        if ($customerSearch) {
            $caCollectionQuery->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }
        $caCollection = $caCollectionQuery->sum('receipts.paid_amount') ?? 0;

        // Negative CASH invoices: linked CN total exceeds invoice net amount
        $cnTotalsSubQuery = Order::from('orders as cn')
            ->select('cn.credit_invoice_no', DB::raw('SUM(cn.net_amount) as total_cn_amount'))
            ->where('cn.type', 'CN')
            ->groupBy('cn.credit_invoice_no');

        $negativeCashOrderQuery = Order::from('orders as inv')
            ->join('customers', 'inv.customer_id', '=', 'customers.id')
            ->leftJoinSub($cnTotalsSubQuery, 'cn_totals', function ($join) {
                $join->on('inv.reference_no', '=', 'cn_totals.credit_invoice_no');
            })
            ->where('inv.type', 'INV')
            ->where('inv.net_amount', '>', 0)
            ->whereIn('customers.customer_type', ['Cash', 'CASH']);

        if ($calcFromDate && $calcToDate) {
            $negativeCashOrderQuery->whereBetween('inv.order_date', [$calcFromDate, $calcToDate]);
        }
        if ($customerId) {
            $negativeCashOrderQuery->where('inv.customer_id', $customerId);
        }
        if ($agentNo) {
            $negativeCashOrderQuery->where('inv.agent_no', $agentNo);
        }
        if ($customerSearch) {
            $negativeCashOrderQuery->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }

        $totalNegativeCashOrder = $negativeCashOrderQuery
            ->selectRaw('SUM(CASE WHEN (COALESCE(inv.net_amount, 0) - COALESCE(cn_totals.total_cn_amount, 0)) < 0 THEN ABS(COALESCE(inv.net_amount, 0) - COALESCE(cn_totals.total_cn_amount, 0)) ELSE 0 END) as total_negative_cash_order')
            ->value('total_negative_cash_order') ?? 0;

        // CR Collection: All receipts from CREDITOR customers (regardless of payment type)
        $crCollectionQuery = Receipt::whereNull('deleted_at')
            ->join('customers', 'receipts.customer_id', '=', 'customers.id')
            ->whereIn('customers.customer_type', ['CREDITOR', 'Creditor']);

        if ($receiptCalcFromDate && $receiptCalcToDate) {
            $crCollectionQuery->whereBetween('receipts.receipt_date', [$receiptCalcFromDate, $receiptCalcToDate]);
        }
        if ($customerId) {
            $crCollectionQuery->where('receipts.customer_id', $customerId);
        }
        if ($agentNo) {
            $crCollectionQuery->where('customers.agent_no', $agentNo);
        }
        if ($customerSearch) {
            $crCollectionQuery->where(function($q) use ($customerSearch) {
                $q->where('customers.customer_code', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.name', 'like', "%{$customerSearch}%")
                  ->orWhere('customers.company_name', 'like', "%{$customerSearch}%");
            });
        }
        $crCollection = $crCollectionQuery->sum('receipts.paid_amount') ?? 0;

        $BusinessReportService = new BusinessReportService();
        $returnsInfo = $BusinessReportService->getTradeReturns([
            'from_date' => $calcFromDate,
            'to_date' => $calcToDate,
            'agent_no' => $agentNo,
            'customer_id' => $customerId,
        ]);

        $caReturns = $returnsInfo['Cash_withoutInv'];
        $crReturns = 0; // CUSTOMER SAID CR NO NEED RETURN

        // Calculate nett collections (matching API logic)
        $caCollectionNett = $caCollection - $caReturns - $totalNegativeCashOrder;
        $crCollectionNett = $crCollection - $crReturns;
        $totalCollectionByCustomerType = $caCollectionNett + $crCollectionNett;

        // Account Balance = Nett Sales - Total Collection (by customer type)
        $accountBalance = $nettSales - $totalCollectionByCustomerType;

        // Get agents for filter dropdown
        $agents = $this->getAgents();
        request()->flash();

        return view('admin.reports.sales-report', compact(
            'orders', 'receipts', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'agents',
            'caSalesTotal', 'crSalesTotal', 'returnsTotal', 'returnsGood', 'returnsBad', 'totalSales', 'nettSales',
            'cashCollection', 'ewalletCollection', 'onlineTransferCollection', 'cardCollection', 'chequeCollection', 'pdChequeCollection', 'totalCollectionByPaymentType',
            'caCollection', 'crCollection', 'caReturns', 'crReturns', 'totalNegativeCashOrder', 'caCollectionNett', 'crCollectionNett', 'totalCollectionByCustomerType',
            'accountBalance','returnsInfo'
        ));
    }

    /**
     * Transaction Report - Display all orders (all types)
     */
    public function transactionReport(Request $request)
    {
        $this->checkAccess();

        $fromDate = $request->input('from_date');
        $toDate= $request->input('to_date');
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $type = $request->input('type'); // Optional type filter

        $query = Order::query();

        // Apply date filter only if provided
        if ($fromDate && $toDate) {
            // Ensure dates include full time range
            $fromDateTime = $fromDate;
            $toDateTime = $toDate;
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
        $agentNo = $request->input('agent_no');

        $query = Customer::query();

        // Filter by customer search (supports wildcards: * = any, ? = single)
        if ($customerSearch) {
            $pattern = trim($customerSearch);
            $pattern = str_replace(['*', '?'], ['%', '_'], $pattern);
            if (strpos($pattern, '%') === false && strpos($pattern, '_') === false) {
                $pattern = '%' . $pattern . '%';
            }
            $query->where(function($q) use ($pattern) {
                $q->where('customer_code', 'like', $pattern)
                  ->orWhere('name', 'like', $pattern)
                  ->orWhere('company_name', 'like', $pattern);
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
     * Update modification date (updated_at) for selected customers to trigger sync.
     */
    public function updateCustomerModificationDate(Request $request)
    {
        $this->checkAccess();

        $customerIds = $request->input('customer_ids', []);

        if (empty($customerIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No customers selected.'
            ], 400);
        }

        try {
            $updatedCustomers = Customer::whereIn('id', $customerIds)
                ->update(['updated_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated modification date for {$updatedCustomers} customer(s).",
                'updated_customers' => $updatedCustomers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receipt Report - Display all receipts
     */
    public function receiptReport(Request $request)
    {
        $this->checkAccess();

        $fromDateTime = $request->input('from_date');
        $toDateTime = $request->input('to_date');
        $fromDate = $fromDateTime;
        $toDate = $toDateTime;
        $customerId = $request->input('customer_id');
        $agentNo = $request->input('agent_no');
        $customerSearch = $request->input('customer_search');
        $paymentType = $request->input('payment_type');
        $customerType = $request->input('customer_type');

        $query = Receipt::whereNull('deleted_at');

        // Apply date filter only if provided
        if ($fromDateTime && $toDateTime) {
            // Ensure dates include full time range
            $fromDateForQuery = $fromDateTime;
            $toDateForQuery = $toDateTime;
            if (strlen($fromDateForQuery) == 10) {
                $fromDateForQuery .= ' 00:00:00';
            }
            if (strlen($toDateForQuery) == 10) {
                $toDateForQuery .= ' 23:59:59';
            }
            $query->whereBetween('receipt_date', [$fromDateForQuery, $toDateForQuery]);
        }

        // Filter by customer_id
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by payment type (from receipts table, not customer)
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }

        // Filter by customer type (through customer)
        if ($customerType) {
            $query->whereHas('customer', function($q) use ($customerType) {
                if ($customerType === 'Cash') {
                    $q->whereIn('customer_type', ['Cash', 'CASH']);
                } elseif ($customerType === 'CREDITOR') {
                    $q->whereIn('customer_type', ['CREDITOR', 'Creditor']);
                } elseif ($customerType === 'Cash Sales') {
                    $q->whereIn('customer_type', ['Cash Sales', 'CASH SALES']);
                } else {
                    $q->where('customer_type', $customerType);
                }
            });
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

        return view('admin.reports.receipt-report', compact('receipts', 'fromDate', 'toDate', 'customerId', 'agentNo', 'customerSearch', 'paymentType', 'customerType', 'agents'));
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

        // Ensure dates include full time range for queries
        $fromDateForQuery = $fromDate;
        $toDateForQuery = $toDate;
        if (strlen($fromDateForQuery) == 10) {
            $fromDateForQuery .= ' 00:00:00';
        }
        if (strlen($toDateForQuery) == 10) {
            $toDateForQuery .= ' 23:59:59';
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
                ->whereBetween('receipt_date', [$fromDateForQuery, $toDateForQuery])
                ->sum('paid_amount');

            // Total INV (Debit)
            $totalInv = Order::where('customer_id', $customer->id)
                ->where('type', 'INV')
                ->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery])
                ->sum('net_amount');

            // Total CN (Credit - reduces debt)
            $totalCn = Order::where('customer_id', $customer->id)
                ->where('type', 'CN')
                ->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery])
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

        // Ensure dates include full time range for queries
        $fromDateForQuery = $fromDate;
        $toDateForQuery = $toDate;
        if (strlen($fromDateForQuery) == 10) {
            $fromDateForQuery .= ' 00:00:00';
        }
        if (strlen($toDateForQuery) == 10) {
            $toDateForQuery .= ' 23:59:59';
        }

        $customer = Customer::findOrFail($customerId);

        // Get all receipts
        $receipts = Receipt::where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('receipt_date', [$fromDateForQuery, $toDateForQuery])
            ->orderBy('receipt_date', 'asc')
            ->get();

        // Get all INV orders
        $invOrders = Order::where('customer_id', $customerId)
            ->where('type', 'INV')
            ->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery])
            ->orderBy('order_date', 'asc')
            ->get();

        // Get all CN orders
        $cnOrders = Order::where('customer_id', $customerId)
            ->where('type', 'CN')
            ->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery])
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

    private function buildGroupProductSalesByYearData(array $filters): array
    {
        $year = max(2000, (int) ($filters['year'] ?? now()->year));
        $startDate = sprintf('%04d-01-01 00:00:00', $year);
        $endDate = sprintf('%04d-12-31 23:59:59', $year);

        // 1) Base list: include all products from master item table
        $baseItemsQuery = DB::table('icitem as i')
            ->selectRaw("
                COALESCE(i.`GROUP` COLLATE utf8mb4_unicode_ci, '') as item_group,
                i.ITEMNO COLLATE utf8mb4_unicode_ci as item_code,
                COALESCE(NULLIF(i.DESP COLLATE utf8mb4_unicode_ci, ''), i.ITEMNO COLLATE utf8mb4_unicode_ci) as item_description
            ")
            ->whereNotNull('i.GROUP')
            ->whereRaw("TRIM(i.`GROUP`) != ''");

        if (!empty($filters['group'])) {
            $baseItemsQuery->whereRaw(
                "COALESCE(i.`GROUP` COLLATE utf8mb4_unicode_ci, '') = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci",
                [$filters['group']]
            );
        }
        if (!empty($filters['product_search'])) {
            $search = $filters['product_search'];
            $baseItemsQuery->where(function ($q) use ($search) {
                $q->whereRaw("i.ITEMNO COLLATE utf8mb4_unicode_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci", ["%{$search}%"])
                    ->orWhereRaw("COALESCE(i.DESP, '') COLLATE utf8mb4_unicode_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci", ["%{$search}%"]);
            });
        }

        $baseItems = $baseItemsQuery
            ->orderByRaw("COALESCE(i.`GROUP`, '')")
            ->orderBy('i.ITEMNO')
            ->get();

        // 2) Sales qty aggregated by product + month
        $aggQuery = DB::table('order_items as oi')
            ->join('orders as o', 'o.reference_no', '=', 'oi.reference_no')
            ->where('o.type', 'INV')
            ->whereBetween('o.order_date', [$startDate, $endDate]);

        if (!empty($filters['agent_no'])) {
            $aggQuery->where('o.agent_no', $filters['agent_no']);
        }

        $rows = $aggQuery
            ->selectRaw("
                oi.product_no COLLATE utf8mb4_unicode_ci as item_code,
                MONTH(o.order_date) as month_no,
                SUM(COALESCE(oi.quantity, 0)) as qty
            ")
            ->groupByRaw("oi.product_no COLLATE utf8mb4_unicode_ci, MONTH(o.order_date)")
            ->get();

        $months = [
            1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN',
            7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC',
        ];

        $items = [];
        $itemKeyByCode = [];
        $monthTotals = array_fill(1, 12, 0);

        foreach ($baseItems as $baseItem) {
            $itemKey = $baseItem->item_group . '|' . $baseItem->item_code;
            $items[$itemKey] = [
                'item_group' => $baseItem->item_group,
                'item_code' => $baseItem->item_code,
                'item_description' => $baseItem->item_description,
                'months' => array_fill(1, 12, 0),
                'total' => 0,
            ];
            $itemKeyByCode[(string) $baseItem->item_code] = $itemKey;
        }

        foreach ($rows as $row) {
            $code = (string) $row->item_code;
            if (!isset($itemKeyByCode[$code])) {
                continue;
            }
            $itemKey = $itemKeyByCode[$code];
            $monthNo = (int) $row->month_no;
            $qty = (float) $row->qty;
            $items[$itemKey]['months'][$monthNo] += $qty;
            $items[$itemKey]['total'] += $qty;
            $monthTotals[$monthNo] += $qty;
        }

        $grandTotal = array_sum($monthTotals);
        $groupedItems = collect($items)->groupBy('item_group');

        return [
            'months' => $months,
            'groupedItems' => $groupedItems,
            'monthTotals' => $monthTotals,
            'grandTotal' => $grandTotal,
            'year' => $year,
        ];
    }

    private function buildGroupProductSalesByAgentData(array $filters): array
    {
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $fromDateForQuery = strlen($fromDate) === 10 ? $fromDate . ' 00:00:00' : $fromDate;
        $toDateForQuery = strlen($toDate) === 10 ? $toDate . ' 23:59:59' : $toDate;

        // 1) Base list: include all products from master item table
        $baseItemsQuery = DB::table('icitem as i')
            ->selectRaw("
                COALESCE(i.`GROUP` COLLATE utf8mb4_unicode_ci, '') as item_group,
                i.ITEMNO COLLATE utf8mb4_unicode_ci as item_code,
                COALESCE(NULLIF(i.DESP COLLATE utf8mb4_unicode_ci, ''), i.ITEMNO COLLATE utf8mb4_unicode_ci) as item_description
            ")
            ->whereNotNull('i.GROUP')
            ->whereRaw("TRIM(i.`GROUP`) != ''");

        if (!empty($filters['group'])) {
            $baseItemsQuery->whereRaw(
                "COALESCE(i.`GROUP` COLLATE utf8mb4_unicode_ci, '') = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci",
                [$filters['group']]
            );
        }
        if (!empty($filters['product_search'])) {
            $search = $filters['product_search'];
            $baseItemsQuery->where(function ($q) use ($search) {
                $q->whereRaw("i.ITEMNO COLLATE utf8mb4_unicode_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci", ["%{$search}%"])
                    ->orWhereRaw("COALESCE(i.DESP, '') COLLATE utf8mb4_unicode_ci LIKE CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci", ["%{$search}%"]);
            });
        }

        $baseItems = $baseItemsQuery
            ->orderByRaw("COALESCE(i.`GROUP`, '')")
            ->orderBy('i.ITEMNO')
            ->get();

        // 2) Sales qty aggregated by product + agent
        $aggQuery = DB::table('order_items as oi')
            ->join('orders as o', 'o.reference_no', '=', 'oi.reference_no')
            ->where('o.type', 'INV')
            ->whereBetween('o.order_date', [$fromDateForQuery, $toDateForQuery]);

        $rows = $aggQuery
            ->selectRaw("
                oi.product_no COLLATE utf8mb4_unicode_ci as item_code,
                COALESCE(NULLIF(TRIM(o.agent_no) COLLATE utf8mb4_unicode_ci, ''), 'N/A') as agent_no,
                SUM(COALESCE(oi.quantity, 0)) as qty
            ")
            ->groupByRaw("
                oi.product_no COLLATE utf8mb4_unicode_ci,
                COALESCE(NULLIF(TRIM(o.agent_no) COLLATE utf8mb4_unicode_ci, ''), 'N/A')
            ")
            ->get();

        $agentColumns = $rows->pluck('agent_no')->unique()->sort()->values()->all();
        $items = [];
        $itemKeyByCode = [];
        $agentTotals = [];
        foreach ($agentColumns as $agent) {
            $agentTotals[$agent] = 0;
        }

        foreach ($baseItems as $baseItem) {
            $itemKey = $baseItem->item_group . '|' . $baseItem->item_code;
            $items[$itemKey] = [
                'item_group' => $baseItem->item_group,
                'item_code' => $baseItem->item_code,
                'item_description' => $baseItem->item_description,
                'agents' => array_fill_keys($agentColumns, 0),
                'total' => 0,
            ];
            $itemKeyByCode[(string) $baseItem->item_code] = $itemKey;
        }

        foreach ($rows as $row) {
            $code = (string) $row->item_code;
            if (!isset($itemKeyByCode[$code])) {
                continue;
            }
            $itemKey = $itemKeyByCode[$code];
            $agent = $row->agent_no;
            $qty = (float) $row->qty;
            if (!array_key_exists($agent, $items[$itemKey]['agents'])) {
                $items[$itemKey]['agents'][$agent] = 0;
                if (!isset($agentTotals[$agent])) {
                    $agentTotals[$agent] = 0;
                }
            }
            $items[$itemKey]['agents'][$agent] += $qty;
            $items[$itemKey]['total'] += $qty;
            $agentTotals[$agent] += $qty;
        }

        $grandTotal = array_sum($agentTotals);
        $groupedItems = collect($items)->groupBy('item_group');

        return [
            'groupedItems' => $groupedItems,
            'agentColumns' => $agentColumns,
            'agentTotals' => $agentTotals,
            'grandTotal' => $grandTotal,
            'fromDate' => substr($fromDate, 0, 10),
            'toDate' => substr($toDate, 0, 10),
            'periodLabel' => $filters['period_label'] ?? 'CUSTOM',
        ];
    }

    private function getItemGroups()
    {
        return DB::table('icitem')
            ->selectRaw('DISTINCT COALESCE(`GROUP`, \'\') as group_name')
            ->whereNotNull('GROUP')
            ->whereRaw('TRIM(`GROUP`) != \'\'')
            ->orderBy('group_name')
            ->pluck('group_name');
    }

    private function renderGroupProductSalesByAgentPdfCells(\TCPDF $pdf, array $report, array $filters): void
    {
        $groupedItems = $report['groupedItems'];
        $agentColumns = $report['agentColumns'];
        $agentTotals = $report['agentTotals'];
        $fromDate = $report['fromDate'];
        $toDate = $report['toDate'];
        $grandTotal = $report['grandTotal'];

        $formatQty = function ($qty) {
            $v = (float) $qty;
            if (abs($v - round($v)) < 0.00001) {
                return (string) (int) round($v);
            }
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        };

        $agentHeaderColors = [
            [0, 255, 51],   // green
            [255, 0, 255],  // magenta
            [0, 229, 255],  // cyan
            [255, 176, 0],  // orange
            [95, 143, 220], // blue
        ];

        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colCode = 25.0;
        $colDesc = 94.0;
        $remaining = max(40, $pageWidth - $colCode - $colDesc);
        $agentCount = max(1, count($agentColumns));
        $colOther = $remaining / ($agentCount + 1); // +1 total column
        $colTotal = $colOther;

        $drawTableHeader = function () use (
            $pdf,
            $fromDate,
            $toDate,
            $agentColumns,
            $agentTotals,
            $grandTotal,
            $report,
            $formatQty,
            $agentHeaderColors,
            $colCode,
            $colDesc,
            $colOther,
            $colTotal
        ) {
            $year = (int) date('Y', strtotime($fromDate));
            $periodLabel = $report['periodLabel'] ?? 'CUSTOM';

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA', 0, 1, 'C');
            $pdf->Cell(0, 6, 'GROUP PRODUCT SALES REPORT - YEAR ' . $year, 0, 1, 'C');
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, date('d/m/Y', strtotime($fromDate)) . ' - ' . date('d/m/Y', strtotime($toDate)), 0, 1, 'C');
            $pdf->Ln(1);

            // Totals row with period badge at right
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($colCode, 6, '', 0, 0, 'C', true);
            $pdf->Cell($colDesc, 6, '', 0, 0, 'C', true);
            foreach ($agentColumns as $agent) {
                $pdf->Cell($colOther, 6, $formatQty($agentTotals[$agent] ?? 0), 0, 0, 'C', true);
            }
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($colTotal, 6, $periodLabel, 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            // Row: CODE / ITEM DESCRIPTION / QTY SOLD span
            $pdf->SetFont('helvetica', 'BI', 10);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($colCode, 7, 'CODE', 1, 0, 'C', true);
            $pdf->Cell($colDesc, 7, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell($colOther * count($agentColumns), 7, 'QTY SOLD', 1, 0, 'C', true);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($colTotal, 7, '', 1, 1, 'C', true);

            // Agent headers row
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($colCode, 10, '', 1, 0, 'C', true);
            $pdf->Cell($colDesc, 10, '', 1, 0, 'C', true);
            foreach ($agentColumns as $idx => $agent) {
                $c = $agentHeaderColors[$idx % count($agentHeaderColors)];
                $pdf->SetFillColor($c[0], $c[1], $c[2]);
                $pdf->Cell($colOther, 10, $agent, 1, 0, 'C', true);
            }
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($colTotal, 10, 'TOTAL', 1, 1, 'C', true);
        };

        $ensureSpace = function ($neededHeight) use ($pdf, $drawTableHeader) {
            $bottomLimit = $pdf->getPageHeight() - $pdf->getMargins()['bottom'];
            if (($pdf->GetY() + $neededHeight) > $bottomLimit) {
                $pdf->AddPage();
                $drawTableHeader();
            }
        };

        $drawTableHeader();

        foreach ($groupedItems as $groupName => $items) {
            $ensureSpace(7);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($colCode + $colDesc + ($colOther * count($agentColumns)) + $colTotal, 7, 'Group :' . $groupName, 1, 1, 'L', true);

            foreach ($items as $item) {
                $ensureSpace(6);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Cell($colCode, 6, (string) $item['item_code'], 1, 0, 'L', true);
                $pdf->Cell($colDesc, 6, (string) $item['item_description'], 1, 0, 'L', true);

                foreach ($agentColumns as $agent) {
                    $pdf->Cell($colOther, 6, $formatQty($item['agents'][$agent] ?? 0), 1, 0, 'C', true);
                }

                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell($colTotal, 6, $formatQty($item['total'] ?? 0), 1, 1, 'C', true);
            }

            $ensureSpace(6);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($colCode + $colDesc + ($colOther * count($agentColumns)) + $colTotal, 6, '', 1, 1, 'L', true);
        }

        // Grand total footer row
        $ensureSpace(7);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($colCode + $colDesc, 7, 'GRAND TOTAL', 1, 0, 'R', true);
        foreach ($agentColumns as $agent) {
            $pdf->Cell($colOther, 7, $formatQty($agentTotals[$agent] ?? 0), 1, 0, 'C', true);
        }
        $pdf->Cell($colTotal, 7, $formatQty($grandTotal), 1, 1, 'C', true);
    }

    private function resolveDateRangeByPreset(?string $preset, ?string $fromDate, ?string $toDate): array
    {
        $allowed = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'custom'];
        $preset = in_array($preset, $allowed, true) ? $preset : 'this_month';
        $now = Carbon::now();

        switch ($preset) {
            case 'this_month':
                return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), $preset];
            case 'last_month':
                $lastMonth = $now->copy()->subMonthNoOverflow();
                return [$lastMonth->copy()->startOfMonth()->toDateString(), $lastMonth->copy()->endOfMonth()->toDateString(), $preset];
            case 'this_quarter':
                return [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString(), $preset];
            case 'last_quarter':
                $lastQuarter = $now->copy()->subQuarter();
                return [$lastQuarter->copy()->startOfQuarter()->toDateString(), $lastQuarter->copy()->endOfQuarter()->toDateString(), $preset];
            case 'this_year':
                return [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString(), $preset];
            case 'last_year':
                $lastYear = $now->copy()->subYear();
                return [$lastYear->copy()->startOfYear()->toDateString(), $lastYear->copy()->endOfYear()->toDateString(), $preset];
            case 'custom':
            default:
                $from = $fromDate ?: $now->copy()->startOfMonth()->toDateString();
                $to = $toDate ?: $now->copy()->toDateString();
                return [$from, $to, 'custom'];
        }
    }

    private function getAgentPeriodLabel(string $preset, string $fromDate, string $toDate): string
    {
        $from = Carbon::parse($fromDate);

        return match ($preset) {
            'this_month', 'last_month' => strtoupper($from->format('M')),
            'this_quarter', 'last_quarter' => $from->format('Y') . 'Q' . $from->quarter,
            'this_year', 'last_year' => $from->format('Y'),
            default => 'CUSTOM',
        };
    }

    private function renderGroupProductSalesByYearPdfCells(\TCPDF $pdf, array $report, array $filters): void
    {
        $groupedItems = $report['groupedItems'];
        $months = $report['months'];
        $monthTotals = $report['monthTotals'];
        $grandTotal = $report['grandTotal'];
        $year = $report['year'];

        $formatQty = function ($qty) {
            $v = (float) $qty;
            if (abs($v - round($v)) < 0.00001) {
                return (string) (int) round($v);
            }
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        };

        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colCode = 30.0;
        $colDesc = 88.0;
        $monthCount = 13; // Jan-Dec + Total
        $colMonth = max(9.5, ($pageWidth - $colCode - $colDesc) / $monthCount);
        $tableWidth = $colCode + $colDesc + ($colMonth * $monthCount);

        $agentLabel = trim((string) ($filters['agent_no'] ?? ''));
        $agentLabel = $agentLabel !== '' ? $agentLabel : 'ALL AGENTS';

        $drawHeader = function () use (
            $pdf,
            $year,
            $months,
            $monthCount,
            $monthTotals,
            $grandTotal,
            $formatQty,
            $colCode,
            $colDesc,
            $colMonth,
            $tableWidth,
            $agentLabel
        ) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($tableWidth, 7, 'PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA', 1, 1, 'C', true);
            $pdf->Cell($tableWidth, 7, 'GROUP PRODUCT SALES REPORT - YEAR ' . $year, 1, 1, 'C', true);

            $pdf->SetFillColor(235, 0, 235);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($tableWidth, 7, $agentLabel, 1, 1, 'C', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($colCode + $colDesc, 7, '', 1, 0, 'C', true);
            foreach ($months as $monthNo => $monthLabel) {
                $pdf->Cell($colMonth, 7, $formatQty($monthTotals[$monthNo] ?? 0), 1, 0, 'C', true);
            }
            $pdf->Cell($colMonth, 7, $formatQty($grandTotal), 1, 1, 'C', true);

            // Row: CODE / ITEM DESCRIPTION / QTY SOLD span
            $pdf->SetFont('helvetica', 'BI', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($colCode, 8, 'CODE', 1, 0, 'C', true);
            $pdf->Cell($colDesc, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell($colMonth * $monthCount, 8, 'QTY SOLD', 1, 1, 'C', true);

            // Months header row
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($colCode + $colDesc, 8, '', 1, 0, 'C', true);
            foreach ($months as $monthNo => $monthLabel) {
                $pdf->Cell($colMonth, 8, $monthLabel, 1, 0, 'C', true);
            }
            $pdf->Cell($colMonth, 8, 'TOTAL', 1, 1, 'C', true);
        };

        $ensureSpace = function ($neededHeight) use ($pdf, $drawHeader) {
            $bottomLimit = $pdf->getPageHeight() - $pdf->getMargins()['bottom'];
            if (($pdf->GetY() + $neededHeight) > $bottomLimit) {
                $pdf->AddPage();
                $drawHeader();
            }
        };

        $drawHeader();

        foreach ($groupedItems as $groupName => $items) {
            $ensureSpace(7);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($tableWidth, 7, 'Group :' . $groupName, 1, 1, 'L', true);

            foreach ($items as $item) {
                $ensureSpace(6.5);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Cell($colCode, 6.5, (string) $item['item_code'], 1, 0, 'L', true);
                $pdf->Cell($colDesc, 6.5, (string) $item['item_description'], 1, 0, 'L', true);
                foreach ($months as $monthNo => $monthLabel) {
                    $pdf->Cell($colMonth, 6.5, $formatQty($item['months'][$monthNo] ?? 0), 1, 0, 'C', true);
                }
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell($colMonth, 6.5, $formatQty($item['total'] ?? 0), 1, 1, 'C', true);
            }

            $ensureSpace(6.5);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($tableWidth, 6.5, '', 1, 1, 'L', true);
        }
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
