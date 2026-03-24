@php
    $colspan = 3 + count($agentColumns);
@endphp
<table>
    <tr>
        <th colspan="{{ $colspan }}">GROUP PRODUCT SALES REPORT ({{ $fromDate }} - {{ $toDate }})</th>
    </tr>
    <tr>
        <th>CODE</th>
        <th>ITEM DESCRIPTION</th>
        @foreach($agentColumns as $agent)
            <th>{{ $agent }}</th>
        @endforeach
        <th>TOTAL</th>
    </tr>
    <tr>
        <th></th>
        <th></th>
        @foreach($agentColumns as $agent)
            <th>{{ (float)($agentTotals[$agent] ?? 0) }}</th>
        @endforeach
        <th>{{ (float)($grandTotal ?? 0) }}</th>
    </tr>
    @foreach($groupedItems as $groupName => $items)
        <tr>
            <td colspan="{{ $colspan }}"><strong>Group :{{ $groupName }}</strong></td>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>{{ $item['item_code'] }}</td>
                <td>{{ $item['item_description'] }}</td>
                @foreach($agentColumns as $agent)
                    <td>{{ (float)($item['agents'][$agent] ?? 0) }}</td>
                @endforeach
                <td>{{ (float)($item['total'] ?? 0) }}</td>
            </tr>
        @endforeach
    @endforeach
</table>

