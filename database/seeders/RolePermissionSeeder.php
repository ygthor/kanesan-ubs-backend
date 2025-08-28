<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Dashboard permissions
            ['name' => 'access_dashboard', 'display_name' => 'Access Dashboard', 'module' => 'Dashboard'],
            
            // User management permissions
            ['name' => 'access_user_mgmt', 'display_name' => 'Access User Management', 'module' => 'User Management'],
            ['name' => 'create_user', 'display_name' => 'Create User', 'module' => 'User Management'],
            ['name' => 'edit_user', 'display_name' => 'Edit User', 'module' => 'User Management'],
            ['name' => 'delete_user', 'display_name' => 'Delete User', 'module' => 'User Management'],
            ['name' => 'view_user', 'display_name' => 'View User', 'module' => 'User Management'],
            
            // Role management permissions
            ['name' => 'access_role_mgmt', 'display_name' => 'Access Role Management', 'module' => 'Role Management'],
            ['name' => 'create_role', 'display_name' => 'Create Role', 'module' => 'Role Management'],
            ['name' => 'edit_role', 'display_name' => 'Edit Role', 'module' => 'Role Management'],
            ['name' => 'delete_role', 'display_name' => 'Delete Role', 'module' => 'Role Management'],
            ['name' => 'view_role', 'display_name' => 'View Role', 'module' => 'Role Management'],
            
            // Permission management permissions
            ['name' => 'access_permission_mgmt', 'display_name' => 'Access Permission Management', 'module' => 'Permission Management'],
            ['name' => 'create_permission', 'display_name' => 'Create Permission', 'module' => 'Permission Management'],
            ['name' => 'edit_permission', 'display_name' => 'Edit Permission', 'module' => 'Permission Management'],
            ['name' => 'delete_permission', 'display_name' => 'Delete Permission', 'module' => 'Permission Management'],
            ['name' => 'view_permission', 'display_name' => 'View Permission', 'module' => 'Permission Management'],
            
            // Customer management permissions
            ['name' => 'access_customer_mgmt', 'display_name' => 'Access Customer Management', 'module' => 'Customer Management'],
            ['name' => 'create_customer', 'display_name' => 'Create Customer', 'module' => 'Customer Management'],
            ['name' => 'edit_customer', 'display_name' => 'Edit Customer', 'module' => 'Customer Management'],
            ['name' => 'delete_customer', 'display_name' => 'Delete Customer', 'module' => 'Customer Management'],
            ['name' => 'view_customer', 'display_name' => 'View Customer', 'module' => 'Customer Management'],
            
            // Order management permissions
            ['name' => 'access_order_mgmt', 'display_name' => 'Access Order Management', 'module' => 'Order Management'],
            ['name' => 'create_order', 'display_name' => 'Create Order', 'module' => 'Order Management'],
            ['name' => 'edit_order', 'display_name' => 'Edit Order', 'module' => 'Order Management'],
            ['name' => 'delete_order', 'display_name' => 'Delete Order', 'module' => 'Order Management'],
            ['name' => 'view_order', 'display_name' => 'View Order', 'module' => 'Order Management'],
            
            // Product management permissions
            ['name' => 'access_product_mgmt', 'display_name' => 'Access Product Management', 'module' => 'Product Management'],
            ['name' => 'create_product', 'display_name' => 'Create Product', 'module' => 'Product Management'],
            ['name' => 'edit_product', 'display_name' => 'Edit Product', 'module' => 'Product Management'],
            ['name' => 'delete_product', 'display_name' => 'Delete Product', 'module' => 'Product Management'],
            ['name' => 'view_product', 'display_name' => 'View Product', 'module' => 'Product Management'],
            
            // Report permissions
            ['name' => 'access_reports', 'display_name' => 'Access Reports', 'module' => 'Reports'],
            ['name' => 'view_sales_report', 'display_name' => 'View Sales Report', 'module' => 'Reports'],
            ['name' => 'view_inventory_report', 'display_name' => 'View Inventory Report', 'module' => 'Reports'],
            ['name' => 'export_reports', 'display_name' => 'Export Reports', 'module' => 'Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Create roles
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'permissions' => Permission::pluck('id')->toArray()
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System administrator with most permissions',
                'permissions' => Permission::whereNotIn('name', [
                    'access_permission_mgmt',
                    'create_permission',
                    'edit_permission',
                    'delete_permission'
                ])->pluck('id')->toArray()
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Department manager with limited administrative access',
                'permissions' => Permission::whereIn('name', [
                    'access_dashboard',
                    'access_customer_mgmt',
                    'view_customer',
                    'edit_customer',
                    'access_order_mgmt',
                    'view_order',
                    'edit_order',
                    'access_product_mgmt',
                    'view_product',
                    'edit_product',
                    'access_reports',
                    'view_sales_report',
                    'view_inventory_report'
                ])->pluck('id')->toArray()
            ],
            [
                'name' => 'user',
                'display_name' => 'Regular User',
                'description' => 'Standard user with basic access',
                'permissions' => Permission::whereIn('name', [
                    'access_dashboard',
                    'access_customer_mgmt',
                    'view_customer',
                    'access_order_mgmt',
                    'view_order',
                    'access_product_mgmt',
                    'view_product'
                ])->pluck('id')->toArray()
            ]
        ];

        foreach ($roles as $roleData) {
            $permissionIds = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::create($roleData);
            $role->permissions()->attach($permissionIds);
        }

        // Create a default super admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdmin->roles()->attach($superAdminRole->id);
    }
}
