@extends('layouts.admin')

@section('title', 'Territory Management - Kanesan UBS Backend')

@section('page-title', 'Territory Management')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item active">Territory Management</li>
@endsection

@section('card-title', 'Territories')

@section('card-tools')
    <a href="{{ route('admin.territories.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add New Territory
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

    <!-- Search and Filters -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control search-box" placeholder="Search territories..." id="searchInput">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Territories Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Area</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($territories ?? [] as $territory)
                    <tr>
                        <td>{{ $territory->id }}</td>
                        <td>
                            <strong>{{ $territory->area }}</strong>
                        </td>
                        <td>{{ $territory->description }}</td>
                        <td>{{ $territory->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="action-buttons">
                            <a href="{{ route('admin.territories.show', $territory->id) }}" 
                               class="btn btn-info btn-sm" 
                               data-toggle="tooltip" 
                               title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.territories.edit', $territory->id) }}" 
                               class="btn btn-warning btn-sm" 
                               data-toggle="tooltip" 
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.territories.destroy', $territory->id) }}" 
                                  style="display: inline;" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="btn btn-danger btn-sm" 
                                        data-toggle="tooltip" 
                                        title="Delete"
                                        onclick="return confirm('Are you sure you want to delete this territory?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            <i class="fas fa-map fa-3x mb-3"></i>
                            <p>No territories found.</p>
                            <a href="{{ route('admin.territories.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First Territory
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if(isset($territories) && $territories->hasPages())
        <div class="pagination-wrapper">
            <div>
                Showing {{ $territories->firstItem() }} to {{ $territories->lastItem() }} of {{ $territories->total() }} results
            </div>
            <div>
                {{ $territories->links() }}
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Search functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>
@endpush

