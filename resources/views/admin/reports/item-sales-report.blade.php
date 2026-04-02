@extends('layouts.admin')

@section('title', 'Item Sales Report - Kanesan UBS Backend')

@section('page-title', 'Item Sales Report')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Item Sales Report</li>
@endsection

@section('card-title', 'Item Sales Report (Single Product)')

@push('scripts')
    <script>
        (function () {
            const preset = document.getElementById('date_preset');
            const from = document.getElementById('from_date');
            const to = document.getElementById('to_date');
            if (!preset || !from || !to) return;

            function toYmd(date) {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function startOfMonth(date) {
                return new Date(date.getFullYear(), date.getMonth(), 1);
            }

            function endOfMonth(date) {
                return new Date(date.getFullYear(), date.getMonth() + 1, 0);
            }

            function startOfQuarter(date) {
                const qStartMonth = Math.floor(date.getMonth() / 3) * 3;
                return new Date(date.getFullYear(), qStartMonth, 1);
            }

            function endOfQuarter(date) {
                const qStartMonth = Math.floor(date.getMonth() / 3) * 3;
                return new Date(date.getFullYear(), qStartMonth + 3, 0);
            }

            function applyPreset(selected) {
                const now = new Date();
                let start = null;
                let end = null;

                switch (selected) {
                    case 'this_month':
                        start = startOfMonth(now);
                        end = endOfMonth(now);
                        break;
                    case 'last_month': {
                        const prevMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        start = startOfMonth(prevMonth);
                        end = endOfMonth(prevMonth);
                        break;
                    }
                    case 'this_quarter':
                        start = startOfQuarter(now);
                        end = endOfQuarter(now);
                        break;
                    case 'last_quarter': {
                        const prevQuarterAnchor = new Date(now.getFullYear(), now.getMonth() - 3, 1);
                        start = startOfQuarter(prevQuarterAnchor);
                        end = endOfQuarter(prevQuarterAnchor);
                        break;
                    }
                    case 'this_year':
                        start = new Date(now.getFullYear(), 0, 1);
                        end = new Date(now.getFullYear(), 11, 31);
                        break;
                    case 'last_year':
                        start = new Date(now.getFullYear() - 1, 0, 1);
                        end = new Date(now.getFullYear() - 1, 11, 31);
                        break;
                    case 'custom':
                    default:
                        return;
                }

                from.value = toYmd(start);
                to.value = toYmd(end);
            }

            preset.addEventListener('change', function () {
                applyPreset(preset.value);
            });
            from.addEventListener('change', function () {
                if (preset.value !== 'custom') preset.value = 'custom';
            });
            to.addEventListener('change', function () {
                if (preset.value !== 'custom') preset.value = 'custom';
            });
        })();
    </script>
@endpush

@section('admin-content')
    <form method="GET" action="{{ route('admin.reports.item-sales') }}" class="mb-3">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label>Date Preset</label>
                    <select id="date_preset" name="date_preset" class="form-control">
                        <option value="this_month" {{ ($datePreset ?? 'this_month') === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ ($datePreset ?? '') === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_quarter" {{ ($datePreset ?? '') === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="last_quarter" {{ ($datePreset ?? '') === 'last_quarter' ? 'selected' : '' }}>Last Quarter</option>
                        <option value="this_year" {{ ($datePreset ?? '') === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="last_year" {{ ($datePreset ?? '') === 'last_year' ? 'selected' : '' }}>Last Year</option>
                        <option value="custom" {{ ($datePreset ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" id="from_date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach($agents ?? [] as $agent)
                            <option value="{{ $agent->name }}" {{ ($agentNo ?? '') === $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Product Code</label>
                    <input
                        type="text"
                        name="product_no"
                        class="form-control"
                        value="{{ $productNo ?? '' }}"
                        placeholder="Example: ITEM001"
                    >
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-block mb-3">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <a href="{{ route('admin.reports.item-sales') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>

    @if(($productNo ?? '') !== '' && !$selectedProduct)
        <div class="alert alert-warning">
            Product code <strong>{{ $productNo }}</strong> was not found in item master.
        </div>
    @endif

    @if($selectedProduct)
        <div class="mb-3">
            <h5 class="mb-1">Product: {{ $selectedProduct->item_code }} - {{ $selectedProduct->item_description }}</h5>
            <div class="text-muted">Group: {{ $selectedProduct->item_group !== '' ? $selectedProduct->item_group : 'N/A' }}</div>
        </div>
    @elseif(($productNo ?? '') === '')
        <div class="mb-3 text-muted">
            Showing all products for selected filters.
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="card">
                <div class="card-body p-2">
                    <div class="text-muted small">INV Qty</div>
                    <div class="h5 mb-0">{{ rtrim(rtrim(number_format((float)($summary['inv_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card">
                <div class="card-body p-2">
                    <div class="text-muted small">CN Qty</div>
                    <div class="h5 mb-0 text-danger">{{ rtrim(rtrim(number_format((float)($summary['cn_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card">
                <div class="card-body p-2">
                    <div class="text-muted small">Net Qty</div>
                    <div class="h5 mb-0">{{ rtrim(rtrim(number_format((float)($summary['net_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card">
                <div class="card-body p-2">
                    <div class="text-muted small">Net Amount (RM)</div>
                    <div class="h5 mb-0">RM {{ number_format((float)($summary['net_amount'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @php
        $sortArrow = function (string $column) use ($sortBy, $sortDir) {
            if (($sortBy ?? '') !== $column) return '';
            return ($sortDir ?? 'asc') === 'asc' ? ' ▲' : ' ▼';
        };
        $sortUrl = function (string $column) use ($sortBy, $sortDir) {
            $nextDir = (($sortBy ?? '') === $column && ($sortDir ?? 'asc') === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery([
                'sort_by' => $column,
                'sort_dir' => $nextDir,
            ]);
        };
    @endphp

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover table-sm">
            <thead class="thead-dark">
                <tr>
                    <th><a class="text-white" href="{{ $sortUrl('product_no') }}">Product{{ $sortArrow('product_no') }}</a></th>
                    <th><a class="text-white" href="{{ $sortUrl('product_description') }}">Description{{ $sortArrow('product_description') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('inv_qty') }}">INV Qty{{ $sortArrow('inv_qty') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('cn_qty') }}">CN Qty{{ $sortArrow('cn_qty') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('net_qty') }}">Net Qty{{ $sortArrow('net_qty') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('inv_amount') }}">INV Amount{{ $sortArrow('inv_amount') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('cn_amount') }}">CN Amount{{ $sortArrow('cn_amount') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('discount_total') }}">Discount{{ $sortArrow('discount_total') }}</a></th>
                    <th class="text-right"><a class="text-white" href="{{ $sortUrl('net_amount') }}">Net Amount{{ $sortArrow('net_amount') }}</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->product_no }}</td>
                        <td>{{ $row->product_description }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float)$row->inv_qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right text-danger">{{ rtrim(rtrim(number_format((float)$row->cn_qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float)$row->inv_qty - (float)$row->cn_qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right">{{ number_format((float)$row->inv_amount, 2) }}</td>
                        <td class="text-right text-danger">{{ number_format((float)$row->cn_amount, 2) }}</td>
                        <td class="text-right">{{ number_format((float)$row->discount_total, 2) }}</td>
                        <td class="text-right">{{ number_format((float)$row->inv_amount - (float)$row->cn_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">No records found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(($rows ?? collect())->count() > 0)
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th class="text-right">{{ rtrim(rtrim(number_format((float)($summary['inv_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</th>
                        <th class="text-right text-danger">{{ rtrim(rtrim(number_format((float)($summary['cn_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</th>
                        <th class="text-right">{{ rtrim(rtrim(number_format((float)($summary['net_qty'] ?? 0), 2, '.', ''), '0'), '.') }}</th>
                        <th class="text-right">{{ number_format((float)($summary['inv_amount'] ?? 0), 2) }}</th>
                        <th class="text-right text-danger">{{ number_format((float)($summary['cn_amount'] ?? 0), 2) }}</th>
                        <th class="text-right">{{ number_format((float)($summary['discount_total'] ?? 0), 2) }}</th>
                        <th class="text-right">{{ number_format((float)($summary['net_amount'] ?? 0), 2) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
