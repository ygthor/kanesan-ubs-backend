# Authentication & Authorization System

This Laravel project now includes a comprehensive role-based access control (RBAC) system with user management, role management, and permission management.

## Features

### 🔐 Authentication
- User login/logout with Laravel Sanctum tokens
- Secure password hashing
- Session management

### 👥 User Management
- Create, read, update, delete users
- Assign multiple roles to users
- User profile management

### 🎭 Role Management
- Create, read, update, delete roles
- Assign permissions to roles
- Role-based access control

### 🔑 Permission Management
- Granular permission system
- Module-based organization
- Permission assignment to roles

### 🛡️ Security
- Middleware-based permission checking
- Helper functions for easy permission checks
- CSRF protection

## Database Structure

### Tables Created
- `roles` - Stores role information
- `permissions` - Stores permission information
- `user_roles` - Many-to-many relationship between users and roles
- `role_permissions` - Many-to-many relationship between roles and permissions

### Default Data
The system comes with pre-configured:
- **Super Admin** role with all permissions
- **Admin** role with most permissions
- **Manager** role with limited administrative access
- **Regular User** role with basic access
- Default super admin user: `admin@example.com` / `password123`

## Usage

### Helper Functions

The system provides several helper functions for checking permissions and roles:

```php
// Check if user has a specific permission
if (can('access_dashboard')) {
    // User can access dashboard
}

// Check if user has a specific role
if (hasRole('admin')) {
    // User is an admin
}

// Check if user has any of the given roles
if (hasAnyRole(['admin', 'manager'])) {
    // User is either admin or manager
}

// Check if user has all of the given roles
if (hasAllRoles(['admin', 'manager'])) {
    // User has both admin and manager roles
}

// Check if user has any of the given permissions
if (hasAnyPermission(['create_user', 'edit_user'])) {
    // User can create or edit users
}
```

### In Controllers

```php
public function index()
{
    // Check permission using middleware
    if (!auth()->user()->hasPermission('access_user_mgmt')) {
        abort(403, 'Access denied');
    }
    
    // Or use helper function
    if (!can('access_user_mgmt')) {
        abort(403, 'Access denied');
    }
    
    // Continue with the logic
}
```

### In Blade Views

```php
@if(auth()->user()->hasPermission('create_user'))
    <button>Create User</button>
@endif

@if(hasRole('admin'))
    <div>Admin Panel</div>
@endif
```

### In Routes

```php
Route::middleware(['auth:sanctum', 'permission:access_user_mgmt'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout (requires auth)
- `GET /api/auth/me` - Get current user info (requires auth)

### User Management
- `GET /api/admin/users` - List users (requires `access_user_mgmt` permission)
- `POST /api/admin/users` - Create user (requires `create_user` permission)
- `GET /api/admin/users/{user}` - Show user (requires `view_user` permission)
- `PUT /api/admin/users/{user}` - Update user (requires `edit_user` permission)
- `DELETE /api/admin/users/{user}` - Delete user (requires `delete_user` permission)

### Role Management
- `GET /api/admin/roles` - List roles (requires `access_role_mgmt` permission)
- `POST /api/admin/roles` - Create role (requires `create_role` permission)
- `GET /api/admin/roles/{role}` - Show role (requires `view_role` permission)
- `PUT /api/admin/roles/{role}` - Update role (requires `edit_role` permission)
- `DELETE /api/admin/roles/{role}` - Delete role (requires `delete_role` permission)

### Permission Management
- `GET /api/admin/permissions` - List permissions (requires `access_permission_mgmt` permission)
- `POST /api/admin/permissions` - Create permission (requires `create_permission` permission)
- `GET /api/admin/permissions/{permission}` - Show permission (requires `view_permission` permission)
- `PUT /api/admin/permissions/{permission}` - Update permission (requires `edit_permission` permission)
- `DELETE /api/admin/permissions/{permission}` - Delete permission (requires `delete_permission` permission)

## Frontend Pages

### Login Page
- Location: `/public/login.html`
- Features: Modern design, form validation, error handling

### Dashboard
- Location: `/resources/views/dashboard.blade.php`
- Route: `/dashboard` (requires authentication)
- Features: User info, role display, permission overview, admin links

## Testing

Run the permission tests to verify the system works correctly:

```bash
php artisan test tests/Unit/PermissionTest.php
```

## Security Considerations

1. **Always validate permissions** before allowing access to sensitive operations
2. **Use middleware** for route-level protection
3. **Check permissions** in controllers and views
4. **Regularly audit** user roles and permissions
5. **Use HTTPS** in production
6. **Implement rate limiting** for login attempts

## Customization

### Adding New Permissions

1. Add permission to the `RolePermissionSeeder`
2. Run `php artisan db:seed --class=RolePermissionSeeder`
3. Assign permission to appropriate roles

### Adding New Roles

1. Create role using the API or seeder
2. Assign appropriate permissions
3. Assign roles to users as needed

### Custom Permission Checks

You can create custom permission checking logic in your models or controllers:

```php
// In User model
public function canManageUsers()
{
    return $this->hasPermission('access_user_mgmt');
}

// In controller
if (!auth()->user()->canManageUsers()) {
    abort(403);
}
```

## Troubleshooting

### Common Issues

1. **Permission denied errors**: Check if user has the required permission
2. **Role not working**: Verify role is assigned to user and has permissions
3. **Middleware not working**: Ensure middleware is registered in `bootstrap/app.php`

### Debug Commands

```bash
# Check user permissions
php artisan tinker
>>> auth()->user()->getAllPermissions()->pluck('name')

# Check user roles
>>> auth()->user()->roles->pluck('name')

# Check role permissions
>>> \App\Models\Role::find(1)->permissions->pluck('name')
```

## Support

For issues or questions about the authentication system, check:
1. Laravel documentation on authentication
2. Laravel Sanctum documentation
3. Database migrations and seeders
4. Unit tests for examples
