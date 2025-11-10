<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icitem;
use App\Models\ItemTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockManagementController extends Controller
{
    /**
     * Display the stock management page.
     * Only accessible by admin or KBS users.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        // Get inventory summary
        $items = Icitem::select('ITEMNO', 'DESP', 'QTY', 'UNIT', 'PRICE')
            ->orderBy('ITEMNO')
            ->get();
        
        $inventory = $items->map(function ($item) {
            $currentStock = $this->calculateCurrentStock($item->ITEMNO);
            return [
                'ITEMNO' => $item->ITEMNO,
                'DESP' => $item->DESP,
                'current_stock' => $currentStock,
                'QTY' => $item->QTY ?? 0,
                'UNIT' => $item->UNIT,
                'PRICE' => $item->PRICE,
            ];
        });
        
        return view('inventory.stock-management', compact('inventory'));
    }
    
    /**
     * Show the form for creating a new stock transaction.
     */
    public function create()
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        // Get all items for dropdown
        $items = Icitem::select('ITEMNO', 'DESP')
            ->orderBy('ITEMNO')
            ->get();
        
        return view('inventory.stock-transaction-create', compact('items'));
    }
    
    /**
     * Calculate current stock from transactions
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
     * Handle stock transaction (combined for in/out/adjustment)
     */
    public function store(Request $request)
    {
        $request->validate([
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'transaction_type' => 'required|string|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);
        
        // Additional validation for adjustment
        if ($request->transaction_type === 'adjustment' && empty($request->notes)) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Notes are required for stock adjustments.')
                ->withInput();
        }
        
        try {
            $itemno = $request->ITEMNO;
            $transactionType = $request->transaction_type;
            $quantity = $request->quantity;
            
            // For stock out, check if sufficient stock is available
            if ($transactionType === 'out') {
                $currentStock = $this->calculateCurrentStock($itemno);
                if ($currentStock < $quantity) {
                    return redirect()->route('inventory.stock-management')
                        ->with('error', 'Insufficient stock. Available: ' . number_format($currentStock, 2))
                        ->withInput();
                }
                $quantity = -abs($quantity); // Make negative for stock out
            } elseif ($transactionType === 'in') {
                $quantity = abs($quantity); // Ensure positive for stock in
            }
            // For adjustment, quantity can be positive or negative as provided
            
            $this->processStockTransaction(
                $itemno,
                $transactionType,
                $quantity,
                $transactionType === 'adjustment' ? 'adjustment' : null,
                null,
                $request->notes
            );
            
            $message = $transactionType === 'in' ? 'Stock added successfully!' : 
                      ($transactionType === 'out' ? 'Stock removed successfully!' : 'Stock adjusted successfully!');
            
            return redirect()->route('inventory.stock-management', ['itemno' => $itemno])
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Failed to process stock transaction: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Process a stock transaction
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
            ItemTransaction::create([
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

            // Update icitem QTY field
            $item = Icitem::find($itemno);
            if ($item) {
                $item->QTY = $stockAfter;
                $item->UPDATED_BY = auth()->user()->id ?? null;
                $item->UPDATED_ON = now();
                $item->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Show item transactions page
     */
    public function showItemTransactions(Request $request, $itemno)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        // Get item details
        $item = Icitem::find($itemno);
        
        if (!$item) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Item not found.');
        }
        
        // Calculate current stock
        $currentStock = $this->calculateCurrentStock($itemno);
        
        // Get transactions with pagination and filters
        $transactionsQuery = ItemTransaction::where('ITEMNO', $itemno)
            ->orderBy('CREATED_ON', 'desc');
        
        // Filter by transaction type if provided
        if ($request->has('transaction_type') && $request->transaction_type) {
            $transactionsQuery->where('transaction_type', $request->transaction_type);
        }
        
        $transactions = $transactionsQuery->paginate(50);
        
        return view('inventory.item-transactions', compact('item', 'currentStock', 'transactions'));
    }
}

