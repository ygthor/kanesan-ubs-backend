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
        // Basic index, can be expanded with pagination, filtering by customer, date range etc.
        $orders = Order::with('items', 'customer') // Eager load items and customer
                       ->orderBy('order_date', 'desc')
                       ->paginate($request->input('per_page', 15));

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
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id', // Ensure customer exists
            'customer_name' => 'required|string|max:255', // Can be fetched via customer_id too
            'order_date' => 'nullable|date_format:Y-m-d H:i:s',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product,id', // Ensure product exists
            'items.*.product_name' => 'required|string|max:255', // From Flutter OrderItem
            'items.*.sku_code' => 'required|string|max:255',     // From Flutter OrderItem
            'items.*.quantity' => 'required|numeric|min:0', // Allow 0 for free goods if that's a case
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.is_free_good' => 'sometimes|boolean',
            'items.*.is_trade_return' => 'sometimes|boolean',
            'items.*.trade_return_is_good' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        DB::beginTransaction();
        try {
            $orderData = $request->only(['customer_id', 'customer_name', 'order_date', 'remarks']);
            if (empty($orderData['order_date'])) {
                $orderData['order_date'] = now();
            }

            $order = Order::create($orderData);

            $totalOrderAmount = 0;

            foreach ($request->input('items') as $itemData) {
                $product = Product::find($itemData['product_id']); // Fetch product for current price if needed
                if (!$product) {
                    DB::rollBack(); // Should be caught by validator, but as a safeguard
                    return makeResponse(400, 'Invalid product ID: ' . $itemData['product_id'] . ' found in items.');
                }

                $unitPrice = $itemData['is_free_good'] ? 0 : ($itemData['unit_price'] ?? $product->price);
                $quantity = $itemData['quantity'];
                $discount = $itemData['discount'] ?? 0;

                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $itemData['product_name'], // Using name from request (as per Flutter)
                    'sku_code' => $itemData['sku_code'],         // Using SKU from request
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'is_free_good' => $itemData['is_free_good'] ?? false,
                    'is_trade_return' => $itemData['is_trade_return'] ?? false,
                    'trade_return_is_good' => $itemData['is_trade_return'] ? ($itemData['trade_return_is_good'] ?? true) : true,
                ]);
                $totalOrderAmount += $orderItem->getSubtotalAttribute(); // Use accessor
            }

            $order->total_amount = $totalOrderAmount;
            $order->save();

            DB::commit();

            return makeResponse(201, 'Order created successfully.', $order->load('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Order creation failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to create order.', ['error' => $e->getMessage()]);
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
        // Load items and customer relationship for detailed view
        $order->load('items.product', 'customer');
        return makeResponse(200, 'Order retrieved successfully.', $order);
    }

    // You might add update and delete methods for orders later if needed
    // public function update(Request $request, Order $order) { ... }
    // public function destroy(Order $order) { ... }
}
