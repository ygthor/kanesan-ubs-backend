<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Customer;
use App\Models\Order;
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
        
        $receiptsQuery = Receipt::with(['customer:id,customer_code,company_name']);
        
        // Debug logging for filter parameters
        \Log::info('Receipt filter parameters:', [
            'customer_id' => $request->input('customer_id'),
            'customer_code' => $request->input('customer_code'),
            'invoice_refno' => $request->input('invoice_refno'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'per_page' => $request->input('per_page', 15),
        ]);
        
        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $receiptsQuery->whereHas('customer', function ($query) use ($user) {
                $query->whereIn('agent_no', [$user->name]);
            });
        }
        
        // Apply customer filter if provided (supports both customer_id and customer_code)
        if ($request->has('customer_id') && $request->customer_id) {
            // Check if it's a numeric ID or a customer code
            if (is_numeric($request->customer_id)) {
                $receiptsQuery->where('customer_id', $request->customer_id);
                \Log::info('Applied customer filter by ID:', ['customer_id' => $request->customer_id]);
            } else {
                // It's a customer code, join with customers table to filter by code
                $receiptsQuery->whereHas('customer', function($query) use ($request) {
                    $query->where('customer_code', $request->customer_id);
                });
                \Log::info('Applied customer filter by code:', ['customer_code' => $request->customer_id]);
            }
        }
        
        // Also support explicit customer_code parameter
        if ($request->has('customer_code') && $request->customer_code) {
            $receiptsQuery->whereHas('customer', function($query) use ($request) {
                $query->where('customer_code', $request->customer_code);
            });
            \Log::info('Applied customer filter by code (explicit):', ['customer_code' => $request->customer_code]);
        }
        
        // Filter by invoice_refno (order_refno) - only show receipts linked to this specific invoice
        if ($request->has('invoice_refno') && $request->invoice_refno) {
            $receiptsQuery->whereExists(function($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('receipt_orders')
                    ->whereColumn('receipt_orders.receipt_id', 'receipts.id')
                    ->where('receipt_orders.order_refno', $request->invoice_refno);
            });
            \Log::info('Applied invoice filter:', ['invoice_refno' => $request->invoice_refno]);
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
            'trade_return_amount' => 'required|numeric|min:0',
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
            
            // Normalize receipt_date to datetime in app timezone
            // Since app timezone is set to 'Asia/Kuala_Lumpur' in config/app.php,
            // Carbon::parse() will use that timezone automatically
            // MySQL timestamp column will automatically convert to UTC when storing
            if (isset($validated['receipt_date'])) {
                $dateStr = $validated['receipt_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $validated['receipt_date'] = \Carbon\Carbon::parse($dateStr)->startOfDay();
                } else {
                    // If it's datetime format (yyyy-MM-dd HH:mm:ss), parse it directly
                    // Carbon will handle the datetime string properly
                    $validated['receipt_date'] = \Carbon\Carbon::parse($dateStr);
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
                        // Get the invoice from orders table
                        $invoice = Order::where('reference_no', $invoiceRefNo)->where('type', 'INV')->first();
                        if (!$invoice) {
                            DB::rollBack();
                            return makeResponse(404, "Invoice not found: {$invoiceRefNo}", null);
                        }

                        // Calculate existing payments for this invoice from receipt_orders table
                        $existingPayments = DB::table('receipt_orders')
                            ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
                            ->where('receipt_orders.order_refno', $invoiceRefNo)
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_orders.amount_applied') ?? 0.00;

                        // Calculate outstanding amount: net_amount minus payments
                        $invoiceAmount = (float) ($invoice->net_amount ?? $invoice->grand_amount ?? 0);
                        $outstandingAmount = $invoiceAmount - (float) $existingPayments;

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
                        // Get the invoice from orders table
                        $invoice = Order::where('reference_no', $invoiceRefNo)->where('type', 'INV')->first();
                        
                        // Calculate existing payments (excluding current receipt being created)
                        $existingPayments = DB::table('receipt_orders')
                            ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
                            ->where('receipt_orders.order_refno', $invoiceRefNo)
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_orders.amount_applied') ?? 0.00;

                        // Calculate outstanding amount: net_amount minus payments
                        $invoiceAmount = (float) ($invoice->net_amount ?? $invoice->grand_amount ?? 0);
                        $outstandingAmount = $invoiceAmount - (float) $existingPayments;

                        // Get amount from invoice_amounts map, or use outstanding amount as default
                        $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                            ? (float) $invoiceAmounts[$invoiceRefNo] 
                            : (float) $outstandingAmount;

                        // Ensure it doesn't exceed outstanding (safety check)
                        $amountApplied = min($amountApplied, $outstandingAmount);

                        // Insert into receipt_orders table
                        DB::table('receipt_orders')->insert([
                            'receipt_id' => $receipt->id,
                            'order_refno' => $invoiceRefNo,
                            'amount_applied' => $amountApplied,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Update receipt.paid_amount as sum of amount_applied from receipt_orders
            $totalPaidAmount = DB::table('receipt_orders')
                ->where('receipt_id', $receipt->id)
                ->sum('amount_applied') ?? 0.00;

            // Update the receipt's paid_amount field
            $receipt->update(['paid_amount' => $totalPaidAmount]);

            DB::commit();

            // Load receipt with order links for response (manually attach receipt_orders data)
            $receiptOrders = DB::table('receipt_orders')
                ->where('receipt_id', $receipt->id)
                ->get();
            $receipt->setAttribute('receipt_orders', $receiptOrders);

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
        
        $receipt->load(['customer']); // Load related customer
        // Load receipt_orders manually
        $receiptOrders = DB::table('receipt_orders')
            ->where('receipt_id', $receipt->id)
            ->get();
        $receipt->setAttribute('receipt_orders', $receiptOrders);
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
            'trade_return_amount' => 'sometimes|required|numeric|min:0',
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
            
            // Normalize receipt_date to datetime in app timezone
            // Since app timezone is set to 'Asia/Kuala_Lumpur' in config/app.php,
            // Carbon::parse() will use that timezone automatically
            // MySQL timestamp column will automatically convert to UTC when storing
            if (isset($validated['receipt_date'])) {
                $dateStr = $validated['receipt_date'];
                // If it's date-only format (yyyy-MM-dd), parse it in app timezone at start of day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                    $newDate = \Carbon\Carbon::parse($dateStr)->startOfDay();
                    // Only update if the datetime actually changed
                    if ($receipt->receipt_date->format('Y-m-d H:i:s') !== $newDate->format('Y-m-d H:i:s')) {
                        $validated['receipt_date'] = $newDate;
                    } else {
                        // Datetime hasn't changed, don't update it to avoid unnecessary timestamp changes
                        unset($validated['receipt_date']);
                    }
                } else {
                    // If it's datetime format (yyyy-MM-dd HH:mm:ss), parse it directly
                    $newDate = \Carbon\Carbon::parse($dateStr);
                    // Only update if the datetime actually changed
                    if ($receipt->receipt_date->format('Y-m-d H:i:s') !== $newDate->format('Y-m-d H:i:s')) {
                        $validated['receipt_date'] = $newDate;
                    } else {
                        // Datetime hasn't changed, don't update it
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
                        // Get the invoice from orders table
                        $invoice = Order::where('reference_no', $invoiceRefNo)->where('type', 'INV')->first();
                        if (!$invoice) {
                            DB::rollBack();
                            return makeResponse(404, "Invoice not found: {$invoiceRefNo}", null);
                        }

                        // Calculate existing payments for this invoice (excluding current receipt being updated)
                        $existingPayments = DB::table('receipt_orders')
                            ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
                            ->where('receipt_orders.order_refno', $invoiceRefNo)
                            ->where('receipt_orders.receipt_id', '!=', $receipt->id) // Exclude current receipt
                            ->whereNull('receipts.deleted_at')
                            ->sum('receipt_orders.amount_applied') ?? 0.00;

                        // Calculate outstanding amount: net_amount minus payments
                        $invoiceAmount = (float) ($invoice->net_amount ?? $invoice->grand_amount ?? 0);
                        $outstandingAmount = $invoiceAmount - (float) $existingPayments;

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
                // Get old invoice refnos before deleting (for updating paid_amount later)
                $oldReceiptOrders = DB::table('receipt_orders')
                    ->where('receipt_id', $receipt->id)
                    ->pluck('order_refno')
                    ->toArray();

                // Delete existing invoice links
                DB::table('receipt_orders')->where('receipt_id', $receipt->id)->delete();

                // Create new invoice links if provided
                if (!empty($invoiceRefNos) && is_array($invoiceRefNos)) {
                    foreach ($invoiceRefNos as $invoiceRefNo) {
                        if (!empty($invoiceRefNo)) {
                            // Get the invoice from orders table
                            $invoice = Order::where('reference_no', $invoiceRefNo)->where('type', 'INV')->first();
                            
                            // Calculate existing payments (excluding current receipt being updated)
                            $existingPayments = DB::table('receipt_orders')
                                ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
                                ->where('receipt_orders.order_refno', $invoiceRefNo)
                                ->where('receipt_orders.receipt_id', '!=', $receipt->id) // Exclude current receipt
                                ->whereNull('receipts.deleted_at')
                                ->sum('receipt_orders.amount_applied') ?? 0.00;

                            // Calculate outstanding amount: net_amount minus payments
                            $invoiceAmount = (float) ($invoice->net_amount ?? $invoice->grand_amount ?? 0);
                            $outstandingAmount = $invoiceAmount - (float) $existingPayments;

                            // Get amount from invoice_amounts map, or use outstanding amount as default
                            $amountApplied = isset($invoiceAmounts[$invoiceRefNo]) 
                                ? (float) $invoiceAmounts[$invoiceRefNo] 
                                : (float) $outstandingAmount;

                            // Ensure it doesn't exceed outstanding (safety check)
                            $amountApplied = min($amountApplied, $outstandingAmount);

                            // Insert into receipt_orders table
                            DB::table('receipt_orders')->insert([
                                'receipt_id' => $receipt->id,
                                'order_refno' => $invoiceRefNo,
                                'amount_applied' => $amountApplied,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Update receipt.paid_amount as sum of amount_applied from receipt_orders
            $totalPaidAmount = DB::table('receipt_orders')
                ->where('receipt_id', $receipt->id)
                ->sum('amount_applied') ?? 0.00;

            // Update the receipt's paid_amount field
            $receipt->update(['paid_amount' => $totalPaidAmount]);

            DB::commit();

            // Load receipt with order links for response (manually attach receipt_orders data)
            $receiptOrders = DB::table('receipt_orders')
                ->where('receipt_id', $receipt->id)
                ->get();
            $receipt->setAttribute('receipt_orders', $receiptOrders);

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
