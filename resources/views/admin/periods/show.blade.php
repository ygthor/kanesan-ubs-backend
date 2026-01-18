@extends('layouts.admin')

@section('title', 'View Period - Kanesan UBS Backend')

@section('page-title', 'View Period')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.periods.index') }}">Period Management</a></li>
    <li class="breadcrumb-item active">View Period</li>
@endsection

@section('card-title', 'Period Details')

@section('card-tools')
    <a href="{{ route('admin.periods.edit', $period->id) }}" class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> Edit
    </a>
    <a href="{{ route('admin.periods.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('admin-content')
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $period->id }}</dd>

                        <dt class="col-sm-3">Start Date:</dt>
                        <dd class="col-sm-9">{{ $period->start_date?->format('Y-m-d') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">End Date:</dt>
                        <dd class="col-sm-9">{{ $period->end_date?->format('Y-m-d') ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Created At:</dt>
                        <dd class="col-sm-9">{{ $period->created_at ?? 'N/A' }}</dd>

                        <dt class="col-sm-3">Updated At:</dt>
                        <dd class="col-sm-9">{{ $period->updated_at ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Months Breakdown -->
            @if($period->start_date && $period->end_date)
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar"></i> Months in Period</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $months = [];
                            $current = $period->start_date->copy();
                            $end = $period->end_date->copy();
                            
                            while ($current <= $end) {
                                $months[] = $current->copy();
                                $current->addMonth();
                            }
                            
                            $monthCount = count($months);
                        @endphp
                        <p class="text-muted mb-3">
                            <strong>Total:</strong> {{ $monthCount }} month{{ $monthCount !== 1 ? 's' : '' }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 10%">#</th>
                                        <th>Year-Month</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($months as $index => $month)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>{{ $month->format('Y') }}-{{ $month->format('m') }} </td>
                                            
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.periods.edit', $period->id) }}" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-edit"></i> Edit Period
                    </a>
                    <form method="POST" action="{{ route('admin.periods.destroy', $period->id) }}"
                          onsubmit="return confirm('Are you sure you want to delete this period?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> Delete Period
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
