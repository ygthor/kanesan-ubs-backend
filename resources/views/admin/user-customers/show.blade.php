@extends('layouts.admin')

@section('title', 'View User-Customer Assignment - Kanesan UBS Backend')

@section('page-title', 'View User-Customer Assignment')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.user-customers.index') }}">User-Customer Assignments</a></li>
    <li class="breadcrumb-item active">View Assignment</li>
@endsection

@section('card-title', 'User-Customer Assignment Details')

@section('card-tools')
    <a href="{{ route('admin.user-customers.edit', $userCustomer) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a href="{{ route('admin.user-customers.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $userCustomer->user->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>{{ $userCustomer->user->username }}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ $userCustomer->user->email }}</td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $userCustomer->user->formatted_created_at }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $userCustomer->customer->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Customer Code:</strong></td>
                            <td>
                                <span class="badge badge-info">{{ $userCustomer->customer->customer_code }}</span>
                            </td>
                        </tr>
                        @if($userCustomer->customer->company_name)
                        <tr>
                            <td><strong>Company:</strong></td>
                            <td>{{ $userCustomer->customer->company_name }}</td>
                        </tr>
                        @endif
                        @if($userCustomer->customer->email)
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ $userCustomer->customer->email }}</td>
                        </tr>
                        @endif
                        @if($userCustomer->customer->phone)
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>{{ $userCustomer->customer->phone }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Assignment Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Assignment ID:</strong></td>
                            <td>{{ $userCustomer->id }}</td>
                        </tr>
                        <tr>
                            <td><strong>Assigned Date:</strong></td>
                            <td>{{ $userCustomer->created_at->format('M d, Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td>{{ $userCustomer->updated_at->format('M d, Y H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.user-customers.edit', $userCustomer) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit Assignment
                    </a>
                    <form action="{{ route('admin.user-customers.destroy', $userCustomer) }}" 
                          method="POST" class="d-inline" 
                          onsubmit="return confirm('Are you sure you want to delete this assignment?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Assignment
                        </button>
                    </form>
                    <a href="{{ route('admin.user-customers.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
