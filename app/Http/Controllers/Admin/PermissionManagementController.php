<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class PermissionManagementController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index()
    {
        $permissions = Permission::with(['roles'])
            ->orderBy('module')
            ->orderBy('display_name')
            ->paginate(15);

        return response()->json([
            'status' => 200,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'required|string|max:255'
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'module' => $request->module,
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission)
    {
        $permission->load(['roles']);

        return response()->json([
            'status' => 200,
            'message' => 'Permission retrieved successfully',
            'data' => $permission
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $permission->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'module' => $request->module,
            'is_active' => $request->get('is_active', $permission->is_active),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission)
    {
        // Check if permission is assigned to any roles
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'status' => 400,
                'message' => 'Cannot delete permission that is assigned to roles'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * Get all modules for permission organization.
     */
    public function getModules()
    {
        $modules = Permission::distinct()
            ->whereNotNull('module')
            ->pluck('module')
            ->sort()
            ->values();

        return response()->json([
            'status' => 200,
            'message' => 'Modules retrieved successfully',
            'data' => $modules
        ]);
    }

    /**
     * Get permissions by module.
     */
    public function getByModule($module)
    {
        $permissions = Permission::where('module', $module)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions
        ]);
    }

    /**
     * Get permission statistics.
     */
    public function getStats()
    {
        $stats = [
            'total_permissions' => Permission::count(),
            'active_permissions' => Permission::where('is_active', true)->count(),
            'permissions_with_roles' => Permission::whereHas('roles')->count(),
            'modules_count' => Permission::distinct('module')->count(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Permission statistics retrieved successfully',
            'data' => $stats
        ]);
    }
}
