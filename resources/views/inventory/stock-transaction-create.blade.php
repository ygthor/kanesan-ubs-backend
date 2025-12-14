@extends('layouts.admin')

@section('title', 'Create Stock Transaction - Kanesan UBS Backend')

@section('page-title', 'Create Stock Transaction')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}">Stock Management</a></li>
    <li class="breadcrumb-item active">Create Stock Transaction</li>
@endsection

@section('card-title', 'Create Stock Transaction')

@section('card-tools')
    <a href="{{ route('inventory.stock-management') }}" class="btn btn-secondary btn-sm">
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Agent Selection Section -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Step 1: Select Agent</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.stock-management.create') }}" id="agentSelectionForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="agentSelect" class="required">Agent *</label>
                            <select class="form-control select2 @error('agent_no') is-invalid @enderror" 
                                    id="agentSelect" name="agent_no" required style="width: 100%;">
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
                            @error('agent_no')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Select Agent
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($selectedAgent)
        <!-- Transaction Form -->
        <form method="POST" action="{{ route('inventory.stock-management.store') }}" id="stockTransactionForm">
            @csrf
            <input type="hidden" name="agent_no" value="{{ $selectedAgent }}">
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Create Transaction</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="transactionType" class="required">Transaction Type *</label>
                                <select class="form-control select2 @error('transaction_type') is-invalid @enderror" 
                                        id="transactionType" name="transaction_type" required style="width: 100%;">
                                    <option value="">Select Type</option>
                                    <option value="in" {{ old('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                                    <option value="out" {{ old('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                                    <option value="adjustment" {{ old('transaction_type') == 'adjustment' ? 'selected' : '' }}>Stock Adjustment</option>
                                </select>
                                @error('transaction_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group" id="referenceTypeGroup" style="display: none;">
                                <label for="referenceType">Reference Type</label>
                                <select class="form-control select2" id="referenceType" name="reference_type" style="width: 100%;">
                                    <option value="">None</option>
                                    <option value="opening_balance" {{ old('reference_type') == 'opening_balance' ? 'selected' : '' }}>Opening Balance</option>
                                </select>
                                <small class="form-text text-muted">Select "Opening Balance" for initial stock entries (transaction type will be set to "Stock In")</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="groupSelect" class="required">Group *</label>
                                        <select class="form-control select2" id="groupSelect" name="group" required style="width: 100%;">
                                            <option value="">Select Group</option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->name }}" 
                                                        {{ request('group') == $group->name ? 'selected' : '' }}>
                                                    {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Select a group first to load items</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transactionItemCode" class="required">Item *</label>
                                        <select class="form-control select2 @error('ITEMNO') is-invalid @enderror" 
                                                id="transactionItemCode" name="ITEMNO" required style="width: 100%;" disabled>
                                            <option value="">Select Group first</option>
                                        </select>
                                        @error('ITEMNO')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted" id="itemHelpText">Select a group to load items</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="transactionQuantity" class="required">Quantity *</label>
                                <input type="number" class="form-control @error('quantity') is-invalid @enderror" 
                                       id="transactionQuantity" name="quantity" 
                                       step="0.01" min="0.01" 
                                       value="{{ old('quantity') }}" 
                                       required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted" id="quantityHelp">
                                    Enter quantity to add/remove
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="transactionNotes">
                                    Notes 
                                    <span id="notesRequired" style="display:none;" class="text-danger">*</span>
                                </label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" 
                                          id="transactionNotes" name="notes" rows="4">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted" id="notesHelp">
                                    Optional notes for this transaction
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Transaction Information</h5>
                        </div>
                        <div class="card-body">
                            <div id="transactionInfo">
                                <p class="text-muted">Select a transaction type to see details.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Stock</h5>
                        </div>
                        <div class="card-body">
                            <div id="currentStockInfo">
                                <p class="text-muted">Select an item to see current stock.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Selected Agent</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><strong>{{ $selectedAgent }}</strong></p>
                            <small class="text-muted">Change agent in Step 1 above</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="submitButton">
                        <i class="fas fa-save"></i> Create Transaction
                    </button>
                    <a href="{{ route('inventory.stock-management') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Please select an agent first to proceed.
        </div>
    @endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        allowClear: true
    });
    
    // Update transaction form when type changes
    $('#transactionType').on('change', function() {
        updateTransactionForm();
    });
    
    // Update reference type visibility based on transaction type
    $('#transactionType').on('change', function() {
        const type = $(this).val();
        const referenceTypeGroup = $('#referenceTypeGroup');
        if (type === 'in') {
            referenceTypeGroup.show();
        } else {
            $('#referenceType').val('').trigger('change');
            referenceTypeGroup.hide();
        }
        updateTransactionForm();
    });
    
    // When opening balance is selected, ensure transaction type is "in"
    $('#referenceType').on('change', function() {
        if ($(this).val() === 'opening_balance') {
            $('#transactionType').val('in').trigger('change');
        }
        updateTransactionForm();
    });
    
    // Initialize form state
    updateTransactionForm();
    const initialType = $('#transactionType').val();
    if (initialType === 'in') {
        $('#referenceTypeGroup').show();
    } else {
        $('#referenceTypeGroup').hide();
    }
    
    // Update current stock when item is selected
    $('#transactionItemCode').on('change', function() {
        const itemno = $(this).val();
        if (itemno) {
            loadCurrentStock(itemno);
            // Clear manual input when dropdown item is selected
            $('#transactionItemCodeManual').val('');
        } else {
            $('#currentStockInfo').html('<p class="text-muted">Select an item to see current stock.</p>');
        }
    });
    
    // Handle manual item code entry
    $('#transactionItemCodeManual').on('change blur', function() {
        const itemno = $(this).val();
        if (itemno) {
            // Clear dropdown selection when manual entry is used
            $('#transactionItemCode').val('').trigger('change');
            loadCurrentStock(itemno);
        }
    });
    
    // Before form submission, use manual entry if provided, otherwise use dropdown
    $('#stockTransactionForm').on('submit', function(e) {
        const manualItemCode = $('#transactionItemCodeManual').val();
        const dropdownItemCode = $('#transactionItemCode').val();
        
        if (manualItemCode && manualItemCode.trim() !== '') {
            // Use manual entry
            $('#transactionItemCode').removeAttr('required');
            // Create a hidden input with the manual item code
            if ($('#ITEMNO_hidden').length === 0) {
                $('<input>').attr({
                    type: 'hidden',
                    id: 'ITEMNO_hidden',
                    name: 'ITEMNO',
                    value: manualItemCode.trim()
                }).appendTo('#stockTransactionForm');
            } else {
                $('#ITEMNO_hidden').val(manualItemCode.trim());
            }
        } else if (dropdownItemCode) {
            // Use dropdown selection
            $('#ITEMNO_hidden').remove();
        } else {
            // Neither selected - let validation handle it
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-submit agent selection form when agent changes
    $('#agentSelect').on('change', function() {
        if ($(this).val()) {
            $('#agentSelectionForm').submit();
        }
    });
    
});

// Load items when group is selected
$('#groupSelect').on('change', function() {
    const groupName = $(this).val();
    const itemSelect = $('#transactionItemCode');
    const itemHelpText = $('#itemHelpText');
    
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

// Initialize items if group is already selected on page load
@if(request('group'))
$(document).ready(function() {
    $('#groupSelect').trigger('change');
});
@endif

function updateTransactionForm() {
    const type = $('#transactionType').val();
    const quantityInput = $('#transactionQuantity');
    const notesInput = $('#transactionNotes');
    const notesRequired = $('#notesRequired');
    const quantityHelp = $('#quantityHelp');
    const notesHelp = $('#notesHelp');
    const submitButton = $('#submitButton');
    const transactionInfo = $('#transactionInfo');
    const referenceType = $('#referenceType').val();
    
    if (type === 'in') {
        quantityInput.attr('min', '0.01');
        quantityHelp.text('Enter quantity to add to stock');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-warning btn-info').addClass('btn-success').html('<i class="fas fa-plus"></i> Add Stock');
        
        if (referenceType === 'opening_balance') {
            transactionInfo.html('<p><strong>Stock In (Opening Balance):</strong> Initial stock entry for this item. Quantity must be positive.</p>');
        } else {
            transactionInfo.html('<p><strong>Stock In:</strong> Adds stock to inventory. Quantity must be positive.</p>');
        }
    } else if (type === 'out') {
        quantityInput.attr('min', '0.01');
        quantityHelp.text('Enter quantity to remove from stock');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-success btn-info').addClass('btn-warning').html('<i class="fas fa-minus"></i> Remove Stock');
        transactionInfo.html('<p><strong>Stock Out:</strong> Removes stock from inventory. Quantity must be positive. System will check available stock.</p>');
    } else if (type === 'adjustment') {
        quantityInput.removeAttr('min');
        quantityHelp.text('Enter positive value to increase, negative to decrease stock');
        notesInput.prop('required', true);
        notesRequired.show();
        notesHelp.text('Reason for adjustment is required');
        submitButton.removeClass('btn-success btn-warning').addClass('btn-info').html('<i class="fas fa-edit"></i> Adjust Stock');
        transactionInfo.html('<p><strong>Stock Adjustment:</strong> Manual adjustment to correct stock levels. Can be positive or negative. Notes are required.</p>');
    } else {
        quantityHelp.text('Enter quantity to add/remove');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-success btn-warning btn-info').addClass('btn-primary').html('<i class="fas fa-save"></i> Create Transaction');
        transactionInfo.html('<p class="text-muted">Select a transaction type to see details.</p>');
    }
}

// Update transaction info when reference type changes
$('#referenceType').on('change', function() {
    updateTransactionForm();
});

function loadCurrentStock(itemno) {
    const agentNo = '{{ $selectedAgent ?? "" }}';
    if (!agentNo) {
        $('#currentStockInfo').html('<p class="text-muted">Select an item to see current stock.</p>');
        return;
    }
    
    // Show loading state
    $('#currentStockInfo').html('<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading stock information...</p>');
    
    // Make AJAX call to get agent-specific stock
    $.ajax({
        url: '/api/inventory/stock-by-agent/' + itemno,
        method: 'GET',
        data: {
            agent_no: agentNo
        },
        success: function(response) {
            if (response && !response.error && response.data) {
                const stock = response.data.available || 0;
                const stockIn = response.data.stockIn || 0;
                const stockOut = response.data.stockOut || 0;
                const returnGood = response.data.returnGood || 0;
                const returnBad = response.data.returnBad || 0;
                const itemDesp = response.data.DESP || itemno;
                
                let html = '<p><strong>Item:</strong> ' + itemno + '</p>';
                html += '<p><small class="text-muted">' + itemDesp + '</small></p>';
                html += '<p><strong>Available Stock:</strong> <span class="badge badge-primary badge-lg">' + parseFloat(stock).toFixed(2) + '</span></p>';
                html += '<hr class="my-2">';
                html += '<small class="text-muted">';
                html += '<strong>Breakdown:</strong><br>';
                html += 'Stock In: <span class="text-success">' + parseFloat(stockIn).toFixed(2) + '</span><br>';
                html += 'Stock Out: <span class="text-danger">' + parseFloat(stockOut).toFixed(2) + '</span><br>';
                if (returnGood > 0) html += 'Return Good: <span class="text-info">' + parseFloat(returnGood).toFixed(2) + '</span><br>';
                if (returnBad > 0) html += 'Return Bad: <span class="text-warning">' + parseFloat(returnBad).toFixed(2) + '</span><br>';
                html += '</small>';
                html += '<p class="text-muted mt-2"><small>Stock calculated for agent: <strong>' + agentNo + '</strong></small></p>';
                
                $('#currentStockInfo').html(html);
            } else {
                $('#currentStockInfo').html('<p><strong>Item:</strong> ' + itemno + '</p><p class="text-muted">Stock information not available. Stock will be calculated when transaction is created.</p>');
            }
        },
        error: function() {
            $('#currentStockInfo').html('<p><strong>Item:</strong> ' + itemno + '</p><p class="text-muted">Stock information not available. Stock will be calculated when transaction is created.</p>');
        }
    });
}
</script>
@endpush
