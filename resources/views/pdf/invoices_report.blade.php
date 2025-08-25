<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
        .total-row { font-weight: bold; background: #e9e9e9; }
        .right { text-align:right; }
    </style>
</head>
<body>
    <h2>Invoice Summary Report</h2>
    <p>Customer: {{ $customer->name }} ({{ $customer->customer_code }})</p>
    <p>Period: {{ $start_date }} to {{ $end_date }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Remarks</th>
                <th class="right">DEBIT</th>
                <th class="right">CREDIT</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalDebit = 0; 
                $totalCredit = 0; 
            @endphp

            @foreach($invoices as $idx => $invoice)
                @php 
                    $totalDebit += $invoice->DEBIT_BIL; 
                    $totalCredit += $invoice->CREDIT_BIL; 
                @endphp
                <tr>
                    <td>{{ $idx+1 }}</td>
                    <td>{{ $invoice->REFNO }}</td>
                    <td>{{ displayDate($invoice->DATE) }}</td>
                    <td>{{ $invoice->NOTE }}</td>
                    <td class="right">{{ number_format($invoice->DEBIT_BIL, 2) }}</td>
                    <td class="right">{{ number_format($invoice->CREDIT_BIL, 2) }}</td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" class="right">Total Debit</td>
                <td class="right">{{ number_format($totalDebit, 2) }}</td>
                <td></td>
            </tr>
            <tr class="total-row">
                <td colspan="5" class="right">Total Credit</td>
                <td class="right">{{ number_format($totalCredit, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="5" class="right">Balance (Debit - Credit)</td>
                <td class="right">{{ number_format($totalDebit - $totalCredit, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
