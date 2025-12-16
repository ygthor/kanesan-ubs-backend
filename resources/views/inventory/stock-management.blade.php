@extends('layouts.admin')

@section('title', 'Stock Management - Kanesan UBS Backend')

@section('page-title', 'Stock Management')

@push('styles')
<style>
    /* Compact styling for Stock Management page */
    #stockSummaryTable,
    #openingBalanceTable {
        font-size: 0.85rem;
    }
    
    #stockSummaryTable thead th,
    #openingBalanceTable thead th {
        font-size: 0.8rem;
        padding: 0.4rem 0.5rem;
        font-weight: 600;
    }
    
    #stockSummaryTable tbody td,
    #openingBalanceTable tbody td {
        padding: 0.35rem 0.5rem;
        font-size: 0.85rem;
    }
    
    #stockSummaryTable .btn-sm,
    #openingBalanceTable .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    
    /* Reduce card header sizes */
    .card-header h5 {
        font-size: 1rem;
        margin-bottom: 0;
    }
    
    /* Reduce form label sizes */
    .form-group label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }
    
    /* Reduce alert font size */
    .alert {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    /* Reduce button sizes slightly */
    .btn {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
    }
    
    /* Reduce table row height */
    #stockSummaryTable tbody tr,
    #openingBalanceTable tbody tr {
        line-height: 1.4;
    }
    
    /* Compact DataTables controls */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        font-size: 0.8rem;
    }
    
    /* Reduce modal font sizes */
    #addOpeningBalanceModal .modal-body {
        font-size: 0.9rem;
    }
    
    #addOpeningBalanceModal .form-group label {
        font-size: 0.875rem;
    }
    
    /* Editable quantity styles */
    .quantity-editable {
        display: inline-flex;
        align-items: center;
    }
    
    .quantity-view-mode,
    .quantity-edit-mode {
        display: inline-flex;
        align-items: center;
    }
    
    .quantity-display {
        font-weight: bold;
    }
    
    .quantity-input {
        width: 100px !important;
        display: inline-block !important;
    }
    
    .btn-edit-quantity,
    .btn-save-quantity,
    .btn-cancel-quantity {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
</style>
@endpush

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Management</li>
@endsection

@section('card-title', 'Stock Management')

@section('admin-content')
    <!-- Agent Selection Section -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Select Agent</h5>
        </div>
        <div class="card-body p-1">
            <form method="GET" action="{{ route('inventory.stock-management') }}" id="agentSelectionForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="agentSelect">Agent</label>
                            <select class="form-control select2" id="agentSelect" name="agent_no" style="width: 100%;">
                                <option value="">Select Agent</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->name ?? $agent->username }}" 
                                            {{ ($selectedAgent == ($agent->name ?? $agent->username)) ? 'selected' : '' }}>
                                        {{ $agent->name ?? $agent->username }} 
                                        @if($agent->username && $agent->name != $agent->username)
                                            ({{ $agent->username }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if($selectedAgent)
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOpeningBalanceModal">
                                    <i class="fas fa-plus"></i> Create Opening Balance
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($selectedAgent)
        <!-- Search & Filter Section -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="fas fa-search"></i> Search & Filter</h5>
            </div>
            <div class="card-body p-1">
                <form id="searchForm">
                    <input type="hidden" name="agent_no" value="{{ $selectedAgent }}">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="groupFilter">Filter by Group</label>
                                <select class="form-control select2" id="groupFilter" name="group" style="width: 100%;">
                                    <option value="">All Groups</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->name }}" 
                                                {{ request('group') == $group->name ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemSearch">Search by Item Code or Name</label>
                                <input type="text" class="form-control" id="itemSearch" name="search" 
                                       value="{{ request('search') }}" 
                                       placeholder="Enter item code or description...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-info btn-block" id="clearFiltersBtn">
                                        <i class="fas fa-times"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs" id="stockTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="opening-balance-tab" data-toggle="tab" data-bs-toggle="tab" href="#opening-balance" role="tab" aria-controls="opening-balance" aria-selected="true">
                            <i class="fas fa-database"></i> Opening Balance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="transactions-tab" data-toggle="tab" data-bs-toggle="tab" href="#transactions" role="tab" aria-controls="transactions" aria-selected="false">
                            <i class="fas fa-list"></i> Transactions
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-1">
                <div class="tab-content" id="stockTabsContent">
                    <!-- Opening Balance Tab -->
                    <div class="tab-pane fade show active" id="opening-balance" role="tabpanel" aria-labelledby="opening-balance-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="openingBalanceTable">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Description</th>
                                        <th>Group</th>
                                        <th>Quantity</th>
                                        <th>Date Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($openingBalances as $balance)
                                        <tr id="balance-row-{{ $balance->id }}">
                                            <td><strong>{{ $balance->ITEMNO }}</strong></td>
                                            <td>{{ $balance->item->DESP ?? 'N/A' }}</td>
                                            <td>{{ $balance->item->GROUP ?? 'N/A' }}</td>
                                            <td>
                                                <div class="quantity-editable" data-balance-id="{{ $balance->id }}">
                                                    <div class="quantity-view-mode">
                                                        <span class="quantity-display text-success" style="font-weight: bold;">
                                                            {{ number_format($balance->quantity, 2) }}
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-primary btn-edit-quantity" style="margin-left: 5px;">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    </div>
                                                    <div class="quantity-edit-mode d-none">
                                                        <input type="number" 
                                                               class="form-control form-control-sm quantity-input" 
                                                               value="{{ $balance->quantity }}" 
                                                               step="0.01" 
                                                               min="0.01"
                                                               style="width: 100px; display: inline-block;">
                                                        <button type="button" class="btn btn-sm btn-success btn-save-quantity" style="margin-left: 5px;">
                                                            <i class="fas fa-check"></i> Save
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-secondary btn-cancel-quantity" style="margin-left: 2px;">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $balance->CREATED_ON ? \Carbon\Carbon::parse($balance->CREATED_ON)->format('M d, Y H:i') : 'N/A' }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteOpeningBalance({{ $balance->id }})">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                                <p class="mb-0">No opening balance records found for this agent.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                        <div class="alert alert-info mb-3" id="transactionsAlert">
                            <i class="fas fa-info-circle"></i> Showing stock for agent: <strong>{{ $selectedAgent }}</strong>
                            @if($inventory->count() > 0)
                                - <strong>{{ $inventory->count() }}</strong> item(s) found
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="stockSummaryTable">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Description</th>
                                        <th>Group</th>
                                        <th>Current Stock</th>
                                        <th>Stock In</th>
                                        <th>Stock Out</th>
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
                                            <td>{{ $item['GROUP'] ?? 'N/A' }}</td>
                                            <td data-sort="{{ $item['current_stock'] }}">
                                                <strong class="{{ $item['current_stock'] < 0 ? 'text-danger' : ($item['current_stock'] == 0 ? 'text-warning' : 'text-success') }}">
                                                    {{ number_format($item['current_stock'], 2) }}
                                                </strong>
                                            </td>
                                            <td data-sort="{{ $item['stockIn'] ?? 0 }}" class="text-success">
                                                {{ number_format($item['stockIn'] ?? 0, 2) }}
                                            </td>
                                            <td data-sort="{{ $item['stockOut'] ?? 0 }}" class="text-danger">
                                                {{ number_format($item['stockOut'] ?? 0, 2) }}
                                            </td>
                                            <td>{{ $item['UNIT'] ?? 'N/A' }}</td>
                                            <td data-sort="{{ $item['PRICE'] ?? 0 }}">{{ number_format($item['PRICE'] ?? 0, 2) }}</td>
                                            <td>
                                                <a href="{{ route('inventory.stock-management.item.transactions', $item['ITEMNO']) }}?agent_no={{ urlencode($selectedAgent) }}" 
                                                   class="btn btn-sm btn-info" 
                                                   title="View transactions for this item">
                                                    <i class="fas fa-history"></i> View Transactions
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                @if(request('group') || request('search'))
                                                    No items found matching your search criteria.
                                                    <a href="{{ route('inventory.stock-management', ['agent_no' => $selectedAgent]) }}" class="alert-link">Clear filters</a>
                                                @else
                                                    No items with transactions found for this agent.
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Please select an agent to view stock management.
        </div>
    @endif

    <!-- Add Opening Balance Modal -->
    <div class="modal fade" id="addOpeningBalanceModal" tabindex="-1" role="dialog" aria-labelledby="addOpeningBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOpeningBalanceModalLabel">Add Opening Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addOpeningBalanceForm">
                    <div class="modal-body">
                        <input type="hidden" name="agent_no" value="{{ $selectedAgent }}">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modalGroupSelect" class="required">Group *</label>
                                    <select class="form-control select2" id="modalGroupSelect" name="group" required style="width: 100%;">
                                        <option value="">Select Group</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->name }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Select a group first to load items</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modalItemSelect" class="required">Item *</label>
                                    <select class="form-control select2" id="modalItemSelect" name="ITEMNO" required style="width: 100%;" disabled>
                                        <option value="">Select Group first</option>
                                    </select>
                                    <small class="form-text text-muted" id="modalItemHelpText">Select a group to load items</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="modalQuantity" class="required">Quantity *</label>
                            <input type="number" class="form-control" id="modalQuantity" name="quantity" 
                                   step="0.01" min="0.01" required>
                            <small class="form-text text-muted">Enter the opening balance quantity</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Opening Balance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Store DataTable instance
    var stockSummaryTable = null;
    
    // Initialize Bootstrap tabs - compatible with Bootstrap 5
    $('#stockTabs a[data-bs-toggle="tab"]').on('click', function (e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        // Remove active class from all tabs and panes
        $('#stockTabs a').removeClass('active');
        $('.tab-pane').removeClass('show active');
        
        // Add active class to clicked tab
        $(this).addClass('active');
        
        // Show corresponding pane
        $(target).addClass('show active');
        
        // If switching to Transactions tab, initialize or adjust DataTable
        if (target === '#transactions' && $('#stockSummaryTable').length) {
            setTimeout(function() {
                if (stockSummaryTable === null) {
                    // Initialize DataTable if not already initialized
                    @if($selectedAgent && $inventory->count() > 0)
                    stockSummaryTable = $('#stockSummaryTable').DataTable({
                        responsive: true,
                        pageLength: 50,
                        lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
                        order: [[0, 'asc']], // Sort by Item Code by default
                        columnDefs: [
                            {
                                targets: [8], // Actions column
                                orderable: false,
                                searchable: false
                            },
                            {
                                targets: [3, 4, 5, 7], // Current Stock, Stock In, Stock Out, and Price columns
                                orderDataType: 'dom-data-sort-num' // Use custom sorting function
                            }
                        ],
                        language: {
                            search: "Search items:",
                            lengthMenu: "Show _MENU_ items per page",
                            info: "Showing _START_ to _END_ of _TOTAL_ items",
                            infoEmpty: "No items to show",
                            infoFiltered: "(filtered from _MAX_ total items)",
                            zeroRecords: "No matching items found",
                            paginate: {
                                first: "First",
                                last: "Last",
                                next: "Next",
                                previous: "Previous"
                            }
                        },
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                    });
                    @endif
                } else {
                    // Adjust columns if already initialized
                    stockSummaryTable.columns.adjust().responsive.recalc();
                }
            }, 100);
        }
    });
    
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        allowClear: true
    });
    
    // Manual modal trigger for Create Opening Balance button (Bootstrap 5 compatibility)
    $('button[data-bs-target="#addOpeningBalanceModal"]').on('click', function(e) {
        e.preventDefault();
        var modalElement = document.getElementById('addOpeningBalanceModal');
        if (modalElement) {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    });
    
    // Auto-submit agent selection form when agent changes
    $('#agentSelect').on('change', function() {
        $('#agentSelectionForm').submit();
    });
    
    // Filter inventory without page refresh
    var filterTimeout;
    var isFiltering = false;
    
    function filterInventory() {
        if (isFiltering) return;
        
        var agentNo = $('#agentSelect').val();
        if (!agentNo) return;
        
        var group = $('#groupFilter').val();
        var search = $('#itemSearch').val();
        
        isFiltering = true;
        var tbody = $('#stockSummaryTable tbody');
        var alertInfo = $('#transactionsAlert');
        
        // Show loading state
        tbody.html('<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
        
        $.ajax({
            url: '{{ route("inventory.stock-management") }}',
            method: 'GET',
            data: {
                agent_no: agentNo,
                group: group,
                search: search
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                isFiltering = false;
                
                if (response.success && response.data) {
                    var items = response.data;
                    var html = '';
                    
                    if (items.length > 0) {
                        items.forEach(function(item) {
                            var stockClass = item.current_stock < 0 ? 'text-danger' : (item.current_stock == 0 ? 'text-warning' : 'text-success');
                            html += '<tr>';
                            html += '<td><strong>' + (item.ITEMNO || 'N/A') + '</strong></td>';
                            html += '<td>' + (item.DESP || 'N/A') + '</td>';
                            html += '<td>' + (item.GROUP || 'N/A') + '</td>';
                            html += '<td data-sort="' + (item.current_stock || 0) + '">';
                            html += '<strong class="' + stockClass + '">' + parseFloat(item.current_stock || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>';
                            html += '</td>';
                            html += '<td data-sort="' + (item.stockIn || 0) + '" class="text-success">' + parseFloat(item.stockIn || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                            html += '<td data-sort="' + (item.stockOut || 0) + '" class="text-danger">' + parseFloat(item.stockOut || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                            html += '<td>' + (item.UNIT || 'N/A') + '</td>';
                            html += '<td data-sort="' + (item.PRICE || 0) + '">' + parseFloat(item.PRICE || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                            html += '<td>';
                            html += '<a href="/inventory/stock-management/item/' + encodeURIComponent(item.ITEMNO) + '?agent_no=' + encodeURIComponent(agentNo) + '" class="btn btn-sm btn-info" title="View transactions for this item">';
                            html += '<i class="fas fa-history"></i> View Transactions';
                            html += '</a>';
                            html += '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html = '<tr><td colspan="9" class="text-center text-muted">';
                        if (group || search) {
                            html += 'No items found matching your search criteria.';
                        } else {
                            html += 'No items with transactions found for this agent.';
                        }
                        html += '</td></tr>';
                    }
                    
                    tbody.html(html);
                    
                    // Update alert info
                    var countText = items.length > 0 ? ' - <strong>' + items.length + '</strong> item(s) found' : '';
                    alertInfo.html('<i class="fas fa-info-circle"></i> Showing stock for agent: <strong>' + agentNo + '</strong>' + countText);
                    
                    // Reinitialize DataTable if it exists
                    if (stockSummaryTable) {
                        stockSummaryTable.destroy();
                        stockSummaryTable = null;
                    }
                    
                    // Reinitialize DataTable after a short delay
                    setTimeout(function() {
                        if ($('#transactions').hasClass('active') && items.length > 0) {
                            stockSummaryTable = $('#stockSummaryTable').DataTable({
                                responsive: true,
                                pageLength: 50,
                                lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
                                order: [[0, 'asc']],
                                columnDefs: [
                                    {
                                        targets: [8],
                                        orderable: false,
                                        searchable: false
                                    },
                                    {
                                        targets: [3, 4, 5, 7],
                                        orderDataType: 'dom-data-sort-num'
                                    }
                                ],
                                language: {
                                    search: "Search items:",
                                    lengthMenu: "Show _MENU_ items per page",
                                    info: "Showing _START_ to _END_ of _TOTAL_ items",
                                    infoEmpty: "No items to show",
                                    infoFiltered: "(filtered from _MAX_ total items)",
                                    zeroRecords: "No matching items found",
                                    paginate: {
                                        first: "First",
                                        last: "Last",
                                        next: "Next",
                                        previous: "Previous"
                                    }
                                },
                                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                            });
                        }
                    }, 100);
                } else {
                    tbody.html('<tr><td colspan="9" class="text-center text-muted">Error loading data. Please try again.</td></tr>');
                }
            },
            error: function() {
                isFiltering = false;
                tbody.html('<tr><td colspan="9" class="text-center text-danger">Error loading data. Please try again.</td></tr>');
            }
        });
    }
    
    // Filter on group change
    $('#groupFilter').on('change', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(filterInventory, 300);
    });
    
    // Filter on search input (with debounce)
    $('#itemSearch').on('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(filterInventory, 500);
    });
    
    // Clear filters
    $('#clearFiltersBtn').on('click', function() {
        $('#groupFilter').val('').trigger('change');
        $('#itemSearch').val('');
        filterInventory();
    });
    
    // Custom sorting function for numeric data-sort attribute
    $.fn.dataTable.ext.order['dom-data-sort-num'] = function(settings, col) {
        return this.api().column(col, {order: 'index'}).nodes().map(function(td, i) {
            return $(td).attr('data-sort') * 1;
        });
    };
    
    // Load items when group is selected in modal
    $('#modalGroupSelect').on('change', function() {
        const groupName = $(this).val();
        const itemSelect = $('#modalItemSelect');
        const itemHelpText = $('#modalItemHelpText');
        
        if (!groupName) {
            itemSelect.prop('disabled', true).empty().append('<option value="">Select Group first</option>').trigger('change');
            itemHelpText.text('Select a group to load items');
            return;
        }
        
        // Show loading state
        itemSelect.prop('disabled', true).empty().append('<option value="">Loading items...</option>');
        itemHelpText.html('<i class="fas fa-spinner fa-spin"></i> Loading items from group: ' + groupName);
        
        // Load items via AJAX
        $.ajax({
            url: '/api/products',
            method: 'GET',
            data: {
                group_name: groupName
            },
            success: function(response) {
                if (response && !response.error && response.data) {
                    itemSelect.empty().append('<option value="">Select Item</option>');
                    
                    if (response.data.length > 0) {
                        $.each(response.data, function(index, item) {
                            const itemCode = item.code || item.id || item.ITEMNO;
                            const itemDesc = item.description || item.DESP || '';
                            const itemGroup = item.group_name || item.GROUP || 'N/A';
                            itemSelect.append(
                                $('<option></option>')
                                    .attr('value', itemCode)
                                    .text('[' + itemGroup + '] ' + itemCode + ' - ' + itemDesc)
                            );
                        });
                        itemSelect.prop('disabled', false);
                        itemHelpText.text('Found ' + response.data.length + ' item(s) in this group');
                    } else {
                        itemSelect.append('<option value="">No items found in this group</option>');
                        itemHelpText.text('No items found in group: ' + groupName);
                    }
                    
                    // Reinitialize Select2
                    itemSelect.select2({
                        theme: 'bootstrap-5',
                        allowClear: true
                    });
                } else {
                    itemSelect.empty().append('<option value="">Error loading items</option>');
                    itemHelpText.text('Error loading items. Please try again.');
                }
            },
            error: function() {
                itemSelect.empty().append('<option value="">Error loading items</option>');
                itemHelpText.text('Error loading items. Please try again.');
            }
        });
    });
    
    // Handle opening balance form submission
    $('#addOpeningBalanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Disable submit button
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');
        
        $.ajax({
            url: '{{ route("inventory.stock-management.opening-balance.store") }}',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert(response.message);
                    // Reload page to show new opening balance
                    window.location.reload();
                } else {
                    alert('Error: ' + response.message);
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to add opening balance.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert('Error: ' + errorMessage);
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Reset modal when closed (Bootstrap 5 event)
    $('#addOpeningBalanceModal').on('hidden.bs.modal', function() {
        $('#addOpeningBalanceForm')[0].reset();
        $('#modalGroupSelect').val('').trigger('change');
        $('#modalItemSelect').prop('disabled', true).empty().append('<option value="">Select Group first</option>').trigger('change');
        $('#modalItemHelpText').text('Select a group to load items');
    });
    
    // Also handle Bootstrap 5 modal events
    var addOpeningBalanceModal = document.getElementById('addOpeningBalanceModal');
    if (addOpeningBalanceModal) {
        addOpeningBalanceModal.addEventListener('hidden.bs.modal', function() {
            $('#addOpeningBalanceForm')[0].reset();
            $('#modalGroupSelect').val('').trigger('change');
            $('#modalItemSelect').prop('disabled', true).empty().append('<option value="">Select Group first</option>').trigger('change');
            $('#modalItemHelpText').text('Select a group to load items');
        });
    }
    
    // Initialize DataTable for Transactions tab if it's the active tab on page load
    @if($selectedAgent && $inventory->count() > 0)
    if ($('#transactions').hasClass('active')) {
        stockSummaryTable = $('#stockSummaryTable').DataTable({
            responsive: true,
            pageLength: 50,
            lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
            order: [[0, 'asc']], // Sort by Item Code by default
            columnDefs: [
                {
                    targets: [8], // Actions column
                    orderable: false,
                    searchable: false
                },
                {
                    targets: [3, 4, 5, 7], // Current Stock, Stock In, Stock Out, and Price columns
                    orderDataType: 'dom-data-sort-num' // Use custom sorting function
                }
            ],
            language: {
                search: "Search items:",
                lengthMenu: "Show _MENU_ items per page",
                info: "Showing _START_ to _END_ of _TOTAL_ items",
                infoEmpty: "No items to show",
                infoFiltered: "(filtered from _MAX_ total items)",
                zeroRecords: "No matching items found",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
        });
    }
    @endif
});

// Inline editing for opening balance quantity - triggered by Edit button
$(document).on('click', '.btn-edit-quantity', function() {
    const $container = $(this).closest('.quantity-editable');
    const $viewMode = $container.find('.quantity-view-mode');
    const $editMode = $container.find('.quantity-edit-mode');
    const $input = $container.find('.quantity-input');
    
    // Store original value in data attribute if not already stored
    if (!$container.data('original-value')) {
        const originalValue = parseFloat($input.val());
        $container.data('original-value', originalValue);
    }
    
    // Hide view mode, show edit mode
    $viewMode.addClass('d-none');
    $editMode.removeClass('d-none');
    
    // Focus on input
    $input.focus().select();
});

// Cancel editing
$(document).on('click', '.btn-cancel-quantity', function() {
    const $container = $(this).closest('.quantity-editable');
    const $viewMode = $container.find('.quantity-view-mode');
    const $editMode = $container.find('.quantity-edit-mode');
    const $input = $container.find('.quantity-input');
    
    // Reset input value to original
    const originalValue = $container.data('original-value') || parseFloat($input.val());
    $input.val(originalValue);
    $container.removeData('original-value');
    
    // Hide edit mode, show view mode
    $editMode.addClass('d-none');
    $viewMode.removeClass('d-none');
});

// Save quantity
$(document).on('click', '.btn-save-quantity', function() {
    const $container = $(this).closest('.quantity-editable');
    const $viewMode = $container.find('.quantity-view-mode');
    const $editMode = $container.find('.quantity-edit-mode');
    const $display = $container.find('.quantity-display');
    const $input = $container.find('.quantity-input');
    const $saveBtn = $container.find('.btn-save-quantity');
    const $cancelBtn = $container.find('.btn-cancel-quantity');
    const balanceId = $container.data('balance-id');
    const newQuantity = parseFloat($input.val());
    
    // Validate quantity
    if (isNaN(newQuantity) || newQuantity < 0.01) {
        alert('Please enter a valid quantity (minimum 0.01)');
        $input.focus();
        return;
    }
    
    // Disable buttons during save
    $saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    $cancelBtn.prop('disabled', true);
    
    // Save via AJAX
    $.ajax({
        url: '/inventory/stock-management/opening-balance/' + balanceId,
        method: 'POST',
        data: {
            _method: 'PUT',
            quantity: newQuantity
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                // Update display with new value
                $display.text(parseFloat(newQuantity).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
                
                // Clear original value data
                $container.removeData('original-value');
                
                // Hide edit mode, show view mode
                $editMode.addClass('d-none');
                $viewMode.removeClass('d-none');
                
                // Reset button states
                $saveBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Save');
                $cancelBtn.prop('disabled', false);
                
                // Show success message
                alert(response.message);
            } else {
                alert('Error: ' + response.message);
                $saveBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Save');
                $cancelBtn.prop('disabled', false);
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to update opening balance.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            alert('Error: ' + errorMessage);
            $saveBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Save');
            $cancelBtn.prop('disabled', false);
        }
    });
});

// Allow Enter key to save
$(document).on('keypress', '.quantity-input', function(e) {
    if (e.which === 13) { // Enter key
        $(this).closest('.quantity-editable').find('.btn-save-quantity').click();
    }
});

// Allow Escape key to cancel
$(document).on('keydown', '.quantity-input', function(e) {
    if (e.which === 27) { // Escape key
        $(this).closest('.quantity-editable').find('.btn-cancel-quantity').click();
    }
});

function deleteOpeningBalance(id) {
    if (!confirm('Are you sure you want to delete this opening balance? This action cannot be undone.')) {
        return;
    }
    
    $.ajax({
        url: '/inventory/stock-management/opening-balance/' + id,
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                // Remove row from table
                $('#balance-row-' + id).fadeOut(300, function() {
                    $(this).remove();
                    // Check if table is empty
                    if ($('#openingBalanceTable tbody tr').length === 0) {
                        $('#openingBalanceTable tbody').html(
                            '<tr><td colspan="6" class="text-center text-muted py-4">' +
                            '<i class="fas fa-inbox fa-2x mb-2"></i>' +
                            '<p class="mb-0">No opening balance records found for this agent.</p>' +
                            '</td></tr>'
                        );
                    }
                });
                alert(response.message);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to delete opening balance.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            alert('Error: ' + errorMessage);
        }
    });
}
</script>
@endpush
