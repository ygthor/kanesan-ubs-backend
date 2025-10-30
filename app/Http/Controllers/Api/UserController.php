<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

    /**
     * Get authenticated user's assigned branches
     */
    public function branches(Request $request)
    {
        $user = $request->user();
        $rows = DB::table('users_branches as ub')
            ->join('branches as b', 'b.branch_id', '=', 'ub.branch_id')
            ->where('ub.user_id', $user->id)
            ->select('b.branch_id', 'b.branch_name')
            ->orderBy('b.branch_name')
            ->get();

        $branches = $rows->map(function ($r) {
            return [
                'branch_id' => $r->branch_id,
                'branch_name' => $r->branch_name,
            ];
        });

        return makeResponse(200, 'Branches retrieved successfully.', $branches);
    }
}
