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

        // Step 1: Query candidate invoices for the agent/customer directly with an indexed query
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
            ])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.type', 'INV');

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

        $invoices = $invoicesQuery
            ->orderBy('orders.order_date', 'desc')
            ->orderBy('orders.id', 'desc')
            ->orderBy('orders.reference_no', 'desc')
            ->get();

        if ($invoices->isEmpty()) {
            return makeResponse(200, 'Customer debts retrieved successfully.', []);
        }

        // Step 2: Collect all reference numbers for candidate invoices
        $refNos = $invoices->pluck('reference_no')->filter()->unique()->values()->all();

        // Step 3: Fetch aggregated payments ONLY for the candidate invoices (chunked to prevent oversized SQL)
        $payments = [];
        if (!empty($refNos)) {
            foreach (array_chunk($refNos, 1000) as $chunk) {
                $chunkPayments = DB::table('receipt_orders as ro')
                    ->join('receipts as r', 'ro.receipt_id', '=', 'r.id')
                    ->whereNull('r.deleted_at')
                    ->whereIn('ro.order_refno', $chunk)
                    ->selectRaw('ro.order_refno, SUM(ro.amount_applied) as total_payments')
                    ->groupBy('ro.order_refno')
                    ->pluck('total_payments', 'order_refno')
                    ->all();

                foreach ($chunkPayments as $ref => $amt) {
                    $payments[$ref] = (float) $amt;
                }
            }
        }

        // Step 4: Fetch aggregated credit notes ONLY for the candidate invoices
        $creditNotes = [];
        if (!empty($refNos)) {
            foreach (array_chunk($refNos, 1000) as $chunk) {
                $chunkCredits = DB::table('orders as cn')
                    ->whereIn('cn.type', ['CN', 'CN2'])
                    ->whereIn('cn.credit_invoice_no', $chunk)
                    ->selectRaw('cn.credit_invoice_no, SUM(cn.net_amount) as credit_amount')
                    ->groupBy('cn.credit_invoice_no')
                    ->pluck('credit_amount', 'credit_invoice_no')
                    ->all();

                foreach ($chunkCredits as $ref => $amt) {
                    $creditNotes[$ref] = (float) $amt;
                }
            }
        }

        // Step 5: Filter out invoices without customer data and group by customer
        $customersWithDebts = $invoices
            ->filter(function ($invoice) {
                return !empty($invoice->customer_code);
            })
            ->groupBy('customer_code');

        // Step 6: Transform and calculate balances
        $formattedData = $customersWithDebts->map(function ($customerInvoices, $custCode) use ($payments, $creditNotes) {
            $firstInvoice = $customerInvoices->first();

            $debtItems = $customerInvoices->map(function ($invoice) use ($firstInvoice, $payments, $creditNotes) {
                $orderDate = $invoice->order_date instanceof Carbon ? $invoice->order_date : Carbon::parse($invoice->order_date);
                $dueDate = $this->calculateDueDate($orderDate, $firstInvoice->payment_term);

                $refNo             = $invoice->reference_no;
                $totalPayments     = (float) ($payments[$refNo] ?? 0);
                $salesAmount       = (float) ($invoice->net_amount ?? 0);
                $creditAmount      = (float) ($creditNotes[$refNo] ?? 0);
                $tradeReturnAmount = 0.0;
                $totalReturnAmt    = $tradeReturnAmount + $creditAmount;

                // Outstanding balance = sales amount (net_amount) - return amount - credit amount - payments
                $outstandingBalance = $salesAmount - $tradeReturnAmount - $creditAmount - $totalPayments;

                // Filter out fully settled / zero invoices
                if ($salesAmount == 0 && $outstandingBalance <= 0) {
                    return null;
                } else if ($outstandingBalance <= 0.01) {
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

            if ($debtItems->isEmpty()) {
                return null;
            }

            $totalOutstanding = $debtItems->sum('outstandingAmount');

            // Find dates with active regular invoices
            $dateGroup = [];
            foreach ($debtItems as $item) {
                $sAmt = $item['salesAmount'];
                $ostd = $item['outstandingAmount'];
                if (!($sAmt == 0 && $ostd <= 0)) {
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

            if (empty($filteredDebtItems)) {
                return null;
            }

            return [
                'customerCode' => (string) $custCode,
                'outletsCode' => (string) $custCode,
                'companyName' => $firstInvoice->company_name ?? $firstInvoice->customer_name ?? 'Unknown Customer',
                'debtItems' => $filteredDebtItems,
                'totalOutstandingAmount' => $totalOutstanding,
            ];
        })->filter()->values()->all();

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
