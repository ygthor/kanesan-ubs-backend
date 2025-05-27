<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $BranchId = $request->input('branchId');
        $products = Product::selectRaw('
            Id,
            Product_English_Name AS ProductName
        ')
            ->where('BranchId', $BranchId)
            ->get();
        return makeResponse(200, 'Products retrieved successfully.', $products);
    }
}
