@extends('layouts.admin')

@section('title', 'Configuration')
@section('page-title', 'Configuration')
@section('card-title', 'System Configuration')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Configuration</li>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.configurations.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="stock_request_email">STOCK_REQUEST_EMAIL</label>
            <input
                type="text"
                id="stock_request_email"
                name="stock_request_email"
                class="form-control @error('stock_request_email') is-invalid @enderror"
                value="{{ old('stock_request_email', $stockRequestEmail) }}"
                placeholder="example@company.com or a@x.com,b@y.com"
            >
            <small class="form-text text-muted">Supports comma/space/semicolon separated emails.</small>
            @error('stock_request_email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="einvoice_email">EINVOICE_EMAIL</label>
            <input
                type="text"
                id="einvoice_email"
                name="einvoice_email"
                class="form-control @error('einvoice_email') is-invalid @enderror"
                value="{{ old('einvoice_email', $eInvoiceEmail) }}"
                placeholder="example@company.com or a@x.com,b@y.com"
            >
            <small class="form-text text-muted">Supports comma/space/semicolon separated emails.</small>
            @error('einvoice_email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Configuration
        </button>
    </form>
@endsection

