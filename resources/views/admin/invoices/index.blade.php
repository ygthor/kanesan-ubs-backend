@extends('layouts.admin')

@section('title', 'Invoices - Kanesan UBS Backend')

@section('page-title', 'Invoices')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Invoices</li>
@endsection

@section('card-title', 'Invoices')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.invoices.index') }}" class="mb-4">
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
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="INV" {{ ($type ?? '') == 'INV' ? 'selected' : '' }}>INV</option>
                        <option value="DO" {{ ($type ?? '') == 'DO' ? 'selected' : '' }}>DO</option>
                        <option value="CN" {{ ($type ?? '') == 'CN' ? 'selected' : '' }}>CN</option>
                        <option value="SO" {{ ($type ?? '') == 'SO' ? 'selected' : '' }}>SO</option>
                    </select>
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
            <div class="col-md-3">
                <div class="form-group">
                    <label>Reference No</label>
                    <input type="text" name="reference_no" class="form-control" value="{{ $referenceNo ?? '' }}" placeholder="Search by reference number">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Agent No</label>
                    <input type="text" name="agent_no" class="form-control" value="{{ $agentNo ?? '' }}" placeholder="Search by agent number">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Agent No</th>
                    <th>Net Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ $order->reference_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge {{ $order->type == 'INV' ? 'badge-success' : ($order->type == 'CN' ? 'badge-warning' : 'badge-info') }}">
                                {{ $order->type }}
                            </span>
                        </td>
                        <td>{{ $order->customer_code }}</td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ $order->agent_no ?? 'N/A' }}</td>
                        <td class="text-right">RM {{ number_format($order->net_amount ?? 0, 2) }}</td>
                        <td>
                            <a href="{{ route('admin.invoices.show', $order->id) }}" class="btn btn-sm btn-link p-0 text-info">
                                View
                            </a>
                            @if($order->type == 'INV' || $order->type == 'CN')
                                <span class="mx-1">|</span>
                                @php
                                    $eInvoiceRequest = \App\Models\EInvoiceRequest::where('invoice_no', $order->reference_no)
                                        ->where('customer_code', $order->customer_code)
                                        ->first();
                                @endphp
                                @if($eInvoiceRequest)
                                    <a href="{{ route('admin.e-invoice-requests.edit', $eInvoiceRequest->id) }}" 
                                       target="_blank" class="btn btn-sm btn-link p-0 text-warning" title="View E-Invoice Request Details">
                                        E-Invoice (Requested)
                                    </a>
                                @else
                                    <a href="{{ route('e-invoice.form', ['invoice_no' => $order->reference_no, 'customer_code' => $order->customer_code, 'type' => $order->type, 'id' => $order->id]) }}" 
                                       class="btn btn-sm btn-link p-0 text-primary" target="_blank">
                                        E-Invoice
                                    </a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $orders->links() }}
    </div>
@endsection

