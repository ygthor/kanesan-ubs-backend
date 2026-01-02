@extends('layouts.admin')

@section('title', 'E-Invoice Requests - Kanesan UBS Backend')

@section('page-title', 'E-Invoice Requests')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">E-Invoice Requests</li>
@endsection

@section('card-title', 'E-Invoice Requests')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.e-invoice-requests.index') }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control" value="{{ $invoiceNo ?? '' }}" placeholder="Search invoice number">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">All Customers</option>
                        @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}" {{ ($customerId ?? '') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->customer_code }} - {{ $customer->company_name ?? $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.e-invoice-requests.index') }}" class="btn btn-secondary">
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
                    <th>Request Date</th>
                    <th>Invoice No</th>
                    <th>Customer Code</th>
                    <th>Company/Individual Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    <tr>
                        <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $request->invoice_no ?? 'N/A' }}</td>
                        <td>{{ $request->customer_code ?? 'N/A' }}</td>
                        <td>{{ $request->company_individual_name ?? 'N/A' }}</td>
                        <td>{{ $request->contact ?? 'N/A' }}</td>
                        <td>{{ $request->email_address ?? 'N/A' }}</td>
                        <td>
                            <a href="{{ route('admin.e-invoice-requests.edit', $request->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No e-invoice requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $requests->links() }}
    </div>
@endsection

