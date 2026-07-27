@extends('layouts.admin')

@section('title', 'Invoices - Kanesan UBS Backend')

@section('page-title', 'Invoices')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Invoices</li>
@endsection

@section('card-title', 'Invoices')

@section('admin-content')

    <div class="my-3 text-right">
        <a href="/admin/invoice/resync" class="btn btn-sm btn-primary">Trigger Resync</a>
    </div>
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.invoices.index') }}" class="mb-4">
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
                        <option value="CN2" {{ in_array('CN2', $type ?? []) ? 'selected' : '' }}>CN2</option>
                        <option value="SO" {{ in_array('SO', $type ?? []) ? 'selected' : '' }}>SO</option>
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
            <div class="col-md-3">
                <div class="form-group">
                    <label>Reference No</label>
                    <input type="text" name="reference_no" class="form-control" value="{{ $referenceNo ?? '' }}" placeholder="Search by reference number">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Agent No</label>
                    <input type="text" name="agent_no" class="form-control" value="{{ $agentNo ?? '' }}" placeholder="Search by agent number">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    @php
                        $sortable = function($column, $title) use ($sort, $direction) {
                            $isCurrent = $sort === $column;
                            $nextDir = ($isCurrent && $direction === 'asc') ? 'desc' : 'asc';
                            $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDir]);
                            $icon = '';
                            if ($isCurrent) {
                                $icon = $direction === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                            } else {
                                $icon = ' <i class="fas fa-sort text-muted" style="opacity: 0.5;"></i>';
                            }
                            return '<a href="' . e($url) . '" class="text-white text-decoration-none d-block">' . e($title) . $icon . '</a>';
                        };
                    @endphp
                    <th>{!! $sortable('reference_no', 'Reference No') !!}</th>
                    <th>{!! $sortable('order_date', 'Date') !!}</th>
                    <th>{!! $sortable('type', 'Type') !!}</th>
                    <th>{!! $sortable('customer_code', 'Customer Code') !!}</th>
                    <th>{!! $sortable('customer_name', 'Customer Name') !!}</th>
                    <th>{!! $sortable('agent_no', 'Agent No') !!}</th>
                    <th class="text-right">{!! $sortable('net_amount', 'Net Amount') !!}</th>
                    <th class="text-right">Receipt Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ $order->reference_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge {{ $order->type == 'INV' ? 'badge-success' : (in_array($order->type, ['CN', 'CN2']) ? 'badge-warning' : 'badge-info') }}">
                                {{ $order->type }}
                            </span>
                        </td>
                        <td>{{ $order->customer_code }}</td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ $order->agent_no ?? 'N/A' }}</td>
                        <td class="text-right">RM {{ number_format($order->net_amount ?? 0, 2) }}</td>
                        <td class="text-right">
                            @if(!is_null($order->total_receipt_amount))
                                RM {{ number_format($order->total_receipt_amount, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.invoices.show', $order->id) }}" class="btn btn-sm btn-link p-0 text-info">
                                View
                            </a>
                            @if(in_array($order->type, ['INV', 'CN', 'CN2']))
                                <span class="mx-1">|</span>
                                @php
                                    $eInvoiceRequest = \App\Models\EInvoiceRequest::where('invoice_no', $order->reference_no)
                                        ->where('customer_code', $order->customer_code)
                                        ->first();
                                @endphp
                                @if($eInvoiceRequest)
                                    <a href="{{ route('admin.e-invoice-requests.edit', $eInvoiceRequest->id) }}" 
                                       target="_blank" class="btn btn-sm btn-link p-0 text-warning" title="View E-Invoice Request Details">
                                        E-Invoice (Requested)
                                    </a>
                                @else
                                    <a href="{{ route('e-invoice.form', ['invoice_no' => $order->reference_no, 'customer_code' => $order->customer_code, 'type' => $order->type, 'id' => $order->id]) }}" 
                                       class="btn btn-sm btn-link p-0 text-primary" target="_blank">
                                        E-Invoice
                                    </a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $orders->links() }}
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
});
</script>
@endpush
