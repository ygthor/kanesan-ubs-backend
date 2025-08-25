<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artran; // Changed from Order
use App\Models\ArTransItem; // Changed from OrderItem
use App\Models\Customer;
use App\Models\Product;
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
        if ($id == null) {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|max:3', // e.g., INV for Invoice, 
                'customer_id'   => 'required_without:customer_code|nullable|exists:customers,id',
                'customer_code' => 'required_without:customer_id|nullable|string|max:50',

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

            if ($request->type) {
                $invoiceData['TYPE'] = $request->type;
            }


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

            DB::commit();

            return makeResponse(
                $id ? 200 : 201,
                $id ? 'Invoice updated successfully.' : 'Invoice created successfully.',
                $invoice->load('items', 'customer')
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
        $invoice = Artran::with('items.item', 'customer')->findOrFail($id);
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
}
