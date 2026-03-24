<table>
    <tr>
        <th colspan="15">GROUP PRODUCT SALES REPORT - YEAR {{ $year }}</th>
    </tr>
    <tr>
        <th>CODE</th>
        <th>ITEM DESCRIPTION</th>
        @foreach($months as $monthNo => $monthLabel)
            <th>{{ $monthLabel }}</th>
        @endforeach
        <th>TOTAL</th>
    </tr>
    <tr>
        <th></th>
        <th></th>
        @foreach($months as $monthNo => $monthLabel)
            <th>{{ (float)($monthTotals[$monthNo] ?? 0) }}</th>
        @endforeach
        <th>{{ (float)($grandTotal ?? 0) }}</th>
    </tr>
    @foreach($groupedItems as $groupName => $items)
        <tr>
            <td colspan="15"><strong>Group :{{ $groupName }}</strong></td>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>{{ $item['item_code'] }}</td>
                <td>{{ $item['item_description'] }}</td>
                @foreach($months as $monthNo => $monthLabel)
                    <td>{{ (float)($item['months'][$monthNo] ?? 0) }}</td>
                @endforeach
                <td>{{ (float)($item['total'] ?? 0) }}</td>
            </tr>
        @endforeach
    @endforeach
</table>

