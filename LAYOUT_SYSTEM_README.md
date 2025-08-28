# Layout System Documentation

This document describes the reusable layout system implemented for the Kanesan UBS Backend using Laravel Blade templates with Bootstrap 5 and AdminKit.

## Overview

The layout system is built using Laravel Blade's `@extends`, `@section`, and `@stack` directives to create reusable templates. This approach eliminates code duplication and ensures consistency across all pages.

## Layout Files

### 1. `resources/views/layouts/app.blade.php` - Main Application Layout
- **Purpose**: Main layout for authenticated pages with sidebar navigation
- **Features**:
  - Responsive sidebar with navigation menu
  - Top navbar with user dropdown
  - Content wrapper with breadcrumbs
  - Flash message handling
  - Footer
  - Bootstrap 5 + AdminKit styling

**Available Sections:**
- `@section('title')` - Page title
- `@section('page-title')` - Main page heading
- `@section('breadcrumbs')` - Breadcrumb navigation
- `@section('content')` - Main page content
- `@stack('styles')` - Additional CSS
- `@stack('scripts')` - Additional JavaScript

### 2. `resources/views/layouts/auth.blade.php` - Authentication Layout
- **Purpose**: Layout for login, register, and other auth pages
- **Features**:
  - Centered card design
  - Gradient background
  - Bootstrap 5 styling
  - Flash message handling
  - No sidebar

**Available Sections:**
- `@section('title')` - Page title
- `@section('subtitle')` - Page subtitle
- `@section('content')` - Form content
- `@stack('styles')` - Additional CSS
- `@stack('scripts')` - Additional JavaScript

### 3. `resources/views/layouts/admin.blade.php` - Admin Panel Layout
- **Purpose**: Layout for admin management pages
- **Features**:
  - Extends main app layout
  - Card-based content structure
  - Common admin styling
  - JavaScript utilities

**Available Sections:**
- `@section('card-title')` - Card header title
- `@section('card-tools')` - Card header tools (buttons, etc.)
- `@section('admin-content')` - Main admin content

### 4. `resources/views/layouts/form.blade.php` - Form Layout
- **Purpose**: Layout for create/edit forms
- **Features**:
  - Extends admin layout
  - Two-column form layout (main fields + sidebar)
  - Form validation styling
  - Auto-save functionality
  - Common form utilities

**Available Sections:**
- `@section('form-action')` - Form submission URL
- `@section('form-method')` - HTTP method (PUT, PATCH, etc.)
- `@section('form-fields')` - Main form fields
- `@section('form-sidebar')` - Sidebar content
- `@section('submit-text')` - Submit button text
- `@section('cancel-url')` - Cancel button URL

## Usage Examples

### Basic Page (Dashboard)
```php
@extends('layouts.app')

@section('title', 'Dashboard - Kanesan UBS Backend')
@section('page-title', 'Dashboard')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    <!-- Your dashboard content here -->
@endsection
```

### Admin List Page
```php
@extends('layouts.admin')

@section('title', 'User Management - Kanesan UBS Backend')
@section('page-title', 'User Management')
@section('card-title', 'Users')
@section('card-tools')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add New User
    </a>
@endsection

@section('admin-content')
    <!-- Your table/list content here -->
@endsection
```

### Form Page
```php
@extends('layouts.form')

@section('title', 'Create User - Kanesan UBS Backend')
@section('page-title', 'Create User')
@section('form-action', route('admin.users.store'))
@section('submit-text', 'Create User')

@section('form-fields')
    <!-- Your form fields here -->
@endsection

@section('form-sidebar')
    <!-- Your sidebar content here -->
@endsection
```

## Features

### 1. Responsive Design
- Mobile-first approach
- Collapsible sidebar
- Responsive tables and forms

### 2. Bootstrap 5 Integration
- Modern Bootstrap components
- Custom color scheme
- Responsive grid system

### 3. AdminKit Features
- Professional admin interface
- Sidebar navigation
- Card-based layouts
- Icon support (Font Awesome)

### 4. JavaScript Enhancements
- Auto-save forms to localStorage
- Confirmation dialogs
- Tooltips and popovers
- Auto-hiding alerts

### 5. Flash Messages
- Success, error, and warning alerts
- Automatic styling
- Dismissible alerts

### 6. Breadcrumb Navigation
- Dynamic breadcrumb generation
- Consistent navigation structure

## Customization

### Adding Custom Styles
```php
@push('styles')
<style>
    .custom-class {
        /* Your custom styles */
    }
</style>
@endpush
```

### Adding Custom Scripts
```php
@push('scripts')
<script>
    // Your custom JavaScript
</script>
@endpush
```

### Custom Layouts
You can create additional layouts by extending existing ones:

```php
@extends('layouts.admin')

@section('admin-content')
    <!-- Custom admin content -->
@endsection
```

## Best Practices

1. **Always use layouts**: Don't create standalone Blade files
2. **Consistent section naming**: Use the predefined section names
3. **Responsive design**: Test on different screen sizes
4. **Accessibility**: Use proper ARIA labels and semantic HTML
5. **Performance**: Minimize custom CSS/JS in individual views
6. **Maintenance**: Keep layout files clean and focused

## File Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php          # Main application layout
│   ├── auth.blade.php         # Authentication layout
│   ├── admin.blade.php        # Admin panel layout
│   └── form.blade.php         # Form layout
├── auth/
│   └── login.blade.php        # Login page (extends auth layout)
├── admin/
│   └── users/
│       ├── index.blade.php    # User list (extends admin layout)
│       └── create.blade.php   # User form (extends form layout)
└── dashboard.blade.php        # Dashboard (extends app layout)
```

## Dependencies

- **Bootstrap 5.1.3**: CSS framework
- **AdminKit 3.2**: Admin template
- **Font Awesome 6.0**: Icons
- **jQuery 3.6.0**: JavaScript library
- **Google Fonts**: Source Sans Pro

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Internet Explorer 11+ (with polyfills)

## Future Enhancements

1. **Dark mode support**
2. **Customizable themes**
3. **Advanced form validation**
4. **Drag and drop functionality**
5. **Real-time updates**
6. **Progressive Web App features**
