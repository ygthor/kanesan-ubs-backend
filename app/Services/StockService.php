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

        // Calculate from orders - optimized query with direct join using reference_no
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
            }
            elseif ($orderItem->type === 'CN') {
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

        // Also calculate from item_transactions (if table exists and has data)
        try {
            $transactions = ItemTransaction::where('agent_no', $agentNo)
                ->where('ITEMNO', $itemNo)
                ->get();

            foreach ($transactions as $transaction) {
                $qty = (float) $transaction->quantity;

                if ($transaction->transaction_type === 'in') {
                    // All 'in' transactions = Stock IN
                    $stockIn += $qty;
                } elseif ($transaction->transaction_type === 'out') {
                    // All 'out' transactions = Stock OUT
                    $stockOut += $qty;
                }
            }
        } catch (\Exception $e) {
            // If item_transactions table doesn't exist, just skip it
            // We'll rely on orders only
            Log::debug("item_transactions table not available or error: " . $e->getMessage());
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

        // Also include item_transactions (opening balance, manual adjustments, etc.)
        try {
            $transactions = ItemTransaction::where('agent_no', $agentNo)
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
                    // All 'in' transactions = Stock IN
                    $itemTotals[$itemNo]['stockIn'] += $qty;
                } elseif ($transaction->transaction_type === 'out') {
                    // All 'out' transactions = Stock OUT
                    $itemTotals[$itemNo]['stockOut'] += $qty;
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
}
