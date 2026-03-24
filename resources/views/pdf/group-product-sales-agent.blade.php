<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Group Product Sales Report By Agent</title>
    <style>
        body { font-family: helvetica, sans-serif; font-size: 8.5pt; color: #111; }
        h2, h3, h4 { margin: 0; text-align: center; }
        .meta { margin-top: 4pt; margin-bottom: 8pt; text-align: center; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.6pt solid #222; padding: 3pt; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .group-row { background: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $formatQty = function ($qty) {
            return rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
        };
        $colspan = 3 + count($agentColumns);
    @endphp
    <h2>PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA</h2>
    <h3>GROUP PRODUCT SALES REPORT</h3>
    <h4>{{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}</h4>
    <div class="meta">
        Generated: {{ $generatedAt->format('d/m/Y H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                <th colspan="2" class="text-center">QTY SOLD</th>
                @foreach($agentColumns as $agent)
                    <th class="text-center">{{ $agent }}</th>
                @endforeach
                <th class="text-center">TOTAL</th>
            </tr>
            <tr>
                <th>CODE</th>
                <th>ITEM DESCRIPTION</th>
                @foreach($agentColumns as $agent)
                    <th class="text-right">{{ $formatQty($agentTotals[$agent] ?? 0) }}</th>
                @endforeach
                <th class="text-right">{{ $formatQty($grandTotal ?? 0) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupedItems as $groupName => $items)
                <tr>
                    <td colspan="{{ $colspan }}" class="group-row">Group :{{ $groupName }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['item_code'] }}</td>
                        <td>{{ $item['item_description'] }}</td>
                        @foreach($agentColumns as $agent)
                            <td class="text-right">{{ $formatQty($item['agents'][$agent] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right"><strong>{{ $formatQty($item['total'] ?? 0) }}</strong></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>

