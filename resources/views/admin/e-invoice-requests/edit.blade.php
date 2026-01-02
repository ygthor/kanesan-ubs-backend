@extends('layouts.admin')

@section('title', 'Edit E-Invoice Request - Kanesan UBS Backend')

@section('page-title', 'Edit E-Invoice Request')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="/dashboard">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.e-invoice-requests.index') }}">E-Invoice Requests</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('card-title', 'Edit E-Invoice Request')

@section('admin-content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.e-invoice-requests.update', $request->id) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label>Invoice No</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="invoice_no" value="{{ old('invoice_no', $request->invoice_no) }}" id="invoice_no">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('invoice_no')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Customer Code</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="customer_code" value="{{ old('customer_code', $request->customer_code) }}" id="customer_code">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('customer_code')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Company / Individual Name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="company_individual_name" value="{{ old('company_individual_name', $request->company_individual_name) }}" id="company_individual_name">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('company_individual_name')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Business Registration Number (Old)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="business_registration_number_old" value="{{ old('business_registration_number_old', $request->business_registration_number_old) }}" id="business_registration_number_old">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('business_registration_number_old')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Business Registration Number (New)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="business_registration_number_new" value="{{ old('business_registration_number_new', $request->business_registration_number_new) }}" id="business_registration_number_new">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('business_registration_number_new')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>TIN Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="tin_number" value="{{ old('tin_number', $request->tin_number) }}" id="tin_number">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('tin_number')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>MSIC Code</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="msic_code" value="{{ old('msic_code', $request->msic_code) }}" id="msic_code">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('msic_code')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Sales & Service Tax (SST)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="sales_service_tax_sst" value="{{ old('sales_service_tax_sst', $request->sales_service_tax_sst) }}" id="sales_service_tax_sst">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('sales_service_tax_sst')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label>Address</label>
                    <div class="d-flex">
                        <textarea class="form-control" name="address" rows="3" id="address" style="flex: 1;">{{ old('address', $request->address) }}</textarea>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="copyToClipboard('address')" title="Copy to clipboard" style="height: fit-content; align-self: flex-start;">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Person In Charge</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="person_in_charge" value="{{ old('person_in_charge', $request->person_in_charge) }}" id="person_in_charge">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('person_in_charge')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Contact</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="contact" value="{{ old('contact', $request->contact) }}" id="contact">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('contact')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Email Address</label>
                    <div class="input-group">
                        <input type="email" class="form-control" name="email_address" value="{{ old('email_address', $request->email_address) }}" id="email_address">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('email_address')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>IC Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="ic_number" value="{{ old('ic_number', $request->ic_number) }}" id="ic_number">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('ic_number')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Passport Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="passport_number" value="{{ old('passport_number', $request->passport_number) }}" id="passport_number">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('passport_number')" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label>Request Date</label>
                    <input type="text" class="form-control" value="{{ $request->created_at->format('Y-m-d H:i:s') }}" readonly>
                </div>
            </div>
        </div>

        <div class="form-group mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Request
            </button>
            <a href="{{ route('admin.e-invoice-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function copyToClipboard(fieldId) {
        const field = document.getElementById(fieldId);
        const value = field.value || field.textContent || field.innerText;
        
        // Use modern Clipboard API if available
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(() => {
                showCopyFeedback(fieldId);
            }).catch(err => {
                // Fallback to execCommand
                fallbackCopy(fieldId, value);
            });
        } else {
            // Fallback to execCommand
            fallbackCopy(fieldId, value);
        }
    }

    function fallbackCopy(fieldId, value) {
        const tempTextarea = document.createElement('textarea');
        tempTextarea.value = value;
        tempTextarea.style.position = 'fixed';
        tempTextarea.style.opacity = '0';
        document.body.appendChild(tempTextarea);
        tempTextarea.select();
        tempTextarea.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            document.execCommand('copy');
            showCopyFeedback(fieldId);
        } catch (err) {
            alert('Failed to copy: ' + err);
        }
        
        document.body.removeChild(tempTextarea);
    }

    function showCopyFeedback(fieldId) {
        const field = document.getElementById(fieldId);
        const inputGroup = field.closest('.input-group');
        const button = inputGroup.querySelector('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 1000);
    }
</script>
@endpush

