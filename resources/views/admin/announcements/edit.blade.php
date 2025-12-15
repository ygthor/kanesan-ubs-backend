@extends('layouts.form')

@section('title', 'Edit Announcement - Kanesan UBS Backend')

@section('page-title', 'Edit Announcement')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.announcements.index') }}">Announcements</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('card-title', 'Edit Announcement')

@section('form-action', route('admin.announcements.update', $announcement->id))

@section('form-method')
    @method('PUT')
@endsection

@section('submit-text', 'Save Changes')

@section('cancel-url', route('admin.announcements.index'))

@section('form-fields')
    <div class="form-group">
        <label for="title" class="form-label required-field">Title</label>
        <input type="text" class="form-control @error('title') is-invalid @enderror"
               id="title" name="title" value="{{ old('title', $announcement->title) }}" required>
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="body" class="form-label required-field">Message</label>
        <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body"
                  rows="5" required>{{ old('body', $announcement->body) }}</textarea>
        @error('body')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label for="starts_at" class="form-label">Starts At</label>
            <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror"
                   id="starts_at" name="starts_at"
                   value="{{ old('starts_at', optional($announcement->starts_at)->format('Y-m-d\TH:i')) }}">
            @error('starts_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="help-text">Leave blank to start immediately.</div>
        </div>
        <div class="form-group col-md-6">
            <label for="ends_at" class="form-label">Ends At</label>
            <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror"
                   id="ends_at" name="ends_at"
                   value="{{ old('ends_at', optional($announcement->ends_at)->format('Y-m-d\TH:i')) }}">
            @error('ends_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="help-text">Leave blank for no expiry.</div>
        </div>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
               value="1" {{ old('is_active', $announcement->is_active) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Active</label>
    </div>
@endsection

@section('form-sidebar')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Display Rules</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Adjust timing and active status to control when this announcement
                is shown to mobile users.
            </p>
            <ul class="small pl-3 mb-0">
                <li>Inactive announcements are hidden from the app.</li>
                <li>Start/End times are optional.</li>
                <li>Ordering follows start time then creation time.</li>
            </ul>
        </div>
    </div>
@endsection
