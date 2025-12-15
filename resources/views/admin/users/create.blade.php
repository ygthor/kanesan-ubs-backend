@extends('layouts.form')

@section('title', 'Create User - Kanesan UBS Backend')

@section('page-title', 'Create User')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active">Create User</li>
@endsection

@section('card-title', 'Create New User')

@section('form-action', route('admin.users.store'))

@section('submit-text', 'Create User')

@section('cancel-url', route('admin.users.index'))

@section('form-fields')
    <div class="form-group">
        <label for="name" class="form-label required-field">Full Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" 
               id="name" name="name" value="{{ old('name') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter the user's full name</div>
    </div>

    <div class="form-group">
        <label for="username" class="form-label required-field">Username</label>
        <input type="text" class="form-control @error('username') is-invalid @enderror" 
               id="username" name="username" value="{{ old('username') }}" required>
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Username must be unique</div>
    </div>

    <div class="form-group">
        <label for="email" class="form-label required-field">Email Address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" 
               id="email" name="email" value="{{ old('email') }}" required>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Email must be unique and valid</div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="password" class="form-label required-field">Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                       id="password" name="password" required
                       autocomplete="new-password"
                       data-lpignore="true">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="help-text">Minimum 8 characters</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="password_confirmation" class="form-label required-field">Confirm Password</label>
                <input type="password" class="form-control" 
                       id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password"
                       data-lpignore="true">
                <div class="help-text">Must match password</div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="roles" class="form-label">Roles</label>
        <select class="form-select @error('roles') is-invalid @enderror" 
                id="roles" name="roles[]" multiple>
            @foreach($roles ?? [] as $role)
                <option value="{{ $role->role_id }}" 
                        {{ in_array($role->role_id, old('roles', [])) ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('roles')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Hold Ctrl/Cmd to select multiple roles</div>
    </div>
@endsection

@section('form-sidebar')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">User Information</h5>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="suspended" {{ old('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
                <div class="help-text">Set user account status</div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="send_welcome_email" 
                           name="send_welcome_email" value="1" {{ old('send_welcome_email') ? 'checked' : '' }}>
                    <label class="form-check-label" for="send_welcome_email">
                        Send Welcome Email
                    </label>
                </div>
                <div class="help-text">Send welcome email to new user</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Quick Actions</h5>
        </div>
        <div class="card-body">
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-2" 
                    onclick="generatePassword()">
                <i class="fas fa-key"></i> Generate Password
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                    onclick="copyToClipboard()">
                <i class="fas fa-copy"></i> Copy Form Data
            </button>
        </div>
    </div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<script>
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
    document.getElementById('password_confirmation').value = password;
}

function copyToClipboard() {
    const formData = new FormData(document.querySelector('form'));
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    navigator.clipboard.writeText(JSON.stringify(data, null, 2)).then(function() {
        alert('Form data copied to clipboard!');
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#roles').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select roles...',
        allowClear: true,
        width: '100%',
        closeOnSelect: false
    });
});
</script>
@endpush
