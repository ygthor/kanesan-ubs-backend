@extends('layouts.admin')

@section('title', 'Stock Management - Kanesan UBS Backend')

@section('page-title', 'Stock Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Management</li>
@endsection

@section('card-title', 'Stock Management')

@section('card-tools')
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#stockInModal">
        <i class="fas fa-plus"></i> Stock In
    </button>
    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#stockOutModal">
        <i class="fas fa-minus"></i> Stock Out
    </button>
    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#stockAdjustmentModal">
        <i class="fas fa-edit"></i> Stock Adjustment
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
                                <tr>
                                    <td>{{ $item['ITEMNO'] }}</td>
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
                                    <td>{{ $trans->ITEMNO }}</td>
                                    <td>
                                        @if($trans->transaction_type == 'in')
                                            <span class="badge badge-success">Stock In</span>
                                        @elseif($trans->transaction_type == 'out')
                                            <span class="badge badge-danger">Stock Out</span>
                                        @else
                                            <span class="badge badge-warning">Adjustment</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($trans->quantity, 2) }}</td>
                                    <td>{{ number_format($trans->stock_before ?? 0, 2) }}</td>
                                    <td>{{ number_format($trans->stock_after ?? 0, 2) }}</td>
                                    <td>{{ $trans->reference_id ?? 'N/A' }}</td>
                                    <td>{{ $trans->notes ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No transactions found</td>
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

    <!-- Stock In Modal -->
    <div class="modal fade" id="stockInModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Stock In</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('inventory.stock-management.stock-in') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="stockInItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="stockInItemCode" name="ITEMNO" required>
                        </div>
                        <div class="form-group">
                            <label for="stockInQuantity">Quantity *</label>
                            <input type="number" class="form-control" id="stockInQuantity" name="quantity" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="stockInNotes">Notes</label>
                            <textarea class="form-control" id="stockInNotes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Out Modal -->
    <div class="modal fade" id="stockOutModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Stock Out</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('inventory.stock-management.stock-out') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="stockOutItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="stockOutItemCode" name="ITEMNO" required>
                        </div>
                        <div class="form-group">
                            <label for="stockOutQuantity">Quantity *</label>
                            <input type="number" class="form-control" id="stockOutQuantity" name="quantity" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="stockOutNotes">Notes</label>
                            <textarea class="form-control" id="stockOutNotes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Remove Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockAdjustmentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Stock Adjustment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('inventory.stock-management.stock-adjustment') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="adjustmentItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="adjustmentItemCode" name="ITEMNO" required>
                        </div>
                        <div class="form-group">
                            <label for="adjustmentQuantity">Quantity Adjustment *</label>
                            <input type="number" class="form-control" id="adjustmentQuantity" name="quantity" step="0.01" required>
                            <small class="form-text text-muted">Positive value to increase, negative to decrease</small>
                        </div>
                        <div class="form-group">
                            <label for="adjustmentNotes">Notes *</label>
                            <textarea class="form-control" id="adjustmentNotes" name="notes" rows="3" required></textarea>
                            <small class="form-text text-muted">Reason for adjustment is required</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Adjust Stock</button>
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
});
</script>
@endpush
