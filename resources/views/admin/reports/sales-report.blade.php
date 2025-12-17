@extends('layouts.admin')

@section('title', 'Sales Report - Kanesan UBS Backend')

@section('page-title', 'Sales Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Sales Report</li>
@endsection

@section('card-title', 'Sales Report (INV & CN Orders & Receipt)')

@section('admin-content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.sales') }}" class="mb-4">
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
                <a href="{{ route('admin.reports.sales') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Summary Box -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-line"></i> Summary</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-money-bill-wave"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CA Sales</span>
                                    <span class="info-box-number">RM {{ number_format($caSalesTotal ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-file-invoice-dollar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CR Sales</span>
                                    <span class="info-box-number">RM {{ number_format($crSalesTotal ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-calculator"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Sales</span>
                                    <span class="info-box-number">RM {{ number_format($totalSales ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-undo"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Return</span>
                                    <span class="info-box-number">RM {{ number_format($returnsTotal ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger"><i class="fas fa-chart-bar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Nett Sales</span>
                                    <span class="info-box-number">RM {{ number_format($nettSales ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collection Summary Box -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-money-check-alt"></i> Collection Summary</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-money-bill"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CASH</span>
                                    <span class="info-box-number">RM {{ number_format($cashCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-mobile-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">E-WALLET</span>
                                    <span class="info-box-number">RM {{ number_format($ewalletCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-exchange-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">ONLINE TRANSFER</span>
                                    <span class="info-box-number">RM {{ number_format($onlineTransferCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-credit-card"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CARD</span>
                                    <span class="info-box-number">RM {{ number_format($cardCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-secondary"><i class="fas fa-file-invoice"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CHEQUE</span>
                                    <span class="info-box-number">RM {{ number_format($chequeCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box">
                                <span class="info-box-icon bg-dark"><i class="fas fa-calendar-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">PD CHEQUE</span>
                                    <span class="info-box-number">RM {{ number_format($pdChequeCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-calculator"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Collection</span>
                                    <span class="info-box-number">RM {{ number_format($totalCollection ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger"><i class="fas fa-balance-scale"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Account Balance</span>
                                    <span class="info-box-number">RM {{ number_format($accountBalance ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    <th>Status / Payment Type</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Combine orders and receipts, then sort by date
                    $combinedItems = collect();
                    
                    // Add orders
                    foreach($orders ?? [] as $order) {
                        $combinedItems->push([
                            'type' => 'order',
                            'reference_no' => $order->reference_no,
                            'date' => $order->order_date,
                            'order_type' => $order->type,
                            'customer_code' => $order->customer_code,
                            'customer_name' => $order->customer_name,
                            'agent_no' => $order->agent_no ?? 'N/A',
                            'amount' => $order->net_amount ?? 0,
                            'status' => $order->status ?? 'N/A',
                            'payment_type' => null,
                        ]);
                    }
                    
                    // Add receipts
                    foreach($receipts ?? [] as $receipt) {
                        $combinedItems->push([
                            'type' => 'receipt',
                            'reference_no' => $receipt->receipt_no,
                            'date' => $receipt->receipt_date,
                            'order_type' => 'RC',
                            'customer_code' => $receipt->customer_code,
                            'customer_name' => $receipt->customer_name,
                            'agent_no' => ($receipt->customer && $receipt->customer->agent_no) ? $receipt->customer->agent_no : 'N/A',
                            'amount' => $receipt->paid_amount ?? 0,
                            'status' => 'completed',
                            'payment_type' => $receipt->payment_type ?? 'N/A',
                        ]);
                    }
                    
                    // Sort by date descending
                    $combinedItems = $combinedItems->sortByDesc('date');
                @endphp
                
                @forelse($combinedItems as $item)
                    <tr>
                        <td>{{ $item['reference_no'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y') }}</td>
                        <td>
                            @if($item['type'] == 'receipt')
                                <span class="badge badge-primary">RC</span>
                            @else
                                <span class="badge {{ $item['order_type'] == 'INV' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $item['order_type'] }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $item['customer_code'] }}</td>
                        <td>{{ $item['customer_name'] }}</td>
                        <td>{{ $item['agent_no'] }}</td>
                        <td class="text-right">RM {{ number_format($item['amount'], 2) }}</td>
                        <td>
                            @if($item['type'] == 'receipt')
                                <span class="badge badge-info">{{ $item['payment_type'] }}</span>
                            @else
                                <span class="badge badge-{{ $item['status'] == 'completed' ? 'success' : ($item['status'] == 'pending' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($item['status']) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No records found for the selected criteria.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($combinedItems->count() > 0)
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total:</td>
                        <td class="text-right">RM {{ number_format($combinedItems->sum('amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
