@extends('layouts.admin')

@section('title', 'Manage Customer Assignments - ' . $user->name . ' - Kanesan UBS Backend')

@section('page-title', 'Manage Customer Assignments')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active">Customer Assignments - {{ $user->name }}</li>
@endsection

@section('card-title', 'Customer Assignments for ' . $user->name)

@section('card-tools')
    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
@endsection

@section('admin-content')
    <!-- User Information Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Name:</strong><br>
                            {{ $user->name }}
                        </div>
                        <div class="col-md-3">
                            <strong>Username:</strong><br>
                            {{ $user->username }}
                        </div>
                        <div class="col-md-3">
                            <strong>Email:</strong><br>
                            {{ $user->email }}
                        </div>
                        <div class="col-md-3">
                            <strong>Assigned Customers:</strong><br>
                            <span class="badge badge-primary">{{ $user->customers->count() }} customers</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Customers -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add Customer Assignments</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.users.customers.store', $user->id) }}" method="POST" id="assignmentForm">
                        @csrf
                        
                        <div class="form-group">
                            <label for="customer_ids" class="form-label">Select Customers to Assign <span class="text-danger">*</span></label>
                            <select name="customer_ids[]" id="customer_ids" class="form-control @error('customer_ids') is-invalid @enderror" multiple required>
                                @foreach($customers as $customer)
                                    @if(!$user->customers->contains($customer->id))
                                        <option value="{{ $customer->id }}">
                                            {{ $customer->name }} 
                                            @if($customer->customer_code)
                                                ({{ $customer->customer_code }})
                                            @endif
                                            @if($customer->company_name)
                                                - {{ $customer->company_name }}
                                            @endif
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('customer_ids')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Select one or more customers to assign to this user</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Assign Selected Customers
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Currently Assigned Customers -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Currently Assigned Customers</h5>
                </div>
                <div class="card-body">
                    @if($user->customers->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Customer Code</th>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Assigned Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->customers as $customer)
                                        <tr>
                                            <td>
                                                <strong>{{ $customer->name }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $customer->customer_code }}</span>
                                            </td>
                                            <td>{{ $customer->company_name ?? 'N/A' }}</td>
                                            <td>{{ $customer->email ?? 'N/A' }}</td>
                                            <td>{{ $customer->phone ?? 'N/A' }}</td>
                                            <td>{{ $customer->pivot->created_at ? $customer->pivot->created_at->format('M d, Y') : 'N/A' }}</td>
                                            <td>
                                                @if($customer->pivot->id ?? false)
                                                    <form action="{{ route('admin.users.customers.destroy', [$user->id, $customer->pivot->id]) }}" 
                                                          method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to remove this customer assignment?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-muted">No assignment ID</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No customers assigned to this user yet.</p>
                            <p>Use the form above to assign customers.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for customers
    $('#customer_ids').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select customers to assign...',
        allowClear: true,
        width: '100%',
        closeOnSelect: false
    });
    
    // Form validation
    document.getElementById('assignmentForm').addEventListener('submit', function(e) {
        const customerIds = document.getElementById('customer_ids').value;
        
        if (!customerIds || customerIds.length === 0) {
            e.preventDefault();
            alert('Please select at least one customer.');
            return false;
        }
    });
});
</script>
@endpush
