@extends('layouts.admin')

@section('title', 'Stock Requests - Kanesan UBS Backend')

@section('page-title', 'Stock Requests')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Stock Requests</li>
@endsection

@section('card-title', 'Stock Requests')

@section('admin-content')
    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.stock-requests.index') }}" class="mb-3">
        <div class="row align-items-end">
            <div class="col-md-2">
                <label class="small font-weight-bold">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-2">
                <label class="small font-weight-bold">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-3">
                <label class="small font-weight-bold">Agent</label>
                <select name="agent_id" class="form-control form-control-sm">
                    <option value="">All Agents</option>
                    @foreach($agents ?? [] as $agent)
                        <option value="{{ $agent->id }}" {{ (string) request('agent_id') === (string) $agent->id ? 'selected' : '' }}>
                            {{ $agent->name ?? $agent->username }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="small font-weight-bold">Status</label>
                <select name="status" class="form-control form-control-sm">
                    <option value="">All</option>
                    <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.stock-requests.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
            <div class="col-md-1">
                <label class="small font-weight-bold">Per Page</label>
                <select name="per_page" class="form-control form-control-sm">
                    <option value="20" {{ (string) ($perPage ?? request('per_page', 20)) === '20' ? 'selected' : '' }}>20</option>
                    <option value="50" {{ (string) ($perPage ?? request('per_page', 20)) === '50' ? 'selected' : '' }}>50</option>
                    <option value="100" {{ (string) ($perPage ?? request('per_page', 20)) === '100' ? 'selected' : '' }}>100</option>
                </select>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Approved / Rejected</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td>{{ $req->id }}</td>
                        <td>{{ $req->user?->name ?? $req->user?->username ?? 'Unknown' }}</td>
                        <td>{{ $req->items->count() }} item(s)</td>
                        <td>
                            @if($req->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($req->status === 'approved')
                                <span class="badge badge-success">Approved</span>
                            @else
                                <span class="badge badge-danger">Rejected</span>
                            @endif
                        </td>
                        <td>{{ $req->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                        <td>
                            @if($req->approved_at)
                                {{ $req->approved_at->format('d M Y H:i') }}
                                @if($req->approvedBy)
                                    <div class="small text-muted">by {{ $req->approvedBy->name ?? $req->approvedBy->username }}</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.stock-requests.show', $req->id) }}"
                               class="btn btn-info btn-sm" title="View / Approve">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No stock requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($requests->hasPages())
        <div class="pagination-wrapper">
            <div>
                Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} results
            </div>
            <div>
                {{ $requests->links('pagination::bootstrap-4') }}
            </div>
        </div>
    @endif
@endsection
