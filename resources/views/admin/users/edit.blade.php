@extends('layouts.form')

@section('title', 'Edit User - Kanesan UBS Backend')

@section('page-title', 'Edit User')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active">Edit User</li>
@endsection

@section('card-title', 'Edit User: ' . $user->name)

@section('form-action', route('admin.users.update', $user->id))

@section('form-method')
    @method('PUT')
@endsection

@section('submit-text', 'Update User')

@section('cancel-url', route('admin.users.index'))

@section('form-fields')
    <div class="form-group">
        <label for="name" class="form-label required-field">Full Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" 
               id="name" name="name" value="{{ old('name', $user->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter the user's full name</div>
    </div>

    <div class="form-group">
        <label for="username" class="form-label required-field">Username</label>
        <input type="text" class="form-control @error('username') is-invalid @enderror" 
               id="username" name="username" value="{{ old('username', $user->username) }}" required>
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Username must be unique</div>
    </div>

    <div class="form-group">
        <label for="email" class="form-label required-field">Email Address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" 
               id="email" name="email" value="{{ old('email', $user->email) }}" required>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Email must be unique and valid</div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                       id="password" name="password" 
                       placeholder="Leave blank to keep current password"
                       autocomplete="new-password"
                       data-lpignore="true">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="help-text">Leave blank to keep current password</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" 
                       id="password_confirmation" name="password_confirmation" 
                       placeholder="Confirm new password"
                       autocomplete="new-password"
                       data-lpignore="true">
                <div class="help-text">Must match new password</div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="roles" class="form-label">Roles</label>
        <select class="form-select @error('roles') is-invalid @enderror" 
                id="roles" name="roles[]" multiple>
            @foreach($roles as $role)
                <option value="{{ $role->role_id }}" 
                        {{ in_array($role->role_id, old('roles', $user->roles->pluck('role_id')->toArray())) ? 'selected' : '' }}>
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
                <select class="form-select @error('status') is-invalid @enderror" 
                        id="status" name="status">
                    <option value="active" {{ old('status', $user->status ?? 'active') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="suspended" {{ old('status', $user->status ?? 'active') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="help-text">Set user account status</div>
            </div>

            <div class="form-group">
                <label class="form-label">Account Information</label>
                <div class="bg-light p-3 rounded border">
                    <div class="mb-2">
                        <strong>Created:</strong> {{ $user->created_at?->format('M d, Y H:i') ?? 'N/A' }}
                    </div>
                    <div class="mb-2">
                        <strong>Last Updated:</strong> {{ $user->updated_at?->format('M d, Y H:i') ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Last Login:</strong> 
                        @if($user->last_login_at)
                            {{ $user->last_login_at->format('M d, Y H:i') }}
                        @else
                            <span class="text-muted">Never</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Current Roles</label>
                @if($user->roles->count() > 0)
                    @foreach($user->roles as $role)
                        <span class="badge badge-primary">{{ $role->name }}</span>
                    @endforeach
                @else
                    <span class="text-muted">No roles assigned</span>
                @endif
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
                <i class="fas fa-key"></i> Generate New Password
            </button>
            <a href="{{ route('admin.users.show', $user->id) }}" 
               class="btn btn-outline-info btn-sm w-100 mb-2">
                <i class="fas fa-eye"></i> View User Details
            </a>
            <button type="button" class="btn btn-outline-warning btn-sm w-100" 
                    onclick="resetForm()">
                <i class="fas fa-undo"></i> Reset Form
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

function resetForm() {
    if (confirm('Are you sure you want to reset the form? All changes will be lost.')) {
        document.querySelector('form').reset();
        // Restore original values
        document.getElementById('name').value = '{{ $user->name }}';
        document.getElementById('username').value = '{{ $user->username }}';
        document.getElementById('email').value = '{{ $user->email }}';
        document.getElementById('password').value = '';
        document.getElementById('password_confirmation').value = '';
    }
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
