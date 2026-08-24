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
                    <th style="width: 80px;">ID</th>
                    <th>Financial Year</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th class="text-center" style="width: 220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods ?? [] as $period)
                    @php
                        $isClosed = $period->isClosed();
                        $monthCount = $period->month_count;
                        $yearEndDate = $period->getYearEndDate()->format('Y-m-d');
                        $nextFy = 'FY ' . $period->getNextPeriodStartDate()->format('Y');
                        $nextStart = $period->getNextPeriodStartDate()->format('Y-m-d');
                        $nextEnd = $period->getNextPeriodEndDate()->format('Y-m-d');
                    @endphp
                    <tr class="period-row">
                        <td>{{ $period->id }}</td>
                        <td>{{ $period->financial_year }}</td>
                        <td>{{ $period->start_date?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td>{{ $period->end_date?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td>
                            @if($monthCount > 0)
                                {{ $monthCount }} month{{ $monthCount !== 1 ? 's' : '' }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            {{ $isClosed ? 'Closed' : 'Open' }}
                        </td>
                        <td class="action-buttons text-center">
                            @if(!$isClosed)
                                <button type="button" 
                                        class="btn btn-outline-danger btn-sm btn-close-period"
                                        data-toggle="tooltip"
                                        title="Close Period"
                                        data-period-id="{{ $period->id }}"
                                        data-fy="{{ $period->financial_year }}"
                                        data-start-date="{{ $period->start_date?->format('Y-m-d') }}"
                                        data-current-end="{{ $period->end_date?->format('Y-m-d') }}"
                                        data-closed-end="{{ $yearEndDate }}"
                                        data-next-fy="{{ $nextFy }}"
                                        data-next-start="{{ $nextStart }}"
                                        data-next-end="{{ $nextEnd }}">
                                    <i class="fas fa-lock"></i> Close
                                </button>
                            @else
                                <button type="button" class="btn btn-secondary btn-sm" disabled>
                                    <i class="fas fa-check-circle"></i> Closed
                                </button>
                            @endif

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
                        <td colspan="7" class="text-center text-muted py-4">
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

    <!-- Close Period Confirmation Modal -->
    <div class="modal fade" id="closePeriodModal" tabindex="-1" role="dialog" aria-labelledby="closePeriodModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="closePeriodModalLabel">
                        <i class="fas fa-lock mr-1"></i> Close Period: <span id="modalFyName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="closePeriodForm" method="POST" action="">
                    @csrf
                    <div class="modal-body">
                        <p class="mb-3">
                            Are you sure you want to close financial year <strong id="modalCurrentFyText"></strong>?
                        </p>

                        <div class="card bg-light border p-3 mb-3">
                            <ul class="mb-0 pl-3">
                                <li>End Date will be set to <strong id="modalClosedEndText" class="text-danger"></strong> (Year End).</li>
                                <li>Next year <strong id="modalNextFyText" class="text-success"></strong> (<span id="modalNextStartText"></span> to <span id="modalNextEndText"></span>) will be automatically created (+18 Months).</li>
                            </ul>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-lock"></i> Confirm Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Search filter
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.period-row').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Close Period Modal Handler
    $('.btn-close-period').on('click', function() {
        var periodId = $(this).data('period-id');
        var fy = $(this).data('fy');
        var currentEnd = $(this).data('current-end');
        var closedEnd = $(this).data('closed-end');
        var nextFy = $(this).data('next-fy');
        var nextStart = $(this).data('next-start');
        var nextEnd = $(this).data('next-end');

        $('#modalFyName').text(fy);
        $('#modalCurrentFyText').text(fy);
        $('#modalClosedEndText').text(closedEnd);
        $('#modalNextFyText').text(nextFy);
        $('#modalNextStartText').text(nextStart);
        $('#modalNextEndText').text(nextEnd);

        // Set form action
        $('#closePeriodForm').attr('action', '/admin/periods/' + periodId + '/close');

        // Open modal
        var closePeriodModal = new bootstrap.Modal(document.getElementById('closePeriodModal'));
        closePeriodModal.show();
    });
});
</script>
@endpush
