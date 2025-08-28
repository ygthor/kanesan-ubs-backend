<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_helper_function()
    {
        // Create a user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a role
        $role = Role::create([
            'name' => 'test_role',
            'display_name' => 'Test Role',
            'description' => 'A test role',
        ]);

        // Create a permission
        $permission = Permission::create([
            'name' => 'test_permission',
            'display_name' => 'Test Permission',
            'description' => 'A test permission',
            'module' => 'Test',
        ]);

        // Assign permission to role
        $role->permissions()->attach($permission);

        // Assign role to user
        $user->roles()->attach($role);

        // Test the can() helper function
        $this->actingAs($user);
        $this->assertTrue(can('test_permission'));
        $this->assertFalse(can('non_existent_permission'));
    }

    public function test_has_role_helper_function()
    {
        // Create a user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a role
        $role = Role::create([
            'name' => 'test_role',
            'display_name' => 'Test Role',
            'description' => 'A test role',
        ]);

        // Assign role to user
        $user->roles()->attach($role);

        // Test the hasRole() helper function
        $this->actingAs($user);
        $this->assertTrue(hasRole('test_role'));
        $this->assertFalse(hasRole('non_existent_role'));
    }

    public function test_user_can_have_multiple_roles()
    {
        // Create a user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create multiple roles
        $role1 = Role::create([
            'name' => 'role1',
            'display_name' => 'Role 1',
            'description' => 'First role',
        ]);

        $role2 = Role::create([
            'name' => 'role2',
            'display_name' => 'Role 2',
            'description' => 'Second role',
        ]);

        // Assign both roles to user
        $user->roles()->attach([$role1->id, $role2->id]);

        // Test that user has both roles
        $this->actingAs($user);
        $this->assertTrue(hasRole('role1'));
        $this->assertTrue(hasRole('role2'));
        $this->assertTrue(hasAnyRole(['role1', 'role2']));
        $this->assertTrue(hasAllRoles(['role1', 'role2']));
    }
}
