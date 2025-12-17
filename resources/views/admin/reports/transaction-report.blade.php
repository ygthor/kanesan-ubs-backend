@extends('layouts.admin')

@section('title', 'Transaction Report - Kanesan UBS Backend')

@section('page-title', 'Transaction Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Transaction Report</li>
@endsection

@section('card-title', 'Transaction Report (All Orders)')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.transactions') }}" class="mb-4">
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
            <div class="col-md-2">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="INV" {{ ($type ?? '') == 'INV' ? 'selected' : '' }}>INV</option>
                        <option value="DO" {{ ($type ?? '') == 'DO' ? 'selected' : '' }}>DO</option>
                        <option value="CN" {{ ($type ?? '') == 'CN' ? 'selected' : '' }}>CN</option>
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
            <div class="col-md-2">
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
                <a href="{{ route('admin.reports.transactions') }}" class="btn btn-secondary">
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
                    <th>Reference No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Agent No</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders ?? [] as $order)
                    <tr class="order-row" style="cursor: pointer;" data-order-id="{{ $order->id }}" data-order-ref="{{ $order->reference_no }}">
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
                        <td>
                            <span class="badge badge-{{ $order->status == 'completed' ? 'success' : ($order->status == 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($order->status ?? 'N/A') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No orders found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($orders) && $orders->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total:</td>
                        <td class="text-right">RM {{ number_format($orders->sum('net_amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <!-- Order Detail Modal -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailModalLabel">Order Details - <span id="modalOrderRef"></span></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="border: none; background: none; font-size: 1.5rem; opacity: 0.5; cursor: pointer;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="orderLoadingSpinner" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p class="mt-3">Loading order details...</p>
                    </div>
                    <div id="orderDetailContent" style="display: none;">
                        <!-- Order Info -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Customer:</strong> <span id="orderCustomerName"></span><br>
                                <strong>Date:</strong> <span id="orderDate"></span><br>
                                <strong>Agent:</strong> <span id="orderAgent"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Gross Amount:</strong> <span id="orderGrossAmount"></span><br>
                                <strong>Discount:</strong> <span id="orderDiscount"></span><br>
                                <strong>Tax:</strong> <span id="orderTax"></span><br>
                                <strong>Net Amount:</strong> <span id="orderNetAmount" class="font-weight-bold"></span>
                            </div>
                        </div>
                        <hr>
                        <!-- Order Items Table -->
                        <h6>Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Product No</th>
                                        <th>Product Name</th>
                                        <th class="text-right">Quantity</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-right">Discount</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="orderItemsTableBody">
                                    <!-- Data will be loaded here -->
                                </tbody>
                                <tfoot class="font-weight-bold">
                                    <tr>
                                        <td colspan="6" class="text-right">Total:</td>
                                        <td class="text-right" id="orderItemsTotal">RM 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div id="orderDetailError" style="display: none;" class="alert alert-danger">
                        <p>Error loading order details. Please try again.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize modal instance
    const orderModalElement = document.getElementById('orderDetailModal');
    const orderModal = new bootstrap.Modal(orderModalElement);
    
    // Handle modal close events
    orderModalElement.addEventListener('hidden.bs.modal', function () {
        // Reset modal content when closed
        $('#orderLoadingSpinner').show();
        $('#orderDetailContent').hide();
        $('#orderDetailError').hide();
        $('#orderItemsTableBody').empty();
    });
    
    // Fallback close button handler
    $(orderModalElement).find('.close, [data-bs-dismiss="modal"]').on('click', function() {
        orderModal.hide();
    });
    
    // Click handler for order rows
    $('.order-row').click(function() {
        const orderId = $(this).data('order-id');
        const orderRef = $(this).data('order-ref');
        
        // Show modal
        $('#modalOrderRef').text(orderRef);
        orderModal.show();
        
        // Reset modal content
        $('#orderLoadingSpinner').show();
        $('#orderDetailContent').hide();
        $('#orderDetailError').hide();
        $('#orderItemsTableBody').empty();
        
        // Fetch order detail data
        $.ajax({
            url: '/admin/reports/transactions/' + orderId + '/detail',
            method: 'GET',
            success: function(response) {
                $('#orderLoadingSpinner').hide();
                
                if (response.error) {
                    $('#orderDetailError').show();
                    return;
                }
                
                if (response.data) {
                    const order = response.data;
                    
                    // Set order info
                    $('#orderCustomerName').text(order.customer_name || 'N/A');
                    $('#orderDate').text(order.order_date ? new Date(order.order_date).toLocaleDateString('en-GB') : 'N/A');
                    $('#orderAgent').text(order.agent_no || 'N/A');
                    $('#orderGrossAmount').text('RM ' + parseFloat(order.gross_amount || 0).toFixed(2));
                    $('#orderDiscount').text('RM ' + parseFloat(order.discount || 0).toFixed(2));
                    $('#orderTax').text('RM ' + parseFloat(order.tax1 || 0).toFixed(2));
                    $('#orderNetAmount').text('RM ' + parseFloat(order.net_amount || 0).toFixed(2));
                    
                    // Set order items
                    if (order.items && order.items.length > 0) {
                        let tableBody = '';
                        let totalAmount = 0;
                        order.items.forEach(function(item, index) {
                            const amount = parseFloat(item.amount || 0);
                            totalAmount += amount;
                            tableBody += '<tr>';
                            tableBody += '<td>' + (index + 1) + '</td>';
                            tableBody += '<td>' + (item.product_no || 'N/A') + '</td>';
                            tableBody += '<td>' + (item.product_name || item.description || 'N/A') + '</td>';
                            tableBody += '<td class="text-right">' + parseFloat(item.quantity || 0).toFixed(2) + '</td>';
                            tableBody += '<td class="text-right">RM ' + parseFloat(item.unit_price || 0).toFixed(2) + '</td>';
                            tableBody += '<td class="text-right">RM ' + parseFloat(item.discount || 0).toFixed(2) + '</td>';
                            tableBody += '<td class="text-right">RM ' + amount.toFixed(2) + '</td>';
                            tableBody += '</tr>';
                        });
                        
                        $('#orderItemsTableBody').html(tableBody);
                        $('#orderItemsTotal').text('RM ' + totalAmount.toFixed(2));
                    } else {
                        $('#orderItemsTableBody').html('<tr><td colspan="7" class="text-center">No items found for this order.</td></tr>');
                    }
                    
                    $('#orderDetailContent').show();
                } else {
                    $('#orderDetailError').show();
                }
            },
            error: function(xhr, status, error) {
                $('#orderLoadingSpinner').hide();
                $('#orderDetailError').show();
                console.error('Error loading order detail:', error);
            }
        });
    });
});
</script>
@endpush
