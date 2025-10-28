<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    /**
     * Display a listing of receipts.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $receiptsQuery = Receipt::with('customer:id,customer_code,company_name');
        
        // Debug logging for filter parameters
        \Log::info('Receipt filter parameters:', [
            'customer_id' => $request->input('customer_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'per_page' => $request->input('per_page', 15),
        ]);
        
        // Filter by user's assigned customers (unless KBS user)
        if ($user && !($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            $allowedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            if (empty($allowedCustomerIds)) {
                // User has no assigned customers, return empty result
                return makeResponse(200, 'No receipts accessible.', ['data' => [], 'total' => 0]);
            }
            $receiptsQuery->whereIn('customer_id', $allowedCustomerIds);
        }
        
        // Apply customer filter if provided
        if ($request->has('customer_id') && $request->customer_id) {
            $receiptsQuery->where('customer_id', $request->customer_id);
            \Log::info('Applied customer filter:', ['customer_id' => $request->customer_id]);
        }
        
        // Apply date range filters if provided
        if ($request->has('date_from') && $request->date_from) {
            $receiptsQuery->whereDate('receipt_date', '>=', $request->date_from);
            \Log::info('Applied date_from filter:', ['date_from' => $request->date_from]);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $receiptsQuery->whereDate('receipt_date', '<=', $request->date_to);
            \Log::info('Applied date_to filter:', ['date_to' => $request->date_to]);
        }
        
        $receipts = $receiptsQuery->orderBy('receipt_date', 'desc')
                                 ->paginate($request->input('per_page', 15));

        \Log::info('Receipt query result count:', ['count' => $receipts->count()]);

        return makeResponse(200, 'Receipts retrieved successfully.', $receipts);
    }

    /**
     * Store a newly created receipt in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => 'required|string|max:255|unique:receipts,receipt_no',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'customer_code' => 'required|string|max:255',
            'receipt_date' => 'required|date',
            'payment_type' => 'required|string|max:255',
            'debt_amount' => 'required|numeric|min:0',
            'transaction_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_date' => 'nullable|required_if:payment_type,CHEQUE|date',
            'bank_name' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        // Check if user has access to this customer
        $user = auth()->user();
        $customer = \App\Models\Customer::find($request->customer_id);
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to create receipts for this customer.', null);
        }

        try {
            $receipt = Receipt::create($validator->validated());
            return makeResponse(201, 'Receipt created successfully.', $receipt);
        } catch (\Exception $e) {
            // Log::error('Receipt creation failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to create receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(Receipt $receipt)
    {
        $user = auth()->user();
        
        // Check if user has access to this receipt's customer
        if (!$this->userHasAccessToCustomer($user, $receipt->customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this receipt.', null);
        }
        
        $receipt->load('customer'); // Load related customer
        return makeResponse(200, 'Receipt retrieved successfully.', $receipt);
    }

    /**
     * Update the specified receipt in storage.
     */
    public function update(Request $request, Receipt $receipt)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('receipts')->ignore($receipt->id)],
            'customer_id' => 'sometimes|required|exists:customers,id',
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_code' => 'sometimes|required|string|max:255',
            'receipt_date' => 'sometimes|required|date',
            'payment_type' => 'sometimes|required|string|max:255',
            'debt_amount' => 'sometimes|required|numeric|min:0',
            'transaction_amount' => 'sometimes|required|numeric|min:0',
            'paid_amount' => 'sometimes|required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
            'cheque_date' => 'nullable|required_if:payment_type,CHEQUE|date',
            'bank_name' => 'nullable|required_if:payment_type,CHEQUE|string|max:255',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            $receipt->update($validator->validated());
            return makeResponse(200, 'Receipt updated successfully.', $receipt);
        } catch (\Exception $e) {
            // Log::error('Receipt update failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to update receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified receipt from storage.
     */
    public function destroy(Receipt $receipt)
    {
        try {
            $receipt->delete();
            return makeResponse(200, 'Receipt deleted successfully.', null);
        } catch (\Exception $e) {
            // Log::error('Receipt deletion failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if user has access to a specific customer
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Customer  $customer
     * @return bool
     */
    private function userHasAccessToCustomer($user, \App\Models\Customer $customer)
    {
        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            return true;
        }
        
        // Check if user is assigned to this customer
        if ($user && $user->customers()->where('customers.id', $customer->id)->exists()) {
            return true;
        }
        
        return false;
    }
}
