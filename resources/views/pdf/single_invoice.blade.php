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
                {{ $invoice->customer->name ?? $invoice->NAME }} ({{ $invoice->CUSTNO  }})<br>
                @if ($invoice->customer)
                    {!! nl2br(e($invoice->customer->address)) !!}
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
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->DESP }}</td>
                        <td class="text-right">{{ number_format($item->QTY, 2) }}</td>
                        <td class="text-right">{{ number_format($item->PRICE, 2) }}</td>
                        {{-- CORRECTED: Use AMT_BIL for the line item amount --}}
                        <td class="text-right">{{ number_format($item->AMT_BIL, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                {{-- CORRECTED: Use fields from your Artran model --}}
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td class="text-right">RM {{ number_format($invoice->GROSS_BIL, 2) }}</td>
                </tr>
                <tr>
                    {{-- CORRECTED: Added a check to prevent division by zero --}}
                    <td><strong>Tax
                            ({{ $invoice->GROSS_BIL > 0 ? number_format(($invoice->TAX1_BIL / $invoice->GROSS_BIL) * 100, 2) : '0.00' }}%):</strong>
                    </td>
                    <td class="text-right">RM {{ number_format($invoice->TAX1_BIL, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Grand Total:</strong></td>
                    <td class="text-right">RM {{ number_format($invoice->GRAND_BIL, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Amount Due:</strong></td>
                    <td class="text-right"><strong>RM {{ number_format($invoice->NET_BIL, 2) }}</strong></td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>

</html>
