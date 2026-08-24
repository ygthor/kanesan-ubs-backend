@extends('layouts.admin')

@section('title', 'Edit Period - Kanesan UBS Backend')

@section('page-title', 'Edit Period')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.periods.index') }}">Period Management</a></li>
    <li class="breadcrumb-item active">Edit Period</li>
@endsection

@section('card-title', 'Edit Period')

@section('admin-content')
    <form method="POST" action="{{ route('admin.periods.update', $period->id) }}">
        @csrf
        @method('PUT')
        
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
                        @if($period)
                            <p class="text-muted small">
                                <strong>Created:</strong> {{ $period->created_at?->format('Y-m-d H:i') ?? 'N/A' }}<br>
                                <strong>Updated:</strong> {{ $period->updated_at?->format('Y-m-d H:i') ?? 'N/A' }}
                            </p>
                            <hr>
                        @endif
                        <p class="text-muted small">
                            Periods are used to define date ranges for reporting and data management.
                            Start date is fixed as 01 Jan. Default end date is +18 months.
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
                        <i class="fas fa-save"></i> Update Period
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection
