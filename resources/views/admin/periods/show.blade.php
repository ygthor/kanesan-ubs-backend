@extends('layouts.admin')

@section('title', 'View Period - Kanesan UBS Backend')

@section('page-title', 'View Period')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.periods.index') }}">Period Management</a></li>
    <li class="breadcrumb-item active">View Period</li>
@endsection

@section('card-title', 'Period Details')

@section('card-tools')
    <a href="{{ route('admin.periods.edit', $period->id) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a href="{{ route('admin.periods.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $period->id }}</dd>

                        <dt class="col-sm-3">Name:</dt>
                        <dd class="col-sm-9">
                            <strong>{{ $period->name }}</strong>
                        </dd>

                        <dt class="col-sm-3">Start Date:</dt>
                        <dd class="col-sm-9">{{ $period->start_date?->format('M d, Y') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">End Date:</dt>
                        <dd class="col-sm-9">{{ $period->end_date?->format('M d, Y') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Description:</dt>
                        <dd class="col-sm-9">{{ $period->description ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Status:</dt>
                        <dd class="col-sm-9">
                            @if($period->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Created At:</dt>
                        <dd class="col-sm-9">{{ $period->created_at?->format('M d, Y H:i:s') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Updated At:</dt>
                        <dd class="col-sm-9">{{ $period->updated_at?->format('M d, Y H:i:s') ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.periods.edit', $period->id) }}" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-edit"></i> Edit Period
                    </a>
                    <form method="POST" action="{{ route('admin.periods.destroy', $period->id) }}"
                          onsubmit="return confirm('Are you sure you want to delete this period?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> Delete Period
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
