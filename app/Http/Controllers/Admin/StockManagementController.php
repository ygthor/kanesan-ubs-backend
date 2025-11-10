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
        
        // Get transactions with pagination
        $transactionsQuery = ItemTransaction::with('item')
            ->orderBy('CREATED_ON', 'desc');
        
        // Filter by transaction type if provided
        if ($request->has('transaction_type') && $request->transaction_type) {
            $transactionsQuery->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by item code if provided
        if ($request->has('itemno') && $request->itemno) {
            $transactionsQuery->where('ITEMNO', $request->itemno);
        }
        
        $transactions = $transactionsQuery->paginate(50);
        
        return view('inventory.stock-management', compact('inventory', 'transactions'));
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
     * Handle stock in
     */
    public function stockIn(Request $request)
    {
        $request->validate([
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);
        
        try {
            $this->processStockTransaction(
                $request->ITEMNO,
                'in',
                abs($request->quantity),
                null,
                null,
                $request->notes
            );
            
            return redirect()->route('inventory.stock-management')
                ->with('success', 'Stock added successfully!');
        } catch (\Exception $e) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Failed to add stock: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle stock out
     */
    public function stockOut(Request $request)
    {
        $request->validate([
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);
        
        try {
            // Check if sufficient stock is available
            $currentStock = $this->calculateCurrentStock($request->ITEMNO);
            if ($currentStock < $request->quantity) {
                return redirect()->route('inventory.stock-management')
                    ->with('error', 'Insufficient stock. Available: ' . $currentStock);
            }
            
            $this->processStockTransaction(
                $request->ITEMNO,
                'out',
                -abs($request->quantity),
                null,
                null,
                $request->notes
            );
            
            return redirect()->route('inventory.stock-management')
                ->with('success', 'Stock removed successfully!');
        } catch (\Exception $e) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Failed to remove stock: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle stock adjustment
     */
    public function stockAdjustment(Request $request)
    {
        $request->validate([
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'quantity' => 'required|numeric',
            'notes' => 'required|string|min:3',
        ]);
        
        try {
            $this->processStockTransaction(
                $request->ITEMNO,
                'adjustment',
                $request->quantity,
                'adjustment',
                null,
                $request->notes
            );
            
            return redirect()->route('inventory.stock-management')
                ->with('success', 'Stock adjusted successfully!');
        } catch (\Exception $e) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Failed to adjust stock: ' . $e->getMessage());
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
}

