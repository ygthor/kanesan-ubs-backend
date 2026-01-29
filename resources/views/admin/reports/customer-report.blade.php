@extends('layouts.admin')

@section('title', 'Customer Report - Kanesan UBS Backend')

@section('page-title', 'Customer Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Customer Report</li>
@endsection

@section('card-title', 'Customer Report')

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
    <form method="GET" action="{{ route('admin.reports.customers') }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer Search</label>
                    <input type="text" name="customer_search" class="form-control" value="{{ $customerSearch ?? '' }}" placeholder="Code, Name, or Company Name">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Customer Type</label>
                    <select name="customer_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="CREDITOR" {{ ($customerType ?? '') == 'CREDITOR' ? 'selected' : '' }}>Creditor</option>
                        <option value="Cash" {{ ($customerType ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
                        <option value="Cash Sales" {{ ($customerType ?? '') == 'Cash Sales' ? 'selected' : '' }}>Cash Sales</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Territory</label>
                    <select name="territory_id" class="form-control">
                        <option value="">All Territories</option>
                        @foreach(\App\Models\Territory::orderBy('area')->get() as $territory)
                            <option value="{{ $territory->id }}" {{ ($territoryId ?? '') == $territory->id ? 'selected' : '' }}>
                                {{ $territory->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach($agents ?? [] as $agent)
                            <option value="{{ $agent->name }}" {{ ($agentNo ?? '') == $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <a href="{{ route('admin.reports.customers') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <form id="customerUpdateForm">
        @csrf
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllCheckbox" class="">
                        </th>
                        <th>Customer Code</th>
                        <th>Name</th>
                        <th>Company Name</th>
                        <th>Type</th>
                        <th>Agent No</th>
                        <th>Territory</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Last Modified</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers ?? [] as $customer)
                        <tr>
                            <td>
                                <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" class="customer-checkbox">
                            </td>
                            <td>{{ $customer->customer_code }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->company_name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-{{ $customer->customer_type == 'CREDITOR' ? 'primary' : 'success' }}">
                                    {{ $customer->customer_type }}
                                </span>
                            </td>
                            <td>{{ $customer->agent_no ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $territory = \App\Models\Territory::where('area', $customer->territory)->first();
                                @endphp
                                {{ $territory->description ?? $customer->territory ?? 'N/A' }}
                            </td>
                            <td>{{ $customer->phone ?? 'N/A' }}</td>
                            <td>{{ $customer->address1 ?? '' }} {{ $customer->address2 ?? '' }}</td>
                            <td>{{ $customer->updated_at ? \Carbon\Carbon::parse($customer->updated_at)->format('d/m/Y H:i') : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">No customers found for the selected criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(isset($customers) && $customers->count() > 0)
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="10" class="text-center">Total Customers: {{ $customers->count() }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    function updateButtonState() {
        const checkedBoxes = $('.customer-checkbox:checked');
        const updateBtn = $('#updateBtn');

        if (checkedBoxes.length > 0) {
            updateBtn.prop('disabled', false);
            updateBtn.html('<i class="fas fa-sync-alt"></i> Update Last Modification Date (' + checkedBoxes.length + ')');
        } else {
            updateBtn.prop('disabled', true);
            updateBtn.html('<i class="fas fa-sync-alt"></i> Update Last Modification Date');
        }
    }

    $('.customer-checkbox').on('change', function() {
        updateButtonState();
        const selectAllCheckbox = $('#selectAllCheckbox');
        const totalCheckboxes = $('.customer-checkbox').length;
        const checkedBoxes = $('.customer-checkbox:checked').length;

        selectAllCheckbox.prop('checked', checkedBoxes === totalCheckboxes);
    });

    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.customer-checkbox').prop('checked', isChecked);
        updateButtonState();
    });

    $('#checkAllBtn').on('click', function() {
        $('.customer-checkbox').prop('checked', true);
        $('#selectAllCheckbox').prop('checked', true);
        updateButtonState();
    });

    $('#uncheckAllBtn').on('click', function() {
        $('.customer-checkbox').prop('checked', false);
        $('#selectAllCheckbox').prop('checked', false);
        updateButtonState();
    });

    $('#updateBtn').on('click', function() {
        const checkedBoxes = $('.customer-checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('Please select at least one customer.');
            return;
        }

        if (!confirm('Are you sure you want to update the last modification date for ' + checkedBoxes.length + ' customer(s)?')) {
            return;
        }

        const formData = new FormData(document.getElementById('customerUpdateForm'));

        const originalText = $(this).html();
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

        $.ajax({
            url: '{{ route("admin.reports.customers.update-modification-date") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                let message = 'An error occurred while updating customers.';
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

    updateButtonState();
});
</script>
@endpush
