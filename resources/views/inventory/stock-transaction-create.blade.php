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
    
    

    <!-- Agent Selection Section -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Step 1: Select Agent</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.stock-management.create') }}" id="agentSelectionForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="agentSelect" class="required">Agent *</label>
                            <select class="form-control select2 @error('agent_no') is-invalid @enderror" id="agentSelect"
                                name="agent_no" required style="width: 100%;">
                                <option value="">Select Agent</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->name ?? $agent->username }}"
                                        {{ $selectedAgent == ($agent->name ?? $agent->username) ? 'selected' : '' }}>
                                        {{ $agent->name ?? $agent->username }}
                                        @if ($agent->username && $agent->name != $agent->username)
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

    @if ($selectedAgent)
        <!-- Step 2: Select Item -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-box"></i> Step 2: Select Item</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="groupSelect" class="required">Group *</label>
                            <select class="form-control select2" id="groupSelect" name="group" required
                                style="width: 100%;">
                                <option value="">Select Group</option>
                                @foreach ($groups as $group)
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
                                id="transactionItemCode" name="ITEMNO" required style="width: 100%;">
                                <option value="">Select Group first</option>
                            </select>
                            @error('ITEMNO')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted" id="itemHelpText">Select a group to load items</small>
                        </div>
                    </div>
                </div>

                <!-- Current Stock Info - shown below item selection -->
                <div class="mt-3">
                    <div class="d-flex">
                        <h6 class="mb-2 mr-3">Available Stock: </h6>
                        <div id="currentStockInfo">
                            <p class="text-muted mb-0">Select an item to see current stock.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Step 3: Stock Transaction Info -->
        <form method="POST" action="{{ route('inventory.stock-management.store') }}" id="stockTransactionForm">
            @csrf
            <input type="hidden" name="agent_no" value="{{ $selectedAgent }}">

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Step 3: Stock Transaction Info</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="required">Transaction Type *</label>
                        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" role="group">
                            <label
                                class="btn btn-outline-success flex-fill {{ old('transaction_type', '') == 'in' ? 'active' : '' }}"
                                style="border-color: #28a745; color: #28a745;">
                                <input type="radio" name="transaction_type" id="transactionTypeIn" value="in"
                                    {{ old('transaction_type') == 'in' ? 'checked' : '' }} required>
                                <i class="fas fa-arrow-up"></i> Stock In
                            </label>
                            <label
                                class="btn btn-outline-danger flex-fill {{ old('transaction_type', '') == 'out' ? 'active' : '' }}"
                                style="border-color: #dc3545; color: #dc3545;">
                                <input type="radio" name="transaction_type" id="transactionTypeOut" value="out"
                                    {{ old('transaction_type') == 'out' ? 'checked' : '' }}>
                                <i class="fas fa-arrow-down"></i> Stock Out
                            </label>
                            <label
                                class="btn btn-outline-primary flex-fill {{ old('transaction_type', '') == 'adjustment' ? 'active' : '' }}"
                                style="border-color: #007bff; color: #007bff;">
                                <input type="radio" name="transaction_type" id="transactionTypeAdjustment"
                                    value="adjustment" {{ old('transaction_type') == 'adjustment' ? 'checked' : '' }}>
                                <i class="fas fa-edit"></i> Adjustment
                            </label>
                        </div>
                        @error('transaction_type')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>


                    <div class="form-group">
                        <label for="transactionQuantity" class="required">Quantity *</label>
                        <input type="number" class="form-control @error('quantity') is-invalid @enderror"
                            id="transactionQuantity" name="quantity" step="0.01" min="0.01"
                            value="{{ old('quantity') }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="alert alert-warning mt-2" id="negativeValueAlert" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Notice:</strong> Negative values are not
                            allowed for Stock Out transactions. Please enter a positive quantity.
                        </div>
                        <small class="form-text text-muted" id="quantityHelp">
                            Enter quantity to add/remove
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="transactionNotes">
                            Notes
                            <span id="notesRequired" style="display:none;" class="text-danger">*</span>
                        </label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="transactionNotes" name="notes"
                            rows="4">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted" id="notesHelp">
                            Optional notes for this transaction
                        </small>
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

@push('styles')
    <style>
        /* Radio button group styling */
        .btn-group-toggle .btn {
            transition: all 0.3s ease;
        }

        .btn-group-toggle .btn.active {
            color: white !important;
        }

        .btn-group-toggle .btn-outline-success.active {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-group-toggle .btn-outline-danger.active {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-group-toggle .btn-outline-primary.active {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-group-toggle input[type="radio"] {
            position: absolute;
            clip: rect(0, 0, 0, 0);
            pointer-events: none;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Update transaction form when type changes (radio buttons)
            $('input[name="transaction_type"]').on('change', function() {
                // Update active state for button group
                $('input[name="transaction_type"]').each(function() {
                    if ($(this).is(':checked')) {
                        $(this).closest('label').addClass('active');
                    } else {
                        $(this).closest('label').removeClass('active');
                    }
                });

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

            // Also handle click on labels to ensure active state
            $('label[for^="transactionType"]').on('click', function() {
                const radio = $(this).find('input[type="radio"]');
                if (radio.length) {
                    radio.prop('checked', true).trigger('change');
                }
            });

            // When opening balance is selected, ensure transaction type is "in"
            $('#referenceType').on('change', function() {
                if ($(this).val() === 'opening_balance') {
                    $('#transactionTypeIn').prop('checked', true).trigger('change');
                    // Update button active state
                    $('input[name="transaction_type"]').each(function() {
                        $(this).closest('label').removeClass('active');
                    });
                    $('#transactionTypeIn').closest('label').addClass('active');
                }
                updateTransactionForm();
            });

            // Initialize form state
            updateTransactionForm();
            const initialType = $('input[name="transaction_type"]:checked').val();
            if (initialType === 'in') {
                $('#referenceTypeGroup').show();
            } else {
                $('#referenceTypeGroup').hide();
            }

            // Prevent negative values for Stock Out transactions
            // Allow negative values for Adjustment transactions
            $('#transactionQuantity').on('input change', function() {
                const transactionType = $('input[name="transaction_type"]:checked').val();
                const quantity = parseFloat($(this).val());
                const negativeAlert = $('#negativeValueAlert');

                // Only prevent negative for Stock Out, allow for Adjustment
                if (transactionType === 'out' && !isNaN(quantity) && quantity < 0) {
                    // Show alert and prevent negative value for Stock Out
                    negativeAlert.slideDown();
                    $(this).val(Math.abs(quantity)); // Convert to positive
                    return false;
                } else {
                    negativeAlert.slideUp();
                }
            });

            // Update current stock when item is selected
            $('#transactionItemCode').on('change', function() {
                const itemno = $(this).val();
                if (itemno) {
                    loadCurrentStock(itemno);
                } else {
                    $('#currentStockInfo').html(
                        '<p class="text-muted">Select an item to see current stock.</p>');
                }
            });

            // Ensure ITEMNO is submitted - Select2 hides the original select element
            $('#stockTransactionForm').on('submit', function(e) {
                const $select = $('#transactionItemCode');
                let itemValue = $select.val();
                
                // Try to get value from Select2 if regular val() doesn't work
                if (!itemValue) {
                    try {
                        itemValue = $select.select2('val');
                        if (Array.isArray(itemValue)) {
                            itemValue = itemValue[0] || itemValue;
                        }
                    } catch(err) {
                        // Select2 not initialized
                    }
                }
                
                if (!itemValue) {
                    e.preventDefault();
                    alert('Please select an item.');
                    return false;
                }
                
                // Remove name from select to avoid duplicate names, then create hidden input
                $select.removeAttr('name');
                $('#ITEMNO_backup').remove();
                
                $('<input>').attr({
                    type: 'hidden',
                    id: 'ITEMNO_backup',
                    name: 'ITEMNO',
                    value: itemValue
                }).appendTo(this);
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
                itemSelect.empty().append('<option value="">Select Group first</option>');
                itemSelect.prop('disabled', true);
                itemSelect.trigger('change');
                itemHelpText.text('Select a group to load items');
                return;
            }

            // Show loading state
            itemSelect.empty().append('<option value="">Loading items...</option>');
            itemSelect.prop('disabled', true);
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
                                    .text('[' + itemGroup + '] ' + itemCode + ' - ' +
                                        itemDesc)
                                );
                            });
                            itemHelpText.text('Found ' + response.data.length +
                                ' item(s) in this group');
                        } else {
                            itemSelect.append('<option value="">No items found in this group</option>');
                            itemHelpText.text('No items found in group: ' + groupName);
                        }

                        // CRITICAL: Enable select BEFORE initializing/updating Select2
                        // Disabled selects don't submit their values
                        itemSelect.prop('disabled', false);
                        
                        // Check if Select2 is already initialized
                        if (itemSelect.hasClass('select2-hidden-accessible')) {
                            // Update existing Select2 instance
                            itemSelect.trigger('change.select2');
                        } else {
                            // Initialize Select2 for the first time
                            itemSelect.select2({
                                theme: 'bootstrap-5',
                            });
                        }
                        
                        // Auto-select item if item_code is provided in URL
                        const urlParams = new URLSearchParams(window.location.search);
                        const itemCodeParam = urlParams.get('item_code');
                        if (itemCodeParam && itemSelect.find('option[value="' + itemCodeParam + '"]').length > 0) {
                            itemSelect.val(itemCodeParam).trigger('change');
                        }
                    } else {
                        itemSelect.empty().append('<option value="">Error loading items</option>');
                        itemSelect.prop('disabled', true);
                        itemHelpText.text('Error loading items. Please try again.');
                    }
                },
                error: function() {
                    itemSelect.empty().append('<option value="">Error loading items</option>');
                    itemSelect.prop('disabled', true);
                    itemHelpText.text('Error loading items. Please try again.');
                }
            });
        });

        // Initialize items if group is already selected on page load
        @if (request('group'))
            $(document).ready(function() {
                $('#groupSelect').trigger('change');
            });
        @endif

        function updateTransactionForm() {
            const type = $('input[name="transaction_type"]:checked').val();
            const quantityInput = $('#transactionQuantity');
            const notesInput = $('#transactionNotes');
            const notesRequired = $('#notesRequired');
            const quantityHelp = $('#quantityHelp');
            const notesHelp = $('#notesHelp');
            const submitButton = $('#submitButton');
            const referenceType = $('#referenceType').val();

            if (type === 'in') {
                quantityInput.attr('min', '0.01');
                quantityHelp.text('Enter quantity to add to stock');
                notesInput.prop('required', false);
                notesRequired.hide();
                notesHelp.text('Optional notes for this transaction');
                submitButton.removeClass('btn-warning btn-info').addClass('btn-success').html(
                    '<i class="fas fa-plus"></i> Add Stock');
            } else if (type === 'out') {
                quantityInput.attr('min', '0.01');
                quantityHelp.text('Enter quantity to remove from stock (positive values only)');
                notesInput.prop('required', false);
                notesRequired.hide();
                notesHelp.text('Optional notes for this transaction');
                submitButton.removeClass('btn-success btn-info').addClass('btn-warning').html(
                    '<i class="fas fa-minus"></i> Remove Stock');
                // Hide negative alert when switching to out type (will show if user enters negative)
                $('#negativeValueAlert').slideUp();
            } else if (type === 'adjustment') {
                quantityInput.removeAttr('min');
                quantityHelp.text('Enter positive value to increase, negative to decrease stock');
                notesInput.prop('required', true);
                notesRequired.show();
                notesHelp.text('Reason for adjustment is required');
                submitButton.removeClass('btn-success btn-warning').addClass('btn-info').html(
                    '<i class="fas fa-edit"></i> Adjust Stock');
            } else {
                quantityHelp.text('Enter quantity to add/remove');
                notesInput.prop('required', false);
                notesRequired.hide();
                notesHelp.text('Optional notes for this transaction');
                submitButton.removeClass('btn-success btn-warning btn-info').addClass('btn-primary').html(
                    '<i class="fas fa-save"></i> Create Transaction');
            }
        }

        // Update transaction info when reference type changes
        $('#referenceType').on('change', function() {
            updateTransactionForm();
        });

        function loadCurrentStock(itemno) {
            const agentNo = '{{ $selectedAgent ?? '' }}';
            if (!agentNo) {
                $('#currentStockInfo').html('<p class="text-muted">Select an item to see current stock.</p>');
                return;
            }

            // Show loading state
            $('#currentStockInfo').html(
                '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading stock information...</p>');

            // Make AJAX call to get agent-specific stock (using web route)
            $.ajax({
                url: '/inventory/stock-management/stock-by-agent/' + itemno,
                method: 'GET',
                data: {
                    agent_no: agentNo
                },
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Check response structure - makeResponse returns {error, status, message, data}
                    // error: 0 means success, error: 1 means failure
                    let stockData = null;

                    // Handle makeResponse format: {error: 0/1, status: 200, message: "...", data: {...}}
                    if (response && typeof response.error !== 'undefined') {
                        if (response.error === 0 && response.data) {
                            stockData = response.data;
                        } else {
                            // API returned error
                            $('#currentStockInfo').html(
                                '<p class="text-warning mb-0"><i class="fas fa-exclamation-triangle"></i> ' +
                                (response.message || 'Error loading stock information') + '</p>');
                            return;
                        }
                    } else if (response && response.data) {
                        // Direct data response
                        stockData = response.data;
                    } else if (response && (response.available !== undefined || response.stockIn !==
                            undefined)) {
                        // Response is already the data object
                        stockData = response;
                    }

                    // Always show stock information, even if all values are zero (new item)
                    if (stockData && (stockData.available !== undefined || stockData.stockIn !== undefined ||
                            stockData.stockOut !== undefined)) {
                        const stock = stockData.available !== undefined ? stockData.available : 0;
                        const stockIn = stockData.stockIn !== undefined ? stockData.stockIn : 0;
                        const stockOut = stockData.stockOut !== undefined ? stockData.stockOut : 0;
                        const returnGood = stockData.returnGood !== undefined ? stockData.returnGood : 0;
                        const returnBad = stockData.returnBad !== undefined ? stockData.returnBad : 0;
                        const itemDesp = stockData.DESP || itemno;

                        // Determine stock status color
                        let stockClass = 'success';
                        if (stock < 0) stockClass = 'danger';
                        else if (stock == 0) stockClass = 'warning';

                        // Simple display - just show available stock amount
                        let html = '<span class="badge badge-' + stockClass + ' badge-lg">' +
                            parseFloat(stock).toFixed(2) + '</span>';

                        $('#currentStockInfo').html(html);
                    } else {
                        // Fallback if response structure is unexpected
                        $('#currentStockInfo').html(
                            '<span class="badge badge-warning badge-lg">0.00</span>');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg =
                        '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Unable to load stock information. Stock will be calculated when transaction is created.</p>';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = '<p class="text-warning mb-0"><i class="fas fa-exclamation-triangle"></i> ' +
                            xhr.responseJSON.message + '</p>';
                    } else if (xhr.status === 401 || xhr.status === 403) {
                        errorMsg =
                            '<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle"></i> Authentication required. Please refresh the page.</p>';
                    } else if (xhr.status === 404) {
                        errorMsg =
                            '<p class="text-warning mb-0"><i class="fas fa-exclamation-triangle"></i> API endpoint not found. Please check the route.</p>';
                    }
                    $('#currentStockInfo').html(errorMsg);
                }
            });
        }
    </script>
@endpush
