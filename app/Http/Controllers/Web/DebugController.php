<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ItemTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebugController extends Controller
{
    /**
     * Show debug page for order items and item transactions tally
     */
    public function orderItemsTransactions(Request $request)
    {
        $agentNo = $request->get('agent_no');
        $productNo = $request->get('product_no');

        // Build query for order items with order information
        $query = OrderItem::select([
                'order_items.id',
                'order_items.reference_no',
                'order_items.product_no',
                'order_items.product_name',
                'order_items.quantity',
                'order_items.unit_price',
                'order_items.amount',
                'order_items.is_free_good',
                'order_items.is_trade_return',
                'orders.type',
                'orders.agent_no',
                'orders.order_date',
                'orders.customer_code',
                'orders.customer_name',
                'orders.status'
            ])
            ->join('orders', 'order_items.reference_no', '=', 'orders.reference_no')
            ->orderBy('orders.order_date', 'desc')
            ->orderBy('order_items.id', 'desc');

        // Apply filters
        if ($agentNo) {
            $query->where('orders.agent_no', $agentNo);
        }

        if ($productNo) {
            $query->where('order_items.product_no', $productNo);
        }

        // Paginate results (50 per page for performance)
        $orderItems = $query->paginate(50)->withQueryString();

        // For each order item, check if corresponding item_transaction exists
        $debugData = [];
        foreach ($orderItems as $orderItem) {
            // Determine expected transaction type based on order type
            $expectedTransactionType = $this->getExpectedTransactionType($orderItem->type);

            // Check if item transaction exists for this order item
            $itemTransaction = ItemTransaction::where('reference_type', 'order')
                ->where('reference_id', $orderItem->id)
                ->first();

            $debugData[] = [
                'order_item' => $orderItem,
                'expected_transaction_type' => $expectedTransactionType,
                'has_item_transaction' => $itemTransaction !== null,
                'item_transaction' => $itemTransaction,
                'transaction_matches' => $itemTransaction ?
                    $this->validateTransaction($orderItem, $itemTransaction, $expectedTransactionType) : false,
                'mismatch_details' => $itemTransaction ?
                    $this->getMismatchDetails($orderItem, $itemTransaction, $expectedTransactionType) : null
            ];
        }

        // Get distinct agent_no values for filter dropdown
        $agentNos = Order::distinct()->pluck('agent_no')->filter()->sort()->values();

        // Get distinct product_no values for filter dropdown
        $productNos = OrderItem::distinct()->pluck('product_no')->filter()->sort()->values();

        return view('debug.order-items-transactions', compact(
            'debugData',
            'orderItems',
            'agentNos',
            'productNos',
            'agentNo',
            'productNo'
        ));
    }

    /**
     * Get expected transaction type based on order type
     * CN (Credit Note) = stock in (return)
     * INV (Invoice) = stock out (sale)
     * DO (Delivery Order) = stock out (but might be different)
     */
    private function getExpectedTransactionType($orderType)
    {
        switch ($orderType) {
            case 'CN':
                return 'in'; // Stock return for credit notes
            case 'INV':
                return 'out'; // Stock sale for invoices
            case 'DO':
                return 'out'; // Stock out for delivery orders
            default:
                return 'unknown';
        }
    }

    /**
     * Validate if the transaction matches what we expect
     */
    private function validateTransaction($orderItem, $transaction, $expectedType)
    {
        if ($transaction->transaction_type !== $expectedType) {
            return false;
        }

        // For stock out (INV/DO), quantity should be negative or match order item quantity
        // For stock in (CN), quantity should be positive or match order item quantity
        if ($expectedType === 'out') {
            // For stock out, transaction quantity should be negative of order item quantity
            return abs($transaction->quantity) === abs($orderItem->quantity);
        } elseif ($expectedType === 'in') {
            // For stock in, transaction quantity should match order item quantity
            return $transaction->quantity == $orderItem->quantity;
        }

        return true;
    }

    /**
     * Get details about what's mismatched
     */
    private function getMismatchDetails($orderItem, $transaction, $expectedType)
    {
        $details = [];

        if ($transaction->transaction_type !== $expectedType) {
            $details[] = "Transaction type mismatch: expected '{$expectedType}', got '{$transaction->transaction_type}'";
        }

        if ($expectedType === 'out') {
            if (abs($transaction->quantity) !== abs($orderItem->quantity)) {
                $details[] = "Quantity mismatch: expected " . abs($orderItem->quantity) . ", got " . abs($transaction->quantity);
            }
        } elseif ($expectedType === 'in') {
            if ($transaction->quantity != $orderItem->quantity) {
                $details[] = "Quantity mismatch: expected {$orderItem->quantity}, got {$transaction->quantity}";
            }
        }

        return $details;
    }

    /**
     * Create missing item transaction for an order item
     */
    public function createMissingTransaction(Request $request)
    {
        $orderItemId = $request->get('order_item_id');

        $orderItem = OrderItem::with('order')->findOrFail($orderItemId);

        // Skip if transaction already exists
        $existingTransaction = ItemTransaction::where('reference_type', 'order')
            ->where('reference_id', $orderItem->id)
            ->first();

        if ($existingTransaction) {
            return response()->json(['error' => 'Transaction already exists'], 400);
        }

        $expectedType = $this->getExpectedTransactionType($orderItem->order->type);

        // Calculate quantity based on transaction type
        $quantity = $expectedType === 'out' ? -$orderItem->quantity : $orderItem->quantity;

        // Get current stock for the item
        $currentStock = ItemTransaction::where('ITEMNO', $orderItem->product_no)
            ->sum('quantity');

        $stockAfter = $currentStock + $quantity;

        // Create the transaction
        $transaction = ItemTransaction::create([
            'ITEMNO' => $orderItem->product_no,
            'agent_no' => $orderItem->order->agent_no,
            'transaction_type' => $expectedType,
            'quantity' => $quantity,
            'reference_type' => 'order',
            'reference_id' => $orderItem->id,
            'notes' => "Auto-created for {$orderItem->order->type} {$orderItem->reference_no}",
            'stock_before' => $currentStock,
            'stock_after' => $stockAfter,
            'CREATED_BY' => auth()->id(),
            'UPDATED_BY' => auth()->id(),
            'CREATED_ON' => now(),
            'UPDATED_ON' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully',
            'transaction' => $transaction
        ]);
    }

    /**
     * Delete an item transaction
     */
    public function deleteTransaction(Request $request)
    {
        $transactionId = $request->get('transaction_id');

        $transaction = ItemTransaction::findOrFail($transactionId);

        // Only allow deletion of transactions created for debugging
        if (strpos($transaction->notes, 'Auto-created') === false) {
            return response()->json(['error' => 'Only auto-created transactions can be deleted'], 400);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully'
        ]);
    }
}
