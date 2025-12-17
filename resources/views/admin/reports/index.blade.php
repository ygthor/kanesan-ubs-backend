@extends('layouts.admin')

@section('title', 'Reports - Kanesan UBS Backend')

@section('page-title', 'Reports')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Reports</li>
@endsection

@section('card-title', 'Available Reports')

@section('admin-content')
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th style="width: 50px;">Icon</th>
                    <th>Report Name</th>
                    <th>Description</th>
                    <th style="width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports ?? [] as $report)
                    <tr>
                        <td class="text-center">
                            <i class="{{ $report['icon'] }} fa-2x text-primary"></i>
                        </td>
                        <td class="font-weight-bold">{{ $report['name'] }}</td>
                        <td>{{ $report['description'] }}</td>
                        <td>
                            <a href="{{ $report['route'] }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View Report
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No reports available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
