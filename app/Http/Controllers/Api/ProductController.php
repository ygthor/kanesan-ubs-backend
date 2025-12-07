<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Icitem;
use App\Models\Icgroup;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get all products (optionally filtered by group_name)
     * Uses Icitem model (legacy UBS icitem table)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Icitem::query();
            
            // Optional filter by product group
            if ($request->has('group_name') && $request->input('group_name')) {
                $query->where('GROUP', $request->input('group_name'));
            }
            
            // Select fields from icitem table and map to expected format
            $items = $query->select([
                'ITEMNO',
                'DESP',
                'GROUP',
                'UCOST',  // Unit cost - can be changed to PRICE if needed
                'PRICE',  // Price field - alternative to UCOST
            ])
            ->orderBy('GROUP')
            ->orderBy('ITEMNO')
            ->get();
            
            // Transform to match mobile app expected format
            $products = $items->map(function ($item) {
                // Use UCOST if available, fallback to PRICE, default to 0
                $unitPrice = $item->UCOST ?? $item->PRICE ?? 0;
                return [
                    'id' => $item->ITEMNO, // Use ITEMNO as id
                    'code' => $item->ITEMNO,
                    'description' => $item->DESP ?? '',
                    'group_name' => $item->GROUP ?? '',
                    'unit_price' => (float)$unitPrice, // Unit price from UCOST or PRICE
                    'is_active' => true, // Assume all items are active (icitem table doesn't have is_active)
                ];
            })->values()->toArray(); // Convert Collection to array for proper JSON serialization
            
            return makeResponse(200, 'Products retrieved successfully.', $products);
        } catch (\Exception $e) {
            return makeResponse(500, 'Error retrieving products: ' . $e->getMessage(), null);
        }
    }
    
    /**
     * Get all product groups
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function groups(Request $request)
    {
        try {
            // Get groups from icgroup table
            $groups = Icgroup::select('name')
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->values();
            
            return makeResponse(200, 'Product groups retrieved successfully.', $groups);
        } catch (\Exception $e) {
            return makeResponse(500, 'Error retrieving product groups: ' . $e->getMessage(), null);
        }
    }
}
