@extends('layouts.admin')

@section('title', 'Transaction Report - Kanesan UBS Backend')

@section('page-title', 'Transaction Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Transaction Report</li>
@endsection

@section('card-title', 'Transaction Report (All Orders)')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.transactions') }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? date('Y-m-01') }}" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? date('Y-m-d') }}" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="INV" {{ ($type ?? '') == 'INV' ? 'selected' : '' }}>INV</option>
                        <option value="CN" {{ ($type ?? '') == 'CN' ? 'selected' : '' }}>CN</option>
                        <option value="SO" {{ ($type ?? '') == 'SO' ? 'selected' : '' }}>SO</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach($agents ?? [] as $agent)
                            <option value="{{ $agent->name }}" {{ ($agentNo ?? '') == $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Customer Search</label>
                    <input type="text" name="customer_search" class="form-control" value="{{ $customerSearch ?? '' }}" placeholder="Code or Name">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.reports.transactions') }}" class="btn btn-secondary">
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
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Agent No</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders ?? [] as $order)
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
                            <span class="badge badge-{{ $order->status == 'completed' ? 'success' : ($order->status == 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($order->status ?? 'N/A') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No orders found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($orders) && $orders->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total:</td>
                        <td class="text-right">RM {{ number_format($orders->sum('net_amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
