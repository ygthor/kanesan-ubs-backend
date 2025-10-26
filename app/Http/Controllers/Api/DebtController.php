<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Artran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        // Note: User access control will be handled at the invoice level
        // For now, we'll get all invoices and let the frontend handle access control
        // TODO: Implement proper access control based on user permissions

        // Get invoices with customer data using LEFT JOIN
        $invoicesQuery = Artran::select([
                'artrans.*',
                'customers.customer_code',
                'customers.name as customer_name',
                'customers.company_name',
                'customers.payment_type',
                'customers.payment_term'
            ])
            ->leftJoin('customers', 'artrans.CUSTNO', '=', 'customers.customer_code')
            ->where('artrans.TYPE', 'INV');

        // Apply search filter if provided
        if ($searchTerm) {
            $invoicesQuery->where(function ($query) use ($searchTerm) {
                $query->where('customers.customer_code', 'like', '%' . $searchTerm . '%')
                      ->orWhere('customers.company_name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('customers.name', 'like', '%' . $searchTerm . '%');
            });
        }

        $invoicesWithCustomers = $invoicesQuery->orderBy('artrans.DATE', 'asc')->get();

        // Group invoices by customer
        $customersWithDebts = $invoicesWithCustomers->groupBy('customer_code');

        // Transform the data to match the Flutter UI's expected structure
        $formattedData = $customersWithDebts->map(function ($invoices, $customerCode) {
            // Get customer data from the first invoice (all invoices have same customer data)
            $firstInvoice = $invoices->first();
            
            // Map the invoices to the 'debtItems' structure
            $debtItems = $invoices->map(function ($invoice) use ($firstInvoice) {
                
                // Calculate due date based on payment term
                $dueDate = $this->calculateDueDate($invoice->DATE, $firstInvoice->payment_term);

                return [
                    'salesNo' => $invoice->REFNO, // Invoice reference number
                    'salesDate' => $invoice->DATE->toIso8601String(),
                    'paymentType' => $firstInvoice->payment_type ?? 'Credit', // Fallback value
                    'paymentTerm' => $firstInvoice->payment_term ?? '30 Days', // Fallback value
                    'dueDate' => $dueDate->toIso8601String(),
                    'outstandingAmount' => (float) $invoice->NET_BIL, // Net amount outstanding
                    'salesAmount' => (float) $invoice->GRAND_BIL, // Grand total amount
                    'creditAmount' => (float) $invoice->CREDIT_BIL, // Credit amount if any
                    'currency' => 'RM', // Default currency
                ];
            });

            return [
                'customerCode' => $customerCode,
                'outletsCode' => $customerCode, // Outlets code is same as customer code
                'companyName' => $firstInvoice->company_name ?? $firstInvoice->customer_name ?? 'Unknown Customer',
                'debtItems' => $debtItems,
                'totalOutstandingAmount' => $invoices->sum('NET_BIL'),
            ];
        });

        return makeResponse(200, 'Customer debts retrieved successfully.', $formattedData);
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
