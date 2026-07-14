@extends('layouts.admin')

@section('title', 'CN2 Credit Notes Report - Kanesan UBS Backend')

@section('page-title', 'CN2 Credit Notes Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">CN2 Credit Notes Report</li>
@endsection

@section('card-title', 'CN2 Credit Notes Report')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.credit-notes') }}" class="mb-4">
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
            <div class="col-md-2">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ ($status ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ ($status ?? '') == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ ($status ?? '') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ ($status ?? '') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
                <a href="{{ route('admin.reports.credit-notes') }}" class="btn btn-secondary">
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
                    <th>Ref No</th>
                    <th>Date & Time</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Linked Invoice</th>
                    <th>Remarks</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($creditNotes ?? [] as $cn)
                    <tr>
                        <td>{{ $cn->reference_no }}</td>
                        <td>{{ $cn->order_date ? $cn->order_date->format('d/m/Y H:i') : 'N/A' }}</td>
                        <td>{{ $cn->customer_code }}</td>
                        <td>{{ $cn->customer_name }}</td>
                        <td>{{ $cn->credit_invoice_no }}</td>
                        <td>{{ $cn->remarks ?? 'N/A' }}</td>
                        <td>{{ $cn->agent_no ?? 'N/A' }}</td>
                        <td>
                            @if($cn->status == 'completed')
                                <span class="badge badge-success">{{ ucfirst($cn->status) }}</span>
                            @elseif($cn->status == 'pending')
                                <span class="badge badge-warning">{{ ucfirst($cn->status) }}</span>
                            @elseif($cn->status == 'cancelled')
                                <span class="badge badge-danger">{{ ucfirst($cn->status) }}</span>
                            @else
                                <span class="badge badge-secondary">{{ ucfirst($cn->status) }}</span>
                            @endif
                        </td>
                        <td class="text-right font-weight-bold" style="text-align: right;">RM {{ number_format($cn->net_amount ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No CN2 credit notes found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($creditNotes) && $creditNotes->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="8" class="text-right" style="text-align: right;">Total Amount:</td>
                        <td class="text-right" style="text-align: right;">RM {{ number_format($creditNotes->sum('net_amount'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
