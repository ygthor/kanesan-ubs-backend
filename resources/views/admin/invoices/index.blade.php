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
                            <button type="button" class="btn btn-sm btn-link p-0 text-info" data-bs-toggle="modal" data-bs-target="#orderModal{{ $order->id }}">
                                View
                            </button>
                            @if($order->type == 'INV' || $order->type == 'CN')
                                <span class="mx-1">|</span>
                                <a href="{{ route('e-invoice.form', ['invoice_no' => $order->reference_no, 'customer_code' => $order->customer_code, 'type' => $order->type, 'id' => $order->id]) }}" 
                                   class="btn btn-sm btn-link p-0 text-primary" target="_blank">
                                    E-Invoice
                                </a>
                            @endif
                        </td>
                    </tr>

                    <!-- Order Detail Modal -->
                    <div class="modal fade" id="orderModal{{ $order->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Order Details - {{ $order->reference_no }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Reference No:</strong> {{ $order->reference_no }}<br>
                                            <strong>Type:</strong> {{ $order->type }}<br>
                                            <strong>Date:</strong> {{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y H:i') }}<br>
                                            <strong>Status:</strong> {{ $order->status ?? 'N/A' }}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Customer Code:</strong> {{ $order->customer_code }}<br>
                                            <strong>Customer Name:</strong> {{ $order->customer_name }}<br>
                                            <strong>Agent No:</strong> {{ $order->agent_no ?? 'N/A' }}
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Gross Amount:</strong> RM {{ number_format($order->gross_amount ?? 0, 2) }}<br>
                                            <strong>Discount:</strong> RM {{ number_format($order->discount ?? 0, 2) }}<br>
                                            <strong>Tax:</strong> RM {{ number_format($order->tax1 ?? 0, 2) }}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Grand Amount:</strong> RM {{ number_format($order->grand_amount ?? 0, 2) }}<br>
                                            <strong>Net Amount:</strong> <span class="font-weight-bold">RM {{ number_format($order->net_amount ?? 0, 2) }}</span>
                                        </div>
                                    </div>
                                    @if($order->remarks)
                                        <hr>
                                        <div class="mb-3">
                                            <strong>Remarks:</strong><br>
                                            {{ $order->remarks }}
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
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

