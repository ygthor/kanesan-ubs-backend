@extends('layouts.admin')

@section('title', 'Invoice Resync - Kanesan UBS Backend')

@section('page-title', 'Invoice Resync')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
    <li class="breadcrumb-item active">Resync</li>
@endsection

@section('card-title', 'Invoice Resync')

@section('admin-content')
    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-md-12">
            <button type="button" id="checkAllBtn" class="btn btn-outline-primary mr-2">
                <i class="fas fa-check-square"></i> Check All
            </button>
            <button type="button" id="uncheckAllBtn" class="btn btn-outline-secondary mr-2">
                <i class="fas fa-square"></i> Uncheck All
            </button>
            <button type="button" id="updateBtn" class="btn btn-success" disabled>
                <i class="fas fa-sync-alt"></i> Update Last Modification Date
            </button>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.invoice.resync') }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type[]" class="form-control" id="typeSelect" multiple>
                        <option value="INV" {{ in_array('INV', $type ?? []) ? 'selected' : '' }}>INV</option>
                        <option value="DO" {{ in_array('DO', $type ?? []) ? 'selected' : '' }}>DO</option>
                        <option value="CN" {{ in_array('CN', $type ?? []) ? 'selected' : '' }}>CN</option>
                        <option value="SO" {{ in_array('SO', $type ?? []) ? 'selected' : '' }}>SO</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">All Customers</option>
                        @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}" {{ ($customerId ?? '') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->customer_code }} - {{ $customer->company_name ?? $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Reference No (Comma-separated)</label>
                    <textarea name="reference_no" class="form-control" rows="3" placeholder="Paste comma-separated reference numbers&#10;e.g., ORD001,ORD002,ORD003">{{ $referenceNo ?? '' }}</textarea>
                    <small class="form-text text-muted">Paste comma-separated values directly</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Agent No</label>
                    <input type="text" name="agent_no" class="form-control" value="{{ $agentNo ?? '' }}" placeholder="Search by agent number">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Per Page</label>
                    <select name="per_page" class="form-control">
                        <option value="15" {{ ($perPage ?? 15) == 15 ? 'selected' : '' }}>15</option>
                        <option value="100" {{ ($perPage ?? 15) == 100 ? 'selected' : '' }}>100</option>
                        <option value="500" {{ ($perPage ?? 15) == 500 ? 'selected' : '' }}>500</option>
                        <option value="1000" {{ ($perPage ?? 15) == 1000 ? 'selected' : '' }}>1000</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.invoice.resync') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <form id="resyncForm">
        @csrf
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllCheckbox" class="">
                        </th>
                        <th>Reference No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Customer Code</th>
                        <th>Customer Name</th>
                        <th>Agent No</th>
                        <th>Net Amount</th>
                        <th>Last Modified</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <input type="checkbox" name="order_ids[]" value="{{ $order->id }}" class="order-checkbox">
                            </td>
                            <td>{{ $order->reference_no }}</td>
                            <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge {{ $order->type == 'INV' ? 'badge-success' : ($order->type == 'CN' ? 'badge-warning' : 'badge-info') }}">
                                    {{ $order->type }}
                                </span>
                            </td>
                            <td>{{ $order->customer_code }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ $order->agent_no ?? 'N/A' }}</td>
                            <td class="text-right">RM {{ number_format($order->net_amount ?? 0, 2) }}</td>
                            <td>{{ $order->updated_at ? \Carbon\Carbon::parse($order->updated_at)->format('d/m/Y H:i') : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $orders->appends(request()->query())->links() }}
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    // Initialize select2 for type dropdown
    $('#typeSelect').select2({
        placeholder: "Select invoice types",
        allowClear: true,
        width: '100%'
    });
    // Update button state based on selected checkboxes
    function updateButtonState() {
        const checkedBoxes = $('.order-checkbox:checked');
        const updateBtn = $('#updateBtn');
        const checkAllBtn = $('#checkAllBtn');
        const uncheckAllBtn = $('#uncheckAllBtn');

        if (checkedBoxes.length > 0) {
            updateBtn.prop('disabled', false);
            updateBtn.html('<i class="fas fa-sync-alt"></i> Update Last Modification Date (' + checkedBoxes.length + ')');
        } else {
            updateBtn.prop('disabled', true);
            updateBtn.html('<i class="fas fa-sync-alt"></i> Update Last Modification Date');
        }
    }

    // Handle individual checkbox changes
    $('.order-checkbox').on('change', function() {
        updateButtonState();
        const selectAllCheckbox = $('#selectAllCheckbox');
        const totalCheckboxes = $('.order-checkbox').length;
        const checkedBoxes = $('.order-checkbox:checked').length;

        selectAllCheckbox.prop('checked', checkedBoxes === totalCheckboxes);
    });

    // Handle select all checkbox
    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.order-checkbox').prop('checked', isChecked);
        updateButtonState();
    });

    // Check all button
    $('#checkAllBtn').on('click', function() {
        $('.order-checkbox').prop('checked', true);
        $('#selectAllCheckbox').prop('checked', true);
        updateButtonState();
    });

    // Uncheck all button
    $('#uncheckAllBtn').on('click', function() {
        $('.order-checkbox').prop('checked', false);
        $('#selectAllCheckbox').prop('checked', false);
        updateButtonState();
    });

    // Update modification date button
    $('#updateBtn').on('click', function() {
        const checkedBoxes = $('.order-checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('Please select at least one invoice.');
            return;
        }

        if (!confirm('Are you sure you want to update the last modification date for ' + checkedBoxes.length + ' invoice(s)? This will trigger UBS sync.')) {
            return;
        }

        const formData = new FormData(document.getElementById('resyncForm'));

        // Show loading state
        const originalText = $(this).html();
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

        $.ajax({
            url: '{{ route("admin.invoice.resync.update") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload(); // Reload to show updated timestamps
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                let message = 'An error occurred while updating invoices.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
            },
            complete: function() {
                $('#updateBtn').prop('disabled', false).html(originalText);
            }
        });
    });

    // Initialize button state
    updateButtonState();
});
</script>
@endpush