<style>
    body { font-family: helvetica, sans-serif; font-size: 10px; color: #111827; }
    .title { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 6px; }
    .meta { margin-bottom: 8px; }
    .meta table { width: 100%; border-collapse: collapse; }
    .meta td { padding: 2px 4px; vertical-align: top; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th, table.items td { border: 1px solid #d1d5db; padding: 4px 6px; }
    table.items th { background: #f3f4f6; text-align: left; }
    .group-row td { background: #f9fafb; font-weight: bold; }
    .text-right { text-align: right; }
</style>

<div class="title">STOCK REQUEST #{{ $stockRequest->id }}</div>

<div class="meta">
    <table>
        <tr>
            <td style="width: 80px;"><strong>Agent</strong></td>
            <td>{{ $stockRequest->user?->name ?? $stockRequest->user?->username ?? 'Unknown' }}</td>
            <td style="width: 90px;"><strong>Status</strong></td>
            <td>{{ strtoupper((string) $stockRequest->status) }}</td>
        </tr>
        <tr>
            <td><strong>Submitted</strong></td>
            <td>{{ $stockRequest->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
            <td><strong>Printed</strong></td>
            <td>{{ $printedAt }}</td>
        </tr>
        <tr>
            <td><strong>Items</strong></td>
            <td>{{ $stockRequest->items->count() }} item(s)</td>
            <td></td>
            <td></td>
        </tr>
    </table>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width: 18%;">Item Code</th>
            <th>Description</th>
            <th style="width: 10%;">Unit</th>
            <th style="width: 14%;" class="text-right">Requested Qty</th>
        </tr>
    </thead>
    <tbody>
        @foreach($groupedItems as $groupName => $items)
            <tr class="group-row">
                <td colspan="4">Group :{{ $groupName }}</td>
            </tr>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->item_no }}</td>
                    <td>{{ $item->description ?? '-' }}</td>
                    <td>{{ $item->unit ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) $item->requested_qty, 0) }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
