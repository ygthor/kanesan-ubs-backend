<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
                    SELECT SUM(receipt_invoices.amount_applied)
                    FROM receipt_invoices
                    INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                    WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
            ])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.type', 'INV')
            ->whereRaw('COALESCE((
                SELECT SUM(receipt_invoices.amount_applied)
                FROM receipt_invoices
                INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (COALESCE(orders.net_amount, orders.grand_amount, 0) - 0.01)');

        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No debts accessible.', []);
            }
            $invoicesQuery->whereIn('customers.id', $allowedCustomerIds);
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
        $customersWithDebts = $invoicesWithCustomers
            ->filter(function ($invoice) {
                return !empty($invoice->customer_code);
            })
            ->groupBy('customer_code');

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
                $netAmount = (float) ($invoice->net_amount ?? $invoice->grand_amount ?? 0);
                $outstandingBalance = max(0, $netAmount - $totalPayments);
                
                // Debug logging for partially paid invoices
                if ($totalPayments > 0 && $outstandingBalance > 0) {
                    \Log::info("Partially paid invoice: REFNO={$invoice->reference_no}, net_amount={$netAmount}, total_payments={$totalPayments}, outstanding={$outstandingBalance}");
                }

                return [
                    'salesNo' => $invoice->reference_no, // Invoice reference number
                    'salesDate' => $orderDate->toDateString(),  // Return date-only format (YYYY-MM-DD)
                    'paymentType' => $firstInvoice->payment_type ?? 'Credit', // Fallback value
                    'paymentTerm' => $firstInvoice->payment_term ?? '30 Days', // Fallback value
                    'dueDate' => $dueDate->toDateString(),  // Return date-only format (YYYY-MM-DD)
                    'outstandingAmount' => $outstandingBalance, // Remaining outstanding balance after payments
                    'salesAmount' => (float) ($invoice->grand_amount ?? $netAmount), // Grand total amount
                    'returnAmount' => 0.0, // Returns not tracked in orders table
                    'creditAmount' => 0.0, // Credit notes not tracked here
                    'amountPaid' => $totalPayments, // Amount paid from receipts
                    'currency' => 'RM', // Default currency
                ];
            });

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

        return makeResponse(200, 'Customer debts retrieved successfully.', $formattedData->values()->toArray());
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
