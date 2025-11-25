<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer; // To fetch customer details if needed
use App\Models\Product;  // To fetch product details from products table
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderItemController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Basic index, can be expanded with pagination, filtering by customer, date range etc.
        $orders = OrderItem // Eager load items and customer
            ::orderBy('order_date', 'desc')
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
     * @bodyParam items.*.product_no string required The code of the product from products table. Example: "A100"
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
        return $this->saveOrderItem($request);
    }

    public function update(Request $request, $id)
    {
        return $this->saveOrderItem($request, $id);
    }


    public function saveOrderItem(Request $request, $id = null)
    {
        $validator = Validator::make($request->all(), [
            'product_no' => 'required|string|exists:products,code', // Validate product exists in products table
            'quantity' => 'required|min:1',
            // 'items' => 'required|array|min:1',
            // 'items.*.product_no' => 'required|exists:products,code',
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
                'order_id',
                'product_no', // Changed from product_id
                'quantity',
                'unit_price',
                'item_group',
                'is_free_good',
                'is_trade_return',
                'trade_return_is_good',
            ]);

            $order_id = $orderData['order_id'];
            $order = Order::find($order_id);

            $item_count = DB::table('order_items')->where('order_id', $order_id)->count() + 1;

            $orderData['reference_no'] = $order->reference_no;
            $orderData['order_id'] = $order_id;
            $orderData['item_count'] = $item_count;
            $orderData['unique_key'] = $order->reference_no . '|' . $item_count;


            $orderData['order_date'] = $orderData['order_date'] ?? now();
            $orderData['branch_id'] = $orderData['branch_id'] ?? 0;
            $orderData['type'] = $orderData['type'] ?? 'SO';

            // Use Product from products table
            $product = Product::where('code', $orderData['product_no'])->first();
            if (!$product) {
                DB::rollBack();
                return makeResponse(404, 'Product not found with code: ' . $orderData['product_no']);
            }
            
            $orderData['product_no'] = $product->code;
            $orderData['product_name'] = $product->description;
            $orderData['sku_code'] = $product->code; // Use code as SKU
            $orderData['description'] = $product->description;

            // Handle free goods
            if (isset($orderData['is_free_good']) && $orderData['is_free_good']) {
                $orderData['unit_price'] = 0;
            }

            $orderData['amount'] = $orderData['quantity'] * $orderData['unit_price'];
            $orderData['updated_at'] = timestamp();



            if ($id) {
                // ✅ Update mode
                $orderItem = OrderItem::findOrFail($id);
                $orderItem->fill($orderData)->save();
            } else {
                // ✅ Create mode
                $orderItem = OrderItem::create($orderData);
                $orderItem->save();
            }

            $order->calculate();
            $order->save();

            DB::commit();

            return makeResponse($id ? 200 : 201, $id ? 'Order item updated successfully.' : 'Order item created successfully.');
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
        // Load items and customer relationship for detailed view
        // Note: items now reference products table via product_no (code)
        $order->load('items', 'customer');
        return makeResponse(200, 'Order item retrieved successfully.', $order);
    }



    public function destroy($itemId)
    {
        try {
            DB::beginTransaction();

            $item = OrderItem::findOrFail($itemId);
            $order = $item->order;

            $item->delete();

            // Optional: recalculate order total
            $order->calculate();
            $order->save();

            DB::commit();
            return makeResponse(200, 'Order item deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete order item: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete order item.', ['error' => $e->getMessage()]);
        }
    }


    
}
