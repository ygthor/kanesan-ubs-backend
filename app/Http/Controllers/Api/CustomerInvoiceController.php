<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Artran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerInvoiceController extends Controller
{
    /**
     * Get outstanding invoices for a specific customer with calculated balances.
     * 
     * This endpoint returns unpaid or partially paid invoices with:
     * - Outstanding balance (NET_BIL - total_payments)
     * - Total payments made
     * - Simple, flat structure for easy frontend consumption
     *
     * Query parameters:
     * - customer_code: Customer code (required)
     * - include_paid: Set to 'true' to include fully paid invoices (default: false)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOutstandingInvoices(Request $request)
    {
        $user = auth()->user();
        $customerCode = $request->query('customer_code');
        $includePaid = $request->query('include_paid', 'false') === 'true';

        if (!$customerCode) {
            return makeResponse(400, 'customer_code parameter is required', null);
        }

        \Log::info("Fetching invoices for customer: {$customerCode}, include_paid: " . ($includePaid ? 'yes' : 'no'));

        // Find customer
        $customer = Customer::where('customer_code', $customerCode)->first();
        
        if (!$customer) {
            \Log::warning("Customer not found: {$customerCode}");
            return makeResponse(404, 'Customer not found', null);
        }

        \Log::info("Customer found: {$customer->name} (ID: {$customer->id})");

        // Check if user has access to this customer (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $hasAccess = $user->customers()->where('customers.id', $customer->id)->exists();
            if (!$hasAccess) {
                \Log::warning("User {$user->id} denied access to customer {$customerCode}");
                return makeResponse(403, 'Access denied to this customer', null);
            }
        }

        // Build query to get invoices with payment calculations
        $query = Artran::select([
                'artrans.*',
                // Calculate total payments made for this invoice (from non-deleted receipts)
                DB::raw('COALESCE((
                    SELECT SUM(receipt_invoices.amount_applied)
                    FROM receipt_invoices
                    INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                    WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = artrans.REFNO COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
            ])
            ->where('artrans.CUSTNO', $customerCode)
            ->where('artrans.TYPE', 'INV');

        // Only filter out fully paid invoices if include_paid is false
        if (!$includePaid) {
            // Outstanding definition: total_payments < NET_BIL
            // Show invoices where total payments < NET_BIL (allowing 0.01 tolerance for floating point)
            $query->whereRaw('COALESCE((
                SELECT SUM(receipt_invoices.amount_applied)
                FROM receipt_invoices
                INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = artrans.REFNO COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (artrans.NET_BIL - 0.01)');
        }

        $invoices = $query->with(['customer'])
            ->orderBy('DATE', 'desc')
            ->get();

        \Log::info("Found {$invoices->count()} invoices for customer {$customerCode}");

        // Calculate outstanding balance for each invoice and format response
        $formattedInvoices = $invoices->map(function ($invoice) {
            $totalPayments = (float) ($invoice->total_payments ?? 0);
            $netBil = (float) $invoice->NET_BIL;
            // Outstanding balance = invoice amount - payments made
            $outstandingBalance = max(0, $netBil - $totalPayments);

            return [
                'id' => $invoice->id,
                'REFNO' => $invoice->REFNO,
                'TYPE' => $invoice->TYPE,
                'NAME' => $invoice->NAME,
                'CUSTNO' => $invoice->CUSTNO,
                'DATE' => $invoice->DATE ? $invoice->DATE->toDateString() : null,  // Return date-only format (YYYY-MM-DD)
                'NET_BIL' => $netBil,
                'GRAND_BIL' => (float) $invoice->GRAND_BIL,
                'GROSS_BILL' => (float) $invoice->GROSS_BIL,
                'TAX1_BIL' => (float) $invoice->TAX1_BIL,
                'NOTE' => $invoice->NOTE,
                'status' => $invoice->status ?? 'pending',
                'total_payments' => $totalPayments,
                'outstanding_balance' => $outstandingBalance,
                // items excluded for cleaner JSON and better performance
                // customer excluded as customer info is already in invoice (NAME, CUSTNO)
            ];
        });

        // Calculate total outstanding balance
        $totalOutstanding = $formattedInvoices->sum('outstanding_balance');

        \Log::info("Total outstanding for customer {$customerCode}: {$totalOutstanding}");

        return makeResponse(200, 'Outstanding invoices retrieved successfully', [
            'invoices' => $formattedInvoices->values(),
            'total_outstanding' => $totalOutstanding,
            'customer_code' => $customerCode,
            'customer_name' => $customer->name,
            'invoice_count' => $formattedInvoices->count(),
        ]);
    }
}

