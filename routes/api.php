<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * @unauthenticated
 * @bodyParam email string required The user's email. Example: sampleuser@example.com
 * @bodyParam password string required The user's password. Example: password123
 * @response 200 {
 *   "token": 1|xxx,
 * }
 * responseFile storage/responses/auth.token.scr
 */
Route::post('/auth/token', function (Request $request) {
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
});


Route::middleware(['auth:sanctum'])->group(function () {

    
    Route::get('/user', function (Request $request) {
        $user = $request->user();  // This assumes authentication is handled via Sanctum or JWT
        return response()->json($user);
    });
});
