@extends('layouts.admin')

@section('title', 'View Announcement - Kanesan UBS Backend')

@section('page-title', 'Announcement Details')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.announcements.index') }}">Announcements</a></li>
    <li class="breadcrumb-item active">View</li>
@endsection

@section('card-title', 'Announcement')

@section('card-tools')
    <a href="{{ route('admin.announcements.edit', $announcement->id) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h4 class="card-title">{{ $announcement->title }}</h4>
                    <p class="card-text">{{ $announcement->body }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Status:</strong>
                        @if($announcement->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </p>
                    <p><strong>Starts At:</strong> {{ $announcement->starts_at?->format('Y-m-d H:i') ?? 'Now' }}</p>
                    <p><strong>Ends At:</strong> {{ $announcement->ends_at?->format('Y-m-d H:i') ?? 'No end' }}</p>
                    <p><strong>Created:</strong> {{ $announcement->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
                    <p><strong>Updated:</strong> {{ $announcement->updated_at?->format('Y-m-d H:i') ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
