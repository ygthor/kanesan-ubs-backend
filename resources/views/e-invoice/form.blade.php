<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>E-Invoice Request Form</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px 0;
            font-size: 0.9375rem; /* 15px - reduced from 16px (1rem) */
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: 2px solid #dc3545;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #FEF10E;
            color: #000000;
            font-weight: bold;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .required {
            color: red;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
        }
        .form-control[readonly] {
            background-color: #e9ecef;
        }
        .btn-submit {
            background-color: #FEF10E;
            border: 2px solid #dc3545;
            
            font-weight: bold;
        }
        .btn-submit:hover {
            background-color: #ffd700;
            border-color: #c82333;            
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="text-center mb-4">
                <img src="{{ asset('app_logo.png') }}" alt="Logo" style="max-height: 100px; margin-bottom: 20px;">
                <h1>E-Invoice Request Form</h1>
            </div>
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(isset($existingRequest) && $existingRequest)
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> E-Invoice Already Requested</strong><br>
                    An e-invoice request for invoice <strong>{{ $existingRequest->invoice_no ?? 'N/A' }}</strong> 
                    has already been submitted on {{ $existingRequest->created_at ? $existingRequest->created_at->format('d/m/Y H:i:s') : 'N/A' }}.
                    @if($existingRequest->email_address)
                        <br>Submitted by: <strong>{{ $existingRequest->email_address }}</strong>
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('e-invoice.submit') }}" id="eInvoiceForm">
                @csrf

                <!-- Section 1: Fixed Platform Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Section 1: Platform Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <td style="width: 40%;">Company/Individual Name</td>
                                        <td class="fw-bold">Perkhidmatan dan Jualan Kanesan Bersaudara</td>
                                    </tr>
                                    <tr>
                                        <td>Business Registration Number (Old)</td>
                                        <td class="fw-bold">AS0095186-K</td>
                                    </tr>
                                    <tr>
                                        <td>Business Registration Number (New)</td>
                                        <td class="fw-bold">198803068038</td>
                                    </tr>
                                    <tr>
                                        <td>Tax Identification No (TIN)</td>
                                        <td class="fw-bold">D3837677060</td>
                                    </tr>
                                    <tr>
                                        <td>Business MSIC Code</td>
                                        <td class="fw-bold">46321</td>
                                    </tr>
                                    <tr>
                                        <td>Sales and Service Tax No. (SST)</td>
                                        <td class="fw-bold">NIA</td>
                                    </tr>
                                    <tr>
                                        <td>E-Invoice Implementation Date</td>
                                        <td class="fw-bold">2025</td>
                                    </tr>
                                    <tr>
                                        <td>Correspondence Address</td>
                                        <td class="fw-bold">1456 JALAN BESAR, JAWI LIGHT INDUSTRIAL COMPLEKS, SUNGAI BAKAP,S.P.S, 14200 SUNGAI JAWI,PULAU PINANG.</td>
                                    </tr>
                                    <tr>
                                        <td>E-invoice Person In Charge</td>
                                        <td class="fw-bold">Mr.Kanesan</td>
                                    </tr>
                                    <tr>
                                        <td>Contact Number</td>
                                        <td class="fw-bold">019-5515612</td>
                                    </tr>
                                    <tr>
                                        <td>Email Address</td>
                                        <td class="fw-bold">matakanesan@gmail.com</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 2: User Input Fields -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Section 2: Your Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-1">
                            <label class="form-label">Invoice No</label>
                            <input type="text" class="form-control" name="invoice_no" value="{{ old('invoice_no', $prefillData['invoice_no'] ?? '') }}" readonly>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Customer Code</label>
                            <input type="text" class="form-control" name="customer_code" value="{{ old('customer_code', $prefillData['customer_code'] ?? '') }}" readonly>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Company / Individual Name</label>
                            <input type="text" class="form-control" name="company_individual_name" value="{{ old('company_individual_name', $prefillData['company_individual_name'] ?? '') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Business Registration Number (Old)</label>
                            <input type="text" class="form-control" name="business_registration_number_old" value="{{ old('business_registration_number_old') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Business Registration Number (New)</label>
                            <input type="text" class="form-control" name="business_registration_number_new" value="{{ old('business_registration_number_new') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">TIN Number</label>
                            <input type="text" class="form-control" name="tin_number" value="{{ old('tin_number') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">MSIC Code</label>
                            <input type="text" class="form-control" name="msic_code" value="{{ old('msic_code') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Sales & Service Tax (SST)</label>
                            <input type="text" class="form-control" name="sales_service_tax_sst" value="{{ old('sales_service_tax_sst') }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3">{{ old('address', $prefillData['address'] ?? '') }}</textarea>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Person In Charge</label>
                            <input type="text" class="form-control" name="person_in_charge" value="{{ old('person_in_charge', $prefillData['person_in_charge'] ?? '') }}" readonly>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Contact <span class="required">*</span></label>
                            <input type="text" class="form-control" name="contact" value="{{ old('contact', $prefillData['contact'] ?? '') }}" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Email Address <span class="required">*</span></label>
                            <input type="email" class="form-control" name="email_address" value="{{ old('email_address', $prefillData['email_address'] ?? '') }}" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">IC Number <span class="required">*</span></label>
                            <input type="text" class="form-control" name="ic_number" value="{{ old('ic_number') }}" id="ic_number">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Passport Number <span class="required">*</span></label>
                            <input type="text" class="form-control" name="passport_number" value="{{ old('passport_number') }}" id="passport_number">
                        </div>
                        <small class="text-muted d-block mb-1">* At least one of IC Number or Passport Number is required</small>
                    </div>
                </div>

                <div class="text-center mb-4">
                    <button type="submit" class="btn btn-submit btn-lg" id="submitBtn">
                        <span id="submitText"><i class="fas fa-paper-plane"></i> Submit Request</span>
                        <span id="submitLoading" style="display: none;">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isSubmitting = false;
        
        document.getElementById('eInvoiceForm').addEventListener('submit', function(e) {
            // Prevent double submit
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            // Validate that at least one of IC Number or Passport Number is filled
            const icNumber = document.getElementById('ic_number').value.trim();
            const passportNumber = document.getElementById('passport_number').value.trim();
            
            if (!icNumber && !passportNumber) {
                e.preventDefault();
                alert('Please fill in either IC Number or Passport Number');
                return false;
            }
            
            // Show loading and disable button
            isSubmitting = true;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoading = document.getElementById('submitLoading');
            
            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline';
        });
    </script>
</body>
</html>

