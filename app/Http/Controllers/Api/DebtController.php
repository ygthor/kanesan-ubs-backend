<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebtController extends Controller
{
    /**
     * Retrieve a list of customers with their outstanding debts (invoices with type = INV).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'search' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:255',
        ]);

        $searchTerm = $request->input('search');
        $customerCode = $request->input('customer_code');

        // Pre-aggregate payments (receipt_orders) once and join to invoices.
        $paymentsAgg = DB::table('receipt_orders as ro')
            ->join('receipts as r', 'ro.receipt_id', '=', 'r.id')
            ->whereNull('r.deleted_at')
            ->selectRaw('ro.order_refno, SUM(ro.amount_applied) as total_payments')
            ->groupBy('ro.order_refno');

        // Pre-aggregate credit notes once and join to invoices.
        $creditAgg = DB::table('orders as cn')
            ->whereIn('cn.type', ['CN', 'CN2'])  // Include CN (trade returns) and CN2 (manual credit notes)
            ->selectRaw('cn.credit_invoice_no, SUM(cn.net_amount) as credit_amount')
            ->groupBy('cn.credit_invoice_no');

        $invoicesQuery = Order::query()
            ->select([
                'orders.id',
                'orders.reference_no',
                'orders.customer_id',
                'orders.customer_code',
                'orders.order_date',
                'orders.net_amount',
                'orders.type',
                'customers.name as customer_name',
                'customers.company_name',
                'customers.payment_type',
                'customers.payment_term',
                DB::raw('COALESCE(pay_sum.total_payments, 0) as total_payments'),
                DB::raw('COALESCE(cn_sum.credit_amount, 0) as credit_amount'),
            ])
            ->leftJoinSub($paymentsAgg, 'pay_sum', function ($join) {
                $join->on('pay_sum.order_refno', '=', 'orders.reference_no');
            })
            ->leftJoinSub($creditAgg, 'cn_sum', function ($join) {
                $join->on('cn_sum.credit_invoice_no', '=', 'orders.reference_no');
            })
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.type', 'INV')
            ->where(function ($q) {
                $q->whereRaw('(COALESCE(orders.net_amount, 0) - COALESCE(cn_sum.credit_amount, 0) - COALESCE(pay_sum.total_payments, 0)) > 0.01')
                  ->orWhere('orders.net_amount', 0);
            });

        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $invoicesQuery->whereIn('customers.agent_no', [$user->name]);
        }

        // Filter by specific customer code if provided
        if ($customerCode) {
            $invoicesQuery->where('orders.customer_code', $customerCode);
        }

        // Apply search filter if provided
        if ($searchTerm) {
            $invoicesQuery->where(function ($query) use ($searchTerm) {
                $query->where('customers.customer_code', 'like', '%' . $searchTerm . '%')
                    ->orWhere('customers.company_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('customers.name', 'like', '%' . $searchTerm . '%');
            });
        }

        $invoicesWithCustomers = $invoicesQuery
            ->orderBy('order_date', 'desc')
            ->orderBy('orders.id', 'desc')
            ->orderBy('reference_no', 'desc')
            ->get();

        // Filter out invoices without customer data and group by customer
        $customersWithDebts = $invoicesWithCustomers
            ->filter(function ($invoice) {
                return !empty($invoice->customer_code);
            })
            ->groupBy('customer_code');

        // Transform the data to match the Flutter UI's expected structure
        $formattedData = $customersWithDebts->map(function ($invoices, $customerCode) {
            $firstInvoice = $invoices->first();

            // Map the invoices to the 'debtItems' structure
            $debtItems = $invoices->map(function ($invoice) use ($firstInvoice, $customerCode) {
                $orderDate = $invoice->order_date instanceof Carbon ? $invoice->order_date : Carbon::parse($invoice->order_date);
                $dueDate = $this->calculateDueDate($orderDate, $firstInvoice->payment_term);

                $totalPayments     = (float) ($invoice->total_payments ?? 0);
                $salesAmount       = (float) ($invoice->net_amount ?? 0); // Use net_amount to include discounts
                $creditAmount      = (float) ($invoice->credit_amount ?? 0);
                $tradeReturnAmount = 0.0;
                $totalReturnAmt    = $tradeReturnAmount + $creditAmount;

                // Outstanding balance = sales amount (net_amount) - return amount - credit amount - payments
                $outstandingBalance = $salesAmount - $tradeReturnAmount - $creditAmount - $totalPayments;

                // Need to exclude credit note only invoice with zero sales amount and non-positive balance
                if ($salesAmount == 0 && $outstandingBalance <= 0) {
                    return null;
                } else if ($outstandingBalance <= 0) {
                    return null;
                }

                return [
                    'id' => $invoice->id,
                    'salesNo' => $invoice->reference_no,
                    'salesDate' => $orderDate->toDateString(),
                    'paymentType' => $firstInvoice->payment_type ?? 'Credit',
                    'paymentTerm' => $firstInvoice->payment_term ?? '30 Days',
                    'dueDate' => $dueDate->toDateString(),
                    'outstandingAmount' => $outstandingBalance,
                    'salesAmount' => $salesAmount,
                    'returnAmount' => $totalReturnAmt,
                    'creditAmount' => $totalPayments,
                    'amountPaid' => $totalPayments,
                    'currency' => 'RM',
                    'is_cn_only' => false,
                ];
            })->filter()->values();

            $totalOutstanding = $debtItems->sum('outstandingAmount');

            // Find dates with active regular invoices
            $dateGroup = [];
            foreach ($debtItems as $item) {
                $salesAmount = $item['salesAmount'];
                $outstandingBalance = $item['outstandingAmount'];
                if (!($salesAmount == 0 && $outstandingBalance <= 0)) {
                    $dateGroup[$item['salesDate']] = true;
                }
            }

            // Flag and filter CN-only items
            $filteredDebtItems = [];
            foreach ($debtItems as $item) {
                $isCnOnly = ($item['salesAmount'] == 0 && $item['returnAmount'] > 0);
                if ($isCnOnly) {
                    $item['is_cn_only'] = true;
                    if (!isset($dateGroup[$item['salesDate']])) {
                        continue;
                    }
                }
                $filteredDebtItems[] = $item;
            }

            return [
                'customerCode' => (string) $customerCode,
                'outletsCode' => (string) $customerCode,
                'companyName' => $firstInvoice->company_name ?? $firstInvoice->customer_name ?? 'Unknown Customer',
                'debtItems' => $filteredDebtItems,
                'totalOutstandingAmount' => $totalOutstanding,
            ];
        })->values()->all();

        return makeResponse(200, 'Customer debts retrieved successfully.', $formattedData);
    }

    /**
     * A helper function to calculate the due date based on a payment term string.
     * This is a simplified implementation.
     *
     * @param \Carbon\Carbon|\Illuminate\Support\Carbon $orderDate
     * @param string|null $paymentTerm
     * @return \Carbon\Carbon
     */
    private function calculateDueDate($orderDate, ?string $paymentTerm): Carbon
    {
        $date = $orderDate->copy();

        if (is_null($paymentTerm)) {
            return $date->addDays(30); // Default to 30 days if term is not set
        }

        // Try to extract a number from the string
        if (preg_match('/(\d+)/', $paymentTerm, $matches)) {
            $days = (int) $matches[0];
            return $date->addDays($days);
        }

        if (strtolower($paymentTerm) === 'cod') {
            return $date; // Due on the same day for Cash On Delivery
        }

        // Default fallback
        return $date->addDays(30);
    }
}
