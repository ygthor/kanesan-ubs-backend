<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer; // To fetch customer details if needed
use App\Models\Product;  // To fetch product details if needed
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
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No orders accessible.', $paginate ? ['data' => [], 'total' => 0] : []);
            }
            $orders->whereIn('customer_id', $allowedCustomerIds);
        }

        // --- Apply filters conditionally ---

        // Filter by customer name
        if ($customerName) {
            $orders->whereHas('customer', function ($query) use ($customerName) {
                $query->where('name', 'like', "%{$customerName}%");
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
        if ($orderType) {
            // The Flutter app sends a comma-separated string, so we need to split it
            $types = explode(',', $orderType);
            
            // Special handling for Trade Return ('TR')
            // Trade returns are orders that have items with is_trade_return = 1
            if (in_array('TR', $types)) {
                // Remove 'TR' from the types array
                $types = array_filter($types, function($type) {
                    return $type !== 'TR';
                });
                
                // Filter orders that have at least one trade return item
                $orders->whereHas('items', function ($query) {
                    $query->where('is_trade_return', 1);
                });
                
                // If there are other types besides 'TR', also filter by those types
                if (!empty($types)) {
                    $orders->whereIn('type', $types);
                }
            } else {
                // Normal type filtering for non-TR types
                $orders->whereIn('type', $types);
            }
        }

        // Order the results
        $orders->orderBy('order_date', 'desc');
        $orders->orderBy('id', 'desc');

        // Paginate the results
        if($paginate){
            $orders = $orders->paginate($request->input('per_page', 15));
        }else{
            $orders = $orders->get();
        }

        return makeResponse(200, 'Orders retrieved successfully.', $orders);
    }

    /**
     * Store a newly created order in storage.
     *
     * @bodyParam customer_id string required The ID of the customer. Example: "1"
     * @bodyParam customer_name string required The name of the customer. Example: "AHS3185 S02 KANESAN"
     * @bodyParam order_date string nullable The date of the order (YYYY-MM-DD HH:MM:SS). Defaults to now. Example: "2025-05-20 10:00:00"
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
            // 'order_date' => 'nullable|date',
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
                'type',
                'branch_id',
                'customer_id',
                'order_date',
                'remarks',
                'tax1_percentage',
                'discount'
            ]);

            $orderData['order_date'] = $orderData['order_date'] ?? now();
            $orderData['branch_id'] = $orderData['branch_id'] ?? 0;
            $orderData['type'] = $orderData['type'] ?? 'SO';

            $customer = Customer::find($orderData['customer_id']);
            if (!$customer) {
                DB::rollBack();
                return makeResponse(404, 'Customer not found with ID: ' . $orderData['customer_id']);
            }
            
            $orderData['customer_code'] = $customer->customer_code;
            // Always use company_name for customer_name (name field was removed from customer form)
            $orderData['customer_name'] = $customer->company_name ?? 'N/A';

            $orderData['status'] = 'pending';

            if ($id) {
                // ✅ Update mode
                $order = Order::with('items.item')->findOrFail($id);
                $order->fill($orderData)->save();
            } else {
                // ✅ Create mode
                $order = Order::create($orderData);
                $order->reference_no = $order->getReferenceNo();
                $order->save();
            }

            $reference_no = $order->reference_no;
            $item_count = 1;

            $items = $request->input('items') ?? [];
            foreach ($items as $itemData) {
                $product = Product::find($itemData['product_id']);
                if (!$product) {
                    DB::rollBack();
                    return makeResponse(400, 'Invalid product ID: ' . $itemData['product_id']);
                }

                $unitPrice = $itemData['is_free_good'] ? 0 : ($itemData['unit_price'] ?? $product->price);
                $quantity = $itemData['quantity'];
                $discount = $itemData['discount'] ?? 0;
                $unique_key = "$reference_no|$item_count";

                $orderItem = $order->items()->create([
                    'unique_key' => $unique_key,
                    'reference_no' => $reference_no,
                    'item_count' => $item_count,
                    'product_id' => $product->id,
                    'product_no' => $product->product_no,
                    'product_name' => $product->product_name,
                    'description' => $product->product_name,
                    'sku_code' => $product->sku_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'is_free_good' => $itemData['is_free_good'] ?? false,
                    'is_trade_return' => $itemData['is_trade_return'] ?? false,
                    'trade_return_is_good' => $itemData['is_trade_return'] ? ($itemData['trade_return_is_good'] ?? true) : true,
                    'item_group' => $itemData['item_group'] ?? null,
                ]);
                $orderItem->calculate();
                $orderItem->save();
                $item_count++;
            }

            $order->calculate();
            $order->save();

            DB::commit();

            return makeResponse($id ? 200 : 201, $id ? 'Order updated successfully.' : 'Order created successfully.', $order->load('items.item'));
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
    public function show(Order $order)
    {
        $user = auth()->user();
        
        // Check if user has access to this order's customer
        if (!$this->userHasAccessToCustomer($user, $order->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this order.', null);
        }
        
        // Load items and customer relationship for detailed view
        $order->load('items.product', 'items.item', 'customer');
        return makeResponse(200, 'Order retrieved successfully.', $order);
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
}
