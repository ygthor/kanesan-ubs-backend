@extends('layouts.admin')

@section('admin-content')
    <form method="POST" action="@yield('form-action')" enctype="multipart/form-data">
        @csrf
        @yield('form-method')
        
        <div class="row">
            <div class="col-md-8">
                @yield('form-fields')
            </div>
            <div class="col-md-4">
                @yield('form-sidebar')
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card-footer text-right">
                    <a href="@yield('cancel-url', 'javascript:history.back()')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> @yield('submit-text', 'Save')
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('styles')
    <style>
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .card-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-save form data to localStorage
    $('form').on('input', 'input, select, textarea', function() {
        var formId = $('form').attr('id') || 'form-' + Date.now();
        var formData = {};
        
        $('form').find('input, select, textarea').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (name && value !== undefined) {
                formData[name] = value;
            }
        });
        
        localStorage.setItem('form-' + formId, JSON.stringify(formData));
    });
    
    // Restore form data from localStorage
    var formId = $('form').attr('id') || 'form-' + Date.now();
    var savedData = localStorage.getItem('form-' + formId);
    
    if (savedData) {
        var formData = JSON.parse(savedData);
        Object.keys(formData).forEach(function(key) {
            var field = $('form').find('[name="' + key + '"]');
            if (field.length && !field.val()) {
                field.val(formData[key]);
            }
        });
    }
    
    // Clear localStorage on successful form submission
    $('form').on('submit', function() {
        localStorage.removeItem('form-' + formId);
    });
});
</script>
@endpush
