<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Icitem;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IcitemController extends Controller
{
    public function index(Request $request)
    {
        $BranchId = $request->input('branchId');
        $products = Icitem::selectRaw('
            ITEMNO,
            DESP
        ')
            // ->where('BranchId', $BranchId)
            ->get();
        return makeResponse(200, 'Icitem retrieved successfully.', $products);
    }
}
