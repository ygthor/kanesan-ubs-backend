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

    <form method="POST" action="{{ route('inventory.stock-management.store') }}" id="stockTransactionForm">
        @csrf
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="transactionType" class="required">Transaction Type *</label>
                            <select class="form-control @error('transaction_type') is-invalid @enderror" 
                                    id="transactionType" name="transaction_type" required onchange="updateTransactionForm()">
                                <option value="">Select Type</option>
                                <option value="in" {{ old('transaction_type') == 'in' ? 'selected' : '' }}>Stock In</option>
                                <option value="out" {{ old('transaction_type') == 'out' ? 'selected' : '' }}>Stock Out</option>
                                <option value="adjustment" {{ old('transaction_type') == 'adjustment' ? 'selected' : '' }}>Stock Adjustment</option>
                            </select>
                            @error('transaction_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="transactionItemCode" class="required">Item Code *</label>
                            <select class="form-control @error('ITEMNO') is-invalid @enderror" 
                                    id="transactionItemCode" name="ITEMNO" required>
                                <option value="">Select Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->ITEMNO }}" {{ old('ITEMNO') == $item->ITEMNO ? 'selected' : '' }}>
                                        {{ $item->ITEMNO }} - {{ $item->DESP }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ITEMNO')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Or enter item code manually below</small>
                            
                            <input type="text" class="form-control mt-2" 
                                   id="transactionItemCodeManual" 
                                   placeholder="Or enter item code manually"
                                   onchange="document.getElementById('transactionItemCode').value = this.value">
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    updateTransactionForm();
    
    // Update current stock when item is selected
    $('#transactionItemCode, #transactionItemCodeManual').on('change', function() {
        const itemno = $('#transactionItemCode').val() || $('#transactionItemCodeManual').val();
        if (itemno) {
            loadCurrentStock(itemno);
        }
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
    const transactionInfo = $('#transactionInfo');
    
    if (type === 'in') {
        quantityInput.attr('min', '0.01');
        quantityHelp.text('Enter quantity to add to stock');
        notesInput.prop('required', false);
        notesRequired.hide();
        notesHelp.text('Optional notes for this transaction');
        submitButton.removeClass('btn-warning btn-info').addClass('btn-success').html('<i class="fas fa-plus"></i> Add Stock');
        transactionInfo.html('<p><strong>Stock In:</strong> Adds stock to inventory. Quantity must be positive.</p>');
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

function loadCurrentStock(itemno) {
    // You can add AJAX call here to fetch current stock if needed
    // For now, just show the item code
    $('#currentStockInfo').html('<p><strong>Item:</strong> ' + itemno + '</p><p class="text-muted">Current stock will be checked when transaction is created.</p>');
}
</script>
@endpush

