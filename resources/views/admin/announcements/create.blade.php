@extends('layouts.form')

@section('title', 'Create Announcement - Kanesan UBS Backend')

@section('page-title', 'Create Announcement')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.announcements.index') }}">Announcements</a></li>
    <li class="breadcrumb-item active">Create</li>
@endsection

@section('card-title', 'Create New Announcement')

@section('form-action', route('admin.announcements.store'))

@section('submit-text', 'Create Announcement')

@section('cancel-url', route('admin.announcements.index'))

@section('form-fields')
    <div class="form-group">
        <label for="title" class="form-label required-field">Title</label>
        <input type="text" class="form-control @error('title') is-invalid @enderror"
               id="title" name="title" value="{{ old('title') }}" required
               placeholder="Short title for the announcement">
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="body" class="form-label required-field">Message</label>
        <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body"
                  rows="5" required placeholder="Details to show on the app">{{ old('body') }}</textarea>
        @error('body')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label for="starts_at" class="form-label">Starts At</label>
            <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror"
                   id="starts_at" name="starts_at" value="{{ old('starts_at') }}">
            @error('starts_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="help-text">Leave blank to start immediately.</div>
        </div>
        <div class="form-group col-md-6">
            <label for="ends_at" class="form-label">Ends At</label>
            <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror"
                   id="ends_at" name="ends_at" value="{{ old('ends_at') }}">
            @error('ends_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="help-text">Leave blank for no expiry.</div>
        </div>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
               value="1" {{ old('is_active', true) ? 'checked' : '' }}>
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
                Announcements appear on the mobile dashboard. Control when they
                should be visible using the start/end time and active toggle.
            </p>
            <ul class="small pl-3 mb-0">
                <li>Only active announcements are returned by the API.</li>
                <li>Start/End times are optional.</li>
                <li>Multiple announcements will show in the order of start time.</li>
            </ul>
        </div>
    </div>
@endsection
