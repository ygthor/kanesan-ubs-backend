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

        // Start querying customers
        $customersQuery = Customer::query();

        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            // No filtering needed for KBS user
        } else {
            // Filter by allowed customer IDs if middleware has set them
            if ($request->has('allowed_customer_ids') && !empty($request->allowed_customer_ids)) {
                $customersQuery->whereIn('id', $request->allowed_customer_ids);
            } else {
                // If no allowed customer IDs, return empty result
                return makeResponse(200, 'No customers accessible.', []);
            }
        }

        // If a search term is provided, filter customers by code or name
        if ($searchTerm) {
            $customersQuery->where(function ($query) use ($searchTerm) {
                $query->where('customer_code', 'like', '%' . $searchTerm . '%')
                      ->orWhere('company_name', 'like', '%' . $searchTerm . '%');
            });
        }

        // Only get customers who have invoices (TYPE = 'INV')
        $customersWithDebts = $customersQuery->whereHas('artrans', function ($query) {
            $query->where('TYPE', 'INV');
        })->with(['artrans' => function ($query) {
            $query->where('TYPE', 'INV')
                  ->orderBy('DATE', 'asc'); // Order debts by date
        }])->get();

        // Transform the data to match the Flutter UI's expected structure
        $formattedData = $customersWithDebts->map(function ($customer) {
            
            // Map the invoices to the 'debtItems' structure
            $debtItems = $customer->artrans->map(function ($invoice) use ($customer) {
                
                // Calculate due date based on payment term
                $dueDate = $this->calculateDueDate($invoice->DATE, $customer->payment_term);

                return [
                    'salesNo' => $invoice->REFNO, // Invoice reference number
                    'salesDate' => $invoice->DATE->toIso8601String(),
                    'paymentType' => $customer->payment_type ?? 'Credit', // Fallback value
                    'paymentTerm' => $customer->payment_term ?? '30 Days', // Fallback value
                    'dueDate' => $dueDate->toIso8601String(),
                    'outstandingAmount' => (float) $invoice->NET_BIL, // Net amount outstanding
                    'salesAmount' => (float) $invoice->GRAND_BIL, // Grand total amount
                    'creditAmount' => (float) $invoice->CREDIT_BIL, // Credit amount if any
                    'currency' => 'RM', // Default currency
                ];
            });

            return [
                'customerCode' => $customer->customer_code,
                'outletsCode' => $customer->customer_code, // Outlets code is same as customer code
                'companyName' => $customer->company_name,
                'debtItems' => $debtItems,
                'totalOutstandingAmount' => $customer->artrans->sum('NET_BIL'),
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
