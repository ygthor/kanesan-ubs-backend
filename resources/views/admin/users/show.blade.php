@extends('layouts.admin')

@section('title', 'User Details - Kanesan UBS Backend')

@section('page-title', 'User Details')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active">{{ $user->name }}</li>
@endsection

@section('card-title', 'User: ' . $user->name)

@section('card-tools')
    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit User
    </a>
    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <!-- User Information -->
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-user"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Full Name</span>
                            <span class="info-box-number">{{ $user->name }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-at"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Username</span>
                            <span class="info-box-number">{{ $user->username }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-envelope"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Email Address</span>
                            <span class="info-box-number">{{ $user->email }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Member Since</span>
                            <span class="info-box-number">{{ $user->created_at?->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Details Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>User ID:</strong></td>
                                    <td>{{ $user->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email Verified:</strong></td>
                                    <td>
                                        @if ($user->email_verified_at)
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="badge badge-warning">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Last Login:</strong></td>
                                    <td>
                                        @if ($user->last_login_at)
                                            {{ $user->last_login_at->format('M d, Y H:i') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $user->created_at?->format('M d, Y H:i:s') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ $user->updated_at?->format('M d, Y H:i:s') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        @if($user->status === 'active')
                                            <span class="badge badge-success">Active</span>
                                        @elseif($user->status === 'suspended')
                                            <span class="badge badge-danger">Suspended</span>
                                        @else
                                            <span class="badge badge-warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Roles Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Assigned Roles</h5>
                </div>
                <div class="card-body">
                    @if ($user->roles->count() > 0)
                        @foreach ($user->roles as $role)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-primary badge-lg">{{ $role->name }}</span>
                                <small class="text-muted">{{ $role->role_id }}</small>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted">
                            <i class="fas fa-user-tag fa-2x mb-2"></i>
                            <p>No roles assigned</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="text-right">
        @if ($user->id !== auth()->id())
            <form method="POST" class="text-right" action="{{ route('admin.users.destroy', $user->id) }}" style="display: inline;"
                class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-danger btn-sm btn-delete">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </form>
        @else
            <button class="btn btn-secondary btn-sm" disabled>
                <i class="fas fa-ban"></i> Cannot Delete Self
            </button>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .info-box {
            margin-bottom: 1rem;
        }

        .badge-lg {
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
        }

        .table-borderless td {
            padding: 0.5rem 0;
            border: none;
        }
    </style>
@endpush
