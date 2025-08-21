<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artran;
use App\Models\ArTransItem;
use App\Models\Icitem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceItemController extends Controller
{
    /**
     * Display a listing of the invoice items.
     * Allows filtering by date range and customer name via the parent invoice.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Start a query on ArTransItem
        $query = ArTransItem::query()->with(['product', 'artran:id,REFNO,NAME']);

        // Join with parent 'artrans' table to filter by its properties
        $query->join('artrans', 'artrans_items.artrans_id', '=', 'artrans.id');

        // Filter by customer name from the parent invoice
        if ($customerName = $request->input('customer_name')) {
            $query->where('artrans.NAME', 'like', "%{$customerName}%");
        }

        // Filter by date range from the parent invoice
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('artrans.DATE', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('artrans.DATE', '<=', $endDate);
        }
        
        // Select only the columns from the item table to avoid conflicts
        $query->select('artrans_items.*');

        // Order by the parent invoice date, then by the item's count
        $query->orderBy('artrans.DATE', 'desc')->orderBy('artrans_items.ITEMCOUNT', 'asc');

        $items = $query->paginate($request->input('per_page', 20));

        return makeResponse(200, 'Invoice items retrieved successfully.', $items);
    }

    /**
     * Store a newly created invoice item and attach it to an invoice.
     */
    public function store(Request $request)
    {
        return $this->saveInvoiceItem($request);
    }

    /**
     * Update an existing invoice item.
     */
    public function update(Request $request, $id)
    {
        return $this->saveInvoiceItem($request, $id);
    }

    /**
     * Core logic to save (create or update) an invoice item.
     */
    private function saveInvoiceItem(Request $request, $id = null)
    {
        $validator = Validator::make($request->all(), [
            'reference_code' => 'required',
            'product_code' => 'required',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            // Find the parent invoice
            $invoice = Artran::findOrFail($request->reference_code);
            
            $product = Icitem::find($request->product_code);


            $itemData = [
                // IDs and Codes
                'artrans_id' => $invoice->artrans_id,
                'ITEMNO' => $request->product_code,
                'DESP' => $product->DESP,

                // Copied from Parent
                'REFNO' => $invoice->REFNO,
                'TYPE' => $invoice->TYPE,
                'CUSTNO' => $invoice->CUSTNO,
                'DATE' => $invoice->DATE,

                // Item specific data from request
                'QTY' => $request->quantity,
                'PRICE' => $request->unit_price,
            ];

            if ($id) {
                // UPDATE MODE
                $invoiceItem = ArTransItem::findOrFail($id);
                $invoiceItem->update($itemData);
            } else {
                // CREATE MODE
                // Determine the next item count
                $itemCount = $invoice->items()->count() + 1;
                $itemData['ITEMCOUNT'] = $itemCount;
                $invoiceItem = ArTransItem::create($itemData);
            }

            // 1. Calculate totals for the line item itself
            $invoiceItem->calculate();
            $invoiceItem->save();

            // 2. Recalculate all totals for the parent invoice
            $invoice->calculate();
            $invoice->save();

            DB::commit();

            return makeResponse(
                $id ? 200 : 201,
                $id ? 'Invoice item updated successfully.' : 'Invoice item created successfully.',
                $invoiceItem
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Invoice item save failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to save invoice item.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified invoice item.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $item = ArTransItem::with(['item', 'artran.customer'])->findOrFail($id);
        return makeResponse(200, 'Invoice item retrieved successfully.', $item);
    }

    /**
     * Remove the specified item from an invoice.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $item = ArTransItem::findOrFail($id);
            
            // Get the parent invoice *before* deleting the item
            $invoice = $item->artran;

            $item->delete();

            // After deleting, recalculate the parent invoice totals
            if ($invoice) {
                $invoice->calculate();
                $invoice->save();
            }

            DB::commit();
            return makeResponse(200, 'Invoice item deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete invoice item: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete invoice item.', ['error' => $e->getMessage()]);
        }
    }
}