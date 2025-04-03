<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{


    public function info(Request $request){
        $user = $request->user();  // This assumes authentication is handled via Sanctum or JWT
        return response()->json($user);
    }
}
