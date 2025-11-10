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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockSummaryBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading stock data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transaction History Tab -->
        <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
            <div class="mt-3">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-control" id="transactionTypeFilter">
                            <option value="">All Transaction Types</option>
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="itemCodeFilter" placeholder="Filter by Item Code...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="transactionsTable">
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
                        <tbody id="transactionsBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading transactions...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
                <form id="stockInForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="stockInItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="stockInItemCode" required>
                        </div>
                        <div class="form-group">
                            <label for="stockInQuantity">Quantity *</label>
                            <input type="number" class="form-control" id="stockInQuantity" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="stockInNotes">Notes</label>
                            <textarea class="form-control" id="stockInNotes" rows="3"></textarea>
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
                <form id="stockOutForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="stockOutItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="stockOutItemCode" required>
                        </div>
                        <div class="form-group">
                            <label for="stockOutQuantity">Quantity *</label>
                            <input type="number" class="form-control" id="stockOutQuantity" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="stockOutNotes">Notes</label>
                            <textarea class="form-control" id="stockOutNotes" rows="3"></textarea>
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
                <form id="stockAdjustmentForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="adjustmentItemCode">Item Code *</label>
                            <input type="text" class="form-control" id="adjustmentItemCode" required>
                        </div>
                        <div class="form-group">
                            <label for="adjustmentQuantity">Quantity Adjustment *</label>
                            <input type="number" class="form-control" id="adjustmentQuantity" step="0.01" required>
                            <small class="form-text text-muted">Positive value to increase, negative to decrease</small>
                        </div>
                        <div class="form-group">
                            <label for="adjustmentNotes">Notes *</label>
                            <textarea class="form-control" id="adjustmentNotes" rows="3" required></textarea>
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
    const apiBaseUrl = '/api/inventory';
    const token = '{{ csrf_token() }}';

    // Load stock summary
    function loadStockSummary() {
        $.ajax({
            url: apiBaseUrl + '/summary',
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                'X-CSRF-TOKEN': token
            },
            success: function(response) {
                if (response.error === 0 && response.data) {
                    renderStockSummary(response.data);
                } else {
                    $('#stockSummaryBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading stock data</td></tr>');
                }
            },
            error: function() {
                $('#stockSummaryBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading stock data</td></tr>');
            }
        });
    }

    function renderStockSummary(data) {
        let html = '';
        if (data.length === 0) {
            html = '<tr><td colspan="6" class="text-center text-muted">No items found</td></tr>';
        } else {
            data.forEach(function(item) {
                html += `
                    <tr>
                        <td>${item.ITEMNO || 'N/A'}</td>
                        <td>${item.DESP || 'N/A'}</td>
                        <td><strong>${item.current_stock || 0}</strong></td>
                        <td>${item.UNIT || 'N/A'}</td>
                        <td>${item.PRICE || '0.00'}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewItemStock('${item.ITEMNO}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#stockSummaryBody').html(html);
    }

    // Load transactions
    function loadTransactions() {
        $.ajax({
            url: apiBaseUrl + '/transactions',
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                'X-CSRF-TOKEN': token
            },
            success: function(response) {
                if (response.error === 0 && response.data) {
                    renderTransactions(response.data.data || response.data);
                } else {
                    $('#transactionsBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading transactions</td></tr>');
                }
            },
            error: function() {
                $('#transactionsBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading transactions</td></tr>');
            }
        });
    }

    function renderTransactions(data) {
        let html = '';
        if (data.length === 0) {
            html = '<tr><td colspan="8" class="text-center text-muted">No transactions found</td></tr>';
        } else {
            data.forEach(function(trans) {
                const typeBadge = trans.transaction_type === 'in' ? 'success' : 
                                 trans.transaction_type === 'out' ? 'danger' : 'warning';
                const typeLabel = trans.transaction_type === 'in' ? 'Stock In' : 
                                 trans.transaction_type === 'out' ? 'Stock Out' : 'Adjustment';
                html += `
                    <tr>
                        <td>${trans.CREATED_ON ? new Date(trans.CREATED_ON).toLocaleDateString() : 'N/A'}</td>
                        <td>${trans.ITEMNO || 'N/A'}</td>
                        <td><span class="badge badge-${typeBadge}">${typeLabel}</span></td>
                        <td>${trans.quantity || 0}</td>
                        <td>${trans.stock_before || 0}</td>
                        <td>${trans.stock_after || 0}</td>
                        <td>${trans.reference_id || 'N/A'}</td>
                        <td>${trans.notes || '-'}</td>
                    </tr>
                `;
            });
        }
        $('#transactionsBody').html(html);
    }

    // Stock In Form
    $('#stockInForm').on('submit', function(e) {
        e.preventDefault();
        const data = {
            ITEMNO: $('#stockInItemCode').val(),
            quantity: $('#stockInQuantity').val(),
            notes: $('#stockInNotes').val()
        };

        $.ajax({
            url: apiBaseUrl + '/stock-in',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.error === 0) {
                    alert('Stock added successfully!');
                    $('#stockInModal').modal('hide');
                    $('#stockInForm')[0].reset();
                    loadStockSummary();
                    loadTransactions();
                } else {
                    alert('Error: ' + (response.message || 'Failed to add stock'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to add stock'));
            }
        });
    });

    // Stock Out Form
    $('#stockOutForm').on('submit', function(e) {
        e.preventDefault();
        const data = {
            ITEMNO: $('#stockOutItemCode').val(),
            quantity: $('#stockOutQuantity').val(),
            notes: $('#stockOutNotes').val()
        };

        $.ajax({
            url: apiBaseUrl + '/stock-out',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.error === 0) {
                    alert('Stock removed successfully!');
                    $('#stockOutModal').modal('hide');
                    $('#stockOutForm')[0].reset();
                    loadStockSummary();
                    loadTransactions();
                } else {
                    alert('Error: ' + (response.message || 'Failed to remove stock'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to remove stock'));
            }
        });
    });

    // Stock Adjustment Form
    $('#stockAdjustmentForm').on('submit', function(e) {
        e.preventDefault();
        const data = {
            ITEMNO: $('#adjustmentItemCode').val(),
            quantity: $('#adjustmentQuantity').val(),
            notes: $('#adjustmentNotes').val()
        };

        $.ajax({
            url: apiBaseUrl + '/stock-adjustment',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.error === 0) {
                    alert('Stock adjusted successfully!');
                    $('#stockAdjustmentModal').modal('hide');
                    $('#stockAdjustmentForm')[0].reset();
                    loadStockSummary();
                    loadTransactions();
                } else {
                    alert('Error: ' + (response.message || 'Failed to adjust stock'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to adjust stock'));
            }
        });
    });

    // Search functionality
    $('#searchStockInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#stockSummaryTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Load data on tab switch
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'transactions-tab') {
            loadTransactions();
        } else if (e.target.id === 'summary-tab') {
            loadStockSummary();
        }
    });

    // Initial load
    loadStockSummary();
});

function viewItemStock(itemno) {
    alert('Viewing stock for item: ' + itemno);
    // You can implement a detailed view modal here
}
</script>
@endpush

