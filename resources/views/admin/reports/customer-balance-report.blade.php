@extends('layouts.admin')

@section('title', 'Customer Balance Report - Kanesan UBS Backend')

@section('page-title', 'Customer Balance Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Customer Balance Report</li>
@endsection

@section('card-title', 'Customer Balance Report (Receipts - INV + CN)')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.customer-balance') }}" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? date('Y-01-01') }}" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? date('Y-m-d') }}" required>
                </div>
            </div>
            <div class="col-md-3">
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
                    <label>Customer Search</label>
                    <input type="text" name="customer_search" class="form-control" value="{{ $customerSearch ?? '' }}" placeholder="Code or Name">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.reports.customer-balance') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Agent No</th>
                    <th class="text-right">Total Receipts</th>
                    <th class="text-right">Total INV</th>
                    <th class="text-right">Total CN</th>
                    <th class="text-right">Balance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customerBalances ?? [] as $balance)
                    <tr>
                        <td>{{ $balance['customer']->customer_code }}</td>
                        <td>{{ $balance['customer']->name }}</td>
                        <td>{{ $balance['customer']->agent_no ?? 'N/A' }}</td>
                        <td class="text-right">RM {{ number_format($balance['total_receipts'], 2) }}</td>
                        <td class="text-right">RM {{ number_format($balance['total_inv'], 2) }}</td>
                        <td class="text-right">RM {{ number_format($balance['total_cn'], 2) }}</td>
                        <td class="text-right font-weight-bold {{ $balance['balance'] < 0 ? 'text-danger' : ($balance['balance'] > 0 ? 'text-success' : '') }}">
                            RM {{ number_format($balance['balance'], 2) }}
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info view-detail" 
                                    data-customer-id="{{ $balance['customer']->id }}"
                                    data-customer-name="{{ $balance['customer']->name }}"
                                    data-from-date="{{ $fromDate ?? date('Y-01-01') }}"
                                    data-to-date="{{ $toDate ?? date('Y-m-d') }}">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No customer balances found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($customerBalances) && count($customerBalances) > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="3" class="text-right">Grand Total:</td>
                        <td class="text-right">RM {{ number_format(collect($customerBalances)->sum('total_receipts'), 2) }}</td>
                        <td class="text-right">RM {{ number_format(collect($customerBalances)->sum('total_inv'), 2) }}</td>
                        <td class="text-right">RM {{ number_format(collect($customerBalances)->sum('total_cn'), 2) }}</td>
                        <td class="text-right">RM {{ number_format(collect($customerBalances)->sum('balance'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <!-- P&L Detail Modal -->
    <div class="modal fade" id="plDetailModal" tabindex="-1" role="dialog" aria-labelledby="plDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="plDetailModalLabel">P&L Detail - <span id="modalCustomerName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="loadingSpinner" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p class="mt-3">Loading details...</p>
                    </div>
                    <div id="plDetailContent" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-right">Credit</th>
                                        <th class="text-right">Debit</th>
                                    </tr>
                                </thead>
                                <tbody id="plDetailTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                                <tfoot class="font-weight-bold">
                                    <tr>
                                        <td>Balance:</td>
                                        <td class="text-right" id="totalCredit">RM 0.00</td>
                                        <td class="text-right" id="totalDebit">RM 0.00</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-right">Net Balance:</td>
                                        <td class="text-right" id="netBalance">RM 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div id="plDetailError" style="display: none;" class="alert alert-danger">
                        <p>Error loading details. Please try again.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.view-detail').click(function() {
        const customerId = $(this).data('customer-id');
        const customerName = $(this).data('customer-name');
        const fromDate = $(this).data('from-date');
        const toDate = $(this).data('to-date');
        
        // Show modal
        $('#modalCustomerName').text(customerName);
        $('#plDetailModal').modal('show');
        
        // Reset modal content
        $('#loadingSpinner').show();
        $('#plDetailContent').hide();
        $('#plDetailError').hide();
        $('#plDetailTableBody').empty();
        
        // Fetch detail data
        $.ajax({
            url: '/admin/reports/customer-balance/' + customerId + '/detail',
            method: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                
                if (response.pl_data && response.pl_data.length > 0) {
                    let tableBody = '';
                    response.pl_data.forEach(function(item) {
                        const date = new Date(item.date).toLocaleDateString('en-GB');
                        tableBody += '<tr>';
                        tableBody += '<td>' + item.description + ' (' + date + ')</td>';
                        tableBody += '<td class="text-right">' + (item.credit > 0 ? 'RM ' + parseFloat(item.credit).toFixed(2) : '-') + '</td>';
                        tableBody += '<td class="text-right">' + (item.debit > 0 ? 'RM ' + parseFloat(item.debit).toFixed(2) : '-') + '</td>';
                        tableBody += '</tr>';
                    });
                    
                    $('#plDetailTableBody').html(tableBody);
                    $('#totalCredit').text('RM ' + parseFloat(response.total_credit || 0).toFixed(2));
                    $('#totalDebit').text('RM ' + parseFloat(response.total_debit || 0).toFixed(2));
                    
                    const balance = parseFloat(response.balance || 0);
                    $('#netBalance').text('RM ' + balance.toFixed(2));
                    $('#netBalance').removeClass('text-danger text-success');
                    if (balance < 0) {
                        $('#netBalance').addClass('text-danger');
                    } else if (balance > 0) {
                        $('#netBalance').addClass('text-success');
                    }
                    
                    $('#plDetailContent').show();
                } else {
                    $('#plDetailTableBody').html('<tr><td colspan="3" class="text-center">No transactions found for this period.</td></tr>');
                    $('#plDetailContent').show();
                }
            },
            error: function(xhr, status, error) {
                $('#loadingSpinner').hide();
                $('#plDetailError').show();
                console.error('Error loading detail:', error);
            }
        });
    });
});
</script>
@endpush
