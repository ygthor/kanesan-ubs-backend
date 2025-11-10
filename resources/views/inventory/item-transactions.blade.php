@extends('layouts.admin')

@section('title', 'Item Transactions - ' . $item->ITEMNO . ' - Kanesan UBS Backend')

@section('page-title', 'Item Transactions')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}">Stock Management</a></li>
    <li class="breadcrumb-item active">{{ $item->ITEMNO }} - Transactions</li>
@endsection

@section('card-title', 'Item Transactions: ' . $item->ITEMNO)

@section('card-tools')
    <a href="{{ route('inventory.stock-management') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Stock Management
    </a>
@endsection

@section('admin-content')
    <!-- Item Information Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-box"></i> Item Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Item Code:</strong><br>
                    <span class="text-primary">{{ $item->ITEMNO }}</span>
                </div>
                <div class="col-md-3">
                    <strong>Description:</strong><br>
                    {{ $item->DESP ?? 'N/A' }}
                </div>
                <div class="col-md-2">
                    <strong>Current Stock:</strong><br>
                    <span class="badge badge-info" style="font-size: 1em;">{{ number_format($currentStock, 2) }}</span>
                </div>
                <div class="col-md-2">
                    <strong>Unit:</strong><br>
                    {{ $item->UNIT ?? 'N/A' }}
                </div>
                <div class="col-md-2">
                    <strong>Price:</strong><br>
                    {{ number_format($item->PRICE ?? 0, 2) }}
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filter Transactions</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}" class="row">
                <div class="col-md-4">
                    <label for="transactionTypeFilter">Transaction Type</label>
                    <select class="form-control select2" name="transaction_type" id="transactionTypeFilter" style="width: 100%;">
                        <option value="">All Transaction Types</option>
                        <option value="in" {{ request('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                        <option value="out" {{ request('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                        <option value="adjustment" {{ request('transaction_type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <a href="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Transaction History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No transactions found</p>
                                    @if(request('transaction_type'))
                                        <a href="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}" class="btn btn-sm btn-secondary mt-2">Clear Filter</a>
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
});
</script>
@endpush

