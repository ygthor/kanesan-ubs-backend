<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EInvoiceRequest;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;

class EInvoiceRequestController extends Controller
{
    /**
     * Check if user is admin or KBS
     */
    private function checkAccess()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. E-Invoice Requests are only available for administrators and KBS users.');
        }
    }

    /**
     * Display a listing of e-invoice requests with filters.
     */
    public function index(Request $request)
    {
        $this->checkAccess();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $invoiceNo = $request->input('invoice_no');
        $customerCode = $request->input('customer_code');
        $customerId = $request->input('customer_id');

        $query = EInvoiceRequest::with(['order', 'customer']);

        // Apply date filter
        if ($fromDate && $toDate) {
            if (strlen($fromDate) == 10) {
                $fromDate .= ' 00:00:00';
            }
            if (strlen($toDate) == 10) {
                $toDate .= ' 23:59:59';
            }
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        // Filter by invoice number
        if ($invoiceNo) {
            $query->where('invoice_no', 'like', "%{$invoiceNo}%");
        }

        // Filter by customer code
        if ($customerCode) {
            $query->where('customer_code', 'like', "%{$customerCode}%");
        }

        // Filter by customer ID
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $query->where('customer_code', $customer->customer_code);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get customers for filter dropdown
        $customers = Customer::orderBy('customer_code', 'asc')->get();

        return view('admin.e-invoice-requests.index', compact('requests', 'fromDate', 'toDate', 'invoiceNo', 'customerCode', 'customerId', 'customers'));
    }

    /**
     * Show the form for editing the specified e-invoice request.
     */
    public function edit($id)
    {
        $this->checkAccess();

        $request = EInvoiceRequest::findOrFail($id);

        return view('admin.e-invoice-requests.edit', compact('request'));
    }

    /**
     * Update the specified e-invoice request in storage.
     */
    public function update(Request $request, $id)
    {
        $this->checkAccess();

        $eInvoiceRequest = EInvoiceRequest::findOrFail($id);

        $validator = \Validator::make($request->all(), [
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
            'contact' => 'nullable|string|max:255',
            'email_address' => 'nullable|email|max:255',
            'ic_number' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $eInvoiceRequest->update($request->all());

        return redirect()->route('admin.e-invoice-requests.index')
            ->with('success', 'E-Invoice request updated successfully.');
    }
}
