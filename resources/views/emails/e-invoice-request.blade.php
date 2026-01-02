<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table td {
            padding: 10px;
            border: 1px solid #dee2e6;
        }
        table td:first-child {
            font-weight: bold;
            background-color: #f8f9fa;
            width: 40%;
        }
        .footer {
            margin-top: 20px;
            padding: 10px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="{{ url('app_logo.png') }}" alt="Logo" style="max-height: 100px;">
        </div>
        
        <div class="header">
            <h2>E-Invoice Request</h2>
        </div>
        
        <div class="content">
            <p>Dear admin,</p>
            
            <p>Here is the e-invoice request for invoice no <strong>{{ $request->invoice_no ?? 'N/A' }}</strong>.</p>
            
            <table>
                <tr>
                    <td>Invoice No</td>
                    <td>{{ $request->invoice_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Customer Code</td>
                    <td>{{ $request->customer_code ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Company / Individual Name</td>
                    <td>{{ $request->company_individual_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Business Registration Number (Old)</td>
                    <td>{{ $request->business_registration_number_old ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Business Registration Number (New)</td>
                    <td>{{ $request->business_registration_number_new ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>TIN Number</td>
                    <td>{{ $request->tin_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>MSIC Code</td>
                    <td>{{ $request->msic_code ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Sales & Service Tax (SST)</td>
                    <td>{{ $request->sales_service_tax_sst ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Address</td>
                    <td>{{ $request->address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Person In Charge</td>
                    <td>{{ $request->person_in_charge ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Contact</td>
                    <td>{{ $request->contact ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Email Address</td>
                    <td>{{ $request->email_address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>IC Number</td>
                    <td>{{ $request->ic_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Passport Number</td>
                    <td>{{ $request->passport_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Request Date</td>
                    <td>{{ $request->created_at ? $request->created_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <p>This is an automated email from the E-Invoice Request System.</p>
        </div>
    </div>
</body>
</html>
