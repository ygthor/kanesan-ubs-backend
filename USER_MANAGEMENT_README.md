# User Management System

This document describes the complete user management system implemented for the Kanesan UBS Backend.

## Overview

The user management system provides full CRUD (Create, Read, Update, Delete) operations for managing users in the system. It includes role assignment, permission management, and a modern web interface built with Bootstrap 5 and AdminKit.

## Features

### ✅ **User CRUD Operations**
- **Create**: Add new users with validation
- **Read**: View user list and individual user details
- **Update**: Edit user information and roles
- **Delete**: Remove users (with safety checks)

### ✅ **Role Management**
- Assign multiple roles to users
- Role-based access control
- Visual role display with badges

### ✅ **Security Features**
- Password hashing
- Validation rules
- CSRF protection
- Self-deletion prevention

### ✅ **User Interface**
- Responsive design
- Search and filtering
- Pagination
- Action buttons with tooltips
- Flash messages for feedback

## File Structure

```
app/
├── Http/Controllers/Admin/
│   └── UserManagementController.php    # Main controller
├── Models/
│   ├── User.php                        # User model
│   ├── Role.php                        # Role model
│   └── Permission.php                  # Permission model
resources/views/
├── admin/users/
│   ├── index.blade.php                 # User list
│   ├── create.blade.php                # Create form
│   ├── edit.blade.php                  # Edit form
│   └── show.blade.php                  # User details
├── layouts/
│   ├── app.blade.php                   # Main layout
│   ├── admin.blade.php                 # Admin layout
│   └── form.blade.php                  # Form layout
└── demo-users.blade.php                # Demo page
routes/
└── web.php                             # Web routes
```

## Routes

### Admin Routes
```php
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserManagementController::class);
});
```

**Available Routes:**
- `GET /admin/users` - User list (index)
- `GET /admin/users/create` - Create user form
- `POST /admin/users` - Store new user
- `GET /admin/users/{user}` - Show user details
- `GET /admin/users/{user}/edit` - Edit user form
- `PUT/PATCH /admin/users/{user}` - Update user
- `DELETE /admin/users/{user}` - Delete user

## Controller Methods

### UserManagementController

#### `index()`
- Displays paginated list of users
- Includes role relationships
- Returns view with users data

#### `create()`
- Shows user creation form
- Loads available roles for assignment
- Returns create view

#### `store(Request $request)`
- Validates user input
- Creates new user with hashed password
- Assigns selected roles
- Redirects with success message

#### `show(User $user)`
- Displays user details
- Loads roles and permissions
- Returns show view

#### `edit(User $user)`
- Shows user edit form
- Pre-fills current values
- Loads available roles
- Returns edit view

#### `update(Request $request, User $user)`
- Validates input
- Updates user information
- Handles password changes (optional)
- Syncs role assignments
- Redirects with success message

#### `destroy(User $user)`
- Prevents self-deletion
- Removes user from system
- Redirects with success message

## Validation Rules

### Create User
```php
'name' => 'required|string|max:255',
'username' => 'required|string|max:255|unique:users',
'email' => 'required|string|email|max:255|unique:users',
'password' => 'required|string|min:8|confirmed',
'roles' => 'array',
'roles.*' => 'exists:roles,role_id'
```

### Update User
```php
'name' => 'required|string|max:255',
'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
'password' => 'nullable|string|min:8|confirmed',
'roles' => 'array',
'roles.*' => 'exists:roles,role_id'
```

## Views

### 1. User List (`index.blade.php`)
- **Layout**: `layouts.admin`
- **Features**:
  - Search functionality
  - Pagination controls
  - Action buttons (View, Edit, Delete)
  - Role badges
  - Status indicators

### 2. Create User (`create.blade.php`)
- **Layout**: `layouts.form`
- **Features**:
  - Form validation
  - Role selection (multiple)
  - Password confirmation
  - Auto-save to localStorage
  - Password generator

### 3. Edit User (`edit.blade.php`)
- **Layout**: `layouts.form`
- **Features**:
  - Pre-filled form fields
  - Optional password change
  - Current role display
  - Form reset functionality

### 4. User Details (`show.blade.php`)
- **Layout**: `layouts.admin`
- **Features**:
  - User information cards
  - Role and permission display
  - Quick action buttons
  - Account statistics

## Database Schema

### Users Table
```sql
users
├── id (bigint, primary key)
├── name (varchar)
├── username (varchar, unique)
├── email (varchar, unique)
├── password (varchar, hashed)
├── email_verified_at (timestamp, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)
```

### User-Role Relationship
```sql
user_roles
├── id (bigint, primary key)
├── user_id (bigint, foreign key)
├── role_id (varchar, foreign key)
├── created_at (timestamp)
└── updated_at (timestamp)
```

## Security Features

### 1. Authentication
- All routes require authentication
- Uses Laravel's built-in auth system

### 2. Authorization
- Role-based access control
- Permission checking
- KBS user bypass (hardcoded super access)

### 3. Input Validation
- Server-side validation
- CSRF protection
- SQL injection prevention

### 4. Data Protection
- Password hashing
- Self-deletion prevention
- Audit trail (timestamps)

## Usage Examples

### Creating a New User
1. Navigate to `/admin/users`
2. Click "Add New User"
3. Fill in the form:
   - Name: John Doe
   - Username: johndoe
   - Email: john@example.com
   - Password: secure123
   - Confirm Password: secure123
   - Select roles (optional)
4. Click "Create User"

### Editing a User
1. Navigate to `/admin/users`
2. Click the edit button (pencil icon)
3. Modify the desired fields
4. Click "Update User"

### Deleting a User
1. Navigate to `/admin/users`
2. Click the delete button (trash icon)
3. Confirm the deletion
4. User is removed from system

## JavaScript Features

### Auto-save Forms
- Form data is automatically saved to localStorage
- Prevents data loss on page refresh
- Clears saved data on successful submission

### Password Generator
- Generates secure 12-character passwords
- Includes uppercase, lowercase, numbers, and symbols
- Automatically fills password fields

### Form Validation
- Real-time validation feedback
- Bootstrap validation classes
- Custom error messages

## Testing

### Demo Page
Visit `/demo-users` to see:
- Current users in the system
- Role assignments
- Quick access to user management

### Test Data
The system includes sample data:
- Default admin user
- Sample roles and permissions
- Test users for development

## Troubleshooting

### Common Issues

1. **"Table doesn't exist" errors**
   - Run migrations: `php artisan migrate`
   - Check database connection

2. **"Route not found" errors**
   - Clear route cache: `php artisan route:clear`
   - Check route definitions

3. **"Permission denied" errors**
   - Verify user has required permissions
   - Check role assignments

4. **Form validation errors**
   - Check validation rules in controller
   - Verify form field names match

### Debug Commands
```bash
# List all routes
php artisan route:list --name=admin

# Check migration status
php artisan migrate:status

# Clear application cache
php artisan cache:clear

# View application logs
tail -f storage/logs/laravel.log
```

## Future Enhancements

### Planned Features
1. **Bulk Operations**
   - Bulk user import/export
   - Mass role assignment
   - Batch user deletion

2. **Advanced Search**
   - Filter by role, status, date
   - Full-text search
   - Saved search queries

3. **User Activity**
   - Login history
   - Action audit trail
   - Last activity tracking

4. **Email Integration**
   - Welcome emails
   - Password reset emails
   - Account verification

5. **API Endpoints**
   - RESTful API for user management
   - Mobile app integration
   - Third-party integrations

## Support

For technical support or questions about the user management system:
1. Check this documentation
2. Review Laravel documentation
3. Check application logs
4. Contact development team

## Version History

- **v1.0.0** - Initial implementation
  - Basic CRUD operations
  - Role management
  - Bootstrap 5 + AdminKit UI
  - Form validation and security
