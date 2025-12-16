@extends('layouts.admin')

@section('title', 'Item Movements - Kanesan UBS Backend')

@section('page-title', 'Item Movements')

@push('styles')
<style>
    .filter-card {
        margin-bottom: 1rem;
    }
    
    .filter-card .card-body {
        padding: 1rem;
    }
    
    #movementsTable {
        font-size: 0.9rem;
    }
    
    #movementsTable thead th {
        font-size: 0.85rem;
        padding: 0.5rem;
        font-weight: 600;
        background-color: #f8f9fa;
    }
    
    #movementsTable tbody td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    .badge-in {
        background-color: #28a745;
        color: white;
    }
    
    .badge-out {
        background-color: #dc3545;
        color: white;
    }
    
    .badge-inv {
        background-color: #007bff;
        color: white;
    }
    
    .badge-do {
        background-color: #17a2b8;
        color: white;
    }
    
    .badge-cn {
        background-color: #ffc107;
        color: black;
    }
</style>
@endpush

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}">Stock Management</a></li>
    <li class="breadcrumb-item active">Item Movements</li>
@endsection

@section('card-title', 'Item Movements')

@section('admin-content')
    <!-- Filters Section -->
    <div class="card filter-card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.item-movements') }}" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ $filters['date_from'] }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="{{ $filters['date_to'] }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="item_group">Item Group</label>
                            <select class="form-control" id="item_group" name="item_group">
                                <option value="">All Groups</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->name }}" 
                                            {{ $filters['item_group'] == $group->name ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="item_no">Item No</label>
                            <input type="text" class="form-control" id="item_no" name="item_no" 
                                   value="{{ $filters['item_no'] }}" placeholder="Enter item number">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="transaction_type">In/Out</label>
                            <select class="form-control" id="transaction_type" name="transaction_type">
                                <option value="">All</option>
                                <option value="in" {{ $filters['transaction_type'] == 'in' ? 'selected' : '' }}>IN</option>
                                <option value="out" {{ $filters['transaction_type'] == 'out' ? 'selected' : '' }}>OUT</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="order_type">Type (INV/DO/CN)</label>
                            <select class="form-control" id="order_type" name="order_type">
                                <option value="">All Types</option>
                                <option value="INV" {{ $filters['order_type'] == 'INV' ? 'selected' : '' }}>INV</option>
                                <option value="DO" {{ $filters['order_type'] == 'DO' ? 'selected' : '' }}>DO</option>
                                <option value="CN" {{ $filters['order_type'] == 'CN' ? 'selected' : '' }}>CN</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="agent_no">Agent</label>
                            <select class="form-control" id="agent_no" name="agent_no">
                                <option value="">All Agents</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->name }}" 
                                            {{ $filters['agent_no'] == $agent->name ? 'selected' : '' }}>
                                        {{ $agent->name }} ({{ $agent->username }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list"></i> Movements 
                <span class="badge badge-light ml-2">{{ $movements->count() }} records</span>
            </h5>
        </div>
        <div class="card-body">
            @if($movements->count() > 0)
                <div class="table-responsive">
                    <table id="movementsTable" class="table table-striped table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item Group</th>
                                <th>Item No</th>
                                <th>Item Name</th>
                                <th>In/Out</th>
                                <th>Qty</th>
                                <th>Type</th>
                                <th>Reference No</th>
                                <th>Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($movement['date'])->format('Y-m-d H:i') }}</td>
                                    <td>{{ $movement['item_group'] }}</td>
                                    <td>{{ $movement['item_no'] }}</td>
                                    <td>{{ $movement['item_name'] }}</td>
                                    <td>
                                        <span class="badge {{ $movement['in_out'] == 'IN' ? 'badge-in' : 'badge-out' }}">
                                            {{ $movement['in_out'] }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($movement['quantity'], 2) }}</td>
                                    <td>
                                        @if($movement['type'] == 'INV')
                                            <span class="badge badge-inv">INV</span>
                                        @elseif($movement['type'] == 'DO')
                                            <span class="badge badge-do">DO</span>
                                        @elseif($movement['type'] == 'CN')
                                            <span class="badge badge-cn">CN</span>
                                        @else
                                            <span class="badge badge-secondary">{{ $movement['type'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $movement['reference_no'] }}</td>
                                    <td>{{ $movement['agent_no'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No movements found for the selected filters.
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#movementsTable').DataTable({
            order: [[0, 'desc']], // Sort by date descending
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
    });
</script>
@endpush
