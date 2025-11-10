@extends('layouts.admin')

@section('title', 'Stock Management - Kanesan UBS Backend')

@section('page-title', 'Stock Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Management</li>
@endsection

@section('card-title', 'Stock Management')

@section('card-tools')
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#stockTransactionModal">
        <i class="fas fa-plus"></i> Create Stock Transaction
    </button>
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

    <!-- Tabs for different views -->
    <ul class="nav nav-tabs" id="stockTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="summary-tab" data-toggle="tab" href="#summary" role="tab" aria-controls="summary" aria-selected="true">
                <i class="fas fa-list"></i> Stock Summary
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="transactions-tab" data-toggle="tab" href="#transactions" role="tab" aria-controls="transactions" aria-selected="false">
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventory as $item)
                                <tr style="cursor: pointer;" onclick="viewItemTransactions('{{ $item['ITEMNO'] }}')" title="Click to view transactions for this item">
                                    <td><strong>{{ $item['ITEMNO'] }}</strong></td>
                                    <td>{{ $item['DESP'] ?? 'N/A' }}</td>
                                    <td><strong>{{ number_format($item['current_stock'], 2) }}</strong></td>
                                    <td>{{ $item['UNIT'] ?? 'N/A' }}</td>
                                    <td>{{ number_format($item['PRICE'] ?? 0, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No items found</td>
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
                        <select class="form-control" name="transaction_type" onchange="this.form.submit()">
                            <option value="">All Transaction Types</option>
                            <option value="in" {{ request('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                            <option value="out" {{ request('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                            <option value="adjustment" {{ request('transaction_type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="itemno" placeholder="Filter by Item Code..." value="{{ request('itemno') }}">
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

    <!-- Combined Stock Transaction Modal -->
    <div class="modal fade" id="stockTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Stock Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('inventory.stock-management.store') }}" id="stockTransactionForm">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="transactionType">Transaction Type *</label>
                            <select class="form-control" id="transactionType" name="transaction_type" required onchange="updateTransactionForm()">
                                <option value="">Select Type</option>
                                <option value="in">Stock In</option>
                                <option value="out">Stock Out</option>
                                <option value="adjustment">Stock Adjustment</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="transactionItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="transactionItemCode" name="ITEMNO" required>
                        </div>
                        <div class="form-group">
                            <label for="transactionQuantity">Quantity *</label>
                            <input type="number" class="form-control" id="transactionQuantity" name="quantity" step="0.01" min="0.01" required>
                            <small class="form-text text-muted" id="quantityHelp">
                                Enter quantity to add/remove
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="transactionNotes">Notes <span id="notesRequired" style="display:none;">*</span></label>
                            <textarea class="form-control" id="transactionNotes" name="notes" rows="3"></textarea>
                            <small class="form-text text-muted" id="notesHelp">
                                Optional notes for this transaction
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitButton">Create Transaction</button>
                    </div>
                </form>
            </div>
        </div>
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
    
    // Reset form when modal is closed
    $('#stockTransactionModal').on('hidden.bs.modal', function () {
        $('#stockTransactionForm')[0].reset();
        updateTransactionForm();
    });
});

function updateTransactionForm() {
    const type = $('#transactionType').val();
    const quantityInput = $('#transactionQuantity');
    const notesInput = $('#transactionNotes');
    const notesRequired = $('#notesRequired');
    const quantityHelp = $('#quantityHelp');
    const notesHelp = $('#notesHelp');
    const submitButton = $('#submitButton');
    
    if (type === 'in') {
        quantityInput.attr('min', '0.01');
        quantityHelp.text('Enter quantity to add to stock');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-warning btn-info').addClass('btn-success').text('Add Stock');
    } else if (type === 'out') {
        quantityInput.attr('min', '0.01');
        quantityHelp.text('Enter quantity to remove from stock');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-success btn-info').addClass('btn-warning').text('Remove Stock');
    } else if (type === 'adjustment') {
        quantityInput.removeAttr('min');
        quantityHelp.text('Enter positive value to increase, negative to decrease stock');
        notesInput.prop('required', true);
        notesRequired.show();
        notesHelp.text('Reason for adjustment is required');
        submitButton.removeClass('btn-success btn-warning').addClass('btn-info').text('Adjust Stock');
    } else {
        quantityHelp.text('Enter quantity to add/remove');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-success btn-warning btn-info').addClass('btn-primary').text('Create Transaction');
    }
}

function viewItemTransactions(itemno) {
    // Switch to transactions tab
    $('#transactions-tab').tab('show');
    
    // Set the item filter and submit
    $('input[name="itemno"]').val(itemno);
    $('form[method="GET"]').submit();
}
</script>
@endpush
