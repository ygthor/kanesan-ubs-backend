@extends('layouts.admin')

@section('title', 'View Territory - Kanesan UBS Backend')

@section('page-title', 'View Territory')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.territories.index') }}">Territory Management</a></li>
    <li class="breadcrumb-item active">View Territory</li>
@endsection

@section('card-title', 'Territory Details')

@section('card-tools')
    <a href="{{ route('admin.territories.edit', $territory->id) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a href="{{ route('admin.territories.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $territory->id }}</dd>

                        <dt class="col-sm-3">Area Code:</dt>
                        <dd class="col-sm-9">
                            <strong>{{ $territory->area }}</strong>
                        </dd>

                        <dt class="col-sm-3">Description:</dt>
                        <dd class="col-sm-9">{{ $territory->description }}</dd>

                        <dt class="col-sm-3">Created At:</dt>
                        <dd class="col-sm-9">{{ $territory->created_at?->format('M d, Y H:i:s') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Updated At:</dt>
                        <dd class="col-sm-9">{{ $territory->updated_at?->format('M d, Y H:i:s') ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.territories.edit', $territory->id) }}" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-edit"></i> Edit Territory
                    </a>
                    <form method="POST" action="{{ route('admin.territories.destroy', $territory->id) }}" 
                          onsubmit="return confirm('Are you sure you want to delete this territory?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> Delete Territory
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

