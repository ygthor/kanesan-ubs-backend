<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleManagementController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index()
    {
        $roles = Role::with(['permissions', 'users'])->paginate(15);

        return response()->json([
            'status' => 200,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->attach($request->permissions);
        }

        $role->load(['permissions']);

        return response()->json([
            'status' => 201,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return response()->json([
            'status' => 200,
            'message' => 'Role retrieved successfully',
            'data' => $role
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'is_active' => $request->get('is_active', $role->is_active),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        $role->load(['permissions']);

        return response()->json([
            'status' => 200,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'status' => 400,
                'message' => 'Cannot delete role that has assigned users'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get all permissions for role assignment.
     */
    public function getPermissions()
    {
        $permissions = Permission::where('is_active', true)
            ->orderBy('module')
            ->orderBy('display_name')
            ->get()
            ->groupBy('module');

        return response()->json([
            'status' => 200,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions
        ]);
    }

    /**
     * Get role statistics.
     */
    public function getStats()
    {
        $stats = [
            'total_roles' => Role::count(),
            'active_roles' => Role::where('is_active', true)->count(),
            'roles_with_users' => Role::whereHas('users')->count(),
            'roles_with_permissions' => Role::whereHas('permissions')->count(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Role statistics retrieved successfully',
            'data' => $stats
        ]);
    }
}
