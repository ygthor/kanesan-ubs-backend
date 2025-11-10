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
    <!-- Tabs for different views -->
    <ul class="nav nav-tabs" id="stockTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="summary-tab" data-bs-toggle="tab" href="#summary" role="tab" aria-controls="summary" aria-selected="true">
                <i class="fas fa-list"></i> Stock Summary
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="transactions-tab" data-bs-toggle="tab" href="#transactions" role="tab" aria-controls="transactions" aria-selected="false">
                <i class="fas fa-history"></i> Transaction History
            </a>
        </li>
    </ul>

    <div class="tab-content" id="stockTabsContent">
        <!-- Stock Summary Tab -->
        <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="mt-3">
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
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewItemTransactions('{{ $item['ITEMNO'] }}')" title="View transactions for this item">
                                            <i class="fas fa-history"></i> View Transactions
                                        </button>
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
            </div>
        </div>

        <!-- Transaction History Tab -->
        <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
            <div class="mt-3">
                <form method="GET" action="{{ route('inventory.stock-management') }}" class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-control select2" name="transaction_type" id="transactionTypeFilter" style="width: 100%;">
                            <option value="">All Transaction Types</option>
                            <option value="in" {{ request('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                            <option value="out" {{ request('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                            <option value="adjustment" {{ request('transaction_type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control select2" name="itemno" id="itemnoFilter" style="width: 100%;" data-placeholder="Filter by Item Code...">
                            <option value=""></option>
                            @foreach($inventory as $item)
                                <option value="{{ $item['ITEMNO'] }}" {{ request('itemno') == $item['ITEMNO'] ? 'selected' : '' }}>
                                    {{ $item['ITEMNO'] }} - {{ $item['DESP'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('inventory.stock-management') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item Code</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Stock Before</th>
                                <th>Stock After</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $trans)
                                <tr>
                                    <td>{{ $trans->CREATED_ON ? \Carbon\Carbon::parse($trans->CREATED_ON)->format('M d, Y H:i') : 'N/A' }}</td>
                                    <td><strong>{{ $trans->ITEMNO }}</strong></td>
                                    <td>
                                        @if($trans->transaction_type == 'in')
                                            <span class="badge badge-success">Stock In</span>
                                        @elseif($trans->transaction_type == 'out')
                                            <span class="badge badge-danger">Stock Out</span>
                                        @else
                                            <span class="badge badge-warning">Adjustment</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($trans->quantity > 0)
                                            <span class="text-success">+{{ number_format($trans->quantity, 2) }}</span>
                                        @else
                                            <span class="text-danger">{{ number_format($trans->quantity, 2) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($trans->stock_before ?? 0, 2) }}</td>
                                    <td><strong>{{ number_format($trans->stock_after ?? 0, 2) }}</strong></td>
                                    <td>{{ $trans->reference_id ?? 'N/A' }}</td>
                                    <td>{{ $trans->notes ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p class="mb-0">No transactions found</p>
                                        @if(request('itemno') || request('transaction_type'))
                                            <a href="{{ route('inventory.stock-management') }}" class="btn btn-sm btn-secondary mt-2">Clear Filters</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($transactions->hasPages())
                    <div class="pagination-wrapper mt-3">
                        <div>
                            Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} results
                        </div>
                        <div>
                            {{ $transactions->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        allowClear: true
    });
    
    // Auto-submit form when transaction type changes
    $('#transactionTypeFilter').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Auto-submit form when item filter changes
    $('#itemnoFilter').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Search functionality
    $('#searchStockInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#stockSummaryTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});

function viewItemTransactions(itemno) {
    // Switch to transactions tab using Bootstrap 5 API
    const transactionsTab = document.querySelector('#transactions-tab');
    const tab = new bootstrap.Tab(transactionsTab);
    tab.show();
    
    // Set the item filter and submit after a short delay to ensure tab is shown
    setTimeout(function() {
        $('#itemnoFilter').val(itemno).trigger('change');
    }, 300);
}
</script>
@endpush
