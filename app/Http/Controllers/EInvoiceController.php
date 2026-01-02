<?php

namespace App\Http\Controllers;

use App\Models\EInvoiceRequest;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EInvoiceController extends Controller
{
    /**
     * Show the e-invoice request form.
     * Can be accessed with query parameters to pre-fill data.
     */
    public function showForm(Request $request)
    {
        $invoiceNo = $request->query('invoice_no');
        $customerCode = $request->query('customer_code');
        $type = $request->query('type');
        $id = $request->query('id');

        // Pre-fill data from invoice/customer if available
        $prefillData = [];
        if ($invoiceNo || $customerCode || $id) {
            // Try to find order/invoice
            $order = null;
            if ($id) {
                $order = Order::find($id);
            } elseif ($invoiceNo) {
                $order = Order::where('reference_no', $invoiceNo)->first();
            }

            if ($order && $order->customer) {
                $customer = $order->customer;
                $prefillData = [
                    'invoice_no' => $order->reference_no,
                    'customer_code' => $customer->customer_code,
                    'company_individual_name' => $customer->company_name ?? $customer->name,
                    'address' => $customer->address ?? ($customer->address1 . "\n" . ($customer->address2 ?? '') . "\n" . ($customer->address3 ?? '') . "\n" . ($customer->postcode ?? '') . " " . ($customer->state ?? '')),
                    'person_in_charge' => $order->agent_no ?? $customer->agent_no,
                    'contact' => $customer->telephone1 ?? $customer->phone,
                    'email_address' => $customer->email,
                ];
            } elseif ($customerCode) {
                // Try to find customer by code
                $customer = Customer::where('customer_code', $customerCode)->first();
                if ($customer) {
                    $prefillData = [
                        'customer_code' => $customer->customer_code,
                        'company_individual_name' => $customer->company_name ?? $customer->name,
                        'address' => $customer->address ?? ($customer->address1 . "\n" . ($customer->address2 ?? '') . "\n" . ($customer->address3 ?? '') . "\n" . ($customer->postcode ?? '') . " " . ($customer->state ?? '')),
                        'person_in_charge' => $customer->agent_no,
                        'contact' => $customer->telephone1 ?? $customer->phone,
                        'email_address' => $customer->email,
                    ];
                }
            }

            // Override with query parameters if provided
            if ($invoiceNo) {
                $prefillData['invoice_no'] = $invoiceNo;
            }
            if ($customerCode) {
                $prefillData['customer_code'] = $customerCode;
            }
        }

        return view('e-invoice.form', compact('prefillData'));
    }

    /**
     * Submit the e-invoice request form.
     */
    public function submitForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_no' => 'nullable|string|max:255',
            'customer_code' => 'nullable|string|max:255',
            'company_individual_name' => 'nullable|string|max:255',
            'business_registration_number_old' => 'nullable|string|max:255',
            'business_registration_number_new' => 'nullable|string|max:255',
            'tin_number' => 'nullable|string|max:255',
            'msic_code' => 'nullable|string|max:255',
            'sales_service_tax_sst' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'person_in_charge' => 'nullable|string|max:255',
            'contact' => 'required|string|max:255',
            'email_address' => 'required|email|max:255',
            'ic_number' => 'required_without:passport_number|nullable|string|max:255',
            'passport_number' => 'required_without:ic_number|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Find order if invoice_no is provided
        $order = null;
        if ($request->invoice_no) {
            $order = Order::where('reference_no', $request->invoice_no)->first();
        }

        // Create e-invoice request
        $eInvoiceRequest = EInvoiceRequest::create([
            'invoice_no' => $request->invoice_no,
            'customer_code' => $request->customer_code,
            'order_id' => $order ? $order->id : null,
            'company_individual_name' => $request->company_individual_name,
            'business_registration_number_old' => $request->business_registration_number_old,
            'business_registration_number_new' => $request->business_registration_number_new,
            'tin_number' => $request->tin_number,
            'msic_code' => $request->msic_code,
            'sales_service_tax_sst' => $request->sales_service_tax_sst,
            'address' => $request->address,
            'person_in_charge' => $request->person_in_charge,
            'contact' => $request->contact,
            'email_address' => $request->email_address,
            'ic_number' => $request->ic_number,
            'passport_number' => $request->passport_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email notification
        try {
            Mail::send('emails.e-invoice-request', ['request' => $eInvoiceRequest], function ($message) use ($eInvoiceRequest) {
                $message->to(config('app.admin_email'))
                    ->subject('E-Invoice Request - ' . ($eInvoiceRequest->invoice_no ?? 'N/A'));
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send e-invoice request email: ' . $e->getMessage());
        }

        return redirect()->route('e-invoice.form')->with('success', 'E-Invoice request submitted successfully. We will process your request shortly.');
    }

    /**
     * Test email functionality
     */
    public function testEmail()
    {
        try {
            // Create a dummy e-invoice request for testing
            $testRequest = new EInvoiceRequest();
            $testRequest->invoice_no = 'TEST-001';
            $testRequest->customer_code = '3000/001';
            $testRequest->company_individual_name = 'Test Company Sdn Bhd';
            $testRequest->business_registration_number_old = 'TEST-OLD-123';
            $testRequest->business_registration_number_new = 'TEST-NEW-456';
            $testRequest->tin_number = 'TEST-TIN-789';
            $testRequest->msic_code = '46321';
            $testRequest->sales_service_tax_sst = 'TEST-SST';
            $testRequest->address = '123 Test Street, Test City, 12345';
            $testRequest->person_in_charge = 'Test Person';
            $testRequest->contact = '012-3456789';
            $testRequest->email_address = 'test@example.com';
            $testRequest->ic_number = '123456789012';
            $testRequest->passport_number = null;
            $testRequest->created_at = \Carbon\Carbon::now();
            $testRequest->updated_at = \Carbon\Carbon::now();

            Mail::send('emails.e-invoice-request', ['request' => $testRequest], function ($message) {
                $message->to('luckboy5566@gmail.com')
                    ->subject('Test E-Invoice Request Email');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent successfully to luckboy5566@gmail.com'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }
}
