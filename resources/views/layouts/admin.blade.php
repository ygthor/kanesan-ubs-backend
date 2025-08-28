@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">@yield('card-title', 'Admin Panel')</h3>
                    <div class="card-tools">
                        @yield('card-tools')
                    </div>
                </div>
                <div class="card-body">
                    @yield('admin-content')
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .action-buttons .btn {
            margin-right: 0.25rem;
        }
        
        .action-buttons .btn:last-child {
            margin-right: 0;
        }
        
        .search-box {
            max-width: 300px;
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        
        .per-page-selector {
            width: auto;
            display: inline-block;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Common admin functionality
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-toggle="popover"]').popover();
            
            // Confirm delete actions
            $('.btn-delete').click(function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
@endpush
