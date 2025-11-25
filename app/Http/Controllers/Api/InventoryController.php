<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Icitem;
use App\Models\ItemTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    /**
     * Retrieve a list of inventory items based on filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'groupId' => 'nullable|string',
            'subGroupId' => 'nullable|string',
            'inventoryType' => 'nullable|string',
        ]);

        $query = Product::query();

        // Apply filters if they are provided
        // if ($request->has('groupId')) {
        //     $query->where('group_id_text', $request->input('groupId'));
        // }

        // if ($request->has('subGroupId')) {
        //     $query->where('sub_group_id_text', $request->input('subGroupId'));
        // }

        // if ($request->has('inventoryType')) {
        //     $query->where('inventory_type', $request->input('inventoryType'));
        // }

        $query->orderBy('Product_Id');
        $query->limit('300');
        $inventoryItems = $query->get();

        // Transform the data to match the Flutter InventoryItem model structure
        $formattedData = $inventoryItems->map(function ($product) {
            return [
                'skuCode' => $product->Product_Id,
                'productName' => $product->Product_English_Name,
                'quantity' => (float) $product->CurrentStock,
                'groupId' => $product->group_id_text ?? '',
                'subGroupId' => $product->sub_group_id_text ?? '',
                'inventoryType' => $product->inventory_type ?? '',
            ];
        });

        return makeResponse(200, 'Inventory retrieved successfully.', $formattedData);
    }

    /**
     * Get current stock for an item (from products table)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStock(Request $request, $itemno)
    {
        // itemno is now a product code from products table
        $product = Product::where('code', $itemno)->where('is_active', true)->first();
        
        if (!$product) {
            return makeResponse(404, 'Product not found.', null);
        }

        // Calculate current stock from transactions
        $currentStock = $this->calculateCurrentStock($itemno);

        return makeResponse(200, 'Stock retrieved successfully.', [
            'ITEMNO' => $product->code, // For backward compatibility
            'code' => $product->code,
            'DESP' => $product->description ?? 'Unknown Product', // For backward compatibility
            'description' => $product->description,
            'current_stock' => $currentStock,
            'QTY' => $currentStock, // Use calculated stock
            'group_name' => $product->group_name,
        ]);
    }

    /**
     * Get all transactions for an item
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request, $itemno = null)
    {
        $query = ItemTransaction::with('item')
            ->orderBy('CREATED_ON', 'desc');

        if ($itemno) {
            $query->where('ITEMNO', $itemno);
        }

        // Filter by transaction type if provided
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Filter by reference if provided
        if ($request->has('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->has('reference_id')) {
            $query->where('reference_id', $request->reference_id);
        }

        // Pagination
        $perPage = $request->input('per_page', 50);
        $transactions = $query->paginate($perPage);

        return makeResponse(200, 'Transactions retrieved successfully.', $transactions);
    }

    /**
     * Stock In - Add stock to inventory
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ITEMNO' => 'required|string|exists:products,code',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        return $this->processStockTransaction(
            $request->ITEMNO,
            'in',
            abs($request->quantity), // Ensure positive
            $request->reference_type,
            $request->reference_id,
            $request->notes
        );
    }

    /**
     * Stock Out - Remove stock from inventory
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ITEMNO' => 'required|string|exists:products,code',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        // Check if sufficient stock is available
        $currentStock = $this->calculateCurrentStock($request->ITEMNO);
        if ($currentStock < $request->quantity) {
            return makeResponse(400, 'Insufficient stock. Available: ' . $currentStock, null);
        }

        return $this->processStockTransaction(
            $request->ITEMNO,
            'out',
            -abs($request->quantity), // Negative for stock out
            $request->reference_type,
            $request->reference_id,
            $request->notes
        );
    }

    /**
     * Stock Adjustment - Manual stock adjustment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ITEMNO' => 'required|string|exists:products,code',
            'quantity' => 'required|numeric',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|string|max:100',
            'notes' => 'required|string|min:3', // Notes required for adjustments
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        return $this->processStockTransaction(
            $request->ITEMNO,
            'adjustment',
            $request->quantity, // Can be positive or negative
            $request->reference_type ?? 'adjustment',
            $request->reference_id,
            $request->notes
        );
    }

    /**
     * Process a stock transaction (in/out/adjustment)
     *
     * @param string $itemno
     * @param string $transactionType
     * @param float $quantity
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param string|null $notes
     * @return \Illuminate\Http\JsonResponse
     */
    private function processStockTransaction(
        $itemno,
        $transactionType,
        $quantity,
        $referenceType = null,
        $referenceId = null,
        $notes = null
    ) {
        DB::beginTransaction();
        try {
            // Get current stock
            $stockBefore = $this->calculateCurrentStock($itemno);
            
            // Calculate new stock
            $stockAfter = $stockBefore + $quantity;

            // Create transaction record
            $transaction = ItemTransaction::create([
                'ITEMNO' => $itemno,
                'transaction_type' => $transactionType,
                'quantity' => $quantity,
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

            // Note: No need to update products table as stock is calculated from transactions
            // The products table doesn't store stock quantity

            DB::commit();

            return makeResponse(200, 'Stock transaction processed successfully.', [
                'transaction' => $transaction,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stock transaction error: ' . $e->getMessage());
            return makeResponse(500, 'Failed to process stock transaction: ' . $e->getMessage(), null);
        }
    }

    /**
     * Calculate current stock from transactions
     * Now uses product code (from products table) instead of ITEMNO from icitem
     *
     * @param string $itemno Product code from products table
     * @return float
     */
    private function calculateCurrentStock($itemno)
    {
        // Sum all quantities from transactions
        // ITEMNO in item_transactions now references products.code
        $total = ItemTransaction::where('ITEMNO', $itemno)
            ->sum('quantity');

        // If no transactions found, return 0 (no stock)
        return $total !== null ? (float)$total : 0.0;
    }

    /**
     * Get inventory summary (all items with current stock)
     * Uses new products table instead of icitem
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInventorySummary(Request $request)
    {
        // Get all active products from new products table
        $products = Product::where('is_active', true)
            ->orderBy('code')
            ->get();

        $inventory = $products->map(function ($product) {
            // Calculate current stock from transactions using product code
            $currentStock = $this->calculateCurrentStock($product->code);
            return [
                'ITEMNO' => $product->code, // Map code to ITEMNO for backward compatibility
                'DESP' => $product->description ?? 'Unknown Product', // Map description to DESP
                'current_stock' => $currentStock,
                'QTY' => $currentStock, // Use calculated stock as QTY
                'UNIT' => null, // Unit not available in products table
                'PRICE' => null, // Price not available in products table
                'code' => $product->code, // Include new field
                'description' => $product->description, // Include new field
                'group_name' => $product->group_name, // Include group name
            ];
        });

        return makeResponse(200, 'Inventory summary retrieved successfully.', $inventory);
    }
}
