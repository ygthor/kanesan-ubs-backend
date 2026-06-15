<!DOCTYPE html>
<html>
<head>
    <title>Customer Statement - {{ $customer->name }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: helvetica, sans-serif;
            color: #333;
            font-size: 8.5pt;
            line-height: 1.3;
        }   
        .header-title {
            text-align: center;
            color: #007bff;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .subtitle {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 15px;
            color: #555;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .info-table td {
            padding: 3px 0;
            font-size: 9pt;
        }
        .statement-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .statement-table th,
        .statement-table td {
            border: 0.5pt solid #aaaaaa;
            padding: 5px 4px;
            vertical-align: middle;
        }
        .statement-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 8.5pt;
        }
        .numeric-column {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-danger {
            color: #dc3545;
        }
        .text-success {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="header-title">PERKHIDMATAN DAN JUALAN KANESAN BERSAUDARA</div>
    <div class="subtitle">CUSTOMER STATEMENT</div>
    
    <table class="info-table" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td width="18%"><strong>Customer Code:</strong></td>
            <td width="32%">{{ $customer->customer_code }}</td>
            <td width="18%"><strong>Statement Date:</strong></td>
            <td width="32%">{{ date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Customer Name:</strong></td>
            <td>{{ $customer->name }}</td>
            <td><strong>Period:</strong></td>
            <td>{{ date('d/m/Y', strtotime($fromDate)) }} - {{ date('d/m/Y', strtotime($toDate)) }}</td>
        </tr>
        @if($customer->company_name)
        <tr>
            <td><strong>Company Name:</strong></td>
            <td colspan="3">{{ $customer->company_name }}</td>
        </tr>
        @endif
    </table>

    <table class="statement-table" cellpadding="3">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th width="38%">Description</th>
                <th width="16%" class="numeric-column">Debit (INV)</th>
                <th width="16%" class="numeric-column">Credit (REC/CN)</th>
                <th width="18%" class="numeric-column">Running Balance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $runningBalance = 0;
            @endphp
            @forelse($plData as $item)
                @php
                    // Running Balance = Receipts (Credit) - Invoices (Debit) + Credit Notes (Credit)
                    if ($item['type'] === 'inv') {
                        $runningBalance -= $item['debit'];
                    } else {
                        // receipt or cn
                        $runningBalance += $item['credit'];
                    }
                @endphp
                <tr>
                    <td class="text-center" width="12%">{{ date('d/m/Y', strtotime($item['date'])) }}</td>
                    <td width="38%">{{ $item['description'] }}</td>
                    <td class="numeric-column" width="16%">{{ $item['debit'] > 0 ? 'RM ' . number_format($item['debit'], 2) : '-' }}</td>
                    <td class="numeric-column" width="16%">{{ $item['credit'] > 0 ? 'RM ' . number_format($item['credit'], 2) : '-' }}</td>
                    <td class="numeric-column {{ $runningBalance < 0 ? 'text-danger' : ($runningBalance > 0 ? 'text-success' : '') }}" width="18%">
                        RM {{ number_format($runningBalance, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No transactions found for this period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f8f9fa;">
                <td colspan="2" class="text-right">Total Debit / Credit:</td>
                <td class="numeric-column">RM {{ number_format($totalDebit, 2) }}</td>
                <td class="numeric-column">RM {{ number_format($totalCredit, 2) }}</td>
                <td></td>
            </tr>
            <tr style="font-weight: bold; background-color: #e9ecef;">
                <td colspan="4" class="text-right">Net Balance:</td>
                <td class="numeric-column {{ $balance < 0 ? 'text-danger' : ($balance > 0 ? 'text-success' : '') }}">
                    RM {{ number_format($balance, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
