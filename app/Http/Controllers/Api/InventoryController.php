<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Icitem;
use App\Models\ItemTransaction;
use App\Models\User;
use App\Services\StockService;
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

        // Get agent_no from request or current user (required for agent-specific stock)
        $agentNo = $request->input('agent_no');
        if (!$agentNo && auth()->user()) {
            $agentNo = auth()->user()->name ?? auth()->user()->username;
        }

        if (!$agentNo || empty($agentNo)) {
            return makeResponse(400, 'Agent number is required for stock calculations.', null);
        }

        // Calculate current stock from transactions (agent-specific)
        $currentStock = $this->calculateCurrentStock($itemno, $agentNo);

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
            'agent_no' => 'required|string',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        // agent_no is now required by validation, no fallback needed
        $agentNo = $request->input('agent_no');

        // Check if sufficient stock is available
        $currentStock = $this->calculateCurrentStock($request->ITEMNO, $agentNo);
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
     * Uses ITEMNO from icitem table (matches item_transactions.ITEMNO)
     *
     * @param string $itemno ITEMNO from icitem table
     * @return float
     */
    private function calculateCurrentStock($itemno, $agentNo)
    {
        if (!$agentNo || empty($agentNo)) {
            throw new \InvalidArgumentException('agentNo is required for stock calculations. Stock is by agent.');
        }

        $query = ItemTransaction::where('ITEMNO', $itemno)
            ->where('agent_no', $agentNo);

        $total = $query->sum('quantity');

        // If no transactions found, return 0 (no stock)
        return $total !== null ? (float)$total : 0.0;
    }

    /**
     * Get inventory summary (all items with current stock)
     * Uses icitem table to match actual inventory data
     * Now supports agent filtering and shows return good/bad columns
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInventorySummary(Request $request)
    {
        $request->validate([
            'agent_no' => 'required|string',
            'group_name' => 'nullable|string',
        ]);

        // Get all items from icitem table (actual inventory data)
        $query = Icitem::query();

        // Filter by product group if provided (use GROUP field from icitem)
        if ($request->has('group_name') && $request->input('group_name')) {
            $query->where('GROUP', $request->input('group_name'));
        }

        $items = $query->orderBy('ITEMNO')->get();

        // agent_no is now required by validation
        $agentNo = $request->input('agent_no');
        
        // Normalize agent_no: if it's a username, convert to user's name
        // (because orders store agent_no as user's name, not username)
        if ($agentNo) {
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
            // If not found by username, assume agent_no is already the name
        }

        $stockService = new StockService();

        $inventory = $items->map(function ($item) use ($agentNo, $stockService) {
            // Calculate stock totals for this agent (required)
            $totals = $stockService->calculateStockTotals($agentNo, $item->ITEMNO);
            $currentStock = $totals['available'];
            $stockIn = $totals['stockIn'];
            $stockOut = $totals['stockOut'];
            $returnGood = $totals['returnGood'];
            $returnBad = $totals['returnBad'];

            return [
                'ITEMNO' => $item->ITEMNO,
                'DESP' => $item->DESP ?? 'Unknown Product',
                'current_stock' => $currentStock,
                'QTY' => $currentStock, // Use calculated stock as QTY
                'stockIn' => $stockIn,
                'stockOut' => $stockOut,
                'returnGood' => $returnGood,
                'returnBad' => $returnBad,
                'UNIT' => $item->UNIT, // Unit from icitem table
                'PRICE' => $item->PRICE ? (float)$item->PRICE : null, // Price from icitem table
                'code' => $item->ITEMNO, // Map ITEMNO to code for backward compatibility
                'description' => $item->DESP ?? 'Unknown Product', // Map DESP to description
                'group_name' => $item->GROUP ?? '', // Include group name from GROUP field
                'agent_no' => $agentNo, // Include agent_no in response
            ];
        })
        ->filter(function ($item) {
            // Hide items with no transactions (all zeros)
            // Show only items that have at least one transaction (stockIn, stockOut, returnGood, or returnBad > 0)
            return $item['stockIn'] > 0 || 
                   $item['stockOut'] > 0 || 
                   $item['returnGood'] > 0 || 
                   $item['returnBad'] > 0;
        })
        ->values(); // Re-index the array after filtering

        return makeResponse(200, 'Inventory summary retrieved successfully.', $inventory);
    }

    /**
     * Get stock by agent and product
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $itemno Item number (ITEMNO)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockByAgent(Request $request, $itemno = null)
    {
        $request->validate([
            'agent_no' => 'required|string',
            'itemno' => 'nullable|string',
        ]);

        $agentNo = $request->input('agent_no');
        
        // Normalize agent_no: if it's a username, convert to user's name
        if ($agentNo) {
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
        }
        
        $stockService = new StockService();

        if ($itemno) {
            // Get stock for specific item
            $totals = $stockService->calculateStockTotals($agentNo, $itemno);
            
            // Get item details from icitem
            $item = Icitem::find($itemno);
            
            return makeResponse(200, 'Stock retrieved successfully.', [
                'ITEMNO' => $itemno,
                'DESP' => $item->DESP ?? 'Unknown Product',
                'agent_no' => $agentNo,
                'stockIn' => $totals['stockIn'],
                'stockOut' => $totals['stockOut'],
                'returnGood' => $totals['returnGood'],
                'returnBad' => $totals['returnBad'],
                'available' => $totals['available'],
            ]);
        } else {
            // Get stock summary for all items for this agent
            $summary = $stockService->getAgentStockSummary($agentNo);
            
            // Enrich with item details
            $enrichedSummary = collect($summary)->map(function ($stockItem) use ($agentNo) {
                $item = Icitem::find($stockItem['ITEMNO']);
                return [
                    'ITEMNO' => $stockItem['ITEMNO'],
                    'DESP' => $item->DESP ?? 'Unknown Product',
                    'agent_no' => $agentNo,
                    'stockIn' => $stockItem['stockIn'],
                    'stockOut' => $stockItem['stockOut'],
                    'returnGood' => $stockItem['returnGood'],
                    'returnBad' => $stockItem['returnBad'],
                    'available' => $stockItem['available'],
                ];
            });

            return makeResponse(200, 'Agent stock summary retrieved successfully.', $enrichedSummary);
        }
    }
}
