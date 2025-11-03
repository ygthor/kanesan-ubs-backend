@extends('layouts.form')

@section('title', 'Edit Territory - Kanesan UBS Backend')

@section('page-title', 'Edit Territory')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.territories.index') }}">Territory Management</a></li>
    <li class="breadcrumb-item active">Edit Territory</li>
@endsection

@section('card-title', 'Edit Territory')

@section('form-action', route('admin.territories.update', $territory->id))

@section('form-method')
    @method('PUT')
@endsection

@section('submit-text', 'Update Territory')

@section('cancel-url', route('admin.territories.index'))

@section('form-fields')
    <div class="form-group">
        <label for="area" class="form-label required-field">Area Code</label>
        <input type="text" class="form-control @error('area') is-invalid @enderror" 
               id="area" name="area" value="{{ old('area', $territory->area) }}" required>
        @error('area')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="help-text">Enter the area code/abbreviation (must be unique)</div>
    </div>

    <div class="form-group">
        <label for="description" class="form-label required-field">Description</label>
        <input type="text" class="form-control @error('description') is-invalid @enderror" 
               id="description" name="description" value="{{ old('description', $territory->description) }}" required>
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
                <strong>Created:</strong> {{ $territory->created_at?->format('M d, Y') ?? 'N/A' }}<br>
                <strong>Updated:</strong> {{ $territory->updated_at?->format('M d, Y') ?? 'N/A' }}
            </p>
            <hr>
            <p class="text-muted small">
                Note: Changing the area code may affect existing customer records that reference this territory.
            </p>
        </div>
    </div>
@endsection

