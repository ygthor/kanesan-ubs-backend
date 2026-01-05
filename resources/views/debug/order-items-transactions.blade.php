@extends('layouts.admin')

@section('title', 'Order Items & Transactions Debug')

@section('admin-content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Items & Item Transactions Debug</h3>
                    <p class="card-text">Check if item_transactions are properly created for order_items</p>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="agent_no" class="form-label">Agent No</label>
                                <select name="agent_no" id="agent_no" class="form-select">
                                    <option value="">All Agents</option>
                                    @foreach($agentNos as $agent)
                                        <option value="{{ $agent }}" {{ $agent == $agentNo ? 'selected' : '' }}>
                                            {{ $agent }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="product_no" class="form-label">Product No</label>
                                <select name="product_no" id="product_no" class="form-select">
                                    <option value="">All Products</option>
                                    @foreach($productNos as $product)
                                        <option value="{{ $product }}" {{ $product == $productNo ? 'selected' : '' }}>
                                            {{ $product }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="{{ route('debug.order-items-transactions') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ $orderItems->total() }}</h5>
                                    <p class="card-text">Total Order Items</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ collect($debugData)->where('has_item_transaction', true)->count() }}</h5>
                                    <p class="card-text text-white">Have Transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ collect($debugData)->where('has_item_transaction', false)->count() }}</h5>
                                    <p class="card-text text-white">Missing Transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ collect($debugData)->where('has_item_transaction', true)->where('transaction_matches', false)->count() }}</h5>
                                    <p class="card-text text-white">Transaction Mismatches</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Order</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Order Type</th>
                                    <th>Expected Transaction</th>
                                    <th>Transaction Status</th>
                                    <th>Transaction Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($debugData as $data)
                                    <tr class="{{ $data['has_item_transaction'] ? ($data['transaction_matches'] ? 'table-success' : 'table-warning') : 'table-danger' }}">
                                        <td>
                                            <strong>{{ $data['order_item']->reference_no }}</strong><br>
                                            <small class="text-muted">{{ $data['order_item']->order_date }}</small><br>
                                            <small class="text-muted">{{ $data['order_item']->customer_name }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $data['order_item']->product_no }}</strong><br>
                                            <small class="text-muted">{{ Str::limit($data['order_item']->product_name, 30) }}</small>
                                        </td>
                                        <td>{{ number_format($data['order_item']->quantity, 2) }}</td>
                                        <td>
                                            <span class="badge
                                                @if($data['order_item']->type === 'INV') bg-primary
                                                @elseif($data['order_item']->type === 'CN') bg-success
                                                @elseif($data['order_item']->type === 'DO') bg-info
                                                @else bg-secondary
                                                @endif">
                                                {{ $data['order_item']->type }}
                                            </span>
                                            <br>
                                            <small class="text-muted">{{ $data['order_item']->agent_no }}</small>
                                        </td>
                                        <td>
                                            <span class="badge
                                                @if($data['expected_transaction_type'] === 'out') bg-danger
                                                @elseif($data['expected_transaction_type'] === 'in') bg-success
                                                @else bg-secondary
                                                @endif">
                                                {{ $data['expected_transaction_type'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($data['has_item_transaction'])
                                                @if($data['transaction_matches'])
                                                    <span class="badge bg-success">✓ Match</span>
                                                @else
                                                    <span class="badge bg-warning">⚠ Mismatch</span>
                                                @endif
                                            @else
                                                <span class="badge bg-danger">✗ Missing</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($data['item_transaction'])
                                                <div>
                                                    <strong>Type:</strong> {{ $data['item_transaction']->transaction_type }}<br>
                                                    <strong>Qty:</strong> {{ number_format($data['item_transaction']->quantity, 2) }}<br>
                                                    <strong>Stock:</strong> {{ number_format($data['item_transaction']->stock_before, 2) }} → {{ number_format($data['item_transaction']->stock_after, 2) }}<br>
                                                    <small class="text-muted">{{ $data['item_transaction']->CREATED_ON->format('d/m/Y H:i') }}</small>
                                                </div>
                                                @if($data['mismatch_details'])
                                                    <div class="mt-2">
                                                        <strong class="text-danger">Issues:</strong>
                                                        <ul class="mb-0">
                                                            @foreach($data['mismatch_details'] as $detail)
                                                                <li class="text-danger small">{{ $detail }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            @else
                                                <em class="text-muted">No transaction found</em>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$data['has_item_transaction'])
                                                <button type="button" class="btn btn-sm btn-success create-transaction-btn"
                                                        data-order-item-id="{{ $data['order_item']->id }}"
                                                        data-reference-no="{{ $data['order_item']->reference_no }}"
                                                        data-product-no="{{ $data['order_item']->product_no }}">
                                                    <i class="fas fa-plus"></i> Create
                                                </button>
                                            @else
                                                @if(strpos($data['item_transaction']->notes ?? '', 'Auto-created') !== false)
                                                    <button type="button" class="btn btn-sm btn-danger delete-transaction-btn"
                                                            data-transaction-id="{{ $data['item_transaction']->id }}"
                                                            data-reference-no="{{ $data['order_item']->reference_no }}">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                @else
                                                    <span class="text-muted small">Manual transaction</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    {{ $orderItems->links() }}

                    <!-- Legend -->
                    <div class="mt-4">
                        <h5>Legend:</h5>
                        <ul class="list-inline">
                            <li class="list-inline-item">
                                <span class="badge bg-success">✓ Match</span> - Transaction exists and matches expectations
                            </li>
                            <li class="list-inline-item">
                                <span class="badge bg-warning">⚠ Mismatch</span> - Transaction exists but has issues
                            </li>
                            <li class="list-inline-item">
                                <span class="badge bg-danger">✗ Missing</span> - No transaction found
                            </li>
                        </ul>
                        <p class="small text-muted">
                            <strong>Order Types:</strong> CN = Credit Note (stock in), INV = Invoice (stock out), DO = Delivery Order (stock out)<br>
                            <strong>Expected Transactions:</strong> CN should have 'in' transactions, INV/DO should have 'out' transactions
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for confirmation -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    let confirmCallback = null;

    // Create transaction button
    $('.create-transaction-btn').on('click', function() {
        const orderItemId = $(this).data('order-item-id');
        const referenceNo = $(this).data('reference-no');
        const productNo = $(this).data('product-no');

        $('#confirmMessage').text(`Create item transaction for ${referenceNo} - ${productNo}?`);
        confirmCallback = function() {
            createTransaction(orderItemId);
        };
        $('#confirmModal').modal('show');
    });

    // Delete transaction button
    $('.delete-transaction-btn').on('click', function() {
        const transactionId = $(this).data('transaction-id');
        const referenceNo = $(this).data('reference-no');

        $('#confirmMessage').text(`Delete auto-created transaction for ${referenceNo}?`);
        confirmCallback = function() {
            deleteTransaction(transactionId);
        };
        $('#confirmModal').modal('show');
    });

    // Confirm action
    $('#confirmAction').on('click', function() {
        if (confirmCallback) {
            confirmCallback();
        }
        $('#confirmModal').modal('hide');
    });

    function createTransaction(orderItemId) {
        $.post('{{ route("debug.create-transaction") }}', {
            order_item_id: orderItemId,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.error);
            }
        })
        .fail(function() {
            alert('Failed to create transaction');
        });
    }

    function deleteTransaction(transactionId) {
        $.post('{{ route("debug.delete-transaction") }}', {
            transaction_id: transactionId,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.error);
            }
        })
        .fail(function() {
            alert('Failed to delete transaction');
        });
    }
});
</script>
@endsection
