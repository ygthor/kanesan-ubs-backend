@extends('layouts.admin')

@section('title', 'Stock Data Validation - Kanesan UBS Backend')

@section('page-title', 'Stock Data Validation')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}">Stock Management</a></li>
    <li class="breadcrumb-item active">Stock Data Validation</li>
@endsection

@section('card-title', 'Agent Item Stock Validation')

@section('card-tools')
    <a href="{{ route('inventory.stock-management') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Stock Management
    </a>
@endsection

@section('admin-content')
    @if(empty($negativeStocks))
        <div class="alert alert-success py-4 text-center">
            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
            <h4>All Good!</h4>
            <p class="mb-0">No stock data problems found in the system. All agent item stocks are non-negative.</p>
        </div>
    @else
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Found <strong>{{ count($negativeStocks) }}</strong> instance(s) where an agent's stock for an item is less than zero.
        </div>

        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0"><i class="fas fa-bug"></i> Negative Stock Balances</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Agent Name</th>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Group</th>
                                <th class="text-end text-right" style="text-align: right;">Stock In + Return</th>
                                <th class="text-end text-right" style="text-align: right;">Stock Out</th>
                                <th class="text-end text-right" style="text-align: right;">Calculated Stock</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($negativeStocks as $entry)
                                <tr>
                                    <td>
                                        <strong>{{ $entry['agent_name'] }}</strong> 
                                        @if($entry['agent_username'] && $entry['agent_name'] != $entry['agent_username'])
                                            <span class="text-muted">({{ $entry['agent_username'] }})</span>
                                        @endif
                                    </td>
                                    <td><code class="text-dark">{{ $entry['ITEMNO'] }}</code></td>
                                    <td>{{ $entry['DESP'] }}</td>
                                    <td><span class="badge badge-secondary">{{ $entry['GROUP'] }}</span></td>
                                    <td align="right" class="text-end text-right text-success">{{ number_format($entry['stock_in'] + $entry['return_good'], 2) }}</td>
                                    <td align="right" class="text-end text-right text-danger">{{ number_format($entry['stock_out'], 2) }}</td>
                                    <td align="right" class="text-end text-right text-danger font-weight-bold" style="font-weight: bold;">{{ number_format($entry['real_stock'], 2) }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('inventory.stock-management.item.transactions', $entry['ITEMNO']) }}?agent_no={{ urlencode($entry['agent_name']) }}" 
                                           class="btn btn-sm btn-primary" 
                                           target="_blank"
                                           title="View Stock Transactions">
                                            <i class="fas fa-history"></i> View Transactions
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
