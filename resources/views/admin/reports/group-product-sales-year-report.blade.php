@extends('layouts.admin')

@section('title', 'Group Product Sales Report By Year - Kanesan UBS Backend')

@section('page-title', 'Group Product Sales Report By Year')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Group Product Sales Report By Year</li>
@endsection

@section('card-title', 'Group Product Sales Report By Year')

@section('admin-content')
    <form method="GET" action="{{ route('admin.reports.group-product-sales-year') }}" class="mb-3">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $filters['year'] ?? now()->year }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->name }}" {{ ($filters['agent_no'] ?? '') === $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Group</label>
                    <select name="group" class="form-control">
                        <option value="">All Groups</option>
                        @foreach($groups as $group)
                            <option value="{{ $group }}" {{ ($filters['group'] ?? '') === $group ? 'selected' : '' }}>
                                {{ $group }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Product Search</label>
                    <input type="text" name="product_search" class="form-control" value="{{ $filters['product_search'] ?? '' }}" placeholder="Code or Description">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Preview
                </button>
                <a href="{{ route('admin.reports.group-product-sales-year') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-year.export.pdf') }}"
                    formtarget="_blank"
                    class="btn btn-danger"
                >
                    <i class="fas fa-file-pdf"></i> Print PDF
                </button>
                {{-- <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-year.export.excel') }}"
                    class="btn btn-success"
                >
                    <i class="fas fa-file-excel"></i> Export Excel
                </button> --}}
            </div>
        </div>
    </form>

    <div class="mb-2 text-center">
        <h4 class="mb-1">PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA</h4>
        <h5 class="mb-0">GROUP PRODUCT SALES REPORT - YEAR {{ $year }}</h5>
    </div>

    @include('admin.reports.partials.group-product-sales-year-table')
@endsection
