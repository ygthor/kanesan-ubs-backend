<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Icgroup;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get all products (optionally filtered by group_name)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Product::active(); // Only get active products
            
            // Optional filter by product group
            if ($request->has('group_name') && $request->input('group_name')) {
                $query->byGroup($request->input('group_name'));
            }
            
            // Select only the fields needed by the mobile app
            $products = $query->select([
                'id',
                'code',
                'description',
                'group_name',
                'is_active',
            ])
            ->orderBy('group_name')
            ->orderBy('code')
            ->get();
            
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
            // Try to get from Icgroup table first, fallback to Product group_name if needed
            try {
                $groups = Icgroup::select('name')
                    ->orderBy('name')
                    ->pluck('name')
                    ->filter()
                    ->values();
            } catch (\Exception $e) {
                // Fallback to getting from Product table if Icgroup table doesn't exist yet
                $groups = Product::active()
                    ->select('group_name')
                    ->distinct()
                    ->orderBy('group_name')
                    ->pluck('group_name')
                    ->filter()
                    ->values();
            }
            
            return makeResponse(200, 'Product groups retrieved successfully.', $groups);
        } catch (\Exception $e) {
            return makeResponse(500, 'Error retrieving product groups: ' . $e->getMessage(), null);
        }
    }
}
