@extends('layouts.admin')

@section('title', 'Opening Balance Management - Kanesan UBS Backend')

@section('page-title', 'Opening Balance Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.stock-management') }}">Stock Management</a></li>
    <li class="breadcrumb-item active">Opening Balance</li>
@endsection

@section('card-title', 'Opening Balance Management')

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

    <!-- Agent Selection Section -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Select Agent</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.stock-management.opening-balance') }}" id="agentSelectionForm">
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
                </div>
            </form>
        </div>
    </div>

    @if($selectedAgent)
        <!-- Action Buttons -->
        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addOpeningBalanceModal">
                <i class="fas fa-plus"></i> Add Opening Balance
            </button>
        </div>

        <!-- Opening Balance List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-list"></i> Opening Balance for Agent: <strong>{{ $selectedAgent }}</strong></h5>
            </div>
            <div class="card-body">
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
                                    <td><strong class="text-success">{{ number_format($balance->quantity, 2) }}</strong></td>
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
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Please select an agent to view opening balance records.
        </div>
    @endif

    <!-- Add Opening Balance Modal -->
    <div class="modal fade" id="addOpeningBalanceModal" tabindex="-1" role="dialog" aria-labelledby="addOpeningBalanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOpeningBalanceModalLabel">Add Opening Balance</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
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
                                        @php
                                            $allGroups = \App\Models\Icgroup::select('name')->orderBy('name', 'asc')->get();
                                        @endphp
                                        @foreach($allGroups as $group)
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
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        allowClear: true
    });
    
    // Auto-submit agent selection form when agent changes
    $('#agentSelect').on('change', function() {
        $('#agentSelectionForm').submit();
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
    
    // Handle form submission
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
    
    // Reset modal when closed
    $('#addOpeningBalanceModal').on('hidden.bs.modal', function() {
        $('#addOpeningBalanceForm')[0].reset();
        $('#modalGroupSelect').val('').trigger('change');
        $('#modalItemSelect').prop('disabled', true).empty().append('<option value="">Select Group first</option>').trigger('change');
        $('#modalItemHelpText').text('Select a group to load items');
    });
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
