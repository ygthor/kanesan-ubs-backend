@extends('layouts.admin')

@section('title', 'Group Product Sales Report By Agent - Kanesan UBS Backend')

@section('page-title', 'Group Product Sales Report By Agent')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Group Product Sales Report By Agent</li>
@endsection

@section('card-title', 'Group Product Sales Report By Agent')

@section('admin-content')
    <form method="GET" action="{{ route('admin.reports.group-product-sales-agent') }}" class="mb-3">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Date Preset</label>
                    <select name="date_preset" id="date_preset" class="form-control">
                        <option value="this_month" {{ ($filters['date_preset'] ?? 'this_month') === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ ($filters['date_preset'] ?? '') === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_quarter" {{ ($filters['date_preset'] ?? '') === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="last_quarter" {{ ($filters['date_preset'] ?? '') === 'last_quarter' ? 'selected' : '' }}>Last Quarter</option>
                        <option value="this_year" {{ ($filters['date_preset'] ?? '') === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="last_year" {{ ($filters['date_preset'] ?? '') === 'last_year' ? 'selected' : '' }}>Last Year</option>
                        <option value="custom" {{ ($filters['date_preset'] ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Group</label>
                    <select name="group" class="form-control">
                        <option value="">All Groups</option>
                        @foreach($groups as $group)
                            <option value="{{ $group }}" {{ ($filters['group'] ?? '') === $group ? 'selected' : '' }}>
                                {{ $group }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Product Search</label>
                    <input type="text" name="product_search" class="form-control" value="{{ $filters['product_search'] ?? '' }}" placeholder="Code or Description">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Preview
                </button>
                <a href="{{ route('admin.reports.group-product-sales-agent') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-agent.export.pdf') }}"
                    formtarget="_blank"
                    class="btn btn-danger"
                >
                    <i class="fas fa-file-pdf"></i> Print PDF
                </button>
                {{-- <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-agent.export.excel') }}"
                    class="btn btn-success"
                >
                    <i class="fas fa-file-excel"></i> Export Excel
                </button> --}}
            </div>
        </div>
    </form>

    <div class="mb-2 text-center">
        <h4 class="mb-1">PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA</h4>
        <h5 class="mb-0">GROUP PRODUCT SALES REPORT</h5>
        <h6 class="mb-0">{{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}</h6>
    </div>

    <div class="row mb-3">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Sales by Salesman</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="agentBarChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Sales Share by Salesman</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="agentPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    @include('admin.reports.partials.group-product-sales-agent-table')
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (typeof Chart !== 'undefined' && !Chart.registry.plugins.get('valueOnBarPlugin')) {
                Chart.register({
                    id: 'valueOnBarPlugin',
                    afterDatasetsDraw(chart, _args, pluginOptions) {
                        if (chart.config.type !== 'bar' || pluginOptions?.enabled === false) {
                            return;
                        }
                        const { ctx } = chart;
                        const isHorizontal = chart.options?.indexAxis === 'y';
                        const dataset = chart.data.datasets?.[0];
                        const meta = chart.getDatasetMeta(0);
                        if (!dataset || !meta?.data?.length) {
                            return;
                        }

                        ctx.save();
                        ctx.fillStyle = pluginOptions?.color || '#111827';
                        ctx.font = pluginOptions?.font || '12px sans-serif';
                        ctx.textBaseline = 'middle';
                        ctx.textAlign = isHorizontal ? 'left' : 'center';

                        meta.data.forEach((bar, index) => {
                            const rawValue = Number(dataset.data[index] || 0);
                            if (!rawValue) {
                                return;
                            }
                            const valueText = rawValue.toLocaleString(undefined, {
                                maximumFractionDigits: 2
                            });
                            const x = isHorizontal ? bar.x + 6 : bar.x;
                            const y = isHorizontal ? bar.y : bar.y - 8;
                            ctx.fillText(valueText, x, y);
                        });
                        ctx.restore();
                    }
                });
            }

            const preset = document.getElementById('date_preset');
            const from = document.getElementById('from_date');
            const to = document.getElementById('to_date');

            function toggleCustomDates() {
                const isCustom = preset.value === 'custom';
                from.readOnly = !isCustom;
                to.readOnly = !isCustom;
            }

            preset.addEventListener('change', toggleCustomDates);
            toggleCustomDates();

            if (typeof Chart === 'undefined') {
                return;
            }

            const agentLabels = @json($agentColumns);
            const agentValues = @json(array_values($agentTotals));
            const chartColors = [
                '#4e79a7', '#f28e2b', '#e15759', '#76b7b2',
                '#59a14f', '#edc948', '#b07aa1', '#ff9da7',
                '#9c755f', '#bab0ab'
            ];

            const barCanvas = document.getElementById('agentBarChart');
            if (barCanvas) {
                const salesmanData = agentLabels.map((label, index) => ({
                    label: label || 'N/A',
                    value: Number(agentValues[index] || 0)
                })).sort((a, b) => b.value - a.value);

                new Chart(barCanvas, {
                    type: 'bar',
                    data: {
                        labels: salesmanData.map(item => item.label),
                        datasets: [{
                            label: 'Total Sales',
                            data: salesmanData.map(item => item.value),
                            backgroundColor: '#007bff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            valueOnBarPlugin: { enabled: true }
                        },
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            const pieCanvas = document.getElementById('agentPieChart');
            if (pieCanvas) {
                const pieLabels = [];
                const pieValues = [];

                agentValues.forEach((value, index) => {
                    if (Number(value) > 0) {
                        pieLabels.push(agentLabels[index] || 'N/A');
                        pieValues.push(value);
                    }
                });

                if (!pieValues.length) {
                    pieLabels.push('No Sales');
                    pieValues.push(1);
                }

                new Chart(pieCanvas, {
                    type: 'pie',
                    data: {
                        labels: pieLabels,
                        datasets: [{
                            data: pieValues,
                            backgroundColor: pieLabels.length === 1 && pieLabels[0] === 'No Sales'
                                ? ['#d1d5db']
                                : pieValues.map((_, index) => chartColors[index % chartColors.length])
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        })();
    </script>
@endpush
