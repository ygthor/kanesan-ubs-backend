<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer; // To fetch customer details if needed
use App\Models\Product;  // To fetch product details if needed
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Retrieve all filter parameters from the request
        $customerId = $request->input('customer_id');
        $customerCode = $request->input('customer_code');
        $customerName = $request->input('customer_name');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $orderType = $request->input('order_type');
        $paginate = $request->input('paginate');

        if($paginate === null){
            $paginate = true;
        }

        // Start building the query
        $orders = Order::with('items.item', 'customer');

        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $orders->whereHas('customer', function ($query) use ($user) {
                $query->whereIn('agent_no', [$user->name]);
            });
        }

        // --- Apply filters conditionally ---

        // Filter by customer name (search in both name and company_name)
        if ($customerName) {
            $orders->whereHas('customer', function ($query) use ($customerName) {
                $query->where(function ($q) use ($customerName) {
                    $q->where('name', 'like', "%{$customerName}%")
                      ->orWhere('company_name', 'like', "%{$customerName}%");
                });
            });
        }
        if($customerCode){
            $orders->where('customer_code', 'like', "{$customerCode}");
        }

        if($customerId){
            $orders->where('customer_id', 'like', "{$customerId}");
        }

        // Filter by date range
        if ($startDate) {
            // Assuming your order_date is a DateTime column
            $orders->whereDate('order_date', '>=', $startDate);
        }

        if ($endDate) {
            $orders->whereDate('order_date', '<=', $endDate);
        }

        // Filter by order type
        // If called from /api/invoices route, default to 'INV' type
        if (request()->is('api/invoices*')) {
            $orderType = $orderType ?? 'INV';
        }

        if ($orderType) {
            // The Flutter app sends a comma-separated string, so we need to split it
            $types = explode(',', $orderType);

            // Filter by order types (INV, CN, SO, etc.)
            // Note: 'TR' type is deprecated - use 'CN' (Credit Note) instead
            // Remove 'TR' if present and map to 'CN'
            $types = array_map(function($type) {
                return $type === 'TR' ? 'CN' : $type;
            }, $types);
            $types = array_unique($types);

            $orders->whereIn('type', $types);
        }

        // Order the results
        $orders->orderBy('order_date', 'desc');
        $orders->orderBy('id', 'desc');

        // Paginate the results
        if($paginate){
            $orders = $orders->paginate($request->input('per_page', 15));
            $ordersCollection = $orders->getCollection();
        }else{
            $orders = $orders->get();
            $ordersCollection = $orders;
        }

        // Calculate adjusted net amount for INV orders (deduct linked CN totals)
        foreach ($ordersCollection as $order) {
            if ($order->type === 'INV') {
                // Get linked CN orders for this invoice
                /* $linkedCNs = Order::where('credit_invoice_no', $order->reference_no)
                    ->where('type', 'CN')
                    ->with('items')
                    ->get();

                // Calculate total from linked CN orders
                $cnTotal = 0.0;
                foreach ($linkedCNs as $cnOrder) {
                    foreach ($cnOrder->items as $item) {
                        $cnTotal += ($item->quantity * $item->unit_price);
                    }
                }

                // Calculate adjusted net amount (original net_amount - CN total)
                $adjustedNetAmount = ($order->net_amount ?? 0.0) - $cnTotal; */

                $adjustedNetAmount = 0.0;
                foreach ($order->items as $item) {
                    // Check if item is a free good
                    if (!$item->isFreeGood) {
                        $adjustedNetAmount += ($item->quantity * $item->unit_price);
                    }
                }

                // Add adjusted_net_amount to the order data
                $order->setAttribute('adjusted_net_amount', $adjustedNetAmount);
            } else {
                // For non-INV orders, adjusted_net_amount is the same as net_amount
                $order->setAttribute('adjusted_net_amount', $order->net_amount ?? 0.0);
            }
        }

        return makeResponse(200, 'Orders retrieved successfully.', $orders);
    }

    /**
     * Store a newly created order in storage.
     *
     * @bodyParam customer_id string required The ID of the customer. Example: "1"
     * @bodyParam customer_name string required The name of the customer. Example: "AHS3185 S02 KANESAN"
     * @bodyParam remarks string nullable Any remarks for the order.
     * @bodyParam items array required An array of order items.
     * @bodyParam items.*.product_id string required The ID of the product. Example: "p1" (or integer ID from your products table)
     * @bodyParam items.*.quantity float required The quantity of the product. Example: 2
     * @bodyParam items.*.unit_price float required The unit price of the product. Example: 10.00
     * @bodyParam items.*.discount float nullable The discount for this item. Example: 1.00
     * @bodyParam items.*.is_free_good boolean nullable Is this a free good? Example: false
     * @bodyParam items.*.is_trade_return boolean nullable Is this a trade return? Example: false
     * @bodyParam items.*.trade_return_is_good boolean nullable If trade return, is it good condition? Example: true
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->saveOrder($request);
    }

    public function update(Request $request, $id)
    {
        return $this->saveOrder($request, $id);
    }


    public function saveOrder(Request $request, $id = null)
    {
        $validator = Validator::make($request->all(), [
            // 'type' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'remarks' => 'nullable|string',
            // 'items' => 'required|array|min:1',
            // 'items.*.product_id' => 'required|exists:product,id',
            // 'items.*.quantity' => 'required|numeric|min:0',
            // 'items.*.unit_price' => 'required|numeric|min:0',
            // 'items.*.discount' => 'nullable|numeric|min:0',
            // 'items.*.is_free_good' => 'sometimes|boolean',
            // 'items.*.is_trade_return' => 'sometimes|boolean',
            // 'items.*.trade_return_is_good' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            $orderData = $request->only([
                'branch_id',
                'customer_id',
                'remarks',
                'tax1_percentage',
                'discount'
            ]);

            // Only set order_date when creating new orders, not when updating
            // Preserve original order_date when updating
            if (!$id) {
                $orderData['order_date'] = now();
            }
            $orderData['branch_id'] = $orderData['branch_id'] ?? 0;
            // If called from /api/invoices route, default to 'INV', otherwise use request or default to INV
            if (request()->is('api/invoices*')) {
                $orderData['type'] = $request->input('type', 'INV');
            } else {
                $orderData['type'] = $request->input('type', 'INV');
            }

            $customer = Customer::find($orderData['customer_id']);
            if (!$customer) {
                DB::rollBack();
                return makeResponse(404, 'Customer not found with ID: ' . $orderData['customer_id']);
            }

            $orderData['customer_code'] = $customer->customer_code;
            // Use company_name if available, otherwise use name field
            $orderData['customer_name'] = $customer->company_name ?? $customer->name ?? 'N/A';

            // Handle agent_no: KBS users can set/update it, others default to their name
            $user = auth()->user();
            if ($user) {
                $isKBS = ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my');

                if ($isKBS && $request->has('agent_no') && $request->input('agent_no') !== null) {
                    // KBS user: use agent_no from request if provided (can be empty string to clear)
                    $orderData['agent_no'] = $request->input('agent_no');
                } elseif (!$isKBS) {
                    // Non-KBS: always default to user's name
                    $orderData['agent_no'] = $user->name ?? $user->username ?? null;
                } elseif ($isKBS && !$id) {
                    // KBS in create mode without agent_no: default to user's name
                    $orderData['agent_no'] = $user->name ?? $user->username ?? null;
                }
                // For KBS in update mode without agent_no: don't set it, keep existing value
            }

            $orderData['status'] = 'pending';

            // Initialize stock service
            $stockService = new StockService();

            if ($id) {
                // ✅ Update mode - reverse existing stock movements first
                $order = Order::with('items.item')->findOrFail($id);

                // Reverse existing stock movements before updating
                $stockService->reverseOrderMovements($order);

                // Delete old items
                $order->items()->delete();

                $order->fill($orderData)->save();
            } else {
                // ✅ Create mode
                $order = Order::create($orderData);
                $order->reference_no = $order->getReferenceNo();
                $order->save();
            }

            // Get agent_no for stock validation
            $agentNo = $order->agent_no;
            if (!$agentNo) {
                DB::rollBack();
                return makeResponse(422, 'Agent number is required for stock management.', null);
            }

            $items = $request->input('items') ?? [];

            // Separate regular items from trade return items
            $regularItems = [];
            $tradeReturnItems = [];

            foreach ($items as $itemData) {
                if ($itemData['is_trade_return'] ?? false) {
                    $tradeReturnItems[] = $itemData;
                } else {
                    $regularItems[] = $itemData;
                }
            }

            // Helper function to get product by product_id or product_no
            $getProduct = function($itemData) {
                if (isset($itemData['product_id'])) {
                    return Product::find($itemData['product_id']);
                } elseif (isset($itemData['product_no'])) {
                    return Product::where('product_no', $itemData['product_no'])->first();
                }
                return null;
            };

            // Prepare items for stock validation (for INV orders - regular items only)
            if ($orderData['type'] === 'INV' && !empty($regularItems)) {
                $itemsForValidation = [];
                foreach ($regularItems as $itemData) {
                    $product = $getProduct($itemData);
                    if (!$product) {
                        continue;
                    }
                    $itemsForValidation[] = [
                        'product_no' => $product->product_no,
                        'quantity' => $itemData['quantity'] ?? 0,
                        'is_trade_return' => false,
                    ];
                }

                // Validate stock availability for INV orders
                if (!empty($itemsForValidation)) {
                    $stockValidation = $stockService->validateOrderStock($agentNo, $itemsForValidation);
                    if (!$stockValidation['valid']) {
                        DB::rollBack();
                        return makeResponse(422, 'Stock validation failed.', ['errors' => $stockValidation['errors']]);
                    }
                }
            }

            // Create INV order with regular items only (even if no regular items, create empty INV for CN to link to)
            $invOrder = $order;
            $invReferenceNo = $order->reference_no;
            $invItemCount = 1;

            // Add regular items to INV order
            foreach ($regularItems as $itemData) {
                $product = $getProduct($itemData);
                if (!$product) {
                    DB::rollBack();
                    $productId = $itemData['product_id'] ?? $itemData['product_no'] ?? 'unknown';
                    return makeResponse(400, 'Invalid product ID or product_no: ' . $productId);
                }

                $unitPrice = $itemData['is_free_good'] ? 0 : ($itemData['unit_price'] ?? $product->price);
                $quantity = $itemData['quantity'];
                $discount = $itemData['discount'] ?? 0;
                $unique_key = "$invReferenceNo|$invItemCount";

                $orderItem = $invOrder->items()->create([
                    'unique_key' => $unique_key,
                    'reference_no' => $invReferenceNo,
                    'order_id' => $invOrder->id,
                    'item_count' => $invItemCount,
                    'product_id' => $product->id,
                    'product_no' => $product->product_no,
                    'product_name' => $product->product_name,
                    'description' => $product->product_name,
                    'sku_code' => $product->sku_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'is_free_good' => $itemData['is_free_good'] ?? false,
                    'is_trade_return' => false, // Regular items are not trade returns
                    'trade_return_is_good' => true,
                    'item_group' => $itemData['item_group'] ?? null,
                ]);
                $orderItem->calculate();
                $orderItem->save();
                $invItemCount++;
            }

            $invOrder->calculate();
            $invOrder->save();

            // Reload INV order with items for stock movement recording
            $invOrder->load('items');

            // Record stock movements for the INV order
            $stockService->recordOrderMovements($invOrder);

            // Create CN order for trade return items if any exist
            $cnOrder = null;
            if (!empty($tradeReturnItems)) {
                // Create CN order
                $cnOrderData = $orderData;
                $cnOrderData['type'] = 'CN';
                $cnOrderData['credit_invoice_no'] = $invOrder->reference_no; // Link to INV order

                $cnOrder = Order::create($cnOrderData);
                $cnOrder->reference_no = $cnOrder->getReferenceNo();
                $cnOrder->save();

                // Add trade return items to CN order
                $cnReferenceNo = $cnOrder->reference_no;
                $cnItemCount = 1;

                foreach ($tradeReturnItems as $itemData) {
                    $product = $getProduct($itemData);
                    if (!$product) {
                        DB::rollBack();
                        $productId = $itemData['product_id'] ?? $itemData['product_no'] ?? 'unknown';
                        return makeResponse(400, 'Invalid product ID or product_no: ' . $productId);
                    }

                    $unitPrice = $itemData['is_free_good'] ? 0 : ($itemData['unit_price'] ?? $product->price);
                    $quantity = $itemData['quantity'];
                    $discount = $itemData['discount'] ?? 0;
                    $unique_key = "$cnReferenceNo|$cnItemCount";

                    $orderItem = $cnOrder->items()->create([
                        'unique_key' => $unique_key,
                        'reference_no' => $cnReferenceNo,
                        'order_id' => $cnOrder->id,
                        'item_count' => $cnItemCount,
                        'product_id' => $product->id,
                        'product_no' => $product->product_no,
                        'product_name' => $product->product_name,
                        'description' => $product->product_name,
                        'sku_code' => $product->sku_code,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'is_free_good' => $itemData['is_free_good'] ?? false,
                        'is_trade_return' => false, // CN items are not marked as trade return anymore
                        'trade_return_is_good' => $itemData['trade_return_is_good'] ?? true,
                        'item_group' => $itemData['item_group'] ?? null,
                    ]);
                    $orderItem->calculate();
                    $orderItem->save();
                    $cnItemCount++;
                }

                $cnOrder->calculate();
                $cnOrder->save();

                // Reload CN order with items for stock movement recording
                $cnOrder->load('items');

                // Record stock movements for the CN order
                $stockService->recordOrderMovements($cnOrder);
            }

            DB::commit();

            // Return INV order with items, and include CN order if created
            $invOrder->load('items.item', 'customer');

            // Prepare response data - return INV order as main data
            // Frontend can fetch CN order separately using credit_invoice_no if needed
            // For now, just return the INV order (CN order is already created and linked)
            $responseData = $invOrder;

            return makeResponse($id ? 200 : 201, $id ? 'Order updated successfully.' : 'Order created successfully.', $responseData);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order save failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to save order.', ['error' => $e->getMessage()]);
        }
    }


    /**
     * Display the specified order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = auth()->user();

        // Try to find by reference_no first (for invoice lookups), then by ID
        $order = Order::where('reference_no', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$order) {
            return makeResponse(404, 'Order/Invoice not found.', null);
        }

        // Check if user has access to this order's customer
        if (!$this->userHasAccessToCustomer($user, $order->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this order.', null);
        }

        // Load items and customer relationship for detailed view
        // Also load order relationship for items to optimize linked_credit_note_count calculation
        $order->load('items.item', 'items.order', 'customer');

        // If this is an INV order, also load linked CN orders
        $linkedCreditNotes = [];
        if ($order->type === 'INV') {
            $linkedCreditNotes = Order::where('credit_invoice_no', $order->reference_no)
                ->where('type', 'CN')
                ->with('items.item', 'customer')
                ->get()
                ->toArray();
        }

        $responseData = $order->toArray();
        if (!empty($linkedCreditNotes)) {
            $responseData['linked_credit_notes'] = $linkedCreditNotes;
        }

        return makeResponse(200, 'Order retrieved successfully.', $responseData);
    }


    public function deleteOrder($id)
    {
        $user = auth()->user();

        try {
            DB::beginTransaction();

            $order = Order::with('items.item', 'customer')->findOrFail($id);

            // Check if user has access to this order's customer
            if (!$this->userHasAccessToCustomer($user, $order->customer)) {
                return makeResponse(403, 'Access denied. You do not have permission to delete this order.', null);
            }

            // Reverse stock movements before deleting
            $stockService = new StockService();
            $stockService->reverseOrderMovements($order);

            // Delete all items
            $order->items()->delete();

            // Delete the order itself
            $order->delete();

            DB::commit();
            return makeResponse(200, 'Order deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete order: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete order.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if user has access to a specific customer
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Customer  $customer
     * @return bool
     */
    private function userHasAccessToCustomer($user, Customer $customer)
    {
        // KBS user or admin role has full access to all customers
        if ($user && hasFullAccess()) {
            return true;
        }

        // Check if user is assigned to this customer
        if ($user && $user->customers()->where('customers.id', $customer->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Get outstanding invoices (orders with type='INV') for a specific customer with calculated balances.
     *
     * This endpoint returns unpaid or partially paid invoices with:
     * - Outstanding balance (net_amount - total_payments)
     * - Total payments made from receipt_order table
     * - Simple, flat structure for easy frontend consumption
     *
     * Query parameters:
     * - customer_code: Customer code (required)
     * - include_paid: Set to 'true' to include fully paid invoices (default: false)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOutstandingInvoices(Request $request)
    {
        $user = auth()->user();
        $customerCode = $request->query('customer_code');
        $includePaid = $request->query('include_paid', 'false') === 'true';

        if (!$customerCode) {
            return makeResponse(400, 'customer_code parameter is required', null);
        }

        \Log::info("Fetching invoices for customer: {$customerCode}, include_paid: " . ($includePaid ? 'yes' : 'no'));

        // Find customer
        $customer = Customer::where('customer_code', $customerCode)->first();

        if (!$customer) {
            \Log::warning("Customer not found: {$customerCode}");
            return makeResponse(404, 'Customer not found', null);
        }

        \Log::info("Customer found: {$customer->name} (ID: {$customer->id})");

        // Check if user has access to this customer (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $hasAccess = $user->customers()->where('customers.id', $customer->id)->exists();
            if (!$hasAccess) {
                \Log::warning("User {$user->id} denied access to customer {$customerCode}");
                return makeResponse(403, 'Access denied to this customer', null);
            }
        }

        // Build query to get invoices (orders with type='INV') with payment calculations
        // Using receipt_orders table with order_refno field
        $query = Order::select([
                'orders.*',
                // Calculate total payments made for this invoice from receipt_orders table
                DB::raw('COALESCE((
                    SELECT SUM(receipt_orders.amount_applied)
                    FROM receipt_orders
                    INNER JOIN receipts ON receipt_orders.receipt_id = receipts.id
                    WHERE receipt_orders.order_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                    AND receipts.deleted_at IS NULL
                ), 0) as total_payments')
            ])
            ->where('orders.customer_code', $customerCode)
            ->where('orders.type', 'INV');

        // Only filter out fully paid invoices if include_paid is false
        if (!$includePaid) {
            // Outstanding definition: total_payments < net_amount
            // Show invoices where total payments < net_amount (allowing 0.01 tolerance for floating point)
            $query->whereRaw('COALESCE((
                SELECT SUM(receipt_orders.amount_applied)
                FROM receipt_orders
                INNER JOIN receipts ON receipt_orders.receipt_id = receipts.id
                WHERE receipt_orders.order_refno COLLATE utf8mb4_unicode_ci = orders.reference_no COLLATE utf8mb4_unicode_ci
                AND receipts.deleted_at IS NULL
            ), 0) < (COALESCE(orders.net_amount, orders.grand_amount, 0) - 0.01)');
        }

        $invoices = $query->with(['customer'])
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info("Found {$invoices->count()} invoices for customer {$customerCode}");

        // Calculate outstanding balance for each invoice and format response
        $formattedInvoices = $invoices->map(function ($invoice) {
            $totalPayments = (float) ($invoice->total_payments ?? 0);

            // Get invoice amounts
            $netAmount = (float) ($invoice->net_amount ?? 0);
            $grandAmount = (float) ($invoice->grand_amount ?? 0);
            $grossAmount = (float) ($invoice->gross_amount ?? 0);
            $tax1 = (float) ($invoice->tax1 ?? 0);

            // Deduct linked credit notes (CN orders) from invoice amounts
            $linkedCNs = Order::where('credit_invoice_no', $invoice->reference_no)
                ->where('type', 'CN')
                ->get();

            $cnTotal = 0.0;
            foreach ($linkedCNs as $cnOrder) {
                $cnTotal += (float) ($cnOrder->net_amount ?? 0);
            }

            // Adjust amounts by deducting linked CN totals
            $adjustedNetAmount = max(0, $netAmount - $cnTotal);
            $adjustedGrandAmount = max(0, $grandAmount - $cnTotal);
            $adjustedGrossAmount = max(0, $grossAmount - $cnTotal);

            // Outstanding balance = adjusted invoice amount - payments made
            $outstandingBalance = max(0, $adjustedNetAmount - $totalPayments);

            return [
                'id' => $invoice->id,
                'reference_no' => $invoice->reference_no,
                'type' => $invoice->type,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer_name ?? 'N/A',
                'customer_code' => $invoice->customer_code,
                'order_date' => $invoice->order_date ? $invoice->order_date->toDateString() : null,
                'net_amount' => $adjustedNetAmount, // Adjusted amount (deducted linked CN totals)
                'grand_amount' => $adjustedGrandAmount, // Adjusted amount (deducted linked CN totals)
                'gross_amount' => $adjustedGrossAmount, // Adjusted amount (deducted linked CN totals)
                'tax1' => $tax1,
                'remarks' => $invoice->remarks ?? '',
                'status' => $invoice->status ?? 'pending',
                'total_payments' => $totalPayments,
                'outstanding_balance' => $outstandingBalance,
                // items excluded for cleaner JSON and better performance
                // customer excluded as customer info is already in invoice (customer_name, customer_code)
            ];
        });

        // Calculate total outstanding balance
        $totalOutstanding = $formattedInvoices->sum('outstanding_balance');

        \Log::info("Total outstanding for customer {$customerCode}: {$totalOutstanding}");

        return makeResponse(200, 'Outstanding invoices retrieved successfully', [
            'invoices' => $formattedInvoices->values(),
            'total_outstanding' => $totalOutstanding,
            'customer_code' => $customerCode,
            'customer_name' => $customer->name,
            'invoice_count' => $formattedInvoices->count(),
        ]);
    }
}
