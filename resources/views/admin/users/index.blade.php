@extends('layouts.admin')

@section('title', 'User Management - Kanesan UBS Backend')

@section('page-title', 'User Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">User Management</li>
@endsection

@section('card-title', 'Users')

@section('card-tools')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add New User
    </a>
@endsection

@section('admin-content')
    <!-- Search and Filters -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control search-box" placeholder="Search users..." id="searchInput">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <select class="form-control per-page-selector" id="perPage">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users ?? [] as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>
                            <strong>{{ $user->name }}</strong>
                        </td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="badge badge-primary">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if($user->status === 'active')
                                <span class="badge badge-success">Active</span>
                            @elseif($user->status === 'inactive')
                                <span class="badge badge-secondary">Inactive</span>
                            @elseif($user->status === 'suspended')
                                <span class="badge badge-danger">Suspended</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="action-buttons">
                            <a href="{{ route('admin.users.show', $user->id) }}" 
                               class="btn btn-info btn-sm" 
                               data-toggle="tooltip" 
                               title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.users.edit', $user->id) }}" 
                               class="btn btn-warning btn-sm" 
                               data-toggle="tooltip" 
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="{{ route('admin.users.customers', $user->id) }}" 
                               class="btn btn-success btn-sm" 
                               data-toggle="tooltip" 
                               title="Manage Customer Assignments">
                                <i class="fas fa-users-cog"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" 
                                  style="display: inline;" class="d-inline">
                                @csrf
                                
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No users found.</p>
                            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First User
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if(isset($users) && $users->hasPages())
        <div class="pagination-wrapper">
            <div>
                Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} results
            </div>
            <div>
                {{ $users->links() }}
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Search functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Per page selector
    $('#perPage').on('change', function() {
        var perPage = $(this).val();
        // You can implement AJAX pagination here
        console.log('Per page changed to:', perPage);
    });
});
</script>
@endpush
