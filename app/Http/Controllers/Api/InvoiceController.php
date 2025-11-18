<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artran; // Changed from Order
use App\Models\ArTransItem; // Changed from OrderItem
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Icitem;
use App\Models\ItemTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PDF;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the invoices.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $CUSTNO = $request->input('CUSTNO');
        $customerName = $request->input('customer_name');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $invoiceType = $request->input('invoice_type'); // e.g., 'SO', 'IV'
        $paginate = $request->input('paginate');

        if ($paginate === null) {
            $paginate = true;
        }

        // Start building the query on the Artran model
        $invoices = Artran::with('items', 'customer');
        
        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No invoices accessible.', $paginate ? ['data' => [], 'total' => 0] : []);
            }
            // Filter by customer IDs - assuming Artran has a customer_id field or CUSTNO field
            $invoices->whereIn('CUSTNO', $user->customers()->pluck('customers.customer_code')->toArray());
        }

        // Filter by customer name (using the 'NAME' column in 'artrans')
        if ($customerName) {
            $invoices->where('NAME', 'like', "%{$customerName}%");
        }
        if ($CUSTNO) {
            $invoices->where('CUSTNO', 'like', "{$CUSTNO}");
        }

        // Filter by date range (using the 'DATE' column)
        if ($startDate) {
            $invoices->whereDate('DATE', '>=', $startDate);
        }
        if ($endDate) {
            $invoices->whereDate('DATE', '<=', $endDate);
        }

        // Filter by invoice type (using the 'TYPE' column)
        if ($invoiceType) {
            $types = explode(',', $invoiceType);
            $invoices->whereIn('TYPE', $types);
        }

        $invoices->orderBy('DATE', 'desc')->orderBy('REFNO', 'desc');

        if ($paginate) {
            $paginatedInvoices = $invoices->paginate($request->input('per_page', 15));
        } else {
            $paginatedInvoices = $invoices->get();
        }


        return makeResponse(200, 'Invoices retrieved successfully.', $paginatedInvoices);
    }

    /**
     * Store a newly created invoice in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->saveInvoice($request);
    }

    /**
     * Update an existing invoice in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return $this->saveInvoice($request, $id);
    }

    /**
     * Core logic to save (create or update) an invoice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null $id
     * @return \Illuminate\Http\JsonResponse
     */
    private function saveInvoice(Request $request, $id = null)
    {
        $user = auth()->user();
        
        if ($id == null) {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|max:3', // e.g., INV for Invoice, 
                'customer_id'   => 'required_without:customer_code|nullable|exists:customers,id',
                'customer_code' => 'required_without:customer_id|nullable|string|max:50',
                'order_id' => 'nullable|exists:orders,id', // Optional: link to order if invoice created from order

                'date' => 'nullable|date',
                'remarks' => 'nullable|string',
                // 'items' => 'required|array|min:1',
                // 'items.*.product_id' => 'required|exists:products,id',
                // 'items.*.quantity' => 'required|numeric|min:0',
                // 'items.*.unit_price' => 'required|numeric|min:0',
            ]);
        } else {
            $validator = Validator::make($request->all(), []);
        }


        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            if ($request->customer_code) {
                $customer = Customer::fromCode($request->customer_code);
            }
            if ($request->customer_id) {
                $customer = Customer::find($request->customer_id);
            }
            
            // Check if user has access to this customer
            if (!$this->userHasAccessToCustomer($user, $customer)) {
                return makeResponse(403, 'Access denied. You do not have permission to create/update invoices for this customer.', null);
            }


            // Data for Artran (header)
            $invoiceData = [

                // 'branch_id' => $request->branch_id ?? 0,
                'CUSTNO' => $customer->customer_code, // Map to legacy customer code
                'NAME' => $customer->name, // Denormalized name
                'DATE' => $request->date ?? now(),
                'NOTE' => $request->remarks,
                'TERM' => $customer->term, // Get term from customer
                'USERID' => auth()->user()->id ?? '', // Assuming you have auth
                // Other header fields like 'tax1_percentage' can be added here
            ];

            // Set TYPE from request, default to 'INV' if not provided
            $invoiceData['TYPE'] = $request->input('type', 'INV');
            
            // Log for debugging
            \Log::info('Invoice creation - Type received:', [
                'request_type' => $request->input('type'),
                'final_type' => $invoiceData['TYPE']
            ]);


            if ($id) {
                // UPDATE MODE
                $invoice = Artran::findOrFail($id);
                $invoice->update($invoiceData);
            } else {
                // CREATE MODE
                $invoiceData['REFNO'] = Artran::generateReferenceNumber($invoiceData['TYPE']);
                $invoice = Artran::create($invoiceData);
            }

            // Before processing new items, remove old ones in update mode
            // if ($id) {
            //     $invoice->items()->delete();
            // }

            // $item_count = 1;
            // $items = $request->input('items', []);

            // foreach ($items as $itemData) {
            //     $product = Product::find($itemData['product_id']);
            //     if (!$product) {
            //         DB::rollBack();
            //         return makeResponse(404, 'Product not found with ID: ' . $itemData['product_id']);
            //     }

            //     // Create ArTransItem (line item)
            //     $orderItem = $invoice->items()->create([
            //         'REFNO' => $invoice->REFNO,
            //         'TYPE' => $invoice->TYPE,
            //         'CUSTNO' => $invoice->CUSTNO,
            //         'DATE' => $invoice->DATE,
            //         'ITEMCOUNT' => $item_count,
            //         'TRANCODE' => $product->product_no, // Product code
            //         'DESP' => $product->product_name, // Product description
            //         'QTY' => $itemData['quantity'],
            //         'PRICE' => $itemData['unit_price'],
            //         // Map boolean flags from request if you have corresponding columns
            //         // 'is_free_good' => $itemData['is_free_good'] ?? false,
            //         // 'is_trade_return' => $itemData['is_trade_return'] ?? false,
            //     ]);

            //     // Calculate amounts for the line item
            //     $orderItem->calculate();
            //     $orderItem->save();
            //     $item_count++;
            // }

            // // Recalculate totals for the main invoice
            // $invoice->calculate();
            // $invoice->save();

            // Link invoice to order and create invoice items from order items if order_id is provided (only in create mode)
            if (!$id && $request->order_id) {
                $order = Order::with('items')->find($request->order_id);
                if ($order) {
                    // Link invoice to order in pivot table
                    $invoice->orders()->attach($order->id);
                    
                    // Create invoice items from order items
                    $itemCount = 1;
                    foreach ($order->items as $orderItem) {
                        // Skip free goods if needed (optional - you can remove this if you want to include them)
                        // if ($orderItem->is_free_good) {
                        //     continue;
                        // }
                        
                        // Get product info from Icitem table using product_no
                        // OrderItem.product_no should match Icitem.ITEMNO
                        $product = Icitem::find($orderItem->product_no);
                        
                        if (!$product) {
                            \Log::warning("Product not found for order item. Order Item ID: {$orderItem->id}, Product No: {$orderItem->product_no}");
                            // Continue with next item, or skip this one
                            continue;
                        }
                        
                        // Verify that OrderItem.product_no matches ArTransItem.ITEMNO/TRANCODE (both reference Icitem.ITEMNO)
                        // This ensures invoice items and order items refer to the same product
                        
                        // Map OrderItem to ArTransItem
                        $invoiceItemData = [
                            'artrans_id' => $invoice->artrans_id ?? null, // May be auto-generated
                            'ITEMNO' => $orderItem->product_no, // Product code/item number - references Icitem.ITEMNO
                            'TRANCODE' => $orderItem->product_no, // Product code (same as ITEMNO) - references Icitem.ITEMNO
                            'DESP' => $orderItem->product_name ?? $product->DESP ?? $orderItem->description ?? 'Unknown Product',
                            
                            // Copied from parent invoice
                            'REFNO' => $invoice->REFNO,
                            'TYPE' => $invoice->TYPE,
                            'CUSTNO' => $invoice->CUSTNO,
                            'DATE' => $invoice->DATE,
                            'ITEMCOUNT' => $itemCount,
                            
                            // Item specific data from order item
                            'QTY' => $orderItem->quantity,
                            'PRICE' => $orderItem->unit_price,
                            
                            // Note: Discount from order item is not directly mapped to ArTransItem
                            // as ArTransItem doesn't have a discount field at line level
                            // The discount would be applied at invoice header level if needed
                        ];
                        
                        // Create the invoice item
                        $invoiceItem = ArTransItem::create($invoiceItemData);
                        
                        // Calculate amounts for the line item
                        $invoiceItem->calculate();
                        $invoiceItem->save();
                        
                        // Auto-process stock when invoice item is created from order
                        // Determine transaction type based on invoice type
                        $isCreditNote = in_array($invoice->TYPE, ['CN', 'CR']);
                        $transactionType = $isCreditNote ? 'invoice_return' : 'invoice_sale';
                        
                        $this->processStockTransaction(
                            $orderItem->product_no,
                            $transactionType,
                            $orderItem->quantity,
                            'invoice',
                            $invoice->REFNO,
                            $isCreditNote
                                ? 'Stock returned for credit note ' . $invoice->REFNO . ' (from order)'
                                : 'Stock deducted for invoice ' . $invoice->REFNO . ' (from order)'
                        );
                        
                        $itemCount++;
                    }
                    
                    // Recalculate totals for the invoice after adding all items
                    $invoice->calculate();
                    $invoice->save();
                }
            }

            DB::commit();

            return makeResponse(
                $id ? 200 : 201,
                $id ? 'Invoice updated successfully.' : 'Invoice created successfully.',
                $invoice->load('items', 'customer', 'orders')
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Invoice save failed: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return makeResponse(500, 'Failed to save invoice.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = auth()->user();
        $invoice = Artran::with('items.item', 'customer')->findOrFail($id);
        
        // Check if user has access to this invoice's customer
        if (!$this->userHasAccessToCustomer($user, $invoice->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this invoice.', null);
        }
        
        return makeResponse(200, 'Invoice retrieved successfully.', $invoice);
    }

    /**
     * Remove the specified invoice from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $invoice = Artran::findOrFail($id);
            $invoice->items()->delete(); // Delete related items
            $invoice->delete();         // Delete the invoice header
            DB::commit();
            return makeResponse(200, 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete invoice: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete invoice.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate a batch PDF for a given list of invoice reference numbers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function batchPrint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ref_nos'   => 'required|array|min:1',
            'ref_nos.*' => 'string|exists:artrans,REFNO', // Ensure each REFNO exists
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        $validated = $validator->validated();

        // Eager load relationships to avoid N+1 query problems
        $invoices = Artran::with('items', 'customer')
            ->whereIn('REFNO', $validated['ref_nos'])
            ->orderBy('DATE', 'asc') // Order them consistently
            ->get();

        if ($invoices->isEmpty()) {
            return makeResponse(404, 'No invoices found for the provided reference numbers.');
        }

        // Load the view and pass the data
        $pdf = PDF::loadView('pdf.invoices_batch_print', ['invoices' => $invoices]);

        // Set paper size and orientation if needed
        $pdf->setPaper('a4', 'portrait');

        // Return the PDF as a stream to the client
        return $pdf->stream('invoices.pdf');
    }

    public function printInvoice($refNo)
    {
        // Find the invoice by its REFNO or fail with a 404 error
        $invoice = Artran::with('items', 'customer')
            ->where('REFNO', $refNo)
            ->firstOrFail();

        // Load the view and pass the single invoice object
        $pdf = PDF::loadView('pdf.single_invoice', ['invoice' => $invoice]);

        $pdf->setPaper('a4', 'portrait');

        // Return the PDF to be viewed in the browser/client
        return $pdf->stream("invoice-{$refNo}.pdf");
    }


    public function printInvoiceReport(Request $request)
    {

        $custNo    = $request->input('CUSTNO');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        // Fetch invoices with items + customer in one go
        $invoices = Artran::with('items', 'customer');
        if (!empty($custNo)) {
            $invoices->where('CUSTNO', $custNo);
        }
        if (!empty($startDate)) {
            $invoices->whereDate('DATE', '>=', $startDate);
        }
        if (!empty($startDate)) {
            $invoices->whereDate('DATE', '<=', $endDate);
        }

        $cusotmer = Customer::fromCode($custNo);


        $invoices = $invoices
            ->SelectRaw('
                TYPE,
                REFNO,
                CUSTNO,
                NAME,
                DATE,
                NOTE,
                DEBIT_BIL,
                CREDIT_BIL,
                NET_BIL
            ')
            ->orderBy('DATE', 'asc')
            ->orderBy('REFNO', 'asc')
            ->get();

        if ($invoices->isEmpty()) {
            return makeResponse(404, 'No invoices found for this customer in the given date range.');
        }
        
        // Load a Blade view for batch report
        // Create `resources/views/pdf/invoices_report.blade.php`
        $pdf = PDF::loadView('pdf.invoices_report', [
            'invoices'   => $invoices,
            'customer'   => $cusotmer,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("invoices_report_{$custNo}_{$startDate}_to_{$endDate}.pdf");
    }

    /**
     * Check if user has access to a specific customer
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Customer  $customer
     * @return bool
     */
    private function userHasAccessToCustomer($user, Customer $customer)
    {
        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            return true;
        }
        
        // Check if user is assigned to this customer
        if ($user && $user->customers()->where('customers.id', $customer->id)->exists()) {
            return true;
        }
        
        return false;
    }

    /**
     * Process stock transaction for invoice items
     *
     * @param string $itemno
     * @param string $transactionType
     * @param float $quantity
     * @param string $referenceType
     * @param string $referenceId
     * @param string $notes
     * @return void
     */
    private function processStockTransaction(
        $itemno,
        $transactionType,
        $quantity,
        $referenceType,
        $referenceId,
        $notes
    ) {
        try {
            // Get current stock
            $stockBefore = $this->calculateCurrentStock($itemno);
            
            // Calculate new stock
            // invoice_sale = stock out (negative), invoice_return = stock in (positive)
            // out = stock out (negative), in = stock in (positive), adjustment = can be either
            $quantityChange = in_array($transactionType, ['out', 'invoice_sale']) 
                ? -abs($quantity) 
                : abs($quantity);
            $stockAfter = $stockBefore + $quantityChange;
            
            // Check if sufficient stock for stock out
            if (in_array($transactionType, ['out', 'invoice_sale']) && $stockAfter < 0) {
                \Log::warning("Insufficient stock for item {$itemno}. Current: {$stockBefore}, Required: {$quantity}");
                // Still process but log warning
            }

            // Create transaction record
            ItemTransaction::create([
                'ITEMNO' => $itemno,
                'transaction_type' => $transactionType,
                'quantity' => $quantityChange,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'CREATED_BY' => auth()->user()->id ?? null,
                'UPDATED_BY' => auth()->user()->id ?? null,
                'CREATED_ON' => now(),
                'UPDATED_ON' => now(),
            ]);

            // Update icitem QTY field
            $item = Icitem::find($itemno);
            if ($item) {
                $item->QTY = $stockAfter;
                $item->UPDATED_BY = auth()->user()->id ?? null;
                $item->UPDATED_ON = now();
                $item->save();
            }
        } catch (\Exception $e) {
            \Log::error('Stock transaction error in invoice: ' . $e->getMessage());
            // Don't throw - allow invoice to be saved even if stock transaction fails
        }
    }

    /**
     * Calculate current stock from transactions
     *
     * @param string $itemno
     * @return float
     */
    private function calculateCurrentStock($itemno)
    {
        $total = ItemTransaction::where('ITEMNO', $itemno)->sum('quantity');
        
        if ($total === null) {
            $item = Icitem::find($itemno);
            return $item ? (float)($item->QTY ?? 0) : 0;
        }

        return (float)$total;
    }
}
