@extends('layouts.form')

@section('title', 'Create Territory - Kanesan UBS Backend')

@section('page-title', 'Create Territory')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.territories.index') }}">Territory Management</a></li>
    <li class="breadcrumb-item active">Create Territory</li>
@endsection

@section('card-title', 'Create New Territory')

@section('form-action', route('admin.territories.store'))

@section('submit-text', 'Create Territory')

@section('cancel-url', route('admin.territories.index'))

@section('form-fields')
    <div class="form-group">
        <label for="area" class="form-label required-field">Area Code</label>
        <input type="text" class="form-control @error('area') is-invalid @enderror" 
               id="area" name="area" value="{{ old('area') }}" required 
               placeholder="e.g., IPOH, TAIPING">
        @error('area')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter the area code/abbreviation (must be unique)</div>
    </div>

    <div class="form-group">
        <label for="description" class="form-label required-field">Description</label>
        <input type="text" class="form-control @error('description') is-invalid @enderror" 
               id="description" name="description" value="{{ old('description') }}" required
               placeholder="e.g., IPOH, TAIPING">
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter the full description of the territory</div>
    </div>
@endsection

@section('form-sidebar')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Territory Information</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Territories are used to categorize customers by geographical areas. 
                Make sure the area code is unique and descriptive.
            </p>
        </div>
    </div>
@endsection

