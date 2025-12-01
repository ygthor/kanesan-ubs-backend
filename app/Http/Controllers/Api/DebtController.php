<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Artran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Get invoices with customer data using LEFT JOIN
        // Include invoices with partial or no payments, calculate outstanding balance after payments
        $invoicesQuery = Artran::select([
                'artrans.*',
                'customers.customer_code',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.company_name',
                'customers.payment_type',
                'customers.payment_term',
                // Calculate total payments made for this invoice (from non-deleted receipts)
                DB::raw('COALESCE((
                    SELECT SUM(receipt_invoices.amount_applied)
                    FROM receipt_invoices
                    INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                    WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = artrans.REFNO COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
            ])
            ->leftJoin('customers', function($join) {
                // Fix collation mismatch by converting both sides to the same collation
                $join->on(DB::raw('artrans.CUSTNO COLLATE utf8mb4_unicode_ci'), '=', DB::raw('customers.customer_code COLLATE utf8mb4_unicode_ci'));
            })
            ->where('artrans.TYPE', 'INV')
            // Only exclude invoices that are fully paid (total payments >= NET_BIL)
            // Use small tolerance (0.01) for floating point precision
            // This allows partial payments to show in the debt list with updated outstanding balance
            ->whereRaw('COALESCE((
                SELECT SUM(receipt_invoices.amount_applied)
                FROM receipt_invoices
                INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = artrans.REFNO COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (artrans.NET_BIL - 0.01)');

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

        $invoicesWithCustomers = $invoicesQuery->with('items')->orderBy('artrans.DATE', 'asc')->get();

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
                
                // Calculate due date based on payment term
                $dueDate = $this->calculateDueDate($invoice->DATE, $firstInvoice->payment_term);

                // Calculate outstanding balance: NET_BIL minus total payments made
                $totalPayments = (float) ($invoice->total_payments ?? 0);
                $outstandingBalance = max(0, (float) $invoice->NET_BIL - $totalPayments);

                // Calculate return amount from invoice items (items with negative SIGN or negative AMT_BIL)
                $returnAmount = 0;
                if ($invoice->items) {
                    foreach ($invoice->items as $item) {
                        if (($item->SIGN ?? 1) < 0 || $item->AMT_BIL < 0) {
                            $returnAmount += abs($item->AMT_BIL);
                        }
                    }
                }

                return [
                    'salesNo' => $invoice->REFNO, // Invoice reference number
                    'salesDate' => $invoice->DATE->toIso8601String(),
                    'paymentType' => $firstInvoice->payment_type ?? 'Credit', // Fallback value
                    'paymentTerm' => $firstInvoice->payment_term ?? '30 Days', // Fallback value
                    'dueDate' => $dueDate->toIso8601String(),
                    'outstandingAmount' => $outstandingBalance, // Remaining outstanding balance after payments
                    'salesAmount' => (float) $invoice->GRAND_BIL, // Grand total amount
                    'returnAmount' => (float) $returnAmount, // Return amount from invoice items
                    'creditAmount' => (float) $invoice->CREDIT_BIL, // Credit amount if any
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
     * @param \Illuminate\Support\Carbon $orderDate
     * @param string|null $paymentTerm
     * @return \Illuminate\Support\Carbon
     */
    private function calculateDueDate(Carbon $orderDate, ?string $paymentTerm): Carbon
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
