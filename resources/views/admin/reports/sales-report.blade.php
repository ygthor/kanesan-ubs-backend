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

    <!-- Summary Tables -->
    <div class="row mb-4">
        <!-- Sales Summary Table -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-line"></i> Sales</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <td class="font-weight-bold">CA Sales</td>
                                <td class="text-right">RM {{ number_format($caSalesTotal ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">CR Sales</td>
                                <td class="text-right">RM {{ number_format($crSalesTotal ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Total Sales</td>
                                <td class="text-right">RM {{ number_format($totalSales ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Return</td>
                                <td class="text-right">RM {{ number_format($returnsTotal ?? 0, 2) }}</td>
                            </tr>
                            <tr class="bg-light">
                                <td class="font-weight-bold">Nett Sales</td>
                                <td class="text-right font-weight-bold">RM {{ number_format($nettSales ?? 0, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Collection Summary Table -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-money-check-alt"></i> Collection</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <td class="font-weight-bold">CASH</td>
                                <td class="text-right">RM {{ number_format($cashCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">E-WALLET</td>
                                <td class="text-right">RM {{ number_format($ewalletCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">ONLINE TRANSFER</td>
                                <td class="text-right">RM {{ number_format($onlineTransferCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">CARD</td>
                                <td class="text-right">RM {{ number_format($cardCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">CHEQUE</td>
                                <td class="text-right">RM {{ number_format($chequeCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">PD CHEQUE</td>
                                <td class="text-right">RM {{ number_format($pdChequeCollection ?? 0, 2) }}</td>
                            </tr>
                            <tr class="bg-light">
                                <td class="font-weight-bold">Total Collection</td>
                                <td class="text-right font-weight-bold">RM {{ number_format($totalCollection ?? 0, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Balance -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <h4 class="mb-0">
                                <strong>Account Balance = RM {{ number_format($accountBalance ?? 0, 2) }}</strong>
                            </h4>
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
                    @php
                        $totalINV = $combinedItems->where('order_type', 'INV')->sum('amount');
                        $totalCN = $combinedItems->where('order_type', 'CN')->sum('amount');
                        $totalRC = $combinedItems->where('order_type', 'RC')->sum('amount');
                    @endphp
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total INV:</td>
                        <td class="text-right">RM {{ number_format($totalINV, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total CN:</td>
                        <td class="text-right">RM {{ number_format($totalCN, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="font-weight-bold">
                        <td colspan="6" class="text-right">Total RC:</td>
                        <td class="text-right">RM {{ number_format($totalRC, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
