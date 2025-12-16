<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icitem;
use App\Models\Icgroup;
use App\Models\ItemTransaction;
use App\Models\User;
use App\Services\StockService;
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
        
        // Get all agents (users) - exclude KBS and admin users
        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function($query) {
                $query->where('username', '!=', 'KBS')
                      ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function($user) {
                // Filter out admin users (check if user has admin role or is admin)
                return !$user->hasRole('admin');
            })
            ->values(); // Re-index the collection
        
        // Get all product groups
        $groups = Icgroup::select('name')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected agent from request
        $selectedAgent = $request->input('agent_no');
        $inventory = collect([]);
        $openingBalances = collect([]);
        $stockService = new StockService();
        
        // Only get items if agent is selected - show only items that have transactions for this agent
        if ($selectedAgent) {
            // Normalize agent_no: if it's a username, convert to user's name
            $agentNo = $selectedAgent;
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
            
            // Get distinct items from BOTH orders and item_transactions for this agent
            // This matches how StockService calculates stock
            
            // Get items from orders (DO and INV types)
            $itemsFromOrders = DB::table('orders')
                ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
                ->join('icitem', function($join) {
                    $join->on(DB::raw('order_items.product_no COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
                })
                ->where('orders.agent_no', $agentNo)
                ->whereNotNull('order_items.product_no')
                ->select(
                    'icitem.ITEMNO',
                    'icitem.DESP',
                    'icitem.GROUP',
                    'icitem.UNIT',
                    'icitem.PRICE'
                )
                ->distinct()
                ->get()
                ->pluck('ITEMNO')
                ->toArray();
            
            // Get items from item_transactions
            $itemsFromTransactions = DB::table('item_transactions')
                ->join('icitem', function($join) {
                    $join->on(DB::raw('item_transactions.ITEMNO COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
                })
                ->where('item_transactions.agent_no', $agentNo)
                ->select(
                    'icitem.ITEMNO',
                    'icitem.DESP',
                    'icitem.GROUP',
                    'icitem.UNIT',
                    'icitem.PRICE'
                )
                ->distinct()
                ->get()
                ->pluck('ITEMNO')
                ->toArray();
            
            // Combine and get unique item numbers
            $allItemNos = array_unique(array_merge($itemsFromOrders, $itemsFromTransactions));
            
            if (empty($allItemNos)) {
                $inventory = collect([]);
            } else {
                // Get item details from icitem for all unique items
                $itemsQuery = Icitem::whereIn('ITEMNO', $allItemNos);
                
                // Filter by group if provided
                if ($request->has('group') && $request->input('group')) {
                    $itemsQuery->where('GROUP', $request->input('group'));
                }
                
                // Filter by search term (item code or name)
                if ($request->has('search') && $request->input('search')) {
                    $searchTerm = $request->input('search');
                    $itemsQuery->where(function($q) use ($searchTerm) {
                        $q->where('ITEMNO', 'like', '%' . $searchTerm . '%')
                          ->orWhere('DESP', 'like', '%' . $searchTerm . '%');
                    });
                }
                
                $items = $itemsQuery->orderBy('GROUP')->orderBy('ITEMNO')->get();
                
                // Calculate agent-specific stock for each item using StockService
                // This will include both orders and item_transactions
                $inventory = $items->map(function ($item) use ($agentNo, $stockService) {
                    // Calculate stock totals for this agent (includes orders + item_transactions)
                    $totals = $stockService->calculateStockTotals($agentNo, $item->ITEMNO);
                    $currentStock = $totals['available'];
                    
                    return [
                        'ITEMNO' => $item->ITEMNO,
                        'DESP' => $item->DESP ?? 'N/A',
                        'current_stock' => $currentStock,
                        'QTY' => $currentStock,
                        'UNIT' => $item->UNIT ?? 'N/A',
                        'PRICE' => $item->PRICE ?? 0,
                        'GROUP' => $item->GROUP ?? '',
                        'stockIn' => $totals['stockIn'],
                        'stockOut' => $totals['stockOut'],
                        'returnGood' => $totals['returnGood'],
                        'returnBad' => $totals['returnBad'],
                    ];
                });
            }
            
            // Get opening balances for this agent
            $openingBalances = ItemTransaction::where('agent_no', $agentNo)
                ->where('reference_type', 'opening balance')
                ->where('transaction_type', 'in')
                ->with('item')
                ->orderBy('CREATED_ON', 'desc')
                ->get();
        }
        
        // If this is an AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $inventory->values()->all(),
                'count' => $inventory->count()
            ]);
        }
        
        return view('inventory.stock-management', compact('inventory', 'agents', 'groups', 'selectedAgent', 'openingBalances'));
    }
    
    /**
     * Show the form for creating a new stock transaction.
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        // Get all agents (users) - exclude KBS and admin users
        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function($query) {
                $query->where('username', '!=', 'KBS')
                      ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function($user) {
                // Filter out admin users (check if user has admin role or is admin)
                return !$user->hasRole('admin');
            })
            ->values(); // Re-index the collection
        
        // Get all product groups
        $groups = Icgroup::select('name')
            ->orderBy('name', 'asc')
            ->get();
        
        // Get selected agent from request (for stateful UI)
        $selectedAgent = $request->input('agent_no');
        
        $items = collect([]);
        
        // Only get items if agent is selected
        if ($selectedAgent) {
            // Normalize agent_no: if it's a username, convert to user's name
            $agentNo = $selectedAgent;
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
            
            // Check if user is searching by group or item
            $hasGroupFilter = $request->has('group') && $request->input('group');
            $hasItemSearch = $request->has('item_search') && $request->input('item_search');
            
            if ($hasGroupFilter || $hasItemSearch) {
                // If searching, show ALL items from icitem that match the search
                // This allows finding items even if they don't have transactions yet
                $itemsQuery = Icitem::query();
                
                // Filter by group if provided (use LIKE for partial match)
                if ($hasGroupFilter) {
                    $groupTerm = $request->input('group');
                    $itemsQuery->where('GROUP', 'like', '%' . $groupTerm . '%');
                }
                
                // Filter by item search (item code or name)
                if ($hasItemSearch) {
                    $itemSearchTerm = $request->input('item_search');
                    $itemsQuery->where(function($q) use ($itemSearchTerm) {
                        $q->where('ITEMNO', 'like', '%' . $itemSearchTerm . '%')
                          ->orWhere('DESP', 'like', '%' . $itemSearchTerm . '%');
                    });
                }
                
                $items = $itemsQuery->orderBy('GROUP')->orderBy('ITEMNO')->get();
            } else {
                // If no search, show only items that have transactions for this agent
                // Get distinct items from BOTH orders and item_transactions for this agent
                
                // Get items from orders (DO and INV types)
                $itemsFromOrders = DB::table('orders')
                    ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
                    ->join('icitem', function($join) {
                        $join->on(DB::raw('order_items.product_no COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
                    })
                    ->where('orders.agent_no', $agentNo)
                    ->whereNotNull('order_items.product_no')
                    ->select('icitem.ITEMNO')
                    ->distinct()
                    ->get()
                    ->pluck('ITEMNO')
                    ->toArray();
                
                // Get items from item_transactions
                $itemsFromTransactions = DB::table('item_transactions')
                    ->join('icitem', function($join) {
                        $join->on(DB::raw('item_transactions.ITEMNO COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
                    })
                    ->where('item_transactions.agent_no', $agentNo)
                    ->select('icitem.ITEMNO')
                    ->distinct()
                    ->get()
                    ->pluck('ITEMNO')
                    ->toArray();
                
                // Combine and get unique item numbers
                $allItemNos = array_unique(array_merge($itemsFromOrders, $itemsFromTransactions));
                
                if (empty($allItemNos)) {
                    $items = collect([]);
                } else {
                    // Get item details from icitem for all unique items
                    $items = Icitem::whereIn('ITEMNO', $allItemNos)
                        ->orderBy('GROUP')
                        ->orderBy('ITEMNO')
                        ->get();
                }
            }
        }
        
        return view('inventory.stock-transaction-create', compact('items', 'agents', 'groups', 'selectedAgent'));
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
            'agent_no' => 'required|string',
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'transaction_type' => 'required|string|in:in,out,adjustment,invoice_sale,invoice_return',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
        ]);
        
        // Additional validation for adjustment
        if ($request->transaction_type === 'adjustment' && empty($request->notes)) {
            return redirect()->route('inventory.stock-management.create', [
                'agent_no' => $request->agent_no,
                'group' => $request->group,
                'item_search' => $request->item_search,
            ])
                ->with('error', 'Notes are required for stock adjustments.')
                ->withInput();
        }
        
        try {
            $agentNo = $request->agent_no;
            $itemno = $request->ITEMNO;
            $transactionType = $request->transaction_type;
            $quantity = $request->quantity;
            $referenceType = $request->reference_type;
            
            // Determine reference type
            // If opening balance is selected, ensure transaction type is "in"
            if ($referenceType === 'opening_balance') {
                $referenceType = 'opening balance';
                $transactionType = 'in'; // Opening balance must be stock in
            } elseif ($transactionType === 'adjustment') {
                $referenceType = 'adjustment';
            } else {
                $referenceType = null;
            }
            
            // For stock out and invoice sale, check if sufficient stock is available
            if (in_array($transactionType, ['out', 'invoice_sale'])) {
                $currentStock = $this->calculateCurrentStock($itemno);
                if ($currentStock < $quantity) {
                    return redirect()->route('inventory.stock-management.create', [
                        'agent_no' => $request->agent_no,
                        'group' => $request->group,
                        'item_search' => $request->item_search,
                    ])
                        ->with('error', 'Insufficient stock. Available: ' . number_format($currentStock, 2))
                        ->withInput();
                }
                $quantity = -abs($quantity); // Make negative for stock out
            } elseif (in_array($transactionType, ['in', 'invoice_return'])) {
                $quantity = abs($quantity); // Ensure positive for stock in
            }
            // For adjustment, quantity can be positive or negative as provided
            
            $this->processStockTransaction(
                $itemno,
                $transactionType,
                $quantity,
                $referenceType,
                null,
                $request->notes,
                $agentNo
            );
            
            if ($transactionType === 'in') {
                $message = 'Stock added successfully!';
            } elseif ($transactionType === 'out') {
                $message = 'Stock removed successfully!';
            } elseif ($transactionType === 'invoice_sale') {
                $message = 'Invoice sale transaction created successfully!';
            } elseif ($transactionType === 'invoice_return') {
                $message = 'Invoice return transaction created successfully!';
            } else {
                $message = 'Stock adjusted successfully!';
            }
            
            // Redirect to item transactions page with agent_no
            return redirect()->route('inventory.stock-management.item.transactions', [
                'itemno' => $itemno,
                'agent_no' => $agentNo,
            ])
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('inventory.stock-management.create', [
                'agent_no' => $request->agent_no,
                'group' => $request->group,
                'item_search' => $request->item_search,
            ])
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
        $notes = null,
        $agentNo = null
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
                'agent_no' => $agentNo,
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
        
        // Get agent_no from request if provided
        $agentNo = $request->input('agent_no');
        if ($agentNo) {
            // Normalize agent_no: if it's a username, convert to user's name
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
        }
        
        $stockService = new StockService();
        
        // Calculate agent-specific stock if agent is provided
        if ($agentNo) {
            $totals = $stockService->calculateStockTotals($agentNo, $itemno);
            $currentStock = $totals['available'];
        }
        
        // Get transactions from BOTH item_transactions and orders
        $allTransactions = collect([]);
        
        // 1. Get transactions from item_transactions table
        $itemTransactionsQuery = ItemTransaction::where('ITEMNO', $itemno);
        
        // Filter by agent if provided
        if ($agentNo) {
            $itemTransactionsQuery->where('agent_no', $agentNo);
        }
        
        // Filter by transaction type if provided
        if ($request->has('transaction_type') && $request->transaction_type) {
            $itemTransactionsQuery->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $itemTransactionsQuery->whereDate('CREATED_ON', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $itemTransactionsQuery->whereDate('CREATED_ON', '<=', $request->date_to);
        }
        
        $itemTransactions = $itemTransactionsQuery->get();
        
        // Transform item_transactions to a common format
        foreach ($itemTransactions as $trans) {
            $allTransactions->push([
                'date' => $trans->CREATED_ON,
                'type' => $trans->transaction_type,
                'quantity' => $trans->quantity,
                'stock_before' => $trans->stock_before,
                'stock_after' => $trans->stock_after,
                'reference_type' => $trans->reference_type,
                'reference_id' => $trans->reference_id,
                'notes' => $trans->notes,
                'source' => 'item_transaction',
                'id' => $trans->id,
            ]);
        }
        
        // 2. Get transactions from orders (DO and INV types)
        if ($agentNo) {
            $ordersQuery = DB::table('orders')
                ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
                ->where('orders.agent_no', $agentNo)
                ->where('order_items.product_no', $itemno)
                ->select(
                    'orders.id',
                    'orders.reference_no',
                    'orders.type',
                    'orders.order_date',
                    'order_items.quantity',
                    'order_items.is_trade_return',
                    'order_items.trade_return_is_good'
                );
            
            // Filter by date range if provided
            if ($request->has('date_from') && $request->date_from) {
                $ordersQuery->whereDate('orders.order_date', '>=', $request->date_from);
            }
            
            if ($request->has('date_to') && $request->date_to) {
                $ordersQuery->whereDate('orders.order_date', '<=', $request->date_to);
            }
            
            $orders = $ordersQuery->get();
            
            // Transform orders to transaction format
            foreach ($orders as $order) {
                $transactionType = null;
                $quantity = (float) $order->quantity;
                
                if ($order->type === 'DO') {
                    $transactionType = 'in';
                } elseif ($order->type === 'INV') {
                    if ($order->is_trade_return) {
                        if ($order->trade_return_is_good) {
                            $transactionType = 'in'; // Return good = stock in
                        } else {
                            $transactionType = 'out'; // Return bad = stock out
                        }
                    } else {
                        $transactionType = 'out';
                    }
                }
                
                // Skip if transaction type filter doesn't match
                if ($request->has('transaction_type') && $request->transaction_type && $transactionType !== $request->transaction_type) {
                    continue;
                }
                
                // Calculate stock before/after for this order (simplified - would need to calculate based on order date)
                $allTransactions->push([
                    'date' => $order->order_date,
                    'type' => $transactionType,
                    'quantity' => $transactionType === 'out' ? -abs($quantity) : abs($quantity),
                    'stock_before' => null, // Would need complex calculation
                    'stock_after' => null, // Would need complex calculation
                    'reference_type' => 'order',
                    'reference_id' => $order->reference_no,
                    'notes' => $order->type === 'DO' ? 'DO Order' : ($order->is_trade_return ? 'Trade Return' : 'Invoice'),
                    'source' => 'order',
                    'id' => 'order_' . $order->id,
                ]);
            }
        }
        
        // Sort all transactions by date (descending)
        $allTransactions = $allTransactions->sortByDesc('date')->values();
        
        // Manual pagination for combined results
        $page = $request->get('page', 1);
        $perPage = 50;
        $total = $allTransactions->count();
        $items = $allTransactions->slice(($page - 1) * $perPage, $perPage)->values();
        
        // Create a paginator manually
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return view('inventory.item-transactions', compact('item', 'currentStock', 'transactions', 'agentNo'));
    }
    
    /**
     * Show opening balance management page
     */
    public function openingBalance(Request $request)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        // Get all agents (users) - exclude KBS and admin users
        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function($query) {
                $query->where('username', '!=', 'KBS')
                      ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function($user) {
                return !$user->hasRole('admin');
            })
            ->values();
        
        // Get selected agent from request
        $selectedAgent = $request->input('agent_no');
        $openingBalances = collect([]);
        
        if ($selectedAgent) {
            // Normalize agent_no
            $agentNo = $selectedAgent;
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
            
            // Get all opening balance transactions for this agent
            $openingBalances = ItemTransaction::where('agent_no', $agentNo)
                ->where('reference_type', 'opening balance')
                ->where('transaction_type', 'in')
                ->with('item')
                ->orderBy('CREATED_ON', 'desc')
                ->get();
        }
        
        return view('inventory.opening-balance', compact('agents', 'selectedAgent', 'openingBalances'));
    }
    
    /**
     * Store opening balance transaction
     */
    public function storeOpeningBalance(Request $request)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access.');
        }
        
        $request->validate([
            'agent_no' => 'required|string',
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'quantity' => 'required|numeric|min:0.01',
        ]);
        
        try {
            $agentNo = $request->agent_no;
            // Normalize agent_no
            $user = User::where('username', $agentNo)->first();
            if ($user && $user->name) {
                $agentNo = $user->name;
            }
            
            $itemno = $request->ITEMNO;
            $quantity = abs($request->quantity);
            
            // Check if opening balance already exists for this agent and item
            $existing = ItemTransaction::where('agent_no', $agentNo)
                ->where('ITEMNO', $itemno)
                ->where('reference_type', 'opening balance')
                ->where('transaction_type', 'in')
                ->first();
            
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opening balance already exists for this item. Please delete it first before adding a new one.'
                ], 400);
            }
            
            // Calculate current stock before adding opening balance
            $stockService = new StockService();
            $stockBefore = $stockService->getAvailableStock($agentNo, $itemno);
            $stockAfter = $stockBefore + $quantity;
            
            // Create opening balance transaction
            ItemTransaction::create([
                'ITEMNO' => $itemno,
                'agent_no' => $agentNo,
                'transaction_type' => 'in',
                'quantity' => $quantity,
                'reference_type' => 'opening balance',
                'reference_id' => null,
                'notes' => 'Opening Balance',
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'CREATED_BY' => auth()->user()->id ?? null,
                'UPDATED_BY' => auth()->user()->id ?? null,
                'CREATED_ON' => now(),
                'UPDATED_ON' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance added successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add opening balance: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete opening balance transaction
     */
    public function deleteOpeningBalance($id)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access.');
        }
        
        try {
            $transaction = ItemTransaction::findOrFail($id);
            
            // Verify it's an opening balance transaction
            if ($transaction->reference_type !== 'opening balance' || $transaction->transaction_type !== 'in') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not an opening balance transaction.'
                ], 400);
            }
            
            $transaction->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Opening balance deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete opening balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display item movements page with filters
     */
    public function itemMovements(Request $request)
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Item Movements is only available for administrators and KBS users.');
        }

        // Get all product groups for filter
        $groups = Icgroup::select('name')
            ->orderBy('name', 'asc')
            ->get();

        // Get all agents for filter
        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function($query) {
                $query->where('username', '!=', 'KBS')
                      ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function($user) {
                return !$user->hasRole('admin');
            })
            ->values();

        // Get filter values from request
        $filters = [
            'date_from' => $request->input('date_from', date('Y-m-01')), // Default to first day of current month
            'date_to' => $request->input('date_to', date('Y-m-d')), // Default to today
            'item_group' => $request->input('item_group'),
            'item_no' => $request->input('item_no'),
            'transaction_type' => $request->input('transaction_type'), // 'in' or 'out'
            'order_type' => $request->input('order_type'), // 'INV', 'DO', 'CN'
            'agent_no' => $request->input('agent_no'),
        ];

        $movements = collect([]);

        // Get movements from item_transactions
        $itemTransactionsQuery = ItemTransaction::with('item')
            ->join('icitem', function($join) {
                $join->on(DB::raw('item_transactions.ITEMNO COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
            });

        if ($filters['agent_no']) {
            $itemTransactionsQuery->where('item_transactions.agent_no', $filters['agent_no']);
        }

        if ($filters['item_no']) {
            $itemTransactionsQuery->where('item_transactions.ITEMNO', $filters['item_no']);
        }

        if ($filters['item_group']) {
            $itemTransactionsQuery->where('icitem.GROUP', $filters['item_group']);
        }

        if ($filters['transaction_type']) {
            $itemTransactionsQuery->where('item_transactions.transaction_type', $filters['transaction_type']);
        }

        if ($filters['date_from']) {
            $itemTransactionsQuery->whereDate('item_transactions.CREATED_ON', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $itemTransactionsQuery->whereDate('item_transactions.CREATED_ON', '<=', $filters['date_to']);
        }

        $itemTransactions = $itemTransactionsQuery->select(
            'item_transactions.*',
            'icitem.DESP',
            'icitem.GROUP'
        )->get();

        foreach ($itemTransactions as $trans) {
            $orderType = 'Manual';
            $referenceNo = $trans->notes ?? 'N/A';
            
            if ($trans->reference_type === 'order' && $trans->reference_id) {
                $order = DB::table('orders')->where('id', $trans->reference_id)->first();
                if ($order) {
                    $orderType = $order->type;
                    $referenceNo = $order->reference_no;
                }
            }
            
            $movements->push([
                'date' => $trans->CREATED_ON,
                'item_group' => $trans->GROUP ?? 'N/A',
                'item_no' => $trans->ITEMNO,
                'item_name' => $trans->DESP ?? 'N/A',
                'in_out' => $trans->transaction_type === 'in' ? 'IN' : 'OUT',
                'quantity' => (float) $trans->quantity,
                'type' => $orderType,
                'reference_no' => $referenceNo,
                'agent_no' => $trans->agent_no,
            ]);
        }

        // Get movements from orders
        $ordersQuery = DB::table('orders')
            ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
            ->join('icitem', function($join) {
                $join->on(DB::raw('order_items.product_no COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('orders.type', ['INV', 'DO', 'CN']);

        if ($filters['agent_no']) {
            $ordersQuery->where('orders.agent_no', $filters['agent_no']);
        }

        if ($filters['item_no']) {
            $ordersQuery->where('order_items.product_no', $filters['item_no']);
        }

        if ($filters['item_group']) {
            $ordersQuery->where('icitem.GROUP', $filters['item_group']);
        }

        if ($filters['order_type']) {
            $ordersQuery->where('orders.type', $filters['order_type']);
        }

        if ($filters['date_from']) {
            $ordersQuery->whereDate('orders.order_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $ordersQuery->whereDate('orders.order_date', '<=', $filters['date_to']);
        }

        $orders = $ordersQuery->select(
            'orders.id',
            'orders.reference_no',
            'orders.type',
            'orders.order_date',
            'orders.agent_no',
            'order_items.product_no',
            'order_items.quantity',
            'order_items.is_trade_return',
            'order_items.trade_return_is_good',
            'icitem.DESP',
            'icitem.GROUP'
        )->get();

        foreach ($orders as $order) {
            $transactionType = null;
            $qty = (float) $order->quantity;

            // Determine transaction type based on order type
            if ($order->type === 'DO') {
                $transactionType = 'in';
            } elseif ($order->type === 'INV') {
                if ($order->is_trade_return && $order->trade_return_is_good) {
                    $transactionType = 'in'; // Trade return good
                } else {
                    $transactionType = 'out'; // Regular INV or trade return bad
                }
            } elseif ($order->type === 'CN') {
                if ($order->trade_return_is_good) {
                    $transactionType = 'in'; // Trade return good
                } else {
                    // Trade return bad - skip (no stock change)
                    continue;
                }
            }

            // Apply transaction_type filter if set
            if ($filters['transaction_type'] && $transactionType !== $filters['transaction_type']) {
                continue;
            }

            $movements->push([
                'date' => $order->order_date,
                'item_group' => $order->GROUP ?? 'N/A',
                'item_no' => $order->product_no,
                'item_name' => $order->DESP ?? 'N/A',
                'in_out' => strtoupper($transactionType),
                'quantity' => $qty,
                'type' => $order->type,
                'reference_no' => $order->reference_no,
                'agent_no' => $order->agent_no,
            ]);
        }

        // Sort by date descending
        $movements = $movements->sortByDesc('date')->values();

        return view('inventory.item-movements', compact('movements', 'groups', 'agents', 'filters'));
    }
}

