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
    public function get_token(Request $request)
    {
        // Validate the request data
        // Now accepts 'identifier' which can be an email or username
        $request->validate([
            'identifier' => 'required|string', // Can be email or username
            'password' => 'required|string',
        ]);

        // Retrieve the identifier and password from the request
        $identifier = $request->input('identifier');
        $password = $request->input('password');

        // Attempt to find the user by email or username
        // This assumes you have a 'username' column in your 'users' table
        // If your username column has a different name, adjust it accordingly (e.g., 'user_name', 'login_id')
        $user = User::where('email', $identifier)
                    ->orWhere('username', $identifier) // Add this line to check for username
                    ->first();

        // Check if a user was found and if the password is correct
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Revoke all old tokens for the user to ensure only one active token (optional, but good practice)
        // $user->tokens()->delete();

        // Create a new Sanctum token for the user
        $token = $user->createToken('auth-token')->plainTextToken;

        return makeResponse(200,'login success',[
            'token' => $token,
            'user' => [ // Optionally, return some user details
                'id' => $user->id,
                'name' => $user->name, // Assuming you have a 'name' field
                'email' => $user->email,
                'username' => $user->username, // Assuming you have a 'username' field
            ]
        ]);
    }
}
