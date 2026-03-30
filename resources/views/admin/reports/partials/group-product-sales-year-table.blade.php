@php
    $formatQty = function ($qty) {
        return rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    };
    $tooltipQty = function ($inv, $cn) use ($formatQty) {
        return 'INV: ' . $formatQty($inv) . ' | CN: ' . $formatQty($cn);
    };
    $colspan = count($months) + 3;
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-sm" style="font-size: 12px;">
        <thead class="thead-light">
            <tr>
                <th colspan="2" class="text-center">QTY SOLD</th>
                @foreach($months as $monthNo => $monthLabel)
                    <th class="text-center">{{ $monthLabel }}</th>
                @endforeach
                <th class="text-center">TOTAL</th>
            </tr>
            <tr>
                <th style="width: 120px;">CODE</th>
                <th>ITEM DESCRIPTION</th>
                @foreach($months as $monthNo => $monthLabel)
                    <th
                        class="text-right"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $tooltipQty($monthTotalsBreakdown[$monthNo]['inv'] ?? 0, $monthTotalsBreakdown[$monthNo]['cn'] ?? 0) }}"
                    >{{ $formatQty($monthTotals[$monthNo] ?? 0) }}</th>
                @endforeach
                <th
                    class="text-right"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="{{ $tooltipQty(collect($monthTotalsBreakdown ?? [])->sum('inv'), collect($monthTotalsBreakdown ?? [])->sum('cn')) }}"
                >{{ $formatQty($grandTotal ?? 0) }}</th>
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
                            <td
                                class="text-right"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="{{ $tooltipQty($item['month_breakdown'][$monthNo]['inv'] ?? 0, $item['month_breakdown'][$monthNo]['cn'] ?? 0) }}"
                            >{{ $formatQty($item['months'][$monthNo] ?? 0) }}</td>
                        @endforeach
                        <td
                            class="text-center font-weight-bold"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="{{ $tooltipQty(collect($item['month_breakdown'] ?? [])->sum('inv'), collect($item['month_breakdown'] ?? [])->sum('cn')) }}"
                        >{{ $formatQty($item['total'] ?? 0) }}</td>
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
