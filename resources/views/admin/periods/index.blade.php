@extends('layouts.admin')

@section('title', 'Period Management - Kanesan UBS Backend')

@section('page-title', 'Period Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Period Management</li>
@endsection

@section('card-title', 'Periods')

@section('card-tools')
    <a href="{{ route('admin.periods.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add New Period
    </a>
@endsection

@section('admin-content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Search and Filters -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control search-box" placeholder="Search periods..." id="searchInput">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Periods Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods ?? [] as $period)
                    <tr>
                        <td>{{ $period->id }}</td>
                        <td>
                            <strong>{{ $period->name }}</strong>
                            @if($period->description)
                                <br><small class="text-muted">{{ $period->description }}</small>
                            @endif
                        </td>
                        <td>{{ $period->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                        <td>{{ $period->end_date?->format('M d, Y') ?? 'N/A' }}</td>
                        <td>
                            @if($period->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="action-buttons">
                            <a href="{{ route('admin.periods.show', $period->id) }}"
                               class="btn btn-info btn-sm"
                               data-toggle="tooltip"
                               title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.periods.edit', $period->id) }}"
                               class="btn btn-warning btn-sm"
                               data-toggle="tooltip"
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.periods.destroy', $period->id) }}"
                                  style="display: inline;" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-danger btn-sm"
                                        data-toggle="tooltip"
                                        title="Delete"
                                        onclick="return confirm('Are you sure you want to delete this period?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                            <p>No periods found.</p>
                            <a href="{{ route('admin.periods.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First Period
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if(isset($periods) && $periods->hasPages())
        <div class="pagination-wrapper">
            <div>
                Showing {{ $periods->firstItem() }} to {{ $periods->lastItem() }} of {{ $periods->total() }} results
            </div>
            <div>
                {{ $periods->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Search functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>
@endpush
