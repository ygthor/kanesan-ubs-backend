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
            <div class="col-md-2">
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
            <div class="col-md-2">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach(($agents ?? []) as $agent)
                            <option value="{{ $agent->name }}" {{ ($filters['agent_no'] ?? '') === $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
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
        <div class="small text-muted mt-1">
            Total Qty: {{ rtrim(rtrim(number_format((float) ($grandTotal ?? 0), 2, '.', ''), '0'), '.') }}
            |
            Total Discount: RM {{ number_format((float) ($grandDiscountTotal ?? 0), 2) }}
        </div>
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

    <div class="row mb-3 d-none">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Group Discount by Agent</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="agentDiscountBarChart"></canvas>
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
            if (typeof Chart !== 'undefined' && !Chart.registry.plugins.get('piePercentageLabelPlugin')) {
                Chart.register({
                    id: 'piePercentageLabelPlugin',
                    afterDatasetsDraw(chart, _args, pluginOptions) {
                        if ((chart.config.type !== 'pie' && chart.config.type !== 'doughnut') || pluginOptions?.enabled === false) {
                            return;
                        }
                        const labels = chart.data.labels || [];
                        if (labels.length === 1 && labels[0] === 'No Sales') {
                            return;
                        }

                        const dataset = chart.data.datasets?.[0];
                        const meta = chart.getDatasetMeta(0);
                        if (!dataset || !meta?.data?.length) {
                            return;
                        }

                        const total = (dataset.data || []).reduce((sum, value) => sum + Number(value || 0), 0);
                        if (!total) {
                            return;
                        }

                        const minPercent = Number(pluginOptions?.minPercent ?? 4);
                        const { ctx } = chart;
                        ctx.save();
                        ctx.fillStyle = pluginOptions?.color || '#ffffff';
                        ctx.font = pluginOptions?.font || 'bold 11px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';

                        meta.data.forEach((arc, index) => {
                            const value = Number(dataset.data[index] || 0);
                            if (!value) return;
                            const percent = (value / total) * 100;
                            if (percent < minPercent) return;

                            const angle = (arc.startAngle + arc.endAngle) / 2;
                            const radius = arc.innerRadius + (arc.outerRadius - arc.innerRadius) * 0.62;
                            const x = arc.x + Math.cos(angle) * radius;
                            const y = arc.y + Math.sin(angle) * radius;
                            ctx.fillText(`${percent.toFixed(1)}%`, x, y);
                        });

                        ctx.restore();
                    }
                });
            }

            const preset = document.getElementById('date_preset');
            const from = document.getElementById('from_date');
            const to = document.getElementById('to_date');

            function getPercentage(value, values) {
                const total = (values || []).reduce((sum, v) => sum + Number(v || 0), 0);
                if (!total) return 0;
                return (Number(value || 0) / total) * 100;
            }

            function getPieTooltipCallbacks(values) {
                return {
                    label(context) {
                        const label = context.label || '';
                        const value = Number(context.parsed || 0);
                        const percent = getPercentage(value, values);
                        return `${label}: ${value.toLocaleString(undefined, { maximumFractionDigits: 2 })} (${percent.toFixed(1)}%)`;
                    }
                };
            }

            function toggleCustomDates() {
                const isCustom = preset.value === 'custom';
                from.readOnly = !isCustom;
                to.readOnly = !isCustom;
            }

            preset.addEventListener('change', toggleCustomDates);
            toggleCustomDates();

            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                    const existing = bootstrap.Tooltip.getInstance(el);
                    if (existing) {
                        existing.dispose();
                    }
                    new bootstrap.Tooltip(el, {
                        container: 'body',
                        trigger: 'hover focus',
                        delay: { show: 0, hide: 0 },
                    });
                });
            }

            if (typeof Chart === 'undefined') {
                return;
            }

            const agentLabels = @json($agentColumns);
            const agentValues = @json(array_values($agentTotals));
            const agentDiscountValues = @json(array_values($agentDiscountTotals ?? []));
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
                        maintainAspectRatio: false,
                        plugins: {
                            piePercentageLabelPlugin: { enabled: true },
                            tooltip: {
                                callbacks: getPieTooltipCallbacks(pieValues)
                            }
                        }
                    }
                });
            }

            const discountBarCanvas = document.getElementById('agentDiscountBarChart');
            if (discountBarCanvas) {
                const discountBySalesmanData = agentLabels.map((label, index) => ({
                    label: label || 'N/A',
                    value: Number(agentDiscountValues[index] || 0)
                })).sort((a, b) => b.value - a.value);

                if (!discountBySalesmanData.length || discountBySalesmanData.every(item => item.value <= 0)) {
                    discountBarCanvas.parentElement.innerHTML = '<div class="text-muted">No discount data for selected filters.</div>';
                } else {
                    new Chart(discountBarCanvas, {
                        type: 'bar',
                        data: {
                            labels: discountBySalesmanData.map(item => item.label),
                            datasets: [{
                                label: 'Discount (RM)',
                                data: discountBySalesmanData.map(item => item.value),
                                backgroundColor: '#fb8c00'
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
            }
        })();
    </script>
@endpush
