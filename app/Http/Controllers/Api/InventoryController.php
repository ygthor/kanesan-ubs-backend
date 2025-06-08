<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Retrieve a list of inventory items based on filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'groupId' => 'nullable|string',
            'subGroupId' => 'nullable|string',
            'inventoryType' => 'nullable|string',
        ]);

        $query = Product::query();

        // Apply filters if they are provided
        // if ($request->has('groupId')) {
        //     $query->where('group_id_text', $request->input('groupId'));
        // }

        // if ($request->has('subGroupId')) {
        //     $query->where('sub_group_id_text', $request->input('subGroupId'));
        // }

        // if ($request->has('inventoryType')) {
        //     $query->where('inventory_type', $request->input('inventoryType'));
        // }

        $query->orderBy('Product_Id');
        $query->limit('300');
        $inventoryItems = $query->get();

        // Transform the data to match the Flutter InventoryItem model structure
        $formattedData = $inventoryItems->map(function ($product) {
            return [
                'skuCode' => $product->Product_Id,
                'productName' => $product->Product_English_Name,
                'quantity' => (float) $product->CurrentStock,
                'groupId' => $product->group_id_text ?? '',
                'subGroupId' => $product->sub_group_id_text ?? '',
                'inventoryType' => $product->inventory_type ?? '',
            ];
        });

        return makeResponse(200, 'Inventory retrieved successfully.', $formattedData);
    }
}
