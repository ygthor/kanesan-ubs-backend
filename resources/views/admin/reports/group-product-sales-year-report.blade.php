@extends('layouts.admin')

@section('title', 'Group Product Sales Report By Year - Kanesan UBS Backend')

@section('page-title', 'Group Product Sales Report By Year')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/reports">Reports</a></li>
    <li class="breadcrumb-item active">Group Product Sales Report By Year</li>
@endsection

@section('card-title', 'Group Product Sales Report By Year')

@section('admin-content')
    <form method="GET" action="{{ route('admin.reports.group-product-sales-year') }}" class="mb-3">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $filters['year'] ?? now()->year }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Agent</label>
                    <select name="agent_no" class="form-control">
                        <option value="">All Agents</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->name }}" {{ ($filters['agent_no'] ?? '') === $agent->name ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-4">
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
                <a href="{{ route('admin.reports.group-product-sales-year') }}" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-year.export.pdf') }}"
                    formtarget="_blank"
                    class="btn btn-danger"
                >
                    <i class="fas fa-file-pdf"></i> Print PDF
                </button>
                {{-- <button
                    type="submit"
                    formaction="{{ route('admin.reports.group-product-sales-year.export.excel') }}"
                    class="btn btn-success"
                >
                    <i class="fas fa-file-excel"></i> Export Excel
                </button> --}}
            </div>
        </div>
    </form>

    <div class="mb-2 text-center">
        <h4 class="mb-1">PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA</h4>
        <h5 class="mb-0">GROUP PRODUCT SALES REPORT - YEAR {{ $year }}</h5>
        <div class="small text-muted mt-1">
            Total Qty: {{ rtrim(rtrim(number_format((float) ($grandTotal ?? 0), 2, '.', ''), '0'), '.') }}
            |
            Total Discount: RM {{ number_format((float) ($yearDiscountTotal ?? 0), 2) }}
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Month Sales</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="yearMonthlyBarChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Month Sales Share</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="yearMonthlyPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Month Discount (RM)</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="yearMonthlyDiscountBarChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Discount by Product Group</strong>
                </div>
                <div class="card-body" style="height: 320px;">
                    <canvas id="yearGroupDiscountBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Sales Share by Product Group</strong>
                </div>
                <div class="card-body" style="height: 340px;">
                    <canvas id="yearGroupPieChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong>Sales by Product Group</strong>
                </div>
                <div class="card-body" style="height: 340px;">
                    <canvas id="yearGroupBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <div class="d-flex align-items-center w-100">
                        <strong>Sales by Item (Top 20)</strong>
                        <div class="ml-auto">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#allItemsSalesModal"
                            >
                                View Full Chart
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="yearItemsTop50BarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="allItemsSalesModal" tabindex="-1" aria-labelledby="allItemsSalesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="allItemsSalesModalLabel">Sales by Item (All)</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="allItemsSalesModalChartWrap" style="height: 600px;">
                        <canvas id="allItemsSalesModalChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.reports.partials.group-product-sales-year-table')
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const valueOnBarPlugin = {
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
            };

            if (!Chart.registry.plugins.get('valueOnBarPlugin')) {
                Chart.register(valueOnBarPlugin);
            }

            const groupedItems = @json($groupedItems->toArray());
            const monthLabels = @json(array_values($months));
            const monthValues = @json(array_values($monthTotals));
            const monthDiscountValues = @json(array_values($monthDiscountTotals ?? []));
            const chartColors = [
                '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728',
                '#9467bd', '#8c564b', '#e377c2', '#7f7f7f',
                '#bcbd22', '#17becf', '#4e79a7', '#f28e2b'
            ];

            const barCanvas = document.getElementById('yearMonthlyBarChart');
            if (barCanvas) {
                new Chart(barCanvas, {
                    type: 'bar',
                    data: {
                        labels: monthLabels,
                        datasets: [{
                            label: 'Qty Sold',
                            data: monthValues,
                            backgroundColor: '#36a2eb'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true },
                            valueOnBarPlugin: { enabled: true }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Qty'
                                }
                            }
                        }
                    }
                });
            }

            const monthDiscountCanvas = document.getElementById('yearMonthlyDiscountBarChart');
            if (monthDiscountCanvas) {
                new Chart(monthDiscountCanvas, {
                    type: 'bar',
                    data: {
                        labels: monthLabels,
                        datasets: [{
                            label: 'Discount (RM)',
                            data: monthDiscountValues,
                            backgroundColor: '#f59e0b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            valueOnBarPlugin: { enabled: true }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            const pieCanvas = document.getElementById('yearMonthlyPieChart');
            if (pieCanvas) {
                const pieLabels = [];
                const pieValues = [];

                monthValues.forEach((value, index) => {
                    if (Number(value) > 0) {
                        pieLabels.push(monthLabels[index]);
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
                                : chartColors.slice(0, pieValues.length)
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            const groupTotalsData = Object.entries(groupedItems).map(([groupName, items]) => {
                const total = (items || []).reduce((sum, item) => sum + Number(item.total || 0), 0);
                return {
                    group: groupName || 'N/A',
                    total
                };
            }).filter(row => row.total > 0).sort((a, b) => b.total - a.total);

            const groupPieCanvas = document.getElementById('yearGroupPieChart');
            if (groupPieCanvas) {
                const pieLabels = groupTotalsData.length ? groupTotalsData.map(row => row.group) : ['No Sales'];
                const pieValues = groupTotalsData.length ? groupTotalsData.map(row => row.total) : [1];

                new Chart(groupPieCanvas, {
                    type: 'pie',
                    data: {
                        labels: pieLabels,
                        datasets: [{
                            data: pieValues,
                            backgroundColor: groupTotalsData.length
                                ? pieValues.map((_, index) => chartColors[index % chartColors.length])
                                : ['#d1d5db']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            const groupBarCanvas = document.getElementById('yearGroupBarChart');
            if (groupBarCanvas) {
                new Chart(groupBarCanvas, {
                    type: 'bar',
                    data: {
                        labels: groupTotalsData.map(row => row.group),
                        datasets: [{
                            label: 'Sales',
                            data: groupTotalsData.map(row => row.total),
                            backgroundColor: '#17a2b8'
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

            const groupDiscountTotalsData = Object.entries(groupedItems).map(([groupName, items]) => {
                const totalDiscount = (items || []).reduce((sum, item) => sum + Number(item.discount_total || 0), 0);
                return {
                    group: groupName || 'N/A',
                    totalDiscount
                };
            }).filter(row => row.totalDiscount > 0).sort((a, b) => b.totalDiscount - a.totalDiscount);

            const groupDiscountBarCanvas = document.getElementById('yearGroupDiscountBarChart');
            if (groupDiscountBarCanvas) {
                if (!groupDiscountTotalsData.length) {
                    groupDiscountBarCanvas.parentElement.innerHTML = '<div class="text-muted">No discount data for selected filters.</div>';
                } else {
                    new Chart(groupDiscountBarCanvas, {
                        type: 'bar',
                        data: {
                            labels: groupDiscountTotalsData.map(row => row.group),
                            datasets: [{
                                label: 'Discount (RM)',
                                data: groupDiscountTotalsData.map(row => row.totalDiscount),
                                backgroundColor: '#fb8c00'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                valueOnBarPlugin: { enabled: true }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }

            const itemTotalsData = Object.values(groupedItems).flatMap(items => (items || []).map(item => ({
                group: item.item_group || 'N/A',
                code: item.item_code || '',
                description: item.item_description || '',
                total: Number(item.total || 0)
            }))).filter(item => item.total > 0).sort((a, b) => b.total - a.total);

            const top50Canvas = document.getElementById('yearItemsTop50BarChart');
            const modal = document.getElementById('allItemsSalesModal');
            const modalChartWrap = document.getElementById('allItemsSalesModalChartWrap');
            const modalChartCanvas = document.getElementById('allItemsSalesModalChart');
            let allItemsChartInstance = null;

            const buildItemChart = (canvas, dataRows, color) => {
                return new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: dataRows.map(item => `${item.group} | ${item.code || item.description}`),
                        datasets: [{
                            label: 'Sales',
                            data: dataRows.map(item => item.total),
                            backgroundColor: color
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            valueOnBarPlugin: { enabled: true },
                            tooltip: {
                                callbacks: {
                                    title: (context) => {
                                        const idx = context?.[0]?.dataIndex ?? 0;
                                        const row = dataRows[idx];
                                        return `${row.group} | ${row.code} - ${row.description}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            };

            if (top50Canvas) {
                if (!itemTotalsData.length) {
                    top50Canvas.parentElement.innerHTML = '<div class="text-muted">No sales data for selected filters.</div>';
                } else {
                    const top20 = itemTotalsData.slice(0, 20);
                    buildItemChart(top50Canvas, top20, '#6f42c1');
                }
            }

            if (modal && modalChartWrap && modalChartCanvas) {
                modal.addEventListener('shown.bs.modal', function () {
                    if (!itemTotalsData.length) {
                        modalChartWrap.innerHTML = '<div class="text-muted">No sales data for selected filters.</div>';
                        return;
                    }

                    modalChartWrap.style.height = `${Math.max(600, itemTotalsData.length * 26)}px`;

                    if (allItemsChartInstance) {
                        allItemsChartInstance.destroy();
                    }
                    allItemsChartInstance = buildItemChart(modalChartCanvas, itemTotalsData, '#5a67d8');
                });

                modal.addEventListener('hidden.bs.modal', function () {
                    if (allItemsChartInstance) {
                        allItemsChartInstance.destroy();
                        allItemsChartInstance = null;
                    }
                });
            }
        })();
    </script>
@endpush
