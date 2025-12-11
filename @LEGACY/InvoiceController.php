<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtransCreditNote;
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

        // Use Orders table - artrans is deprecated
        $invoices = Order::with(['items.item', 'customer'])
            ->where('type', 'INV');
        
        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No invoices accessible.', $paginate ? ['data' => [], 'total' => 0] : []);
            }
            // Filter by customer IDs
            $invoices->whereIn('customer_id', $allowedCustomerIds);
        }

        // Enhanced customer search: search across multiple customer fields
        if ($customerName) {
            // Join with customers table to search across multiple fields
            $invoices->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                ->where(function($query) use ($customerName) {
                    // Search in orders.customer_name (denormalized customer name)
                    $query->where('orders.customer_name', 'like', "%{$customerName}%")
                        // Search in customers table fields
                        ->orWhere('customers.name', 'like', "%{$customerName}%")
                        ->orWhere('customers.company_name', 'like', "%{$customerName}%")
                        ->orWhere('customers.company_name2', 'like', "%{$customerName}%")
                        ->orWhere('customers.email', 'like', "%{$customerName}%")
                        ->orWhere('customers.phone', 'like', "%{$customerName}%")
                        ->orWhere('customers.telephone1', 'like', "%{$customerName}%")
                        ->orWhere('customers.telephone2', 'like', "%{$customerName}%")
                        ->orWhere('customers.customer_code', 'like', "%{$customerName}%");
                });
            // Select only orders columns to avoid conflicts with joined table
            $invoices->select('orders.*');
        }
        if ($CUSTNO) {
            $invoices->where('customer_code', 'like', "%{$CUSTNO}%");
        }

        // Filter by date range (using the 'order_date' column)
        if ($startDate) {
            $invoices->whereDate('order_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoices->whereDate('order_date', '<=', $endDate);
        }

        // Filter by invoice type (using the 'type' column)
        if ($invoiceType) {
            $types = explode(',', $invoiceType);
            $invoices->whereIn('type', $types);
        }

        $invoices->orderBy('order_date', 'desc')->orderBy('reference_no', 'desc');

        if ($paginate) {
            $paginatedInvoices = $invoices->paginate($request->input('per_page', 15));
            // Adjust amounts for INV invoices and add payment/credit note info
            $paginatedInvoices->getCollection()->transform(function ($invoice) {
                return $this->enrichInvoiceWithPaymentInfo($invoice);
            });
        } else {
            $paginatedInvoices = $invoices->get();
            // Adjust amounts for INV invoices and add payment/credit note info
            $paginatedInvoices->transform(function ($invoice) {
                return $this->enrichInvoiceWithPaymentInfo($invoice);
            });
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


            // Normalize invoice date to prevent timezone conversion issues
            // If date is provided, parse it in app timezone at start of day
            // Since app timezone is set to 'Asia/Kuala_Lumpur' in config/app.php,
            // Carbon::parse() will use that timezone automatically
            $invoiceDate = $request->date ?? now();
            if ($request->has('date') && $request->date) {
                $dateStr = $request->date;
                // Parse date in app timezone at start of day to prevent day shift
                $invoiceDate = \Carbon\Carbon::parse($dateStr)->startOfDay();
            }

            // Data for Artran (header)
            $invoiceData = [

                // 'branch_id' => $request->branch_id ?? 0,
                'CUSTNO' => $customer->customer_code, // Map to legacy customer code
                'NAME' => $customer->company_name, // Company name (not customer name)
                'EMAIL' => $customer->email, // Customer email
                'AREA' => $customer->territory, // Territory/Area from customer
                'DATE' => $invoiceDate,
                'NOTE' => $request->remarks,
                'TERM' => $customer->payment_term, // Payment term from customer
                'USERID' => auth()->user()->id ?? '', // Assuming you have auth
                // Other header fields like 'tax1_percentage' can be added here
            ];

            // Set TYPE from request, default to 'INV' if not provided
            $requestType = $request->input('type');
            $invoiceData['TYPE'] = !empty($requestType) ? strtoupper(trim($requestType)) : 'INV';
            
            // Log for debugging - log all request data
            \Log::info('Invoice creation - Request data:', [
                'all_request_data' => $request->all(),
                'request_type' => $requestType,
                'request_type_raw' => $request->input('type'),
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
                        
                        // Get product info from products table using product_no
                        // OrderItem.product_no should match Product.code
                        $product = Product::where('code', $orderItem->product_no)->first();
                        
                        if (!$product) {
                            \Log::warning("Product not found for order item. Order Item ID: {$orderItem->id}, Product No: {$orderItem->product_no}");
                            // Continue with next item, or skip this one
                            continue;
                        }
                        
                        // Verify that OrderItem.product_no matches ArTransItem.ITEMNO/TRANCODE (both reference Product.code)
                        // This ensures invoice items and order items refer to the same product
                        
                        // Map OrderItem to ArTransItem
                        $baseDescription = $orderItem->product_name ?? $product->description ?? $orderItem->description ?? 'Unknown Product';
                        // Add trade return indicator to description if it's a trade return
                        if ($orderItem->is_trade_return) {
                            $returnType = $orderItem->trade_return_is_good ? 'Good' : 'Bad';
                            $baseDescription .= ' (TRADE RETURN - ' . $returnType . ')';
                        }
                        
                        $invoiceItemData = [
                            'artrans_id' => $invoice->artrans_id ?? null, // May be auto-generated
                            'ITEMNO' => $orderItem->product_no, // Product code/item number - references Product.code
                            'TRANCODE' => $orderItem->product_no, // Product code (same as ITEMNO) - references Product.code
                            'DESP' => $baseDescription,
                            
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
                        
                        // Handle trade returns: if order item is a trade return, set SIGN to -1 and recalculate AMT_BIL
                        // This ensures trade returns are displayed with negative amounts and deducted from total
                        if ($orderItem->is_trade_return) {
                            $invoiceItem->SIGN = -1;
                            // Recalculate AMT_BIL with negative sign for trade returns
                            $lineTotal = $invoiceItem->QTY * $invoiceItem->PRICE;
                            $invoiceItem->AMT_BIL = $lineTotal * -1;
                        }
                        
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

            // If this is a credit note (CN/CR) and invoice_refno is provided, link them
            // This works for both CREATE and UPDATE modes
            if (in_array($invoiceData['TYPE'], ['CN', 'CR']) && $request->has('invoice_refno') && $request->invoice_refno) {
                try {
                    // Get credit note artrans_id
                    // In CREATE mode, artrans_id might not be loaded yet (since primary key is REFNO, not artrans_id)
                    // In UPDATE mode, artrans_id should already be available
                    $creditNoteArtransId = $invoice->artrans_id;
                    
                    // If artrans_id is not available (usually in CREATE mode), refresh or query from DB
                    if (!$creditNoteArtransId) {
                        // Refresh to load artrans_id (auto-increment field)
                        $invoice->refresh();
                        $creditNoteArtransId = $invoice->artrans_id;
                        
                        // Fallback: query directly from database if refresh didn't work
                        if (!$creditNoteArtransId) {
                            $creditNoteArtransId = Artran::where('REFNO', $invoice->REFNO)->value('artrans_id');
                        }
                    }
                    
                    if ($creditNoteArtransId) {
                        $this->linkCreditNoteToInvoice($creditNoteArtransId, $request->invoice_refno);
                        \Log::info("Credit note linking: CN {$invoice->REFNO} (artrans_id: {$creditNoteArtransId}) -> Invoice {$request->invoice_refno}");
                    } else {
                        \Log::warning("Could not find artrans_id for credit note {$invoice->REFNO}. Cannot create link.");
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to link credit note to invoice: ' . $e->getMessage(), [
                        'credit_note_refno' => $invoice->REFNO,
                        'invoice_refno' => $request->invoice_refno,
                        'exception' => $e->getTraceAsString()
                    ]);
                    // Don't fail the invoice creation/update if linking fails
                }
            } else {
                // Log when invoice_refno is not provided for CN
                if (in_array($invoiceData['TYPE'], ['CN', 'CR'])) {
                    \Log::info("Credit note {$invoice->REFNO} saved without invoice_refno parameter");
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
     * Only uses the orders table - artrans table is deprecated and will be removed.
     *
     * @param  string  $id  The reference_no from orders table
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = auth()->user();
        
        // Only use orders table - artrans is deprecated
        $order = Order::with(['items.item', 'customer'])
            ->where('reference_no', $id)
            ->where('type', 'INV')
            ->first();
        
        if (!$order) {
            return makeResponse(404, 'Invoice not found.', null);
        }
        
        // Convert order to invoice format
        $invoice = $this->convertOrderToInvoice($order);
        
        // Check if user has access to this invoice's customer
        if (!$this->userHasAccessToCustomer($user, $invoice->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this invoice.', null);
        }
        
        // Enrich invoice with payment and credit note information
        $enrichedInvoice = $this->enrichInvoiceWithPaymentInfo($invoice);
        $invoiceData = $enrichedInvoice->toArray();
        
        // Get linked credit notes if any
        $creditNotes = ArtransCreditNote::where('invoice_id', $invoice->artrans_id)
            ->with(['creditNote.items.detail', 'creditNote.items.item', 'creditNote.customer'])
            ->get()
            ->map(function ($link) {
                return $link->creditNote;
            })
            ->filter()
            ->values();
        
        $invoiceData['credit_notes'] = $creditNotes;
        
        return makeResponse(200, 'Invoice retrieved successfully.', $invoiceData);
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
        $invoices = Artran::with(['items.detail', 'items.item', 'customer'])
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
        $invoice = Artran::with(['items.detail', 'items.item', 'customer'])
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
        $invoices = Artran::with(['items.item', 'items.detail', 'customer']);
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

    /**
     * Link a credit note to an invoice
     *
     * @param int $creditNoteArtransId The artrans_id of the credit note
     * @param string $invoiceRefNo The REFNO of the invoice to link to
     * @return ArtransCreditNote
     * @throws \Exception
     */
    private function linkCreditNoteToInvoice($creditNoteArtransId, $invoiceRefNo)
    {
        // Find the invoice by REFNO
        $invoice = Artran::where('REFNO', $invoiceRefNo)
            ->where('TYPE', 'INV')
            ->first();
        
        if (!$invoice) {
            throw new \Exception("Invoice with REFNO '{$invoiceRefNo}' not found or is not an INV type");
        }
        
        // Verify the credit note exists
        $creditNote = Artran::where('artrans_id', $creditNoteArtransId)
            ->whereIn('TYPE', ['CN', 'CR'])
            ->first();
        
        if (!$creditNote) {
            throw new \Exception("Credit note with artrans_id '{$creditNoteArtransId}' not found or is not a CN/CR type");
        }
        
        // Check if link already exists
        $existingLink = ArtransCreditNote::where('credit_note_id', $creditNoteArtransId)->first();
        if ($existingLink) {
            // If link exists but points to a different invoice, update it
            if ($existingLink->invoice_id != $invoice->artrans_id) {
                $existingLink->invoice_id = $invoice->artrans_id;
                $existingLink->save();
                \Log::info("Updated link: Credit note {$creditNote->REFNO} is now linked to invoice {$invoice->REFNO}");
                return $existingLink;
            } else {
                // Already linked to the same invoice
                \Log::info("Credit note {$creditNote->REFNO} is already linked to invoice {$invoice->REFNO}");
                return $existingLink;
            }
        }
        
        // Create the link
        $link = ArtransCreditNote::create([
            'invoice_id' => $invoice->artrans_id,
            'credit_note_id' => $creditNoteArtransId,
        ]);
        
        \Log::info("Linked credit note {$creditNote->REFNO} to invoice {$invoice->REFNO}");
        
        return $link;
    }

    /**
     * Adjust invoice amounts by deducting linked credit notes and add payment information.
     * 
     * @param \App\Models\Artran $invoice
     * @return \App\Models\Artran
     */
    private function enrichInvoiceWithPaymentInfo($invoice)
    {
        // Store original amounts before any adjustments
        $originalNetBil = (float) $invoice->NET_BIL;
        $originalGrandBil = (float) $invoice->GRAND_BIL;
        
        // Calculate total credit notes amount
        $totalCreditNotes = 0;
        if ($invoice->TYPE === 'INV' && $invoice->artrans_id) {
            $totalCreditNotes = $invoice->getTotalCreditNotesAmount();
        }
        
        // Calculate total payments made
        $totalPayments = 0;
        if ($invoice->REFNO) {
            $totalPayments = (float) DB::table('receipt_invoices')
                ->join('receipts', 'receipt_invoices.receipt_id', '=', 'receipts.id')
                ->where('receipt_invoices.invoice_refno', $invoice->REFNO)
                ->whereNull('receipts.deleted_at')
                ->sum('receipt_invoices.amount_applied') ?? 0;
        }
        
        // Adjust amounts for INV invoices (deduct credit notes)
        if ($invoice->TYPE === 'INV' && $totalCreditNotes > 0) {
            $originalGrossBil = (float) $invoice->GROSS_BIL;
            $originalTax1Bil = (float) $invoice->TAX1_BIL;
            
            // Adjust amounts by deducting credit notes
            $invoice->NET_BIL = max(0, $originalNetBil - $totalCreditNotes);
            $invoice->GRAND_BIL = max(0, $originalGrandBil - $totalCreditNotes);
            $invoice->GROSS_BIL = max(0, $originalGrossBil - $totalCreditNotes);
            
            // Proportionally adjust tax based on original GRAND_BIL
            if ($originalGrandBil > 0) {
                $creditNoteRatio = $totalCreditNotes / $originalGrandBil;
                $invoice->TAX1_BIL = max(0, $originalTax1Bil * (1 - $creditNoteRatio));
            }
        }
        
        // Calculate net amount (after credit notes) and outstanding amount
        $netAmount = $invoice->TYPE === 'INV' 
            ? (float) $invoice->NET_BIL  // Already adjusted
            : $originalNetBil;  // For CN/CR, use original
        
        $outstandingAmount = max(0, $netAmount - $totalPayments);
        
        // Add these as attributes to the invoice model for JSON serialization
        $invoice->setAttribute('original_amount', $originalNetBil);
        $invoice->setAttribute('credit_note_amount', $totalCreditNotes);
        $invoice->setAttribute('net_amount', $netAmount);
        $invoice->setAttribute('paid_amount', $totalPayments);
        $invoice->setAttribute('outstanding_amount', $outstandingAmount);
        
        return $invoice;
    }

    /**
     * Convert an Order to an Artran-like format for invoice display.
     * This allows viewing invoices from the orders table (new table) instead of just artrans (old table).
     *
     * @param \App\Models\Order $order
     * @return \App\Models\Artran
     */
    private function convertOrderToInvoice($order)
    {
        // Check if there's a linked artrans record via invoice_orders pivot table
        $linkedArtran = null;
        $invoiceOrders = DB::table('invoice_orders')
            ->where('order_id', $order->id)
            ->first();
        
        if ($invoiceOrders) {
            // Try to find the linked artrans record by REFNO
            $linkedArtran = Artran::where('REFNO', $invoiceOrders->invoice_refno)->first();
        }
        
        // Create a new Artran instance with order data
        $invoice = new Artran();
        
        // Use artrans_id from linked artrans if available, otherwise use order id
        // This ensures credit notes can be found if they're linked to the artrans record
        $invoice->artrans_id = $linkedArtran ? $linkedArtran->artrans_id : $order->id;
        $invoice->REFNO = $order->reference_no;
        $invoice->TYPE = $order->type ?? 'INV';
        $invoice->CUSTNO = $order->customer_code;
        $invoice->NAME = $order->customer_name;
        $invoice->DATE = $order->order_date ? $order->order_date->format('Y-m-d') : now()->format('Y-m-d');
        $invoice->GROSS_BIL = (float) ($order->gross_amount ?? 0);
        $invoice->TAX1_BIL = (float) ($order->tax1 ?? 0);
        $invoice->GRAND_BIL = (float) ($order->grand_amount ?? 0);
        $invoice->NET_BIL = (float) ($order->net_amount ?? $order->grand_amount ?? 0);
        $invoice->DEBIT_BIL = (float) ($order->net_amount ?? $order->grand_amount ?? 0);
        $invoice->CREDIT_BIL = 0;
        $invoice->NOTE = $order->remarks;
        
        // Set customer relationship
        $invoice->setRelation('customer', $order->customer);
        
        // Convert order items to ArTransItem format
        $invoiceItems = [];
        $itemCount = 1;
        foreach ($order->items as $orderItem) {
            $invoiceItem = new ArTransItem();
            $invoiceItem->id = $orderItem->id;
            $invoiceItem->REFNO = $order->reference_no;
            $invoiceItem->TYPE = $order->type ?? 'INV';
            $invoiceItem->CUSTNO = $order->customer_code;
            $invoiceItem->DATE = $order->order_date ? $order->order_date->format('Y-m-d') : now()->format('Y-m-d');
            $invoiceItem->ITEMCOUNT = $itemCount++;
            $invoiceItem->ITEMNO = $orderItem->product_no;
            $invoiceItem->TRANCODE = $orderItem->product_no;
            $invoiceItem->DESP = $orderItem->product_name ?? $orderItem->description ?? 'Unknown Product';
            $invoiceItem->QTY = (float) $orderItem->quantity;
            $invoiceItem->PRICE = (float) $orderItem->unit_price;
            $invoiceItem->AMT_BIL = (float) $orderItem->amount;
            $invoiceItem->DISAMT = (float) ($orderItem->discount ?? 0);
            $invoiceItem->SIGN = $orderItem->is_trade_return ? -1 : 1;
            
            // Set item relationship (icitem)
            if ($orderItem->relationLoaded('item')) {
                $invoiceItem->setRelation('item', $orderItem->item);
            }
            
            // Set detail relationship if it exists (for trade returns)
            if ($orderItem->is_trade_return) {
                $detail = new \App\Models\ArTransItemDetail();
                $detail->is_trade_return = true;
                $detail->return_status = $orderItem->trade_return_is_good ? 'good' : 'bad';
                $invoiceItem->setRelation('detail', $detail);
            }
            
            $invoiceItems[] = $invoiceItem;
        }
        
        // Set items relationship
        $invoice->setRelation('items', collect($invoiceItems));
        
        return $invoice;
    }
}
