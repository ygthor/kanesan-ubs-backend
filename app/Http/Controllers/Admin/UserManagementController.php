<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use DB;
class UserManagementController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::with(['roles'])->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $roles = Role::all();
        $branches = DB::table('branches')
            ->select('branch_id', 'branch_name')
            ->orderBy('branch_name')
            ->get();
        return view('admin.users.create', compact('roles', 'branches'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,role_id',
            'branches' => 'array',
            'branches.*' => 'exists:branches,branch_id'
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->has('roles')) {
            $user->roles()->attach($request->roles);
        }

        if ($request->has('branches')) {
            $branches = $request->branches; // e.g. ['Ipoh', 'Penang'] ids
            foreach ($branches as $branchId) {
                DB::table('users_branches')->insert([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                ]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully!');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['roles.permissions']);
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $user->load(['roles']);
        $branches = DB::table('branches')
            ->select('branch_id', 'branch_name')
            ->orderBy('branch_name')
            ->get();
        $userBranchIds = DB::table('users_branches')
            ->where('user_id', $user->id)
            ->pluck('branch_id')
            ->toArray();
        return view('admin.users.edit', compact('user', 'roles', 'branches', 'userBranchIds'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,role_id',
            'branches' => 'array',
            'branches.*' => 'exists:branches,branch_id'
        ]);

        $user->update([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        if ($request->has('roles')) {
            $roles = $request->roles; // e.g. ['admin', 'user', 'editor']

            // Delete existing roles
            DB::table('user_roles')->where('user_id', $user->id)->delete();

            // Insert new roles
            
            foreach ($roles as $roleId) {
                $insertData = [
                    'user_id' => $user->id,
                    'role_id' => $roleId, // string OK
                ];
                
                DB::table('user_roles')->insert($insertData);
            }
            
        }

        if ($request->has('branches')) {
            $branches = $request->branches; // array of branch_ids (varchar)
            // Delete existing branches
            DB::table('users_branches')->where('user_id', $user->id)->delete();
            // Insert new branches
            foreach ($branches as $branchId) {
                DB::table('users_branches')->insert([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                ]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Prevent deleting the current user
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully!');
    }
}
