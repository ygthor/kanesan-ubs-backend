<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\ReceiptInvoice;
use App\Models\Customer;
use App\Models\Artran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    /**
     * Display a listing of receipts.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $receiptsQuery = Receipt::with(['customer:id,customer_code,company_name', 'receiptInvoices']);
        
        // Debug logging for filter parameters
        \Log::info('Receipt filter parameters:', [
            'customer_id' => $request->input('customer_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'per_page' => $request->input('per_page', 15),
        ]);
        
        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No receipts accessible.', ['data' => [], 'total' => 0]);
            }
            $receiptsQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        
        // Apply customer filter if provided
        if ($request->has('customer_id') && $request->customer_id) {
            $receiptsQuery->where('customer_id', $request->customer_id);
            \Log::info('Applied customer filter:', ['customer_id' => $request->customer_id]);
        }
        
        // Apply date range filters if provided
        if ($request->has('date_from') && $request->date_from) {
            $receiptsQuery->whereDate('receipt_date', '>=', $request->date_from);
            \Log::info('Applied date_from filter:', ['date_from' => $request->date_from]);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $receiptsQuery->whereDate('receipt_date', '<=', $request->date_to);
            \Log::info('Applied date_to filter:', ['date_to' => $request->date_to]);
        }
        
        // Order by date only (ignore time), then by ID for consistent ordering
        $receipts = $receiptsQuery->orderByRaw('DATE(receipt_date) DESC')
                                 ->orderBy('id', 'desc')
                                 ->paginate($request->input('per_page', 15));

        \Log::info('Receipt query result count:', ['count' => $receipts->count()]);

        return makeResponse(200, 'Receipts retrieved successfully.', $receipts);
    }

    /**
     * Store a newly created receipt in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => 'required|string|max:255|unique:receipts,receipt_no',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'customer_code' => 'required|string|max:255',
            'receipt_date' => 'required|date',
            'payment_type' => 'required|string|max:255',
            'debt_amount' => 'required|numeric|min:0',
            'transaction_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_date' => 'nullable|required_if:payment_type,CHEQUE|date',
            'bank_name' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'invoice_refnos' => 'nullable|array',
            'invoice_refnos.*' => 'required|string|max:255',
            'invoice_amounts' => 'nullable|array',
            'invoice_amounts.*' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        // Check if user has access to this customer
        $user = auth()->user();
        $customer = \App\Models\Customer::find($request->customer_id);
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to create receipts for this customer.', null);
        }

        try {
            DB::beginTransaction();

            // Get validated data and separate invoice-related fields
            $validated = $validator->validated();
            $invoiceRefNos = $request->input('invoice_refnos', []);
            $invoiceAmounts = $request->input('invoice_amounts', []);

            // Remove invoice fields from validated data as they're not part of receipts table
            unset($validated['invoice_refnos'], $validated['invoice_amounts']);
            
            // Normalize receipt_date to start of day in app timezone
            // Since app timezone is set to 'Asia/Kuala_Lumpur' in config/app.php,
            // Carbon::parse() will use that timezone automatically
            // MySQL timestamp column will automatically convert to UTC when storing
            if (isset($validated['receipt_date'])) {
                $dateStr = $validated['receipt_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $validated['receipt_date'] = \Carbon\Carbon::parse($dateStr)->startOfDay();
                }
            }
            
            // Normalize cheque_date if provided
            if (isset($validated['cheque_date']) && !empty($validated['cheque_date'])) {
                $dateStr = $validated['cheque_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $validated['cheque_date'] = \Carbon\Carbon::parse($dateStr)->startOfDay();
                }
            }

            // Validate partial payment amounts before creating receipt
            if (!empty($invoiceRefNos) && is_array($invoiceRefNos)) {
                foreach ($invoiceRefNos as $invoiceRefNo) {
                    if (!empty($invoiceRefNo)) {
                        // Get the invoice
                        $invoice = Artran::where('REFNO', $invoiceRefNo)->where('TYPE', 'INV')->first();
                        if (!$invoice) {
                            DB::rollBack();
                            return makeResponse(404, "Invoice not found: {$invoiceRefNo}", null);
                        }

                        // Calculate existing payments for this invoice
                        $existingPayments = DB::table('receipt_invoices')
                            ->join('receipts', 'receipt_invoices.receipt_id', '=', 'receipts.id')
                            ->where('receipt_invoices.invoice_refno', $invoiceRefNo)
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_invoices.amount_applied') ?? 0.00;

                        // Calculate outstanding amount
                        $outstandingAmount = (float) $invoice->NET_BIL - (float) $existingPayments;

                        // Get the amount being applied in this receipt
                        $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                            ? (float) $invoiceAmounts[$invoiceRefNo] 
                            : (float) $outstandingAmount; // Default to full outstanding if not specified

                        // Validate: amount applied should not exceed outstanding amount
                        if ($amountApplied > $outstandingAmount + 0.01) { // Add small tolerance for floating point
                            DB::rollBack();
                            return makeResponse(422, "Payment amount (RM " . number_format($amountApplied, 2) . ") exceeds outstanding amount (RM " . number_format($outstandingAmount, 2) . ") for invoice {$invoiceRefNo}", null);
                        }

                        // Validate: amount should be positive
                        if ($amountApplied <= 0) {
                            DB::rollBack();
                            return makeResponse(422, "Payment amount must be greater than 0 for invoice {$invoiceRefNo}", null);
                        }
                    }
                }
            }

            // Create the receipt
            $receipt = Receipt::create($validated);

            // Link invoices if provided
            if (!empty($invoiceRefNos) && is_array($invoiceRefNos)) {
                foreach ($invoiceRefNos as $invoiceRefNo) {
                    if (!empty($invoiceRefNo)) {
                        // Get the invoice to calculate outstanding
                        $invoice = Artran::where('REFNO', $invoiceRefNo)->where('TYPE', 'INV')->first();
                        
                        // Calculate existing payments (excluding current receipt being created)
                        $existingPayments = DB::table('receipt_invoices')
                            ->join('receipts', 'receipt_invoices.receipt_id', '=', 'receipts.id')
                            ->where('receipt_invoices.invoice_refno', $invoiceRefNo)
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_invoices.amount_applied') ?? 0.00;

                        $outstandingAmount = (float) $invoice->NET_BIL - (float) $existingPayments;

                        // Get amount from invoice_amounts map, or use outstanding amount as default
                        $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                            ? (float) $invoiceAmounts[$invoiceRefNo] 
                            : (float) $outstandingAmount;

                        // Ensure it doesn't exceed outstanding (safety check)
                        $amountApplied = min($amountApplied, $outstandingAmount);

                        ReceiptInvoice::create([
                            'receipt_id' => $receipt->id,
                            'invoice_refno' => $invoiceRefNo,
                            'amount_applied' => $amountApplied,
                        ]);
                    }
                }
            }

            DB::commit();

            // Load receipt with invoice links for response
            $receipt->load('receiptInvoices');

            return makeResponse(201, 'Receipt created successfully.', $receipt);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Receipt creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return makeResponse(500, 'Failed to create receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(Receipt $receipt)
    {
        $user = auth()->user();
        
        // Check if user has access to this receipt's customer
        if (!$this->userHasAccessToCustomer($user, $receipt->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this receipt.', null);
        }
        
        $receipt->load(['customer', 'receiptInvoices']); // Load related customer and invoice links
        return makeResponse(200, 'Receipt retrieved successfully.', $receipt);
    }

    /**
     * Update the specified receipt in storage.
     */
    public function update(Request $request, Receipt $receipt)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('receipts')->ignore($receipt->id)],
            // Customer fields are not editable in the frontend, so we don't validate or accept them
            'receipt_date' => 'sometimes|required|date',
            'payment_type' => 'sometimes|required|string|max:255',
            'debt_amount' => 'sometimes|required|numeric|min:0',
            'transaction_amount' => 'sometimes|required|numeric|min:0',
            'paid_amount' => 'sometimes|required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_date' => 'nullable|required_if:payment_type,CHEQUE|date',
            'bank_name' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'invoice_refnos' => 'nullable|array',
            'invoice_refnos.*' => 'required|string|max:255',
            'invoice_amounts' => 'nullable|array',
            'invoice_amounts.*' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        $user = auth()->user();
        
        // Load the customer relationship to ensure it's available
        $receipt->load('customer');
        
        // Check if customer exists
        if (!$receipt->customer) {
            \Log::warning('Receipt update attempted but customer not found', [
                'receipt_id' => $receipt->id,
                'customer_id' => $receipt->customer_id,
                'user_id' => $user?->id,
            ]);
            return makeResponse(404, 'Customer not found for this receipt.', null);
        }
        
        // Check if user has access to the current receipt's customer
        // Note: Customer cannot be changed in edit mode (frontend prevents it)
        if (!$this->userHasAccessToCustomer($user, $receipt->customer)) {
            \Log::warning('Receipt update denied - user does not have access to customer', [
                'receipt_id' => $receipt->id,
                'customer_id' => $receipt->customer_id,
                'customer_code' => $receipt->customer->customer_code,
                'user_id' => $user?->id,
                'has_full_access' => $user ? hasFullAccess() : false,
            ]);
            return makeResponse(403, 'Access denied. You do not have permission to update this receipt.', null);
        }

        try {
            DB::beginTransaction();

            // Get validated data and separate invoice-related fields
            $validated = $validator->validated();
            $invoiceRefNos = $request->input('invoice_refnos');
            $invoiceAmounts = $request->input('invoice_amounts', []);

            // Remove invoice fields from validated data as they're not part of receipts table
            unset($validated['invoice_refnos'], $validated['invoice_amounts']);
            
            // Remove customer fields if they were sent (they shouldn't be editable, but remove them to be safe)
            unset($validated['customer_id'], $validated['customer_name'], $validated['customer_code']);
            
            // Normalize receipt_date to start of day in app timezone
            // Since app timezone is set to 'Asia/Kuala_Lumpur' in config/app.php,
            // Carbon::parse() will use that timezone automatically
            // MySQL timestamp column will automatically convert to UTC when storing
            if (isset($validated['receipt_date'])) {
                $dateStr = $validated['receipt_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $newDate = \Carbon\Carbon::parse($dateStr)->startOfDay();
                    // Only update if the date actually changed (compare date parts only, ignore time)
                    // This prevents unnecessary timestamp updates that could change the sort order
                    if ($receipt->receipt_date->format('Y-m-d') !== $newDate->format('Y-m-d')) {
                        $validated['receipt_date'] = $newDate;
                    } else {
                        // Date hasn't changed, don't update it to avoid unnecessary timestamp changes
                        unset($validated['receipt_date']);
                    }
                }
            }
            
            // Normalize cheque_date if provided
            if (isset($validated['cheque_date']) && !empty($validated['cheque_date'])) {
                $dateStr = $validated['cheque_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $newChequeDate = \Carbon\Carbon::parse($dateStr)->startOfDay();
                    // Only update if the date actually changed
                    if ($receipt->cheque_date && $receipt->cheque_date->format('Y-m-d') !== $newChequeDate->format('Y-m-d')) {
                        $validated['cheque_date'] = $newChequeDate;
                    } elseif (!$receipt->cheque_date) {
                        // Setting cheque_date for the first time
                        $validated['cheque_date'] = $newChequeDate;
                    } else {
                        // Date hasn't changed, don't update it
                        unset($validated['cheque_date']);
                    }
                }
            }

            // Validate partial payment amounts before updating receipt
            if ($request->has('invoice_refnos') && !empty($invoiceRefNos) && is_array($invoiceRefNos)) {
                foreach ($invoiceRefNos as $invoiceRefNo) {
                    if (!empty($invoiceRefNo)) {
                        // Get the invoice
                        $invoice = Artran::where('REFNO', $invoiceRefNo)->where('TYPE', 'INV')->first();
                        if (!$invoice) {
                            DB::rollBack();
                            return makeResponse(404, "Invoice not found: {$invoiceRefNo}", null);
                        }

                        // Calculate existing payments for this invoice (excluding current receipt being updated)
                        $existingPayments = DB::table('receipt_invoices')
                            ->join('receipts', 'receipt_invoices.receipt_id', '=', 'receipts.id')
                            ->where('receipt_invoices.invoice_refno', $invoiceRefNo)
                            ->where('receipt_invoices.receipt_id', '!=', $receipt->id) // Exclude current receipt
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_invoices.amount_applied') ?? 0.00;

                        // Calculate outstanding amount
                        $outstandingAmount = (float) $invoice->NET_BIL - (float) $existingPayments;

                        // Get the amount being applied in this receipt
                        $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                            ? (float) $invoiceAmounts[$invoiceRefNo] 
                            : (float) $outstandingAmount; // Default to full outstanding if not specified

                        // Validate: amount applied should not exceed outstanding amount
                        if ($amountApplied > $outstandingAmount + 0.01) { // Add small tolerance for floating point
                            DB::rollBack();
                            return makeResponse(422, "Payment amount (RM " . number_format($amountApplied, 2) . ") exceeds outstanding amount (RM " . number_format($outstandingAmount, 2) . ") for invoice {$invoiceRefNo}", null);
                        }

                        // Validate: amount should be positive
                        if ($amountApplied <= 0) {
                            DB::rollBack();
                            return makeResponse(422, "Payment amount must be greater than 0 for invoice {$invoiceRefNo}", null);
                        }
                    }
                }
            }

            // Update the receipt
            $receipt->update($validated);

            // Update invoice links if provided
            if ($request->has('invoice_refnos')) {
                // Delete existing invoice links
                ReceiptInvoice::where('receipt_id', $receipt->id)->delete();

                // Create new invoice links if provided
                if (!empty($invoiceRefNos) && is_array($invoiceRefNos)) {
                    foreach ($invoiceRefNos as $invoiceRefNo) {
                        if (!empty($invoiceRefNo)) {
                            // Get the invoice to calculate outstanding
                            $invoice = Artran::where('REFNO', $invoiceRefNo)->where('TYPE', 'INV')->first();
                            
                            // Calculate existing payments (excluding current receipt being updated)
                            $existingPayments = DB::table('receipt_invoices')
                                ->join('receipts', 'receipt_invoices.receipt_id', '=', 'receipts.id')
                                ->where('receipt_invoices.invoice_refno', $invoiceRefNo)
                                ->where('receipt_invoices.receipt_id', '!=', $receipt->id) // Exclude current receipt
                                ->whereNull('receipts.deleted_at')
                                ->sum('receipt_invoices.amount_applied') ?? 0.00;

                            $outstandingAmount = (float) $invoice->NET_BIL - (float) $existingPayments;

                            // Get amount from invoice_amounts map, or use outstanding amount as default
                            $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                                ? (float) $invoiceAmounts[$invoiceRefNo] 
                                : (float) $outstandingAmount;

                            // Ensure it doesn't exceed outstanding (safety check)
                            $amountApplied = min($amountApplied, $outstandingAmount);

                            ReceiptInvoice::create([
                                'receipt_id' => $receipt->id,
                                'invoice_refno' => $invoiceRefNo,
                                'amount_applied' => $amountApplied,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Load receipt with invoice links for response
            $receipt->load('receiptInvoices');

            return makeResponse(200, 'Receipt updated successfully.', $receipt);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Receipt update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return makeResponse(500, 'Failed to update receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified receipt from storage.
     */
    public function destroy(Receipt $receipt)
    {
        try {
            $receipt->delete();
            return makeResponse(200, 'Receipt deleted successfully.', null);
        } catch (\Exception $e) {
            // Log::error('Receipt deletion failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if user has access to a specific customer
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Customer  $customer
     * @return bool
     */
    private function userHasAccessToCustomer($user, \App\Models\Customer $customer)
    {
        // KBS user or admin role has full access to all customers
        if ($user && hasFullAccess()) {
            return true;
        }
        
        // Check if user is assigned to this customer
        if ($user && $user->customers()->where('customers.id', $customer->id)->exists()) {
            return true;
        }
        
        return false;
    }
}
