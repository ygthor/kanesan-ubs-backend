@php
    $formatQty = function ($qty) {
        return rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    };
    $tooltipInvCn = function ($inv, $cn) use ($formatQty) {
        $overall = (float) $inv - (float) $cn;
        return 'CN: ' . $formatQty($cn) . ' | INV: ' . $formatQty($inv) . ' | Overall: ' . $formatQty($overall);
    };
    $colspan = (count($months) * 3) + 5;
    $totalRg = collect($monthTotalsBreakdown ?? [])->sum('rg');
    $totalRb = collect($monthTotalsBreakdown ?? [])->sum('rb');
    $totalSales = collect($monthTotalsBreakdown ?? [])->sum('sales');
    $totalCn = (float) $totalRg + (float) $totalRb;
    $overallTotal = (float) $totalSales - $totalCn;
@endphp

<div class="table-responsive">
    <div class="small text-muted mb-1">
        CN: {{ $formatQty($totalCn) }} | INV: {{ $formatQty($totalSales) }} | Overall: {{ $formatQty($overallTotal) }}
    </div>
    <table class="table table-bordered table-sm" style="font-size: 12px;">
        <thead class="thead-light">
            <tr>
                <th colspan="2" rowspan="2" class="text-center align-middle">QTY SOLD</th>
                @foreach($months as $monthNo => $monthLabel)
                    <th class="text-center" colspan="3">{{ $monthLabel }}</th>
                @endforeach
                <th class="text-center" colspan="3">TOTAL</th>
            </tr>
            <tr>
                @foreach($months as $monthNo => $monthLabel)
                    <th class="text-center">RG</th>
                    <th class="text-center">RB</th>
                    <th class="text-center">Sales</th>
                @endforeach
                <th class="text-center">RG</th>
                <th class="text-center">RB</th>
                <th class="text-center">Sales</th>
            </tr>
            <tr>
                <th style="width: 120px;">CODE</th>
                <th>ITEM DESCRIPTION</th>
                @foreach($months as $monthNo => $monthLabel)
                    <th class="text-right">{{ $formatQty($monthTotalsBreakdown[$monthNo]['rg'] ?? 0) }}</th>
                    <th class="text-right">{{ $formatQty($monthTotalsBreakdown[$monthNo]['rb'] ?? 0) }}</th>
                    <th
                        class="text-right"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $tooltipInvCn($monthTotalsBreakdown[$monthNo]['sales'] ?? 0, ($monthTotalsBreakdown[$monthNo]['rg'] ?? 0) + ($monthTotalsBreakdown[$monthNo]['rb'] ?? 0)) }}"
                    >{{ $formatQty($monthTotalsBreakdown[$monthNo]['sales'] ?? 0) }}</th>
                @endforeach
                <th class="text-right">{{ $formatQty($totalRg) }}</th>
                <th class="text-right">{{ $formatQty($totalRb) }}</th>
                <th
                    class="text-right"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="{{ $tooltipInvCn($totalSales, $totalRg + $totalRb) }}"
                >{{ $formatQty($totalSales) }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedItems as $groupName => $items)
                <tr>
                    <td colspan="{{ $colspan }}" class="bg-light" style="font-weight: 700; font-size: 14px;">Group :{{ $groupName }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['item_code'] }}</td>
                        <td>{{ $item['item_description'] }}</td>
                        @foreach($months as $monthNo => $monthLabel)
                            <td class="text-right">{{ $formatQty($item['month_breakdown'][$monthNo]['rg'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatQty($item['month_breakdown'][$monthNo]['rb'] ?? 0) }}</td>
                            <td
                                class="text-right"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="{{ $tooltipInvCn($item['month_breakdown'][$monthNo]['sales'] ?? 0, ($item['month_breakdown'][$monthNo]['rg'] ?? 0) + ($item['month_breakdown'][$monthNo]['rb'] ?? 0)) }}"
                            >{{ $formatQty($item['month_breakdown'][$monthNo]['sales'] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right font-weight-bold">{{ $formatQty($item['total_rg'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ $formatQty($item['total_rb'] ?? 0) }}</td>
                        <td
                            class="text-right font-weight-bold"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="{{ $tooltipInvCn($item['total_sales'] ?? ($item['total'] ?? 0), ($item['total_rg'] ?? 0) + ($item['total_rb'] ?? 0)) }}"
                        >{{ $formatQty($item['total_sales'] ?? ($item['total'] ?? 0)) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center">No data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
