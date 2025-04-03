<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * @unauthenticated
     * @bodyParam email string required The user's email. Example: sampleuser@example.com
     * @bodyParam password string required The user's password. Example: password123
     * @response 200 {
     *   "token": 1|xxx,
     * }
     * responseFile storage/responses/auth.token.scr
     */
    public function get_token(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
    
        // Create a Sanctum token for the user
        $token = $user->createToken('auth-token')->plainTextToken;
    
        return response()->json(['token' => $token]);
    }
}
