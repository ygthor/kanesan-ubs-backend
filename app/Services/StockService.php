<?php

namespace App\Services;

use App\Models\ItemTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Calculate stock totals for an agent and item from orders AND item_transactions
     *
     * IMPORTANT: Return Bad Logic
     * - Trade return GOOD adds to available stock (via returnGood)
     * - Trade return BAD does NOT affect stock (no stock change)
     * - Formula: available = stockIn + returnGood - stockOut
     *   (returnBad is tracked for display/reporting only, NOT subtracted from available)
     *
     * @param string $agentNo Agent number (user name)
     * @param string $itemNo Item number (ITEMNO from icitem)
     * @return array ['stockIn', 'stockOut', 'returnGood', 'returnBad', 'available']
     */
    public function calculateStockTotals(string $agentNo, string $itemNo): array
    {
        $stockIn = 0;
        $stockOut = 0;
        $returnGood = 0;
        $returnBad = 0;

        // 1. Calculate from orders - optimized query with direct join using reference_no
        $orderItems = DB::table('orders')
            ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
            ->where('orders.agent_no', $agentNo)
            ->where('order_items.product_no', $itemNo)
            ->select(
                'orders.type',
                'order_items.quantity',
                'order_items.is_trade_return',
                'order_items.trade_return_is_good'
            )
            ->get();

        foreach ($orderItems as $orderItem) {
            $qty = (float) $orderItem->quantity;
            $isTradeReturn = (bool) ($orderItem->is_trade_return ?? false);
            $tradeReturnIsGood = (bool) ($orderItem->trade_return_is_good ?? true);

            if ($orderItem->type === 'DO') {
                // DO orders = Stock IN
                $stockIn += $qty;
            } elseif ($orderItem->type === 'INV') {
                if ($isTradeReturn) {
                    if ($tradeReturnIsGood) {
                        // Trade return good = Stock IN (returnGood) - adds to available stock
                        $returnGood += $qty;
                    } else {
                        // Trade return bad = No stock change (do nothing)
                        // Items are physically returned but don't affect available stock
                        // Don't add to returnBad - it doesn't affect stock calculation
                    }
                } else {
                    // Normal INV item = Stock OUT
                    $stockOut += $qty;
                }
            } elseif ($orderItem->type === 'CN') {
                if ($tradeReturnIsGood) {
                    // Trade return good = Stock IN (returnGood) - adds to available stock
                    $returnGood += $qty;
                } else {
                    // Trade return bad = No stock change (do nothing)
                    // Items are physically returned but don't add to available stock
                    $returnBad += $qty; // Track for display/reporting only
                }
            }
        }

        // 2. Calculate from item_transactions - ONLY standalone transactions (no reference_type)
        // IMPORTANT: Exclude transactions with reference_type to avoid double counting orders
        // Standalone transactions include: opening balance, manual adjustments, etc.
        $transactions = ItemTransaction::where('agent_no', $agentNo)
            ->where('ITEMNO', $itemNo)
            ->whereNull('reference_type') // Only standalone transactions
            ->get();

        foreach ($transactions as $transaction) {
            $qty = (float) $transaction->quantity;

            if ($transaction->transaction_type === 'in') {
                // All 'in' transactions = Stock IN (stored as positive)
                $stockIn += $qty;
            } elseif ($transaction->transaction_type === 'out') {
                // All 'out' transactions = Stock OUT (convert to positive for calculation)
                $stockOut += abs($qty);
            } elseif ($transaction->transaction_type === 'adjustment') {
                // Adjustments can be positive or negative
                if ($qty >= 0) {
                    $stockIn += $qty;
                } else {
                    $stockOut += abs($qty);
                }
            }
        }

        // Available stock = stockIn + returnGood - stockOut
        // Note: returnBad is NOT subtracted (trade return bad doesn't affect stock)
        $available = $stockIn + $returnGood - $stockOut;

        return [
            'stockIn' => $stockIn,
            'stockOut' => $stockOut,
            'returnGood' => $returnGood,
            'returnBad' => $returnBad,
            'available' => max(0, $available), // Never go negative in display
        ];
    }

    /**
     * Get stock movements (transaction details) for an agent and item
     * 
     * Combines data from:
     * 1. item_transactions table (standalone only - no reference_type)
     * 2. orders table (DO, INV, CN)
     * 
     * @param string $agentNo Agent number (user name)
     * @param string $itemNo Item number (ITEMNO from icitem)
     * @param array $filters Optional filters: ['transaction_type', 'date_from', 'date_to']
     * @return \Illuminate\Support\Collection Collection of movement records
     */
    public function getStockMovements(string $agentNo, string $itemNo, array $filters = []): \Illuminate\Support\Collection
    {
        $allTransactions = collect([]);

        // 1. Get transactions from item_transactions table (standalone only)
        $itemTransactionsQuery = ItemTransaction::where('ITEMNO', $itemNo)
            ->where('agent_no', $agentNo)
            ->whereNull('reference_type'); // Only standalone transactions

        // Filter by transaction type if provided
        if (!empty($filters['transaction_type'])) {
            $itemTransactionsQuery->where('transaction_type', $filters['transaction_type']);
        }

        // Filter by date range if provided
        if (!empty($filters['date_from'])) {
            $itemTransactionsQuery->whereDate('CREATED_ON', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $itemTransactionsQuery->whereDate('CREATED_ON', '<=', $filters['date_to']);
        }

        $itemTransactions = $itemTransactionsQuery->get();

        // Transform item_transactions to a common format
        // Note: stock_before/stock_after set to null - will be recalculated in preprocessing
        foreach ($itemTransactions as $trans) {
            $allTransactions->push([
                'date' => $trans->CREATED_ON->toDateTimeString(),
                'type' => $trans->transaction_type,
                'quantity' => $trans->quantity,
                'stock_before' => null, // Will be recalculated
                'stock_after' => null,  // Will be recalculated
                'reference_type' => $trans->reference_type,
                'reference_id' => $trans->reference_id,
                'notes' => $trans->notes,
                'source' => 'item_transaction',
                'id' => $trans->id,
            ]);
        }

        // 2. Get transactions from orders (DO, INV, CN types)
        $ordersQuery = DB::table('orders')
            ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
            ->where('orders.agent_no', $agentNo)
            ->where('order_items.product_no', $itemNo)
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
        if (!empty($filters['date_from'])) {
            $ordersQuery->whereDate('orders.order_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $ordersQuery->whereDate('orders.order_date', '<=', $filters['date_to']);
        }

        $orders = $ordersQuery->get();

        // Transform orders to transaction format
        foreach ($orders as $order) {
            $transactionType = null;
            $quantity = (float) $order->quantity;
            $tradeReturnIsGood = (bool) ($order->trade_return_is_good ?? true);

            if ($order->type === 'DO') {
                $transactionType = 'in';
            } elseif ($order->type === 'INV') {
                $transactionType = 'out';
            } elseif ($order->type === 'CN' && $tradeReturnIsGood) {
                $transactionType = 'in'; // CN with good return = stock in
            }

            if ($transactionType === null) {
                continue;
            }

            // Skip if transaction type filter doesn't match
            if (!empty($filters['transaction_type']) && $transactionType !== $filters['transaction_type']) {
                continue;
            }

            $notes = '-';
            if ($order->type === 'DO') {
                $notes = 'DO';
            } elseif ($order->type === 'INV') {
                $notes = 'Invoice';
            } elseif ($order->type === 'CN') {
                $notes = 'CN';
            }

            $allTransactions->push([
                'date' => $order->order_date,
                'type' => $transactionType,
                'quantity' => $transactionType === 'out' ? -abs($quantity) : abs($quantity),
                'stock_before' => null,
                'stock_after' => null,
                'reference_type' => 'order',
                'reference_id' => $order->reference_no,
                'notes' => $notes,
                'source' => 'order',
                'id' => 'order_' . $order->id,
            ]);
        }

        // Preprocess: Calculate stock_before and stock_after for each transaction
        // Sort by date ASCENDING (oldest first) to calculate running totals
        $sortedAsc = $allTransactions->sortBy([
            ['date', 'asc'],
            ['reference_id', 'asc'],
        ])->values();

        // Calculate running stock totals
        $runningStock = 0;
        $processedTransactions = collect([]);

        foreach ($sortedAsc as $trans) {
            $type = $trans['type'];
            $quantity = abs($trans['quantity']);

            $stockBefore = $runningStock;

            // Calculate stock change
            if ($type === 'in' || $type === 'adjustment') {
                $runningStock += $quantity;
            } else {
                // 'out' type
                $runningStock -= $quantity;
            }

            $stockAfter = $runningStock;

            // Update transaction with calculated values
            $trans['stock_before'] = $stockBefore;
            $trans['stock_after'] = $stockAfter;
            $processedTransactions->push($trans);
        }

        // Sort all transactions by date (descending) for display
        return $processedTransactions->sortByDesc('date')->values();
    }

    /**
     * Get paginated stock movements for an agent and item
     * 
     * @param string $agentNo Agent number (user name)
     * @param string $itemNo Item number (ITEMNO from icitem)
     * @param array $filters Optional filters: ['transaction_type', 'date_from', 'date_to']
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param string $url Base URL for pagination
     * @param array $queryParams Query parameters for pagination links
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getStockMovementsPaginated(
        string $agentNo,
        string $itemNo,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
        string $url = '',
        array $queryParams = []
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $allTransactions = $this->getStockMovements($agentNo, $itemNo, $filters);

        $total = $allTransactions->count();
        $items = $allTransactions->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $url, 'query' => $queryParams]
        );
    }

    /**
     * Get available stock for an agent and item
     *
     * @param string $agentNo Agent number
     * @param string $itemNo Item number
     * @return float Available stock quantity
     */
    public function getAvailableStock(string $agentNo, string $itemNo): float
    {
        $totals = $this->calculateStockTotals($agentNo, $itemNo);
        return $totals['available'];
    }

    /**
     * Check if sufficient stock is available
     *
     * @param string $agentNo Agent number
     * @param string $itemNo Item number
     * @param float $requiredQty Required quantity
     * @return bool True if sufficient stock available
     */
    public function hasSufficientStock(string $agentNo, string $itemNo, float $requiredQty): bool
    {
        $available = $this->getAvailableStock($agentNo, $itemNo);
        return $available >= $requiredQty;
    }

    /**
     * Validate stock availability for order items
     *
     * @param string $agentNo Agent number
     * @param array $items Array of order items with product_no and quantity
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateOrderStock(string $agentNo, array $items): array
    {
        $errors = [];

        foreach ($items as $item) {
            $itemNo = $item['product_no'] ?? null;
            $quantity = (float) ($item['quantity'] ?? 0);
            $isTradeReturn = $item['is_trade_return'] ?? false;

            // Skip validation for trade returns (they add stock back)
            if ($isTradeReturn) {
                continue;
            }

            if (!$itemNo) {
                $errors[] = "Item missing product_no";
                continue;
            }

            if ($quantity <= 0) {
                continue; // Skip zero quantity items
            }

            if (!$this->hasSufficientStock($agentNo, $itemNo, $quantity)) {
                $available = $this->getAvailableStock($agentNo, $itemNo);
                $errors[] = "Insufficient stock for item {$itemNo}. Available: {$available}, Required: {$quantity}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Record stock movement for an order
     *
     * @param Order $order The order
     * @return void
     */
    public function recordOrderMovements(Order $order): void
    {
        $agentNo = $order->agent_no;
        if (!$agentNo) {
            Log::warning("Order {$order->id} has no agent_no, skipping stock movement");
            return;
        }

        $orderType = $order->type;
        $referenceType = 'order';
        $referenceId = $order->id;

        foreach ($order->items as $orderItem) {
            $itemNo = $orderItem->product_no;
            if (!$itemNo) {
                continue;
            }

            $quantity = (float) $orderItem->quantity;
            $isTradeReturn = $orderItem->is_trade_return ?? false;
            $tradeReturnIsGood = $orderItem->trade_return_is_good ?? true;

            // Get current stock before transaction
            $stockBefore = $this->getAvailableStock($agentNo, $itemNo);

            if ($orderType === 'DO') {
                // DO = Stock IN
                $this->recordMovement(
                    $agentNo,
                    $itemNo,
                    $quantity,
                    'in',
                    $referenceType,
                    $referenceId,
                    "DO Order: {$order->reference_no}"
                );
            } elseif ($orderType === 'INV') {
                if ($isTradeReturn) {
                    if ($tradeReturnIsGood) {
                        // Trade return good = Stock IN
                        $this->recordMovement(
                            $agentNo,
                            $itemNo,
                            $quantity,
                            'in',
                            $referenceType,
                            $referenceId,
                            "INV Trade Return Good: {$order->reference_no}"
                        );
                    } else {
                        // Trade return bad = No stock change (do nothing)
                        // Don't record transaction - it doesn't affect stock
                    }
                } else {
                    // Normal INV item = Stock OUT
                    $this->recordMovement(
                        $agentNo,
                        $itemNo,
                        $quantity,
                        'out',
                        $referenceType,
                        $referenceId,
                        "INV Order: {$order->reference_no}"
                    );
                }
            } elseif ($orderType === 'CN') {
                // CN = Trade Return
                if ($tradeReturnIsGood) {
                    // Trade return good = Stock IN
                    $this->recordMovement(
                        $agentNo,
                        $itemNo,
                        $quantity,
                        'in',
                        $referenceType,
                        $referenceId,
                        "CN Trade Return Good: {$order->reference_no}"
                    );
                } else {
                    // Trade return bad = No stock change (do nothing)
                    // Don't record transaction - it doesn't affect stock
                }
            }
        }
    }

    /**
     * Record a stock movement transaction
     *
     * @param string $agentNo Agent number
     * @param string $itemNo Item number
     * @param float $quantity Quantity (positive for in, negative for out)
     * @param string $transactionType 'in' or 'out'
     * @param string $referenceType Reference type (e.g., 'order')
     * @param string|int $referenceId Reference ID
     * @param string|null $notes Additional notes
     * @return ItemTransaction
     */
    public function recordMovement(
        string $agentNo,
        string $itemNo,
        float $quantity,
        string $transactionType,
        string $referenceType,
        $referenceId,
        ?string $notes = null
    ): ItemTransaction {
        // Ensure quantity is positive (we use transaction_type to indicate direction)
        $quantity = abs($quantity);

        // Get stock before
        $stockBefore = $this->getAvailableStock($agentNo, $itemNo);

        // Calculate stock after
        $stockAfter = $stockBefore;
        if ($transactionType === 'in') {
            $stockAfter += $quantity;
        } elseif ($transactionType === 'out') {
            $stockAfter -= $quantity;
        }

        // Create transaction record
        $transaction = ItemTransaction::create([
            'ITEMNO' => $itemNo,
            'agent_no' => $agentNo,
            'transaction_type' => $transactionType,
            'quantity' => $quantity, // Store as positive, type indicates direction
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'CREATED_BY' => auth()->user()?->name ?? auth()->user()?->username ?? 'system',
        ]);

        return $transaction;
    }

    /**
     * Reverse stock movements for an order (used when updating or deleting)
     *
     * @param Order $order The order
     * @return void
     */
    public function reverseOrderMovements(Order $order): void
    {
        // Find all transactions for this order
        $transactions = ItemTransaction::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->get();

        foreach ($transactions as $transaction) {
            // Reverse the transaction by creating opposite movement
            $reverseType = $transaction->transaction_type === 'in' ? 'out' : 'in';

            // Use the recordMovement method to ensure consistency
            $this->recordMovement(
                $transaction->agent_no,
                $transaction->ITEMNO,
                $transaction->quantity,
                $reverseType,
                'order_reversal',
                $order->id,
                "Reversal of order: {$order->reference_no}"
            );
        }
    }

    /**
     * Get stock summary for all items for an agent
     *
     * @param string $agentNo Agent number
     * @return array Array of items with stock totals
     */
    public function getAgentStockSummary(string $agentNo): array
    {
        // Optimized query - get all order items for this agent in one query using reference_no
        $orderItems = DB::table('orders')
            ->join('order_items', 'orders.reference_no', '=', 'order_items.reference_no')
            ->where('orders.agent_no', $agentNo)
            ->whereNotNull('order_items.product_no')
            ->select(
                'order_items.product_no',
                'orders.type',
                'order_items.quantity',
                'order_items.is_trade_return',
                'order_items.trade_return_is_good'
            )
            ->get();

        $itemTotals = [];

        foreach ($orderItems as $orderItem) {
            $itemNo = $orderItem->product_no;
            if (!$itemNo) {
                continue;
            }

            if (!isset($itemTotals[$itemNo])) {
                $itemTotals[$itemNo] = [
                    'ITEMNO' => $itemNo,
                    'stockIn' => 0,
                    'stockOut' => 0,
                    'returnGood' => 0,
                    'returnBad' => 0,
                ];
            }

            $qty = (float) $orderItem->quantity;
            $isTradeReturn = (bool) ($orderItem->is_trade_return ?? false);
            $tradeReturnIsGood = (bool) ($orderItem->trade_return_is_good ?? true);

            if ($orderItem->type === 'DO') {
                $itemTotals[$itemNo]['stockIn'] += $qty;
            } elseif ($orderItem->type === 'INV') {
                if ($isTradeReturn) {
                    if ($tradeReturnIsGood) {
                        // Trade return good = adds to available stock
                        $itemTotals[$itemNo]['returnGood'] += $qty;
                    } else {
                        // Trade return bad = no stock change (do nothing)
                        // Don't add to returnBad - it doesn't affect stock
                    }
                } else {
                    $itemTotals[$itemNo]['stockOut'] += $qty;
                }
            } elseif ($orderItem->type === 'CN') {
                // CN = Trade Return
                if ($tradeReturnIsGood) {
                    // Trade return good = adds to available stock
                    $itemTotals[$itemNo]['returnGood'] += $qty;
                } else {
                    // Trade return bad = no stock change (do nothing)
                    // Don't add to returnBad - it doesn't affect stock
                }
            }
        }

        // Also include item_transactions - ONLY standalone transactions (no reference_type)
        // IMPORTANT: Exclude transactions with reference_type to avoid double counting orders
        // Standalone transactions include: opening balance, manual adjustments, etc.
        try {
            $transactions = ItemTransaction::where('agent_no', $agentNo)
                ->whereNull('reference_type') // Only standalone transactions
                ->get();

            foreach ($transactions as $transaction) {
                $itemNo = $transaction->ITEMNO;
                if (!$itemNo) {
                    continue;
                }

                if (!isset($itemTotals[$itemNo])) {
                    $itemTotals[$itemNo] = [
                        'ITEMNO' => $itemNo,
                        'stockIn' => 0,
                        'stockOut' => 0,
                        'returnGood' => 0,
                        'returnBad' => 0,
                    ];
                }

                $qty = (float) $transaction->quantity;

                if ($transaction->transaction_type === 'in') {
                    // All 'in' transactions = Stock IN (stored as positive)
                    $itemTotals[$itemNo]['stockIn'] += $qty;
                } elseif ($transaction->transaction_type === 'out') {
                    // All 'out' transactions = Stock OUT (convert to positive for calculation)
                    $itemTotals[$itemNo]['stockOut'] += abs($qty);
                } elseif ($transaction->transaction_type === 'adjustment') {
                    // Adjustments can be positive or negative
                    if ($qty >= 0) {
                        $itemTotals[$itemNo]['stockIn'] += $qty;
                    } else {
                        $itemTotals[$itemNo]['stockOut'] += abs($qty);
                    }
                }
            }
        } catch (\Exception $e) {
            // If item_transactions table doesn't exist, just skip it
            Log::debug("item_transactions table not available or error: " . $e->getMessage());
        }

        // Calculate available for each item
        $summary = [];
        foreach ($itemTotals as $itemNo => $totals) {
            // Available stock = stockIn + returnGood - stockOut
            // Note: returnBad is NOT subtracted (trade return bad doesn't affect stock)
            $available = $totals['stockIn'] + $totals['returnGood'] - $totals['stockOut'];
            $summary[] = [
                'ITEMNO' => $itemNo,
                'stockIn' => $totals['stockIn'],
                'stockOut' => $totals['stockOut'],
                'returnGood' => $totals['returnGood'],
                'returnBad' => $totals['returnBad'],
                'available' => max(0, $available),
            ];
        }

        return $summary;
    }

    /*
    
    private function calculateCurrentStock($itemno, $agentNo)
    {
        if (!$agentNo || empty($agentNo)) {
            throw new \InvalidArgumentException('agentNo is required for stock calculations. Stock is by agent.');
        }

        $non_accepted_reference_type = [
            'invoice',
            'order',
            'test'
        ];
        $item_trans_in = ItemTransaction::where('ITEMNO', $itemno)
            ->whereNotIn('reference_type', $non_accepted_reference_type)
            ->where('transaction_type', 'in')
            ->where('agent_no', $agentNo)
            ->sum('quantity');

        $item_trans_out = ItemTransaction::where('ITEMNO', $itemno)
            ->whereNotIn('reference_type', $non_accepted_reference_type)
            ->where('transaction_type', 'out')
            ->where('agent_no', $agentNo)
            ->sum('quantity');

        $total_trans = $item_trans_in - $item_trans_out;


        $order_summary = DB::Table('order_items AS oi')
        ->selectRaw('
            SUM(IF(o.type = "DO", oi.quantity, 0)) AS do_stock_in,
            SUM(IF(o.type = "CN" AND oi.trade_return_is_good = 1, oi.quantity, 0)) AS cn_stock_in,
            SUM(IF(o.type = "INV", oi.quantity, 0)) AS inv_stock_out
        ')
        ->leftJoin('orders AS o','o.reference_no','=','oi.reference_no')
        ->where('oi.product_no', $itemno)
        ->where('o.agent_no', $agentNo)
        ->first();

        $do_stock_in = $order_summary->do_stock_in ?? 0;
        $cn_stock_in = $order_summary->cn_stock_in ?? 0;
        $inv_stock_out = $order_summary->inv_stock_out ?? 0;

        $stock = $total_trans + $do_stock_in

        
    }
    */
}
