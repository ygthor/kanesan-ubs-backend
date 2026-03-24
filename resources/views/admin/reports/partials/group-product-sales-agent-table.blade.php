@php
    $formatQty = function ($qty) {
        return rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    };
    $colspan = 3 + count($agentColumns);
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-sm" style="font-size: 12px;">
        <thead class="thead-light">
            <tr>
                <th colspan="{{ 2 + count($agentColumns) }}" class="text-center"></th>
                <th class="text-center bg-dark text-white">{{ $periodLabel ?? 'CUSTOM' }}</th>
            </tr>
            <tr>
                <th colspan="2" class="text-center">QTY SOLD</th>
                @foreach($agentColumns as $agent)
                    <th class="text-center">{{ $agent }}</th>
                @endforeach
                <th class="text-center">TOTAL</th>
            </tr>
            <tr>
                <th style="width: 120px;">CODE</th>
                <th>ITEM DESCRIPTION</th>
                @foreach($agentColumns as $agent)
                    <th class="text-right">{{ $formatQty($agentTotals[$agent] ?? 0) }}</th>
                @endforeach
                <th class="text-right">{{ $formatQty($grandTotal ?? 0) }}</th>
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
                        @foreach($agentColumns as $agent)
                            <td class="text-right">{{ $formatQty($item['agents'][$agent] ?? 0) }}</td>
                        @endforeach
                        <td class="text-center font-weight-bold">{{ $formatQty($item['total'] ?? 0) }}</td>
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
