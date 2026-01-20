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
use Maatwebsite\Excel\Facades\Excel;
use App\Services\PDF\StockManagementPDF;


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
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
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

            // Check if user is searching - if so, search ALL items, not just items with transactions
            $searchTerm = trim($request->input('search', ''));
            $groupFilter = trim($request->input('group', ''));
            $hasSearch = !empty($searchTerm);
            $hasGroupFilter = !empty($groupFilter);

            // Pre-fetch all stock data for this agent in ONE batch call (2 queries total)
            // This avoids N+1 query problem when looping through items
            $stockSummaryKeyed = $stockService->getAgentStockSummaryKeyed($agentNo);

            // Base query
            $itemsQuery = Icitem::query();

            if ($hasSearch || $hasGroupFilter) {
                // Search ALL items

                if ($hasGroupFilter) {
                    $itemsQuery->where('GROUP', $groupFilter);
                }

                if ($hasSearch) {
                    $itemsQuery->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(ITEMNO) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                            ->orWhereRaw('LOWER(DESP) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                    });
                }
            } else {
                // No search/filter → only items with transactions
                $allItemNos = array_keys($stockSummaryKeyed);

                if (empty($allItemNos)) {
                    $inventory = collect([]);
                    return;
                }

                $itemsQuery->whereIn('ITEMNO', $allItemNos);
            }

            // Fetch items
            $items = $itemsQuery
                ->orderBy('GROUP')
                ->orderBy('ITEMNO')
                ->get();

            // Map stock data (single place)
            $inventory = $items->map(function ($item) use ($stockService, $stockSummaryKeyed) {
                $totals = $stockService->getStockFromKeyedSummary(
                    $stockSummaryKeyed,
                    $item->ITEMNO
                );

                $currentStock = $totals['available'];

                return [
                    'ITEMNO'        => $item->ITEMNO,
                    'DESP'          => $item->DESP ?? 'N/A',
                    'current_stock' => $currentStock,
                    'QTY'           => $currentStock,
                    'UNIT'          => $item->UNIT ?? 'N/A',
                    'PRICE'         => $item->PRICE ?? 0,
                    'GROUP'         => $item->GROUP ?? '',
                    'stockIn'       => $totals['stockIn'] + $totals['returnGood'],
                    'stockOut'      => $totals['stockOut'],
                    'returnGood'    => $totals['returnGood'],
                    'returnBad'     => $totals['returnBad'],
                ];
            });


            // Get opening balances for this agent with filters
            $itemTransactionTable = (new ItemTransaction())->getTable();
            $openingBalancesQuery = ItemTransaction::where($itemTransactionTable . '.agent_no', $agentNo)
                ->where($itemTransactionTable . '.reference_type', 'opening balance')
                ->where($itemTransactionTable . '.transaction_type', 'in')
                ->join('icitem', function ($join) use ($itemTransactionTable) {
                    $join->on(DB::raw($itemTransactionTable . '.ITEMNO COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
                });

            // Apply group filter if provided
            if ($hasGroupFilter) {
                $openingBalancesQuery->where('icitem.GROUP', $groupFilter);
            }

            // Apply search filter if provided
            if ($hasSearch) {
                $openingBalancesQuery->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(icitem.ITEMNO) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(icitem.DESP) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                });
            }

            $openingBalances = $openingBalancesQuery
                ->select($itemTransactionTable . '.*')
                ->with('item')
                ->orderBy($itemTransactionTable . '.CREATED_ON', 'desc')
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
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
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
                    $itemsQuery->where(function ($q) use ($itemSearchTerm) {
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
                    ->join('icitem', function ($join) {
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
                    ->join('icitem', function ($join) {
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
     * Handle stock transaction (combined for in/out/adjustment)
     */
    public function store(Request $request)
    {
        // Validate quantity based on transaction type
        $quantityRules = 'required|numeric';
        if ($request->transaction_type === 'adjustment') {
            // Adjustment can be positive or negative
            $quantityRules .= '|not_in:0';
        } else {
            // Other types must be positive
            $quantityRules .= '|min:0.01';
        }

        $request->validate([
            'agent_no' => 'required|string',
            'ITEMNO' => 'required|string|exists:icitem,ITEMNO',
            'transaction_type' => 'required|string|in:in,out,adjustment,invoice_sale,invoice_return',
            'quantity' => $quantityRules,
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
        ]);

        // Additional validation for adjustment
        if ($request->transaction_type === 'adjustment' && empty($request->notes)) {
            return redirect()->route('inventory.stock-management.create', [
                'agent_no' => $request->agent_no,
                'group' => $request->group,
                'item_search' => $request->item_search,
                'item_code' => $request->ITEMNO, // Add selected item to maintain form state
            ])
                ->with('error', 'Notes are required for stock adjustments.')
                ->withInput();
        }

        try {
            $agentNo = $request->agent_no;
            $itemno = $request->ITEMNO;
            $transactionType = $request->transaction_type;
            $quantity = (float)$request->quantity;
            $referenceType = $request->reference_type;
            $stockService = new StockService();

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
                $absQuantity = abs($quantity);
                $totals = $stockService->calculateStockTotals($agentNo, $itemno);
                $currentStock = $totals['available'];

                if ($currentStock < $absQuantity) {
                    return redirect()->route('inventory.stock-management.create', [
                        'agent_no' => $request->agent_no,
                        'group' => $request->group,
                        'item_search' => $request->item_search,
                        'item_code' => $request->ITEMNO, // Add selected item to maintain form state
                    ])
                        ->with('error', 'Insufficient stock. Available: ' . number_format($currentStock, 2))
                        ->withInput();
                }
                // Store as negative for stock calculations
                $quantity = -$absQuantity;
            } elseif (in_array($transactionType, ['in', 'invoice_return'])) {
                // Store as positive
                $quantity = abs($quantity);
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
                'item_code' => $request->ITEMNO, // Add selected item to maintain form state
            ])
                ->with('error', 'Failed to process stock transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Process a stock transaction
     *
     * @param string $agentNo Agent number (required - stock is by agent)
     */
    private function processStockTransaction(
        $itemno,
        $transactionType,
        $quantity,
        $referenceType = null,
        $referenceId = null,
        $notes = null,
        $agentNo
    ) {
        if (!$agentNo || empty($agentNo)) {
            throw new \InvalidArgumentException('agentNo is required for stock transactions. Stock is by agent.');
        }
        $stockService = new StockService();
        DB::beginTransaction();
        try {
            // Get current stock
            $totals = $stockService->calculateStockTotals($agentNo, $itemno);
            $stockBefore = $totals['available'];

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

        // Get agent_no from request (required for agent-specific stock)
        $agentNo = $request->input('agent_no');

        if (!$agentNo) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Agent number is required for viewing stock transactions.');
        }

        // Normalize agent_no: if it's a username, convert to user's name
        $agentUser = User::where('username', $agentNo)->first();
        if ($agentUser && $agentUser->name) {
            $agentNo = $agentUser->name;
        }

        // Use StockService for all stock calculations and movements
        $stockService = new StockService();

        // Calculate current stock
        $totals = $stockService->calculateStockTotals($agentNo, $itemno);
        $currentStock = $totals['available'];

        // Build filters from request
        $filters = [];
        if ($request->has('transaction_type') && $request->transaction_type) {
            $filters['transaction_type'] = $request->transaction_type;
        }
        if ($request->has('date_from') && $request->date_from) {
            $filters['date_from'] = $request->date_from;
        }
        if ($request->has('date_to') && $request->date_to) {
            $filters['date_to'] = $request->date_to;
        }

        // Get paginated stock movements using shared StockService method
        $page = $request->get('page', 1);
        $perPage = 50;

        $transactions = $stockService->getStockMovementsPaginated(
            $agentNo,
            $itemno,
            $filters,
            (int) $page,
            $perPage,
            $request->url(),
            $request->query()
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
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
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
     * Update opening balance transaction
     */
    public function updateOpeningBalance(Request $request, $id)
    {
        $user = auth()->user();

        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            $transaction = ItemTransaction::findOrFail($id);

            // Verify it's an opening balance transaction
            if ($transaction->reference_type !== 'opening balance' || $transaction->transaction_type !== 'in') {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not an opening balance transaction.'
                ], 400);
            }

            $oldQuantity = $transaction->quantity;
            $newQuantity = abs($request->quantity);

            // Calculate the difference
            $quantityDiff = $newQuantity - $oldQuantity;

            // Get current stock before update
            $stockService = new StockService();
            $stockBefore = $stockService->getAvailableStock($transaction->agent_no, $transaction->ITEMNO);

            // Calculate new stock after update
            $stockAfter = $stockBefore + $quantityDiff;

            // Update the transaction
            $transaction->quantity = $newQuantity;
            $transaction->stock_before = $stockBefore;
            $transaction->stock_after = $stockAfter;
            $transaction->UPDATED_BY = auth()->user()->id ?? null;
            $transaction->UPDATED_ON = now();
            $transaction->save();

            return response()->json([
                'success' => true,
                'message' => 'Opening balance updated successfully!',
                'data' => [
                    'quantity' => $newQuantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update opening balance: ' . $e->getMessage()
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
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
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
            ->join('icitem', function ($join) {
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
            ->join('icitem', function ($join) {
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

        // Calculate item summary (totals per item) from orders and item_transactions
        $itemSummary = [];

        // Process item_transactions for summary
        foreach ($itemTransactions as $trans) {
            $itemNo = $trans->ITEMNO;

            if (!isset($itemSummary[$itemNo])) {
                $itemSummary[$itemNo] = [
                    'item_no' => $itemNo,
                    'item_name' => $trans->DESP ?? 'N/A',
                    'item_group' => $trans->GROUP ?? 'N/A',
                    'stock_in' => 0,
                    'stock_out' => 0,
                    'return_good' => 0,
                    'return_bad' => 0,
                ];
            }

            // Manual transactions from item_transactions
            if ($trans->transaction_type === 'in') {
                $itemSummary[$itemNo]['stock_in'] += (float) $trans->quantity;
            } else {
                $itemSummary[$itemNo]['stock_out'] += (float) $trans->quantity;
            }
        }

        // Process orders for summary (re-query to get all order data)
        $summaryOrdersQuery = DB::table('orders')
            ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
            ->join('icitem', function ($join) {
                $join->on(DB::raw('order_items.product_no COLLATE utf8mb4_unicode_ci'), '=', DB::raw('icitem.ITEMNO COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('orders.type', ['INV', 'DO', 'CN']);

        if ($filters['agent_no']) {
            $summaryOrdersQuery->where('orders.agent_no', $filters['agent_no']);
        }

        if ($filters['item_no']) {
            $summaryOrdersQuery->where('order_items.product_no', $filters['item_no']);
        }

        if ($filters['item_group']) {
            $summaryOrdersQuery->where('icitem.GROUP', $filters['item_group']);
        }

        if ($filters['date_from']) {
            $summaryOrdersQuery->whereDate('orders.order_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $summaryOrdersQuery->whereDate('orders.order_date', '<=', $filters['date_to']);
        }

        $summaryOrders = $summaryOrdersQuery->select(
            'order_items.product_no',
            'order_items.quantity',
            'order_items.is_trade_return',
            'order_items.trade_return_is_good',
            'orders.type',
            'icitem.DESP',
            'icitem.GROUP'
        )->get();

        foreach ($summaryOrders as $order) {
            $itemNo = $order->product_no;
            $qty = (float) $order->quantity;

            if (!isset($itemSummary[$itemNo])) {
                $itemSummary[$itemNo] = [
                    'item_no' => $itemNo,
                    'item_name' => $order->DESP ?? 'N/A',
                    'item_group' => $order->GROUP ?? 'N/A',
                    'stock_in' => 0,
                    'stock_out' => 0,
                    'return_good' => 0,
                    'return_bad' => 0,
                ];
            }

            // Categorize based on order type and trade return flags
            if ($order->type === 'DO') {
                // DO = Stock IN
                $itemSummary[$itemNo]['stock_in'] += $qty;
            } elseif ($order->type === 'INV') {
                if ($order->is_trade_return) {
                    if ($order->trade_return_is_good) {
                        // Trade return good = Return Good
                        $itemSummary[$itemNo]['return_good'] += $qty;
                    } else {
                        // Trade return bad = Return Bad (for display only)
                        $itemSummary[$itemNo]['return_bad'] += $qty;
                    }
                } else {
                    // Regular INV = Stock OUT
                    $itemSummary[$itemNo]['stock_out'] += $qty;
                }
            } elseif ($order->type === 'CN') {
                if ($order->trade_return_is_good) {
                    // Trade return good = Return Good
                    $itemSummary[$itemNo]['return_good'] += $qty;
                } else {
                    // Trade return bad = Return Bad (for display only)
                    $itemSummary[$itemNo]['return_bad'] += $qty;
                }
            }
        }

        // Calculate available stock for each item
        // Available = stockIn + returnGood - stockOut (returnBad is NOT subtracted)
        foreach ($itemSummary as $itemNo => &$summary) {
            $summary['available'] = $summary['stock_in'] + $summary['return_good'] - $summary['stock_out'];
        }
        unset($summary);

        // Convert to collection and sort by item_no
        $itemSummary = collect($itemSummary)->sortBy('item_no')->values();

        return view('inventory.item-movements', compact('movements', 'itemSummary', 'groups', 'agents', 'filters'));
    }

    /**
     * Get stock by agent and item for web requests (AJAX)
     * Returns JSON response for use in blade templates
     */
    public function getStockByAgentWeb(Request $request, $itemno)
    {
        $user = auth()->user();

        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            return response()->json([
                'error' => 1,
                'status' => 403,
                'message' => 'Unauthorized access.',
                'data' => []
            ], 403);
        }

        $agentNo = $request->input('agent_no');

        if (!$agentNo) {
            return response()->json([
                'error' => 1,
                'status' => 400,
                'message' => 'Agent number is required.',
                'data' => []
            ], 400);
        }

        // Normalize agent_no: if it's a username, convert to user's name
        $user = User::where('username', $agentNo)->first();
        if ($user && $user->name) {
            $agentNo = $user->name;
        }

        $stockService = new StockService();

        // Get stock for specific item
        $totals = $stockService->calculateStockTotals($agentNo, $itemno);

        // Get item details from icitem
        $item = Icitem::find($itemno);

        return response()->json([
            'error' => 0,
            'status' => 200,
            'message' => 'Stock retrieved successfully.',
            'data' => [
                'ITEMNO' => $itemno,
                'DESP' => $item->DESP ?? 'Unknown Product',
                'agent_no' => $agentNo,
                'stockIn' => $totals['stockIn'],
                'stockOut' => $totals['stockOut'],
                'returnGood' => $totals['returnGood'],
                'returnBad' => $totals['returnBad'],
                'available' => $totals['available'],
            ]
        ]);
    }

    /**
     * Export stock management data to Excel
     */
    public function exportExcel(Request $request)
    {
        $user = auth()->user();

        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access.');
        }

        // Get selected agent from request
        $selectedAgent = $request->input('agent_no');

        if (!$selectedAgent) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Agent number is required for export.');
        }

        // Normalize agent_no: if it's a username, convert to user's name
        $agentUser = User::where('username', $selectedAgent)->first();
        if ($agentUser && $agentUser->name) {
            $selectedAgent = $agentUser->name;
        }

        // Get filters from request
        $searchTerm = trim($request->input('search', ''));
        $groupFilter = trim($request->input('group', ''));

        // Get inventory data (same logic as index method)
        $stockService = new StockService();
        $stockSummaryKeyed = $stockService->getAgentStockSummaryKeyed($selectedAgent);

        // Base query
        $itemsQuery = Icitem::query();

        if ($searchTerm || $groupFilter) {
            // Search ALL items
            if ($groupFilter) {
                $itemsQuery->where('GROUP', $groupFilter);
            }

            if ($searchTerm) {
                $itemsQuery->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(ITEMNO) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(DESP) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                });
            }
        } else {
            // No search/filter → only items with transactions
            $allItemNos = array_keys($stockSummaryKeyed);

            if (empty($allItemNos)) {
                return redirect()->route('inventory.stock-management', ['agent_no' => $request->input('agent_no')])
                    ->with('error', 'No items found with transactions for this agent.');
            }

            $itemsQuery->whereIn('ITEMNO', $allItemNos);
        }

        // Fetch items
        $items = $itemsQuery
            ->orderBy('GROUP')
            ->orderBy('ITEMNO')
            ->get();

        // Map stock data
        $inventory = $items->map(function ($item) use ($stockService, $stockSummaryKeyed) {
            $totals = $stockService->getStockFromKeyedSummary(
                $stockSummaryKeyed,
                $item->ITEMNO
            );

            $currentStock = $totals['available'];

            return [
                'ITEMNO' => $item->ITEMNO,
                'DESP' => $item->DESP ?? 'N/A',
                'current_stock' => $currentStock,
                'QTY' => $currentStock,
                'UNIT' => $item->UNIT ?? 'N/A',
                'PRICE' => $item->PRICE ?? 0,
                'GROUP' => $item->GROUP ?? '',
                'stockIn' => $totals['stockIn'] + $totals['returnGood'],
                'stockOut' => $totals['stockOut'],
                'returnGood' => $totals['returnGood'],
                'returnBad' => $totals['returnBad'],
            ];
        });

        // Generate filename with timestamp and agent
        $filename = 'stock_management_' . $selectedAgent . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new \App\Exports\StockManagementExport($inventory, $selectedAgent), $filename);
    }

    /**
     * Export stock management data to PDF
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();

        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access.');
        }

        // Get selected agent from request
        $selectedAgent = $request->input('agent_no');

        if (!$selectedAgent) {
            return redirect()->route('inventory.stock-management')
                ->with('error', 'Agent number is required for export.');
        }

        // Normalize agent_no: if it's a username, convert to user's name
        $agentUser = User::where('username', $selectedAgent)->first();
        if ($agentUser && $agentUser->name) {
            $selectedAgent = $agentUser->name;
        }

        // Get filters from request
        $searchTerm = trim($request->input('search', ''));
        $groupFilter = trim($request->input('group', ''));

        // Get inventory data (same logic as index method)
        $stockService = new StockService();
        $stockSummaryKeyed = $stockService->getAgentStockSummaryKeyed($selectedAgent);

        // Base query
        $itemsQuery = Icitem::query();

        if ($searchTerm || $groupFilter) {
            // Search ALL items
            if ($groupFilter) {
                $itemsQuery->where('GROUP', $groupFilter);
            }

            if ($searchTerm) {
                $itemsQuery->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(ITEMNO) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(DESP) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                });
            }
        } else {
            // No search/filter → only items with transactions
            $allItemNos = array_keys($stockSummaryKeyed);

            if (empty($allItemNos)) {
                return redirect()->route('inventory.stock-management', ['agent_no' => $request->input('agent_no')])
                    ->with('error', 'No items found with transactions for this agent.');
            }

            $itemsQuery->whereIn('ITEMNO', $allItemNos);
        }

        // Fetch items
        $items = $itemsQuery
            ->orderBy('GROUP')
            ->orderBy('ITEMNO')
            ->get();

        // Map stock data
        $inventory = $items->map(function ($item) use ($stockService, $stockSummaryKeyed) {
            $totals = $stockService->getStockFromKeyedSummary(
                $stockSummaryKeyed,
                $item->ITEMNO
            );

            $currentStock = $totals['available'];

            return [
                'ITEMNO' => $item->ITEMNO,
                'DESP' => $item->DESP ?? 'N/A',
                'current_stock' => $currentStock,
                'QTY' => $currentStock,
                'UNIT' => $item->UNIT ?? 'N/A',
                'PRICE' => $item->PRICE ?? 0,
                'GROUP' => $item->GROUP ?? '',
                'stockIn' => $totals['stockIn'] + $totals['returnGood'],
                'stockOut' => $totals['stockOut'],
                'returnGood' => $totals['returnGood'],
                'returnBad' => $totals['returnBad'],
            ];
        });

        // Generate PDF using custom StockManagementPDF class
        $filename = 'stock_management_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $selectedAgent) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $exportDate = now()->format('Y-m-d H:i:s');

        $html = view('pdf.stock-management', [
            'inventory' => $inventory,
            'selectedAgent' => $selectedAgent,
            'exportDate' => $exportDate,
            'filters' => [
                'search' => $searchTerm,
                'group' => $groupFilter,
            ]
        ])->render();

        // Create custom PDF instance with header/footer
        $pdf = new StockManagementPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setAgentName($selectedAgent);
        $pdf->setExportDate($exportDate);

        // Set document information
        $pdf->SetCreator('KBS System');
        $pdf->SetAuthor($user->name ?? 'System');
        $pdf->SetTitle('Stock Management Report - ' . $selectedAgent);
        $pdf->SetSubject('Stock Management Export');

        // Enable header and footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);

        // Set margins (left, top, right) - top margin accounts for header
        $pdf->SetMargins(10, 35, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 20);

        // Add first page
        $pdf->AddPage();

        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Save to file
        $pdf->Output(storage_path('app/' . $filename), 'F');

        return response()->file(storage_path('app/' . $filename), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ])->deleteFileAfterSend(true);
    }
}
