@extends('layouts.app')

@section('title', 'Dashboard - Kanesan UBS Backend')

@section('page-title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    <!-- Welcome Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Welcome, {{ $user->name }}!</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Manage your system efficiently with role-based access control.</p>
                    
                    <div class="alert alert-info">
                        <strong>User:</strong> {{ $user->name }} ({{ $user->username }})<br>
                        <strong>Email:</strong> {{ $user->email }}<br>
                        <strong>Roles:</strong> 
                        @foreach($user->roles as $role)
                            <span class="badge badge-primary">{{ $role->name }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ \App\Models\Role::count() }}</h3>
                    <p>Total Roles</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-tag"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ \App\Models\Permission::count() }}</h3>
                    <p>Total Permissions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ \App\Models\User::count() }}</h3>
                    <p>Active Users</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ \App\Models\Customer::count() ?? 0 }}</h3>
                    <p>Total Customers</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-friends"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Permissions</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($user->getAllPermissions() as $permission)
                            <div class="col-md-4 col-sm-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-check"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ $permission->display_name }}</span>
                                        <span class="info-box-number">{{ $permission->module }}</span>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 100%"></div>
                                        </div>
                                        <span class="progress-description">
                                            {{ $permission->description ?? 'No description available' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
