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
        ]);

        $searchTerm = $request->input('search');

        // Fetch invoice-type orders with outstanding balances
        $invoicesQuery = Order::select([
            'orders.*',
            'customers.customer_code',
            'customers.id as customer_id',
            'customers.name as customer_name',
            'customers.company_name',
            'customers.payment_type',
            'customers.payment_term',
            DB::raw('COALESCE((
                    SELECT SUM(receipt_orders.amount_applied)
                    FROM receipt_orders
                    INNER JOIN receipts ON receipt_orders.receipt_id = receipts.id
                    WHERE receipt_orders.order_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
        ])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.type', 'INV')
            ->whereRaw('(
                COALESCE((
                    SELECT SUM(receipt_orders.amount_applied)
                    FROM receipt_orders
                    INNER JOIN receipts ON receipt_orders.receipt_id = receipts.id
                    WHERE receipt_orders.order_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) < (COALESCE(orders.net_amount, orders.grand_amount, 0) - 0.01)
                OR
                orders.net_amount = "0"
            )');

        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $invoicesQuery->whereIn('customers.agent_no', [$user->name]);
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
            ->with(['items', 'customer'])
            ->orderBy('orders.order_date', 'asc')
            ->get();

        // Filter out invoices without customer data and group by customer
        // $customersWithDebts = $invoicesWithCustomers
        //     ->filter(function ($invoice) {
        //         return !empty($invoice->customer_code);
        //     })
        //     ->groupBy('customer_code');
        $customersWithDebts = $invoicesWithCustomers
            ->filter(function ($invoice) {
                return !empty($invoice->customer_code);
            });
            //->groupBy('customer_code');

        // Transform the data to match the Flutter UI's expected structure
        $formattedData = $customersWithDebts->map(function ($invoices, $customerCode) {
            // Get customer data from the first invoice (all invoices have same customer data)
            $firstInvoice = $invoices->first();

            // Map the invoices to the 'debtItems' structure
            $debtItems = $invoices->map(function ($invoice) use ($firstInvoice) {

                $orderDate = $invoice->order_date instanceof Carbon ? $invoice->order_date : Carbon::parse($invoice->order_date);
                // Calculate due date based on payment term
                $dueDate = $this->calculateDueDate($orderDate, $firstInvoice->payment_term);

                // Calculate total payments made
                $totalPayments = (float) ($invoice->total_payments ?? 0);

                // Calculate trade return amount from order items
                $tradeReturnAmount = 0.0;
                $salesAmount = 0.0;

                if ($invoice->relationLoaded('items')) {
                    foreach ($invoice->items as $item) {
                        $itemAmount = (float) ($item->amount ?? 0.0);
                        $salesAmount += $itemAmount;
                    }
                }
                // Calculate credit note amount from linked CN orders
                $creditAmount = 0.0;
                $linkedCNs = Order::where('credit_invoice_no', $invoice->reference_no)
                    ->where('type', 'CN')
                    ->get();

                foreach ($linkedCNs as $cnOrder) {
                    $creditAmount += (float) ($cnOrder->net_amount ?? 0);
                }

                $totalReturnAmt = $tradeReturnAmount + $creditAmount;

                // Outstanding balance = sales amount - return amount - credit amount - payments
                // $outstandingBalance = max(0, $salesAmount - $tradeReturnAmount - $creditAmount - $totalPayments);
                $outstandingBalance = $salesAmount - $tradeReturnAmount - $creditAmount - $totalPayments;

                // need to exclude credit note only invoice, cn is with zero salesamount
                if($salesAmount == 0 && $outstandingBalance <= 0 ){
                    // IT IS CN
                }else{
                    if($outstandingBalance <= 0){
                        return null;
                    };
                }

                // Debug logging for partially paid invoices
                if ($totalPayments > 0 && $outstandingBalance > 0) {
                    \Log::info("Partially paid invoice: REFNO={$invoice->reference_no}, salesAmount={$salesAmount}, returnAmount={$tradeReturnAmount}, creditAmount={$creditAmount}, total_payments={$totalPayments}, outstanding={$outstandingBalance}");
                }

                return [
                    'salesNo' => $invoice->reference_no, // Invoice reference number
                    'salesDate' => $orderDate->toDateString(),  // Return date-only format (YYYY-MM-DD)
                    'paymentType' => $firstInvoice->payment_type ?? 'Credit', // Fallback value
                    'paymentTerm' => $firstInvoice->payment_term ?? '30 Days', // Fallback value
                    'dueDate' => $dueDate->toDateString(),  // Return date-only format (YYYY-MM-DD)
                    'outstandingAmount' => $outstandingBalance, // Remaining outstanding balance after payments, returns, and credit notes
                    'salesAmount' => $salesAmount, // Sales amount (excluding trade returns)
                    // 'returnAmount' => $tradeReturnAmount, // Trade return amount
                    // 'creditAmount' => $creditAmount, // Credit note amount from linked CN orders
                    // 'amountPaid' => $totalPayments, // Amount paid from receipts
                    'returnAmount' => $totalReturnAmt,
                    'creditAmount' => $totalPayments,
                    'amountPaid' => $totalPayments,
                    'currency' => 'RM', // Default currency
                ];
            })->filter()->values();

            // Calculate total outstanding amount from remaining balances (not original NET_BIL)
            $totalOutstanding = $debtItems->sum('outstandingAmount');

            return [
                'customerCode' => $customerCode,
                'outletsCode' => $customerCode, // Outlets code is same as customer code
                'companyName' => $firstInvoice->company_name ?? $firstInvoice->customer_name ?? 'Unknown Customer',
                'debtItems' => $debtItems,
                'totalOutstandingAmount' => $totalOutstanding, // Sum of remaining outstanding balances
            ];
        });

        $formattedData = json_decode(json_encode(
            $formattedData->values()
        ), true);


        // GET ALL ORDER WITH ZERO REGULAR ITEM
        foreach ($formattedData as &$customer) {
            $date_group = [];
            foreach ($customer['debtItems'] as $debtItem) {
                $salesAmount = $debtItem['salesAmount'];
                $outstandingBalance = $debtItem['outstandingAmount'];
                if($salesAmount == 0 && $outstandingBalance <= 0 ){
                    // IT IS CN
                }else{ // IF IS NOT CN
                    $date_group[$debtItem['salesDate']] = $debtItem['salesDate'];
                }
            }

            foreach ($customer['debtItems'] as $k => $debtItem) {
                $CN_ONLY = $debtItem['salesAmount'] == 0 && $debtItem['returnAmount'] > 0;
                if ($CN_ONLY) {
                    $customer['debtItems'][$k]['is_cn_only'] = true;
                    if (!isset($date_group[$debtItem['salesDate']])) {
                        unset($customer['debtItems'][$k]);
                    }
                    // THIS IS THE KEY FIX: Re-index the array to make it a proper list
                    $customer['debtItems'] = array_values($customer['debtItems']);
                }

            }
        }
        // dd($formattedData);

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
