<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artran;
use App\Models\ArTransItem;
use App\Models\Icitem;
use App\Models\ItemTransaction;
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
                $oldQuantity = $invoiceItem->QTY;
                $invoiceItem->update($itemData);
                
                // Adjust stock: add back old quantity, deduct new quantity
                $quantityDifference = $oldQuantity - $request->quantity;
                if ($quantityDifference != 0) {
                    $this->processStockTransaction(
                        $request->product_code,
                        $quantityDifference > 0 ? 'in' : 'out',
                        abs($quantityDifference),
                        'invoice',
                        $invoice->REFNO,
                        'Invoice item updated - quantity changed from ' . $oldQuantity . ' to ' . $request->quantity
                    );
                }
            } else {
                // CREATE MODE
                // Determine the next item count
                $itemCount = $invoice->items()->count() + 1;
                $itemData['ITEMCOUNT'] = $itemCount;
                $invoiceItem = ArTransItem::create($itemData);
                
                // Auto-deduct stock when invoice item is created
                $this->processStockTransaction(
                    $request->product_code,
                    'out',
                    $request->quantity,
                    'invoice',
                    $invoice->REFNO,
                    'Stock deducted for invoice ' . $invoice->REFNO
                );
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
            
            // Store item details before deletion for stock adjustment
            $itemno = $item->ITEMNO;
            $quantity = $item->QTY;
            $refno = $invoice ? $invoice->REFNO : null;

            $item->delete();
            
            // Add back stock when invoice item is deleted
            if ($itemno && $quantity > 0) {
                $this->processStockTransaction(
                    $itemno,
                    'in',
                    $quantity,
                    'invoice',
                    $refno,
                    'Stock returned - invoice item deleted from invoice ' . $refno
                );
            }

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
            $quantityChange = $transactionType === 'out' ? -abs($quantity) : abs($quantity);
            $stockAfter = $stockBefore + $quantityChange;
            
            // Check if sufficient stock for stock out
            if ($transactionType === 'out' && $stockAfter < 0) {
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
            \Log::error('Stock transaction error in invoice item: ' . $e->getMessage());
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