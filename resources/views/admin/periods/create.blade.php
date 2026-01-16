@extends('layouts.form')

@section('title', 'Create Period - Kanesan UBS Backend')

@section('page-title', 'Create Period')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.periods.index') }}">Period Management</a></li>
    <li class="breadcrumb-item active">Create Period</li>
@endsection

@section('card-title', 'Create New Period')

@section('form-action', route('admin.periods.store'))

@section('submit-text', 'Create Period')

@section('cancel-url', route('admin.periods.index'))

@section('form-fields')
    <div class="form-group">
        <label for="name" class="form-label required-field">Period Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name') }}" required
               placeholder="e.g., Q1 2026, January 2026">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter a descriptive name for this period</div>
    </div>

    <div class="form-group">
        <label for="start_date" class="form-label required-field">Start Date</label>
        <input type="date" class="form-control @error('start_date') is-invalid @enderror"
               id="start_date" name="start_date" value="{{ old('start_date') }}" required>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Select the start date of the period</div>
    </div>

    <div class="form-group">
        <label for="end_date" class="form-label required-field">End Date</label>
        <input type="date" class="form-control @error('end_date') is-invalid @enderror"
               id="end_date" name="end_date" value="{{ old('end_date') }}" required>
        @error('end_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Select the end date of the period (must be after start date)</div>
    </div>

    <div class="form-group">
        <label for="description" class="form-label">Description</label>
        <input type="text" class="form-control @error('description') is-invalid @enderror"
               id="description" name="description" value="{{ old('description') }}"
               placeholder="Optional description">
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter an optional description for this period</div>
    </div>

    <div class="form-group">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                   {{ old('is_active', true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <div class="help-text">Check to make this period active</div>
    </div>
@endsection

@section('form-sidebar')
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
@endsection
