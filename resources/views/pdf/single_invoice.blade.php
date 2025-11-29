{{-- resources/views/pdf/single_invoice.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    {{-- UPDATED: The title is now dynamic --}}
    <title>{{ $invoice->document_title }} {{ $invoice->REFNO }}</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 25px;
            font-size: 14px;
        }

        .invoice-container {
            padding: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .header h1 {
            margin: 0;
        }

        .text-right {
            text-align: right;
        }

        .totals {
            margin-top: 20px;
            float: right;
            width: 300px;
        }

        .totals table td {
            border: none;
        }
        
        .total-amount-pay {
            font-size: 18px;
            font-weight: bold;
        }
        
        .total-return {
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <h1>{{ $invoice->document_title }}</h1>
            <p><strong>Ref No:</strong> {{ $invoice->REFNO }}</p>
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->DATE)->format('d M Y') }}</p>
            <hr>
            <p>
                <strong>Bill To:</strong><br>
                @if ($invoice->customer)
                    {{ $invoice->customer->company_name ?? $invoice->customer->name ?? $invoice->NAME }}
                    @if (!empty($invoice->customer->company_name2))
                        <br>{{ $invoice->customer->company_name2 }}
                    @endif
                    ({{ $invoice->CUSTNO  }})<br>
                    @if (!empty($invoice->customer->address1))
                        {{ $invoice->customer->address1 }}<br>
                    @endif
                    @if (!empty($invoice->customer->address2))
                        {{ $invoice->customer->address2 }}<br>
                    @endif
                    @if (!empty($invoice->customer->address3))
                        {{ $invoice->customer->address3 }}<br>
                    @endif
                    @if (!empty($invoice->customer->postcode) || !empty($invoice->customer->state))
                        {{ trim(($invoice->customer->postcode ?? '') . ' ' . ($invoice->customer->state ?? '')) }}<br>
                    @endif
                @else
                    {{ $invoice->NAME }} ({{ $invoice->CUSTNO  }})
                @endif
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalReturnAmount = 0;
                @endphp
                @foreach ($invoice->items as $item)
                    @php
                        // Calculate return amount (items with negative SIGN or negative AMT_BIL)
                        if (($item->SIGN ?? 1) < 0 || $item->AMT_BIL < 0) {
                            $totalReturnAmount += abs($item->AMT_BIL);
                        }
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $item->DESP }}
                            @if (($item->SIGN ?? 1) < 0 || $item->AMT_BIL < 0)
                                <span style="color: red;"> (RETURN)</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format(abs($item->QTY), 2) }}</td>
                        <td class="text-right">{{ number_format($item->PRICE, 2) }}</td>
                        {{-- CORRECTED: Use AMT_BIL for the line item amount --}}
                        <td class="text-right">{{ number_format($item->AMT_BIL, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                @php
                    $totalReturnAmount = 0;
                    foreach ($invoice->items as $item) {
                        if (($item->SIGN ?? 1) < 0 || $item->AMT_BIL < 0) {
                            $totalReturnAmount += abs($item->AMT_BIL);
                        }
                    }
                @endphp
                {{-- CORRECTED: Use fields from your Artran model --}}
                <tr>
                    <td><strong>S.amt:</strong></td>
                    <td class="text-right">RM {{ number_format($invoice->GROSS_BIL, 2) }}</td>
                </tr>
                @if ($totalReturnAmount > 0)
                <tr>
                    <td class="total-return"><strong>RETURN AMOUNT:</strong></td>
                    <td class="text-right total-return"><strong>RM {{ number_format($totalReturnAmount, 2) }}</strong></td>
                </tr>
                @endif
                <tr>
                    {{-- CORRECTED: Added a check to prevent division by zero --}}
                    <td><strong>Tax
                            ({{ $invoice->GROSS_BIL > 0 ? number_format(($invoice->TAX1_BIL / $invoice->GROSS_BIL) * 100, 2) : '0.00' }}%):</strong>
                    </td>
                    <td class="text-right">RM {{ number_format($invoice->TAX1_BIL, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>C.Amt:</strong></td>
                    <td class="text-right">RM {{ number_format($invoice->GRAND_BIL, 2) }}</td>
                </tr>
                <tr>
                    <td class="total-amount-pay"><strong>TOTAL AMOUNT TO PAY:</strong></td>
                    <td class="text-right total-amount-pay"><strong>RM {{ number_format($invoice->NET_BIL, 2) }}</strong></td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>

</html>
