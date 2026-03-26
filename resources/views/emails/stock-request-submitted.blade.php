<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Request Submitted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <h2 style="margin-bottom: 8px;">New Stock Request Submitted</h2>
    <p style="margin: 0 0 12px;">
        A new stock request has been submitted by
        <strong>{{ $submittedBy->name ?? $submittedBy->username ?? ('User #' . $submittedBy->id) }}</strong>.
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; max-width: 760px;">
        <tr>
            <td style="width: 180px;"><strong>Request ID</strong></td>
            <td>#{{ $stockRequest->id }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>{{ strtoupper($stockRequest->status) }}</td>
        </tr>
        <tr>
            <td><strong>Submitted At</strong></td>
            <td>{{ optional($stockRequest->created_at)->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <td><strong>Notes</strong></td>
            <td>{{ $stockRequest->notes ?: '-' }}</td>
        </tr>
    </table>

    <h3 style="margin-top: 16px; margin-bottom: 8px;">Items</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; max-width: 900px;">
        <thead>
            <tr style="background: #f3f4f6;">
                <th align="left">Item No</th>
                <th align="left">Description</th>
                <th align="left">Unit</th>
                <th align="right">Requested Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockRequest->items as $item)
                <tr>
                    <td>{{ $item->item_no }}</td>
                    <td>{{ $item->description ?: '-' }}</td>
                    <td>{{ $item->unit ?: '-' }}</td>
                    <td align="right">{{ rtrim(rtrim(number_format((float) $item->requested_qty, 4, '.', ''), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

