@extends('layouts.admin')

@section('title', 'User-Customer Assignments - Kanesan UBS Backend')

@section('page-title', 'User-Customer Assignments')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">User-Customer Assignments</li>
@endsection

@section('card-title', 'User-Customer Assignments')

@section('card-tools')
    <a href="{{ route('admin.user-customers.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Assign Customers to User
    </a>
@endsection

@section('admin-content')
    <!-- Search and Filters -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control search-box" placeholder="Search assignments..." id="searchInput">
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

    <!-- Assignments Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Customer</th>
                    <th>Customer Code</th>
                    <th>Assigned Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($userCustomers as $assignment)
                    <tr>
                        <td>{{ $assignment->id }}</td>
                        <td>
                            <div>
                                <strong>{{ $assignment->user->name }}</strong>
                                <br>
                                <small class="text-muted">{{ $assignment->user->email }}</small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong>{{ $assignment->customer->name }}</strong>
                                @if($assignment->customer->company_name)
                                    <br>
                                    <small class="text-muted">{{ $assignment->customer->company_name }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info">{{ $assignment->customer->customer_code }}</span>
                        </td>
                        <td>{{ $assignment->created_at->format('M d, Y') }}</td>
                        <td class="action-buttons">
                            <a href="{{ route('admin.user-customers.show', $assignment) }}" 
                               class="btn btn-info btn-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.user-customers.edit', $assignment) }}" 
                               class="btn btn-warning btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.user-customers.destroy', $assignment) }}" 
                                  method="POST" class="d-inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this assignment?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle"></i> No user-customer assignments found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($userCustomers->hasPages())
        <div class="pagination-wrapper">
            {{ $userCustomers->links() }}
        </div>
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
@endpush
