@extends('layouts.admin')

@section('title', 'Item Transactions - ' . $item->ITEMNO . ' - Kanesan UBS Backend')

@section('page-title', 'Item Transactions')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}{{ $agentNo ? '?agent_no=' . urlencode($agentNo) : '' }}">Stock Management</a></li>
    <li class="breadcrumb-item active">{{ $item->ITEMNO }} - Transactions</li>
@endsection

@section('card-title', 'Item Transactions: ' . $item->ITEMNO)

@section('card-tools')
    @if($agentNo)
        <a href="{{ route('inventory.stock-management.create', ['agent_no' => $agentNo, 'group' => $item->GROUP ?? '']) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Stock
        </a>
    @endif
    <a href="{{ route('inventory.stock-management') }}{{ $agentNo ? '?agent_no=' . urlencode($agentNo) : '' }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Stock Management
    </a>
@endsection

@section('admin-content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Item Information Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-box"></i> Item Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
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
                @if($agentNo)
                <div class="col-md-2">
                    <strong>Agent:</strong><br>
                    <span class="badge badge-primary">{{ $agentNo }}</span>
                </div>
                @endif
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
            <form method="GET" action="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}" id="filterForm">
                @if($agentNo)
                    <input type="hidden" name="agent_no" value="{{ $agentNo }}">
                @endif
                <!-- Quick Date Range Filters -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="mb-2">Quick Date Range:</label>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDateRange('this_month')">This Month</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDateRange('last_month')">Last Month</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDateRange('last_3_months')">Last 3 Months</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDateRange('this_year')">This Year</button>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label for="dateFrom">Date From</label>
                        <input type="date" class="form-control" name="date_from" id="dateFrom" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="dateTo">Date To</label>
                        <input type="date" class="form-control" name="date_to" id="dateTo" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="transactionTypeFilter">Transaction Type</label>
                        <select class="form-control select2" name="transaction_type" id="transactionTypeFilter" style="width: 100%;">
                            <option value="">All Transaction Types</option>
                            <option value="in" {{ request('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                            <option value="out" {{ request('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                            <option value="invoice_sale" {{ request('transaction_type') == 'invoice_sale' ? 'selected' : '' }}>Invoice Sale</option>
                            <option value="invoice_return" {{ request('transaction_type') == 'invoice_return' ? 'selected' : '' }}>Invoice Return</option>
                            <option value="adjustment" {{ request('transaction_type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <a href="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}{{ $agentNo ? '?agent_no=' . urlencode($agentNo) : '' }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
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
                            @php
                                // Handle both array and object formats
                                $date = is_array($trans) ? ($trans['date'] ?? null) : ($trans->CREATED_ON ?? null);
                                $type = is_array($trans) ? ($trans['type'] ?? null) : ($trans->transaction_type ?? null);
                                $quantity = is_array($trans) ? ($trans['quantity'] ?? 0) : ($trans->quantity ?? 0);
                                $stockBefore = is_array($trans) ? ($trans['stock_before'] ?? null) : ($trans->stock_before ?? null);
                                $stockAfter = is_array($trans) ? ($trans['stock_after'] ?? null) : ($trans->stock_after ?? null);
                                $referenceId = is_array($trans) ? ($trans['reference_id'] ?? null) : ($trans->reference_id ?? null);
                                $notes = is_array($trans) ? ($trans['notes'] ?? null) : ($trans->notes ?? null);
                                $source = is_array($trans) ? ($trans['source'] ?? 'item_transaction') : 'item_transaction';
                            @endphp
                            <tr>
                                <td>{{ $date ? \Carbon\Carbon::parse($date)->format('M d, Y H:i') : 'N/A' }}</td>
                                <td>
                                    @if($type == 'in')
                                        <span class="badge badge-success">Stock In</span>
                                    @elseif($type == 'out')
                                        <span class="badge badge-danger">Stock Out</span>
                                    @elseif($type == 'invoice_sale')
                                        <span class="badge badge-danger">Invoice Sale</span>
                                    @elseif($type == 'invoice_return')
                                        <span class="badge badge-info">Invoice Return</span>
                                    @else
                                        <span class="badge badge-warning">Adjustment</span>
                                    @endif
                                    @if($source == 'order')
                                        <small class="text-muted d-block">(from Order)</small>
                                    @endif
                                </td>
                                <td>
                                    @if($quantity > 0)
                                        <span class="text-success">+{{ number_format($quantity, 2) }}</span>
                                    @else
                                        <span class="text-danger">{{ number_format($quantity, 2) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($stockBefore !== null)
                                        {{ number_format($stockBefore, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($stockAfter !== null)
                                        <strong>{{ number_format($stockAfter, 2) }}</strong>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $referenceId ?? 'N/A' }}</td>
                                <td>{{ $notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No transactions found</p>
                                    @if(request('transaction_type') || request('date_from') || request('date_to'))
                                        <a href="{{ route('inventory.stock-management.item.transactions', $item->ITEMNO) }}{{ $agentNo ? '?agent_no=' . urlencode($agentNo) : '' }}" class="btn btn-sm btn-secondary mt-2">Clear Filters</a>
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
        $('#filterForm').submit();
    });
    
    // Auto-submit form when date inputs change
    $('#dateFrom, #dateTo').on('change', function() {
        $('#filterForm').submit();
    });
});

function setDateRange(range) {
    const today = new Date();
    let dateFrom, dateTo;
    
    switch(range) {
        case 'this_month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            dateFrom = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            dateTo = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'last_3_months':
            dateFrom = new Date(today.getFullYear(), today.getMonth() - 3, 1);
            dateTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'this_year':
            dateFrom = new Date(today.getFullYear(), 0, 1);
            dateTo = new Date(today.getFullYear(), 11, 31);
            break;
        default:
            return;
    }
    
    // Format dates as YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    // Set the date inputs
    $('#dateFrom').val(formatDate(dateFrom));
    $('#dateTo').val(formatDate(dateTo));
    
    // Submit the form
    $('#filterForm').submit();
}
</script>
@endpush

