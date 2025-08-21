{{-- resources/views/pdf/invoices_batch_print.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Invoices</title>
    <style>
        body { font-family: sans-serif; margin: 25px; }
        .invoice-container {
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 25px;
        }
        .page-break {
            page-break-after: always;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th { background-color: #f2f2f2; }
        .header, .footer { margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .text-right { text-align: right; }
        .totals { margin-top: 20px; float: right; width: 250px; }
    </style>
</head>
<body>
    @foreach ($invoices as $invoice)
        <div class="invoice-container">
            <div class="header">
                <h1>Invoice</h1>
                <p><strong>Ref No:</strong> {{ $invoice->REFNO }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->DATE)->format('d M Y') }}</p>
                <hr>
                <p>
                    <strong>Bill To:</strong><br>
                    {{ $invoice->customer->name ?? 'N/A' }} ({{ $invoice->CUSTNO }})<br>
                    {!! nl2br(e($invoice->customer->address)) !!}
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
                            <td class="text-right">{{ number_format($item->AMT, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                 <table>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right">RM {{ number_format($invoice->NETAMT, 2) }}</td>
                    </tr>
                     <tr>
                        <td><strong>Total:</strong></td>
                        <td class="text-right"><strong>RM {{ number_format($invoice->NETBIL, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
            <div style="clear: both;"></div>
        </div>

        {{-- Add a page break after each invoice, except for the last one --}}
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>