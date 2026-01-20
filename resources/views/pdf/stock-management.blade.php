<!DOCTYPE html>
<html>

<head>
    <title>Stock Management Report - Agent {{ $selectedAgent }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: helvetica, sans-serif;

            color: #333;
        }   

        h5 {
            color: #007bff;
            font-size: 12pt;
            font-weight: bold;
            margin: 5pt 0 8pt 0;
            padding: 5pt 0;
            border-bottom: 1pt solid #dee2e6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15pt;
            font-size: 9pt;
        }

        th,
        td {
            border: 1pt solid #cccccc;
            padding: 4pt 3pt;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
            padding: 5pt 3pt;
        }

        .numeric-column {
            text-align: right;

        }

    </style>
</head>

<body>
    <!-- Header is now handled by custom PDF class -->

    @if (!empty($filters['search']) || !empty($filters['group']))
        <div class="filters-info">
            <h3>Applied Filters:</h3>
            @if (!empty($filters['search']))
                <p><strong>Search Term:</strong> {{ $filters['search'] }}</p>
            @endif
            @if (!empty($filters['group']))
                <p><strong>Group Filter:</strong> {{ $filters['group'] }}</p>
            @endif
        </div>
    @endif

    @php
        $totalCurrentStock = 0;
        $totalStockIn = 0;
        $totalStockOut = 0;
        $totalReturnGood = 0;

        $inventory_by_group = $inventory->groupBy('GROUP');
    @endphp

    @foreach ($inventory_by_group as $groupName => $groupItems)
        <h5>Group: {{ $groupName }}</h5>

        <table>
            <thead>
                <tr>
                    <th width="50px">Item Code</th>
                    <th width="160px">Description</th>
                    <th width="40px">Group</th>
                    <th width="70px" class="text-center">Current Stock</th>
                    <th width="70px" class="text-center">Stock In</th>
                    <th width="70px" class="text-center">Stock Out</th>
                    <th width="40px" class="text-center">Unit</th>
                    <th width="50px" class="text-center">Price</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupCurrentStock = 0;
                    $groupStockIn = 0;
                    $groupStockOut = 0;
                @endphp

                @foreach ($groupItems as $item)
                    @php
                        $groupCurrentStock += $item['current_stock'];
                        $groupStockIn += $item['stockIn'];
                        $groupStockOut += $item['stockOut'];

                        $totalCurrentStock += $item['current_stock'];
                        $totalStockIn += $item['stockIn'];
                        $totalStockOut += $item['stockOut'];
                        $totalReturnGood += $item['returnGood'] ?? 0;
                    @endphp

                    <tr>
                        <td width="50px"><strong>{{ $item['ITEMNO'] }}</strong></td>
                        <td width="160px">{{ $item['DESP'] }}</td>
                        <td width="40px">{{ $item['GROUP'] }}</td>
                        <td width="70px" class="numeric-column">{{ number_format($item['current_stock'], 2) }}</td>
                        <td width="70px" class="numeric-column">{{ number_format($item['stockIn'], 2) }}</td>
                        <td width="70px" class="numeric-column">{{ number_format($item['stockOut'], 2) }}</td>
                        <td width="40px" class="text-center">{{ $item['UNIT'] }}</td>
                        <td width="50px" class="numeric-column">{{ number_format($item['PRICE'], 2) }}</td>
                    </tr>
                @endforeach

                <tr class="summary-row">
                    <td colspan="3"><strong>SUBTOTAL - {{ $groupName }}</strong></td>
                    <td class="numeric-column"><strong>{{ number_format($groupCurrentStock, 2) }}</strong></td>
                    <td class="numeric-column"><strong>{{ number_format($groupStockIn, 2) }}</strong></td>
                    <td class="numeric-column"><strong>{{ number_format($groupStockOut, 2) }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    @endforeach

</body>

</html>
