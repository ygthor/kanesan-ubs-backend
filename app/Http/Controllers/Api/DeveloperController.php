<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeveloperController extends Controller
{
    

    /**
     * @unauthenticated
     * @group Developer
     *
     * Create a new user
     *
     * This endpoint allows you to create a new user with their name, email, and password.
     *
     * @bodyParam name string required The name of the user. Example: John Doe
     * @bodyParam email string required The email address of the user. Example: john@example.com
     * @bodyParam password string required The password of the user. Example: secret123
     *
     * @response 201 {
     *   "message": "User created successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "created_at": "2025-04-02T12:34:56",
     *     "updated_at": "2025-04-02T12:34:56"
     *   }
     * }
     * @response 400 {
     *   "message": "Validation errors",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     */
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }
}
