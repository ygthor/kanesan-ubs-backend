@extends('layouts.admin')

@section('title', 'Stock Management - Kanesan UBS Backend')

@section('page-title', 'Stock Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Management</li>
@endsection

@section('card-title', 'Stock Management')

@section('card-tools')
    <a href="{{ route('inventory.stock-management.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Create Stock Transaction
    </a>
@endsection

@section('admin-content')
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control" id="searchStockInput" placeholder="Search items by code or description...">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover" id="stockSummaryTable">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>Current Stock</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventory as $item)
                    <tr>
                        <td><strong>{{ $item['ITEMNO'] }}</strong></td>
                        <td>{{ $item['DESP'] ?? 'N/A' }}</td>
                        <td><strong>{{ number_format($item['current_stock'], 2) }}</strong></td>
                        <td>{{ $item['UNIT'] ?? 'N/A' }}</td>
                        <td>{{ number_format($item['PRICE'] ?? 0, 2) }}</td>
                        <td>
                            <a href="{{ route('inventory.stock-management.item.transactions', $item['ITEMNO']) }}" class="btn btn-sm btn-info" title="View transactions for this item">
                                <i class="fas fa-history"></i> View Transactions
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No items found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Search functionality
    $('#searchStockInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#stockSummaryTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>
@endpush
