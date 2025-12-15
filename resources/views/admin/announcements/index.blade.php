@extends('layouts.admin')

@section('title', 'Announcement Management - Kanesan UBS Backend')

@section('page-title', 'Announcement Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Announcements</li>
@endsection

@section('card-title', 'Announcements')

@section('card-tools')
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add Announcement
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

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Schedule</th>
                    <th>Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($announcements ?? [] as $announcement)
                    <tr>
                        <td>
                            <strong>{{ $announcement->title }}</strong>
                            <div class="text-muted small">
                                {{ \Illuminate\Support\Str::limit($announcement->body, 80) }}
                            </div>
                        </td>
                        <td>
                            @if($announcement->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="small text-muted">
                                Starts: {{ $announcement->starts_at?->format('Y-m-d H:i') ?? 'Now' }}<br>
                                Ends: {{ $announcement->ends_at?->format('Y-m-d H:i') ?? 'No end' }}
                            </div>
                        </td>
                        <td>{{ $announcement->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="action-buttons text-right">
                            <a href="{{ route('admin.announcements.show', $announcement->id) }}"
                               class="btn btn-info btn-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.announcements.edit', $announcement->id) }}"
                               class="btn btn-warning btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST"
                                  action="{{ route('admin.announcements.destroy', $announcement->id) }}"
                                  style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete this announcement?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            <i class="fas fa-bullhorn fa-3x mb-3"></i>
                            <p>No announcements yet.</p>
                            <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First Announcement
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($announcements) && $announcements->hasPages())
        <div class="pagination-wrapper">
            <div>
                Showing {{ $announcements->firstItem() }} to {{ $announcements->lastItem() }} of {{ $announcements->total() }} results
            </div>
            <div>
                {{ $announcements->links() }}
            </div>
        </div>
    @endif
@endsection
