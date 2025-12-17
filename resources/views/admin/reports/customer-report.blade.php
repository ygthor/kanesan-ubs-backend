@extends('layouts.admin')

@section('title', 'Customer Report - Kanesan UBS Backend')

@section('page-title', 'Customer Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Customer Report</li>
@endsection

@section('card-title', 'Customer Report')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.customers') }}" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Customer Search</label>
                    <input type="text" name="customer_search" class="form-control" value="{{ $customerSearch ?? '' }}" placeholder="Code, Name, or Company Name">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer Type</label>
                    <select name="customer_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="CREDITOR" {{ ($customerType ?? '') == 'CREDITOR' ? 'selected' : '' }}>Creditor</option>
                        <option value="Cash" {{ ($customerType ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
                        <option value="Cash Sales" {{ ($customerType ?? '') == 'Cash Sales' ? 'selected' : '' }}>Cash Sales</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Territory</label>
                    <select name="territory_id" class="form-control">
                        <option value="">All Territories</option>
                        @foreach(\App\Models\Territory::orderBy('area')->get() as $territory)
                            <option value="{{ $territory->id }}" {{ ($territoryId ?? '') == $territory->id ? 'selected' : '' }}>
                                {{ $territory->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <a href="{{ route('admin.reports.customers') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Customer Code</th>
                    <th>Name</th>
                    <th>Company Name</th>
                    <th>Type</th>
                    <th>Agent No</th>
                    <th>Territory</th>
                    <th>Phone</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers ?? [] as $customer)
                    <tr>
                        <td>{{ $customer->customer_code }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->company_name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge badge-{{ $customer->customer_type == 'CREDITOR' ? 'primary' : 'success' }}">
                                {{ $customer->customer_type }}
                            </span>
                        </td>
                        <td>{{ $customer->agent_no ?? 'N/A' }}</td>
                        <td>
                            @php
                                $territory = \App\Models\Territory::where('area', $customer->territory)->first();
                            @endphp
                            {{ $territory->description ?? $customer->territory ?? 'N/A' }}
                        </td>
                        <td>{{ $customer->phone ?? 'N/A' }}</td>
                        <td>{{ $customer->address1 ?? '' }} {{ $customer->address2 ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No customers found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($customers) && $customers->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="8" class="text-center">Total Customers: {{ $customers->count() }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
