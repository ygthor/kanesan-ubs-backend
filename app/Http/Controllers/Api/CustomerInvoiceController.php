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
     * @param  string  $customerCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOutstandingInvoices($customerCode)
    {
        $user = auth()->user();

        // Find customer
        $customer = Customer::where('customer_code', $customerCode)->first();
        
        if (!$customer) {
            return makeResponse(404, 'Customer not found', null);
        }

        // Check if user has access to this customer (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $hasAccess = $user->customers()->where('customers.id', $customer->id)->exists();
            if (!$hasAccess) {
                return makeResponse(403, 'Access denied to this customer', null);
            }
        }

        // Get invoices with outstanding balances
        $invoices = Artran::select([
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
            ->where('artrans.TYPE', 'INV')
            // Only include invoices that are not fully paid
            // Show invoices where total payments < NET_BIL (allowing 0.01 tolerance for floating point)
            ->whereRaw('COALESCE((
                SELECT SUM(receipt_invoices.amount_applied)
                FROM receipt_invoices
                INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = artrans.REFNO COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (artrans.NET_BIL - 0.01)')
            ->with(['items', 'customer'])
            ->orderBy('DATE', 'desc')
            ->get();

        // Calculate outstanding balance for each invoice and format response
        $formattedInvoices = $invoices->map(function ($invoice) {
            $totalPayments = (float) ($invoice->total_payments ?? 0);
            $netBil = (float) $invoice->NET_BIL;
            $outstandingBalance = max(0, $netBil - $totalPayments);

            return [
                'id' => $invoice->id,
                'REFNO' => $invoice->REFNO,
                'TYPE' => $invoice->TYPE,
                'NAME' => $invoice->NAME,
                'CUSTNO' => $invoice->CUSTNO,
                'DATE' => $invoice->DATE ? $invoice->DATE->toIso8601String() : null,
                'NET_BIL' => $netBil,
                'GRAND_BIL' => (float) $invoice->GRAND_BIL,
                'GROSS_BILL' => (float) $invoice->GROSS_BIL,
                'TAX1_BIL' => (float) $invoice->TAX1_BIL,
                'NOTE' => $invoice->NOTE,
                'status' => $invoice->status ?? 'pending',
                'total_payments' => $totalPayments,
                'outstanding_balance' => $outstandingBalance,
                'items' => $invoice->items,
                'customer' => $invoice->customer,
            ];
        });

        // Calculate total outstanding balance
        $totalOutstanding = $formattedInvoices->sum('outstanding_balance');

        return makeResponse(200, 'Outstanding invoices retrieved successfully', [
            'invoices' => $formattedInvoices->values(),
            'total_outstanding' => $totalOutstanding,
            'customer_code' => $customerCode,
            'invoice_count' => $formattedInvoices->count(),
        ]);
    }
}

