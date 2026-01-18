@extends('layouts.admin')

@section('title', 'Create Period - Kanesan UBS Backend')

@section('page-title', 'Create Period')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.periods.index') }}">Period Management</a></li>
    <li class="breadcrumb-item active">Create Period</li>
@endsection

@section('card-title', 'Create New Period')

@section('admin-content')
    <form method="POST" action="{{ route('admin.periods.store') }}" enctype="multipart/form-data">
        @csrf
        
        <div class="row">
            <div class="col-md-8">
                @include('admin.periods._form')
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Period Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Periods are used to define date ranges for reporting and data management.
                            Make sure the start date is before the end date.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card-footer text-right">
                    <a href="{{ route('admin.periods.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Period
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('styles')
    <style>
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .card-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const monthCountInput = document.getElementById('month_count');

    function calculateMonthCount() {
        if (!startDateInput.value || !endDateInput.value) {
            monthCountInput.value = '';
            return;
        }

        // Parse date string explicitly to avoid timezone issues
        const [startYear, startMonth, startDay] = startDateInput.value.split('-').map(Number);
        const [endYear, endMonth, endDay] = endDateInput.value.split('-').map(Number);
        
        const startDate = new Date(startYear, startMonth - 1, startDay);
        const endDate = new Date(endYear, endMonth - 1, endDay);

        if (startDate <= endDate) {
            // Calculate months difference
            const months = (endYear - startYear) * 12 + (endMonth - startMonth) + 1;
            monthCountInput.value = months + ' month' + (months !== 1 ? 's' : '');
        } else {
            monthCountInput.value = '';
        }
    }

    startDateInput.addEventListener('change', calculateMonthCount);
    endDateInput.addEventListener('change', calculateMonthCount);

    // Calculate on page load if dates are already set
    calculateMonthCount();
});
</script>
@endpush
