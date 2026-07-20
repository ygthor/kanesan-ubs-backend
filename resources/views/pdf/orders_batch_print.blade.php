{{-- resources/views/pdf/orders_batch_print.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <title>Batch Print</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 25px;
            font-size: 13px;
        }

        .order-container {
            border: 1px solid #ccc;
            padding: 18px;
            margin-bottom: 25px;
        }

        .page-break {
            page-break-after: always;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 7px 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .header h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }

        .text-right {
            text-align: right;
        }

        .totals {
            margin-top: 16px;
            float: right;
            width: 300px;
        }

        .totals table td {
            border: none;
            padding: 4px 8px;
        }

        .badge-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            color: #fff;
        }

        .badge-inv  { background: #28a745; }
        .badge-cn   { background: #ffc107; color: #333; }
        .badge-cn2  { background: #17a2b8; }
        .badge-do   { background: #007bff; }

        .cn2-section {
            margin-top: 16px;
            border: 1px solid #b8daff;
            background: #e8f4fd;
            border-radius: 4px;
            padding: 12px;
        }

        .cn2-section h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #0056b3;
        }

        .cn2-section table th {
            background-color: #cce5ff;
        }

        .trade-return-tag {
            color: #d9534f;
            font-style: italic;
            font-size: 11px;
        }

        .total-return {
            font-weight: bold;
            color: #d9534f;
        }

        .total-amount-pay {
            font-size: 14px;
            font-weight: bold;
        }

        .remarks-box {
            margin-top: 8px;
            font-size: 12px;
            color: #555;
        }
    </style>
</head>

<body>
    @foreach ($orders as $order)
        <div class="order-container">
            <div class="header">
                {{-- Document title based on type --}}
                @php
                    $docTitle = match($order->type) {
                        'INV'  => 'Invoice',
                        'CN'   => 'Credit Note (Trade Return)',
                        'CN2'  => 'Credit Note (CN2)',
                        'DO'   => 'Delivery Order',
                        'SO'   => 'Sales Order',
                        default => $order->type,
                    };
                    $badgeClass = match($order->type) {
                        'INV'  => 'badge-inv',
                        'CN'   => 'badge-cn',
                        'CN2'  => 'badge-cn2',
                        default => 'badge-do',
                    };
                @endphp
                <h1>
                    {{ $docTitle }}
                    <span class="badge-type {{ $badgeClass }}">{{ $order->type }}</span>
                </h1>
                <p><strong>Ref No:</strong> {{ $order->reference_no }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</p>
                @if ($order->agent_no)
                    <p><strong>Agent:</strong> {{ $order->agent_no }}</p>
                @endif
                @if ($order->credit_invoice_no)
                    <p><strong>Linked Invoice:</strong> {{ $order->credit_invoice_no }}</p>
                @endif
                <hr>
                <p>
                    <strong>Bill To:</strong><br>
                    @if ($order->customer)
                        {{ $order->customer->company_name ?? $order->customer->name ?? $order->customer_name }}
                        @if (!empty($order->customer->company_name2))
                            <br>{{ $order->customer->company_name2 }}
                        @endif
                        ({{ $order->customer_code }})<br>
                        @if (!empty($order->customer->address1))
                            {{ $order->customer->address1 }}<br>
                        @endif
                        @if (!empty($order->customer->address2))
                            {{ $order->customer->address2 }}<br>
                        @endif
                        @if (!empty($order->customer->address3))
                            {{ $order->customer->address3 }}<br>
                        @endif
                        @if (!empty($order->customer->postcode) || !empty($order->customer->state))
                            {{ trim(($order->customer->postcode ?? '') . ' ' . ($order->customer->state ?? '')) }}<br>
                        @endif
                    @else
                        {{ $order->customer_name }} ({{ $order->customer_code }})
                    @endif
                </p>
            </div>

            {{-- Items table --}}
            @if ($order->items && $order->items->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Amount</th>
                            @if ($order->type === 'CN')
                                <th>Condition</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{ $item->product_name ?? $item->description ?? 'N/A' }}
                                    @if ($item->is_trade_return)
                                        <br><span class="trade-return-tag">(Trade Return)</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{ $item->quantity == floor($item->quantity) ? number_format($item->quantity, 0) : number_format($item->quantity, 2) }}
                                </td>
                                <td class="text-right">{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($item->discount ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($item->amount ?? 0, 2) }}</td>
                                @if ($order->type === 'CN')
                                    <td>
                                        @if ($item->is_trade_return)
                                            {{ $item->trade_return_is_good ? 'Good' : 'Bad' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                @if ($order->type === 'CN2')
                    {{-- CN2 has no order_items rows — amount is stored on the order header --}}
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Manual Credit Note{{ $order->remarks ? ': ' . $order->remarks : '' }}</td>
                                <td class="text-right">{{ number_format($order->net_amount ?? 0, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            @endif

            {{-- Totals --}}
            <div class="totals">
                <table>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right">RM {{ number_format($order->gross_amount ?? 0, 2) }}</td>
                    </tr>
                    @if (($order->discount ?? 0) > 0)
                        <tr>
                            <td><strong>Discount:</strong></td>
                            <td class="text-right">RM {{ number_format($order->discount, 2) }}</td>
                        </tr>
                    @endif
                    @if (($order->tax1 ?? 0) > 0)
                        <tr>
                            <td><strong>Tax ({{ $order->tax1_percentage ?? 0 }}%):</strong></td>
                            <td class="text-right">RM {{ number_format($order->tax1, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="total-amount-pay"><strong>TOTAL:</strong></td>
                        <td class="text-right total-amount-pay"><strong>RM {{ number_format($order->net_amount ?? 0, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
            <div style="clear: both;"></div>

            @if ($order->remarks)
                <div class="remarks-box"><strong>Remarks:</strong> {{ $order->remarks }}</div>
            @endif

            {{-- CN2 linked to this CN (trade return) --}}
            @if ($order->type === 'CN' && isset($cn2Map[$order->reference_no]) && $cn2Map[$order->reference_no]->count() > 0)
                <div class="cn2-section">
                    <h4>Linked CN2 Manual Credit Notes</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>CN2 Ref No</th>
                                <th>Date</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cn2Map[$order->reference_no] as $cn2)
                                <tr>
                                    <td>{{ $cn2->reference_no }}</td>
                                    <td>{{ \Carbon\Carbon::parse($cn2->order_date)->format('d M Y') }}</td>
                                    <td>{{ $cn2->remarks ?? '—' }}</td>
                                    <td>{{ ucfirst($cn2->status ?? 'N/A') }}</td>
                                    <td class="text-right">RM {{ number_format($cn2->net_amount ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Total CN2:</strong></td>
                                <td class="text-right total-return">
                                    RM {{ number_format($cn2Map[$order->reference_no]->sum('net_amount'), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

        </div>

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>
