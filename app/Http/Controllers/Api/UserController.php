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
        
        // Add admin status to user data
        $userData = $user->toArray();
        $userData['is_admin'] = ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my');
        
        return makeResponse(200,'success',$userData);
    }

    /**
     * Get a list of users for dropdown selection
     */
    public function list(Request $request)
    {
        try {
            $users = User::select('id', 'name', 'username', 'email')
                ->orderBy('name', 'asc')
                ->get();
            
            return makeResponse(200, 'Users retrieved successfully.', $users);
        } catch (\Exception $e) {
            return makeResponse(500, 'Error retrieving users: ' . $e->getMessage(), []);
        }
    }
}
