<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SyncController extends Controller
{
    
    public function syncLocalData(Request $request)
    {
        $directory = $request->input('directory');
        $filename = $request->input('filename');
        $data = $request->input('data');

        return response()->json([
            'message' => "Received $directory / $filename successfully",
            // 'data' => $data
        ], 201);
    }
}
