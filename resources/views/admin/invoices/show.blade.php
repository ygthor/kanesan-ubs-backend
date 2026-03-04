@extends('layouts.admin')

@section('title', 'Invoice Details - Kanesan UBS Backend')

@section('page-title', 'Invoice Details')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
    <li class="breadcrumb-item active">{{ $order->reference_no }}</li>
@endsection

@section('card-title', 'Invoice Details - ' . $order->reference_no)

@section('admin-content')
    <!-- E-Invoice Request Status -->
    @if($order->type == 'INV' || $order->type == 'CN')
        <div class="alert {{ $eInvoiceRequest ? 'alert-warning' : 'alert-info' }} mb-4 alert-persistent">
            <strong><i class="fas fa-file-invoice"></i> E-Invoice Request Status:</strong>
            @if($eInvoiceRequest)
                <span class="badge badge-warning">Requested</span>
                Submitted on {{ $eInvoiceRequest->created_at->format('d/m/Y H:i:s') }} by {{ $eInvoiceRequest->email_address }}
                <a href="{{ route('admin.e-invoice-requests.edit', $eInvoiceRequest->id) }}" target="_blank" class="btn btn-sm btn-outline-warning ml-2">
                    <i class="fas fa-eye"></i> View Request Details
                </a>
            @else
                <span class="badge badge-secondary">Not Requested</span>
                <a href="{{ route('e-invoice.form', ['invoice_no' => $order->reference_no, 'customer_code' => $order->customer_code, 'type' => $order->type, 'id' => $order->id]) }}" 
                   class="btn btn-sm btn-outline-primary ml-2" target="_blank">
                    <i class="fas fa-file-invoice"></i> Request E-Invoice
                </a>
            @endif
        </div>
    @endif

    <div class="row">
        <!-- Invoice Information -->
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Invoice Information</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; font-weight: 600; background-color: #f8f9fa;">Reference No</td>
                                    <td><strong>{{ $order->reference_no }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Type</td>
                                    <td>
                                        <span class="badge {{ $order->type == 'INV' ? 'badge-success' : ($order->type == 'CN' ? 'badge-warning' : 'badge-info') }}">
                                            {{ $order->type }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Date</td>
                                    <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Agent No</td>
                                    <td>{{ $order->agent_no ?? 'N/A' }}</td>
                                </tr>
                                @if($order->credit_invoice_no)
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Credit Invoice No</td>
                                        <td>{{ $order->credit_invoice_no }}</td>
                                    </tr>
                                @endif
                                @if($order->remarks)
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Remarks</td>
                                        <td>{{ $order->remarks }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Customer Information</h5>
                </div>
                <div class="card-body">
                    @if($order->customer)
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <td style="width: 30%; font-weight: 600; background-color: #f8f9fa;">Customer Code</td>
                                        <td>{{ $order->customer->customer_code ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Company/Individual Name</td>
                                        <td>{{ $order->customer->company_name ?? $order->customer->name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Contact Person</td>
                                        <td>{{ $order->customer->contact_person ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Address</td>
                                        <td>
                                            @if($order->customer->address)
                                                {{ $order->customer->address }}
                                            @else
                                                {{ $order->customer->address1 ?? '' }}
                                                @if($order->customer->address2) {{ $order->customer->address2 }} @endif
                                                @if($order->customer->address3) {{ $order->customer->address3 }} @endif
                                                @if($order->customer->postcode) {{ $order->customer->postcode }} @endif
                                                @if($order->customer->state) {{ $order->customer->state }} @endif
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Phone</td>
                                        <td>{{ $order->customer->telephone1 ?? $order->customer->phone ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Email</td>
                                        <td>{{ $order->customer->email ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Tax ID</td>
                                        <td>{{ $order->customer->tax_id ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">Customer information not available.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Order Items ({{ $order->items->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($order->items && $order->items->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Product No</th>
                                        <th>Product Name</th>
                                        <th>Description</th>
                                        <th>SKU Code</th>
                                        <th class="text-right">Quantity</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-right">Discount</th>
                                        <th class="text-right">Amount</th>
                                        <th>Free Good</th>
                                        <th>Trade Return</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $item->product_no ?? 'N/A' }}</td>
                                            <td>{{ $item->product_name ?? 'N/A' }}</td>
                                            <td>{{ $item->description ?? 'N/A' }}</td>
                                            <td>{{ $item->sku_code ?? 'N/A' }}</td>
                                            <td class="text-right">{{ number_format($item->quantity ?? 0, 2) }}</td>
                                            <td class="text-right">RM {{ number_format($item->unit_price ?? 0, 2) }}</td>
                                            <td class="text-right">RM {{ number_format($item->discount ?? 0, 2) }}</td>
                                            <td class="text-right"><strong>RM {{ number_format($item->amount ?? 0, 2) }}</strong></td>
                                            <td>
                                                @if($item->is_free_good)
                                                    <span class="badge badge-info">Yes</span>
                                                @else
                                                    <span class="badge badge-secondary">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($item->is_trade_return)
                                                    <span class="badge badge-warning">Yes</span>
                                                @else
                                                    <span class="badge badge-secondary">No</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No items found for this order.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Financial Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tbody>
                                <tr>
                                    <td style="width: 50%; font-weight: 600; background-color: #f8f9fa;">Gross Amount</td>
                                    <td class="text-right">RM {{ number_format($order->gross_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Discount</td>
                                    <td class="text-right">RM {{ number_format($order->discount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Tax ({{ number_format($order->tax1_percentage ?? 0, 2) }}%)</td>
                                    <td class="text-right">RM {{ number_format($order->tax1 ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; background-color: #f8f9fa;">Grand Amount</td>
                                    <td class="text-right"><strong>RM {{ number_format($order->grand_amount ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td style="font-weight: 700; background-color: #e9ecef;">Net Amount</td>
                                    <td class="text-right" style="font-weight: 700; background-color: #e9ecef;">
                                        <strong>RM {{ number_format($order->net_amount ?? 0, 2) }}</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipts -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-money-check-alt"></i> Receipts ({{ $receipts->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($receipts && $receipts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Date</th>
                                        <th class="text-right">Amount Applied</th>
                                        <th>Payment Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($receipts as $receipt)
                                        <tr>
                                            <td>{{ $receipt->receipt_no }}</td>
                                            <td>{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</td>
                                            <td class="text-right">RM {{ number_format($receipt->amount_applied ?? 0, 2) }}</td>
                                            <td>{{ $receipt->payment_type ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <td colspan="2" style="font-weight: 700;">Total Received</td>
                                        <td class="text-right" style="font-weight: 700;">
                                            RM {{ number_format($receipts->sum('amount_applied') ?? 0, 2) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No receipts found for this invoice.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Linked Credit Notes (if invoice) -->
        @if($order->type == 'INV' && $linkedCreditNotes && $linkedCreditNotes->count() > 0)
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Linked Credit Notes ({{ $linkedCreditNotes->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Reference No</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th class="text-right">Net Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($linkedCreditNotes as $cn)
                                        <tr>
                                            <td>{{ $cn->reference_no }}</td>
                                            <td>{{ \Carbon\Carbon::parse($cn->order_date)->format('d/m/Y') }}</td>
                                            <td>{{ $cn->customer_name }}</td>
                                            <td class="text-right">RM {{ number_format($cn->net_amount ?? 0, 2) }}</td>
                                            <td>
                                                <a href="{{ route('admin.invoices.show', $cn->id) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Linked Invoice (if credit note) -->
        @if($order->type == 'CN' && $linkedInvoice)
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Linked Invoice</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <td style="width: 30%; font-weight: 600; background-color: #f8f9fa;">Reference No</td>
                                        <td>{{ $linkedInvoice->reference_no }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Date</td>
                                        <td>{{ \Carbon\Carbon::parse($linkedInvoice->order_date)->format('d/m/Y H:i:s') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Customer</td>
                                        <td>{{ $linkedInvoice->customer_name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Net Amount</td>
                                        <td>RM {{ number_format($linkedInvoice->net_amount ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight: 600; background-color: #f8f9fa;">Actions</td>
                                        <td>
                                            <a href="{{ route('admin.invoices.show', $linkedInvoice->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Invoice
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
    </div>
@endsection

@push('styles')
    <style>
        /* Ensure content area properly aligns with sidebar */
        /* AdminLTE's content-wrapper handles sidebar spacing automatically,
           but we ensure the content doesn't overflow or extend beyond boundaries */
        
        /* Prevent horizontal overflow in content area */
        .content-wrapper .content {
            overflow-x: hidden;
        }
        
        /* Ensure container-fluid respects boundaries */
        .content-wrapper .content .container-fluid {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Ensure nested Bootstrap grid respects container boundaries */
        .content-wrapper .content .container-fluid > .row {
            margin-left: -15px;
            margin-right: -15px;
        }
        
        .content-wrapper .content .container-fluid > .row > [class*="col-"] {
            padding-left: 15px;
            padding-right: 15px;
        }
        
        /* Prevent cards from overflowing their containers */
        .card {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Ensure table responsiveness */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
@endpush