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
                <form method="GET" action="{{ route('inventory.stock-management') }}" id="searchForm">
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
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="itemSearch">Search by Item Code or Name</label>
                                <input type="text" class="form-control" id="itemSearch" name="search" 
                                       value="{{ request('search') }}" 
                                       placeholder="Enter item code or description...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="btn-group btn-group-block" style="display: flex; gap: 5px;">
                                    <button type="submit" class="btn btn-primary" id="searchBtn" style="flex: 1;">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="{{ route('inventory.stock-management', ['agent_no' => $selectedAgent]) }}" class="btn btn-info" style="flex: 1;">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
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
                        <a class="nav-link {{ request()->get('tab') != 'transactions' ? 'active' : '' }}" id="opening-balance-tab" data-toggle="tab" data-bs-toggle="tab" href="#opening-balance" role="tab" aria-controls="opening-balance" aria-selected="{{ request()->get('tab') != 'transactions' ? 'true' : 'false' }}">
                            <i class="fas fa-database"></i> Opening Balance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->get('tab') == 'transactions' ? 'active' : '' }}" id="transactions-tab" data-toggle="tab" data-bs-toggle="tab" href="#transactions" role="tab" aria-controls="transactions" aria-selected="{{ request()->get('tab') == 'transactions' ? 'true' : 'false' }}">
                            <i class="fas fa-list"></i> Transactions
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-1">
                <div class="tab-content" id="stockTabsContent">
                    <!-- Opening Balance Tab -->
                    <div class="tab-pane fade {{ request()->get('tab') != 'transactions' ? 'show active' : '' }}" id="opening-balance" role="tabpanel" aria-labelledby="opening-balance-tab">
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
                                                <p class="mb-0">
                                                    @if(request('group') || request('search'))
                                                        No opening balance records found matching your search criteria.
                                                        <a href="{{ route('inventory.stock-management', ['agent_no' => $selectedAgent]) }}" class="alert-link">Clear filters</a>
                                                    @else
                                                        No opening balance records found for this agent.
                                                    @endif
                                                </p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div class="tab-pane fade {{ request()->get('tab') == 'transactions' ? 'show active' : '' }}" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">

                        <!-- Export Buttons -->
                        <div class="mb-3 d-flex justify-content-end gap-2">
                            <a href="{{ route('inventory.stock-management.export.excel', request()->query()) }}"
                               class="btn btn-success btn-sm"
                               title="Export to Excel">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('inventory.stock-management.export.pdf', request()->query()) }}"
                               class="btn btn-danger btn-sm"
                               title="Export to PDF">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="stockSummaryTable">
                                <thead>
                                    <tr>
                                        <th class="text-center">Item Code</th>
                                        <th class="text-center">Description</th>
                                        <th class="text-center">Group</th>
                                        <th class="text-center">Current Stock</th>
                                        <th class="text-center">Stock In + Return</th>
                                        <th class="text-center">Stock Out</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-center">Price</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inventory as $item)
                                        <?php 
                                        //stockIn included returnGood already
                                        $count_stock = $item['stockIn'] - $item['stockOut'];
                                        ?>
                                        <tr>
                                            <td><strong>{{ $item['ITEMNO'] }}</strong></td>
                                            <td>{{ $item['DESP'] ?? 'N/A' }}</td>
                                            <td>{{ $item['GROUP'] ?? 'N/A' }}</td>
                                            <td data-sort="{{ $item['current_stock'] }}" align="right">
                                                <strong>
                                                    {{ number_format($item['current_stock'], 2) }}
                                                </strong>
                                                <?php
                                                    if($item['current_stock'] != $count_stock){
                                                        echo '<br><span class="text-danger">'.$count_stock.'</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td align="right" data-sort="{{ $item['stockIn'] ?? 0 }}" class="text-success">
                                                {{ number_format($item['stockIn'] ?? 0, 2) }}
                                            </td>
                                            <td align="right" data-sort="{{ $item['stockOut'] ?? 0 }}" class="text-danger">
                                                {{ number_format($item['stockOut'] ?? 0, 2) }}
                                            </td>
                                            <td>{{ $item['UNIT'] ?? 'N/A' }}</td>
                                            <td data-sort="{{ $item['PRICE'] ?? 0 }}">{{ number_format($item['PRICE'] ?? 0, 2) }}</td>
                                            <td>
                                                <a href="{{ route('inventory.stock-management.item.transactions', $item['ITEMNO']) }}?agent_no={{ urlencode($selectedAgent) }}" 
                                                target="_blank"   
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
    // Get current tab from URL hash or query parameter
    var hash = window.location.hash;
    var tabParam = new URLSearchParams(window.location.search).get('tab');
    var currentTab = hash || (tabParam === 'transactions' ? '#transactions' : '#opening-balance');
    
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
        
        // Update URL hash and query parameter
        var url = new URL(window.location);
        url.hash = target;
        if (target === '#transactions') {
            url.searchParams.set('tab', 'transactions');
        } else {
            url.searchParams.delete('tab');
        }
        window.history.pushState({}, '', url);
    });
    
    // Handle initial tab state from URL
    if (currentTab === '#transactions' || tabParam === 'transactions') {
        $('#transactions-tab').addClass('active');
        $('#opening-balance-tab').removeClass('active');
        $('#transactions').addClass('show active');
        $('#opening-balance').removeClass('show active');
        // Ensure hash is set
        if (!window.location.hash) {
            var url = new URL(window.location);
            url.hash = '#transactions';
            window.history.replaceState({}, '', url);
        }
    } else {
        $('#opening-balance-tab').addClass('active');
        $('#transactions-tab').removeClass('active');
        $('#opening-balance').addClass('show active');
        $('#transactions').removeClass('show active');
        // Ensure hash is set
        if (!window.location.hash) {
            var url = new URL(window.location);
            url.hash = '#opening-balance';
            window.history.replaceState({}, '', url);
        }
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        var hash = window.location.hash || '#opening-balance';
        var tabParam = new URLSearchParams(window.location.search).get('tab');
        if (tabParam === 'transactions' || hash === '#transactions') {
            $('#transactions-tab').click();
        } else {
            $('#opening-balance-tab').click();
        }
    });
    
    // Initialize Select2 - simple and clean
    $('.select2').select2({
        theme: 'bootstrap-5',
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
    
    // Auto-submit search form when group filter changes (including when cleared)
    $('#groupFilter').on('change', function() {
        $('#searchForm').submit();
    });
    
    // Handle search form submission - preserve tab hash in URL
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        var url = new URL($(this).attr('action'), window.location.origin);
        var formData = $(this).serializeArray();
        
        // Always preserve agent_no
        var agentNo = $('#searchForm input[name="agent_no"]').val();
        if (agentNo) {
            url.searchParams.set('agent_no', agentNo);
        }
        
        // Add or remove form data to/from URL
        formData.forEach(function(item) {
            // Skip agent_no as it's handled above
            if (item.name === 'agent_no') {
                return;
            }
            
            if (item.value && item.value.trim() !== '') {
                url.searchParams.set(item.name, item.value);
            } else {
                // Remove parameter if value is empty
                url.searchParams.delete(item.name);
            }
        });
        
        // Preserve current tab hash
        var currentHash = window.location.hash || '#opening-balance';
        if (currentHash === '#transactions') {
            url.searchParams.set('tab', 'transactions');
        }
        url.hash = currentHash;
        
        // Navigate to new URL
        window.location.href = url.toString();
        return false;
    });
    
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
