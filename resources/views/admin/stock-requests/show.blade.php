@extends('layouts.admin')

@section('title', 'Stock Request #' . $stockRequest->id . ' - Kanesan UBS Backend')

@section('page-title', 'Stock Request #' . $stockRequest->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.stock-requests.index') }}">Stock Requests</a></li>
    <li class="breadcrumb-item active">#{{ $stockRequest->id }}</li>
@endsection

@section('card-title', 'Stock Request #' . $stockRequest->id)

@section('card-tools')
    <a href="{{ route('admin.stock-requests.export.pdf', $stockRequest->id) }}" target="_blank" class="btn btn-danger btn-sm mr-2">
        <i class="fas fa-file-pdf"></i> Print PDF
    </a>
    <a href="{{ route('admin.stock-requests.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('admin-content')
    @php
        $formatQty = function ($qty) {
            $v = (float) $qty;
            if (abs($v - round($v)) < 0.00001) {
                return (string) (int) round($v);
            }
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        };
    @endphp

    {{-- Request Summary --}}
    <div class="row mb-3">
        <div class="col-md-6">
            <table class="table table-sm table-borderless">
                <tr>
                    <th style="width:140px">Agent</th>
                    <td>{{ $stockRequest->user?->name ?? $stockRequest->user?->username ?? 'Unknown' }}</td>
                </tr>
                <tr>
                    <th>Submitted</th>
                    <td>{{ $stockRequest->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @if($stockRequest->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($stockRequest->status === 'approved')
                            <span class="badge badge-success">Approved</span>
                        @else
                            <span class="badge badge-danger">Rejected</span>
                        @endif
                    </td>
                </tr>
                @if($stockRequest->notes)
                <tr>
                    <th>Agent Notes</th>
                    <td>{{ $stockRequest->notes }}</td>
                </tr>
                @endif
                @if($stockRequest->admin_notes)
                <tr>
                    <th>Admin Notes</th>
                    <td>{{ $stockRequest->admin_notes }}</td>
                </tr>
                @endif
                @if($stockRequest->approved_at)
                <tr>
                    <th>{{ ucfirst($stockRequest->status) }} At</th>
                    <td>
                        {{ $stockRequest->approved_at->format('d M Y H:i') }}
                        @if($stockRequest->approvedBy)
                            by {{ $stockRequest->approvedBy->name ?? $stockRequest->approvedBy->username }}
                        @endif
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Items Table (read-only if already processed) --}}
    @if($stockRequest->status === 'pending')
        <form method="POST" action="{{ route('admin.stock-requests.approve', $stockRequest->id) }}">
            @csrf
            <h6 class="font-weight-bold mb-2">Requested Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Unit</th>
                            <th style="width:130px">Requested Qty</th>
                            <th style="width:150px">Approved Qty <small class="text-muted">(editable)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $itemIndex = 0; @endphp
                        @foreach($groupedItems as $groupName => $items)
                            <tr>
                                <td colspan="5" class="bg-light font-weight-bold">Group :{{ $groupName }}</td>
                            </tr>
                            @foreach($items as $item)
                                <tr>
                                    <input type="hidden" name="items[{{ $itemIndex }}][id]" value="{{ $item->id }}">
                                    <td>{{ $item->item_no }}</td>
                                    <td>{{ $item->description ?? '-' }}</td>
                                    <td>{{ $item->unit ?? '-' }}</td>
                                    <td class="text-right">{{ $formatQty($item->requested_qty) }}</td>
                                    <td>
                                        <input type="number"
                                               name="items[{{ $itemIndex }}][approved_qty]"
                                               class="form-control form-control-sm"
                                               value="{{ $item->requested_qty }}"
                                               min="0"
                                               step="1"
                                               required>
                                    </td>
                                </tr>
                                @php $itemIndex++; @endphp
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-group">
                <label class="font-weight-bold small">Admin Notes (optional)</label>
                <textarea name="admin_notes" class="form-control" rows="2" maxlength="500"
                          placeholder="Optional notes to the agent...">{{ old('admin_notes') }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success mr-2">
                    <i class="fas fa-check"></i> Approve Request
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.stock-requests.reject', $stockRequest->id) }}" class="mt-2">
            @csrf
            <div class="form-group">
                <label class="font-weight-bold small">Rejection Reason (optional)</label>
                <textarea name="admin_notes" class="form-control" rows="2" maxlength="500"
                          placeholder="Reason for rejection..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Reject this stock request?')">
                <i class="fas fa-times"></i> Reject Request
            </button>
        </form>

    @else
        {{-- Read-only view for already-processed requests --}}
        <h6 class="font-weight-bold mb-2">Requested Items</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th class="text-right">Requested Qty</th>
                        <th class="text-right">Approved Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupedItems as $groupName => $items)
                        <tr>
                            <td colspan="5" class="bg-light font-weight-bold">Group :{{ $groupName }}</td>
                        </tr>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item->item_no }}</td>
                                <td>{{ $item->description ?? '-' }}</td>
                                <td>{{ $item->unit ?? '-' }}</td>
                                <td class="text-right">{{ $formatQty($item->requested_qty) }}</td>
                                <td class="text-right">
                                    @if($item->approved_qty !== null)
                                        {{ $formatQty($item->approved_qty) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
