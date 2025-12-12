<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ArtransCreditNote;
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

        // Build query to get invoices with payment calculations from orders table
        $query = Order::select([
                'orders.*',
                // Calculate total payments made for this invoice (from non-deleted receipts)
                DB::raw('COALESCE((
                    SELECT SUM(receipt_invoices.amount_applied)
                    FROM receipt_invoices
                    INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                    WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
            ])
            ->where('orders.customer_code', $customerCode)
            ->where('orders.type', 'INV');

        // Only filter out fully paid invoices if include_paid is false
        if (!$includePaid) {
            // Outstanding definition: total_payments < net_amount
            // Show invoices where total payments < net_amount (allowing 0.01 tolerance for floating point)
            $query->whereRaw('COALESCE((
                SELECT SUM(receipt_invoices.amount_applied)
                FROM receipt_invoices
                INNER JOIN receipts ON receipt_invoices.receipt_id = receipts.id
                WHERE receipt_invoices.invoice_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (COALESCE(orders.net_amount, orders.grand_amount, 0) - 0.01)');
        }

        $invoices = $query->with(['customer'])
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info("Found {$invoices->count()} invoices for customer {$customerCode}");

        // Calculate outstanding balance for each invoice and format response
        $formattedInvoices = $invoices->map(function ($invoice) {
            $totalPayments = (float) ($invoice->total_payments ?? 0);
            
            // Calculate total credit notes amount for this invoice
            // Note: Credit notes may still be in artrans table, so we check via invoice_orders pivot
            $totalCreditNotes = 0;
            // Try to find linked artrans record via invoice_orders pivot table
            $linkedArtran = DB::table('invoice_orders')
                ->join('artrans', 'invoice_orders.invoice_refno', '=', 'artrans.REFNO')
                ->where('invoice_orders.order_id', $invoice->id)
                ->where('artrans.TYPE', 'INV')
                ->select('artrans.artrans_id')
                ->first();
            
            if ($linkedArtran && $linkedArtran->artrans_id) {
                $creditNotes = ArtransCreditNote::where('invoice_id', $linkedArtran->artrans_id)
                    ->with('creditNote')
                    ->get();
                foreach ($creditNotes as $cnLink) {
                    if ($cnLink->creditNote) {
                        $totalCreditNotes += (float) ($cnLink->creditNote->NET_BIL ?? 0);
                    }
                }
            }
            
            // Adjust amounts by deducting credit notes (for INV type)
            $originalNetAmount = (float) ($invoice->net_amount ?? 0);
            $originalGrandAmount = (float) ($invoice->grand_amount ?? 0);
            $originalGrossAmount = (float) ($invoice->gross_amount ?? 0);
            $originalTax1 = (float) ($invoice->tax1 ?? 0);
            
            $netAmount = $originalNetAmount;
            $grandAmount = $originalGrandAmount;
            $grossAmount = $originalGrossAmount;
            $tax1 = $originalTax1;
            
            if ($totalCreditNotes > 0) {
                $netAmount = max(0, $originalNetAmount - $totalCreditNotes);
                $grandAmount = max(0, $originalGrandAmount - $totalCreditNotes);
                $grossAmount = max(0, $originalGrossAmount - $totalCreditNotes);
                
                // Proportionally adjust tax
                if ($originalGrandAmount > 0) {
                    $creditNoteRatio = $totalCreditNotes / $originalGrandAmount;
                    $tax1 = max(0, $originalTax1 * (1 - $creditNoteRatio));
                }
            }
            
            // Outstanding balance = adjusted invoice amount - payments made
            $outstandingBalance = max(0, $netAmount - $totalPayments);

            return [
                'id' => $invoice->id,
                'REFNO' => $invoice->reference_no,
                'TYPE' => $invoice->type,
                'NAME' => $invoice->customer_name ?? 'N/A',
                'CUSTNO' => $invoice->customer_code,
                'DATE' => $invoice->order_date ? $invoice->order_date->toDateString() : null,  // Return date-only format (YYYY-MM-DD)
                'NET_BIL' => $netAmount, // Adjusted amount (deducting CN)
                'GRAND_BIL' => $grandAmount, // Adjusted amount (deducting CN)
                'GROSS_BILL' => $grossAmount, // Adjusted amount (deducting CN)
                'TAX1_BIL' => $tax1, // Adjusted amount (proportionally)
                'NOTE' => $invoice->remarks ?? '',
                'status' => $invoice->status ?? 'pending',
                'total_payments' => $totalPayments,
                'outstanding_balance' => $outstandingBalance,
                'total_credit_notes' => $totalCreditNotes, // Total amount of linked credit notes
                // items excluded for cleaner JSON and better performance
                // customer excluded as customer info is already in invoice (customer_name, customer_code)
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

