@extends('layouts.admin')

@section('title', 'Receipt Report - Kanesan UBS Backend')

@section('page-title', 'Receipt Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Receipt Report</li>
@endsection

@section('card-title', 'Receipt Report')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.receipts') }}" class="mb-4">
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
                    <label>Payment Type</label>
                    <select name="payment_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Cash" {{ ($paymentType ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
                        <option value="Card" {{ ($paymentType ?? '') == 'Card' ? 'selected' : '' }}>Card</option>
                        <option value="Cheque" {{ ($paymentType ?? '') == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="Online Transfer" {{ ($paymentType ?? '') == 'Online Transfer' ? 'selected' : '' }}>Online Transfer</option>
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
                <a href="{{ route('admin.reports.receipts') }}" class="btn btn-secondary">
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
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Payment Type</th>
                    <th>Paid Amount</th>
                    <th>Reference No</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts ?? [] as $receipt)
                    <tr>
                        <td>{{ $receipt->receipt_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</td>
                        <td>{{ $receipt->customer->customer_code ?? 'N/A' }}</td>
                        <td>{{ $receipt->customer->name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge badge-info">{{ $receipt->payment_type }}</span>
                        </td>
                        <td class="text-right">RM {{ number_format($receipt->paid_amount ?? 0, 2) }}</td>
                        <td>{{ $receipt->payment_reference_no ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No receipts found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($receipts) && $receipts->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="5" class="text-right">Total:</td>
                        <td class="text-right">RM {{ number_format($receipts->sum('paid_amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
