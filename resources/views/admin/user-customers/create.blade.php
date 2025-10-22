@extends('layouts.admin')

@section('title', 'Assign Customers to User - Kanesan UBS Backend')

@section('page-title', 'Assign Customers to User')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.user-customers.index') }}">User-Customer Assignments</a></li>
    <li class="breadcrumb-item active">Assign Customers</li>
@endsection

@section('card-title', 'Assign Multiple Customers to User')

@section('admin-content')
    <form action="{{ route('admin.user-customers.store') }}" method="POST" id="assignmentForm">
        @csrf
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
                    <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror" required>
                        <option value="">Select a user...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="customer_ids" class="form-label">Customers <span class="text-danger">*</span></label>
                    <select name="customer_ids[]" id="customer_ids" class="form-control @error('customer_ids') is-invalid @enderror" multiple required>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ in_array($customer->id, old('customer_ids', [])) ? 'selected' : '' }}>
                                {{ $customer->name }} 
                                @if($customer->customer_code)
                                    ({{ $customer->customer_code }})
                                @endif
                                @if($customer->company_name)
                                    - {{ $customer->company_name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('customer_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple customers</small>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Assignments
                    </button>
                    <a href="{{ route('admin.user-customers.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
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
        placeholder: 'Select customers...',
        allowClear: true,
        width: '100%',
        closeOnSelect: false
    });
    
    // Initialize Select2 for users
    $('#user_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select a user...',
        allowClear: true,
        width: '100%'
    });
    
    // Form validation
    document.getElementById('assignmentForm').addEventListener('submit', function(e) {
        const userId = document.getElementById('user_id').value;
        const customerIds = document.getElementById('customer_ids').value;
        
        if (!userId) {
            e.preventDefault();
            alert('Please select a user.');
            return false;
        }
        
        if (!customerIds || customerIds.length === 0) {
            e.preventDefault();
            alert('Please select at least one customer.');
            return false;
        }
    });
});
</script>
@endpush
