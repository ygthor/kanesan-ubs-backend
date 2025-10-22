@extends('layouts.admin')

@section('title', 'Manage User\'s Customers - Kanesan UBS Backend')

@section('page-title', 'Manage User\'s Customers')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.user-customers.index') }}">User-Customer Assignments</a></li>
    <li class="breadcrumb-item active">Manage User's Customers</li>
@endsection

@section('card-title', 'Manage User\'s Customer Assignments')

@section('admin-content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $userCustomer->user->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>{{ $userCustomer->user->username }}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ $userCustomer->user->email }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add More Customers</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.user-customers.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $userCustomer->user_id }}">
                        
                        <div class="form-group">
                            <label for="customer_ids" class="form-label">Select Additional Customers</label>
                            <select name="customer_ids[]" id="customer_ids" class="form-control" multiple>
                                @foreach($customers as $customer)
                                    @if(!$userCustomer->user->customers->contains($customer->id))
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
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Selected Customers
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Currently Assigned Customers</h5>
                </div>
                <div class="card-body">
                    @if($userCustomer->user->customers->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Customer Code</th>
                                        <th>Company</th>
                                        <th>Assigned Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($userCustomer->user->customers as $customer)
                                        <tr>
                                            <td>{{ $customer->name }}</td>
                                            <td>
                                                <span class="badge badge-info">{{ $customer->customer_code }}</span>
                                            </td>
                                            <td>{{ $customer->company_name ?? 'N/A' }}</td>
                                            <td>{{ $customer->pivot->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <form action="{{ route('admin.user-customers.destroy', $customer->pivot->id) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to remove this customer assignment?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No customers assigned to this user.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <a href="{{ route('admin.user-customers.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
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
        placeholder: 'Select customers to add...',
        allowClear: true,
        width: '100%',
        closeOnSelect: false
    });
});
</script>
@endpush
