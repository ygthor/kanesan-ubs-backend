@extends('layouts.admin')

@section('title', 'Stock Management - Kanesan UBS Backend')

@section('page-title', 'Stock Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Management</li>
@endsection

@section('card-title', 'Stock Management')

@section('card-tools')
    <a href="{{ route('inventory.stock-management.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Create Stock Transaction
    </a>
@endsection

@section('admin-content')
    <div class="table-responsive">
        <table class="table table-striped table-hover" id="stockSummaryTable">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>Current Stock</th>
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
                        <td data-sort="{{ $item['current_stock'] }}"><strong>{{ number_format($item['current_stock'], 2) }}</strong></td>
                        <td>{{ $item['UNIT'] ?? 'N/A' }}</td>
                        <td data-sort="{{ $item['PRICE'] ?? 0 }}">{{ number_format($item['PRICE'] ?? 0, 2) }}</td>
                        <td>
                            <a href="{{ route('inventory.stock-management.item.transactions', $item['ITEMNO']) }}" class="btn btn-sm btn-info" title="View transactions for this item">
                                <i class="fas fa-history"></i> View Transactions
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No items found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Custom sorting function for numeric data-sort attribute
    $.fn.dataTable.ext.order['dom-data-sort-num'] = function(settings, col) {
        return this.api().column(col, {order: 'index'}).nodes().map(function(td, i) {
            return $(td).attr('data-sort') * 1;
        });
    };
    
    // Initialize DataTables
    $('#stockSummaryTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[0, 'asc']], // Sort by Item Code by default
        columnDefs: [
            {
                targets: [5], // Actions column
                orderable: false,
                searchable: false
            },
            {
                targets: [2, 4], // Current Stock and Price columns
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
});
</script>
@endpush
